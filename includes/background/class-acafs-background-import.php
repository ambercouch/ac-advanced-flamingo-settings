<?php

namespace background;

use WP_Background_Process;

if ( ! class_exists( 'WP_Background_Process' ) ) {
	require_once plugin_dir_path( __FILE__ ) . 'lib/wp-background-processing.php';
}

/**
 * Background process for importing Flamingo messages.
 */
class ACAFS_Background_Import extends WP_Background_Process {


	protected $action = 'acafs_import_flamingo';

	public function __construct() {
		parent::__construct(); // Call WP_Background_Process constructor
	}

	/**
	 * Process a single message import.
	 */
	protected function task( $messages_batch ) {
		global $wpdb;

		if ( ! is_array( $messages_batch ) || empty( $messages_batch ) ) {
			return false;
		}

		// Build hashes
		$message_hashes = array();
		foreach ( $messages_batch as $msg ) {
			$hash                    = md5( sanitize_text_field( $msg['post_title'] ) . wp_kses_post( $msg['post_content'] ) );
			$message_hashes[ $hash ] = $msg;
		}

		// Query for existing posts by title/content (bulk match).
		$titles = array_column( $messages_batch, 'post_title' );

		if ( empty( $titles ) ) {
			$existing = array();
		} else {
			$titles = array_column( $messages_batch, 'post_title' );

			if ( empty( $titles ) ) {
				$existing = array();
			} else {
				$placeholders = implode( ', ', array_fill( 0, count( $titles ), '%s' ) );

				$sql = "
		         SELECT ID, post_title, post_content
		         FROM {$wpdb->posts}
		         WHERE post_type = %s
		          AND post_status = %s
		          AND post_title IN ($placeholders)
	           ";

				$args = array_merge(
					array( 'flamingo_inbound', 'publish' ),
					$titles
				);

				$existing = $wpdb->get_results(
					$wpdb->prepare( $sql, ...$args ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				);
			}
		}

		$existing_hashes = array();
		foreach ( $existing as $post ) {
			$existing_hash     = md5( sanitize_text_field( $post->post_title ) . wp_kses_post( $post->post_content ) );
			$existing_hashes[] = $existing_hash;
		}

		// Now import only non-duplicates
		foreach ( $message_hashes as $hash => $message ) {
			if ( in_array( $hash, $existing_hashes, true ) ) {
				continue; // Skip duplicate
			}

			$post_id = wp_insert_post(
				array(
					'post_title'   => sanitize_text_field( $message['post_title'] ),
					'post_content' => wp_kses_post( $message['post_content'] ),
					'post_status'  => 'publish',
					'post_type'    => 'flamingo_inbound',
					'post_date'    => $message['post_date'],
					'post_author'  => isset( $message['post_author'] ) ? intval( $message['post_author'] ) : 0,
				)
			);

			if ( ! $post_id ) {
				continue;
			}

			if ( ! empty( $message['meta'] ) ) {
				foreach ( $message['meta'] as $key => $values ) {
					foreach ( $values as $value ) {
						update_post_meta( $post_id, sanitize_key( $key ), maybe_unserialize( $value ) );
					}
				}
			}

			if ( ! empty( $message['channel_id'] ) && is_numeric( $message['channel_id'] ) ) {
				wp_set_object_terms( $post_id, (int) $message['channel_id'], 'flamingo_inbound_channel', false );
			}
		}

		return false;
	}


	protected function complete() {
		delete_transient( 'acafs_import_started' ); // Clear in-progress marker
		set_transient( 'acafs_import_success', 'completed' );
		parent::complete();
	}
}
