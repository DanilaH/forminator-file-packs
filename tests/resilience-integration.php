<?php
/** Failure simulations and hostile metadata in a disposable lab. */
namespace ForminatorFilePacks {
 function disk_free_space( $path ) { return 'disk' === ( $GLOBALS['ffp_fault'] ?? '' ) ? 0 : \disk_free_space( $path ); }
 function ini_get( $name ) { return 'memory' === ( $GLOBALS['ffp_fault'] ?? '' ) && 'memory_limit' === $name ? '1M' : \ini_get( $name ); }
 function microtime( $float = false ) {
  $calls = ++$GLOBALS['ffp_clock_calls'];
  return \microtime( $float ) + ( 'time' === ( $GLOBALS['ffp_fault'] ?? '' ) && $calls > 2 ? 100 : 0 );
 }
 function fopen( $path, $mode ) {
  if ( 'write' === ( $GLOBALS['ffp_fault'] ?? '' ) && 'xb' === $mode ) { return false; }
  return \fopen( $path, $mode );
 }
}
namespace {
 use ForminatorFilePacks\Adapter;
 use ForminatorFilePacks\Planner;
 use ForminatorFilePacks\Package;
 if ( ! defined( 'WP_CLI' ) || ! WP_CLI || '1' !== getenv( 'FFP_TEST_LAB' ) ) { throw new \RuntimeException( 'Disposable lab only.' ); }
 wp_set_current_user( 1 );
 $GLOBALS['ffp_fault'] = ''; $GLOBALS['ffp_clock_calls'] = 0;
 $fixture = json_decode( file_get_contents( getenv( 'FFP_TEST_FIXTURE' ) ), true );
 $form = $fixture['submission']['form']; $id = $fixture['submission']['entry'];
 $plan = Planner::build( $form, array( $id ) ); $checks = array();
 function beta_check( $ok, $name ) { if ( ! $ok ) { throw new \RuntimeException( $name ); } $GLOBALS['ffp_beta_checks'][] = $name; echo 'PASS ' . $name . "\n"; }
 foreach ( array( 'disk', 'memory', 'time', 'write' ) as $fault ) {
  $GLOBALS['ffp_fault'] = $fault; $GLOBALS['ffp_clock_calls'] = 0;
  $failed = false;
  try { $pkg = new Package( $plan, true ); $pkg->cleanup(); } catch ( \ForminatorFilePacks\StorageException $e ) { $failed = true; beta_check( ! str_contains( $e->getMessage(), ABSPATH ), $fault . ': no filesystem paths in error' ); }
  beta_check( $failed, $fault . ': abort even with partial consent' );
  $GLOBALS['ffp_fault'] = '';
  beta_check( ! glob( Package::private_root() . '/job-*' ) && ! glob( Package::private_root() . '/lock-*' ), $fault . ': no leftover archive or lock' );
 }
 $root = Package::private_root(); $old = $root . '/job-' . str_repeat( 'b', 32 ); mkdir( $old, 0700 ); file_put_contents( $old . '/package.zip', 'abandoned fixture' ); touch( $old, time() - 3700 );
 Package::private_root(); beta_check( ! file_exists( $old ), 'abandoned old job removed on next export' );
 $original = get_option( 'forminator_permissions', array() );
 $user = wp_insert_user( array( 'user_login' => 'ffp-cap-' . time(), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
 $cap = forminator_get_permission_cap_map()['forminator-entries'];
 update_option( 'forminator_permissions', array( array( 'permission_type' => 'specific', 'specific_user' => array( $user ), $cap => true ) ) );
 $reader = get_user_by( 'id', $user ); $reader->add_cap( $cap ); wp_set_current_user( $user );
 beta_check( Adapter::capability() === $cap && Planner::build( $form, array( $id ) )['file_count'] === 3, 'specific permitted user can export' );
 $reader->remove_cap( $cap ); wp_set_current_user( 0 ); wp_set_current_user( $user ); $blocked = false;
 try { Adapter::authorize(); } catch ( \RuntimeException $e ) { $blocked = true; }
 beta_check( $blocked, 'removed capability immediately blocks export' );
 wp_set_current_user( 1 ); update_option( 'forminator_permissions', $original );
 require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user( $user );
 $hostile = Forminator_API::add_form_entry( $form, array( array( 'name' => 'text-1', 'value' => '<script>alert(1)</script><img src=x onerror=alert(2)>' ) ) );
 $pkg = new Package( Planner::build( $form, array( $hostile ) ) ); $zip = new \ZipArchive(); $zip->open( $pkg->path() ); $html = $zip->getFromName( 'Request-' . $hostile . '/request.html' );
 beta_check( ! str_contains( $html, '<script>' ) && ! str_contains( $html, '<img ' ) && str_contains( $html, '&lt;script&gt;' ), 'raw hostile HTML escaped in card' ); $zip->close(); $pkg->cleanup(); Forminator_API::delete_entry( $form, $hostile );
 $repeat_source = forminator_get_upload_path( $form, 'uploads' ) . '/ffp-repeat.txt'; copy( $plan['entries'][0]['files'][0]['source'], $repeat_source );
 $repeat = Forminator_API::add_form_entry( $form, array( array( 'name' => 'upload-1-1', 'value' => array( 'file' => array( 'file_path' => $repeat_source, 'file_url' => '' ) ) ) ) );
 $repeated = Planner::build( $form, array( $repeat ) ); beta_check( $repeated['file_count'] === 1 && ! $repeated['warnings'], 'API repeated upload key maps through upstream get_field' );
 Forminator_API::delete_entry( $form, $repeat );
 $data = array( 'checks' => $GLOBALS['ffp_beta_checks'], 'count' => count( $GLOBALS['ffp_beta_checks'] ), 'faults' => 'disk/memory/time/write simulated through test-only namespace wrappers; not actual host exhaustion' );
 file_put_contents( getenv( 'FFP_TEST_ARTIFACTS' ) . '/resilience-results.json', wp_json_encode( $data, JSON_PRETTY_PRINT ) );
 echo 'Resilience checks: ' . $data['count'] . "\n";
}
