<?php
/** Actual owned-container runner; no browser requests or direct processing calls. */
use ForminatorFilePacks\Jobs;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || '1' !== getenv( 'FFP_TEST_LAB' ) || '1' !== getenv( 'FFP_STAGING_TEST' ) || file_exists( getenv( 'FFP_TEST_FIXTURE' ) ) ) { throw new RuntimeException( 'Owned staging after fixture cleanup only.' ); }
wp_set_current_user( 1 );
$snapshot = static function () { ob_start(); require __DIR__ . '/lifecycle-snapshot.php'; return ob_get_clean(); };
$before = $snapshot(); $form = 0; $task = null; $checks = array();
$wait = static function ( $id, $target ) {
 $deadline = microtime( true ) + 100;
 do {
  $state = Jobs::status( $id ); // Read only: deliberately do not call tick/run.
  if ( $target === $state['status'] ) { return $state; }
  if ( 'error' === $state['status'] ) { throw new RuntimeException( $state['error'] ); }
  usleep( 500000 );
 } while ( microtime( true ) < $deadline );
 throw new RuntimeException( 'Container runner did not reach ' . $target . ' without browser requests' );
};
try {
 $form = Forminator_API::add_form( 'Unattended export lab', array(), array( 'formName' => 'Unattended export lab', 'store_submissions' => '1' ) );
 update_post_meta( $form, '_ffp_staging_fixture', '1' );
 Forminator_API::add_form_field( $form, 'text', array( 'field_label' => 'Serial' ) );
 $entry = Forminator_API::add_form_entry( $form, array( array( 'name' => 'text-1', 'value' => 'UNATTENDED-юникод' ) ) );
 $task = Jobs::create( $form, 'all' ); $state = $wait( $task['id'], 'review' );
 if ( 1 !== $state['checked'] ) { throw new RuntimeException( 'Incorrect unattended checked count' ); }
 $checks[] = 'Actual container WP-CLI cron runner discovers/checks without a browser';
 // Simulate the fresh request that starts a checked task; another process changed cron options.
 if ( function_exists( 'wp_cache_flush_runtime' ) ) { wp_cache_flush_runtime(); }
 Jobs::command( $task['id'], 'start' ); $state = $wait( $task['id'], 'ready' );
 if ( 1 !== $state['built'] || 1 !== $state['completed_parts'] ) { throw new RuntimeException( 'Incorrect unattended completion' ); }
 $checks[] = 'Actual container runner builds and commits ZIP without status processing calls';
 $parts = Jobs::parts( $task['id'], 1 );
 if ( 1 !== $parts['rows'][0]['submissions'] ) { throw new RuntimeException( 'Incorrect unattended part' ); }
} finally {
 if ( $task ) {
  Jobs::command( $task['id'], 'cancel' );
  for ( $i = 0; $i < 10; ++$i ) { Jobs::run( $task['id'] ); if ( ! is_dir( ForminatorFilePacks\Package::private_root() . '/export-' . $task['id'] ) ) { break; } usleep( 200000 ); }
 }
 if ( $form && '1' === get_post_meta( $form, '_ffp_staging_fixture', true ) ) {
  $root = dirname( ( new ReflectionClass( 'Forminator_API' ) )->getFileName(), 2 ) . '/';
  require_once $root . 'admin/abstracts/class-admin-page.php'; require_once $root . 'admin/abstracts/class-admin-module-edit-page.php';
  Forminator_API::delete_form( $form );
 }
}
if ( $before !== $snapshot() ) { throw new RuntimeException( 'Unrelated source data changed during unattended test' ); }
$checks[] = 'Unrelated Forminator source snapshot unchanged; synthetic unattended form removed';
foreach ( $checks as $check ) { echo 'PASS ' . $check . "\n"; }
file_put_contents( getenv( 'FFP_TEST_ARTIFACTS' ) . '/jobs-unattended-results.json', wp_json_encode( array( 'checks' => $checks, 'count' => count( $checks ) ), JSON_PRETTY_PRINT ) );
