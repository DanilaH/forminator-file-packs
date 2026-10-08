<?php
/**
 * Plugin Name: File Packs for Forminator
 * Description: Preview and export Forminator submissions and local attachments as organized ZIP packages.
 * Version: 0.2.0
 * Requires at least: 6.5
 * Requires PHP: 8.2
 * Requires Plugins: forminator
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Domain Path: /languages
 * Text Domain: forminator-file-packs
 *
 * @package ForminatorFilePacks
 */
namespace ForminatorFilePacks;
if ( ! defined( 'ABSPATH' ) ) { exit; }
const VERSION = '0.2.0';
const MAX_ENTRIES = 100;
const MAX_FILES = 500;
const MAX_BYTES = 104857600;
require_once __DIR__ . '/includes/class-adapter.php';
require_once __DIR__ . '/includes/class-planner.php';
require_once __DIR__ . '/includes/class-package.php';
require_once __DIR__ . '/includes/class-admin.php';
add_action( 'plugins_loaded', static function () {
 load_plugin_textdomain( 'forminator-file-packs', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
 Admin::register();
} );
