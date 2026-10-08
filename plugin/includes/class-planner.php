<?php
/** Export planning and public preview. @package ForminatorFilePacks */
namespace ForminatorFilePacks;
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class Planner {
 public static function build( $form_id, $ids ) {
  $form = Adapter::form( $form_id ); $ids = array_values( array_unique( $ids ) );
  // translators: %d: maximum submissions per synchronous package.
  if ( ! $ids || count( $ids ) > MAX_ENTRIES ) { throw new \RuntimeException( esc_html( sprintf( __( 'Select between 1 and %d submissions per package.', 'forminator-file-packs' ), MAX_ENTRIES ) ) ); }
  sort( $ids, SORT_NUMERIC );
  $plan = array( 'form_id' => $form_id, 'form_title' => (string) ( $form->settings['formName'] ?? $form->name ), 'entries' => array(), 'warnings' => array(), 'bytes' => 0, 'file_count' => 0, 'metadata_bytes' => 0 );
  foreach ( $ids as $id ) {
   $entry = Adapter::entry( $form_id, $id );
   $row = array( 'id' => $id, 'date' => $entry->date_created_sql, 'folder' => 'Request-' . $id, 'fields' => array(), 'files' => array(), 'warnings' => array() );
   $used = array( 'request.html' => true );
   foreach ( $entry->meta_data as $key => $meta ) {
    if ( str_starts_with( $key, '_' ) || str_starts_with( $key, 'forminator_addon_' ) ) { continue; }
    $settings = $form->get_field( $key, true ); $label = $settings['field_label'] ?? $settings['label'] ?? $key; $value = $meta['value'];
    $is_upload = isset( $settings['type'] ) && 'upload' === $settings['type'];
    if ( $is_upload || ( is_array( $value ) && isset( $value['file'] ) ) ) {
     if ( empty( $value ) ) { continue; }
     if ( ! $is_upload || ! is_array( $value ) || ! isset( $value['file'] ) || ! is_array( $value['file'] ) ) { self::warn( $row, $label, __( 'Upload metadata is unsupported or the original upload field was removed.', 'forminator-file-packs' ) ); continue; }
     $upload = $value['file']; $paths = $upload['file_path'] ?? null; $urls = $upload['file_url'] ?? null; $media = $upload['attachment_id'] ?? 0;
     if ( ! is_string( $paths ) && ! is_array( $paths ) ) { self::warn( $row, $label, __( 'Upload has no supported local file reference.', 'forminator-file-packs' ) ); continue; }
     $paths = is_array( $paths ) ? array_values( $paths ) : array( $paths );
     $urls = is_array( $urls ) ? array_values( $urls ) : array( $urls ); $media = is_array( $media ) ? array_values( $media ) : array( $media );
     foreach ( $paths as $i => $path ) {
      try {
       $real = Adapter::resolve_file( $form_id, $path, is_string( $urls[$i] ?? null ) ? $urls[$i] : '', absint( $media[$i] ?? 0 ) );
       $name = self::name( $key . '--' . basename( $real ) ); $base = $name; $suffix = 2;
       while ( isset( $used[strtolower( $name )] ) ) { $name = pathinfo( $base, PATHINFO_FILENAME ) . '-' . $suffix++ . '.' . pathinfo( $base, PATHINFO_EXTENSION ); }
       $used[strtolower( $name )] = true; $size = filesize( $real );
       if ( false === $size ) { throw new \RuntimeException( esc_html__( 'File size could not be read.', 'forminator-file-packs' ) ); }
       $row['files'][] = array( 'field' => $key, 'name' => $name, 'label' => $label, 'path' => $real, 'source' => $path, 'url' => $urls[$i] ?? '', 'attachment_id' => absint( $media[$i] ?? 0 ), 'size' => $size, 'mtime' => filemtime( $real ) );
       $plan['bytes'] += $size; ++$plan['file_count'];
      } catch ( \RuntimeException $e ) { self::warn( $row, $label . ' #' . ($i + 1), $e->getMessage() ); }
     }
    } else {
     $text = is_scalar( $value ) || null === $value ? (string) $value : wp_json_encode( $value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
     if ( ! is_string( $text ) || strlen( $text ) > 1048576 ) { throw new \RuntimeException( esc_html__( 'A submission field is too large or unsupported.', 'forminator-file-packs' ) ); }
     $plan['metadata_bytes'] += strlen( $text );
     if ( $plan['metadata_bytes'] > 8388608 ) { throw new \RuntimeException( esc_html__( 'Submission data exceeds the 8 MiB metadata safety limit. Select a smaller batch.', 'forminator-file-packs' ) ); }
     $row['fields'][] = array( 'key' => $key, 'label' => $label, 'type' => $settings['type'] ?? null, 'value' => $text, 'data' => $value );
    }
   }
   $plan['warnings'] = array_merge( $plan['warnings'], $row['warnings'] ); $plan['entries'][] = $row;
  }
  if ( $plan['bytes'] > MAX_BYTES || $plan['file_count'] > MAX_FILES ) { throw new \RuntimeException( esc_html__( 'This package exceeds the synchronous export safety limit (100 MiB or 500 files). Select a smaller batch.', 'forminator-file-packs' ) ); }
  // Hash bounded pieces: a whole-plan JSON string can expand control characters sixfold.
  $hash = hash_init( 'sha256' ); $header = $plan; unset( $header['entries'] );
  hash_update( $hash, serialize( $header ) );
  foreach ( $plan['entries'] as $row ) {
   $identity = $row; unset( $identity['fields'], $identity['files'] );
   hash_update( $hash, 'entry:' . serialize( $identity ) );
   foreach ( $row['fields'] as $field ) { hash_update( $hash, 'field:' . serialize( $field ) ); }
   foreach ( $row['files'] as $file ) { hash_update( $hash, 'file:' . serialize( $file ) ); }
  }
  $plan['fingerprint'] = hash_final( $hash ); return $plan;
 }
 public static function warn( &$row, $label, $reason ) { $row['warnings'][] = array( 'entry' => $row['id'], 'field' => $label, 'reason' => $reason ); }
 public static function name( $name ) {
  $name = sanitize_file_name( preg_replace( '/[\x00-\x1f\x7f<>:"\/\\\\|?*]/u', '-', $name ) ); $name = trim( $name, '. ' );
  if ( '' === $name ) { $name = 'attachment'; }
  $ext = pathinfo( $name, PATHINFO_EXTENSION );
  return self::short_name( pathinfo( $name, PATHINFO_FILENAME ) ) . ( $ext ? '.' . $ext : '' );
 }
 public static function short_name( $name ) {
  while ( strlen( $name ) > 150 ) { $name = preg_replace( '/.$/us', '', $name ); }
  return $name;
 }
 public static function preview( $plan ) {
  foreach ( $plan['entries'] as &$row ) {
   unset( $row['fields'] ); foreach ( $row['files'] as &$file ) { $file = array_intersect_key( $file, array_flip( array( 'name', 'label', 'size' ) ) ); } unset( $file );
  } unset( $row ); return $plan;
 }
}
