<h1 align="center">GioCookies</h1>

<p align="center">
  Lightweight cookie consent banner for WordPress, built around Google Consent Mode v2, Google Tag Manager and the WP Consent API.
</p>

<p align="center">
  <img src="https://img.shields.io/badge/WordPress-6.2%2B-21759b?logo=wordpress&logoColor=white" alt="WordPress 6.2+">
  <img src="https://img.shields.io/badge/PHP-7.4%2B-777bb4?logo=php&logoColor=white" alt="PHP 7.4+">
  <a href="https://github.com/GiovanniBevacqua/giocookies/actions/workflows/php-lint.yml"><img src="https://github.com/GiovanniBevacqua/giocookies/actions/workflows/php-lint.yml/badge.svg" alt="PHP Lint"></a>
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-GPLv2%2B-blue" alt="License: GPLv2 or later"></a>
</p>

---

## About

**GioCookies** shows an accessible cookie banner, prints Google Consent Mode v2 defaults before your tags load and
updates them when visitors decide. It runs entirely on your site: no external service, no account, no cookie scanning in the cloud.

> GioCookies does not provide legal advice and does not guarantee compliance. It does not scan cookies and is not an
> IAB TCF or Google-certified CMP. Review your cookie policy and configuration with your legal advisor.

## Features

- **Consent Mode v2** — `gtag('consent', 'default', …)` is printed at the top of the `<head>`, before Google Tag Manager, and updated on every decision.
- **GTM-friendly** — a `cookie_consent_update` event with `{ necessary, analytics, marketing }` is pushed to the `dataLayer`.
- **Equal choices** — "Reject all" and "Accept all" have the same visual weight; "Customize" opens the per-category switches.
- **Configurable categories** — Analytics and Marketing can be turned off when a site does not use them.
- **Script blocking** — scripts not managed by GTM run only after consent (`type="text/plain"` + `data-giocookies-category`, or enqueued handles via a filter).
- **WP Consent API** — choices are shared with plugins that support it.
- **Privacy-friendly consent log** — anonymized IP, random consent ID, choices, policy version and date, with automatic retention cleanup and CSV export.
- **Re-ask consent** — change the consent version when your policy changes.
- **Multilingual** — empty fields use translatable defaults; custom texts work with WPML/Polylang (`wpml-config.xml`) or the `giocookies_text` filter.
- **Accessible** — dialog semantics, keyboard support, visible focus, native switches, reduced motion.
- **Themeable** — style it with `--giocookies-*` CSS custom properties.

## Requirements

- WordPress 6.2 or later
- PHP 7.4 or later

## Installation

Download the latest zip from the [Releases](https://github.com/GiovanniBevacqua/giocookies/releases) page and upload it from
**Plugins → Add New → Upload Plugin**, or clone the repository into `wp-content/plugins/`:

```bash
git clone https://github.com/GiovanniBevacqua/giocookies.git wp-content/plugins/giocookies
```

Then open **GioCookies → Settings**, enable the banner and set your privacy and cookie policy pages.

### Google Tag Manager

Keep your GTM snippet after `wp_head()` (GioCookies prints the Consent Mode defaults earlier, at the very top of the head).
In GTM, enable **Consent Overview** and require `analytics_storage` for analytics tags and `ad_storage` for advertising tags,
or trigger tags on the `cookie_consent_update` event.

## For developers

| Hook | Type | Description |
| --- | --- | --- |
| `giocookies_text` | filter | Change any banner text before output (`$value`, `$option_name`). |
| `giocookies_blocked_script_handles` | filter | Map enqueued script handles to `analytics` or `marketing`. |
| `giocookies_consent_defaults` | filter | Parameters of the Consent Mode default command (e.g. `region`, `wait_for_update`). |
| `giocookies_cookie_days` | filter | Lifetime of the consent cookies (default 365). |
| `giocookies_client_ip` | filter | Visitor IP used for rate limiting and the anonymized log (behind a trusted proxy). |
| `giocookies_rate_limit` | filter | Maximum consent saves per IP every 10 minutes (default 20, `0` disables). |

```html
<!-- Runs only after the visitor accepts Marketing cookies -->
<script type="text/plain" data-giocookies-category="marketing" src="https://example.com/pixel.js"></script>
```

```php
// Block an enqueued script until Analytics consent is given.
add_filter( 'giocookies_blocked_script_handles', function ( $handles ) {
    $handles['my-analytics'] = 'analytics';
    return $handles;
} );
```

JavaScript API: `window.GioCookies.open()`, `window.GioCookies.getConsent()`, `window.GioCookies.hasConsent( 'analytics' )`
and the `giocookies:consent` DOM event. The `[giocookies_preferences]` shortcode prints a button that reopens the banner.

## Contributing

Contributions are welcome! Please read [CONTRIBUTING.md](CONTRIBUTING.md) before opening an issue or a pull request.

To report a security vulnerability, please follow [SECURITY.md](SECURITY.md) and **do not** open a public issue.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

GioCookies is free software, released under the [GNU General Public License v2.0 or later](LICENSE).

Made by [Giovanni Bevacqua](https://www.linkedin.com/in/giovanni-bevacqua/) · [giosuite.com](https://giosuite.com)
