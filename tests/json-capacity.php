<?php
/** Adversarial text at the 8 MiB metadata ceiling; isolated disposable CLI process. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || '1' !== getenv( 'FFP_TEST_LAB' ) ) { throw new RuntimeException( 'Disposable lab only.' ); }
wp_set_current_user( 1 ); ini_set( 'memory_limit', '256M' );
$form = Forminator_API::add_form( 'JSON metadata ceiling', array(), array( 'formName' => 'JSON metadata ceiling', 'store_submissions' => '1' ) );
$value = str_repeat( "\0", 1048576 ); $meta = array();
for ( $i = 1; $i <= 8; ++$i ) { Forminator_API::add_form_field( $form, 'textarea', array( 'field_label' => 'Large field ' . $i ) ); $meta[] = array( 'name' => 'textarea-' . $i, 'value' => $value ); }
$id = Forminator_API::add_form_entry( $form, wp_slash( $meta ) );
$plan = \ForminatorFilePacks\Planner::build( $form, array( $id ) );
if ( 8388608 !== $plan['metadata_bytes'] ) { throw new RuntimeException( 'Expected exact metadata ceiling.' ); }
$start = microtime( true ); $package = new \ForminatorFilePacks\Package( $plan );
copy( $package->path(), getenv( 'FFP_TEST_ARTIFACTS' ) . '/json-metadata-ceiling.zip' );
$result = array( 'source_metadata_bytes' => $plan['metadata_bytes'], 'json_bytes' => 0, 'php_memory_limit' => ini_get( 'memory_limit' ), 'peak_memory_bytes' => memory_get_peak_usage( true ), 'seconds' => round( microtime( true ) - $start, 3 ), 'expected_field_sha256' => hash( 'sha256', $value ) );
$zip = new ZipArchive(); $zip->open( $package->path() ); $result['json_bytes'] = $zip->statName( 'data.json' )['size']; $zip->close(); $package->cleanup();
Forminator_API::delete_entry( $form, $id );
file_put_contents( getenv( 'FFP_TEST_ARTIFACTS' ) . '/json-capacity-results.json', wp_json_encode( $result, JSON_PRETTY_PRINT ) );
echo 'PASS 8 MiB metadata ceiling with sixfold JSON escaping: ' . wp_json_encode( $result ) . "\n";
