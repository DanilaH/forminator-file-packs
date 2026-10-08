<?php
/** Run under an actual low-memory or ZIP-disabled PHP process. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || '1' !== getenv( 'FFP_TEST_LAB' ) ) { throw new RuntimeException( 'Disposable lab only.' ); }
wp_set_current_user( 1 ); $f = json_decode( file_get_contents( getenv( 'FFP_TEST_FIXTURE' ) ), true );
if ( 'memory' === getenv( 'FFP_RESOURCE_MODE' ) ) {
 ini_set( 'memory_limit', '80M' );
 $available = wp_convert_hr_to_bytes( ini_get( 'memory_limit' ) ) - memory_get_usage( true );
 $pressure = str_repeat( 'x', max( 0, $available - 8388608 ) );
}
$plan = ForminatorFilePacks\Planner::build( $f['submission']['form'], array( $f['submission']['entry'] ) );
try { $p = new ForminatorFilePacks\Package( $plan ); $p->cleanup(); throw new LogicException( 'Expected resource rejection' ); }
catch ( RuntimeException $e ) {
 $needle = 'nozip' === getenv( 'FFP_RESOURCE_MODE' ) ? 'ZIP support' : 'PHP memory';
 if ( ! str_contains( $e->getMessage(), $needle ) ) { throw $e; }
 echo 'PASS actual environment: ' . getenv( 'FFP_RESOURCE_MODE' );
}
