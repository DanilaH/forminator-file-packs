<?php
/** Read-only source snapshot for disposable installation lifecycle tests. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || '1' !== getenv( 'FFP_TEST_LAB' ) ) { throw new RuntimeException( 'Disposable lab only.' ); }
global $wpdb;
$data = array();
foreach ( array( Forminator_Database_Tables::FORM_ENTRY, Forminator_Database_Tables::FORM_ENTRY_META ) as $type ) {
 $table = Forminator_Database_Tables::get_table_name( $type ); $data[] = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i', $table ), ARRAY_A );
}
foreach ( get_posts( array( 'post_type' => 'forminator_forms', 'post_status' => 'any', 'numberposts' => -1 ) ) as $post ) { $data[] = array( $post, get_post_meta( $post->ID ) ); }
$uploads = wp_upload_dir()['basedir']; $files = array();
if ( is_dir( $uploads ) ) { foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $uploads, FilesystemIterator::SKIP_DOTS ) ) as $file ) { if ( $file->isFile() && ! $file->isLink() ) { $files[$file->getPathname()] = hash_file( 'sha256', $file->getPathname() ); } } }
ksort( $files ); echo hash( 'sha256', serialize( array( $data, $files ) ) );
