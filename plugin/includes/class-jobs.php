<?php
/** Resumable private export tasks. @package ForminatorFilePacks */
namespace ForminatorFilePacks;
if ( ! defined( 'ABSPATH' ) ) { exit; }
// Native local streams are required for atomic rename, flock, bounded reads and private permissions.
// phpcs:disable WordPress.WP.AlternativeFunctions
final class Jobs {
 const PART_ENTRIES = 1000;
 const PART_FILES = 500;
 const PART_BYTES = 16777216;
 const PART_TEXT = 1048576;
 const RETENTION = 86400;
 private static function path( $id ) {
  if ( ! is_string( $id ) || ! preg_match( '/^[a-f0-9]{32}$/D', $id ) ) { throw new \RuntimeException( esc_html__( 'Invalid export task.', 'file-packs-for-forminator' ) ); }
  $path = Package::private_root() . '/export-' . $id;
  if ( is_link( $path ) || ! is_dir( $path ) || realpath( $path ) !== $path ) { throw new \RuntimeException( esc_html__( 'The export task expired or its private files are unavailable. Check the selection again.', 'file-packs-for-forminator' ) ); }
  return $path;
 }
 private static function lock( $shared = false ) {
  $path = Package::private_root() . '/exports.lock';
  if ( is_link( $path ) ) { throw new StorageException( esc_html__( 'Private storage is unavailable.', 'file-packs-for-forminator' ) ); }
  $stream = @fopen( $path, 'c+b' );
  if ( ! $stream || ! @chmod( $path, 0600 ) ) { if ( $stream ) { fclose( $stream ); } throw new StorageException( esc_html__( 'Private storage is unavailable.', 'file-packs-for-forminator' ) ); }
  if ( ! flock( $stream, ( $shared ? LOCK_SH : LOCK_EX ) | LOCK_NB ) ) { fclose( $stream ); return false; }
  return $stream;
 }
 private static function read( $path ) {
  if ( is_link( $path ) || ! is_file( $path ) || filesize( $path ) > 4194304 ) { throw new StorageException( esc_html__( 'Export progress is unavailable. Check the selection again.', 'file-packs-for-forminator' ) ); }
  $data = json_decode( file_get_contents( $path ), true );
  if ( ! is_array( $data ) ) { throw new StorageException( esc_html__( 'Export progress is unavailable. Check the selection again.', 'file-packs-for-forminator' ) ); }
  return $data;
 }
 private static function write( $path, $data ) {
  $json = wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR );
  if ( strlen( $json ) > 4194304 ) { throw new StorageException( esc_html__( 'Export progress data is too large.', 'file-packs-for-forminator' ) ); }
  $temp = $path . '.tmp';
  if ( is_link( $path ) || is_link( $temp ) ) { throw new StorageException( esc_html__( 'Private storage is unavailable.', 'file-packs-for-forminator' ) ); }
  $stream = @fopen( $temp, 'wb' );
  try {
   if ( ! $stream || ! chmod( $temp, 0600 ) || strlen( $json ) !== fwrite( $stream, $json ) || ! fflush( $stream ) ) { throw new StorageException( esc_html__( 'Could not save export progress.', 'file-packs-for-forminator' ) ); }
  } finally { if ( $stream ) { fclose( $stream ); } }
  if ( ! rename( $temp, $path ) ) { throw new StorageException( esc_html__( 'Could not save export progress.', 'file-packs-for-forminator' ) ); }
 }
 private static function save( $path, &$state ) { $state['updated'] = time(); self::write( $path . '/state.json', $state ); }
 private static function owned( $id ) {
  Adapter::authorize(); $path = self::path( $id ); $state = self::read( $path . '/state.json' );
  if ( (int) $state['owner'] !== get_current_user_id() ) { throw new \RuntimeException( esc_html__( 'Access denied.', 'file-packs-for-forminator' ) ); }
  if ( 1 !== $state['schema'] || VERSION !== $state['version'] ) { throw new \RuntimeException( esc_html__( 'The plugin changed during this export. Check the selection again.', 'file-packs-for-forminator' ) ); }
  return array( $path, $state );
 }
 private static function schedule( $id ) {
  $next = wp_next_scheduled( 'ffp_export_step', array( $id ) );
  if ( ! $next || $next > time() + 60 ) { wp_schedule_single_event( time() + 5, 'ffp_export_step', array( $id ) ); }
 }
 public static function latest() {
  try { self::collect(); } catch ( \RuntimeException $e ) { return null; }
  Adapter::authorize(); $id = get_user_meta( get_current_user_id(), '_ffp_export', true );
  if ( ! $id ) { return null; }
  try { return self::status( $id ); } catch ( \RuntimeException $e ) { delete_user_meta( get_current_user_id(), '_ffp_export' ); return null; }
 }
 /** Also recover expired orphan tasks if a process died before scheduling their cleanup. */
 public static function collect() {
  $deadline = microtime( true ) + 2; $root = Package::private_root();
  foreach ( new \DirectoryIterator( $root ) as $item ) {
   if ( microtime( true ) > $deadline ) { break; }
   if ( ! $item->isDir() || $item->isLink() || ! preg_match( '/^export-([a-f0-9]{32})$/D', $item->getFilename(), $match ) ) { continue; }
   try { $state = self::read( $item->getPathname() . '/state.json' ); if ( $state['expires'] <= time() || 'cancelled' === $state['status'] ) { self::schedule( $match[1] ); } } catch ( \RuntimeException $e ) { /* Corrupt state is never guessed or exposed. */ }
  }
 }
 public static function uninstall() {
  $lock = self::lock(); if ( ! $lock ) { throw new \RuntimeException( esc_html__( 'An export step is running. Wait briefly before deleting the plugin.', 'file-packs-for-forminator' ) ); }
  try {
   foreach ( new \DirectoryIterator( Package::private_root() ) as $item ) {
    if ( ! $item->isDir() || $item->isLink() || ! preg_match( '/^export-[a-f0-9]{32}$/D', $item->getFilename() ) ) { continue; }
    foreach ( new \DirectoryIterator( $item->getPathname() ) as $file ) { if ( ! $file->isDot() && ( $file->isFile() || $file->isLink() ) ) { unlink( $file->getPathname() ); } }
    rmdir( $item->getPathname() );
   }
   delete_metadata( 'user', 0, '_ffp_export', '', true ); wp_unschedule_hook( 'ffp_export_step' ); wp_unschedule_hook( 'ffp_export_gc' );
  } finally { fclose( $lock ); }
 }
 public static function create( $form, $mode, $from = '', $to = '' ) {
  $model = Adapter::form( $form );
  if ( ! in_array( $mode, array( 'all', 'manual' ), true ) ) { throw new \RuntimeException( esc_html__( 'Invalid submission selection.', 'file-packs-for-forminator' ) ); }
  $ceiling = Adapter::scan( $form, $from, $to );
  $lock = self::lock(); if ( ! $lock ) { throw new \RuntimeException( esc_html__( 'Another export step is running. Try again shortly.', 'file-packs-for-forminator' ) ); }
  try {
   $old = get_user_meta( get_current_user_id(), '_ffp_export', true );
   if ( $old ) {
    try { list( $old_path, $old_state ) = self::owned( $old ); }
    catch ( \RuntimeException $e ) { $old_path = ''; }
    if ( $old_path ) {
     if ( ! in_array( $old_state['status'], array( 'ready', 'error', 'cancelled', 'expired' ), true ) && $old_state['expires'] > time() ) { throw new \RuntimeException( esc_html__( 'An export task already exists. Finish or cancel it before starting another.', 'file-packs-for-forminator' ) ); }
     $old_state['status'] = 'cancelled'; self::save( $old_path, $old_state ); self::purge_step( $old_path, $old_state );
     if ( is_dir( $old_path ) ) { throw new \RuntimeException( esc_html__( 'Previous export files are still being deleted. Refresh progress before starting another task.', 'file-packs-for-forminator' ) ); }
    }
   }
   $id = bin2hex( random_bytes( 16 ) ); $path = Package::private_root() . '/export-' . $id;
   if ( ! mkdir( $path, 0700 ) || ! chmod( $path, 0700 ) ) { throw new StorageException( esc_html__( 'Cannot prepare the package.', 'file-packs-for-forminator' ) ); }
   $state = array( 'schema' => 1, 'version' => VERSION, 'id' => $id, 'owner' => get_current_user_id(), 'locale' => determine_locale(), 'form' => (int) $form, 'mode' => $mode, 'from' => $from, 'to' => $to, 'ceiling' => $ceiling, 'cursor' => 0, 'status' => 'all' === $mode ? 'discovering' : 'selecting', 'chunks' => 0, 'total' => 0, 'checked' => 0, 'built' => 0, 'files' => 0, 'bytes' => 0, 'warnings' => 0, 'parts' => 0, 'completed_parts' => 0, 'check_chunk' => 0, 'check_offset' => 0, 'pending' => array(), 'pending_bytes' => 0, 'pending_files' => 0, 'pending_text' => 0, 'partial' => false, 'error' => '', 'created' => time(), 'updated' => time(), 'expires' => time() + self::RETENTION );
   $state['form_title'] = wp_html_excerpt( wp_strip_all_tags( (string) ( $model->settings['formName'] ?? $model->name ) ), 180, '…' );
   self::save( $path, $state ); update_user_meta( $state['owner'], '_ffp_export', $id ); self::schedule( $id );
   wp_schedule_single_event( $state['expires'], 'ffp_export_step', array( $id ) );
   return self::view( $state );
  } finally { fclose( $lock ); }
 }
 /** Sequence numbers make retries after a lost HTTP response idempotent. */
 public static function append( $id, $sequence, $ids ) {
  $lock = self::lock(); if ( ! $lock ) { throw new \RuntimeException( esc_html__( 'Another export step is running. Try again shortly.', 'file-packs-for-forminator' ) ); }
  try {
   list( $path, $state ) = self::owned( $id );
   if ( 'selecting' !== $state['status'] || $state['expires'] <= time() || ! is_array( $ids ) || ! array_is_list( $ids ) || count( $ids ) > 100 || $sequence > $state['chunks'] || $sequence < 0 ) { throw new \RuntimeException( esc_html__( 'Invalid submission selection.', 'file-packs-for-forminator' ) ); }
   $ids = array_values( array_unique( array_map( 'intval', $ids ) ) );
   $hash = hash( 'sha256', wp_json_encode( $ids ) );
   if ( $sequence < $state['chunks'] ) {
    $chunk = self::read( $path . '/ids-' . $sequence . '.json' );
    if ( ! hash_equals( $chunk['hash'], $hash ) ) { throw new \RuntimeException( esc_html__( 'The selection changed. Cancel this task and check again.', 'file-packs-for-forminator' ) ); }
    return self::view( $state );
   }
   $unique = array();
   foreach ( $ids as $entry ) {
    if ( $entry < 1 ) { throw new \RuntimeException( esc_html__( 'Invalid identifier.', 'file-packs-for-forminator' ) ); }
    Adapter::entry( $state['form'], $entry );
    $marker = $path . '/seen-' . $entry . '.json';
    if ( is_file( $marker ) && self::read( $marker )['sequence'] < $sequence ) { continue; }
    $unique[] = $entry; self::write( $marker, array( 'sequence' => $sequence ) );
   }
   self::write( $path . '/ids-' . $sequence . '.json', array( 'ids' => $unique, 'hash' => $hash ) );
   ++$state['chunks']; $state['total'] += count( $unique ); self::save( $path, $state );
   return self::view( $state );
  } finally { fclose( $lock ); }
 }
 public static function command( $id, $command, $partial = false ) {
  $lock = self::lock(); if ( ! $lock ) { throw new \RuntimeException( esc_html__( 'Another export step is running. Try again shortly.', 'file-packs-for-forminator' ) ); }
  try {
   list( $path, $state ) = self::owned( $id );
   if ( 'cancel' === $command ) { $state['status'] = 'cancelled'; }
   elseif ( $state['expires'] <= time() ) { throw new \RuntimeException( esc_html__( 'The export task expired. Check the selection again.', 'file-packs-for-forminator' ) ); }
   elseif ( 'finish' === $command && 'selecting' === $state['status'] && $state['total'] ) { $state['status'] = 'checking'; }
   elseif ( 'start' === $command && 'review' === $state['status'] ) {
    if ( $state['warnings'] && ! $partial ) { throw new \RuntimeException( esc_html__( 'Review the warnings and explicitly accept an incomplete package.', 'file-packs-for-forminator' ) ); }
    $state['partial'] = $partial; $state['status'] = 'building';
   } else { throw new \RuntimeException( esc_html__( 'This export task cannot perform that action.', 'file-packs-for-forminator' ) ); }
   self::save( $path, $state ); self::schedule( $id ); return self::view( $state );
  } finally { fclose( $lock ); }
 }
 private static function part( $path, &$state ) {
  if ( ! $state['pending'] ) { return; }
  self::write( $path . '/part-' . $state['parts'] . '.json', array( 'entries' => $state['pending'], 'bytes' => $state['pending_bytes'], 'files' => $state['pending_files'], 'verified_bytes' => $state['pending_bytes'], 'verified_files' => $state['pending_files'] ) );
  ++$state['parts']; $state['pending'] = array(); $state['pending_bytes'] = 0; $state['pending_files'] = 0; $state['pending_text'] = 0;
 }
 /** One bounded pass, serialized site-wide; flock is released automatically after SIGKILL. */
 public static function run( $id ) {
  $lock = self::lock(); if ( ! $lock ) { self::schedule( $id ); return; }
  $previous = get_current_user_id(); $switched = false; $state = null;
  try {
   $path = self::path( $id ); $state = self::read( $path . '/state.json' );
   if ( $state['expires'] <= time() ) { $state['status'] = 'expired'; }
   if ( in_array( $state['status'], array( 'cancelled', 'expired' ), true ) ) { self::purge_step( $path, $state ); return; }
   if ( in_array( $state['status'], array( 'ready', 'review', 'error', 'selecting' ), true ) ) { return; }
   if ( 1 !== $state['schema'] || VERSION !== $state['version'] || ! get_user_by( 'id', $state['owner'] ) ) { throw new \RuntimeException( esc_html__( 'The plugin or export owner changed. Check the selection again.', 'file-packs-for-forminator' ) ); }
   wp_set_current_user( $state['owner'] ); Adapter::authorize();
   $switched = switch_to_locale( $state['locale'] );
   // Schedule recovery before work: a killed process still has another opportunity to resume.
   self::schedule( $id );
   if ( 'discovering' === $state['status'] ) {
    $ids = Adapter::scan( $state['form'], $state['from'], $state['to'], $state['cursor'], $state['ceiling'] );
    if ( $ids ) {
     self::write( $path . '/ids-' . $state['chunks'] . '.json', array( 'ids' => $ids ) );
     ++$state['chunks']; $state['total'] += count( $ids ); $state['cursor'] = max( $ids );
    } else {
     if ( ! $state['total'] ) { throw new \RuntimeException( esc_html__( 'No completed submissions match this period. Clear the period to see other submissions.', 'file-packs-for-forminator' ) ); }
     $state['status'] = 'checking';
    }
   } elseif ( 'checking' === $state['status'] ) {
    $deadline = microtime( true ) + 8; $steps = 0; $memory = wp_convert_hr_to_bytes( ini_get( 'memory_limit' ) );
    while ( $state['check_chunk'] < $state['chunks'] && $steps < 200 && microtime( true ) < $deadline && ( ! $steps || $memory <= 0 || memory_get_usage( true ) < $memory - 33554432 ) ) {
     $chunk = self::read( $path . '/ids-' . $state['check_chunk'] . '.json' );
     if ( $state['check_offset'] >= count( $chunk['ids'] ) ) { ++$state['check_chunk']; $state['check_offset'] = 0; continue; }
     $entry = $chunk['ids'][$state['check_offset']];
     $plan = Planner::build( $state['form'], array( $entry ) );
     // translators: %d: submission identifier.
     if ( $plan['bytes'] > self::PART_BYTES || $plan['file_count'] > self::PART_FILES || $plan['metadata_bytes'] > self::PART_TEXT ) { throw new \RuntimeException( esc_html( sprintf( __( 'Submission #%d exceeds the per-submission safety limit: 16 MiB of attachments, 500 attachments or 1 MiB of field data. Export it separately in a small selection.', 'file-packs-for-forminator' ), $entry ) ) ); }
     if ( count( $state['pending'] ) >= self::PART_ENTRIES || $state['pending_bytes'] + $plan['bytes'] > self::PART_BYTES || $state['pending_files'] + $plan['file_count'] > self::PART_FILES || $state['pending_text'] + $plan['metadata_bytes'] > self::PART_TEXT ) { self::part( $path, $state ); }
     $state['pending'][] = array( 'id' => $entry, 'fingerprint' => $plan['fingerprint'], 'warnings' => count( $plan['warnings'] ) );
     $state['pending_bytes'] += $plan['bytes']; $state['pending_files'] += $plan['file_count']; $state['pending_text'] += $plan['metadata_bytes'];
     $review = array( 'id' => $entry, 'summary' => Planner::summary( $plan['entries'][0] ), 'warnings' => $plan['warnings'], 'files' => $plan['file_count'], 'bytes' => $plan['bytes'] );
     self::write( $path . '/review-' . $state['checked'] . '.json', $review );
     ++$state['checked']; ++$state['check_offset']; ++$steps;
     $state['files'] += $plan['file_count']; $state['bytes'] += $plan['bytes']; $state['warnings'] += count( $plan['warnings'] );
     self::save( $path, $state );
    }
    if ( $state['check_chunk'] >= $state['chunks'] ) { self::part( $path, $state ); $state['status'] = 'review'; }
   } elseif ( 'building' === $state['status'] ) {
    $number = $state['completed_parts']; $part = self::read( $path . '/part-' . $number . '.json' );
    $plan = null;
    foreach ( $part['entries'] as $item ) {
     $fresh = Planner::build( $state['form'], array( $item['id'] ) );
     if ( ! hash_equals( $item['fingerprint'], $fresh['fingerprint'] ) ) { throw new \RuntimeException( esc_html__( 'The selected data changed. Cancel this task and check the selection again.', 'file-packs-for-forminator' ) ); }
     if ( null === $plan ) { $plan = $fresh; }
     else {
      $plan['entries'] = array_merge( $plan['entries'], $fresh['entries'] );
      $plan['warnings'] = array_merge( $plan['warnings'], $fresh['warnings'] );
      foreach ( array( 'bytes', 'file_count', 'metadata_bytes' ) as $key ) { $plan[$key] += $fresh[$key]; }
     }
    }
    unset( $fresh );
    // Package the exact bounded plans just compared; do not re-read unchecked field values.
    $disk = disk_free_space( $path );
    if ( false !== $disk && $disk < $plan['bytes'] * 3 + 33554432 ) { throw new StorageException( esc_html__( 'Not enough temporary disk space for this package.', 'file-packs-for-forminator' ) ); }
    $package = new Package( $plan, $state['partial'], $id );
    try {
     do_action( 'ffp_export_part_prepared', $id, $number );
     Adapter::authorize();
     $target = $path . '/archive-' . $number . '.zip';
     if ( is_link( $target ) || ! rename( $package->path(), $target ) || ! chmod( $target, 0600 ) ) { throw new StorageException( esc_html__( 'Could not save the finished archive.', 'file-packs-for-forminator' ) ); }
     $part['size'] = filesize( $target ); $part['warnings'] = count( $package->plan['warnings'] );
     $part['files'] = $package->plan['file_count'];
     $part['bytes'] = $package->plan['bytes'];
     $state['files'] += $part['files'] - $part['verified_files'];
     $state['bytes'] += $part['bytes'] - $part['verified_bytes'];
     $state['warnings'] += $part['warnings'] - array_sum( array_column( $part['entries'], 'warnings' ) );
     $part['name'] = substr( Admin::download_name( $plan ), 0, -4 ) . '-part-' . ( $number + 1 ) . '.zip';
     self::write( $path . '/part-' . $number . '.json', $part );
     ++$state['completed_parts']; $state['built'] += count( $part['entries'] );
     if ( $state['completed_parts'] === $state['parts'] ) { $state['status'] = 'ready'; $state['expires'] = time() + self::RETENTION; }
    } finally { $package->cleanup(); }
   }
   self::save( $path, $state );
   if ( 'ready' === $state['status'] ) { wp_clear_scheduled_hook( 'ffp_export_step', array( $id ) ); wp_schedule_single_event( $state['expires'], 'ffp_export_step', array( $id ) ); }
  } catch ( \Throwable $e ) {
   if ( $state && isset( $path ) ) { $state['status'] = 'error'; $state['error'] = $e instanceof \RuntimeException ? $e->getMessage() : __( 'The export could not be completed. Check the environment and try again.', 'file-packs-for-forminator' ); self::save( $path, $state ); }
  } finally {
   // Evict only this process's runtime cache; never flush a shared persistent cache.
   if ( function_exists( 'wp_cache_flush_runtime' ) ) { wp_cache_flush_runtime(); }
   if ( $switched ) { restore_previous_locale(); } wp_set_current_user( $previous ); fclose( $lock );
  }
 }
 private static function view( $state ) {
  return array_intersect_key( $state, array_flip( array( 'id', 'form', 'form_title', 'mode', 'from', 'to', 'status', 'total', 'checked', 'built', 'files', 'bytes', 'warnings', 'parts', 'completed_parts', 'error', 'updated', 'expires' ) ) );
 }
 public static function status( $id ) {
  list( $path, $state ) = self::owned( $id );
  if ( $state['expires'] <= time() ) { $state['status'] = 'expired'; self::schedule( $id ); }
  return self::view( $state );
 }
 public static function tick( $id ) {
  $before = self::status( $id ); self::run( $id );
  if ( in_array( $before['status'], array( 'cancelled', 'expired' ), true ) && ! is_dir( Package::private_root() . '/export-' . $id ) ) { $before['cleanup_done'] = true; return $before; }
  return self::status( $id );
 }
 public static function review( $id, $page ) {
  list( $path, $state ) = self::owned( $id );
  if ( ! in_array( $state['status'], array( 'review', 'building', 'ready' ), true ) || $state['expires'] <= time() ) { throw new \RuntimeException( esc_html__( 'The export is not ready for review.', 'file-packs-for-forminator' ) ); }
  $rows = array(); $start = ( max( 1, $page ) - 1 ) * 10;
  for ( $i = $start; $i < min( $start + 10, $state['checked'] ); ++$i ) { $rows[] = self::read( $path . '/review-' . $i . '.json' ); }
  return array( 'rows' => $rows, 'page' => max( 1, $page ), 'pages' => max( 1, (int) ceil( $state['checked'] / 10 ) ) );
 }
 public static function parts( $id, $page ) {
  list( $path, $state ) = self::owned( $id );
  if ( 'ready' !== $state['status'] || $state['expires'] <= time() ) { throw new \RuntimeException( esc_html__( 'The archive is not available for download.', 'file-packs-for-forminator' ) ); }
  $rows = array(); $start = ( max( 1, $page ) - 1 ) * 10;
  for ( $i = $start; $i < min( $start + 10, $state['parts'] ); ++$i ) {
   $part = self::read( $path . '/part-' . $i . '.json' );
   $rows[] = array( 'number' => $i, 'name' => $part['name'], 'size' => $part['size'], 'submissions' => count( $part['entries'] ), 'files' => $part['files'], 'warnings' => $part['warnings'] );
  }
  return array( 'rows' => $rows, 'page' => max( 1, $page ), 'pages' => max( 1, (int) ceil( $state['parts'] / 10 ) ) );
 }
 public static function download( $id, $number ) {
  $lock = self::lock( true ); if ( ! $lock ) { throw new \RuntimeException( esc_html__( 'Another export step is running. Try again shortly.', 'file-packs-for-forminator' ) ); }
  try {
   list( $path, $state ) = self::owned( $id );
   if ( 'ready' !== $state['status'] || $state['expires'] <= time() || $number < 0 || $number >= $state['parts'] ) { throw new \RuntimeException( esc_html__( 'The archive is not available for download.', 'file-packs-for-forminator' ) ); }
   $part = self::read( $path . '/part-' . $number . '.json' ); $file = $path . '/archive-' . $number . '.zip';
   if ( is_link( $file ) || ! is_file( $file ) || filesize( $file ) !== $part['size'] ) { throw new StorageException( esc_html__( 'The archive is unavailable. Check the selection again.', 'file-packs-for-forminator' ) ); }
   Adapter::authorize();
   while ( ob_get_level() ) { ob_end_clean(); }
   nocache_headers(); header( 'Content-Type: application/zip' ); header( 'X-Content-Type-Options: nosniff' );
   header( 'Content-Disposition: attachment; filename="file-pack-part-' . ( $number + 1 ) . '.zip"; filename*=UTF-8\'\'' . rawurlencode( $part['name'] ) );
   header( 'Content-Length: ' . $part['size'] ); readfile( $file );
  } finally { fclose( $lock ); }
 }
 private static function purge_step( $path, &$state ) {
  Package::recover_task( $state['id'], $state['owner'] );
  $count = 0;
  foreach ( new \DirectoryIterator( $path ) as $file ) {
   if ( $file->isDot() || 'state.json' === $file->getFilename() ) { continue; }
   if ( $file->isFile() || $file->isLink() ) { if ( ! unlink( $file->getPathname() ) ) { throw new StorageException( esc_html__( 'Could not clean private export files.', 'file-packs-for-forminator' ) ); } }
   if ( ++$count >= 200 ) { self::save( $path, $state ); self::schedule( $state['id'] ); return; }
  }
  unlink( $path . '/state.json' ); rmdir( $path );
  if ( get_user_meta( $state['owner'], '_ffp_export', true ) === $state['id'] ) { delete_user_meta( $state['owner'], '_ffp_export' ); }
  wp_clear_scheduled_hook( 'ffp_export_step', array( $state['id'] ) );
 }
}
