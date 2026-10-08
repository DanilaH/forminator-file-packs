<?php
/** Real saved structured data, partial export and serialization failure. Disposable lab only. */
use ForminatorFilePacks\Planner;
use ForminatorFilePacks\Package;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || '1' !== getenv( 'FFP_TEST_LAB' ) ) { throw new RuntimeException( 'Disposable lab only.' ); }
wp_set_current_user( 1 );
$form = Forminator_API::add_form( 'JSON structure <test>', array(), array( 'formName' => 'JSON structure <test>', 'store_submissions' => '1' ) );
if ( '1' === getenv( 'FFP_STAGING_TEST' ) ) { update_post_meta( $form, '_ffp_staging_fixture', '1' ); }
foreach ( array( 'text', 'name', 'checkbox', 'address', 'upload' ) as $type ) { Forminator_API::add_form_field( $form, $type, array( 'field_label' => $type . ' — данные' ) ); }
$root = forminator_get_upload_path( $form, 'uploads' ); wp_mkdir_p( $root );
$file = $root . '/json-document.txt'; file_put_contents( $file, 'Exact synthetic JSON attachment' );
$values = array( 'text-1' => "00123\n=SUM(A1)\t\"\\\0<script>🙂", 'name-1' => array( 'first-name' => 'Данила', 'last-name' => 'Test' ), 'checkbox-1' => array( 'one', 'two', '003' ), 'address-1' => array( 'city' => 'Екатеринбург', 'nested' => array( 'integer' => 0, 'float' => 1.25, 'zero_float' => 0.0, 'false' => false, 'null' => null, 'empty' => array() ) ), 'text-1-2' => 'Second repeat row' );
$meta = array(); foreach ( $values as $key => $value ) { $meta[] = array( 'name' => $key, 'value' => $value ); }
$meta[] = array( 'name' => '_internal_secret', 'value' => 'SYNTHETIC-INTERNAL-SENTINEL' );
$meta[] = array( 'name' => 'forminator_addon_test', 'value' => 'SYNTHETIC-ADDON-SENTINEL' );
$meta[] = array( 'name' => 'upload-1', 'value' => array( 'file' => array( 'file_path' => $file, 'file_url' => '' ) ) );
$id = Forminator_API::add_form_entry( $form, wp_slash( $meta ) );
$plan = Planner::build( $form, array( $id ) );
$before = serialize( Forminator_API::get_entry( $form, $id )->meta_data ); $hash = hash_file( 'sha256', $file );
$out = getenv( 'FFP_TEST_ARTIFACTS' );
$package = new Package( $plan ); copy( $package->path(), $out . '/json-complete.zip' ); $package->cleanup();
$expected = array( 'form' => $form, 'entry' => $id, 'values' => $values, 'attachment_hash' => $hash );
file_put_contents( $out . '/json-expected.json', wp_json_encode( $expected, JSON_UNESCAPED_UNICODE ) );
// Missing after planning: JSON must reflect the actual final package, not its preview.
unlink( $file ); clearstatcache(); $package = new Package( $plan, true ); copy( $package->path(), $out . '/json-partial.zip' ); $package->cleanup();
$malformed = $plan; $malformed['entries'][0]['files'] = array(); $malformed['file_count'] = 0; $malformed['bytes'] = 0;
$malformed['entries'][0]['fields'][0]['data'] = "\xFF";
$rejected = false;
try { $package = new Package( $malformed ); $package->cleanup(); } catch ( \ForminatorFilePacks\StorageException $e ) { $rejected = true; }
if ( ! $rejected || glob( Package::private_root() . '/job-*' ) || glob( Package::private_root() . '/lock-*' ) ) { throw new RuntimeException( 'Invalid UTF-8 did not abort and clean the whole package.' ); }
if ( $before !== serialize( Forminator_API::get_entry( $form, $id )->meta_data ) ) { throw new RuntimeException( 'Export changed saved metadata.' ); }
file_put_contents( $out . '/json-php-results.json', wp_json_encode( array( 'malformed_utf8_aborts' => true, 'private_cleanup' => true, 'saved_metadata_unchanged' => true, 'partial_source_removed_by_test_only' => true ) ) );
Forminator_API::delete_entry( $form, $id );
echo "PASS structured saved JSON, omission after preview, invalid UTF-8 abort and cleanup, unchanged metadata\n";
