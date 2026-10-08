<?php
/** Confirm real persisted repeater references and Media Library IDs, not just field settings. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || '1' !== getenv( 'FFP_TEST_LAB' ) ) { throw new RuntimeException( 'Disposable lab only.' ); }
wp_set_current_user( 1 );
$fixture = json_decode( file_get_contents( getenv( 'FFP_REPEATER_FIXTURE' ) ), true );
$entry = Forminator_API::get_entry( $fixture['form'], $fixture['entry'] );
$expect_media = str_ends_with( $fixture['mode'], '-media' );
$files = 0; $media_files = 0; $stored_ids = 0; $looked_up_ids = 0; $fields = array();
foreach ( $entry->meta_data as $key => $meta ) {
 if ( ! str_starts_with( $key, 'upload-1' ) ) { continue; }
 $fields[] = $key; $file = $meta['value']['file'];
 $paths = is_array( $file['file_path'] ) ? $file['file_path'] : array( $file['file_path'] );
 $ids = $file['attachment_id'] ?? 0; $ids = is_array( $ids ) ? $ids : array( $ids );
 foreach ( $paths as $i => $path ) {
  ++$files;
  if ( ! empty( $ids[$i] ) ) { ++$stored_ids; }
  // 1.57.1 creates attachment posts but does not include their IDs in upload metadata.
  // Independently verify its actual Media Library post, never treat a setting as evidence.
  if ( $expect_media && empty( $ids[$i] ) && version_compare( FORMINATOR_VERSION, '1.58.0', '<' ) ) {
   $base = trailingslashit( wp_normalize_path( wp_upload_dir()['basedir'] ) );
   $normalized = wp_normalize_path( $path );
   if ( ! str_starts_with( $normalized, $base ) ) { throw new RuntimeException( 'Media fixture outside uploads.' ); }
   $found = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => 2, 'fields' => 'ids', 'meta_key' => '_wp_attached_file', 'meta_value' => substr( $normalized, strlen( $base ) ) ) );
   if ( 1 !== count( $found ) ) { throw new RuntimeException( 'Expected one actual Media Library post for this saved path.' ); }
   $ids[$i] = $found[0]; ++$looked_up_ids;
  }
  if ( ! empty( $ids[$i] ) ) {
   if ( realpath( get_attached_file( $ids[$i] ) ) !== realpath( $path ) ) { throw new RuntimeException( 'Media Library attachment does not match saved file.' ); }
   ++$media_files;
  } elseif ( $expect_media ) { throw new RuntimeException( 'Expected a real Media Library attachment ID for every file.' ); }
 }
}
if ( 2 !== count( $fields ) || $files !== 2 * $fixture['files_per_row'] || ( $expect_media ? $media_files !== $files : 0 !== $media_files ) ) { throw new RuntimeException( 'Unexpected persisted repeater shape or Media Library behavior.' ); }
file_put_contents( getenv( 'FFP_TEST_ARTIFACTS' ) . '/repeater-' . $fixture['mode'] . '-metadata-results.json', wp_json_encode( array( 'mode' => $fixture['mode'], 'persisted_upload_fields' => $fields, 'files' => $files, 'verified_media_attachment_ids' => $media_files, 'stored_attachment_ids' => $stored_ids, 'media_ids_resolved_from_attachment_posts' => $looked_up_ids ), JSON_PRETTY_PRINT ) );
echo 'PASS persisted repeater ' . $fixture['mode'] . ': two fields, expected file count and verified Media Library references' . "\n";
