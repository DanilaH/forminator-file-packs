<?php
/** Private temporary storage and ZIP assembly. @package ForminatorFilePacks */
namespace ForminatorFilePacks;
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class Package {
	private $directory = '';
	private $lock = '';
	private $archive;
	public $plan;
	public static function private_root() {
		$base = realpath( defined( 'FFP_PRIVATE_TEMP_DIR' ) ? FFP_PRIVATE_TEMP_DIR : sys_get_temp_dir() );
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- Private local streams, restrictive permissions and atomic locks require native operations; no FTP or remote filesystem fallback.
		if ( ! $base || ! is_writable( $base ) ) { throw new \RuntimeException( esc_html__( 'Private temporary storage is unavailable.', 'forminator-file-packs' ) ); }
		$uploads = wp_upload_dir();
		foreach ( array( ABSPATH, WP_CONTENT_DIR, $uploads['basedir'], isset( $_SERVER['DOCUMENT_ROOT'] ) && is_string( $_SERVER['DOCUMENT_ROOT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['DOCUMENT_ROOT'] ) ) : '' ) as $public ) {
			$public = $public ? realpath( $public ) : false;
			if ( $public && ( $base === $public || Adapter::within( $base, $public ) ) ) { throw new \RuntimeException( esc_html__( 'Temporary storage must be outside all public web directories.', 'forminator-file-packs' ) ); }
		}
		$root = $base . '/ffp-private-' . substr( hash( 'sha256', ABSPATH ), 0, 20 );
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir,WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Private local streams, restrictive permissions and atomic locks require native operations; no FTP or remote filesystem fallback.
		if ( is_link( $root ) || ( ! is_dir( $root ) && ! mkdir( $root, 0700 ) ) || ! chmod( $root, 0700 ) || realpath( $root ) !== $root ) { throw new \RuntimeException( esc_html__( 'Cannot create private temporary storage.', 'forminator-file-packs' ) ); }
		if ( DIRECTORY_SEPARATOR === '/' && ( fileperms( $root ) & 0077 ) ) { throw new \RuntimeException( esc_html__( 'Temporary storage permissions are unsafe.', 'forminator-file-packs' ) ); }
		// Opportunistic cleanup of old, flat jobs only; no recursive traversal or scheduler.
		foreach ( glob( $root . '/job-*', GLOB_ONLYDIR ) ?: array() as $job ) {
			if ( ! is_link( $job ) && preg_match( '/^job-[a-f0-9]{32}$/D', basename( $job ) ) && filemtime( $job ) < time() - 3600 ) { self::remove_job( $job ); }
		}
		return $root;
	}
	public function __construct( $plan, $allow_partial = false ) {
		Adapter::authorize();
		if ( $plan['warnings'] && ! $allow_partial ) { throw new \RuntimeException( esc_html__( 'Explicit acceptance is required for an incomplete package.', 'forminator-file-packs' ) ); }
		if ( ! class_exists( '\ZipArchive' ) ) { throw new \RuntimeException( esc_html__( 'PHP ZIP support is required. Ask your host to enable it.', 'forminator-file-packs' ) ); }
		$root = self::private_root();
		$this->lock = $root . '/lock-' . get_current_user_id();
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Private local streams, restrictive permissions and atomic locks require native operations; no FTP or remote filesystem fallback.
		if ( is_dir( $this->lock ) && ! is_link( $this->lock ) && filemtime( $this->lock ) < time() - 3600 ) { rmdir( $this->lock ); }
		if ( ! @mkdir( $this->lock, 0700 ) ) { $this->lock = ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Atomic private local directory creation; no FTP filesystem.
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Private local streams, restrictive permissions and atomic locks require native operations; no FTP or remote filesystem fallback.
			throw new \RuntimeException( esc_html__( 'An export is already running for your account. Please wait.', 'forminator-file-packs' ) ); }
		register_shutdown_function( array( $this, 'cleanup' ) );
		$this->directory = $root . '/job-' . bin2hex( random_bytes( 16 ) );
		if ( ! mkdir( $this->directory, 0700 ) ) { $this->cleanup(); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Atomic private local directory creation; no FTP filesystem.
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Private local streams, restrictive permissions and atomic locks require native operations; no FTP or remote filesystem fallback.
			throw new \RuntimeException( esc_html__( 'Cannot prepare the package.', 'forminator-file-packs' ) ); }
		$this->plan = $plan;
		try {
			$disk = disk_free_space( $root );
			if ( false !== $disk && $disk < $plan['bytes'] * 2 + 10485760 ) { throw new \RuntimeException( esc_html__( 'Not enough temporary disk space for this package.', 'forminator-file-packs' ) ); }
			$this->archive = new \ZipArchive();
			if ( true !== $this->archive->open( $this->path(), \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) ) { throw new \RuntimeException( esc_html__( 'Cannot create the ZIP archive.', 'forminator-file-packs' ) ); }
			$total_bytes = 0;
			$actual_files = 0;
			foreach ( $this->plan['entries'] as &$row ) {
				$included = array();
				foreach ( $row['files'] as $file ) {
					unset( $snapshot );
					try {
						$current = Adapter::resolve_file( $plan['form_id'], $file['source'], $file['url'], $file['attachment_id'] );
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Private local streams, restrictive permissions and atomic locks require native operations; no FTP or remote filesystem fallback.
						$source = fopen( $current, 'rb' );
						if ( ! $source ) { throw new \RuntimeException( esc_html__( 'File became unreadable during export.', 'forminator-file-packs' ) ); }
						$snapshot = $this->directory . '/file-' . $actual_files;
						$target = null;
						try {
							$stat = fstat( $source );
							$expected = stat( $current );
							if ( ! $stat || ! $expected || $stat['ino'] !== $expected['ino'] || $stat['dev'] !== $expected['dev'] ) { throw new \RuntimeException( esc_html__( 'File changed during export. Refresh the preview.', 'forminator-file-packs' ) ); }
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Private local streams, restrictive permissions and atomic locks require native operations; no FTP or remote filesystem fallback.
							$target = fopen( $snapshot, 'xb' );
							if ( ! $target ) { throw new \RuntimeException( esc_html__( 'Temporary storage is not writable.', 'forminator-file-packs' ) ); }
							$copied = 0;
							while ( ! feof( $source ) ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- Private local streams, restrictive permissions and atomic locks require native operations; no FTP or remote filesystem fallback.
									  $chunk = fread( $source, 1048576 );
								if ( false === $chunk ) { throw new \RuntimeException( esc_html__( 'Could not read an attachment.', 'forminator-file-packs' ) ); }
									  $copied += strlen( $chunk );
								if ( $total_bytes + $copied > MAX_BYTES ) { throw new \RuntimeException( esc_html__( 'Files grew beyond the export safety limit. Refresh the preview.', 'forminator-file-packs' ) ); }
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Private local streams, restrictive permissions and atomic locks require native operations; no FTP or remote filesystem fallback.
								if ( strlen( $chunk ) !== fwrite( $target, $chunk ) ) { throw new \RuntimeException( esc_html__( 'Could not write an attachment to temporary storage.', 'forminator-file-packs' ) ); }
							}
							if ( $copied !== $file['size'] ) { throw new \RuntimeException( esc_html__( 'File size changed during export. Refresh the preview.', 'forminator-file-packs' ) ); }
						} finally { fclose( $source ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close a local stream; WP_Filesystem has no streaming equivalent.
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Private local streams, restrictive permissions and atomic locks require native operations; no FTP or remote filesystem fallback.
							if ( is_resource( $target ) ) { fclose( $target ); } }
						if ( ! $this->archive->addFile( $snapshot, $row['folder'] . '/' . $file['name'] ) ) { throw new \RuntimeException( esc_html__( 'Could not add an attachment to the archive.', 'forminator-file-packs' ) ); }
						$total_bytes += $copied;
						++$actual_files;
						$included[] = $file;
					} catch ( \RuntimeException $e ) {
						if ( ! $allow_partial ) { throw $e; }
	// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Private local streams, restrictive permissions and atomic locks require native operations; no FTP or remote filesystem fallback.
						if ( isset( $snapshot ) && is_file( $snapshot ) ) { unlink( $snapshot ); }
						Planner::warn( $row, $file['label'], $e->getMessage() );
					}
				}
				$row['files'] = $included;
			} unset( $row );
			$this->plan['file_count'] = $actual_files;
			$this->plan['bytes'] = $total_bytes;
			$this->plan['warnings'] = array_merge( ...array_column( $this->plan['entries'], 'warnings' ) );
			foreach ( $this->plan['entries'] as $row ) { $this->add_text( $row['folder'] . '/request.html', $this->card( $row ) ); }
			$this->add_text( 'index.html', $this->index() );
			$this->add_text( 'register.csv', $this->csv() );
			$this->add_text( 'warnings.json', wp_json_encode( $this->plan['warnings'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
			if ( ! $this->archive->close() ) { throw new \RuntimeException( esc_html__( 'Could not finish the ZIP archive.', 'forminator-file-packs' ) ); }
			$this->archive = null;
		} catch ( \Throwable $e ) { $this->cleanup();
			throw $e; }
	}
	private function add_text( $name, $text ) {
		if ( ! $this->archive->addFromString( $name, $text ) ) { throw new \RuntimeException( esc_html__( 'Could not add package metadata.', 'forminator-file-packs' ) ); }
	}
	public function path() { return $this->directory . '/package.zip'; }
	private function html( $title, $body ) {
		return '<!doctype html><html lang="' . esc_attr( get_locale() ) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta http-equiv="Content-Security-Policy" content="default-src &#39;none&#39;; style-src &#39;unsafe-inline&#39;; base-uri &#39;none&#39;; form-action &#39;none&#39;"><title>' . esc_html( $title ) . '</title><style>body{font:16px/1.6 system-ui,sans-serif;max-width:1000px;margin:40px auto;padding:0 24px;color:#17212b;background:#f5f7f9}main{background:white;border:1px solid #dce2e8;border-radius:10px;padding:28px}a{color:#145ca3}h1{line-height:1.2;overflow-wrap:anywhere}dl{display:grid;grid-template-columns:minmax(120px,1fr) 3fr;gap:12px}dt{font-weight:600}dd{margin:0;white-space:pre-wrap;overflow-wrap:anywhere}.warning{background:#fff3d9;border-left:4px solid #b46b00;padding:12px}li{overflow-wrap:anywhere}@media(max-width:600px){dl{display:block}dd{margin-bottom:16px}}</style></head><body><main><h1>' . esc_html( $title ) . '</h1>' . $body . '</main></body></html>';
	}
	private function warnings( $rows ) {
		if ( ! $rows ) { return ''; }
		$text = '<div class="warning"><strong>' . esc_html__( 'Incomplete package — some attachments were omitted', 'forminator-file-packs' ) . '</strong><ul>';
		foreach ( $rows as $w ) { $text .= '<li>' . esc_html( '#' . $w['entry'] . ' · ' . $w['field'] . ': ' . $w['reason'] ) . '</li>'; }
		return $text . '</ul></div>';
	}
	private function card( $row ) {
		$body = '<p><a href="../index.html">' . esc_html__( 'Package index', 'forminator-file-packs' ) . '</a></p><p>' . esc_html( $row['date'] ) . '</p>' . $this->warnings( $row['warnings'] ) . '<dl>';
		foreach ( $row['fields'] as $f ) { $body .= '<dt>' . esc_html( $f['label'] ) . '</dt><dd>' . esc_html( $f['value'] ) . '</dd>'; }
		$body .= '</dl><h2>' . esc_html__( 'Attachments', 'forminator-file-packs' ) . '</h2><ul>';
		foreach ( $row['files'] as $f ) { $body .= '<li><a href="' . esc_attr( rawurlencode( $f['name'] ) ) . '">' . esc_html( $f['name'] ) . '</a> · ' . esc_html( $f['label'] ) . '</li>'; }
		if ( ! $row['files'] ) { $body .= '<li>' . esc_html__( 'No attachments included.', 'forminator-file-packs' ) . '</li>'; }
		return $this->html( $this->plan['form_title'] . ' — #' . $row['id'], $body . '</ul>' );
	}
	private function index() {
		// translators: %1$d: submission count; %2$d: attachment count.
		$body = '<p>' . esc_html( sprintf( __( '%1$d submissions · %2$d attachments', 'forminator-file-packs' ), count( $this->plan['entries'] ), $this->plan['file_count'] ) ) . '</p>' . $this->warnings( $this->plan['warnings'] ) . '<p><a href="register.csv">' . esc_html__( 'Open CSV register', 'forminator-file-packs' ) . '</a></p><ul>';
		foreach ( $this->plan['entries'] as $row ) { $body .= '<li><a href="' . esc_attr( $row['folder'] . '/request.html' ) . '">' . esc_html( '#' . $row['id'] . ' · ' . $row['date'] ) . '</a></li>'; }
		return $this->html( $this->plan['form_title'], $body . '</ul>' );
	}
	public static function csv_cell( $value ) {
		$value = (string) $value;
		return preg_match( '/^[\p{Z}\s\x00-\x20\x{FEFF}]*[=+\-@]/u', $value ) || preg_match( '/^[\t\r\n]/', $value ) ? "'" . $value : $value;
	}
	private function csv() {
		$columns = array();
		foreach ( $this->plan['entries'] as $row ) { foreach ( $row['fields'] as $field ) { $columns[$field['key']] = $field['label'] . ' [' . $field['key'] . ']'; } }
		$stream = fopen( 'php://temp/maxmemory:1048576', 'w+' );
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen,WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Private local streams, restrictive permissions and atomic locks require native operations; no FTP or remote filesystem fallback.
		fwrite( $stream, "\xEF\xBB\xBF" );
		fputcsv( $stream, array_map( array( self::class, 'csv_cell' ), array_merge( array( 'entry_id', 'created_at', 'card', 'attachment_count', 'warnings' ), array_values( $columns ) ) ), ',', '"', '', "\r\n" );
		foreach ( $this->plan['entries'] as $row ) {
			$values = array_column( $row['fields'], 'value', 'key' );
			$record = array( $row['id'], $row['date'], $row['folder'] . '/request.html', count( $row['files'] ), implode( '; ', array_map( static fn( $w ) => $w['field'] . ': ' . $w['reason'], $row['warnings'] ) ) );
			foreach ( $columns as $key => $label ) { $record[] = $values[$key] ?? ''; }
			fputcsv( $stream, array_map( array( self::class, 'csv_cell' ), $record ), ',', '"', '', "\r\n" );
		}
		rewind( $stream );
		$csv = stream_get_contents( $stream );
		fclose( $stream ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close a local stream; WP_Filesystem has no streaming equivalent.
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Private local streams, restrictive permissions and atomic locks require native operations; no FTP or remote filesystem fallback.
		return $csv;
	}
	private static function remove_job( $directory ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Private local streams, restrictive permissions and atomic locks require native operations; no FTP or remote filesystem fallback.
		foreach ( glob( $directory . '/*' ) ?: array() as $file ) { if ( is_file( $file ) || is_link( $file ) ) { unlink( $file ); } }
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Private local streams, restrictive permissions and atomic locks require native operations; no FTP or remote filesystem fallback.
		rmdir( $directory );
	}
	public function cleanup() {
		if ( $this->archive instanceof \ZipArchive ) { $this->archive->close();
			$this->archive = null; }
		if ( $this->directory && is_dir( $this->directory ) && ! is_link( $this->directory ) ) { self::remove_job( $this->directory ); }
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Private local streams, restrictive permissions and atomic locks require native operations; no FTP or remote filesystem fallback.
		if ( $this->lock && is_dir( $this->lock ) && ! is_link( $this->lock ) ) { rmdir( $this->lock ); }
		$this->directory = '';
		$this->lock = '';
	}
	public function __destruct() { $this->cleanup(); }
}
