<?php
/** Terminate only a child export process on the owned synthetic Docker site. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || '1' !== getenv( 'FFP_TEST_LAB' ) || '1' !== getenv( 'FFP_STAGING_TEST' ) ) { throw new RuntimeException( 'Owned staging only.' ); }
wp_set_current_user( 1 );
$fixture = json_decode( file_get_contents( getenv( 'FFP_TEST_FIXTURE' ) ), true );
$checks = array();
$snapshot = static function () { ob_start(); require '/opt/ffp/tests/lifecycle-snapshot.php'; return ob_get_clean(); };
$before = $snapshot();
foreach ( array( 'normal', 'fatal', 'kill' ) as $mode ) {
 putenv( 'FFP_HOLD_MODE=' . $mode );
 $process = proc_open( array( PHP_BINARY, '/usr/local/bin/wp', '--path=' . ABSPATH, 'eval-file', '/opt/ffp/tests/held-export.php' ), array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
 if ( ! is_resource( $process ) ) { throw new RuntimeException( 'Could not start synthetic child export.' ); }
 $archive = trim( fgets( $pipes[1] ) );
 if ( dirname( dirname( $archive ) ) !== ForminatorFilePacks\Package::private_root() || ! is_file( $archive ) ) { proc_terminate( $process, 9 ); throw new RuntimeException( 'Child archive must be in plugin private storage.' ); }
 if ( 'kill' === $mode ) {
  if ( ! proc_terminate( $process, 9 ) ) { throw new RuntimeException( 'Could not terminate own child process.' ); }
 } else { fwrite( $pipes[0], "continue\n" ); }
 fclose( $pipes[0] );
 $stderr = stream_get_contents( $pipes[2] ); fclose( $pipes[2] );
 stream_get_contents( $pipes[1] ); fclose( $pipes[1] );
 $exit = proc_close( $process ); clearstatcache();
 if ( 'normal' === $mode ? 0 !== $exit : 0 === $exit ) { throw new RuntimeException( 'Unexpected child exit status.' ); }
 if ( 'fatal' === $mode && ! str_contains( $stderr, 'Synthetic fatal failure' ) ) { throw new RuntimeException( 'Expected actual synthetic PHP fatal error.' ); }
 if ( 'kill' === $mode ) {
  if ( ! is_file( $archive ) ) { throw new RuntimeException( 'Abrupt exit should leave a private abandoned archive.' ); }
  touch( dirname( $archive ), time() - 3700 );
  touch( ForminatorFilePacks\Package::private_root() . '/lock-1', time() - 3700 );
  $package = new ForminatorFilePacks\Package( ForminatorFilePacks\Planner::build( $fixture['submission']['form'], array( $fixture['submission']['entry'] ) ) );
  $package->cleanup(); clearstatcache();
 }
 if ( file_exists( $archive ) ) { throw new RuntimeException( 'Private archive remained after recovery.' ); }
 $checks[] = $mode . ': real child exit and private archive cleanup';
 echo 'PASS ' . end( $checks ) . "\n";
}
if ( $before !== $snapshot() ) { throw new RuntimeException( 'Sources changed during shutdown checks.' ); }
$checks[] = 'source snapshot intact after real process exits';
file_put_contents( getenv( 'FFP_TEST_ARTIFACTS' ) . '/staging-shutdown-results.json', wp_json_encode( array( 'checks' => $checks, 'count' => count( $checks ), 'scope' => 'Normal exit, real PHP fatal and SIGKILL of our child export only; Docker/server crash not simulated' ), JSON_PRETTY_PRINT ) );
