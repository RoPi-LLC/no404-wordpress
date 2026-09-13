=== no404 – Auto 404 Redirect ===
Contributors: ropillc
Tags: 404, redirect, 301, seo, broken links
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically redirect 404s to the closest matching live URL on your site, with a real server-side 301. No manual redirect rules.

== Description ==

When you delete a product, change a permalink or migrate a site, the old URLs keep getting traffic. Visitors land on an empty 404 page, and the traffic and the signals those old links carry go nowhere.

Most redirect plugins ask you to fix this by hand: you write a rule for every broken URL. That does not scale past a few dozen.

**no404 works the other way around.** It keeps a synchronised index of the URLs your site actually has, and when a request 404s it finds the closest match in that index automatically. You write no rules.

= How it is different =

* **No manual rules.** Other plugins need `/old-url` → `/new-url` written out one at a time. no404 matches against your live catalogue, so a deleted product URL finds its closest surviving equivalent on its own.
* **A real 301, server side.** JavaScript based solutions leave the HTTP status at 404, so Google still sees a dead page and bots that do not run JavaScript are never redirected. This plugin redirects before any HTML is sent.
* **It defers to your existing setup.** no404 runs late on `template_redirect`, so Yoast SEO, Rank Math and Redirection get to act first. It is a last resort, not a competitor to your redirect table.

= Your site never waits =

If the service is slow or unreachable, the plugin steps aside and your theme's own 404 page renders normally. Requests time out after 1.5 seconds, and after a failure the plugin stops calling out entirely for a short while instead of adding that delay to every 404.

= 301 or 302? =

You decide, in your no404 dashboard (site Settings → "Permanent (301) or temporary (302)?"). Because 301s may be cached aggressively by browsers and search engines and can be hard to reverse quickly, the default is cautious:

* Redirects you defined by hand in your no404 dashboard → **301**
* Catalogue matches at or above your 301 threshold (0.5 by default; you can pick 0.7, 0.35, a custom value, or "only manual redirects get a 301") → **301**
* Lower confidence matches and fallbacks → **302**

The no404 API sends this decision with every result and the plugin follows it, so changing the setting needs no plugin update. You can still force everything to 301 in the plugin settings, but it is off by default.

= Quota friendly by design =

Results are cached locally for an hour, including "no match found". A bot hammering the same dead URL two hundred times a day costs you one lookup, not two hundred. Static files (.css, .js, .png and so on) and admin paths such as /wp-admin and /wp-json are never sent to the service at all.

= WooCommerce =

Deleted product URLs are the main thing this plugin was built for. It does not interfere with WooCommerce permalink structures.

= Account required =

The matching engine runs as a hosted service, so the plugin needs a free no404 account and an API key. The free plan covers 1,000 lookups per month. See the External services section below for exactly what is transmitted.

= Languages =

The plugin is written in English and ships with translations for Turkish, German, French, Spanish, Russian, Hindi and Arabic (right-to-left). It follows the language of your WordPress installation, so there is nothing to configure.

== External services ==

This plugin relies on the no404 service to work out where a broken URL should be redirected. The matching is done remotely, against an index of your site built from your sitemap, so the plugin cannot perform it on its own.

**When data is sent**

A request is sent when a visitor hits a 404 page, and only then. Requests are not sent when:

* the result for that path is already in the local cache (one hour by default),
* the path is a static file or an administrative path (see the exclusion list in the settings),
* the request is not a GET or HEAD request,
* the service recently failed or the request limit was reached.

Two more requests are made only when you ask for them in the admin: the connection test (a lookup like any other, listed in your dashboard) and, when you connect the plugin in the setup wizard, a check of which no404 site the API key belongs to. That check sends only the API key, is not recorded as a 404 and does not use quota.

The setup wizard's "Create a free account" button opens no404's sign-up page with your site's domain in the address, so the right site is preselected there. Nothing is sent until you click it.

**What is sent**

* The path that returned 404, for example `/old-product`. Query strings are stripped before sending.
* The referring URL, taken from the HTTP `Referer` header, when the browser supplies one.
* The visitor's IP address truncated to its network: the last part of an IPv4 address is set to zero (203.0.113.45 becomes 203.0.113.0), and only the first 48 bits of an IPv6 address are kept. Private and local addresses are not sent. This is what lets your dashboard tell bots from people and group requests by network.
* A pseudonymous visitor ID: a keyed hash (HMAC-SHA256) of the visitor's IP address, computed on your server with a secret derived from your site's own security keys. no404 never receives the secret, so it cannot turn the ID back into an address or recognise the same visitor on another site. It only lets your dashboard count unique visitors.
* The visitor's browser user agent (for example `Mozilla/5.0 (iPhone; …)`), used to classify the request as a bot or a browser and by device type.
* The visitor's country code, only when your site is behind Cloudflare and it supplies one (`CF-IPCountry`).
* Your site's home URL and the plugin version, sent in the User-Agent header to identify the installation.
* Your API key, which identifies your account.

**What is not sent**

The plugin never transmits the visitor's full IP address, cookies, session data, form contents, the query string or click IDs. Developers can change or remove the visitor data with the `no404_visitor` filter.

**Service provider**

The service is operated by no404, https://no404.tr

* Terms of Service: https://no404.tr/terms
* Privacy Policy: https://no404.tr/privacy

== Installation ==

1. Install and activate the plugin. A short setup wizard opens.
2. No account yet? The wizard's "Create a free account" button opens no404 with your site's address filled in. Add your site there through Google Search Console.
3. Open your site in the no404 dashboard, go to Integration, copy the API key and paste it into the wizard. The wizard checks that the key belongs to this site before it saves it.
4. Choose how redirects are sent, then try an old address on the last step.

You can run the wizard again from Settings → no404, or skip it and paste the key on that page directly. The API key is the only value you need to enter; the service address field is already filled in correctly.

The API key is used server side only and never appears in your site's HTML.

== Frequently Asked Questions ==

= Do I need an account? =

Yes. The matching engine is a hosted service, so the plugin needs an API key. There is a free plan that covers 1,000 lookups per month, and local caching means a lookup is not the same as a page view.

= What happens if the no404 service goes down? =

Nothing breaks. The plugin fails open: if a request times out or errors, no redirect happens and your theme's 404 page is shown as usual. The wait is capped by the timeout you configure, 1.5 seconds by default, and only ever applies to 404 pages. After a failure the plugin pauses outgoing requests for a while so a service outage does not add that delay to every 404 on your site.

= Does every 404 use up part of my quota? =

No. Results are cached for an hour by default, so the same URL costs at most one lookup per hour. Results with no match are cached too. Static files and admin paths are never sent. The one exception is a visit from an ad click: it is always looked up, so paid traffic that lands on a 404 shows up in your dashboard.

= Does the plugin send my visitors' click IDs to no404? =

No. The query string never leaves your site. When a visit carries an ad click ID (gclid, msclkid) or a paid `utm_medium`, the plugin sends only the ad network — google, microsoft, meta or other.

= Does the plugin send my visitors' IP addresses? =

Only a truncated one. Since 1.0.3 the plugin sends the network part of the visitor's IP (203.0.113.0 rather than 203.0.113.45), a pseudonymous visitor ID (a hash of the address keyed with a secret only your site knows) and the browser's user agent, so the dashboard can separate bots from people and count unique visitors. The full address never leaves your site. Before 1.0.3 every 404 was recorded under your own server's address. You can change or remove this data with the `no404_visitor` filter.

= It redirected someone to the wrong page. How do I fix that? =

Add a manual redirect for that URL in your no404 dashboard. Manual redirects always take priority over automatic matches. Note that if the redirect was sent as a 301, browsers may have cached it, which is why speculative matches are sent as 302 by default.

= Will it conflict with Yoast SEO, Rank Math or Redirection? =

No. The plugin hooks into `template_redirect` at a very low priority, so those plugins resolve their own redirect tables first. no404 only acts on 404s that nothing else handled.

= Does it work on multisite? =

Yes. Each site keeps its own API key, its own settings and its own cache.

= How can I tell that a redirect came from this plugin? =

Redirected responses carry an `X-Redirect-By: no404` header. Every request is also listed in the 404 Events tab of your no404 dashboard.

= Can I exclude certain URLs? =

Yes. Settings → no404 has an "Excluded paths" field. Enter one path prefix per line. Static files and admin paths are excluded automatically.

= Does the plugin slow down my site? =

Normal pages are untouched, because the plugin only runs when WordPress has already decided the request is a 404. On a 404, a cached result adds no network wait at all.

= What language does the plugin use? =

It follows your WordPress language setting. English is the default, and translations are bundled for Turkish, German, French, Spanish, Russian, Hindi and Arabic. If your language is not among them, the plugin falls back to English rather than showing untranslated placeholders.

== Screenshots ==

1. Turn 404 errors into relevant redirects automatically with real server-side 301/302 redirects.
2. Automatically match broken URLs with the closest relevant live pages without creating redirect rules one by one.
3. Set up no404 in minutes by installing the plugin, adding your API key, and testing the connection.
4. Protect SEO and paid traffic by redirecting visitors from broken landing pages, search results, and old backlinks.
5. Built for WooCommerce stores and changing catalogs, including deleted products, renamed URLs, seasonal pages, and migrations.

== Changelog ==

= 1.1.1 =
* Changed: the wizard's button to your no404 dashboard now opens the new dashboard address directly. The old address still redirects there, so nothing breaks on 1.1.0.

= 1.1.0 =
* New: a setup wizard opens once after activation. Create an account, paste your API key, choose how redirects are sent and try an old address, in four short steps. It does not open on bulk or network activation, for sites that already have a key, or ever again once you finish or skip it. You can rerun it from Settings → no404.
* New: the wizard checks that the API key belongs to this site. A key copied from another site in the same no404 account used to pass the connection test and then silently redirect nothing, because every target it returned was on the other domain.
* New: until the plugin is connected, the Plugins screen shows a notice with a link to the wizard. Dismissing it counts as skipping the wizard.

= 1.0.3 =
* Fixed: every 404 in the no404 dashboard showed your own server's IP address and the plugin's user agent, because the lookup is made from your server. The plugin now forwards the visitor's user agent and truncated IP address in separate headers, so the dashboard's bot/browser/device breakdown and visitor counts work for WordPress sites too.
* Privacy: the full IP address never leaves your site. Only its network is sent (the last part of an IPv4 address set to zero, the first 48 bits of an IPv6 address), plus a pseudonymous visitor ID keyed with a secret only your site knows, for unique-visitor counts. Private addresses are not sent. See the External services section.
* New: the `no404_visitor` filter to change or remove the visitor data.

= 1.0.2 =
* New: the redirect status (301 or 302) now follows the threshold you choose in your no404 dashboard (site Settings → "Permanent (301) or temporary (302)?"). The no404 API sends its decision with every result and the plugin uses it, so changing the setting takes effect without a plugin update.
* With older API versions that don't send a decision, the plugin keeps its built-in rule: manual redirects and catalog matches scoring 0.5 or higher get a 301, everything else a 302.
* The "Send every redirect as a 301" setting still takes precedence over everything.
* New: the API key now travels in an `Authorization: Bearer` header instead of the request address, so it no longer appears in web server, proxy or CDN logs.
* New: ad measurement. When a visitor who hits a 404 came from a Google, Microsoft or Meta ad (gclid, msclkid or a paid `utm_medium`), the plugin tells no404 the ad network — only the category, never the click ID. These visits skip the local cache, so paid traffic that lands on a 404 is counted in full in your dashboard.

= 1.0.1 =
* Fixed: the connection test reported "unexpected response (HTTP 302)" on a fresh install. The bundled address, https://no404.tr, redirects to the www host, and the plugin did not follow it. The default is now https://www.no404.tr, sites upgrading from 1.0.0 are moved across automatically, and a redirect the plugin cannot follow now names the address to use instead of reporting an unexpected response.

= 1.0.0 =
* Initial release.
* Server side 301/302 redirects on `template_redirect`.
* Local caching, including negative results, with a circuit breaker for outages.
* Exclusion list for static files and administrative paths.
* Open redirect and redirect loop protection.
* Connection test that reports invalid key, inactive subscription and quota errors separately.
* Multisite support.

== Upgrade Notice ==

= 1.1.1 =
Small fix: the setup wizard links straight to the new no404 dashboard address.

= 1.1.0 =
Adds a one-time setup wizard and catches API keys that belong to a different site. Sites that are already connected see no change.

= 1.0.3 =
The no404 dashboard stops showing your server's IP for every 404: the visitor's user agent and truncated IP address are now forwarded (never the full IP). Recommended for everyone.

= 1.0.2 =
The 301/302 decision now follows the threshold you set in your no404 dashboard, the API key moves out of the request address into a header, and 404s from ad clicks are measured. Recommended for everyone.

= 1.0.1 =
Fixes the connection test failing with "unexpected response (HTTP 302)" on a fresh install. Recommended for everyone.

= 1.0.0 =
Initial release.
