<?php
defined( 'ABSPATH' ) || exit;

/**
 * Reusable service for exporting Flamingo inbound messages.
 */
class ACAFS_Export_Service {

	/**
	 * Count messages matching the supplied filters.
	 *
	 * @param array $args Optional export arguments. Supports `from` and `to` dates.
	 * @return int|WP_Error
	 */
	public function count_messages( array $args = array() ) {
		global $wpdb;

		$query = $this->build_query( $args, true );

		if ( is_wp_error( $query ) ) {
			return $query;
		}

		$prepared = $wpdb->prepare( $query['sql'], $query['params'] );

		return (int) $wpdb->get_var( $prepared ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Export messages matching the supplied filters.
	 *
	 * The returned message representation intentionally matches the existing
	 * ACAFS JSON export schema.
	 *
	 * @param array $args Optional export arguments. Supports `from` and `to` dates.
	 * @return array|WP_Error
	 */
	public function export_messages( array $args = array() ) {
		global $wpdb;

		$query = $this->build_query( $args, false );

		if ( is_wp_error( $query ) ) {
			return $query;
		}

		$total = $this->count_messages( $args );

		if ( is_wp_error( $total ) ) {
			return $total;
		}

		$batch    = 500;
		$offset   = 0;
		$messages = array();

		while ( $offset < $total ) {
			$sql    = $query['sql'] . ' LIMIT %d OFFSET %d';
			$params = array_merge( $query['params'], array( $batch, $offset ) );

			$prepared = $wpdb->prepare( $sql, $params );
			$results  = $wpdb->get_results( $prepared, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

			foreach ( $results as &$message ) {
				$message['meta'] = get_post_meta( (int) $message['ID'] );

				$terms                 = wp_get_post_terms( (int) $message['ID'], 'flamingo_inbound_channel', array( 'fields' => 'ids' ) );
				$message['channel_id'] = ! is_wp_error( $terms ) && ! empty( $terms ) ? (int) $terms[0] : 0;
			}
			unset( $message );

			$messages = array_merge( $messages, $results );
			$offset  += $batch;
		}

		return $messages;
	}

	/**
	 * Export matching messages as JSON to a supplied file path.
	 *
	 * @param string $file Destination file path.
	 * @param array  $args Optional export arguments. Supports `from` and `to` dates.
	 * @return array|WP_Error Export result containing file, count, and JSON.
	 */
	public function export_to_file( $file, array $args = array() ) {
		if ( ! is_string( $file ) || '' === $file ) {
			return new WP_Error( 'acafs_invalid_export_file', __( 'A valid export file path is required.', 'ac-advanced-flamingo-settings' ) );
		}

		$messages = $this->export_messages( $args );

		if ( is_wp_error( $messages ) ) {
			return $messages;
		}

		$json = wp_json_encode( $messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );

		if ( false === $json ) {
			return new WP_Error( 'acafs_export_json_error', __( 'Export failed: messages could not be encoded as JSON.', 'ac-advanced-flamingo-settings' ) );
		}

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		WP_Filesystem();

		global $wp_filesystem;

		if ( ! $wp_filesystem || ! $wp_filesystem->put_contents( $file, $json, FS_CHMOD_FILE ) ) {
			return new WP_Error( 'acafs_export_write_error', __( 'Export failed: could not write file.', 'ac-advanced-flamingo-settings' ) );
		}

		return array(
			'file'  => $file,
			'count' => count( $messages ),
			'json'  => $json,
		);
	}

	/**
	 * Build a prepared-query template for the supplied filters.
	 *
	 * @param array $args       Export arguments.
	 * @param bool  $count_only Whether to build a count query.
	 * @return array|WP_Error
	 */
	private function build_query( array $args, $count_only ) {
		global $wpdb;

		$filters = $this->validate_filters( $args );

		if ( is_wp_error( $filters ) ) {
			return $filters;
		}

		$select = $count_only ? 'COUNT(*)' : '*';
		$sql    = "SELECT {$select} FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s";
		$params = array( 'flamingo_inbound', 'publish' );

		if ( '' !== $filters['from'] ) {
			$sql     .= ' AND post_date >= %s';
			$params[] = $filters['from'] . ' 00:00:00';
		}

		if ( '' !== $filters['to'] ) {
			$sql     .= ' AND post_date <= %s';
			$params[] = $filters['to'] . ' 23:59:59';
		}

		return array(
			'sql'    => $sql,
			'params' => $params,
		);
	}

	/**
	 * Validate and normalize export filters.
	 *
	 * @param array $args Export arguments.
	 * @return array|WP_Error
	 */
	private function validate_filters( array $args ) {
		$from = isset( $args['from'] ) ? $args['from'] : '';
		$to   = isset( $args['to'] ) ? $args['to'] : '';

		if ( ! is_string( $from ) || ! is_string( $to ) ) {
			return new WP_Error( 'acafs_invalid_export_date', __( 'Export dates must use the Y-m-d format.', 'ac-advanced-flamingo-settings' ) );
		}

		$from = trim( $from );
		$to   = trim( $to );

		if ( '' !== $from && ! $this->is_valid_date( $from ) ) {
			return new WP_Error( 'acafs_invalid_export_from_date', __( 'The export from date must be a valid Y-m-d date.', 'ac-advanced-flamingo-settings' ) );
		}

		if ( '' !== $to && ! $this->is_valid_date( $to ) ) {
			return new WP_Error( 'acafs_invalid_export_to_date', __( 'The export to date must be a valid Y-m-d date.', 'ac-advanced-flamingo-settings' ) );
		}

		if ( '' !== $from && '' !== $to && $from > $to ) {
			return new WP_Error( 'acafs_invalid_export_date_range', __( 'The export from date cannot be later than the to date.', 'ac-advanced-flamingo-settings' ) );
		}

		return array(
			'from' => $from,
			'to'   => $to,
		);
	}

	/**
	 * Determine whether a value is an exact Y-m-d calendar date.
	 *
	 * @param string $date Date value.
	 * @return bool
	 */
	private function is_valid_date( $date ) {
		$parsed = DateTime::createFromFormat( '!Y-m-d', $date );

		return false !== $parsed && $parsed->format( 'Y-m-d' ) === $date;
	}
}
