<?php
defined( 'ABSPATH' ) || exit;

/**
 * Reusable synchronous service for importing Flamingo inbound messages.
 */
class ACAFS_Import_Service {

	/**
	 * Read and validate an ACAFS JSON export file.
	 *
	 * @param string $file Import file path.
	 * @return array|WP_Error
	 */
	public function parse_file( $file ) {
		if ( ! is_string( $file ) || '' === $file || ! is_readable( $file ) ) {
			return new WP_Error( 'acafs_import_file_unreadable', __( 'The import file could not be read.', 'ac-advanced-flamingo-settings' ) );
		}

		$file_content = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		if ( false === $file_content ) {
			return new WP_Error( 'acafs_import_file_unreadable', __( 'The import file could not be read.', 'ac-advanced-flamingo-settings' ) );
		}

		$messages = json_decode( $file_content, true );

		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return new WP_Error( 'acafs_import_invalid_json', __( 'Invalid JSON file. Please check the format and try again.', 'ac-advanced-flamingo-settings' ) );
		}

		$valid = $this->validate_messages( $messages );

		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		return $messages;
	}

	/**
	 * Import all messages in a JSON export file synchronously.
	 *
	 * @param string $file Import file path.
	 * @param array  $args Optional import arguments reserved for future use.
	 * @return array|WP_Error
	 */
	public function import_file( $file, array $args = array() ) {
		$messages = $this->parse_file( $file );

		if ( is_wp_error( $messages ) ) {
			return $messages;
		}

		return $this->import_messages( $messages, $args );
	}

	/**
	 * Import validated Flamingo messages synchronously.
	 *
	 * @param array $messages Messages using the ACAFS export schema.
	 * @param array $args     Optional import arguments reserved for future use.
	 * @return array|WP_Error
	 */
	public function import_messages( array $messages, array $args = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		global $wpdb;

		$valid = $this->validate_messages( $messages );

		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$stats = array(
			'received' => count( $messages ),
			'imported' => 0,
			'skipped'  => 0,
			'failed'   => 0,
			'errors'   => array(),
		);

		$message_hashes = array();

		foreach ( $messages as $message ) {
			$hash = $this->message_hash( $message['post_title'], $message['post_content'] );

			if ( isset( $message_hashes[ $hash ] ) ) {
				++$stats['skipped'];
			}

			$message_hashes[ $hash ] = $message;
		}

		$titles = array_column( $messages, 'post_title' );

		if ( empty( $titles ) ) {
			$existing = array();
		} else {
			$placeholders = implode( ', ', array_fill( 0, count( $titles ), '%s' ) );
			$sql          = "SELECT ID, post_title, post_content FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s AND post_title IN ($placeholders)";
			$query_args   = array_merge( array( 'flamingo_inbound', 'publish' ), $titles );
			$existing     = $wpdb->get_results( $wpdb->prepare( $sql, $query_args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
		}

		$existing_hashes = array();

		foreach ( $existing as $post ) {
			$existing_hashes[] = $this->message_hash( $post->post_title, $post->post_content );
		}

		foreach ( $message_hashes as $hash => $message ) {
			if ( in_array( $hash, $existing_hashes, true ) ) {
				++$stats['skipped'];
				continue;
			}

			$post_id = wp_insert_post(
				array(
					'post_title'   => sanitize_text_field( $message['post_title'] ),
					'post_content' => wp_kses_post( $message['post_content'] ),
					'post_status'  => 'publish',
					'post_type'    => 'flamingo_inbound',
					'post_date'    => $message['post_date'],
					'post_author'  => isset( $message['post_author'] ) ? (int) $message['post_author'] : 0,
				),
				true
			);

			if ( is_wp_error( $post_id ) ) {
				++$stats['failed'];
				$stats['errors'][] = array(
					'code'    => $post_id->get_error_code(),
					'message' => $post_id->get_error_message(),
				);
				continue;
			}

			if ( ! $post_id ) {
				++$stats['failed'];
				$stats['errors'][] = array(
					'code'    => 'acafs_import_insert_failed',
					'message' => __( 'A Flamingo message could not be inserted.', 'ac-advanced-flamingo-settings' ),
				);
				continue;
			}

			if ( ! empty( $message['meta'] ) ) {
				foreach ( $message['meta'] as $key => $values ) {
					foreach ( $values as $value ) {
						update_post_meta( $post_id, sanitize_key( $key ), maybe_unserialize( $value ) );
					}
				}
			}

			if ( ! empty( $message['channel_id'] ) ) {
				$term_result = wp_set_object_terms( $post_id, (int) $message['channel_id'], 'flamingo_inbound_channel', false );

				if ( is_wp_error( $term_result ) ) {
					$stats['errors'][] = array(
						'code'    => $term_result->get_error_code(),
						'message' => $term_result->get_error_message(),
					);
				}
			}

			++$stats['imported'];
		}

		return $stats;
	}

	/**
	 * Validate the complete import document before processing any records.
	 *
	 * @param mixed $messages Decoded JSON data.
	 * @return true|WP_Error
	 */
	private function validate_messages( $messages ) {
		if ( ! is_array( $messages ) || ! $this->is_list( $messages ) ) {
			return new WP_Error( 'acafs_import_invalid_data', __( 'The import file must contain an array of message records.', 'ac-advanced-flamingo-settings' ) );
		}

		foreach ( $messages as $index => $message ) {
			if ( ! is_array( $message ) ) {
				return new WP_Error( 'acafs_import_invalid_message', sprintf( /* translators: %d: Message record number. */ __( 'Import message %d is not a valid record.', 'ac-advanced-flamingo-settings' ), $index + 1 ) );
			}

			foreach ( array( 'post_title', 'post_content', 'post_date' ) as $required_key ) {
				if ( ! array_key_exists( $required_key, $message ) || ! is_string( $message[ $required_key ] ) ) {
					return new WP_Error( 'acafs_import_invalid_message', sprintf( /* translators: 1: Message record number, 2: Required field name. */ __( 'Import message %1$d has an invalid or missing %2$s field.', 'ac-advanced-flamingo-settings' ), $index + 1, $required_key ) );
				}
			}

			if ( isset( $message['post_author'] ) && ! is_numeric( $message['post_author'] ) ) {
				return new WP_Error( 'acafs_import_invalid_message', sprintf( /* translators: %d: Message record number. */ __( 'Import message %d has an invalid post_author field.', 'ac-advanced-flamingo-settings' ), $index + 1 ) );
			}

			if ( isset( $message['channel_id'] ) && ! is_numeric( $message['channel_id'] ) ) {
				return new WP_Error( 'acafs_import_invalid_message', sprintf( /* translators: %d: Message record number. */ __( 'Import message %d has an invalid channel_id field.', 'ac-advanced-flamingo-settings' ), $index + 1 ) );
			}

			if ( isset( $message['meta'] ) ) {
				if ( ! is_array( $message['meta'] ) ) {
					return new WP_Error( 'acafs_import_invalid_message', sprintf( /* translators: %d: Message record number. */ __( 'Import message %d has an invalid meta field.', 'ac-advanced-flamingo-settings' ), $index + 1 ) );
				}

				foreach ( $message['meta'] as $values ) {
					if ( ! is_array( $values ) ) {
						return new WP_Error( 'acafs_import_invalid_message', sprintf( /* translators: %d: Message record number. */ __( 'Import message %d has invalid metadata values.', 'ac-advanced-flamingo-settings' ), $index + 1 ) );
					}
				}
			}
		}

		return true;
	}

	/**
	 * Determine whether an array has sequential integer keys.
	 *
	 * @param array $value Array to inspect.
	 * @return bool
	 */
	private function is_list( array $value ) {
		return array() === $value || array_keys( $value ) === range( 0, count( $value ) - 1 );
	}

	/**
	 * Generate the legacy duplicate-detection hash for a message.
	 *
	 * @param string $title   Message title.
	 * @param string $content Message content.
	 * @return string
	 */
	private function message_hash( $title, $content ) {
		return md5( sanitize_text_field( $title ) . wp_kses_post( $content ) );
	}
}
