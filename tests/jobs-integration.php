<?php
/** Actual persisted large exports, only in the isolated lab. */
use ForminatorFilePacks\Jobs;
use ForminatorFilePacks\Package;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || '1' !== getenv( 'FFP_TEST_LAB' ) ) { throw new RuntimeException( 'Disposable lab only.' ); }
wp_set_current_user( 1 );
$GLOBALS['ffp_jobs_checks'] = array(); $GLOBALS['jobs_peak'] = 0; $GLOBALS['jobs_step_seconds'] = 0;
function jobs_check( $ok, $name ) {  if ( ! $ok ) { throw new RuntimeException( $name ); } $GLOBALS['ffp_jobs_checks'][] = $name; echo 'PASS ' . $name . "\n"; }
function jobs_reject( $fn, $name ) { try { $fn(); } catch ( RuntimeException $e ) { jobs_check( true, $name ); return; } throw new RuntimeException( 'Expected rejection: ' . $name ); }
function jobs_advance( $id, $target, $max = 3000 ) { for ( $i = 0; $i < $max; ++$i ) { if ( function_exists( 'memory_reset_peak_usage' ) ) { memory_reset_peak_usage(); } $step_start = microtime( true ); Jobs::run( $id ); $GLOBALS['jobs_peak'] = max( $GLOBALS['jobs_peak'], memory_get_peak_usage( true ) ); $GLOBALS['jobs_step_seconds'] = max( $GLOBALS['jobs_step_seconds'], microtime( true ) - $step_start ); $s = Jobs::status( $id ); if ( $target === $s['status'] ) { return $s; } if ( 'error' === $s['status'] ) { throw new RuntimeException( $s['error'] ); } } throw new RuntimeException( 'No task progress: ' . $target ); }
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
$snapshot = static function () {
 $wp_cli = realpath( $_SERVER['argv'][0] );
 $child = proc_open( array( PHP_BINARY, $wp_cli, '--path=' . ABSPATH, 'eval-file', __DIR__ . '/lifecycle-snapshot.php' ), array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
 fclose( $pipes[0] ); $hash = trim( stream_get_contents( $pipes[1] ) ); fclose( $pipes[1] ); $stderr = stream_get_contents( $pipes[2] ); fclose( $pipes[2] );
 if ( 0 !== proc_close( $child ) || ! preg_match( '/^[a-f0-9]{64}$/D', $hash ) ) { throw new RuntimeException( 'Independent source snapshot failed: ' . $stderr ); }
 return $hash;
};
$source_before = $snapshot();
jobs_check( '128M' === ini_get( 'memory_limit' ), 'Actual 128 MiB PHP limit enforced during all large-export passes' );
$review = jobs_advance( $id, 'review' );
jobs_check( $total === $review['total'] && $total === $review['checked'] && $total === $review['files'], $total . ' persisted submissions checked without a total-count ceiling; later entry excluded' );
jobs_check( 0 === $review['warnings'] && $review['parts'] === (int) ceil( $total / min( Jobs::PART_ENTRIES, Jobs::PART_FILES ) ), 'Measured safe independent part boundaries' );
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
jobs_check( $source_before === $snapshot(), 'All Forminator forms, records, metadata and original file hashes unchanged by large export' );
$resource = array( 'submissions' => $total, 'parts' => $ready['parts'], 'seconds' => round( microtime( true ) - $started, 3 ), 'peak_memory_bytes' => $GLOBALS['jobs_peak'], 'max_step_seconds' => round( $GLOBALS['jobs_step_seconds'], 3 ), 'php_memory_limit' => ini_get( 'memory_limit' ), 'zip_bytes' => $zip_bytes );
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
$binding_path = Package::private_root() . '/export-' . $id . '/state.json'; $binding = json_decode( file_get_contents( $binding_path ), true ); $original_blog = $binding['blog']; $binding['blog'] = $original_blog + 1; file_put_contents( $binding_path, wp_json_encode( $binding ) );
jobs_reject( static fn() => Jobs::status( $id ), 'Task from another site context is rejected even for the same owner' );
Jobs::run( $id ); $unchanged_binding = json_decode( file_get_contents( $binding_path ), true ); jobs_check( $unchanged_binding === $binding, 'Wrong-site worker does not process or mutate another site task' );
$binding['blog'] = $original_blog; file_put_contents( $binding_path, wp_json_encode( $binding ) );

wp_set_current_user( 0 ); jobs_reject( static fn() => Jobs::status( $id ), 'Guest task access rejected' ); wp_set_current_user( 1 );
$other = wp_insert_user( array( 'user_login' => 'ffp-jobs-other-' . time(), 'user_pass' => wp_generate_password(), 'role' => 'administrator' ) );
wp_set_current_user( $other ); jobs_reject( static fn() => Jobs::status( $id ), 'Other authorized users cannot read another owner task' ); wp_set_current_user( 1 );
$entry = Forminator_API::get_entry( $form, $ids[0] );
Forminator_API::update_form_entry( $form, $ids[0], array( array( 'name' => 'text-1', 'value' => 'CHANGED-AFTER-CHECK' ) ) );
Jobs::command( $id, 'start' ); Jobs::run( $id );
jobs_check( 'error' === Jobs::status( $id )['status'], 'Changed submission blocks publication of stale checked contents' ); jobs_remove( $id );

$omitted = Forminator_API::add_form_entry( $form, array( array( 'name' => 'upload-1', 'value' => array( 'file' => array( 'file_path' => $root . '/missing-task.txt', 'file_url' => '' ) ) ) ) );
$partial_task = Jobs::create( $form, 'manual' ); Jobs::append( $partial_task['id'], 0, array( $omitted ) ); Jobs::command( $partial_task['id'], 'finish' );
$partial_review = jobs_advance( $partial_task['id'], 'review' ); jobs_check( 1 === $partial_review['warnings'], 'Large review reports actual missing-file warning' );
jobs_reject( static fn() => Jobs::command( $partial_task['id'], 'start' ), 'Large export requires explicit omission consent' );
Jobs::command( $partial_task['id'], 'start', true ); $partial_ready = jobs_advance( $partial_task['id'], 'ready' );
jobs_check( 1 === $partial_ready['warnings'] && 0 === $partial_ready['files'], 'Consented partial task retains truthful actual counts and warning status' );
copy( Package::private_root() . '/export-' . $partial_task['id'] . '/archive-0.zip', $out . '/large-partial.zip' ); jobs_remove( $partial_task['id'] );
// One large object has a clear failure, even though the total-count ceiling is removed.
$oversize = Forminator_API::add_form_entry( $form, array( array( 'name' => 'upload-1', 'value' => array( 'file' => array( 'file_path' => array_fill( 0, 501, $file ), 'file_url' => array_fill( 0, 501, '' ) ) ) ) ) );
$too_large = Jobs::create( $form, 'manual' ); Jobs::append( $too_large['id'], 0, array( $oversize ) ); Jobs::command( $too_large['id'], 'finish' ); Jobs::run( $too_large['id'] );
jobs_check( 'error' === Jobs::status( $too_large['id'] )['status'], 'Oversized single submission fails without a ready archive claim' ); jobs_remove( $too_large['id'] );

// Leave a small dedicated form for actual browser background/parts checks.
$fixture = json_decode( file_get_contents( getenv( 'FFP_TEST_FIXTURE' ) ), true );
$ui_form = Forminator_API::add_form( 'Заявки на ремонт · 101 заявка', array(), array( 'formName' => 'Заявки на ремонт · 101 заявка', 'store_submissions' => '1' ) );
update_post_meta( $ui_form, '_ffp_staging_fixture', '1' );
Forminator_API::add_form_field( $ui_form, 'text', array( 'field_label' => 'Клиент' ) );
Forminator_API::add_form_field( $ui_form, 'upload', array( 'field_label' => 'Документы', 'file-type' => 'multiple', 'upload-method' => 'submission' ) );
$ui_root = forminator_get_upload_path( $ui_form, 'uploads' ); wp_mkdir_p( $ui_root );
$ui_file = $ui_root . '/document.txt'; file_put_contents( $ui_file, random_bytes( 262144 ) );
for ( $i = 0; $i < 101; ++$i ) { Forminator_API::add_form_entry( $ui_form, array( array( 'name' => 'text-1', 'value' => 'Клиент ' . $i ), array( 'name' => 'upload-1', 'value' => array( 'file' => array( 'file_path' => $ui_file, 'file_url' => '' ) ) ) ) ); }
$fixture['large'] = array( 'form' => $ui_form, 'count' => 101, 'hash' => hash_file( 'sha256', $ui_file ) );
// Exercise real process deaths after close but before publishing/checkpointing the part.
foreach ( array( 'normal', 'fatal', 'kill' ) as $mode ) {
 $task = Jobs::create( $ui_form, 'all' ); $task_id = $task['id']; jobs_advance( $task_id, 'review' ); Jobs::command( $task_id, 'start' );
 putenv( 'FFP_HOLD_MODE=' . $mode ); putenv( 'FFP_JOB_ID=' . $task_id );
 $wp_cli = realpath( $_SERVER['argv'][0] );
 $child = proc_open( array( PHP_BINARY, $wp_cli, '--path=' . ABSPATH, 'eval-file', __DIR__ . '/held-task.php' ), array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
 if ( ! is_resource( $child ) ) { throw new RuntimeException( 'Cannot start synthetic task child' ); }
 $archive = trim( fgets( $pipes[1] ) );
 if ( $archive !== Package::private_root() . '/job-' . $task_id . '/package.zip' || ! is_file( $archive ) ) { proc_terminate( $child, 9 ); throw new RuntimeException( 'Child did not prepare a private task archive' ); }
 if ( 'kill' === $mode ) { proc_terminate( $child, 9 ); } else { fwrite( $pipes[0], "continue\n" ); }
 fclose( $pipes[0] ); stream_get_contents( $pipes[1] ); fclose( $pipes[1] ); $stderr = stream_get_contents( $pipes[2] ); fclose( $pipes[2] ); $exit = proc_close( $child );
 if ( 'normal' === $mode ? 0 !== $exit : 0 === $exit ) { throw new RuntimeException( 'Unexpected child status' ); }
 if ( 'fatal' === $mode && ! str_contains( $stderr, 'Synthetic task fatal failure' ) ) { throw new RuntimeException( 'Actual task fatal missing' ); }
 if ( 'kill' === $mode ) { clearstatcache(); jobs_check( is_file( $archive ), 'SIGKILL leaves only a private unpublished archive' ); }
 $done = jobs_advance( $task_id, 'ready' );
 jobs_check( 101 === $done['built'] && 2 === $done['completed_parts'], $mode . ': task resumes without duplicate parts or submissions' );
 jobs_check( ! is_dir( dirname( $archive ) ), $mode . ': task work directory reclaimed without a one-hour wait' ); jobs_remove( $task_id );
}
// Background access uses the owner, including a capability revocation after queuing.
wp_set_current_user( $other ); $task = Jobs::create( $ui_form, 'all' ); wp_set_current_user( 1 );
$other_user = get_user_by( 'id', $other ); $other_user->set_role( 'subscriber' ); Jobs::run( $task['id'] );
$private_state = json_decode( file_get_contents( Package::private_root() . '/export-' . $task['id'] . '/state.json' ), true );
jobs_check( 'error' === $private_state['status'] && 1 === get_current_user_id(), 'Background owner permission revocation blocks processing and restores caller' );
$other_user->set_role( 'administrator' ); wp_set_current_user( $other ); jobs_remove( $task['id'] ); wp_set_current_user( 1 );
// Expired parts cannot be listed even when their private bytes still exist.
$task = Jobs::create( $ui_form, 'all' ); jobs_advance( $task['id'], 'review' ); Jobs::command( $task['id'], 'start' ); jobs_advance( $task['id'], 'ready' );
$state_path = Package::private_root() . '/export-' . $task['id'] . '/state.json'; $private_state = json_decode( file_get_contents( $state_path ), true ); $private_state['expires'] = time() - 1; file_put_contents( $state_path, wp_json_encode( $private_state ) );
jobs_reject( static fn() => Jobs::parts( $task['id'], 1 ), 'Expired archives are not downloadable' ); Jobs::run( $task['id'] ); jobs_check( ! is_dir( dirname( $state_path ) ), 'Expiry removes only task-owned private artifacts' );

file_put_contents( getenv( 'FFP_TEST_FIXTURE' ), wp_json_encode( $fixture ) );
file_put_contents( $out . '/jobs-results.json', wp_json_encode( array( 'count' => count( $GLOBALS['ffp_jobs_checks'] ), 'checks' => $GLOBALS['ffp_jobs_checks'], 'resource' => $resource ), JSON_PRETTY_PRINT ) );
echo 'Large export resource: ' . wp_json_encode( $resource ) . "\n";
