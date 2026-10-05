# Contributing to GioCookies

Thanks for your interest in improving GioCookies! Bug reports, ideas and pull requests are all welcome.

## Reporting bugs and requesting features

- Search the [existing issues](../../issues) first to avoid duplicates.
- Use the issue templates and include your GioCookies, WordPress and PHP versions.
- For questions about using the plugin, use the [GioSuite support page](https://giosuite.com/support/).
- **Security issues must not be reported publicly**: see [SECURITY.md](SECURITY.md).

## Development setup

1. Clone the repository into the `wp-content/plugins/` folder of a local WordPress site:
   ```bash
   git clone https://github.com/GiovanniBevacqua/giocookies.git wp-content/plugins/giocookies
   ```
2. Activate **GioCookies** from the Plugins screen.
3. Enable debugging in `wp-config.php` while developing:
   ```php
   define( 'WP_DEBUG', true );
   define( 'WP_DEBUG_LOG', true );
   ```

## Pull requests

1. Fork the repository and create a branch from `main` (e.g. `fix/short-description`).
2. Keep each pull request focused on one change.
3. Follow the [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/):
   sanitize input, escape output, use nonces and capability checks for every action.
4. Keep compatibility with the minimum versions declared in `readme.txt` (PHP 7.4).
5. Make every user-facing string translatable with the `giocookies` text domain.
6. Make sure the **PHP Lint** check passes.
7. Describe how to test your change in the pull request.

## Releases

The code on `main` is prepared for submission to the WordPress.org plugin directory.
Version numbers follow the `Stable tag` in `readme.txt`, and every release is tagged (`vX.Y.Z`) with a GitHub release.

## License

By contributing, you agree that your contributions are licensed under the [GPLv2 or later](LICENSE), the same license as the plugin.
