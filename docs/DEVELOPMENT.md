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

The local source directory identifier is `plugin`; an installed ZIP uses `forminator-file-packs`. Open Tools → File Packs. Header minimum targets PHP 8.2 / WordPress 6.5 are provisional; the native test matrix covers WordPress 6.5.5 and PHP 8.2.32, not every patch release.

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

The browser test requires Playwright and Chromium. Optional `FFP_PLAYWRIGHT_MODULE` and `FFP_CHROMIUM_PATH` select existing installations. Plugin Check must be installed separately. CLI checks above cover static diagnostics; runtime Plugin Check requires its CLI setup (`--require=/absolute/path/to/plugin-check/cli.php`) and was run separately for the beta.

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

## Beta checks

`tests/resilience-integration.php` adds test-only namespace wrappers for failures; never load it from the plugin. Seed the repeater with `FFP_REPEATER_FIXTURE` pointing to a private generated JSON file, then run `tests/repeater-browser.cjs`. Its ZIP is checked for exact attachment bytes and unique members. `FFP_REPEATER_MODE` accepts `single`, `single-media`, `multiple`, `multiple-media`, `ajax`, `ajax-media` (default `single`).

`tests/lifecycle.py` drives real ZIP upgrades, uninstall/reinstall, child-process exit/fatal/SIGKILL and Russian UI states. It requires `FFP_WP_COMMAND` as a JSON array (for example `["wp", "--path=/absolute/disposable/wordpress"]`), `FFP_WP_PATH`, the common lab/fixture/credentials/artifact variables, Playwright/Chromium and a built ZIP. Set `FFP_PREVIOUS_ZIP` to the previous build to verify an actual upgrade; without it, that comparison is skipped. The test temporarily sets the synthetic admin locale to Russian and restores English. It intentionally adds a missing-file entry and only reclaims its own stale private job after terminating the child process.

`tests/resource-environment.php` runs under `FFP_RESOURCE_MODE=memory` or `nozip`. Memory mode sets a real 80M PHP limit and allocates synthetic pressure before checking the guard. No-ZIP mode requires a PHP configuration with ZIP actually disabled. Keep vendor/bootstrap failures distinct from exporter behavior.

Compile bundled translations with `python3 scripts/compile-translations.py`; the build script runs it automatically. The compiler handles the project's simple singular PO catalog; general plural/context catalogs require a standard gettext compiler.

## Extended native lab checks

Run `python3 tests/extended.py` after the ordinary HTTP fixtures exist. It requires the common test variables above, Playwright/Chromium and `FFP_WP_COMMAND` as a JSON array including the disposable WordPress path. It creates six real repeater forms and a synthetic capacity form with 100 entries / 500 random attachments / 100 MiB. Allow several hundred MiB of local disk space for sources, private snapshots, ZIP and browser download.

The capacity test explicitly sets PHP memory to 128M during planning/assembly, then restores the CLI limit for separate boundary fixtures. The browser test selects all four pages and downloads through the shipped Blob route. It inspects Chromium accessible names, live regions, focus, computed text contrast on solid backgrounds and 320 px layout; this is a focused check, not a WCAG or screen-reader audit. `permissions-http.py` grants/revokes synthetic user capabilities and changes Forminator's permission option, restoring the original option and deleting its synthetic user and test role in `finally`. Do not run these tests concurrently or against real data.

The suite compares complete source snapshots before/after capacity and permission HTTP checks. Generated fixture JSON, credentials and ZIPs stay outside Git. Publish only sanitized result JSON. These tests add evidence to beta 0.2.0; no new plugin build is needed when production code is unchanged.

## JSON regression checks (0.3.0)

After real HTTP fixtures and exports exist, run `wp eval-file tests/json-integration.php`, `wp eval-file tests/json-write-failure.php`, and `wp eval-file tests/json-capacity.php`, then `python3 tests/validate-json.py`. Use the same disposable lab/artifact variables. The independent Python reader checks every ZIP in the artifact directory against JSON/CSV/warnings/member sizes and the separate structured fixture. Keep that directory free of old-version ZIPs. `json-capacity.php` seeds eight 1 MiB NUL-text fields, sets PHP to 256M and checks a 48 MiB JSON expansion; its result records resource usage. This is a deliberately adversarial API fixture, not a normal frontend upload. API test metadata is passed through `wp_slash` because Forminator unslashes saved values.

Build and install 0.3.0 before running `tests/lifecycle.py`; point `FFP_PREVIOUS_ZIP` at 0.2.0 to exercise the actual upgrade. Retain historical matrix results separately from the combinations rerun for JSON.
