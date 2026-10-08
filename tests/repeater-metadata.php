<?php
/** Confirm real persisted repeater references and Media Library IDs, not just field settings. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || '1' !== getenv( 'FFP_TEST_LAB' ) ) { throw new RuntimeException( 'Disposable lab only.' ); }
wp_set_current_user( 1 );
$fixture = json_decode( file_get_contents( getenv( 'FFP_REPEATER_FIXTURE' ) ), true );
$entry = Forminator_API::get_entry( $fixture['form'], $fixture['entry'] );
$expect_media = str_ends_with( $fixture['mode'], '-media' );
$files = 0; $media_files = 0; $fields = array();
foreach ( $entry->meta_data as $key => $meta ) {
 if ( ! str_starts_with( $key, 'upload-1' ) ) { continue; }
 $fields[] = $key; $file = $meta['value']['file'];
 $paths = is_array( $file['file_path'] ) ? $file['file_path'] : array( $file['file_path'] );
 $ids = $file['attachment_id'] ?? 0; $ids = is_array( $ids ) ? $ids : array( $ids );
 foreach ( $paths as $i => $path ) {
  ++$files;
  if ( ! empty( $ids[$i] ) ) {
   if ( realpath( get_attached_file( $ids[$i] ) ) !== realpath( $path ) ) { throw new RuntimeException( 'Media Library attachment does not match saved file.' ); }
   ++$media_files;
  } elseif ( $expect_media ) { throw new RuntimeException( 'Expected a real Media Library attachment ID for every file.' ); }
 }
}
if ( 2 !== count( $fields ) || $files !== 2 * $fixture['files_per_row'] || ( $expect_media ? $media_files !== $files : 0 !== $media_files ) ) { throw new RuntimeException( 'Unexpected persisted repeater shape or Media Library behavior.' ); }
file_put_contents( getenv( 'FFP_TEST_ARTIFACTS' ) . '/repeater-' . $fixture['mode'] . '-metadata-results.json', wp_json_encode( array( 'mode' => $fixture['mode'], 'persisted_upload_fields' => $fields, 'files' => $files, 'verified_media_attachment_ids' => $media_files ), JSON_PRETTY_PRINT ) );
echo 'PASS persisted repeater ' . $fixture['mode'] . ': two fields, expected file count and verified Media Library references' . "\n";
