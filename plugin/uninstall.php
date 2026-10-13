<?php
/** Delete only exporter-owned private tasks. @package ForminatorFilePacks */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) { exit; }
require_once __DIR__ . '/file-packs-for-forminator.php';
\ForminatorFilePacks\Jobs::uninstall();
