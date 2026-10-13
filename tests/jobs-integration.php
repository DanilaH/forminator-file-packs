<?php
/** Actual persisted large exports, only in the isolated lab. */
use ForminatorFilePacks\Jobs;
use ForminatorFilePacks\Package;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || '1' !== getenv( 'FFP_TEST_LAB' ) ) { throw new RuntimeException( 'Disposable lab only.' ); }
wp_set_current_user( 1 );
$checks = array();
function jobs_check( $ok, $name ) { global $checks; if ( ! $ok ) { throw new RuntimeException( $name ); } $checks[] = $name; echo 'PASS ' . $name . "\n"; }
function jobs_reject( $fn, $name ) { try { $fn(); } catch ( RuntimeException $e ) { jobs_check( true, $name ); return; } throw new RuntimeException( 'Expected rejection: ' . $name ); }
function jobs_advance( $id, $target, $max = 3000 ) { for ( $i = 0; $i < $max; ++$i ) { Jobs::run( $id ); $s = Jobs::status( $id ); if ( $target === $s['status'] ) { return $s; } if ( 'error' === $s['status'] ) { throw new RuntimeException( $s['error'] ); } if ( function_exists( 'wp_cache_flush_runtime' ) ) { wp_cache_flush_runtime(); } } throw new RuntimeException( 'No task progress: ' . $target ); }
function jobs_remove( $id ) { Jobs::command( $id, 'cancel' ); for ( $i = 0; $i < 200; ++$i ) { Jobs::run( $id ); if ( ! is_dir( Package::private_root() . '/export-' . $id ) ) { return; } } throw new RuntimeException( 'Private task cleanup incomplete' ); }
$form = Forminator_API::add_form( 'Large export lab', array(), array( 'formName' => 'Large export lab', 'store_submissions' => '1' ) );
update_post_meta( $form, '_ffp_staging_fixture', '1' );
Forminator_API::add_form_field( $form, 'text', array( 'field_label' => 'Serial' ) );
Forminator_API::add_form_field( $form, 'upload', array( 'field_label' => 'Files', 'file-type' => 'multiple', 'upload-method' => 'submission' ) );
$root = forminator_get_upload_path( $form, 'uploads' ); wp_mkdir_p( $root );
$file = $root . '/independent-bytes.txt'; file_put_contents( $file, random_bytes( 8192 ) ); $hash = hash_file( 'sha256', $file );
$total = ( ! getenv( 'FFP_STAGING_TEST' ) && '7.1.3' === get_bloginfo( 'version' ) ) ? 10000 : 1000;
$ids = array();
for ( $i = 0; $i < $total; ++$i ) {
 $ids[] = Forminator_API::add_form_entry( $form, array( array( 'name' => 'text-1', 'value' => 'LARGE-' . $i . '-юникод' ), array( 'name' => 'upload-1', 'value' => array( 'file' => array( 'file_path' => $file, 'file_url' => '' ) ) ) ) );
 if ( 0 === $i % 100 && function_exists( 'wp_cache_flush_runtime' ) ) { wp_cache_flush_runtime(); }
}
$started = microtime( true ); $original_memory = ini_get( 'memory_limit' ); ini_set( 'memory_limit', '128M' );
$job = Jobs::create( $form, 'all' ); $id = $job['id'];
$late = Forminator_API::add_form_entry( $form, array( array( 'name' => 'text-1', 'value' => 'AFTER-START' ) ) );
$review = jobs_advance( $id, 'review' );
jobs_check( $total === $review['total'] && $total === $review['checked'] && $total === $review['files'], $total . ' persisted submissions checked without a total-count ceiling; later entry excluded' );
jobs_check( 0 === $review['warnings'] && $review['parts'] === (int) ceil( $total / Jobs::PART_ENTRIES ), 'Measured safe independent part boundaries' );
jobs_check( 10 === count( Jobs::review( $id, 1 )['rows'] ), 'Review is paginated and contains no private paths' );
Jobs::command( $id, 'start' ); $ready = jobs_advance( $id, 'ready' );
$found = array(); $zip_bytes = 0;
for ( $p = 1; $p <= (int) ceil( $ready['parts'] / 10 ); ++$p ) {
 foreach ( Jobs::parts( $id, $p )['rows'] as $part ) {
  $file_path = Package::private_root() . '/export-' . $id . '/archive-' . $part['number'] . '.zip';
  $zip = new ZipArchive(); if ( true !== $zip->open( $file_path ) ) { throw new RuntimeException( 'ZIP cannot be opened' ); }
  $data = json_decode( $zip->getFromName( 'data.json' ), true );
  foreach ( $data['submissions'] as $row ) {
   if ( isset( $found[$row['id']] ) ) { throw new RuntimeException( 'Duplicate submission' ); }
   $found[$row['id']] = true;
   if ( $hash !== hash( 'sha256', $zip->getFromName( $row['attachments'][0]['path'] ) ) ) { throw new RuntimeException( 'Wrong attachment bytes' ); }
   if ( false === $zip->getFromName( $row['card'] ) ) { throw new RuntimeException( 'Missing card' ); }
  }
  if ( false === $zip->getFromName( 'index.html' ) || false === $zip->getFromName( 'register.csv' ) ) { throw new RuntimeException( 'Not independently readable' ); }
  $zip_bytes += filesize( $file_path ); $zip->close();
  jobs_check( 0 === ( fileperms( $file_path ) & 0077 ), 'Part ' . ( $part['number'] + 1 ) . ' is private' );
 }
}
$expected = $ids; $actual = array_keys( $found ); sort( $expected ); sort( $actual );
jobs_check( $actual === $expected, 'All submission identities preserved exactly once across all ZIPs' );
jobs_check( $ready['built'] === $total && $ready['completed_parts'] === $ready['parts'], 'Completion reflects committed parts and actual submissions' );
jobs_check( $hash === hash_file( 'sha256', $file ), 'Original attachment bytes unchanged' );
jobs_check( 1 === get_current_user_id(), 'Worker restores the calling user after each pass' );
$resource = array( 'submissions' => $total, 'parts' => $ready['parts'], 'seconds' => round( microtime( true ) - $started, 3 ), 'peak_memory_bytes' => memory_get_peak_usage( true ), 'php_memory_limit' => ini_get( 'memory_limit' ), 'zip_bytes' => $zip_bytes );
$out = getenv( 'FFP_TEST_ARTIFACTS' );
// Keep only a representative part for independent Python CRC/content validation.
copy( Package::private_root() . '/export-' . $id . '/archive-0.zip', $out . '/large-first.zip' );
jobs_remove( $id ); jobs_check( ! Jobs::latest(), 'Cancel removes private parts and the owner pointer' );
ini_set( 'memory_limit', $original_memory );
$manual = Jobs::create( $form, 'manual' ); $id = $manual['id'];
$first = array_slice( $ids, 0, 100 ); Jobs::append( $id, 0, $first ); Jobs::append( $id, 0, $first );
Jobs::append( $id, 1, array( $ids[0], $ids[100] ) ); Jobs::command( $id, 'finish' );
$review = jobs_advance( $id, 'review' ); jobs_check( 101 === $review['total'], '101 manual selections; lost-response retry and cross-chunk duplicates are idempotent' );
jobs_reject( static fn() => Jobs::parts( $id, 1 ), 'Unbuilt archives cannot be downloaded' );
jobs_reject( static fn() => Jobs::status( '../state' ), 'Task traversal rejected' );
wp_set_current_user( 0 ); jobs_reject( static fn() => Jobs::status( $id ), 'Guest task access rejected' ); wp_set_current_user( 1 );
$other = wp_insert_user( array( 'user_login' => 'ffp-jobs-other-' . time(), 'user_pass' => wp_generate_password(), 'role' => 'administrator' ) );
wp_set_current_user( $other ); jobs_reject( static fn() => Jobs::status( $id ), 'Other authorized users cannot read another owner task' ); wp_set_current_user( 1 );
$entry = Forminator_API::get_entry( $form, $ids[0] );
Forminator_API::update_form_entry( $form, $ids[0], array( array( 'name' => 'text-1', 'value' => 'CHANGED-AFTER-CHECK' ) ) );
Jobs::command( $id, 'start' ); Jobs::run( $id );
jobs_check( 'error' === Jobs::status( $id )['status'], 'Changed submission blocks publication of stale checked contents' ); jobs_remove( $id );
// Leave a small dedicated form for actual browser background/parts checks.
$fixture = json_decode( file_get_contents( getenv( 'FFP_TEST_FIXTURE' ) ), true );
$ui_form = Forminator_API::add_form( 'Большая выгрузка · 101 заявка', array(), array( 'formName' => 'Большая выгрузка · 101 заявка', 'store_submissions' => '1' ) );
update_post_meta( $ui_form, '_ffp_staging_fixture', '1' );
Forminator_API::add_form_field( $ui_form, 'text', array( 'field_label' => 'Клиент' ) );
Forminator_API::add_form_field( $ui_form, 'upload', array( 'field_label' => 'Документы', 'file-type' => 'multiple', 'upload-method' => 'submission' ) );
$ui_root = forminator_get_upload_path( $ui_form, 'uploads' ); wp_mkdir_p( $ui_root );
$ui_file = $ui_root . '/document.txt'; file_put_contents( $ui_file, 'Actual synthetic attachment bytes' );
for ( $i = 0; $i < 101; ++$i ) { Forminator_API::add_form_entry( $ui_form, array( array( 'name' => 'text-1', 'value' => 'Клиент ' . $i ), array( 'name' => 'upload-1', 'value' => array( 'file' => array( 'file_path' => $ui_file, 'file_url' => '' ) ) ) ) ); }
$fixture['large'] = array( 'form' => $ui_form, 'count' => 101 );
file_put_contents( getenv( 'FFP_TEST_FIXTURE' ), wp_json_encode( $fixture ) );
file_put_contents( $out . '/jobs-results.json', wp_json_encode( array( 'count' => count( $checks ), 'checks' => $checks, 'resource' => $resource ), JSON_PRETTY_PRINT ) );
echo 'Large export resource: ' . wp_json_encode( $resource ) . "\n";
