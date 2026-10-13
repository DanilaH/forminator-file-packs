<?php
/** A nonexistent, test-owned private root must produce actionable unready state. */
use ForminatorFilePacks\Package;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || '1' !== getenv( 'FFP_TEST_LAB' ) ) { throw new RuntimeException( 'Disposable lab only.' ); }
if ( defined( 'FFP_PRIVATE_TEMP_DIR' ) ) { throw new RuntimeException( 'Unexpected preconfigured private root in lab.' ); }
define( 'FFP_PRIVATE_TEMP_DIR', __DIR__ . '/ffp-nonexistent-' . bin2hex( random_bytes( 12 ) ) );
$r = Package::readiness();
if ( $r['ready'] || $r['storage'] || ! $r['messages'] || str_contains( wp_json_encode( $r ), FFP_PRIVATE_TEMP_DIR ) || file_exists( FFP_PRIVATE_TEMP_DIR ) ) { throw new RuntimeException( 'Invalid private root was accepted or exposed.' ); }
echo "PASS invalid private storage gives actionable unready state without paths or source mutations\n";
file_put_contents( getenv( 'FFP_TEST_ARTIFACTS' ) . '/readiness-unavailable-results.json', wp_json_encode( array( 'count' => 1, 'checks' => array( 'Invalid private storage rejected without exposing paths or creating source files' ) ), JSON_PRETTY_PRINT ) );
