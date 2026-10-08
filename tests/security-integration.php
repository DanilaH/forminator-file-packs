<?php
/** Negative cases and data integrity in a disposable WP-CLI lab. */
use ForminatorFilePacks\Adapter;
use ForminatorFilePacks\Planner;
use ForminatorFilePacks\Package;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || '1' !== getenv( 'FFP_TEST_LAB' ) ) { throw new RuntimeException( 'Disposable lab only.' ); }
wp_set_current_user( 1 );
$fixture = json_decode( file_get_contents( getenv( 'FFP_TEST_FIXTURE' ) ), true );
$form = $fixture['submission']['form']; $id = $fixture['submission']['entry'];
$GLOBALS['ffp_test_checks'] = array();
function verify( $condition, $name ) { if ( ! $condition ) { throw new RuntimeException( $name ); } $GLOBALS['ffp_test_checks'][] = $name; echo 'PASS ' . $name . "\n"; }
function rejected( $fn, $name ) { try { $fn(); } catch ( RuntimeException $e ) { verify( true, $name ); return; } throw new RuntimeException( 'Expected rejection: ' . $name ); }
function source_state( $form, $id ) {
 global $wpdb;
 $table = Forminator_Database_Tables::get_table_name( Forminator_Database_Tables::FORM_ENTRY_META );
 $entry_table = Forminator_Database_Tables::get_table_name( Forminator_Database_Tables::FORM_ENTRY );
 $plan = Planner::build( $form, array( $id ) ); $hashes = array();
 foreach ( $plan['entries'][0]['files'] as $file ) { $hashes[$file['path']] = hash_file( 'sha256', $file['path'] ); }
 return hash( 'sha256', serialize( array( get_post( $form ), get_post_meta( $form ), $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE entry_id = %d ORDER BY meta_id", $id ), ARRAY_A ), $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$entry_table} WHERE entry_id = %d", $id ), ARRAY_A ), $hashes ) ) );
}
$before = source_state( $form, $id );
$plan = Planner::build( $form, array( $id ) ); $pkg = new Package( $plan );
$path = $pkg->path();
verify( ! Adapter::within( realpath( $path ), realpath( ABSPATH ) ), 'archive outside WordPress web root' );
verify( 0 === ( fileperms( dirname( $path ) ) & 0077 ), 'private job permissions' );
rejected( static fn() => new Package( $plan ), 'concurrent export lock' );
$pkg->cleanup(); verify( ! file_exists( $path ), 'archive removed after cleanup' );
verify( $before === source_state( $form, $id ), 'form, entry, metadata and original hashes unchanged' );
$empty = Planner::build( $form, array( $fixture['submission']['empty_entry'] ) );
verify( 0 === $empty['file_count'] && ! $empty['warnings'], 'no-file entry is complete' );
rejected( static fn() => Planner::build( $form, array() ), 'empty selection rejected' );
rejected( static fn() => Planner::build( $form, range( 1, 101 ) ), 'entry safety limit' );
rejected( static fn() => Adapter::entry( $form, $fixture['media']['entry'] ), 'cross-form ownership check' );
wp_set_current_user( 0 ); rejected( static fn() => Planner::build( $form, array( $id ) ), 'anonymous planner access rejected' );
$subscriber = wp_insert_user( array( 'user_login' => 'ffp-test-reader-' . time(), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
wp_set_current_user( $subscriber ); rejected( static fn() => Adapter::forms(), 'subscriber access rejected' );
wp_set_current_user( 1 );
$known = $plan['entries'][0]['files'][0]; $root = forminator_get_upload_path( $form, 'uploads' );
$external = tempnam( sys_get_temp_dir(), 'ffp-fixture-' ); file_put_contents( $external, 'Synthetic outside-storage file' );
rejected( static fn() => Adapter::resolve_file( $form, $external, '', 0 ), 'outside upload storage rejected' );
$symlink = $root . '/ffp-test-symlink.txt'; symlink( $external, $symlink );
rejected( static fn() => Adapter::resolve_file( $form, $symlink, '', 0 ), 'symlink escape rejected' ); unlink( $symlink ); unlink( $external );
rejected( static fn() => Adapter::resolve_file( $form, 's3://bucket/file.txt', '', 0 ), 'remote stream rejected' );
rejected( static fn() => Adapter::resolve_file( $form, $known['source'], 'https://remote.example/file.txt', 0 ), 'remote URL rejected without retrieval' );
$other = forminator_get_upload_path( $fixture['ajax']['form'], 'uploads' ) . '/ffp-unrelated.txt'; file_put_contents( $other, 'Unrelated synthetic file' );
rejected( static fn() => Adapter::resolve_file( $form, $other, '', 0 ), 'unrelated form file rejected' ); unlink( $other );
$active = $root . '/ffp-active.html'; file_put_contents( $active, '<script>alert(1)</script>' );
rejected( static fn() => Adapter::resolve_file( $form, $active, '', 0 ), 'active HTML attachment excluded' ); unlink( $active );
// Deliberately damaged metadata uses API-created fixtures, separate from real frontend cases.
$broken = Forminator_API::add_form_entry( $form, array( array( 'name' => 'text-1', 'value' => '=HYPERLINK("test")' ), array( 'name' => 'upload-1', 'value' => array( 'file' => array( 'file_path' => $root . '/missing.txt', 'file_url' => '' ) ) ) ) );
$damaged = Planner::build( $form, array( $broken ) );
verify( 1 === count( $damaged['warnings'] ) && 0 === $damaged['file_count'], 'missing attachment explicitly reported' );
$pkg = new Package( $damaged, true ); $zip = new ZipArchive(); $zip->open( $pkg->path() );
verify( str_contains( $zip->getFromName( 'index.html' ), 'Incomplete package' ) && str_contains( $zip->getFromName( 'warnings.json' ), 'missing' ), 'incomplete archive includes warnings' );
verify( str_contains( $zip->getFromName( 'register.csv' ), "'=HYPERLINK" ), 'CSV formula escaped' ); $zip->close(); $pkg->cleanup();
$collision = Forminator_API::add_form_entry( $form, array( array( 'name' => 'upload-2', 'value' => array( 'file' => array( 'file_path' => array( $known['source'], $known['source'] ), 'file_url' => array( '', '' ) ) ) ) ) );
$dupe = Planner::build( $form, array( $collision ) );
verify( 2 === $dupe['file_count'] && $dupe['entries'][0]['files'][0]['name'] !== $dupe['entries'][0]['files'][1]['name'], 'identical attachment names get unique members' );
$unsafe = Planner::name( '../../CON<>:"test?.txt' ); verify( ! str_contains( $unsafe, '/' ) && ! str_contains( $unsafe, '..' ) && ! str_contains( $unsafe, ':' ), 'ZIP member names normalized' );
foreach ( array( '=1+1', ' +SUM(A1)', "\t=1", '@SUM(A1)', '-2' ) as $value ) { verify( str_starts_with( Package::csv_cell( $value ), "'" ), 'CSV injection case: ' . json_encode( $value ) ); }
// Data changed after planning: strict export refuses, explicitly partial export records the new omission.
$racefile = $root . '/ffp-race.txt'; file_put_contents( $racefile, 'Race fixture' );
$race_id = Forminator_API::add_form_entry( $form, array( array( 'name' => 'upload-1', 'value' => array( 'file' => array( 'file_path' => $racefile, 'file_url' => '' ) ) ) ) );
$race = Planner::build( $form, array( $race_id ) ); unlink( $racefile ); clearstatcache();
rejected( static fn() => new Package( $race, false ), 'new omission fails strict export' );
$pkg = new Package( $race, true ); verify( 1 === count( $pkg->plan['warnings'] ) && 0 === $pkg->plan['file_count'], 'new omission marked in partial export' ); $pkg->cleanup();
// Resource probe is an API fixture, not a claim of frontend upload compatibility at this size.
$large = $root . '/ffp-resource.txt'; $handle = fopen( $large, 'wb' ); for ( $i = 0; $i < 20; ++$i ) { fwrite( $handle, random_bytes( 1048576 ) ); } fclose( $handle );
$large_id = Forminator_API::add_form_entry( $form, array( array( 'name' => 'upload-1', 'value' => array( 'file' => array( 'file_path' => $large, 'file_url' => '' ) ) ) ) );
$start = microtime( true ); $pkg = new Package( Planner::build( $form, array( $large_id ) ) );
$resource = array( 'input_bytes' => filesize( $large ), 'zip_bytes' => filesize( $pkg->path() ), 'seconds' => round( microtime( true ) - $start, 3 ), 'peak_memory_bytes' => memory_get_peak_usage( true ) );
verify( $pkg->plan['bytes'] === filesize( $large ), '20 MiB resource fixture fully exported' ); $pkg->cleanup(); unlink( $large );
verify( ! glob( Package::private_root() . '/job-*' ) && ! glob( Package::private_root() . '/lock-*' ), 'private jobs and locks cleaned after failures and successes' );
// Restore synthetic fixtures; no uninstall hook deletes source submissions.
foreach ( array( $broken, $collision, $race_id, $large_id ) as $fixture_id ) { Forminator_API::delete_entry( $form, $fixture_id ); }
require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user( $subscriber );
$artifacts = getenv( 'FFP_TEST_ARTIFACTS' );
if ( $artifacts ) { file_put_contents( $artifacts . '/security-results.json', wp_json_encode( array( 'checks' => $GLOBALS['ffp_test_checks'], 'count' => count( $GLOBALS['ffp_test_checks'] ), 'resource' => $resource ), JSON_PRETTY_PRINT ) ); }
echo 'Security checks: ' . count( $GLOBALS['ffp_test_checks'] ) . '. Resource probe: ' . wp_json_encode( $resource ) . "\n";
