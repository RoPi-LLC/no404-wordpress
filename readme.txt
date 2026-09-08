=== no404 – Auto 404 Redirect ===
Contributors: ropillc
Tags: 404, redirect, 301, seo, broken links
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically redirect 404s to the closest matching live URL on your site, with a real server-side 301. No manual redirect rules.

== Description ==

When you delete a product, change a permalink or migrate a site, the old URLs keep getting traffic. Visitors land on an empty 404 page and Google throws away the link equity those URLs had earned.

Most redirect plugins ask you to fix this by hand: you write a rule for every broken URL. That does not scale past a few dozen.

**no404 works the other way around.** It keeps a synchronised index of the URLs your site actually has, and when a request 404s it finds the closest match in that index automatically. You write no rules.

= How it is different =

* **No manual rules.** Other plugins need `/old-url` → `/new-url` written out one at a time. no404 matches against your live catalogue, so a deleted product URL finds its closest surviving equivalent on its own.
* **A real 301, server side.** JavaScript based solutions leave the HTTP status at 404, so Google still sees a dead page and bots that do not run JavaScript are never redirected. This plugin redirects before any HTML is sent.
* **It defers to your existing setup.** no404 runs late on `template_redirect`, so Yoast SEO, Rank Math and Redirection get to act first. It is a last resort, not a competitor to your redirect table.

= Your site never waits =

If the service is slow or unreachable, the plugin steps aside and your theme's own 404 page renders normally. Requests time out after 1.5 seconds, and after a failure the plugin stops calling out entirely for a short while instead of adding that delay to every 404.

= 301 or 302? =

The default is deliberately cautious, because a 301 is cached permanently by browsers and by Google and cannot be taken back:

* Redirects you defined by hand in your no404 dashboard → **301**
* High confidence catalogue matches (score 0.5 and above) → **301**
* Lower confidence matches and fallbacks → **302**

You can force everything to 301 in the settings, but it is off by default.

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

**What is sent**

* The path that returned 404, for example `/old-product`. Query strings are stripped before sending.
* The referring URL, taken from the HTTP `Referer` header, when the browser supplies one.
* Your site's home URL and the plugin version, sent in the User-Agent header to identify the installation.
* Your API key, which identifies your account.

**What is not sent**

The plugin does not transmit the visitor's IP address, the visitor's user agent, cookies, session data, form contents, or any other personal data.

**Service provider**

The service is operated by no404, https://no404.tr

* Terms of Service: https://no404.tr/terms
* Privacy Policy: https://no404.tr/privacy

== Installation ==

1. Install and activate the plugin.
2. Create an account at https://no404.tr and add your site through Google Search Console.
3. Open your site in the no404 dashboard, go to Integration, and copy the API key.
4. In WordPress, go to Settings → no404, paste the key and save.
5. Click "Test the connection" to confirm everything works.

The API key is the only value you need to enter. The service address field is already filled in correctly.

The API key is used server side only and never appears in your site's HTML.

== Frequently Asked Questions ==

= Do I need an account? =

Yes. The matching engine is a hosted service, so the plugin needs an API key. There is a free plan that covers 1,000 lookups per month, and local caching means a lookup is not the same as a page view.

= What happens if the no404 service goes down? =

Nothing breaks. The plugin fails open: if a request times out or errors, no redirect happens and your theme's 404 page is shown as usual. The wait is capped by the timeout you configure, 1.5 seconds by default, and only ever applies to 404 pages. After a failure the plugin pauses outgoing requests for a while so a service outage does not add that delay to every 404 on your site.

= Does every 404 use up part of my quota? =

No. Results are cached for an hour by default, so the same URL costs at most one lookup per hour. Results with no match are cached too. Static files and admin paths are never sent.

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

1. Settings → no404: connection and behaviour settings.
2. The connection test reporting a successful lookup.
3. Excluded paths and cache configuration.

== Changelog ==

= 1.0.0 =
* Initial release.
* Server side 301/302 redirects on `template_redirect`.
* Local caching, including negative results, with a circuit breaker for outages.
* Exclusion list for static files and administrative paths.
* Open redirect and redirect loop protection.
* Connection test that reports invalid key, inactive subscription and quota errors separately.
* Multisite support.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
