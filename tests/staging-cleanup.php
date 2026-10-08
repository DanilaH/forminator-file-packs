<?php
/** Remove only this suite's explicitly marked synthetic forms. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || '1' !== getenv( 'FFP_TEST_LAB' ) ) { throw new RuntimeException( 'Disposable lab only.' ); }
wp_set_current_user( 1 );
$fixture = json_decode( file_get_contents( getenv( 'FFP_TEST_FIXTURE' ) ), true );
$vendor_root = dirname( ( new ReflectionClass( 'Forminator_API' ) )->getFileName(), 2 ) . '/';
require_once $vendor_root . 'admin/abstracts/class-admin-page.php';
require_once $vendor_root . 'admin/abstracts/class-admin-module-edit-page.php';
// Include this run's separately marked JSON fixture, which is not in seed-lab's list.
$marked = get_posts( array( 'post_type' => 'forminator_forms', 'post_status' => 'any', 'posts_per_page' => -1, 'meta_key' => '_ffp_staging_fixture', 'meta_value' => '1', 'fields' => 'ids' ) );
foreach ( $marked as $id ) {
 if ( ! in_array( $id, array_column( $fixture, 'form' ), false ) ) { $fixture[] = array( 'form' => $id, 'page' => 0 ); }
}
foreach ( $fixture as $row ) {
 $id = (int) $row['form'];
 if ( '1' !== get_post_meta( $id, '_ffp_staging_fixture', true ) ) { throw new RuntimeException( 'Unmarked form; refusing cleanup.' ); }
 Forminator_API::delete_form( $id );
 if ( get_post( $id ) ) { throw new RuntimeException( 'Synthetic form was not removed.' ); }
 if ( ! empty( $row['page'] ) ) { wp_delete_post( (int) $row['page'], true ); }
}
unlink( getenv( 'FFP_TEST_FIXTURE' ) );
