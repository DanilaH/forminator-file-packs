<?php
/** Run with WP-CLI only in a disposable test installation. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || '1' !== getenv( 'FFP_TEST_LAB' ) ) { throw new RuntimeException( 'Disposable lab only: set FFP_TEST_LAB=1.' ); }
wp_set_current_user( 1 );
$fixture = array();
foreach ( array( 'submission' => false, 'ajax' => false, 'media' => true ) as $mode => $media ) {
 $id = Forminator_API::add_form( 'Service requests ' . $mode, array(), array( 'formName' => 'Service requests ' . $mode, 'form-type' => 'default', 'submission-behaviour' => 'behaviour-thankyou', 'thankyou-message' => 'Received', 'ajax_submit' => true, 'enable-ajax' => true, 'store_submissions' => '1', 'honeypot' => '0', 'submitData' => array( 'custom-submit-text' => 'Send request' ) ) );
 if ( is_wp_error( $id ) ) { throw new RuntimeException( $id->get_error_message() ); }
 Forminator_API::update_form_setting( $id, 'formName', 'Service requests ' . $mode );
 Forminator_API::add_form_field( $id, 'text', array( 'field_label' => 'Serial number', 'required' => 'true' ) );
 Forminator_API::add_form_field( $id, 'textarea', array( 'field_label' => 'Description' ) );
 Forminator_API::add_form_field( $id, 'upload', array( 'field_label' => 'Photograph', 'file-type' => 'single', 'upload-method' => 'submission', 'use_library' => $media ? 'true' : 'false', 'custom-files' => 'true', 'filetypes' => array( 'jpg', 'png', 'pdf', 'txt' ), 'upload-limit' => 8, 'filesize' => 'MB' ) );
 Forminator_API::add_form_field( $id, 'upload', array( 'field_label' => 'Documents', 'file-type' => 'multiple', 'upload-method' => 'ajax' === $mode ? 'ajax' : 'submission', 'use_library' => $media ? 'true' : 'false', 'custom-files' => 'true', 'filetypes' => array( 'jpg', 'png', 'pdf', 'txt' ), 'file-limit' => 'unlimited', 'upload-limit' => 8, 'filesize' => 'MB' ) );
 $page = wp_insert_post( array( 'post_title' => 'Lab ' . $mode, 'post_status' => 'publish', 'post_type' => 'page', 'post_content' => '[forminator_form id="' . $id . '"]' ) );
 $fixture[$mode] = array( 'form' => $id, 'page' => $page );
}
$path = getenv( 'FFP_TEST_FIXTURE' );
if ( ! $path ) { throw new RuntimeException( 'Set FFP_TEST_FIXTURE to a generated JSON path outside the plugin.' ); }
file_put_contents( $path, wp_json_encode( $fixture, JSON_PRETTY_PRINT ) );
echo 'Synthetic forms created: ' . wp_json_encode( $fixture ) . "\n";
