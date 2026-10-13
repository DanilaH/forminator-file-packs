<?php
/** Read-only integration with Forminator. @package ForminatorFilePacks */
namespace ForminatorFilePacks;
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Adapter {
 public static function available() {
  return class_exists( '\Forminator_API' ) && class_exists( '\Forminator_Database_Tables' ) && function_exists( 'forminator_get_permission' );
 }
 public static function capability() {
  return self::available() ? forminator_get_permission( 'forminator-entries' ) : 'manage_options';
 }
 public static function authorize() {
  if ( ! self::available() ) { throw new \RuntimeException( esc_html__( 'Activate Forminator to use File Packs.', 'file-packs-for-forminator' ) ); }
  if ( ! is_user_logged_in() || ! current_user_can( self::capability() ) ) { throw new \RuntimeException( esc_html__( 'You do not have permission to export submissions.', 'file-packs-for-forminator' ) ); }
 }
 public static function form( $id ) {
  self::authorize();
  $model = \Forminator_API::get_form( $id );
  if ( is_wp_error( $model ) || ! $model instanceof \Forminator_Form_Model || (int) $model->id !== (int) $id ) { throw new \RuntimeException( esc_html__( 'The selected form is unavailable.', 'file-packs-for-forminator' ) ); }
  return $model;
 }
 public static function forms( $search = '' ) {
  self::authorize();
  $query = new \WP_Query( array( 'post_type' => 'forminator_forms', 'post_status' => array( 'publish', 'draft', 'private' ), 'posts_per_page' => 100, 's' => $search, 'orderby' => 'title', 'order' => 'ASC' ) );
  return array( 'forms' => array_map( static fn( $p ) => array( 'id' => $p->ID, 'title' => (string) ( get_post_meta( $p->ID, 'forminator_form_meta', true )['settings']['formName'] ?? $p->post_title ) ), $query->posts ), 'truncated' => $query->found_posts > 100 );
 }
 /** Dates are site-local. Half-open interval avoids Forminator's 23:59:00 end-date truncation. */
 public static function entries( $form_id, $page, $from = '', $to = '' ) {
  $form = self::form( $form_id );
  global $wpdb;
  $table = \Forminator_Database_Tables::get_table_name( \Forminator_Database_Tables::FORM_ENTRY );
  $where = 'form_id = %d AND entry_type = %s AND is_spam = 0 AND status = %s';
  $args = array( $form_id, 'custom-forms', 'active' );
  foreach ( array( $from, $to ) as $date ) {
   if ( '' !== $date && ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/D', $date ) || ! \DateTimeImmutable::createFromFormat( '!Y-m-d', $date ) || \DateTimeImmutable::createFromFormat( '!Y-m-d', $date )->format( 'Y-m-d' ) !== $date ) ) { throw new \RuntimeException( esc_html__( 'Enter valid dates.', 'file-packs-for-forminator' ) ); }
  }
  if ( $from && $to && $from > $to ) { throw new \RuntimeException( esc_html__( 'The start date must precede the end date.', 'file-packs-for-forminator' ) ); }
  if ( $from ) { $where .= ' AND date_created >= %s'; $args[] = $from . ' 00:00:00'; }
  if ( $to ) { $where .= ' AND date_created < %s'; $args[] = ( new \DateTimeImmutable( $to ) )->modify( '+1 day' )->format( 'Y-m-d' ) . ' 00:00:00'; }
  // Separately bind WHERE values; table identifier and pagination are bound below.
  $where_sql = $wpdb->prepare( $where, $args ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- WHERE consists exclusively of fixed placeholder fragments above; all request-derived values are bound here.
  $count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE {$where_sql}", $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Read-only request-specific query; identifier escaped by %i and WHERE separately prepared from fixed fragments and bound values.
  $ids = $wpdb->get_col( $wpdb->prepare( "SELECT entry_id FROM %i WHERE {$where_sql} ORDER BY date_created DESC, entry_id DESC LIMIT %d OFFSET %d", $table, 25, ( max( 1, $page ) - 1 ) * 25 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Read-only request-specific query; identifier escaped by %i and WHERE separately prepared from fixed fragments and bound values.
  $rows = array();
  foreach ( $ids as $id ) {
   $entry = self::entry( $form_id, $id ); $summary = array(); $attachments = 0;
   foreach ( $entry->meta_data as $key => $meta ) {
    if ( str_starts_with( $key, '_' ) || str_starts_with( $key, 'forminator_addon_' ) ) { continue; }
    $field = $form->get_field( $key, true );
    if ( 'upload' === ( $field['type'] ?? '' ) ) {
     $paths = is_array( $meta['value'] ) ? ( $meta['value']['file']['file_path'] ?? null ) : null;
     foreach ( is_array( $paths ) ? $paths : array( $paths ) as $path ) { if ( is_string( $path ) && '' !== $path ) { ++$attachments; } }
     continue;
    }
    if ( count( $summary ) < 2 && is_scalar( $meta['value'] ) && '' !== (string) $meta['value'] ) { $summary[] = wp_html_excerpt( wp_strip_all_tags( (string) $meta['value'] ), 180, '…' ); }
   }
   $rows[] = array( 'id' => (int) $id, 'date' => $entry->date_created_sql, 'display_date' => mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $entry->date_created_sql ), 'summary' => wp_html_excerpt( implode( ' · ', $summary ), 180, '…' ), 'attachment_count' => $attachments );
  }
  return array( 'entries' => $rows, 'total' => $count, 'page' => $page, 'pages' => max( 1, (int) ceil( $count / 25 ) ), 'timezone' => wp_timezone_string() );
 }
 public static function entry( $form_id, $entry_id ) {
  self::authorize(); $entry = \Forminator_API::get_entry( $form_id, $entry_id );
  // Upstream get_entry does not enforce form ownership.
  if ( is_wp_error( $entry ) || (int) $entry->entry_id !== (int) $entry_id || (int) $entry->form_id !== (int) $form_id || 'custom-forms' !== $entry->entry_type || $entry->is_spam || 'active' !== $entry->status ) { throw new \RuntimeException( esc_html__( 'A selected submission is unavailable or does not belong to this form. Refresh the list.', 'file-packs-for-forminator' ) ); }
  return $entry;
 }
 /** Read-only keyset scan. New entries above the captured ceiling are excluded. */
 public static function scan( $form_id, $from, $to, $after = 0, $ceiling = null ) {
  self::form( $form_id );
  foreach ( array( $from, $to ) as $date ) {
   $parsed = \DateTimeImmutable::createFromFormat( '!Y-m-d', $date );
   if ( '' !== $date && ( ! $parsed || $parsed->format( 'Y-m-d' ) !== $date ) ) { throw new \RuntimeException( esc_html__( 'Enter valid dates.', 'file-packs-for-forminator' ) ); }
  }
  if ( $from && $to && $from > $to ) { throw new \RuntimeException( esc_html__( 'The start date must precede the end date.', 'file-packs-for-forminator' ) ); }
  global $wpdb;
  $table = \Forminator_Database_Tables::get_table_name( \Forminator_Database_Tables::FORM_ENTRY );
  $where = 'form_id = %d AND entry_type = %s AND is_spam = 0 AND status = %s';
  $args = array( $form_id, 'custom-forms', 'active' );
  if ( $from ) { $where .= ' AND date_created >= %s'; $args[] = $from . ' 00:00:00'; }
  if ( $to ) { $where .= ' AND date_created < %s'; $args[] = ( new \DateTimeImmutable( $to ) )->modify( '+1 day' )->format( 'Y-m-d' ) . ' 00:00:00'; }
  $where_sql = $wpdb->prepare( $where, $args ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Fixed placeholder fragments; all values bound above.
  if ( null === $ceiling ) {
   return (int) $wpdb->get_var( $wpdb->prepare( "SELECT MAX(entry_id) FROM %i WHERE {$where_sql}", $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Read-only Forminator identifier and separately prepared fixed WHERE.
  }
  return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT entry_id FROM %i WHERE {$where_sql} AND entry_id > %d AND entry_id <= %d ORDER BY entry_id ASC LIMIT 1000", $table, $after, $ceiling ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Bounded read-only keyset query, identifier and cursor bound.
 }
 /** Persisted metadata in known upload fields only; no fetching or path guessing. */
 public static function resolve_file( $form_id, $path, $url, $attachment_id ) {
  if ( ! is_string( $path ) || '' === $path || str_contains( $path, "\0" ) || preg_match( '#^[a-z][a-z0-9+.-]*://#i', $path ) ) { throw new \RuntimeException( esc_html__( 'Unsupported or remote file reference.', 'file-packs-for-forminator' ) ); }
  $uploads = wp_upload_dir();
  if ( $url && wp_parse_url( $url, PHP_URL_HOST ) !== wp_parse_url( $uploads['baseurl'], PHP_URL_HOST ) ) { throw new \RuntimeException( esc_html__( 'Remote storage is not supported in this version.', 'file-packs-for-forminator' ) ); }
  $real = realpath( $path ); $root = realpath( $uploads['basedir'] );
  if ( ! $real || ! $root || ! self::within( $real, $root ) || ! is_file( $real ) || ! is_readable( $real ) ) { throw new \RuntimeException( esc_html__( 'File is missing, unreadable, or outside allowed storage.', 'file-packs-for-forminator' ) ); }
  $managed = function_exists( 'forminator_get_upload_path' ) ? realpath( forminator_get_upload_path( $form_id, 'uploads' ) ) : false;
  $media = $attachment_id > 0 ? get_attached_file( $attachment_id, true ) : false;
  if ( ! ( $managed && self::within( $real, $managed ) ) && ! ( $media && realpath( $media ) === $real && 'attachment' === get_post_type( $attachment_id ) ) ) { throw new \RuntimeException( esc_html__( 'File cannot be verified as an attachment of this submission.', 'file-packs-for-forminator' ) ); }
  $ext = strtolower( pathinfo( $real, PATHINFO_EXTENSION ) ); $type = wp_check_filetype( basename( $real ) );
  if ( ! $type['type'] || in_array( $ext, array( 'php', 'phtml', 'phar', 'html', 'htm', 'svg', 'js', 'mjs', 'exe', 'sh', 'bat', 'cmd', 'ps1' ), true ) ) { throw new \RuntimeException( esc_html__( 'This file type is not supported for safe export.', 'file-packs-for-forminator' ) ); }
  return $real;
 }
 public static function within( $path, $root ) { return str_starts_with( wp_normalize_path( $path ), trailingslashit( wp_normalize_path( $root ) ) ); }
}
