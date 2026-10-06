<?php

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

/**
 * Reusable Flamingo message import service.
 *
 * The service accepts explicit files and message batches and has no dependency
 * on an HTTP request, admin screen, or background-processing transport.
 */
class ACAFS_Import_Service {

	/**
	 * Read and validate a JSON export file.
	 *
	 * @param string $file JSON file path.
	 * @return array|WP_Error Validated messages, or an error.
	 */
	public function read_file( $file ) {
		if ( ! is_string( $file ) || '' === trim( $file ) || ! is_readable( $file ) || ! is_file( $file ) ) {
			return new WP_Error( 'acafs_import_unreadable_file', __( 'The import file could not be read.', 'ac-advanced-flamingo-settings' ) );
		}

		$contents = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $contents ) {
			return new WP_Error( 'acafs_import_unreadable_file', __( 'The import file could not be read.', 'ac-advanced-flamingo-settings' ) );
		}

		$messages = json_decode( $contents, true );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return new WP_Error( 'acafs_import_invalid_json', __( 'Invalid JSON file. Please check the format and try again.', 'ac-advanced-flamingo-settings' ) );
		}

		if ( ! is_array( $messages ) || ! $this->is_list( $messages ) ) {
			return new WP_Error( 'acafs_import_invalid_structure', __( 'The import file must contain a list of message records.', 'ac-advanced-flamingo-settings' ) );
		}

		$normalized = array();
		foreach ( $messages as $index => $message ) {
			$message = $this->normalize_message( $message, $index );
			if ( is_wp_error( $message ) ) {
				return $message;
			}
			$normalized[] = $message;
		}

		return $normalized;
	}

	/**
	 * Import one batch of validated Flamingo message records.
	 *
	 * Duplicate detection intentionally retains the existing title-plus-content
	 * hash behavior for compatibility.
	 *
	 * @param array $messages Message records.
	 * @param array $args     Reserved import arguments for future compatibility.
	 * @return array Structured processing statistics.
	 */
	public function import_batch( array $messages, array $args = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		global $wpdb;

		$result = array(
			'processed' => count( $messages ),
			'inserted'  => 0,
			'skipped'   => 0,
			'failed'    => 0,
			'errors'    => array(),
		);

		if ( empty( $messages ) ) {
			return $result;
		}

		$message_hashes = array();
		foreach ( $messages as $index => $message ) {
			$message = $this->normalize_message( $message, $index );
			if ( is_wp_error( $message ) ) {
				++$result['failed'];
				$result['errors'][] = $message;
				continue;
			}

			$hash = $this->get_message_hash( $message['post_title'], $message['post_content'] );
			if ( isset( $message_hashes[ $hash ] ) ) {
				++$result['skipped'];
			}
			$message_hashes[ $hash ] = $message;
		}

		if ( empty( $message_hashes ) ) {
			return $result;
		}

		$titles       = array_column( $message_hashes, 'post_title' );
		$placeholders = implode( ', ', array_fill( 0, count( $titles ), '%s' ) );
		$sql          = "SELECT ID, post_title, post_content FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s AND post_title IN ($placeholders)";
		$query_args   = array_merge( array( 'flamingo_inbound', 'publish' ), $titles );
		$query        = $wpdb->prepare( $sql, ...$query_args );
		$existing     = $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( null === $existing ) {
			$error = new WP_Error( 'acafs_import_duplicate_query_failed', $wpdb->last_error );
			$result['failed'] += count( $message_hashes );
			$result['errors'][] = $error;
			return $result;
		}

		$existing_hashes = array();
		foreach ( $existing as $post ) {
			$existing_hashes[ $this->get_message_hash( $post->post_title, $post->post_content ) ] = true;
		}

		foreach ( $message_hashes as $hash => $message ) {
			if ( isset( $existing_hashes[ $hash ] ) ) {
				++$result['skipped'];
				continue;
			}

			$post_id = wp_insert_post(
				array(
					'post_title'   => sanitize_text_field( $message['post_title'] ),
					'post_content' => wp_kses_post( $message['post_content'] ),
					'post_status'  => 'publish',
					'post_type'    => 'flamingo_inbound',
					'post_date'    => $message['post_date'],
					'post_author'  => $message['post_author'],
				),
				true
			);

			if ( is_wp_error( $post_id ) ) {
				++$result['failed'];
				$result['errors'][] = $post_id;
				continue;
			}

			if ( ! $post_id ) {
				++$result['failed'];
				$result['errors'][] = new WP_Error( 'acafs_import_insert_failed', __( 'A Flamingo message could not be inserted.', 'ac-advanced-flamingo-settings' ) );
				continue;
			}

			foreach ( $message['meta'] as $key => $values ) {
				foreach ( $values as $value ) {
					update_post_meta( $post_id, sanitize_key( $key ), maybe_unserialize( $value ) );
				}
			}

			$channel_term_id = $this->resolve_channel_term_id( $message );
			if ( is_wp_error( $channel_term_id ) ) {
				$result['errors'][] = $channel_term_id;
			} elseif ( $channel_term_id > 0 ) {
				$term_result = wp_set_object_terms( $post_id, $channel_term_id, 'flamingo_inbound_channel', false );
				if ( is_wp_error( $term_result ) ) {
					$result['errors'][] = $term_result;
				}
			}

			++$result['inserted'];
		}

		return $result;
	}

	/**
	 * Validate and normalize one exported message record.
	 *
	 * @param mixed $message Message value.
	 * @param int   $index   Message index for useful error context.
	 * @return array|WP_Error
	 */
	private function normalize_message( $message, $index ) {
		if ( ! is_array( $message ) ) {
			return $this->invalid_message_error( $index, __( 'the record is not an object', 'ac-advanced-flamingo-settings' ) );
		}

		foreach ( array( 'post_title', 'post_content', 'post_date' ) as $required_key ) {
			if ( ! array_key_exists( $required_key, $message ) || ! is_string( $message[ $required_key ] ) ) {
				return $this->invalid_message_error(
					$index,
					sprintf(
						/* translators: %s: Required message field name. */
						__( 'the %s field is missing or invalid', 'ac-advanced-flamingo-settings' ),
						$required_key
					)
				);
			}
		}

		if ( isset( $message['post_author'] ) && ! is_numeric( $message['post_author'] ) ) {
			return $this->invalid_message_error( $index, __( 'the post_author field is invalid', 'ac-advanced-flamingo-settings' ) );
		}

		if ( isset( $message['channel_id'] ) && ! is_numeric( $message['channel_id'] ) ) {
			return $this->invalid_message_error( $index, __( 'the channel_id field is invalid', 'ac-advanced-flamingo-settings' ) );
		}

		if ( isset( $message['meta'] ) && ! is_array( $message['meta'] ) ) {
			return $this->invalid_message_error( $index, __( 'the meta field is invalid', 'ac-advanced-flamingo-settings' ) );
		}

		$message['post_author']     = isset( $message['post_author'] ) ? (int) $message['post_author'] : 0;
		$message['channel_id']      = isset( $message['channel_id'] ) ? (int) $message['channel_id'] : 0;
		$message['channel_slug']    = isset( $message['channel_slug'] ) && is_string( $message['channel_slug'] )
			? sanitize_title( $message['channel_slug'] )
			: '';
		$message['channel_name']    = isset( $message['channel_name'] ) && is_string( $message['channel_name'] )
			? sanitize_text_field( $message['channel_name'] )
			: '';
		$message['meta']            = isset( $message['meta'] ) ? $message['meta'] : array();

		foreach ( $message['meta'] as $key => $values ) {
			if ( ! is_string( $key ) || ! is_array( $values ) ) {
				return $this->invalid_message_error( $index, __( 'the meta field contains an invalid entry', 'ac-advanced-flamingo-settings' ) );
			}

			foreach ( $values as $value ) {
				if ( ! is_scalar( $value ) && null !== $value ) {
					return $this->invalid_message_error( $index, __( 'the meta field contains an invalid value', 'ac-advanced-flamingo-settings' ) );
				}
			}
		}

		return $message;
	}

	/**
	 * Resolve an exported channel against this site's taxonomy.
	 *
	 * Numeric term IDs in legacy exports are deliberately not used: term IDs are
	 * site-specific and cannot safely identify the source channel. Such messages
	 * remain unassigned. New exports use the stable slug, reusing a destination
	 * term when possible and creating it only when it is missing.
	 *
	 * @param array $message Normalized exported message.
	 * @return int|WP_Error Destination term ID, zero when no safe assignment exists,
	 *                      or an error when term creation fails.
	 */
	private function resolve_channel_term_id( array $message ) {
		$taxonomy = 'flamingo_inbound_channel';
		$slug     = $message['channel_slug'];

		if ( '' === $slug || ! taxonomy_exists( $taxonomy ) ) {
			return 0;
		}

		$term = get_term_by( 'slug', $slug, $taxonomy );
		if ( $term instanceof WP_Term ) {
			return (int) $term->term_id;
		}

		$name     = '' !== $message['channel_name'] ? $message['channel_name'] : $slug;
		$inserted = wp_insert_term(
			$name,
			$taxonomy,
			array( 'slug' => $slug )
		);

		if ( is_wp_error( $inserted ) ) {
			// Another worker may have created the same term between lookup and insert.
			if ( 'term_exists' === $inserted->get_error_code() ) {
				$term = get_term_by( 'slug', $slug, $taxonomy );
				if ( $term instanceof WP_Term ) {
					return (int) $term->term_id;
				}
			}

			return $inserted;
		}

		return isset( $inserted['term_id'] ) ? (int) $inserted['term_id'] : 0;
	}

	/**
	 * Determine whether an array has sequential integer keys.
	 *
	 * Compatible with the plugin's PHP 7.4 minimum.
	 *
	 * @param array $value Array to inspect.
	 * @return bool
	 */
	private function is_list( array $value ) {
		return array_keys( $value ) === range( 0, count( $value ) - 1 ) || array() === $value;
	}

	/**
	 * Build the compatibility duplicate hash.
	 *
	 * @param string $title   Post title.
	 * @param string $content Post content.
	 * @return string
	 */
	private function get_message_hash( $title, $content ) {
		return md5( sanitize_text_field( $title ) . wp_kses_post( $content ) );
	}

	/**
	 * Create an indexed validation error.
	 *
	 * @param int    $index  Zero-based record index.
	 * @param string $reason Validation failure.
	 * @return WP_Error
	 */
	private function invalid_message_error( $index, $reason ) {
		return new WP_Error(
			'acafs_import_invalid_message',
			sprintf(
				/* translators: 1: One-based message number. 2: Validation failure. */
				__( 'Import message %1$d is invalid: %2$s.', 'ac-advanced-flamingo-settings' ),
				$index + 1,
				$reason
			)
		);
	}
}
