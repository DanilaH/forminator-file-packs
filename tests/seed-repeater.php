<?php
/** Native repeater fixture; invoke only in a disposable WP-CLI lab. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || '1' !== getenv( 'FFP_TEST_LAB' ) ) { throw new RuntimeException( 'Disposable lab only.' ); }
wp_set_current_user( 1 );
$id = Forminator_API::add_form( 'Repeating documents', array(), array( 'formName' => 'Repeating documents', 'form-type' => 'default', 'submission-behaviour' => 'behaviour-thankyou', 'thankyou-message' => 'Received', 'ajax_submit' => true, 'enable-ajax' => true, 'store_submissions' => '1', 'honeypot' => '0', 'submitData' => array( 'custom-submit-text' => 'Send request' ) ) );
Forminator_API::add_form_field( $id, 'text', array( 'field_label' => 'Serial number', 'required' => 'true' ) );
Forminator_API::add_form_field( $id, 'group', array( 'field_label' => 'Documents', 'is_repeater' => 'true', 'add_action_text' => 'Add document' ) );
Forminator_API::add_form_field( $id, 'upload', array( 'field_label' => 'Document', 'file-type' => 'single', 'upload-method' => 'submission', 'custom-files' => 'true', 'filetypes' => array( 'txt' ), 'upload-limit' => 8, 'filesize' => 'MB' ) );
$form = Forminator_API::get_form( $id ); $form->get_field( 'upload-1', false )->parent_group = 'group-1'; $form->save();
$page = wp_insert_post( array( 'post_title' => 'Repeater lab', 'post_status' => 'publish', 'post_type' => 'page', 'post_content' => '[forminator_form id="' . $id . '"]' ) );
file_put_contents( getenv( 'FFP_REPEATER_FIXTURE' ), wp_json_encode( array( 'form' => $id, 'page' => $page ), JSON_PRETTY_PRINT ) );
