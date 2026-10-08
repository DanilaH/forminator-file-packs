<?php
/** A real short write through a test-only namespace hook; never load from the plugin. */
namespace ForminatorFilePacks {
 function fwrite( $stream, $data, $length = null ) {
  if ( ! empty( $GLOBALS['ffp_json_short_write'] ) && str_ends_with( stream_get_meta_data( $stream )['uri'], '/data.json' ) ) { return \fwrite( $stream, substr( $data, 0, 1 ) ); }
  return null === $length ? \fwrite( $stream, $data ) : \fwrite( $stream, $data, $length );
 }
}
namespace {
 if ( ! defined( 'WP_CLI' ) || ! WP_CLI || '1' !== getenv( 'FFP_TEST_LAB' ) ) { throw new \RuntimeException( 'Disposable lab only.' ); }
 wp_set_current_user( 1 );
 $f = json_decode( file_get_contents( getenv( 'FFP_TEST_FIXTURE' ) ), true );
 $plan = \ForminatorFilePacks\Planner::build( $f['submission']['form'], array( $f['submission']['entry'] ) );
 $hashes = array(); foreach ( $plan['entries'][0]['files'] as $file ) { $hashes[$file['path']] = hash_file( 'sha256', $file['path'] ); }
 $GLOBALS['ffp_json_short_write'] = true; $aborted = false;
 try { $package = new \ForminatorFilePacks\Package( $plan, true ); $package->cleanup(); }
 catch ( \ForminatorFilePacks\StorageException $e ) { $aborted = true; }
 finally { $GLOBALS['ffp_json_short_write'] = false; }
 if ( ! $aborted || glob( \ForminatorFilePacks\Package::private_root() . '/job-*' ) || glob( \ForminatorFilePacks\Package::private_root() . '/lock-*' ) ) { throw new \RuntimeException( 'JSON short write must abort and clean even with partial consent.' ); }
 foreach ( $hashes as $path => $hash ) { if ( $hash !== hash_file( 'sha256', $path ) ) { throw new \RuntimeException( 'Original changed during failed JSON write.' ); } }
 file_put_contents( getenv( 'FFP_TEST_ARTIFACTS' ) . '/json-write-results.json', wp_json_encode( array( 'short_write_aborts_with_partial_consent' => true, 'private_cleanup' => true, 'original_attachment_hashes_unchanged' => true, 'fault_scope' => 'Test-only namespace hook, not actual host disk exhaustion' ) ) );
 echo "PASS JSON short write aborts with partial consent, cleans private files and preserves originals\n";
}
