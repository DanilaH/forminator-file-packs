<?php
/**
 * Plugin Name: File Packs for Forminator
 * Description: Preview and export Forminator submissions and local attachments as organized ZIP packages.
 * Version: 0.4.1
 * Requires at least: 6.5
 * Requires PHP: 8.2
 * Requires Plugins: forminator
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: file-packs-for-forminator
 *
 * @package ForminatorFilePacks
 */
namespace ForminatorFilePacks;
if ( ! defined( 'ABSPATH' ) ) { exit; }
const VERSION = '0.4.1';
const MAX_ENTRIES = 100;
const MAX_FILES = 500;
const MAX_BYTES = 104857600;
require_once __DIR__ . '/includes/class-adapter.php';
require_once __DIR__ . '/includes/class-planner.php';
require_once __DIR__ . '/includes/class-package.php';
require_once __DIR__ . '/includes/class-admin.php';
require_once __DIR__ . '/includes/class-jobs.php';
add_action( 'ffp_export_step', array( Jobs::class, 'run' ) );
add_action( 'ffp_export_gc', array( Jobs::class, 'collect' ) );
// WordPress.org language packs are loaded automatically from WP_LANG_DIR/plugins.
add_action( 'init', static function () {
 Admin::register();
 if ( ! wp_next_scheduled( 'ffp_export_gc' ) ) { wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'ffp_export_gc' ); }
} );
