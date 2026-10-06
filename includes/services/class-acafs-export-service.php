<?php

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

/**
 * Reusable Flamingo message export service.
 *
 * This class deliberately contains no HTTP or admin-screen concerns so it can
 * be consumed by other plugins after ACAFS has loaded.
 */
class ACAFS_Export_Service {

	/**
	 * Count published Flamingo inbound messages matching the supplied filters.
	 *
	 * Dates use the WordPress site's local date semantics because Flamingo stores
	 * the message date in the post_date column.
	 *
	 * @param array $args {
	 *     Optional export arguments.
	 *
	 *     @type string|null $from       Inclusive start date in Y-m-d format.
	 *     @type string|null $to         Inclusive end date in Y-m-d format.
	 *     @type int         $batch_size Number of records processed per batch.
	 * }
	 * @return int|WP_Error Message count, or an error for invalid arguments.
	 */
	public function count( array $args = array() ) {
		global $wpdb;

		$args = $this->normalize_args( $args );
		if ( is_wp_error( $args ) ) {
			return $args;
		}

		$where  = $this->get_where_clause( $args );
		$sql    = "SELECT COUNT(*) FROM {$wpdb->posts} WHERE {$where['sql']}";
		$query  = $wpdb->prepare( $sql, ...$where['params'] );
		$result = $wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( null === $result && ! empty( $wpdb->last_error ) ) {
			return new WP_Error( 'acafs_export_count_failed', $wpdb->last_error );
		}

		return (int) $result;
	}

	/**
	 * Export published Flamingo inbound messages to a JSON file.
	 *
	 * The JSON representation intentionally remains a top-level list containing
	 * raw post rows with the existing meta and channel additions.
	 *
	 * @param string $destination Absolute destination file path.
	 * @param array  $args        Optional filters documented in count().
	 * @return array|WP_Error Export result containing file and count, or an error.
	 */
	public function export_to_file( $destination, array $args = array() ) {
		global $wpdb;

		if ( ! is_string( $destination ) || '' === trim( $destination ) ) {
			return new WP_Error( 'acafs_export_invalid_destination', __( 'A valid export destination is required.', 'ac-advanced-flamingo-settings' ) );
		}

		$args = $this->normalize_args( $args );
		if ( is_wp_error( $args ) ) {
			return $args;
		}

		$total = $this->count( $args );
		if ( is_wp_error( $total ) ) {
			return $total;
		}

		$where    = $this->get_where_clause( $args );
		$last_id  = 0;
		$messages = array();

		do {
			$sql    = "SELECT * FROM {$wpdb->posts} WHERE {$where['sql']} AND ID > %d ORDER BY ID ASC LIMIT %d";
			$params = array_merge( $where['params'], array( $last_id, $args['batch_size'] ) );
			$query  = $wpdb->prepare( $sql, ...$params );
			$rows   = $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

			if ( null === $rows ) {
				return new WP_Error( 'acafs_export_query_failed', $wpdb->last_error );
			}

			foreach ( $rows as $message ) {
				$message_id             = (int) $message['ID'];
				$message['meta']         = get_post_meta( $message_id );
				$message['channel_id']   = 0;
				$message['channel_slug'] = '';
				$message['channel_name'] = '';
				$terms                   = wp_get_post_terms( $message_id, 'flamingo_inbound_channel' );

				if ( ! is_wp_error( $terms ) && ! empty( $terms ) && $terms[0] instanceof WP_Term ) {
					$message['channel_id']   = (int) $terms[0]->term_id;
					$message['channel_slug'] = (string) $terms[0]->slug;
					$message['channel_name'] = (string) $terms[0]->name;
				}

				$messages[] = $message;
				$last_id    = $message_id;
			}
		} while ( count( $rows ) === $args['batch_size'] );

		$json = wp_json_encode( $messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		if ( false === $json ) {
			return new WP_Error( 'acafs_export_json_failed', __( 'Export failed: messages could not be encoded as JSON.', 'ac-advanced-flamingo-settings' ) );
		}

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		WP_Filesystem();
		global $wp_filesystem;

		if ( ! $wp_filesystem || ! $wp_filesystem->put_contents( $destination, $json, FS_CHMOD_FILE ) ) {
			return new WP_Error( 'acafs_export_write_failed', __( 'Export failed: could not write file.', 'ac-advanced-flamingo-settings' ) );
		}

		return array(
			'file'  => $destination,
			'count' => $total,
		);
	}

	/**
	 * Normalize and validate service arguments.
	 *
	 * @param array $args Raw arguments.
	 * @return array|WP_Error
	 */
	private function normalize_args( array $args ) {
		$args = wp_parse_args(
			$args,
			array(
				'from'       => null,
				'to'         => null,
				'batch_size' => 500,
			)
		);

		foreach ( array( 'from', 'to' ) as $key ) {
			if ( '' === $args[ $key ] || null === $args[ $key ] ) {
				$args[ $key ] = null;
				continue;
			}

			if ( ! is_string( $args[ $key ] ) || ! $this->is_valid_date( $args[ $key ] ) ) {
				return new WP_Error(
					'acafs_export_invalid_date',
					sprintf(
						/* translators: %s: Invalid date value. */
						__( 'Invalid export date "%s". Dates must use the Y-m-d format.', 'ac-advanced-flamingo-settings' ),
						is_scalar( $args[ $key ] ) ? (string) $args[ $key ] : ''
					)
				);
			}
		}

		if ( null !== $args['from'] && null !== $args['to'] && $args['from'] > $args['to'] ) {
			return new WP_Error( 'acafs_export_invalid_range', __( 'The export start date must not be later than the end date.', 'ac-advanced-flamingo-settings' ) );
		}

		$args['batch_size'] = (int) $args['batch_size'];
		if ( $args['batch_size'] < 1 ) {
			return new WP_Error( 'acafs_export_invalid_batch_size', __( 'The export batch size must be greater than zero.', 'ac-advanced-flamingo-settings' ) );
		}

		return $args;
	}

	/**
	 * Validate a real calendar date in strict Y-m-d format.
	 *
	 * @param string $date Date value.
	 * @return bool
	 */
	private function is_valid_date( $date ) {
		$parsed = DateTime::createFromFormat( '!Y-m-d', $date );

		return false !== $parsed && $parsed->format( 'Y-m-d' ) === $date;
	}

	/**
	 * Build the prepared-query components shared by count and export.
	 *
	 * @param array $args Normalized arguments.
	 * @return array
	 */
	private function get_where_clause( array $args ) {
		$clauses = array(
			'post_type = %s',
			'post_status = %s',
		);
		$params   = array( 'flamingo_inbound', 'publish' );

		if ( null !== $args['from'] ) {
			$clauses[] = 'post_date >= %s';
			$params[]  = $args['from'] . ' 00:00:00';
		}

		if ( null !== $args['to'] ) {
			$clauses[] = 'post_date <= %s';
			$params[]  = $args['to'] . ' 23:59:59';
		}

		return array(
			'sql'    => implode( ' AND ', $clauses ),
			'params' => $params,
		);
	}
}
