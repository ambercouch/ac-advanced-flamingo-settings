<?php
/**
 * AC Advanced Flamingo Settings – Single Message helpers
 *
 * - Persist CF7 uploads to a permanent location and store URLs in Flamingo.
 * - Enhance Flamingo single message meta table so upload URLs are clickable.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Persist CF7 uploads and ensure Flamingo stores URLs instead of hashes.
 */
class ACAFS_CF7_Persist_Uploads {

	/**
	 * Store URLs we persisted this request.
	 *
	 * Format: [ 'your-file' => [ 'https://…/file1.ext', 'https://…/file2.ext' ] ].
	 *
	 * @var array
	 */
	private $latest_urls = array();

	/**
	 * Hook into CF7 + Flamingo.
	 */
	public function __construct() {
		// Copy CF7 temp uploads to a permanent folder and replace posted data before mail is sent.
		add_action( 'wpcf7_before_send_mail', array( $this, 'persist_and_replace' ), 1, 1 );

		// Safety net: adjust Flamingo meta right before the message is written.
		add_filter( 'flamingo_add_inbound', array( $this, 'replace_hash_with_url_for_flamingo' ) );

		// Final guard: force our URLs into Flamingo at a very late priority.
		add_filter( 'flamingo_add_inbound', array( $this, 'force_file_urls_into_flamingo' ), 9999 );
	}

	/**
	 * Copy uploaded files to /uploads/acafs-cf7/Y/m/ and replace posted data with URLs.
	 *
	 * @param WPCF7_ContactForm $contact_form Contact form instance.
	 * @return void
	 */
	public function persist_and_replace( $contact_form ) {

		if ( ! class_exists( 'WPCF7_Submission' ) ) {
			return;
		}

		$submission = WPCF7_Submission::get_instance();
		if ( ! $submission ) {
			return;
		}

		// ['field-name' => '/tmp/path' OR ['path1','path2']].
		$uploaded = $submission->uploaded_files();
		if ( ! is_array( $uploaded ) || empty( $uploaded ) ) {
			return;
		}

		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			// Uploads not available.
			return;
		}

		$subdir     = 'acafs-cf7/' . gmdate( 'Y/m' ) . '/';
		$target_dir = trailingslashit( $uploads['basedir'] ) . $subdir;
		$base_url   = trailingslashit( $uploads['baseurl'] ) . $subdir;

		wp_mkdir_p( $target_dir );

		foreach ( $uploaded as $field => $paths ) {

			// Normalize to array of strings.
			$paths = is_array( $paths ) ? array_filter( $paths ) : ( $paths ? array( $paths ) : array() );
			if ( empty( $paths ) ) {
				continue;
			}

			$urls_for_field = array();

			foreach ( $paths as $path ) {
				if ( ! is_string( $path ) || '' === $path ) {
					continue;
				}
				if ( ! file_exists( $path ) ) {
					continue;
				}

				$unique = wp_unique_filename( $target_dir, basename( $path ) );
				$dest   = $target_dir . $unique;

				// Copy (not move) so CF7 can clean up its temp file as usual.
				if ( copy( $path, $dest ) ) {
					$urls_for_field[] = esc_url_raw( $base_url . $unique );
				}
			}

			if ( ! empty( $urls_for_field ) ) {
				$this->latest_urls[ $field ] = $urls_for_field;
			}
		}

		// Replace posted data values so Flamingo stores URLs, not hashes.
		if ( ! empty( $this->latest_urls ) ) {
			$posted_data = $submission->get_posted_data();

			foreach ( $this->latest_urls as $field => $urls ) {
				if ( isset( $posted_data[ $field ] ) ) {
					// Preserve structure: if CF7 gave an array, keep array; else use newline-joined string.
					if ( is_array( $posted_data[ $field ] ) ) {
						$posted_data[ $field ] = $urls;
					} else {
						$posted_data[ $field ] = implode( "\n", $urls );
					}
				}
			}

			// CF7 5.2+ supports this setter.
			if ( method_exists( $submission, 'set_posted_data' ) ) {
				$submission->set_posted_data( $posted_data );
			}
		}
	}

	/**
	 * As a safety net, swap any remaining hashes with URLs we just saved before Flamingo writes the message.
	 *
	 * @param array $args Flamingo inbound args (includes 'meta').
	 * @return array
	 */
	public function replace_hash_with_url_for_flamingo( $args ) {
		if ( empty( $args['meta'] ) || ! is_array( $args['meta'] ) ) {
			return $args;
		}

		if ( empty( $this->latest_urls ) || ! is_array( $this->latest_urls ) ) {
			return $args;
		}

		foreach ( $this->latest_urls as $field => $urls ) {
			if ( empty( $urls ) || ! is_array( $urls ) ) {
				continue;
			}

			// Normalize to newline-joined string for scalar fields.
			$joined = implode( "\n", array_map( 'esc_url_raw', $urls ) );

			if ( array_key_exists( $field, $args['meta'] ) ) {
				// Preserve structure: if Flamingo meta is array -> array of URLs; else -> newline string.
				if ( is_array( $args['meta'][ $field ] ) ) {
					$args['meta'][ $field ] = $urls;
				} else {
					$args['meta'][ $field ] = $joined;
				}
			} else {
				// If Flamingo didn’t include the key (edge case), add it as string to ensure visibility.
				$args['meta'][ $field ] = $joined;
			}
		}

		return $args;
	}

	/**
	 * Final guard that runs very late to force our URLs into Flamingo meta.
	 *
	 * @param array $args Flamingo inbound args.
	 * @return array
	 */
	public function force_file_urls_into_flamingo( $args ) {
		// Right now this simply delegates to the same logic as above.
		// Keeping this as a separate method means we can extend it later if needed.
		return $this->replace_hash_with_url_for_flamingo( $args );
	}
}

// Boot persistence.
new ACAFS_CF7_Persist_Uploads();
