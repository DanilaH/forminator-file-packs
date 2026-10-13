<?php
/** WordPress admin page and authorized endpoints. @package ForminatorFilePacks */
namespace ForminatorFilePacks;
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class Admin {
 public static function register() {
  add_filter( 'plugin_action_links_' . plugin_basename( dirname( __DIR__ ) . '/file-packs-for-forminator.php' ), array( self::class, 'action_links' ) );
  add_action( 'admin_menu', array( self::class, 'menu' ) );
  add_action( 'admin_enqueue_scripts', array( self::class, 'assets' ) );
  foreach ( array( 'forms', 'entries', 'preview', 'download', 'job_create', 'job_append', 'job_finish', 'job_start', 'job_cancel', 'job_status', 'job_review', 'job_parts', 'job_file' ) as $action ) {
   add_action( 'wp_ajax_ffp_' . $action, static function () use ( $action ) { self::endpoint( $action ); } );
  }
 }
 public static function action_links( $links ) {
  if ( current_user_can( Adapter::capability() ) ) {
   array_unshift( $links, '<a href="' . esc_url( admin_url( 'tools.php?page=file-packs-for-forminator' ) ) . '">' . esc_html__( 'Open export', 'file-packs-for-forminator' ) . '</a>' );
  }
  return $links;
 }
 public static function menu() {
  add_management_page( __( 'File Packs for Forminator', 'file-packs-for-forminator' ), __( 'Export Forminator submissions', 'file-packs-for-forminator' ), Adapter::capability(), 'file-packs-for-forminator', array( self::class, 'page' ) );
 }
 public static function assets( $hook ) {
  if ( 'tools_page_file-packs-for-forminator' !== $hook ) { return; }
  $base = plugin_dir_url( dirname( __DIR__ ) . '/file-packs-for-forminator.php' );
  wp_enqueue_style( 'ffp-admin', $base . 'assets/admin.css', array(), VERSION );
  wp_enqueue_script( 'ffp-admin', $base . 'assets/admin.js', array(), VERSION, true );
  wp_localize_script( 'ffp-admin', 'FFP', array( 'url' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'ffp_export' ), 'maxEntries' => MAX_ENTRIES, 'requestTimeout' => 90000, 'downloadTimeout' => 600000, 'strings' => array(
   'loading' => __( 'Loading…', 'file-packs-for-forminator' ), 'choose' => __( 'Choose a form', 'file-packs-for-forminator' ), 'noForms' => __( 'No forms match your search. Clear the search or try another name.', 'file-packs-for-forminator' ),
   'truncated' => __( 'Showing the first 100 matching forms. Refine your search.', 'file-packs-for-forminator' ), 'noEntries' => __( 'No completed submissions match this period. Clear the period to see other submissions.', 'file-packs-for-forminator' ),
   'selected' => __( 'Selected submissions:', 'file-packs-for-forminator' ), 'max' => __( 'Select no more than 100 submissions per package.', 'file-packs-for-forminator' ),
   'page' => __( 'Page', 'file-packs-for-forminator' ), 'of' => __( 'of', 'file-packs-for-forminator' ), 'total' => __( 'Submissions', 'file-packs-for-forminator' ),
   'dateZone' => __( 'Dates use the site timezone:', 'file-packs-for-forminator' ), 'building' => __( 'Building your ZIP. Keep this page open…', 'file-packs-for-forminator' ),
   'previewing' => __( 'Checking submissions and files…', 'file-packs-for-forminator' ), 'error' => __( 'The operation failed. Try again or refresh this page.', 'file-packs-for-forminator' ),
   'network' => __( 'The request was interrupted. Check your connection and refresh the preview before retrying.', 'file-packs-for-forminator' ),
   'complete' => __( 'ZIP ready. Download started; original submissions and files were not changed.', 'file-packs-for-forminator' ),
   'incomplete' => __( 'Incomplete ZIP ready. Download started. See the warnings in the package index.', 'file-packs-for-forminator' ),
   'submissions' => __( 'Submissions', 'file-packs-for-forminator' ), 'files' => __( 'Attachments', 'file-packs-for-forminator' ), 'warnings' => __( 'Warnings', 'file-packs-for-forminator' ),
   'noFiles' => __( 'No attachments included.', 'file-packs-for-forminator' ), 'previewChanged' => __( 'The selected data changed. Refresh the preview.', 'file-packs-for-forminator' ),
   'listChanged' => __( 'The period was edited. Your selection is kept until you apply it; export is paused until the list is refreshed.', 'file-packs-for-forminator' ),
   'emptyForms' => __( 'No forms are available. Create a form in Forminator and save submissions before exporting.', 'file-packs-for-forminator' ),
   'emptyEntries' => __( 'No saved submissions are available for this form. Check that submissions are saved in Forminator.', 'file-packs-for-forminator' ),
   'emptySelection' => __( 'Select at least one submission, then choose Check and download.', 'file-packs-for-forminator' ),
   'remove' => __( 'Remove submission', 'file-packs-for-forminator' ),
   'searchReset' => __( 'The selected form is outside the search results. The previous selection was cleared.', 'file-packs-for-forminator' ),
   'retryPreview' => __( 'Refresh preview', 'file-packs-for-forminator' ),
   'retryLoad' => __( 'Retry loading', 'file-packs-for-forminator' ),
   'ready' => __( 'Export requirements are available.', 'file-packs-for-forminator' ),
   'notReady' => __( 'Export is unavailable. Resolve the issues below and check again.', 'file-packs-for-forminator' ),
   'cancelled' => __( 'Waiting stopped in this browser. The server may still be building the package. Wait before refreshing the preview and trying again.', 'file-packs-for-forminator' ),
   'timeout' => __( 'The request took too long. The server may still be processing it. Wait before trying again, or refresh the page.', 'file-packs-for-forminator' ),
   'reload' => __( 'Refresh page', 'file-packs-for-forminator' ),
   'checkSelection' => __( 'Check and download', 'file-packs-for-forminator' ),
   'recheckSelection' => __( 'Check again', 'file-packs-for-forminator' ),
   'allDates' => __( 'All dates', 'file-packs-for-forminator' ),
   'downloadFull' => __( 'Download ZIP', 'file-packs-for-forminator' ),
   'downloadPartial' => __( 'Download available files', 'file-packs-for-forminator' ),
   'unavailableTitle' => __( 'Export is unavailable', 'file-packs-for-forminator' ),
   'attachmentRefs' => __( 'Saved attachments', 'file-packs-for-forminator' ),
   'selectEntry' => __( 'Select submission', 'file-packs-for-forminator' ),
   'allPeriod' => __( 'All submissions in the applied period are selected. New submissions after checking starts are excluded.', 'file-packs-for-forminator' ),
   'discovering' => __( 'Finding submissions…', 'file-packs-for-forminator' ),
   'checking' => __( 'Checking submissions and files…', 'file-packs-for-forminator' ),
   'review' => __( 'Contents checked. Review omissions and start the export.', 'file-packs-for-forminator' ),
   'buildingJob' => __( 'Building ZIP parts…', 'file-packs-for-forminator' ),
   'readyJob' => __( 'Your ZIP files are ready. Originals stay unchanged.', 'file-packs-for-forminator' ),
   'cancelledJob' => __( 'Task cancelled. Its private files are being deleted.', 'file-packs-for-forminator' ),
   'expiredJob' => __( 'The task expired. Check the selection again.', 'file-packs-for-forminator' ),
   'selectingJob' => __( 'Saving the selection. If interrupted, cancel this task and check the selection again.', 'file-packs-for-forminator' ),
   'processed' => __( 'Processed', 'file-packs-for-forminator' ),
   'part' => __( 'Part', 'file-packs-for-forminator' ),
   'expires' => __( 'Archives are deleted after:', 'file-packs-for-forminator' ),
   'downloadStarted' => __( 'Download started. You can download this part again until it expires.', 'file-packs-for-forminator' ),
   'reviewMore' => __( 'More selections are included. Use the checked export review to inspect all submissions.', 'file-packs-for-forminator' ),
   'jobDelay' => __( 'Progress has not advanced recently. Refresh progress to retry. Background processing can be delayed on this hosting.', 'file-packs-for-forminator' ),

  ) ) );
 }
 public static function page() {
  if ( ! current_user_can( Adapter::capability() ) ) { wp_die( esc_html__( 'Access denied.', 'file-packs-for-forminator' ) ); }
  echo '<div class="wrap ffp" id="ffp-app"><h1 class="screen-reader-text">' . esc_html__( 'File Packs for Forminator', 'file-packs-for-forminator' ) . '</h1>';
  if ( ! Adapter::available() ) { echo '<div class="notice notice-warning"><p>' . esc_html__( 'Activate Forminator to start exporting.', 'file-packs-for-forminator' ) . '</p></div></div>'; return; }
  ?>
  <div id="ffp-notification" class="ffp-notification" hidden><div id="ffp-status" role="status" aria-live="polite"></div><button id="ffp-retry" class="button" hidden></button><button id="ffp-cancel" class="button" hidden><?php esc_html_e( 'Stop waiting', 'file-packs-for-forminator' ); ?></button></div>
  <section id="ffp-workspace" class="ffp-panel" aria-labelledby="ffp-main-heading">
   <h2 id="ffp-main-heading"><?php esc_html_e( 'Download submissions with attachments', 'file-packs-for-forminator' ); ?></h2>
   <p class="ffp-intro"><?php esc_html_e( 'Select submissions to get their details and attached files in ZIP archives. Originals stay on your site.', 'file-packs-for-forminator' ); ?></p>
   <div id="ffp-environment" class="ffp-environment" aria-labelledby="ffp-environment-heading">
    <strong id="ffp-environment-heading"><?php esc_html_e( 'Checking export requirements…', 'file-packs-for-forminator' ); ?></strong>
    <p id="ffp-readiness" role="status" aria-live="polite"><?php esc_html_e( 'Checking export requirements…', 'file-packs-for-forminator' ); ?></p>
    <ul id="ffp-requirements"></ul><button id="ffp-check" class="button"><?php esc_html_e( 'Check again', 'file-packs-for-forminator' ); ?></button>
   </div>
   <div class="ffp-form-controls">
    <div class="ffp-form-choice"><label id="ffp-form-heading" for="ffp-form"><?php esc_html_e( 'Choose a form', 'file-packs-for-forminator' ); ?></label><select id="ffp-form"><option value=""><?php esc_html_e( 'Choose a form', 'file-packs-for-forminator' ); ?></option></select></div>
    <details id="ffp-form-search"><summary><?php esc_html_e( 'Find a form', 'file-packs-for-forminator' ); ?></summary><label class="screen-reader-text" for="ffp-search"><?php esc_html_e( 'Find a form', 'file-packs-for-forminator' ); ?></label><input id="ffp-search" type="search" maxlength="100"><button id="ffp-search-button" class="button"><?php esc_html_e( 'Search', 'file-packs-for-forminator' ); ?></button></details>
   </div>
   <p id="ffp-form-note" class="description"></p><a id="ffp-form-help" href="<?php echo esc_url( admin_url( 'admin.php?page=forminator' ) ); ?>" hidden><?php esc_html_e( 'Open Forminator', 'file-packs-for-forminator' ); ?></a>
   <div id="ffp-selection" hidden>
    <div class="ffp-period-bar"><strong><?php esc_html_e( 'Period', 'file-packs-for-forminator' ); ?></strong><button id="ffp-reset-period" class="button"><?php esc_html_e( 'All dates', 'file-packs-for-forminator' ); ?></button><button id="ffp-week" class="button"><?php esc_html_e( 'Last 7 days', 'file-packs-for-forminator' ); ?></button><button id="ffp-month" class="button"><?php esc_html_e( 'This month', 'file-packs-for-forminator' ); ?></button></div>
    <details id="ffp-custom-period"><summary><?php esc_html_e( 'Choose other dates', 'file-packs-for-forminator' ); ?></summary>
     <div class="ffp-controls"><div><label for="ffp-from"><?php esc_html_e( 'From', 'file-packs-for-forminator' ); ?></label><input type="date" id="ffp-from"></div><div><label for="ffp-to"><?php esc_html_e( 'Through', 'file-packs-for-forminator' ); ?></label><input type="date" id="ffp-to"></div><button id="ffp-filter" class="button"><?php esc_html_e( 'Apply period', 'file-packs-for-forminator' ); ?></button></div>
     <p id="ffp-timezone" class="description"></p>
    </details>
    <p id="ffp-applied-period" class="description"></p>
    <div class="ffp-selection-bar"><label><input id="ffp-all" type="checkbox"> <?php esc_html_e( 'Select this page', 'file-packs-for-forminator' ); ?></label><button id="ffp-select-period" class="button"><?php esc_html_e( 'Select all submissions in this period', 'file-packs-for-forminator' ); ?></button><button id="ffp-clear" class="button-link"><?php esc_html_e( 'Clear selection', 'file-packs-for-forminator' ); ?></button></div>
    <table class="widefat striped ffp-table"><thead><tr><th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Select', 'file-packs-for-forminator' ); ?></span></th><th scope="col"><?php esc_html_e( 'Submission', 'file-packs-for-forminator' ); ?></th><th scope="col"><?php esc_html_e( 'Attachments', 'file-packs-for-forminator' ); ?></th></tr></thead><tbody id="ffp-rows"></tbody></table>
    <div id="ffp-pagination" class="ffp-pagination"><button id="ffp-prev" class="button"><?php esc_html_e( 'Previous', 'file-packs-for-forminator' ); ?></button><span id="ffp-page"></span><button id="ffp-next" class="button"><?php esc_html_e( 'Next', 'file-packs-for-forminator' ); ?></button></div>
    <div class="ffp-action-bar"><strong id="ffp-count" aria-live="polite"></strong><button id="ffp-preview-button" class="button button-primary" disabled><?php esc_html_e( 'Check and download', 'file-packs-for-forminator' ); ?></button></div>
    <p id="ffp-selection-help" class="description"></p>
    <details id="ffp-selected-review"><summary><?php esc_html_e( 'Review selected submissions', 'file-packs-for-forminator' ); ?></summary><p class="description"><?php esc_html_e( 'Selection is kept when moving between pages.', 'file-packs-for-forminator' ); ?></p><ul id="ffp-selected-list"></ul></details>
   </div>
   <section id="ffp-preview" class="ffp-confirmation" aria-labelledby="ffp-preview-heading" hidden>
    <h3 id="ffp-preview-heading" tabindex="-1"><?php esc_html_e( 'Ready to download', 'file-packs-for-forminator' ); ?></h3>
    <p id="ffp-totals" class="ffp-totals"></p><div id="ffp-warnings" class="ffp-warnings" hidden></div>
    <p id="ffp-warning-help" hidden><?php esc_html_e( 'Some files cannot be included. Review the reasons before downloading.', 'file-packs-for-forminator' ); ?></p>
    <label id="ffp-partial-label" class="ffp-partial" hidden><input type="checkbox" id="ffp-partial"> <?php esc_html_e( 'I agree to download without the files listed above.', 'file-packs-for-forminator' ); ?></label>
    <button id="ffp-download" class="button button-primary"><?php esc_html_e( 'Download ZIP', 'file-packs-for-forminator' ); ?></button>
    <details id="ffp-content-review"><summary><?php esc_html_e( 'View included files', 'file-packs-for-forminator' ); ?></summary><div id="ffp-tree"></div></details>
   </section>
   <section id="ffp-job" class="ffp-confirmation" aria-labelledby="ffp-job-heading" hidden>
    <h3 id="ffp-job-heading" tabindex="-1"><?php esc_html_e( 'Large export', 'file-packs-for-forminator' ); ?></h3>
    <p id="ffp-job-status" role="status" aria-live="polite"></p><p id="ffp-job-totals"></p>
    <p id="ffp-job-background" class="description"><?php esc_html_e( 'Progress is saved on this site. You can return to this page. Background processing may wait for site visits; keeping this page open also advances the task.', 'file-packs-for-forminator' ); ?></p>
    <div id="ffp-job-review" hidden><p><?php esc_html_e( 'Review the checked submissions and omissions before starting. Large exports are split into independent ZIP files.', 'file-packs-for-forminator' ); ?></p><ul id="ffp-job-review-list"></ul><label id="ffp-job-partial-label" hidden><input id="ffp-job-partial" type="checkbox"> <?php esc_html_e( 'I accept all reported omissions, including those on other review pages.', 'file-packs-for-forminator' ); ?></label><button id="ffp-job-start" class="button button-primary"><?php esc_html_e( 'Start export', 'file-packs-for-forminator' ); ?></button></div>
    <ul id="ffp-job-parts"></ul><p id="ffp-job-expiry" class="description"></p>
    <div id="ffp-job-pager" hidden><button id="ffp-job-prev" class="button"><?php esc_html_e( 'Previous', 'file-packs-for-forminator' ); ?></button><span id="ffp-job-page"></span><button id="ffp-job-next" class="button"><?php esc_html_e( 'Next', 'file-packs-for-forminator' ); ?></button></div>
    <button id="ffp-job-resume" class="button"><?php esc_html_e( 'Refresh progress', 'file-packs-for-forminator' ); ?></button><button id="ffp-job-cancel" class="button"><?php esc_html_e( 'Cancel task and delete its archives', 'file-packs-for-forminator' ); ?></button>
    <p id="ffp-job-result" hidden><?php esc_html_e( 'Download each ZIP separately. Extract it and open index.html. Each part contains its own register and submission folders.', 'file-packs-for-forminator' ); ?></p>
   </section>
   <section id="ffp-result" class="ffp-confirmation" aria-labelledby="ffp-result-heading" hidden>
    <h3 id="ffp-result-heading" tabindex="-1"><?php esc_html_e( 'Download started', 'file-packs-for-forminator' ); ?></h3>
    <p id="ffp-result-status"></p><p><strong id="ffp-result-name"></strong></p>
    <p><?php esc_html_e( 'Extract the ZIP and open index.html to see your submissions and files.', 'file-packs-for-forminator' ); ?></p>
    <div class="ffp-result-example"><strong>index.html</strong><span><?php esc_html_e( 'Submission list → submission details → attached files', 'file-packs-for-forminator' ); ?></span></div>
    <p class="description"><?php esc_html_e( 'Keep the extracted folder together so the file links work.', 'file-packs-for-forminator' ); ?></p>
   </section>
   <details class="ffp-help"><summary><?php esc_html_e( 'How it works', 'file-packs-for-forminator' ); ?></summary>
    <p><?php esc_html_e( 'Choose a form, select submissions, check the contents and download the ZIP. No setup is required.', 'file-packs-for-forminator' ); ?></p>
    <p><?php esc_html_e( 'Extract the ZIP and open index.html in your browser to view submissions and attachments. Open register.csv in a spreadsheet application; data.json is for automated processing.', 'file-packs-for-forminator' ); ?></p>
    <p><?php esc_html_e( 'Only attachments stored locally on this site are supported. Remote storage and unsupported file types are reported in the preview.', 'file-packs-for-forminator' ); ?></p>
    <p><?php esc_html_e( 'Attachment counts in the list reflect saved file references. Availability is checked before downloading.', 'file-packs-for-forminator' ); ?></p>
    <p><?php esc_html_e( 'Changing the period clears selection after the new list loads. Dates use the site timezone.', 'file-packs-for-forminator' ); ?></p>
    <p><?php esc_html_e( 'Large exports have no fixed submission-count limit and are split into ZIP parts. Each submission in a large export may contain up to 16 MiB of attachments, 500 attachments and 1 MiB of field data. Smaller selections use the quick ZIP route. Available disk space and hosting resources still limit exports.', 'file-packs-for-forminator' ); ?></p>
    <button id="ffp-check-ready" class="button"><?php esc_html_e( 'Check export requirements', 'file-packs-for-forminator' ); ?></button>
   </details>
  </section></div>
  <?php
 }
 public static function periods( $now = null ) {
  $now = $now ?? current_datetime();
  return array( 'week' => array( 'from' => $now->modify( '-6 days' )->format( 'Y-m-d' ), 'to' => $now->format( 'Y-m-d' ) ), 'month' => array( 'from' => $now->format( 'Y-m-01' ), 'to' => $now->format( 'Y-m-d' ) ) );
 }
 public static function download_name( $plan ) {
  $title = preg_replace( '/[\x00-\x1f\x7f<>:"\/\\\\|?*]/u', '-', wp_html_excerpt( wp_strip_all_tags( $plan['form_title'] ), 100, '' ) );
  $title = Planner::short_name( trim( sanitize_file_name( $title ), '. ' ) );
  return 'form-' . (int) $plan['form_id'] . '-' . ( $title ?: 'submissions' ) . '-' . wp_date( 'Y-m-d_H-i-s' ) . '.zip';
 }
 private static function id( $value ) {
  if ( ! is_scalar( $value ) || ! preg_match( '/^[1-9][0-9]{0,17}$/D', (string) $value ) || (float) $value > PHP_INT_MAX ) { throw new \RuntimeException( esc_html__( 'Invalid identifier.', 'file-packs-for-forminator' ) ); }
  return (int) $value;
 }
 public static function endpoint( $action ) {
  try {
   Adapter::authorize();
   if ( 'POST' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '' ) || ! check_ajax_referer( 'ffp_export', 'nonce', false ) ) { wp_send_json_error( array( 'code' => 'session_expired', 'message' => __( 'Your session expired or the request is invalid. Refresh this page.', 'file-packs-for-forminator' ) ), 403 ); }
   if ( 'forms' === $action ) { $data = Adapter::forms( isset( $_POST['search'] ) && is_string( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '' ); $data['readiness'] = Package::readiness(); $data['periods'] = self::periods(); $data['job'] = Jobs::latest(); wp_send_json_success( $data ); }
   if ( str_starts_with( $action, 'job_' ) ) { self::job_endpoint( $action ); }
   $form_id = self::id( isset( $_POST['form_id'] ) && is_string( $_POST['form_id'] ) ? sanitize_text_field( wp_unslash( $_POST['form_id'] ) ) : '' );
   if ( 'entries' === $action ) {
    $page = self::id( isset( $_POST['page'] ) && is_string( $_POST['page'] ) ? sanitize_text_field( wp_unslash( $_POST['page'] ) ) : '1' ); if ( $page > 1000000 ) { throw new \RuntimeException( esc_html__( 'Invalid page.', 'file-packs-for-forminator' ) ); }
    $from = isset( $_POST['from'] ) && is_string( $_POST['from'] ) ? sanitize_text_field( wp_unslash( $_POST['from'] ) ) : '';
    $to = isset( $_POST['to'] ) && is_string( $_POST['to'] ) ? sanitize_text_field( wp_unslash( $_POST['to'] ) ) : '';
    $period = isset( $_POST['period'] ) && is_string( $_POST['period'] ) ? sanitize_text_field( wp_unslash( $_POST['period'] ) ) : '';
    $applied = null;
    if ( '' !== $period ) {
     if ( ! in_array( $period, array( 'week', 'month' ), true ) ) { throw new \RuntimeException( esc_html__( 'Enter valid dates.', 'file-packs-for-forminator' ) ); }
     $applied = self::periods()[$period]; $from = $applied['from']; $to = $applied['to'];
    }
    $data = Adapter::entries( $form_id, $page, $from, $to );
    if ( $applied ) { $data['applied_period'] = $applied; }
    wp_send_json_success( $data );
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
    header( 'Content-Disposition: attachment; filename="form-' . $form_id . '-file-pack.zip"; filename*=UTF-8\'\'' . rawurlencode( self::download_name( $plan ) ) );
    header( 'X-FFP-Warnings: ' . count( $package->plan['warnings'] ) );
    header( 'Content-Length: ' . filesize( $package->path() ) );
    readfile( $package->path() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Stream the authorized private archive without loading it into PHP memory.
   } finally { $package->cleanup(); }
   exit;
  } catch ( \RuntimeException $e ) { wp_send_json_error( array( 'message' => $e->getMessage() ), 400 ); }
  catch ( \Throwable $e ) { wp_send_json_error( array( 'message' => __( 'The export could not be completed. Check the environment and try again.', 'file-packs-for-forminator' ) ), 500 ); }
 }
 private static function job_endpoint( $action ) {
  Adapter::authorize();
  if ( ! check_ajax_referer( 'ffp_export', 'nonce', false ) ) { throw new \RuntimeException( esc_html__( 'Your session expired or the request is invalid. Refresh this page.', 'file-packs-for-forminator' ) ); }
  $id = isset( $_POST['job'] ) && is_string( $_POST['job'] ) ? sanitize_text_field( wp_unslash( $_POST['job'] ) ) : '';
  if ( 'job_create' === $action ) {
   $form = self::id( isset( $_POST['form_id'] ) && is_string( $_POST['form_id'] ) ? sanitize_text_field( wp_unslash( $_POST['form_id'] ) ) : '' );
   $mode = isset( $_POST['mode'] ) && is_string( $_POST['mode'] ) ? sanitize_text_field( wp_unslash( $_POST['mode'] ) ) : '';
   $from = isset( $_POST['from'] ) && is_string( $_POST['from'] ) ? sanitize_text_field( wp_unslash( $_POST['from'] ) ) : '';
   $to = isset( $_POST['to'] ) && is_string( $_POST['to'] ) ? sanitize_text_field( wp_unslash( $_POST['to'] ) ) : '';
   wp_send_json_success( Jobs::create( $form, $mode, $from, $to ) );
  }
  if ( 'job_append' === $action ) {
   // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Bounded JSON list; every identifier strictly validated below.
   $raw = isset( $_POST['ids'] ) && is_string( $_POST['ids'] ) ? wp_unslash( $_POST['ids'] ) : '';
   if ( strlen( $raw ) > 4000 ) { throw new \RuntimeException( esc_html__( 'Selection is too large.', 'file-packs-for-forminator' ) ); }
   $ids = json_decode( $raw, true );
   if ( ! is_array( $ids ) || ! array_is_list( $ids ) || count( $ids ) > 100 ) { throw new \RuntimeException( esc_html__( 'Invalid submission selection.', 'file-packs-for-forminator' ) ); }
   $ids = array_map( array( self::class, 'id' ), $ids );
   $sequence = isset( $_POST['sequence'] ) && is_string( $_POST['sequence'] ) ? sanitize_text_field( wp_unslash( $_POST['sequence'] ) ) : '';
   if ( ! preg_match( '/^(0|[1-9][0-9]{0,8})$/D', $sequence ) ) { throw new \RuntimeException( esc_html__( 'Invalid identifier.', 'file-packs-for-forminator' ) ); }
   wp_send_json_success( Jobs::append( $id, (int) $sequence, $ids ) );
  }
  if ( in_array( $action, array( 'job_finish', 'job_start', 'job_cancel' ), true ) ) { wp_send_json_success( Jobs::command( $id, substr( $action, 4 ), isset( $_POST['allow_partial'] ) && '1' === $_POST['allow_partial'] ) ); }
  if ( 'job_status' === $action ) {
   wp_send_json_success( Jobs::tick( $id ) ); // Ownership checked before one bounded pass; also advances cancellation cleanup without cron.
  }
  $page = self::id( isset( $_POST['page'] ) && is_string( $_POST['page'] ) ? sanitize_text_field( wp_unslash( $_POST['page'] ) ) : '1' );
  if ( 'job_review' === $action ) { wp_send_json_success( Jobs::review( $id, $page ) ); }
  if ( 'job_parts' === $action ) { wp_send_json_success( Jobs::parts( $id, $page ) ); }
  if ( 'job_file' === $action ) { Jobs::download( $id, $page - 1 ); exit; }
  throw new \RuntimeException( esc_html__( 'Invalid export task.', 'file-packs-for-forminator' ) );
 }
}
