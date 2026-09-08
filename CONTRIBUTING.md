# Developer guide — no404 WordPress plugin

This file is for people working **inside** the plugin: architecture, the behaviour contract, tests, the translation workflow and packaging.

* User-facing description: [README.md](README.md)
* WordPress.org listing copy: [readme.txt](readme.txt)

> **Everything in this repository is written in English** — code, comments, documentation and commit messages. The plugin's own user-facing strings are English too; see [Translations](#translations) for why that is not negotiable.

---

> **Note:** "why a plugin", the 301/302 decision, quota protection, security and hooks are also described — in user-facing terms — in [README.md](README.md). This file gives the same answers **with their rationale**. If the two ever disagree, **the code wins**, then this file, then the README.

## Why a plugin (why a snippet is not enough)

|  | JS snippet | This plugin |
| --- | --- | --- |
| Redirect type | `location.replace` — the HTTP status **stays 404** | A real **301/302** |
| SEO | Google sees a 404; link equity is lost | Link equity passes to the target |
| Crawlers | Bots that do not run JS are never redirected | Redirected |
| Setup | Paste code into the theme by hand | Install the plugin, enter the key |
| API key | **Visible** in the page source | Server-side only |

The reason this plugin exists is the **301**. If it were not done server-side, there would be no point writing it.

---

## Architecture

```
no404.php                       bootstrap: constants, wiring, multisite context
includes/
  interface-no404-http.php      HTTP transport contract
  interface-no404-cache.php     cache contract
  class-no404-client.php        CORE — the behaviour contract (PLAIN PHP)
  class-no404-wp-http.php       wp_remote_get adapter
  class-no404-wp-cache.php      transient adapter
  class-no404-options.php       option store: defaults, sanitising, masking
  class-no404-redirector.php    the template_redirect hook
  class-no404-admin.php         Settings → no404 + connection test (AJAX)
assets/admin.js                 connection test (no dependencies)
languages/                      .pot + 7 locales (-tr_TR/-de_DE/-fr_FR/-es_ES/-ru_RU/-hi_IN/-ar)
tests/test-core.php             core behaviour tests (no WordPress required)
uninstall.php                   option + transient cleanup (multisite included)
```

### `No404_Client` — read before you touch it

`includes/class-no404-client.php` is **not tied to the WordPress API**: HTTP and caching sit behind interfaces (`No404_Http_Interface`, `No404_Cache_Interface`), and the WordPress implementations live in `class-no404-wp-*.php`. **Do not add `wp_*` calls here** — anything platform-specific belongs in an adapter.

The payoff is `tests/test-core.php`: matching, caching, quota protection and the 301/302 decision are all exercised with fake HTTP/cache adapters, without WordPress ever being loaded. The single exception is `wp_parse_url`, and the test defines a one-line stub for it. Every `wp_*` call added to the core will either break that test or demand another stub — that is how we notice the boundary moving.

Core responsibilities:

| Responsibility | Method |
| --- | --- |
| Request + short timeout (fail-open) | `resolve()` |
| Local cache, negatives included | `resolve()` + `cache_key()` |
| Circuit breaker (when the API is down) | `handle_response()` |
| Never asking about static/admin paths | `is_ignored_path()` |
| The 301/302 decision | `decide_status()` |
| Open-redirect and loop protection | `validate_target()` |
| Path canonicalisation, identical to the server's | `normalize_path()` |
| Settings-screen diagnostics | `ping()` |

---

## The behaviour contract

### Fail-open — not up for negotiation

If no404 is slow, down, or returns something malformed, `resolve()` returns **`null`** and the store renders its own 404 page as usual. `resolve()` never throws under any circumstance (`Throwable` is caught as well).

There is also a **circuit breaker**: after a transport error or a 5xx no request is made for 60 seconds; after a 429 (quota/limit) or a 403/404 (configuration) that becomes 300 seconds. This keeps an outage on our side from putting a timeout cost on every single 404 the store serves.

### Quota protection

Plan limits are based on **monthly event count**, which means this is the customer's bill.

* Results are cached for **one hour** by default (`set_transient`).
* **Negative results are cached too** — otherwise an address with no match would burn quota on every single request.
* **The query string is discarded.** The server only takes the `pathname` anyway; keeping `?utm_source=…` would turn one page into dozens of separate cache entries and dozens of billed events.
* Blacklisted paths (static extensions, `/wp-admin`, `/wp-json` …) are **never** sent to the API.

Cache invalidation uses a **generation counter** (the `no404_cache_generation` option is incremented) rather than `DELETE ... LIKE`: on a site running Redis or Memcached the transients are not in the database at all, and a LIKE query would delete nothing.

### The 301 / 302 decision

| Source | Score | Status |
| --- | --- | --- |
| `REDIRECT` (defined by hand) | — | **301** |
| `CATALOG` | ≥ 0.5 | **301** |
| `CATALOG` | < 0.5 | 302 |
| `FALLBACK` | — | 302 |

A 301 is cached permanently by browsers and by Google; issuing one for a speculative match cannot be taken back, even if the catalogue is corrected later. The "send every match as a 301" setting is therefore **off by default**.

### Security

* **The API key is never printed to the page.** The settings field is a `password` input, and after saving only a mask is shown (`••••••••` + the last 4 characters). If the field is submitted empty or still masked, the stored key is kept.
* **Open-redirect protection:** the target host must be on the allow-list (the hosts of `home_url` and `site_url`, including www / non-www variants). It is additionally run through WordPress's own `wp_validate_redirect()`. `//evil.com`, `javascript:` and `data:` are rejected.
* **Header injection:** a target containing control characters is rejected.
* **Loop protection:** if the normalised target equals the current path, no redirect happens. There are no chains — one redirect per request, then `exit`.
* Server-to-server calls do not carry an `Origin` header, so no404's origin lock never engages. The only things protecting the key are **its secrecy** and the rate limit.

### Ordering against other plugins

`template_redirect` priority is **9999**. The redirect tables of Yoast SEO, Rank Math and Redirection run first; if one of them finds a match and redirects, this code is never reached. no404 is the **last resort**.

### Multisite

Because `get_option` / `set_transient` operate in the blog context, each site uses its own key and cache automatically. `No404_Plugin::client()` caches the client per blog ID and rebuilds it after `switch_to_blog()`. `uninstall.php` walks every site.

---

## Hooks (filters)

| Filter | Purpose |
| --- | --- |
| `no404_skip_request` | `( bool $skip, string $path )` — do not ask about this request at all. |
| `no404_redirect_target` | `( string $target, int $status, array $result, string $path )` |
| `no404_redirect_status` | `( int $status, array $result, string $path )` |
| `no404_allowed_hosts` | `( string[] $hosts )` — the open-redirect allow-list. |
| `no404_client_config` | `( array $config )` — core client configuration. |

---

## Tests

```bash
php tests/test-core.php        # core — 51 checks
php tests/test-wordpress.php   # integration — 48 checks
```

Neither requires a WordPress installation, and `bin/build.sh` runs both automatically.

`test-core.php` drives the core with fake HTTP/cache adapters: path canonicalisation, the blacklist, the 301/302 decision, open-redirect and loop rejection, cache hits (quota), negative caching, fail-open, the circuit breaker, and malformed responses.

`test-wordpress.php` loads the plugin against **stubbed WordPress functions** and runs a 404 request end to end: hook registration and priority, real redirect status codes, `X-Redirect-By`, static-file skipping, cache/quota, the circuit breaker, POST skipping, option cleanup and key masking. It also catches calls to undefined or misspelled WordPress functions.

> Two traps to keep in mind when changing the tests: (1) the redirector checks `headers_sent()`, which is why test output is buffered — remove the buffer and every redirect scenario silently becomes a no-op. (2) The circuit breaker leaks from one scenario into the next, which is why `simulate()` clears the cache by default. Skip that and the tests will "pass" while verifying nothing.

### Two things that must be verified by hand

These cannot be tested; a human has to look:

1. **That fail-open really works.** Point `api_base` at an unreachable address and open a 404 page: the page must render normally, and the delay must not exceed the timeout you configured.
2. **WooCommerce and SEO plugin conflicts.** Take a deleted product URL on a site running Yoast / Rank Math / Redirection and confirm the ordering.

---

## Name, slug and text domain — they are COUPLED

| | Value |
| --- | --- |
| Plugin Name | `no404 – Auto 404 Redirect` |
| WordPress.org slug | `no404-auto-404-redirect` (**derived from the name**) |
| Text Domain | `no404-auto-404-redirect` (**MUST equal the slug**) |

The slug is derived automatically from the plugin name; if the text domain diverges from it, translate.wordpress.org will **never load a translation, and will do so silently**. `bin/build.sh` reads the name, derives the slug and compares it against the text domain, stopping the build on a mismatch.

Once the name is approved it **cannot be changed** on WordPress.org.

There are two places where the string `'no404'` is **not** a text domain — leave them alone: `No404_Admin::PAGE_SLUG` (the settings page URL) and `wp_safe_redirect( …, 'no404' )` (the `X-Redirect-By` header value).

## Translations

**The source language is English** — including the setup and settings screens, it is what appears wherever no translation is found. WordPress.org (GlotPress) generates translations *from* English; if the source were in any other language, the directory's translation infrastructure would not work at all.

Locales shipped with the package (`bin/locales.php` is the SINGLE source of this list):

| Locale  | Language                | Locale  | Language        |
|---------|-------------------------|---------|-----------------|
| `tr_TR` | Türkçe                  | `ru_RU` | Русский         |
| `de_DE` | Deutsch (informal, *du*) | `hi_IN` | हिन्दी            |
| `fr_FR` | Français (formal, *vous*) | `ar`  | العربية (RTL)   |
| `es_ES` | Español (informal, *tú*) |         |                 |

```bash
php bin/i18n.php      # builds the .pot plus .po/.mo per locale, then reads the .mo files back to verify them
```

Files under `languages/` are **generated; never edit them by hand.** The translations themselves live in `bin/translations/<locale>.php` (English → target language). The script stops with an error — rather than quietly emitting an incomplete catalogue — in any of these cases:

- a string exists in the code but not in a catalogue,
- an entry is left in a catalogue but no longer exists in the code,
- an empty `msgstr` (WordPress would silently fall back to English),
- a `%s` / `%1$s` / `%d` placeholder that does not match the source (`sprintf` would break).

`bin/build.sh` runs this step itself and verifies that every locale in `bin/locales.php` has its `.po`/`.mo` in the package.

**Adding a string:** write the English in the code, add its translation to **every** file under `bin/translations/`, then run `php bin/i18n.php`.
**Adding a locale:** put the locale code and its `Plural-Forms` definition in `bin/locales.php`, then create `bin/translations/<locale>.php`.

> The locale code must match the one WordPress uses, exactly. Arabic is `ar`; there is no such thing as `ar_AR`, and a `.mo` built with an invented code will never load.

### Why there is no `load_plugin_textdomain()`

Plugin Check flags that call: since WordPress 4.6 translations load by themselves on the first `__()` call. **But automatic loading only looks in `WP_LANG_DIR/plugins/`** — that is, at files *downloaded* from translate.wordpress.org. The plugin's own `languages/` folder is not on that list (`WP_Textdomain_Registry::get_paths_for_domain`); the only thing that adds a path there is `load_plugin_textdomain()`.

So if we had simply deleted the call and done nothing else, **none of the seven bundled `.mo` files would ever load** and the interface would appear in English in every language — with no warning of any kind. That is why `No404_Plugin::register_translations_path()` registers the path directly (`set_custom_path`, WordPress 6.1+). The registry checks `WP_LANG_DIR/plugins/` first and falls through to ours, so a newer translation downloaded from wp.org always overrides the bundled copy — which is the order we want.

`tests/test-wordpress.php` asserts both that the path is registered and that **no** `load_plugin_textdomain()` call remains in the source.

## WordPress.org submission

**Status: submitted, review in progress.** The directory page opens once it is approved: `https://wordpress.org/plugins/no404-auto-404-redirect/`

The audit against directory rules, the outstanding items and all submission copy live in `_internal/WORDPRESS-ORG-SUBMISSION.md`.

> **`_internal/` is never committed.** This repository is public; submission notes, account details and test credentials go there and are kept out by `.gitignore`. When writing a new internal note, put it there — not in the README.

---

## Packaging

```bash
bash bin/build.sh     # → dist/no404-auto-404-redirect-<version>.zip
```

`tests/`, `bin/`, `screen/`, `README.md`, `CONTRIBUTING.md`, `_internal/` and `.git` are excluded from the package.

---

## Releases

This plugin lives in **its own git repository**; it is not placed in the no404 monorepo. The reason: its release cycle and distribution channel (the WordPress.org SVN) are separate.

As long as the `/api/v1/resolve` contract does not change, nothing needs to change on the no404 side.

## License

GPL-2.0-or-later.
