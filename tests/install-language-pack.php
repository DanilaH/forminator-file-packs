<?php
/** Install the separately distributed catalog in a disposable lab, like a WP language pack. */
if ( '1' !== getenv( 'FFP_TEST_LAB' ) ) { throw new RuntimeException( 'Disposable lab only.' ); }
$base = dirname( __DIR__ );
$packages = is_dir( $base . '/dist' ) ? $base . '/dist' : $base . '/packages';
$archive = new ZipArchive();
if ( true !== $archive->open( $packages . '/languages/file-packs-for-forminator-ru_RU.zip' ) ) { throw new RuntimeException( 'Missing separate language pack.' ); }
$directory = WP_LANG_DIR . '/plugins';
if ( ! wp_mkdir_p( $directory ) ) { throw new RuntimeException( 'Cannot create standard language directory.' ); }
foreach ( array( 'po', 'mo' ) as $extension ) {
 $name = 'file-packs-for-forminator-ru_RU.' . $extension;
 $bytes = $archive->getFromName( $name );
 if ( false === $bytes || strlen( $bytes ) !== file_put_contents( $directory . '/' . $name, $bytes ) ) { throw new RuntimeException( 'Cannot install language pack.' ); }
}
$archive->close();
wp_cache_delete( md5( $directory . '/' ), 'translation_files' );
echo "PASS installed separate Russian catalog in standard WordPress language location; no translation hooks added\n";
