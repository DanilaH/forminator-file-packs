<?php
/** Boundary fixtures and measurements, only in a disposable native lab. */
use ForminatorFilePacks\Planner;
use ForminatorFilePacks\Package;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || '1' !== getenv( 'FFP_TEST_LAB' ) ) { throw new RuntimeException( 'Disposable lab only.' ); }
wp_set_current_user( 1 );
$form = Forminator_API::add_form( 'Capacity boundary', array(), array( 'formName' => 'Capacity boundary', 'store_submissions' => '1' ) );
Forminator_API::add_form_field( $form, 'text', array( 'field_label' => 'Serial' ) );
Forminator_API::add_form_field( $form, 'upload', array( 'field_label' => 'Files', 'file-type' => 'multiple', 'upload-method' => 'submission' ) );
$root = forminator_get_upload_path( $form, 'uploads' );
wp_mkdir_p( $root );
$ids = array(); $hashes = array(); $paths = array(); $GLOBALS['ffp_capacity_checks'] = array();
function capacity_check( $ok, $name ) { if ( ! $ok ) { throw new RuntimeException( $name ); } $GLOBALS['ffp_capacity_checks'][] = $name; echo 'PASS ' . $name . "\n"; }
function capacity_reject( $call, $message ) {
 try { $call(); } catch ( RuntimeException $e ) { capacity_check( str_contains( $e->getMessage(), $message ), 'Boundary rejection: ' . $message ); return; }
 throw new RuntimeException( 'Expected boundary rejection: ' . $message );
}
for ( $i = 0; $i < 500; ++$i ) {
 $path = $root . '/capacity-' . $i . '.txt';
 file_put_contents( $path, random_bytes( 499 === $i ? 209815 : 209715 ) );
 $paths[] = $path; $hashes[$path] = hash_file( 'sha256', $path );
 if ( 4 === $i % 5 ) {
  $ids[] = Forminator_API::add_form_entry( $form, array( array( 'name' => 'text-1', 'value' => 'CAPACITY-' . count( $ids ) ), array( 'name' => 'upload-1', 'value' => array( 'file' => array( 'file_path' => array_slice( $paths, -5 ), 'file_url' => array_fill( 0, 5, '' ) ) ) ) ) );
 }
}
$original_memory_limit = ini_get( 'memory_limit' );
ini_set( 'memory_limit', '128M' );
capacity_check( '128M' === ini_get( 'memory_limit' ), 'Real 128 MiB PHP memory limit enabled for planning and assembly' );
$started = microtime( true ); $plan = Planner::build( $form, $ids );
capacity_check( 100 === count( $plan['entries'] ) && 500 === $plan['file_count'] && 104857600 === $plan['bytes'] && ! $plan['warnings'], 'Exact combined ceiling: 100 submissions, 500 files, 100 MiB' );
$package = new Package( $plan );
$measurement = array( 'seconds' => round( microtime( true ) - $started, 3 ), 'peak_memory_bytes' => memory_get_peak_usage( true ), 'zip_bytes' => filesize( $package->path() ), 'php_memory_limit' => ini_get( 'memory_limit' ), 'api_seeded' => true );
$zip = new ZipArchive(); $zip->open( $package->path() ); $members = array();
foreach ( $plan['entries'] as $entry ) { foreach ( $entry['files'] as $file ) {
 $member = $entry['folder'] . '/' . $file['name']; $members[] = $member;
 if ( $hashes[$file['path']] !== hash( 'sha256', $zip->getFromName( $member ) ) ) { throw new RuntimeException( 'Incorrect attachment bytes.' ); }
} }
capacity_check( true, 'All 500 ZIP attachments match source SHA-256 hashes' );
capacity_check( 500 === count( array_unique( $members ) ), '500 distinct ZIP members' );
$zip->close(); $package->cleanup();
ini_set( 'memory_limit', $original_memory_limit );
foreach ( $hashes as $path => $hash ) { if ( $hash !== hash_file( 'sha256', $path ) ) { throw new RuntimeException( 'Source changed.' ); } }
capacity_check( true, 'All 500 source files retain original bytes' );
$extra = Forminator_API::add_form_entry( $form, array( array( 'name' => 'text-1', 'value' => 'EXTRA' ) ) );
capacity_reject( static fn() => Planner::build( $form, array_merge( $ids, array( $extra ) ) ), 'between 1 and 100' );
$handle = fopen( $paths[499], 'ab' ); fwrite( $handle, 'x' ); fclose( $handle ); clearstatcache();
capacity_reject( static fn() => Planner::build( $form, $ids ), '100 MiB or 500 files' );
$handle = fopen( $paths[499], 'r+' ); ftruncate( $handle, 209815 ); fclose( $handle ); clearstatcache();
$tiny = $root . '/overflow.txt'; file_put_contents( $tiny, 'Overflow' );
$overflow = Forminator_API::add_form_entry( $form, array( array( 'name' => 'upload-1', 'value' => array( 'file' => array( 'file_path' => array_fill( 0, 501, $tiny ), 'file_url' => array_fill( 0, 501, '' ) ) ) ) ) );
capacity_reject( static fn() => Planner::build( $form, array( $overflow ) ), '100 MiB or 500 files' );
$huge = Forminator_API::add_form_entry( $form, array( array( 'name' => 'text-1', 'value' => str_repeat( 'x', 1048577 ) ) ) );
capacity_reject( static fn() => Planner::build( $form, array( $huge ) ), 'field is too large' );
$metadata_ids = array();
for ( $i = 0; $i < 9; ++$i ) { $metadata_ids[] = Forminator_API::add_form_entry( $form, array( array( 'name' => 'text-1', 'value' => str_repeat( 'x', 1048576 ) ) ) ); }
capacity_reject( static fn() => Planner::build( $form, $metadata_ids ), '8 MiB metadata' );
foreach ( array_merge( array( $extra, $overflow, $huge ), $metadata_ids ) as $id ) { Forminator_API::delete_entry( $form, $id ); }
capacity_check( ! glob( Package::private_root() . '/job-*' ) && ! glob( Package::private_root() . '/lock-*' ), 'No private archive or lock remains' );
$out = getenv( 'FFP_TEST_ARTIFACTS' );
file_put_contents( $out . '/capacity-fixture.json', wp_json_encode( array( 'form' => $form, 'ids' => $ids, 'hashes' => array_values( $hashes ) ) ) );
file_put_contents( $out . '/capacity-results.json', wp_json_encode( array( 'count' => count( $GLOBALS['ffp_capacity_checks'] ), 'checks' => $GLOBALS['ffp_capacity_checks'], 'resource' => $measurement ), JSON_PRETTY_PRINT ) );
echo 'Capacity resource: ' . wp_json_encode( $measurement ) . "\n";
