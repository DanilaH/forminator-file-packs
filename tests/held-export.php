<?php
/** Child process used to verify shutdown and abrupt process termination. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || '1' !== getenv( 'FFP_TEST_LAB' ) ) { throw new RuntimeException( 'Disposable lab only.' ); }
wp_set_current_user( 1 );
$f = json_decode( file_get_contents( getenv( 'FFP_TEST_FIXTURE' ) ), true );
$p = new ForminatorFilePacks\Package( ForminatorFilePacks\Planner::build( $f['submission']['form'], array( $f['submission']['entry'] ) ) );
echo $p->path() . "\n"; flush();
// Parent observes the real archive before allowing the Docker child to exit.
if ( '1' === getenv( 'FFP_STAGING_TEST' ) ) { fgets( STDIN ); }
if ( 'fatal' === getenv( 'FFP_HOLD_MODE' ) ) { trigger_error( 'Synthetic fatal failure', E_USER_ERROR ); }
if ( 'kill' === getenv( 'FFP_HOLD_MODE' ) ) { while ( true ) { usleep( 100000 ); } }
