# Development setup

This configuration has been prepared but not started or verified yet. It uses latest WordPress and latest stable Forminator for initial discovery; it is not yet a pinned regression environment. PHP 8.2 and WordPress 6.5 in the scaffold header are provisional development targets, not tested compatibility claims.

## Prerequisites

Docker running locally, Node.js/npm, Python 3 for packaging, and Git. Use a disposable development installation with synthetic uploads only.

## Start WordPress

From the repository root:

```sh
npx @wordpress/env start
```

The checked-in .wp-env.json loads the local plugin and the official Forminator distribution. Record the wp-env version selected by npx and pin it together with WordPress/Forminator before reporting reproducible test results.

Record installed versions:

```sh
npx @wordpress/env run cli wp core version
npx @wordpress/env run cli wp eval 'echo PHP_VERSION;'
npx @wordpress/env run cli wp plugin list --fields=name,status,version --format=json
```

Activate Forminator first, then the scaffold if it is not already active:

```sh
npx @wordpress/env run cli wp plugin activate forminator
npx @wordpress/env run cli wp plugin activate plugin
```

The local source folder is named plugin, so its development plugin identifier is plugin. Packaged installations use forminator-file-packs. The status page is under Tools → File Packs. No export controls exist yet.

## Initial checks

```sh
npx @wordpress/env run cli php -l /var/www/html/wp-content/plugins/plugin/forminator-file-packs.php
python3 scripts/build.py
```

Inspect actual frontend-created submissions and Forminator source before implementing the adapter. Do not substitute invented metadata fixtures for the first integration experiment.

## Stop

```sh
npx @wordpress/env stop
```

## References

- wp-env: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/
- Official Forminator distribution: https://wordpress.org/plugins/forminator/
- Plugin security: https://developer.wordpress.org/plugins/security/
- Plugin Check: https://wordpress.org/plugins/plugin-check/
