# Development setup

Use a disposable local WordPress installation and synthetic data. Tests create forms, entries, attachments and a synthetic user; never point them at production.

## Environment

The completed native lab used WordPress 7.1.3, Forminator 1.58.0, PHP 8.3.6, MariaDB 10.11.14 and WP-CLI 2.12.0. The checked-in `.wp-env.json` pins those WordPress/Forminator distributions and PHP 8.3. Docker/wp-env itself was not available in that lab, so that launch route remains unverified.

With Docker, Node/npm and Python 3 installed:

```sh
npx @wordpress/env start
npx @wordpress/env run cli wp core version
npx @wordpress/env run cli wp eval 'echo PHP_VERSION;'
npx @wordpress/env run cli wp plugin list --fields=name,status,version --format=json
npx @wordpress/env run cli wp plugin activate forminator
npx @wordpress/env run cli wp plugin activate plugin
```

The local source directory identifier is `plugin`; an installed ZIP uses `forminator-file-packs`. Open Tools → File Packs. Header minimum targets PHP 8.2 / WordPress 6.5 are provisional; the test matrix has not established them.

## Integration tests on a native disposable installation

Install/activate Forminator and this plugin. Set the CLI WordPress path with `--path` as needed. Configure the synthetic admin credentials through environment variables, not committed files. Use user ID 1 as that test administrator. Suppress outbound mail in the disposable lab.

```sh
export FFP_TEST_LAB=1
export FFP_TEST_FIXTURE=/absolute/private/path/fixture.json
export FFP_TEST_ARTIFACTS=/absolute/path/to/repository/artifacts
export FFP_TEST_URL=http://127.0.0.1:8080
export FFP_TEST_USER=lab
# Set FFP_TEST_PASSWORD to your disposable admin's password.
wp eval-file tests/seed-lab.php
python3 tests/http-integration.py
wp eval-file tests/security-integration.php
node tests/browser-smoke.cjs
wp plugin check forminator-file-packs --format=json
```

Seed once; HTTP checks create real frontend submissions and update the generated fixture. Run security and browser checks afterwards. The security test intentionally corrupts additional synthetic metadata to exercise failure cases; it restores/deletes those synthetic changes. Test artifacts are ignored by Git.

The browser test requires Playwright and Chromium. Optional `FFP_PLAYWRIGHT_MODULE` and `FFP_CHROMIUM_PATH` select existing installations. Plugin Check must be installed separately. CLI checks above cover static diagnostics; runtime Plugin Check tests are separate remaining release work.

## Packaging and validation

```sh
python3 scripts/build.py
node --check plugin/assets/admin.js
```

Run PHP lint on every `plugin/**/*.php`. Install the ZIP into a clean plugin directory rather than relying only on a development symlink. Check activation, behavior after Forminator deactivation, byte integrity of exports and temporary cleanup. See the [actual results](TEST_REPORT_2026-10-08.md).

```sh
npx @wordpress/env stop
```

References: [wp-env](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/), [Forminator](https://wordpress.org/plugins/forminator/), [Plugin Check](https://wordpress.org/plugins/plugin-check/).
