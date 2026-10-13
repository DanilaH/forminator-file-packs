<?php
/** Child paused after a real ZIP is closed, before its task checkpoint. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || '1' !== getenv( 'FFP_TEST_LAB' ) ) { throw new RuntimeException( 'Disposable lab only.' ); }
add_action( 'ffp_export_part_prepared', static function ( $id ) {
 echo ForminatorFilePacks\Package::private_root() . '/job-' . $id . '/package.zip' . "\n"; flush();
 fgets( STDIN );
 if ( 'fatal' === getenv( 'FFP_HOLD_MODE' ) ) { trigger_error( 'Synthetic task fatal failure', E_USER_ERROR ); }
}, 10, 1 );
wp_set_current_user( 0 ); ForminatorFilePacks\Jobs::run( getenv( 'FFP_JOB_ID' ) );
