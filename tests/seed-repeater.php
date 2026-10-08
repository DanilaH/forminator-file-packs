<?php
/** Native repeater fixture; invoke only in a disposable WP-CLI lab. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || '1' !== getenv( 'FFP_TEST_LAB' ) ) { throw new RuntimeException( 'Disposable lab only.' ); }
wp_set_current_user( 1 );
$mode = getenv( 'FFP_REPEATER_MODE' ) ?: 'single';
$modes = array( 'single', 'single-media', 'multiple', 'multiple-media', 'ajax', 'ajax-media' );
if ( ! in_array( $mode, $modes, true ) ) { throw new RuntimeException( 'Unknown repeater test mode.' ); }
$multiple = ! str_starts_with( $mode, 'single' );
$id = Forminator_API::add_form( 'Repeating documents', array(), array( 'formName' => 'Repeating documents', 'form-type' => 'default', 'submission-behaviour' => 'behaviour-thankyou', 'thankyou-message' => 'Received', 'ajax_submit' => true, 'enable-ajax' => true, 'store_submissions' => '1', 'honeypot' => '0', 'submitData' => array( 'custom-submit-text' => 'Send request' ) ) );
Forminator_API::add_form_field( $id, 'text', array( 'field_label' => 'Serial number', 'required' => 'true' ) );
Forminator_API::add_form_field( $id, 'group', array( 'field_label' => 'Documents', 'is_repeater' => 'true', 'add_action_text' => 'Add document' ) );
Forminator_API::add_form_field( $id, 'upload', array( 'field_label' => 'Document', 'file-type' => $multiple ? 'multiple' : 'single', 'upload-method' => str_starts_with( $mode, 'ajax' ) ? 'ajax' : 'submission', 'use_library' => str_ends_with( $mode, '-media' ), 'custom-files' => 'true', 'filetypes' => array( 'txt' ), 'file-limit' => 'unlimited', 'upload-limit' => 8, 'filesize' => 'MB' ) );
$form = Forminator_API::get_form( $id ); $form->get_field( 'upload-1', false )->parent_group = 'group-1'; $form->save();
$page = wp_insert_post( array( 'post_title' => 'Repeater lab', 'post_status' => 'publish', 'post_type' => 'page', 'post_content' => '[forminator_form id="' . $id . '"]' ) );
file_put_contents( getenv( 'FFP_REPEATER_FIXTURE' ), wp_json_encode( array( 'form' => $id, 'page' => $page, 'mode' => $mode, 'files_per_row' => $multiple ? 2 : 1 ), JSON_PRETTY_PRINT ) );
