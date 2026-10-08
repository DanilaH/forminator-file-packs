<?php
/** Remove only this suite's explicitly marked synthetic forms. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || '1' !== getenv( 'FFP_TEST_LAB' ) ) { throw new RuntimeException( 'Disposable lab only.' ); }
wp_set_current_user( 1 );
$fixture = json_decode( file_get_contents( getenv( 'FFP_TEST_FIXTURE' ) ), true );
foreach ( $fixture as $row ) {
 $id = (int) $row['form'];
 if ( '1' !== get_post_meta( $id, '_ffp_staging_fixture', true ) ) { throw new RuntimeException( 'Unmarked form; refusing cleanup.' ); }
 Forminator_API::delete_form( $id );
 if ( get_post( $id ) ) { throw new RuntimeException( 'Synthetic form was not removed.' ); }
 wp_delete_post( (int) $row['page'], true );
}
unlink( getenv( 'FFP_TEST_FIXTURE' ) );
