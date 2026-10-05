=== GioCookies ===
Contributors: giovannibevacqua
Tags: cookie consent, gdpr, consent mode, cookie banner, privacy
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lightweight cookie consent banner: Google Consent Mode v2, GTM dataLayer event, WP Consent API, script blocking and an anonymized consent log.

== Description ==

GioCookies is a small cookie consent banner for sites that manage their tags with Google Tag Manager (or by hand) and want a clean, predictable consent layer without an external service.

It does a few things and tries to do them well:

* **Google Consent Mode v2.** Prints `gtag('consent', 'default', …)` at the very top of `<head>`, before your GTM snippet, with `analytics_storage`, `ad_storage`, `ad_user_data` and `ad_personalization` set from the visitor's stored choice (denied until they decide). On every decision it sends `gtag('consent', 'update', …)`.
* **GTM dataLayer event.** On every decision it pushes `{ event: 'cookie_consent_update', cookie_consent: { necessary, analytics, marketing } }`, ready to be used as a trigger.
* **WP Consent API.** When the [WP Consent API](https://wordpress.org/plugins/wp-consent-api/) plugin is active, GioCookies registers as a compliant consent plugin, sets the consent type to opt-in and shares the visitor's choices with every plugin that supports the API.
* **Script blocking.** Scripts that are not managed by GTM can be held back until the visitor consents, both hard-coded scripts and enqueued script handles.
* **Anonymized consent log.** Optionally records each choice with a random consent ID, the choices made, the policy version and an anonymized IP address. Daily automatic cleanup, paginated admin screen and CSV export.
* **Re-ask consent.** Change the consent version in the settings and visitors are asked again.
* **Accessible.** Dialog semantics, keyboard support, visible focus, native switches (`role="switch"`), reduced-motion support. "Reject all" and "Accept all" have the same visual weight.
* **Lightweight.** One small CSS file and one small dependency-free JavaScript file. No external requests, no tracking, no account, no upsell.
* **Easy to theme.** Neutral styles driven by CSS custom properties (`--giocookies-*`), plus accent and button text colors in the settings.

= What GioCookies does not do =

Please read this before relying on the plugin:

* It **does not provide legal advice** and **does not guarantee compliance** with the GDPR, the ePrivacy Directive or any other law. You are responsible for configuring your site, your tags and your policies correctly.
* It **does not scan** your site for cookies and does not generate a cookie list or a cookie policy.
* It is **not an IAB TCF CMP** and is **not a Google-certified CMP**. If you serve personalized ads through Google AdSense, Ad Manager or AdMob to visitors in the EEA, the UK or Switzerland, Google requires a certified CMP.
* It does not block anything by itself: tags in GTM must respect Consent Mode or use the `cookie_consent_update` event, and other scripts must be marked as described below.

= Banner categories =

* **Necessary** – always active.
* **Analytics** – maps to `analytics_storage` and to the WP Consent API categories `statistics` and `statistics-anonymous`.
* **Marketing** – maps to `ad_storage`, `ad_user_data`, `ad_personalization` and to the WP Consent API category `marketing`.

Analytics and Marketing can be turned off in **Settings → Categories** when a site does not use them: they disappear from the banner, stay denied in Consent Mode, and "Accept all" grants only the categories that are on. Category names and descriptions can be renamed in **Banner texts**.

The WP Consent API `functional` category is always allowed, `preferences` is always denied (GioCookies has no preferences category).

= Cookies set by GioCookies =

* `giocookies_consent` – the decision type: `accepted`, `declined` or `custom`.
* `giocookies_preferences` – JSON with the choices, the consent version and a random consent ID, for example `{"necessary":true,"analytics":true,"marketing":false,"version":"2","id":"…"}`.

Both are first-party cookies, last 365 days by default (filter `giocookies_cookie_days`) and are set only after the visitor makes a choice.

= Developer reference =

Filters:

* `giocookies_blocked_script_handles` – map enqueued script handles to `analytics` or `marketing`.
* `giocookies_consent_defaults` – change the parameters of the Consent Mode default command (for example to add `region` or `wait_for_update`).
* `giocookies_cookie_days` – lifetime of the consent cookies (default 365).
* `giocookies_client_ip` – the visitor IP used for rate limiting and the anonymized log (useful behind a trusted reverse proxy).
* `giocookies_rate_limit` – maximum consent saves per IP every 10 minutes (default 20, 0 disables).
* `giocookies_text` – change any banner text before output (`$value`, `$option_name`), for example per language.

JavaScript:

* `window.GioCookies.open()` – reopen the banner.
* `window.GioCookies.getConsent()` – `null` or `{ necessary, analytics, marketing }`.
* `window.GioCookies.hasConsent( 'analytics' )` – boolean.
* `document.addEventListener( 'giocookies:consent', e => … )` – fired after every decision.

Shortcode and markup:

* `[giocookies_preferences label="Cookie settings"]` prints a button that reopens the banner. Any element with the `data-giocookies-open` attribute does the same.

== Installation ==

1. Install and activate GioCookies from **Plugins > Add New**, or upload the `giocookies` folder to `/wp-content/plugins/`.
2. Go to **GioCookies > Settings**, check the texts and choose your privacy and cookie policy pages.
3. Configure your tags in Google Tag Manager to respect Consent Mode (see the FAQ).
4. Optional: install the WP Consent API plugin to share choices with other plugins.

== Frequently Asked Questions ==

= How do I set it up with Google Tag Manager and Consent Mode v2? =

1. Keep your normal GTM container snippet in the page head. GioCookies prints the Consent Mode defaults before it automatically (very early `wp_head` priority), so do not add another `gtag('consent', 'default', …)` command or a CMP template that sets defaults.
2. In GTM, enable **Admin > Container settings > Enable consent overview** and check the consent settings of each tag. Google tags (GA4, Google Ads, Floodlight, Conversion Linker) have built-in consent checks and adapt automatically.
3. For non-Google tags, set **Additional consent checks** (for example require `ad_storage`) or fire them on a **Custom Event** trigger named `cookie_consent_update`, using Data Layer Variables `cookie_consent.analytics` and `cookie_consent.marketing` as conditions.
4. Use GTM Preview mode to verify that the default state is "denied" and that the update arrives after a decision.

= How do I block a script that is not managed by GTM? =

Hard-coded scripts: change the type to `text/plain` and add the category. GioCookies turns them into real scripts after consent, on the decision and on every following page load:

`<script type="text/plain" data-giocookies-category="analytics" src="https://example.com/analytics.js"></script>`

`<script type="text/plain" data-giocookies-category="marketing">console.log( 'marketing allowed' );</script>`

Enqueued scripts: map their handles with a filter, for example in your theme's `functions.php`:

`add_filter( 'giocookies_blocked_script_handles', function ( $handles ) { $handles['my-pixel'] = 'marketing'; return $handles; } );`

The tag (including its inline before/after scripts) is printed as `type="text/plain"` and activated after consent, in document order. Notes: do not block a handle that other scripts depend on; a script that already ran cannot be "unloaded" when a visitor withdraws consent, it simply will not load on the next pages.

= How do I ask visitors for consent again? =

Change **GioCookies > Settings > Consent version** (for example from empty to `2`, then to `3` the next time). Visitors whose stored choice has a different version see the banner again, and Consent Mode defaults go back to "denied" until they decide.

= Where is the data stored? =

The visitor's choice is stored only in the two cookies listed above, in the visitor's browser.

If the consent log is enabled (default), each decision is also saved in the `{prefix}giocookies` database table on your own server: a random consent ID (also stored in the visitor's cookie, so you can match a record when a visitor sends it to you), the decision type, the choices, the consent version, the date and the IP address anonymized before storage (last octet of IPv4 and last 80 bits of IPv6 set to zero). Rows older than the retention period (default 365 days) are deleted every day by WP-Cron. Nothing is sent to external services.

Because the log contains no name, email address or full IP address, GioCookies does not register a personal data exporter or eraser: WordPress privacy tools look up data by email address, and the log has no such link. GioCookies adds suggested text to **Settings > Privacy** that you can adapt for your privacy policy.

= Can I remove all data when deleting the plugin? =

Yes. Enable **Remove data** in the settings, then delete the plugin: all options and the consent log table are removed. It is off by default so that a reinstall does not lose your log.

= Does it work with page caching? =

Yes. The banner markup and the blocked scripts are the same for every visitor; the decision is read from cookies in the browser. The consent log request uses a WordPress nonce: if your cache keeps pages for more than 12 hours, some log requests may be rejected (the visitor's choice still works). Excluding the consent cookies from the cache key is not required.

= Does it work on multilingual sites? =

Yes. Every text field left empty uses a translatable default that follows the site or visitor language (translations via translate.wordpress.org). Texts you customize can be translated with WPML or Polylang string translation thanks to the included `wpml-config.xml`, or per language in code with the `giocookies_text` filter.

= Can I turn off a category? =

Yes, in Settings → Categories. Necessary is always active.

= Can I customize the look? =

Set the accent and button text colors in the settings, choose a position (bottom left card, bottom right card or full-width bottom bar), or override the CSS custom properties from your theme:

`.giocookies-banner, .giocookies-bubble { --giocookies-accent: #6d28d9; --giocookies-radius: 12px; }`

Available properties: `--giocookies-bg`, `-text`, `-muted`, `-border`, `-surface`, `-accent`, `-accent-hover`, `-accent-text`, `-radius`, `-button-radius`, `-font`, `-shadow`, `-z`.

== Screenshots ==

1. The default banner: equal "Reject all" and "Accept all" buttons, policy links and the Customize option.
2. Customize view with the Necessary, Analytics and Marketing switches.
3. The consent log with anonymized IP addresses, filters and CSV export.

== Changelog ==

= 1.0.0 =
* Initial release.
