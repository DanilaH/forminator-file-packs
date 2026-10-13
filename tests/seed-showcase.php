<?php
/** Persisted, explicitly synthetic records for screenshots and task-flow checks. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || '1' !== getenv( 'FFP_TEST_LAB' ) ) { throw new RuntimeException( 'Disposable lab only.' ); }
wp_set_current_user( 1 );
$form = Forminator_API::add_form( 'Заявки на ремонт', array(), array( 'formName' => 'Заявки на ремонт', 'form-type' => 'default', 'store_submissions' => '1' ) );
if ( is_wp_error( $form ) ) { throw new RuntimeException( $form->get_error_message() ); }
update_post_meta( $form, '_ffp_staging_fixture', '1' );
Forminator_API::update_form_setting( $form, 'formName', 'Заявки на ремонт' );
Forminator_API::add_form_field( $form, 'text', array( 'field_label' => 'Имя' ) );
Forminator_API::add_form_field( $form, 'textarea', array( 'field_label' => 'Что случилось' ) );
Forminator_API::add_form_field( $form, 'upload', array( 'field_label' => 'Фотографии', 'file-type' => 'multiple', 'upload-method' => 'submission', 'use_library' => 'false' ) );
$root = forminator_get_upload_path( $form, 'uploads' ); wp_mkdir_p( $root );
$ids = array();
foreach ( array( array( 'Иван Петров', 'Холодильник не охлаждает', 3 ), array( 'Анна Смирнова', 'Стиральная машина протекает', 2 ) ) as $i => $sample ) {
 $paths = array();
 for ( $n = 1; $n <= $sample[2]; ++$n ) {
  $path = $root . '/photo-' . $i . '-' . $n . '.png';
  file_put_contents( $path, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j6i8AAAAASUVORK5CYII=' ) );
  $paths[] = $path;
 }
 $id = Forminator_API::add_form_entry( $form, array( array( 'name' => 'text-1', 'value' => $sample[0] ), array( 'name' => 'textarea-1', 'value' => $sample[1] ), array( 'name' => 'upload-1', 'value' => array( 'file' => array( 'file_path' => $paths, 'file_url' => array_fill( 0, count( $paths ), '' ) ) ) ) ) );
 if ( is_wp_error( $id ) ) { throw new RuntimeException( $id->get_error_message() ); }
 $ids[] = $id;
}
$fixture = json_decode( file_get_contents( getenv( 'FFP_TEST_FIXTURE' ) ), true );
$fixture['showcase'] = array( 'form' => $form, 'entries' => $ids );
file_put_contents( getenv( 'FFP_TEST_FIXTURE' ), wp_json_encode( $fixture ) );
echo "PASS persisted synthetic repair submissions for actual UI screenshots\n";
