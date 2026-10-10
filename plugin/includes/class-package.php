<?php
/** Private temporary storage and ZIP assembly. @package ForminatorFilePacks */
namespace ForminatorFilePacks;
if ( ! defined( 'ABSPATH' ) ) { exit; }
/** Storage/resource failures must abort even explicitly partial exports. */
class StorageException extends \RuntimeException {}
final class Package {
	private $directory = '';
	private $lock = '';
	private $archive;
	private $deadline;
	private $reserve = '';
	public $plan;
	public static function private_root() {
		$base = realpath( defined( 'FFP_PRIVATE_TEMP_DIR' ) ? FFP_PRIVATE_TEMP_DIR : sys_get_temp_dir() );
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- Private local streams, restrictive permissions and atomic locks require native operations; no FTP or remote filesystem fallback.
		if ( ! $base || ! is_writable( $base ) ) { throw new \RuntimeException( esc_html__( 'Private temporary storage is unavailable.', 'file-packs-for-forminator' ) ); }
		$uploads = wp_upload_dir();
		foreach ( array( ABSPATH, WP_CONTENT_DIR, $uploads['basedir'], isset( $_SERVER['DOCUMENT_ROOT'] ) && is_string( $_SERVER['DOCUMENT_ROOT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['DOCUMENT_ROOT'] ) ) : '' ) as $public ) {
			$public = $public ? realpath( $public ) : false;
			if ( $public && ( $base === $public || Adapter::within( $base, $public ) ) ) { throw new \RuntimeException( esc_html__( 'Temporary storage must be outside all public web directories.', 'file-packs-for-forminator' ) ); }
		}
		$root = $base . '/ffp-private-' . substr( hash( 'sha256', ABSPATH ), 0, 20 );
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir,WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Private local streams, restrictive permissions and atomic locks require native operations; no FTP or remote filesystem fallback.
		if ( is_link( $root ) || ( ! is_dir( $root ) && ! @mkdir( $root, 0700 ) ) || ! @chmod( $root, 0700 ) || realpath( $root ) !== $root ) { throw new \RuntimeException( esc_html__( 'Cannot create private temporary storage.', 'file-packs-for-forminator' ) ); }
		if ( DIRECTORY_SEPARATOR === '/' && ( fileperms( $root ) & 0077 ) ) { throw new \RuntimeException( esc_html__( 'Temporary storage permissions are unsafe.', 'file-packs-for-forminator' ) ); }
		// Opportunistic cleanup of old, flat jobs only; no recursive traversal or scheduler.
		foreach ( glob( $root . '/job-*', GLOB_ONLYDIR ) ?: array() as $job ) {
			if ( ! is_link( $job ) && preg_match( '/^job-[a-f0-9]{32}$/D', basename( $job ) ) && filemtime( $job ) < time() - 3600 ) { self::remove_job( $job ); }
		}
		return $root;
	}
	public function __construct( $plan, $allow_partial = false ) {
		Adapter::authorize();
		$limit = (int) ini_get( 'max_execution_time' );
		$this->deadline = microtime( true ) + ( $limit > 0 ? max( 1, min( 45, $limit - 5 ) ) : 45 );
		$this->guard( $plan['metadata_bytes'] * 4 + 16777216 );
		$this->reserve = str_repeat( 'x', 1048576 );
		if ( $plan['warnings'] && ! $allow_partial ) { throw new \RuntimeException( esc_html__( 'Explicit acceptance is required for an incomplete package.', 'file-packs-for-forminator' ) ); }
		if ( ! class_exists( '\ZipArchive' ) ) { throw new \RuntimeException( esc_html__( 'PHP ZIP support is required. Ask your host to enable it.', 'file-packs-for-forminator' ) ); }
		$root = self::private_root();
		$this->lock = $root . '/lock-' . get_current_user_id();
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Private local streams, restrictive permissions and atomic locks require native operations; no FTP or remote filesystem fallback.
		if ( is_dir( $this->lock ) && ! is_link( $this->lock ) && filemtime( $this->lock ) < time() - 3600 ) { @rmdir( $this->lock ); }
		if ( ! @mkdir( $this->lock, 0700 ) ) { $this->lock = ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Atomic private local directory creation; no FTP filesystem.
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Private local streams, restrictive permissions and atomic locks require native operations; no FTP or remote filesystem fallback.
			throw new \RuntimeException( esc_html__( 'An export is already running for your account. Please wait.', 'file-packs-for-forminator' ) ); }
		register_shutdown_function( array( $this, 'cleanup' ) );
		$this->directory = $root . '/job-' . bin2hex( random_bytes( 16 ) );
		if ( ! @mkdir( $this->directory, 0700 ) ) { $this->cleanup(); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Atomic private local directory creation; no FTP filesystem.
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Private local streams, restrictive permissions and atomic locks require native operations; no FTP or remote filesystem fallback.
			throw new \RuntimeException( esc_html__( 'Cannot prepare the package.', 'file-packs-for-forminator' ) ); }
		$this->plan = $plan;
		try {
			$disk = @disk_free_space( $root );
			if ( false !== $disk && $disk < $plan['bytes'] * 2 + 10485760 ) { throw new StorageException( esc_html__( 'Not enough temporary disk space for this package.', 'file-packs-for-forminator' ) ); }
			$this->archive = new \ZipArchive();
			if ( true !== $this->archive->open( $this->path(), \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) ) { throw new StorageException( esc_html__( 'Cannot create the ZIP archive.', 'file-packs-for-forminator' ) ); }
			$total_bytes = 0;
			$actual_files = 0;
			foreach ( $this->plan['entries'] as &$row ) {
				$included = array();
				foreach ( $row['files'] as $file ) {
					unset( $snapshot );
					try {
						$this->guard();
						$current = Adapter::resolve_file( $plan['form_id'], $file['source'], $file['url'], $file['attachment_id'] );
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Private local streams, restrictive permissions and atomic locks require native operations; no FTP or remote filesystem fallback.
						$source = @fopen( $current, 'rb' );
						if ( ! $source ) { throw new \RuntimeException( esc_html__( 'File became unreadable during export.', 'file-packs-for-forminator' ) ); }
						$snapshot = $this->directory . '/file-' . $actual_files;
						$target = null;
						try {
							$stat = fstat( $source );
							$expected = @stat( $current );
							if ( ! $stat || ! $expected || $stat['ino'] !== $expected['ino'] || $stat['dev'] !== $expected['dev'] ) { throw new \RuntimeException( esc_html__( 'File changed during export. Refresh the preview.', 'file-packs-for-forminator' ) ); }
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Private local streams, restrictive permissions and atomic locks require native operations; no FTP or remote filesystem fallback.
							$target = @fopen( $snapshot, 'xb' );
							if ( ! $target ) { throw new StorageException( esc_html__( 'Temporary storage is not writable.', 'file-packs-for-forminator' ) ); }
							$copied = 0;
							while ( ! feof( $source ) ) {
								$this->guard();
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- Private local streams, restrictive permissions and atomic locks require native operations; no FTP or remote filesystem fallback.
									  $chunk = @fread( $source, 1048576 );
								if ( false === $chunk ) { throw new \RuntimeException( esc_html__( 'Could not read an attachment.', 'file-packs-for-forminator' ) ); }
									  $copied += strlen( $chunk );
								if ( $total_bytes + $copied > MAX_BYTES ) { throw new \RuntimeException( esc_html__( 'Files grew beyond the export safety limit. Refresh the preview.', 'file-packs-for-forminator' ) ); }
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Private local streams, restrictive permissions and atomic locks require native operations; no FTP or remote filesystem fallback.
								if ( strlen( $chunk ) !== @fwrite( $target, $chunk ) ) { throw new StorageException( esc_html__( 'Could not write an attachment to temporary storage.', 'file-packs-for-forminator' ) ); }
							}
							if ( $copied !== $file['size'] ) { throw new \RuntimeException( esc_html__( 'File size changed during export. Refresh the preview.', 'file-packs-for-forminator' ) ); }
						} finally { fclose( $source ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close a local stream; WP_Filesystem has no streaming equivalent.
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Private local streams, restrictive permissions and atomic locks require native operations; no FTP or remote filesystem fallback.
							if ( is_resource( $target ) ) { fclose( $target ); } }
						if ( ! $this->archive->addFile( $snapshot, $row['folder'] . '/' . $file['name'] ) ) { throw new StorageException( esc_html__( 'Could not add an attachment to the archive.', 'file-packs-for-forminator' ) ); }
						$total_bytes += $copied;
						++$actual_files;
						$included[] = $file;
					} catch ( \RuntimeException $e ) {
						if ( $e instanceof StorageException || ! $allow_partial ) { throw $e; }
	// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Private local streams, restrictive permissions and atomic locks require native operations; no FTP or remote filesystem fallback.
						if ( isset( $snapshot ) && is_file( $snapshot ) ) { @unlink( $snapshot ); }
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
			$this->json_register();
			$this->add_text( 'warnings.json', wp_json_encode( $this->plan['warnings'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
			$this->guard();
			$archive = $this->archive;
			$this->archive = null;
			if ( ! $archive->close() ) { throw new StorageException( esc_html__( 'Could not finish the ZIP archive.', 'file-packs-for-forminator' ) ); }
			$this->archive = null;
		} catch ( \Throwable $e ) { $this->cleanup();
			throw $e; }
	}
	private function guard( $needed = 4194304 ) {
		$memory = wp_convert_hr_to_bytes( ini_get( 'memory_limit' ) );
		if ( $memory > 0 && memory_get_usage( true ) + $needed > $memory ) { throw new StorageException( esc_html__( 'Not enough PHP memory for this package. Select a smaller batch or ask your host to raise the memory limit.', 'file-packs-for-forminator' ) ); }
		if ( microtime( true ) >= $this->deadline ) { throw new StorageException( esc_html__( 'The export reached its time limit. Select a smaller batch and try again.', 'file-packs-for-forminator' ) ); }
	}
	private function add_text( $name, $text ) {
		$this->guard( strlen( $text ) + 4194304 );
		if ( ! $this->archive->addFromString( $name, $text ) ) { throw new StorageException( esc_html__( 'Could not add package metadata.', 'file-packs-for-forminator' ) ); }
	}
	public function path() { return $this->directory . '/package.zip'; }
	private function json_register() {
		$path = $this->directory . '/data.json';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Checked local stream inside the private export job; no public or remote storage.
		$stream = @fopen( $path, 'xb' );
		if ( ! $stream ) { throw new StorageException( esc_html__( 'Could not add package metadata.', 'file-packs-for-forminator' ) ); }
		$write = function ( $text ) use ( $stream ) {
			$this->guard();
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Checked local stream; a short write aborts the whole package.
			if ( strlen( $text ) !== @fwrite( $stream, $text ) ) { throw new StorageException( esc_html__( 'Could not add package metadata.', 'file-packs-for-forminator' ) ); }
		};
		$encode = static function ( $value ) {
			try { return wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR ); }
			catch ( \JsonException $e ) { throw new StorageException( esc_html__( 'Could not add package metadata.', 'file-packs-for-forminator' ) ); }
		};
		try {
			$header = array( 'schema_version' => 1, 'plugin_version' => VERSION, 'generated_at' => gmdate( 'c' ), 'timezone' => wp_timezone_string(), 'form' => array( 'id' => $this->plan['form_id'], 'title' => $this->plan['form_title'] ), 'status' => $this->plan['warnings'] ? 'incomplete' : 'complete', 'submission_count' => count( $this->plan['entries'] ), 'attachment_count' => $this->plan['file_count'], 'attachment_bytes' => $this->plan['bytes'], 'warnings' => $this->plan['warnings'] );
			$write( substr( $encode( $header ), 0, -1 ) . ',"submissions":[' );
			$first = true;
			foreach ( $this->plan['entries'] as $row ) {
				$files = array_map( static fn( $f ) => array( 'field' => $f['field'], 'label' => $f['label'], 'name' => $f['name'], 'path' => $row['folder'] . '/' . $f['name'], 'bytes' => $f['size'] ), $row['files'] );
				if ( ! $first ) { $write( ',' ); }
				$write( substr( $encode( array( 'id' => $row['id'], 'created_at' => $row['date'], 'card' => $row['folder'] . '/request.html', 'attachments' => $files, 'warnings' => $row['warnings'] ) ), 0, -1 ) . ',"fields":[' );
				$first_field = true;
				foreach ( $row['fields'] as $field ) {
					// Sixfold escaping plus temporary encoder allocation, bounded per field.
					$this->guard( strlen( $field['value'] ) * 12 + 4194304 );
					if ( ! $first_field ) { $write( ',' ); }
					$write( $encode( array( 'key' => $field['key'], 'label' => $field['label'], 'type' => $field['type'], 'value' => $field['data'] ) ) );
					$first_field = false;
				}
				$write( ']}' );
				$first = false;
			}
			$write( ']}' );
		} finally {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close the private metadata stream on success and failure.
			fclose( $stream );
		}
		if ( ! $this->archive->addFile( $path, 'data.json' ) ) { throw new StorageException( esc_html__( 'Could not add package metadata.', 'file-packs-for-forminator' ) ); }
	}
	private function html( $title, $body ) {
		return '<!doctype html><html lang="' . esc_attr( str_replace( '_', '-', determine_locale() ) ) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta http-equiv="Content-Security-Policy" content="default-src &#39;none&#39;; style-src &#39;unsafe-inline&#39;; base-uri &#39;none&#39;; form-action &#39;none&#39;"><title>' . esc_html( $title ) . '</title><style>body{font:16px/1.6 system-ui,sans-serif;max-width:1000px;margin:40px auto;padding:0 24px;color:#17212b;background:#f5f7f9}main{background:white;border:1px solid #dce2e8;border-radius:10px;padding:28px}a{color:#145ca3}h1{line-height:1.2;overflow-wrap:anywhere}dl{display:grid;grid-template-columns:minmax(120px,1fr) 3fr;gap:12px}dt{font-weight:600}dd{margin:0;white-space:pre-wrap;overflow-wrap:anywhere}.warning{background:#fff3d9;border-left:4px solid #b46b00;padding:12px}li{overflow-wrap:anywhere}@media(max-width:600px){dl{display:block}dd{margin-bottom:16px}}</style></head><body><main><h1>' . esc_html( $title ) . '</h1>' . $body . '</main></body></html>';
	}
	private function warnings( $rows ) {
		if ( ! $rows ) { return ''; }
		$text = '<div class="warning"><strong>' . esc_html__( 'Incomplete package — some attachments were omitted', 'file-packs-for-forminator' ) . '</strong><ul>';
		foreach ( $rows as $w ) { $text .= '<li>' . esc_html( '#' . $w['entry'] . ' · ' . $w['field'] . ': ' . $w['reason'] ) . '</li>'; }
		return $text . '</ul></div>';
	}
	private function card( $row ) {
		$body = '<p><a href="../index.html">' . esc_html__( 'Package index', 'file-packs-for-forminator' ) . '</a></p><p>' . esc_html( $row['date'] ) . '</p>' . $this->warnings( $row['warnings'] ) . '<dl>';
		foreach ( $row['fields'] as $f ) { $body .= '<dt>' . esc_html( $f['label'] ) . '</dt><dd>' . esc_html( $f['value'] ) . '</dd>'; }
		$body .= '</dl><h2>' . esc_html__( 'Attachments', 'file-packs-for-forminator' ) . '</h2><ul>';
		foreach ( $row['files'] as $f ) { $body .= '<li><a href="' . esc_attr( rawurlencode( $f['name'] ) ) . '">' . esc_html( $f['name'] ) . '</a> · ' . esc_html( $f['label'] ) . '</li>'; }
		if ( ! $row['files'] ) { $body .= '<li>' . esc_html__( 'No attachments included.', 'file-packs-for-forminator' ) . '</li>'; }
		return $this->html( $this->plan['form_title'] . ' — #' . $row['id'], $body . '</ul>' );
	}
	private function index() {
		// translators: %1$d: submission count; %2$d: attachment count.
		$body = '<p>' . esc_html( sprintf( __( '%1$d submissions · %2$d attachments', 'file-packs-for-forminator' ), count( $this->plan['entries'] ), $this->plan['file_count'] ) ) . '</p>' . $this->warnings( $this->plan['warnings'] ) . '<p><a href="register.csv">' . esc_html__( 'Open CSV register', 'file-packs-for-forminator' ) . '</a> · <a href="data.json">' . esc_html__( 'Open JSON data', 'file-packs-for-forminator' ) . '</a></p><ul>';
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
		$stream = @fopen( 'php://temp/maxmemory:1048576', 'w+' );
		if ( ! $stream ) { throw new StorageException( esc_html__( 'Could not create the CSV register.', 'file-packs-for-forminator' ) ); }
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen,WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Private local streams, restrictive permissions and atomic locks require native operations; no FTP or remote filesystem fallback.
		if ( 3 !== @fwrite( $stream, "\xEF\xBB\xBF" ) ) { throw new StorageException( esc_html__( 'Could not create the CSV register.', 'file-packs-for-forminator' ) ); }
		if ( false === @fputcsv( $stream, array_map( array( self::class, 'csv_cell' ), array_merge( array( 'entry_id', 'created_at', 'card', 'attachment_count', 'warnings' ), array_values( $columns ) ) ), ',', '"', '', "\r\n" ) ) { fclose( $stream ); throw new StorageException( esc_html__( 'Could not create the CSV register.', 'file-packs-for-forminator' ) ); } // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close the local CSV stream before propagating a checked write failure.
		foreach ( $this->plan['entries'] as $row ) {
			$values = array_column( $row['fields'], 'value', 'key' );
			$record = array( $row['id'], $row['date'], $row['folder'] . '/request.html', count( $row['files'] ), implode( '; ', array_map( static fn( $w ) => $w['field'] . ': ' . $w['reason'], $row['warnings'] ) ) );
			foreach ( $columns as $key => $label ) { $record[] = $values[$key] ?? ''; }
			if ( false === @fputcsv( $stream, array_map( array( self::class, 'csv_cell' ), $record ), ',', '"', '', "\r\n" ) ) { fclose( $stream ); throw new StorageException( esc_html__( 'Could not create the CSV register.', 'file-packs-for-forminator' ) ); } // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close the local CSV stream before propagating a checked write failure.
		}
		rewind( $stream );
		$csv = stream_get_contents( $stream );
		fclose( $stream ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close a local stream; WP_Filesystem has no streaming equivalent.
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Private local streams, restrictive permissions and atomic locks require native operations; no FTP or remote filesystem fallback.
		if ( false === $csv ) { throw new StorageException( esc_html__( 'Could not create the CSV register.', 'file-packs-for-forminator' ) ); }
		return $csv;
	}
	private static function remove_job( $directory ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Private local streams, restrictive permissions and atomic locks require native operations; no FTP or remote filesystem fallback.
		foreach ( glob( $directory . '/*' ) ?: array() as $file ) { if ( is_file( $file ) || is_link( $file ) ) { @unlink( $file ); } }
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Private local streams, restrictive permissions and atomic locks require native operations; no FTP or remote filesystem fallback.
		@rmdir( $directory );
	}
	public function cleanup() {
		$this->reserve = '';
		if ( $this->archive instanceof \ZipArchive ) {
			$archive = $this->archive; $this->archive = null;
			try { $archive->unchangeAll(); $archive->close(); } catch ( \Throwable $e ) { /* Cleanup must not mask the original failure. */ }
		}
		if ( $this->directory && is_dir( $this->directory ) && ! is_link( $this->directory ) ) { self::remove_job( $this->directory ); }
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Private local streams, restrictive permissions and atomic locks require native operations; no FTP or remote filesystem fallback.
		if ( $this->lock && is_dir( $this->lock ) && ! is_link( $this->lock ) ) { @rmdir( $this->lock ); }
		$this->directory = '';
		$this->lock = '';
	}
	public function __destruct() { $this->cleanup(); }
}
