<?php
/** Real environment probe, archive naming and timezone/calendar boundaries. */
use ForminatorFilePacks\Admin;
use ForminatorFilePacks\Package;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || '1' !== getenv( 'FFP_TEST_LAB' ) ) { throw new RuntimeException( 'Disposable lab only.' ); }
wp_set_current_user( 1 );
$checks = array();
$check = static function ( $ok, $name ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( $name ); } $checks[] = $name; echo 'PASS ' . $name . "\n"; };
$snapshot = static function () { ob_start(); require __DIR__ . '/lifecycle-snapshot.php'; return ob_get_clean(); };
$before = $snapshot();
$root = Package::private_root();
$before_probes = glob( $root . '/probe-*' ) ?: array();
for ( $i = 0; $i < 2; ++$i ) {
 $result = Package::readiness();
 $check( $result['ready'] && $result['zip'] && $result['storage'] && ! $result['messages'], 'actual ZIP support and bounded private write available, pass ' . $i );
}
$check( $before_probes === ( glob( $root . '/probe-*' ) ?: array() ), 'readiness probes leave no files or export locks' );
$check( $before === $snapshot(), 'readiness checks preserve all source forms, entries, metadata and uploads' );
foreach ( array( 'UTC', 'Pacific/Kiritimati', 'America/Los_Angeles' ) as $zone ) {
 $now = new DateTimeImmutable( '2026-01-01 00:30:00', new DateTimeZone( $zone ) );
 $periods = Admin::periods( $now );
 $check( '2025-12-26' === $periods['week']['from'] && '2026-01-01' === $periods['week']['to'], 'seven inclusive site-calendar days over year boundary in ' . $zone );
 $check( '2026-01-01' === $periods['month']['from'] && '2026-01-01' === $periods['month']['to'], 'month starts at site-local midnight in ' . $zone );
}
$p = Admin::periods( new DateTimeImmutable( '2024-03-01', new DateTimeZone( 'UTC' ) ) );
$check( '2024-02-24' === $p['week']['from'], 'seven-day period includes leap-day boundary' );
$p = Admin::periods( new DateTimeImmutable( '2026-03-10', new DateTimeZone( 'America/New_York' ) ) );
$check( '2026-03-04' === $p['week']['from'] && '2026-03-10' === $p['week']['to'], 'calendar-day period survives daylight-saving transition' );
foreach ( array( 'Service requests', 'Заявки на обслуживание', "../../CON\r\n\";evil=<script>x</script>\\\\?*", str_repeat( 'Я', 500 ), '' ) as $title ) {
 $name = Admin::download_name( array( 'form_id' => 99, 'form_title' => $title ) );
 $check( 1 === preg_match( '/^form-99-.+-\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.zip$/u', $name ) && strlen( $name ) < 220 && ! preg_match( '/[\x00-\x1f\x7f<>:"\/\\\\|?*]/u', $name ), 'safe, bounded and dated archive filename for title ' . count( $checks ) );
}
file_put_contents( getenv( 'FFP_TEST_ARTIFACTS' ) . '/environment-check-results.json', wp_json_encode( array( 'count' => count( $checks ), 'checks' => $checks ), JSON_PRETTY_PRINT ) );
