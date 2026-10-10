<?php
/** WordPress admin page and authorized endpoints. @package ForminatorFilePacks */
namespace ForminatorFilePacks;
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class Admin {
 public static function register() {
  add_action( 'admin_menu', array( self::class, 'menu' ) );
  add_action( 'admin_enqueue_scripts', array( self::class, 'assets' ) );
  foreach ( array( 'forms', 'entries', 'preview', 'download' ) as $action ) {
   add_action( 'wp_ajax_ffp_' . $action, static function () use ( $action ) { self::endpoint( $action ); } );
  }
 }
 public static function menu() {
  add_management_page( __( 'File Packs for Forminator', 'file-packs-for-forminator' ), __( 'File Packs', 'file-packs-for-forminator' ), Adapter::capability(), 'file-packs-for-forminator', array( self::class, 'page' ) );
 }
 public static function assets( $hook ) {
  if ( 'tools_page_file-packs-for-forminator' !== $hook ) { return; }
  $base = plugin_dir_url( dirname( __DIR__ ) . '/file-packs-for-forminator.php' );
  wp_enqueue_style( 'ffp-admin', $base . 'assets/admin.css', array(), VERSION );
  wp_enqueue_script( 'ffp-admin', $base . 'assets/admin.js', array(), VERSION, true );
  wp_localize_script( 'ffp-admin', 'FFP', array( 'url' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'ffp_export' ), 'maxEntries' => MAX_ENTRIES, 'strings' => array(
   'loading' => __( 'Loading…', 'file-packs-for-forminator' ), 'choose' => __( 'Choose a form', 'file-packs-for-forminator' ), 'noForms' => __( 'No forms found.', 'file-packs-for-forminator' ),
   'truncated' => __( 'Showing the first 100 matching forms. Refine your search.', 'file-packs-for-forminator' ), 'noEntries' => __( 'No completed submissions match this period.', 'file-packs-for-forminator' ),
   'selected' => __( 'Selected submissions:', 'file-packs-for-forminator' ), 'max' => __( 'Select no more than 100 submissions per package.', 'file-packs-for-forminator' ),
   'page' => __( 'Page', 'file-packs-for-forminator' ), 'of' => __( 'of', 'file-packs-for-forminator' ), 'total' => __( 'Submissions', 'file-packs-for-forminator' ),
   'dateZone' => __( 'Dates use the site timezone:', 'file-packs-for-forminator' ), 'building' => __( 'Building your ZIP. Keep this page open…', 'file-packs-for-forminator' ),
   'previewing' => __( 'Checking submissions and files…', 'file-packs-for-forminator' ), 'error' => __( 'The operation failed. Try again or refresh this page.', 'file-packs-for-forminator' ),
   'network' => __( 'The request was interrupted. Check your connection and refresh the preview before retrying.', 'file-packs-for-forminator' ),
   'complete' => __( 'ZIP ready. Download started; original submissions and files were not changed.', 'file-packs-for-forminator' ),
   'incomplete' => __( 'Incomplete ZIP ready. Download started. See the warnings in the package index.', 'file-packs-for-forminator' ),
   'submissions' => __( 'Submissions', 'file-packs-for-forminator' ), 'files' => __( 'Files', 'file-packs-for-forminator' ), 'warnings' => __( 'Warnings', 'file-packs-for-forminator' ),
   'noFiles' => __( 'No attachments included.', 'file-packs-for-forminator' ), 'previewChanged' => __( 'The selected data changed. Refresh the preview.', 'file-packs-for-forminator' ),
   'listChanged' => __( 'The list changed. Apply the period to reload submissions.', 'file-packs-for-forminator' ),
   'selectEntry' => __( 'Select submission', 'file-packs-for-forminator' ),
  ) ) );
 }
 public static function page() {
  if ( ! current_user_can( Adapter::capability() ) ) { wp_die( esc_html__( 'Access denied.', 'file-packs-for-forminator' ) ); }
  echo '<div class="wrap ffp" id="ffp-app"><h1>' . esc_html__( 'File Packs for Forminator', 'file-packs-for-forminator' ) . '</h1><p class="ffp-intro">' . esc_html__( 'Turn submissions and their attachments into a ready-to-share ZIP. Originals stay untouched.', 'file-packs-for-forminator' ) . '</p>';
  if ( ! Adapter::available() ) { echo '<div class="notice notice-warning"><p>' . esc_html__( 'Activate Forminator to start exporting.', 'file-packs-for-forminator' ) . '</p></div></div>'; return; }
  ?>
  <div id="ffp-status" class="ffp-status" role="status" aria-live="polite"></div>
  <section class="ffp-panel" aria-labelledby="ffp-form-heading">
   <h2 id="ffp-form-heading"><?php esc_html_e( '1. Choose a form', 'file-packs-for-forminator' ); ?></h2>
   <div class="ffp-controls"><div><label for="ffp-search"><?php esc_html_e( 'Find a form', 'file-packs-for-forminator' ); ?></label><input id="ffp-search" type="search" maxlength="100"><button id="ffp-search-button" class="button"><?php esc_html_e( 'Search', 'file-packs-for-forminator' ); ?></button></div><div><label for="ffp-form"><?php esc_html_e( 'Form', 'file-packs-for-forminator' ); ?></label><select id="ffp-form"><option value=""><?php esc_html_e( 'Choose a form', 'file-packs-for-forminator' ); ?></option></select></div></div>
   <p id="ffp-form-note" class="description"></p>
  </section>
  <section id="ffp-selection" class="ffp-panel" aria-labelledby="ffp-entries-heading" hidden>
   <h2 id="ffp-entries-heading"><?php esc_html_e( '2. Select submissions', 'file-packs-for-forminator' ); ?></h2>
   <div class="ffp-controls"><div><label for="ffp-from"><?php esc_html_e( 'From', 'file-packs-for-forminator' ); ?></label><input type="date" id="ffp-from"></div><div><label for="ffp-to"><?php esc_html_e( 'Through', 'file-packs-for-forminator' ); ?></label><input type="date" id="ffp-to"></div><button id="ffp-filter" class="button"><?php esc_html_e( 'Apply period', 'file-packs-for-forminator' ); ?></button></div>
   <p id="ffp-timezone" class="description"></p>
   <p class="description"><?php esc_html_e( 'Only completed, non-spam submissions are shown. Applying a period clears the current selection.', 'file-packs-for-forminator' ); ?></p>
   <div class="ffp-selection-bar"><label><input id="ffp-all" type="checkbox"> <?php esc_html_e( 'Select this page', 'file-packs-for-forminator' ); ?></label><strong id="ffp-count" aria-live="polite"></strong><button id="ffp-clear" class="button-link"><?php esc_html_e( 'Clear selection', 'file-packs-for-forminator' ); ?></button></div>
   <table class="widefat striped ffp-table"><thead><tr><th scope="col"><?php esc_html_e( 'Select', 'file-packs-for-forminator' ); ?></th><th scope="col"><?php esc_html_e( 'Submission', 'file-packs-for-forminator' ); ?></th><th scope="col"><?php esc_html_e( 'Created', 'file-packs-for-forminator' ); ?></th><th scope="col"><?php esc_html_e( 'Summary', 'file-packs-for-forminator' ); ?></th></tr></thead><tbody id="ffp-rows"></tbody></table>
   <div class="ffp-pagination"><button id="ffp-prev" class="button"><?php esc_html_e( 'Previous', 'file-packs-for-forminator' ); ?></button><span id="ffp-page"></span><button id="ffp-next" class="button"><?php esc_html_e( 'Next', 'file-packs-for-forminator' ); ?></button></div>
   <button id="ffp-preview-button" class="button button-primary" disabled><?php esc_html_e( 'Preview package', 'file-packs-for-forminator' ); ?></button>
   <p class="description"><?php esc_html_e( 'Current safety limits: 100 submissions, 500 attachments, 100 MiB of attachment data per package. Use smaller batches on limited hosting.', 'file-packs-for-forminator' ); ?></p>
  </section>
  <section id="ffp-preview" class="ffp-panel" aria-labelledby="ffp-preview-heading" hidden>
   <h2 id="ffp-preview-heading" tabindex="-1"><?php esc_html_e( '3. Review and download', 'file-packs-for-forminator' ); ?></h2>
   <p id="ffp-totals" class="ffp-totals"></p><div id="ffp-warnings" class="ffp-warnings" hidden></div>
   <p><?php esc_html_e( 'ZIP includes index.html, register.csv, data.json, warnings.json and one folder per submission with its card and attachments.', 'file-packs-for-forminator' ); ?></p>
   <div id="ffp-tree"></div>
   <label id="ffp-partial-label" class="ffp-partial" hidden><input type="checkbox" id="ffp-partial"> <?php esc_html_e( 'I understand that the package will be incomplete. Export the available files.', 'file-packs-for-forminator' ); ?></label>
   <button id="ffp-download" class="button button-primary"><?php esc_html_e( 'Build and download ZIP', 'file-packs-for-forminator' ); ?></button>
  </section></div>
  <?php
 }
 private static function id( $value ) {
  if ( ! is_scalar( $value ) || ! preg_match( '/^[1-9][0-9]{0,17}$/D', (string) $value ) || (float) $value > PHP_INT_MAX ) { throw new \RuntimeException( esc_html__( 'Invalid identifier.', 'file-packs-for-forminator' ) ); }
  return (int) $value;
 }
 public static function endpoint( $action ) {
  try {
   Adapter::authorize();
   if ( 'POST' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '' ) || ! check_ajax_referer( 'ffp_export', 'nonce', false ) ) { wp_send_json_error( array( 'message' => __( 'Your session expired or the request is invalid. Refresh this page.', 'file-packs-for-forminator' ) ), 403 ); }
   if ( 'forms' === $action ) { wp_send_json_success( Adapter::forms( isset( $_POST['search'] ) && is_string( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '' ) ); }
   $form_id = self::id( isset( $_POST['form_id'] ) && is_string( $_POST['form_id'] ) ? sanitize_text_field( wp_unslash( $_POST['form_id'] ) ) : '' );
   if ( 'entries' === $action ) {
    $page = self::id( isset( $_POST['page'] ) && is_string( $_POST['page'] ) ? sanitize_text_field( wp_unslash( $_POST['page'] ) ) : '1' ); if ( $page > 1000000 ) { throw new \RuntimeException( esc_html__( 'Invalid page.', 'file-packs-for-forminator' ) ); }
    $from = isset( $_POST['from'] ) && is_string( $_POST['from'] ) ? sanitize_text_field( wp_unslash( $_POST['from'] ) ) : '';
    $to = isset( $_POST['to'] ) && is_string( $_POST['to'] ) ? sanitize_text_field( wp_unslash( $_POST['to'] ) ) : '';
    wp_send_json_success( Adapter::entries( $form_id, $page, $from, $to ) );
   }
   // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Raw JSON is size-limited, decoded as a list, and each identifier strictly validated below.
   $raw = isset( $_POST['ids'] ) && is_string( $_POST['ids'] ) ? wp_unslash( $_POST['ids'] ) : '';
   if ( strlen( $raw ) > 4000 ) { throw new \RuntimeException( esc_html__( 'Selection is too large.', 'file-packs-for-forminator' ) ); }
   $ids = json_decode( $raw, true );
   if ( ! is_array( $ids ) || ! array_is_list( $ids ) || count( $ids ) > MAX_ENTRIES ) { throw new \RuntimeException( esc_html__( 'Invalid submission selection.', 'file-packs-for-forminator' ) ); }
   $ids = array_map( array( self::class, 'id' ), $ids ); $plan = Planner::build( $form_id, $ids );
   if ( 'preview' === $action ) { wp_send_json_success( Planner::preview( $plan ) ); }
   $fingerprint = isset( $_POST['fingerprint'] ) && is_string( $_POST['fingerprint'] ) ? sanitize_text_field( wp_unslash( $_POST['fingerprint'] ) ) : '';
   if ( ! hash_equals( $plan['fingerprint'], $fingerprint ) ) { wp_send_json_error( array( 'message' => __( 'The selected data changed. Refresh the preview before exporting.', 'file-packs-for-forminator' ), 'refresh' => true ), 409 ); }
   $partial = isset( $_POST['allow_partial'] ) && '1' === $_POST['allow_partial'];
   if ( $plan['warnings'] && ! $partial ) { throw new \RuntimeException( esc_html__( 'Review the warnings and explicitly accept an incomplete package.', 'file-packs-for-forminator' ) ); }
   $package = new Package( $plan, $partial );
   try {
    Adapter::authorize();
    if ( headers_sent() ) { throw new \RuntimeException( esc_html__( 'Another plugin interrupted the download. Please try again.', 'file-packs-for-forminator' ) ); }
    while ( ob_get_level() ) { ob_end_clean(); }
    nocache_headers();
    header( 'Content-Type: application/zip' ); header( 'X-Content-Type-Options: nosniff' );
    header( 'Content-Disposition: attachment; filename="form-' . $form_id . '-file-pack.zip"' );
    header( 'X-FFP-Warnings: ' . count( $package->plan['warnings'] ) );
    header( 'Content-Length: ' . filesize( $package->path() ) );
    readfile( $package->path() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Stream the authorized private archive without loading it into PHP memory.
   } finally { $package->cleanup(); }
   exit;
  } catch ( \RuntimeException $e ) { wp_send_json_error( array( 'message' => $e->getMessage() ), 400 ); }
  catch ( \Throwable $e ) { wp_send_json_error( array( 'message' => __( 'The export could not be completed. Check the environment and try again.', 'file-packs-for-forminator' ) ), 500 ); }
 }
}
