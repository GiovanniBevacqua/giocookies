# Changelog

All notable changes to GioCookies are documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project uses [Semantic Versioning](https://semver.org/).

## [1.0.1] - 2026-10-08

### Added
- `giocookies_preferences_end` action to add content at the end of the preferences panel.

## [1.0.0] - 2026-10-05

### Added
- Accessible cookie banner with equal "Reject all" / "Accept all" buttons, per-category switches and a floating preferences button.
- Google Consent Mode v2 defaults printed before Google Tag Manager, updated on every decision, and a `cookie_consent_update` dataLayer event.
- Configurable categories (Analytics, Marketing) and editable texts, with translatable defaults, `wpml-config.xml` and the `giocookies_text` filter for multilingual sites.
- WP Consent API integration.
- Script blocking for `type="text/plain"` scripts and enqueued handles (`giocookies_blocked_script_handles`).
- Consent log with anonymized IP, consent ID, decision type and policy version; admin screen with filters, CSV export and automatic retention cleanup.
- Consent version to ask visitors again after a policy change.
- `[giocookies_preferences]` shortcode, `data-giocookies-open` attribute and `window.GioCookies` JavaScript API.
- Suggested privacy policy text and optional data removal on uninstall.

[1.0.1]: https://github.com/GiovanniBevacqua/giocookies/releases/tag/v1.0.1
[1.0.0]: https://github.com/GiovanniBevacqua/giocookies/releases/tag/v1.0.0
