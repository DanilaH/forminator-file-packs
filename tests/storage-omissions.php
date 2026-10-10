<?php
/** Actual unreadable/type/removed-field cases on synthetic local uploads only. */
use ForminatorFilePacks\Adapter;
use ForminatorFilePacks\Planner;
use ForminatorFilePacks\Package;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || '1' !== getenv( 'FFP_TEST_LAB' ) ) { throw new RuntimeException( 'Disposable lab only.' ); }
wp_set_current_user( 1 );
$fixture = json_decode( file_get_contents( getenv( 'FFP_TEST_FIXTURE' ) ), true );
$form = $fixture['submission']['form'];
$root = forminator_get_upload_path( $form, 'uploads' );
$checks = array(); $created = array(); $files = array();
$check = static function ( $ok, $name ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( $name ); } $checks[] = $name; echo 'PASS ' . $name . "\n"; };
$snapshot = static function () { ob_start(); require __DIR__ . '/lifecycle-snapshot.php'; return ob_get_clean(); };
$before = $snapshot();
try {
 foreach ( array( 'unreadable' => 'txt', 'svg' => 'svg', 'script' => 'js', 'php' => 'php', 'unknown-type' => 'ffpunknown', 'removed-field' => 'txt' ) as $mode => $ext ) {
  $path = $root . '/ffp-storage-' . bin2hex( random_bytes( 6 ) ) . '.' . $ext;
  $files[] = $path;
  file_put_contents( $path, 'Synthetic omission fixture ' . $mode );
  $hash = hash_file( 'sha256', $path );
  $key = 'removed-field' === $mode ? 'upload-removed-ffp' : 'upload-1';
  $id = Forminator_API::add_form_entry( $form, array( array( 'name' => $key, 'value' => array( 'file' => array( 'file_path' => $path, 'file_url' => '' ) ) ) ) );
  $check( is_int( $id ) && $id > 0, $mode . ': synthetic entry created' ); $created[] = $id;
  $metadata = serialize( Adapter::entry( $form, $id )->meta_data );
  if ( 'unreadable' === $mode ) {
   chmod( $path, 0000 ); clearstatcache( true, $path );
   $check( ! is_readable( $path ), 'actual chmod removes PHP read access; lab must run without root privileges' );
  }
  $plan = Planner::build( $form, array( $id ) );
  $check( 0 === $plan['file_count'] && 1 === count( $plan['warnings'] ), $mode . ': explicit omission in preview' );
  $failed = false;
  try { $package = new Package( $plan ); $package->cleanup(); } catch ( RuntimeException $e ) { $failed = true; }
  $check( $failed, $mode . ': strict package rejects omission' );
  $package = new Package( $plan, true );
  try {
   $zip = new ZipArchive(); $check( true === $zip->open( $package->path() ), $mode . ': partial ZIP opens' );
   try {
    $json = json_decode( $zip->getFromName( 'data.json' ), true, 512, JSON_THROW_ON_ERROR );
    $warnings = json_decode( $zip->getFromName( 'warnings.json' ), true, 512, JSON_THROW_ON_ERROR );
    $check( 'incomplete' === $json['status'] && 0 === $json['attachment_count'] && 1 === count( $json['warnings'] ) && $warnings === $json['warnings'] && 5 === $zip->numFiles,
      $mode . ': downloaded format contains cards, zero attachments and matching warnings' );
   } finally { $zip->close(); }
  } finally { $package->cleanup(); }
  $check( $metadata === serialize( Adapter::entry( $form, $id )->meta_data ), $mode . ': stored entry unchanged by export' );
  if ( 'unreadable' === $mode ) { $check( 0 === ( fileperms( $path ) & 0777 ), 'export leaves original unreadable permissions unchanged' ); }
  chmod( $path, 0600 ); clearstatcache( true, $path );
  $check( $hash === hash_file( 'sha256', $path ), $mode . ': original file bytes unchanged' );
 }
} finally {
 foreach ( $files as $path ) { if ( is_file( $path ) ) { chmod( $path, 0600 ); unlink( $path ); } }
 foreach ( $created as $id ) { Forminator_API::delete_entry( $form, $id ); }
}
$check( $before === $snapshot(), 'forms, entries, metadata and source upload hashes preserved after synthetic cleanup' );
$check( ! glob( Package::private_root() . '/job-*' ) && ! glob( Package::private_root() . '/lock-*' ), 'omission tests leave no private archive or lock' );
file_put_contents( getenv( 'FFP_TEST_ARTIFACTS' ) . '/storage-omissions-results.json', wp_json_encode( array( 'count' => count( $checks ), 'checks' => $checks, 'scope' => 'Real local file permissions and selected unsupported types; no third-party offload integration' ), JSON_PRETTY_PRINT ) );
