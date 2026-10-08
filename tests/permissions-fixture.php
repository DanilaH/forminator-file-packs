<?php
/** Configure only synthetic permissions; caller MUST invoke cleanup in finally. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || '1' !== getenv( 'FFP_TEST_LAB' ) ) { throw new RuntimeException( 'Disposable lab only.' ); }
$path = getenv( 'FFP_PERMISSIONS_FIXTURE' ); $mode = getenv( 'FFP_PERMISSIONS_MODE' );
$cap = forminator_get_permission_cap_map()['forminator-entries'];
if ( 'setup' === $mode ) {
 if ( get_user_by( 'login', 'ffp-http-reader' ) || get_role( 'ffp_lab_reader' ) ) { throw new RuntimeException( 'Prior synthetic user/role exists; clean it up first.' ); }
 add_role( 'ffp_lab_reader', 'Disposable File Packs reader', array( 'read' => true ) );
 $id = wp_insert_user( array( 'user_login' => 'ffp-http-reader', 'user_pass' => getenv( 'FFP_TEST_PASSWORD' ), 'role' => 'subscriber' ) );
 if ( is_wp_error( $id ) ) { throw new RuntimeException( $id->get_error_message() ); }
 file_put_contents( $path, wp_json_encode( array( 'user' => $id, 'original' => get_option( 'forminator_permissions', array() ) ) ) );
} else {
 $fixture = json_decode( file_get_contents( $path ), true ); $id = $fixture['user']; $user = get_user_by( 'id', $id );
 if ( 'cleanup' === $mode ) {
  update_option( 'forminator_permissions', $fixture['original'] ); require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user( $id ); remove_role( 'ffp_lab_reader' ); unlink( $path ); return;
 }
 if ( 'revoked' === $mode ) { $user->remove_cap( $cap ); get_role( 'ffp_lab_reader' )->remove_cap( $cap ); return; }
 if ( 'specific' === $mode ) { $user->set_role( 'subscriber' ); $user->add_cap( $cap ); $rules = array( array( 'permission_type' => 'specific', 'specific_user' => array( $id ), $cap => true ) ); }
 elseif ( 'role' === $mode || 'excluded' === $mode ) { $user->remove_cap( $cap ); $user->set_role( 'ffp_lab_reader' ); get_role( 'ffp_lab_reader' )->add_cap( $cap ); $rules = array( array( 'permission_type' => 'role', 'user_role' => 'ffp_lab_reader', 'exclude_users' => 'excluded' === $mode ? array( $id ) : array(), $cap => true ) ); }
 else { throw new RuntimeException( 'Unknown test permission mode.' ); }
 update_option( 'forminator_permissions', $rules );
}
