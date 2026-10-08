<?php
/**
 * Plugin Name: File Packs for Forminator
 * Description: Development scaffold for read-only submission and attachment exports. Export is not implemented yet.
 * Version: 0.0.1
 * Requires at least: 6.5
 * Requires PHP: 8.2
 * Requires Plugins: forminator
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: forminator-file-packs
 *
 * @package ForminatorFilePacks
 */

namespace ForminatorFilePacks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Register a status page only; no submission data is read in this scaffold. */
function register_admin_page() {
	add_management_page(
		__( 'File Packs for Forminator', 'forminator-file-packs' ),
		__( 'File Packs', 'forminator-file-packs' ),
		'manage_options',
		'forminator-file-packs',
		__NAMESPACE__ . '\\render_admin_page'
	);
}

/** Display the current development status. */
function render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to view this page.', 'forminator-file-packs' ) );
	}

	echo '<div class="wrap"><h1>' . esc_html__( 'File Packs for Forminator', 'forminator-file-packs' ) . '</h1>';
	echo '<p>' . esc_html__( 'Development scaffold. Submission preview and ZIP export are not implemented yet.', 'forminator-file-packs' ) . '</p>';
	echo '<p>' . esc_html__( 'This version does not read submissions or change Forminator data.', 'forminator-file-packs' ) . '</p></div>';
}

add_action( 'admin_menu', __NAMESPACE__ . '\\register_admin_page' );
