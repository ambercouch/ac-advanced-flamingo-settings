<?php
defined( 'ABSPATH' ) || exit;

class ACAFS_Columns {

	public function __construct() {
		// Inbound Messages – custom columns
		add_action( 'admin_init', array( $this, 'acafs_setup_inbound_columns' ) );

		// Address Book – last message column
		add_filter( 'manage_flamingo_contact_posts_columns', array( $this, 'acafs_address_book_columns' ) );
		add_action( 'manage_flamingo_contact_posts_custom_column', array( $this, 'acafs_render_address_book_column' ), 10, 2 );
	}

	/**
	 * Set up custom columns for Flamingo Inbound Messages
	 */
	public function acafs_setup_inbound_columns() {
		add_filter( 'manage_flamingo_inbound_posts_columns', array( $this, 'acafs_add_submission_column' ) );
		add_action( 'manage_flamingo_inbound_posts_custom_column', array( $this, 'acafs_render_submission_column' ), 10, 2 );
	}

	/**
	 * Add “Submission Details” column to Inbound Messages
	 */
	public function acafs_add_submission_column( $columns ) {
		$columns['submission_details'] = __( 'Submission Details', 'ac-advanced-flamingo-settings' );
		return $columns;
	}

	/**
	 * Render “Submission Details” column content
	 */
	public function acafs_render_submission_column( $column, $post_id ) {
		if ( $column !== 'submission_details' ) {
			return;
		}

		$selected_fields = get_option( 'acafs_display_fields', array( 'your-message' ) );
		if ( ! is_array( $selected_fields ) ) {
			$selected_fields = array( 'your-message' );
		}

		$meta            = get_post_meta( $post_id );
		$submission_data = array();

		foreach ( $meta as $key => $value ) {
			if ( strpos( $key, '_field_' ) !== 0 || empty( $value[0] ) ) {
				continue;
			}

			$field = str_replace( '_field_', '', $key );

			if ( in_array( $field, $selected_fields, true ) ) {
				$output = maybe_unserialize( $value[0] );

				if ( is_array( $output ) ) {
					$output = array_map( 'sanitize_text_field', $output );
					$output = implode( ', ', $output );
				} else {
					$output = sanitize_text_field( $output );
				}

				$submission_data[ $field ] = $output;
			}
		}

		if ( empty( $submission_data ) ) {
			echo '<em>' . esc_html__( 'No selected submission details available.', 'ac-advanced-flamingo-settings' ) . '</em>';
			return;
		}

		echo '<ul style="margin:0; padding:0; list-style:none;">';
		foreach ( $submission_data as $field => $value ) {
			echo '<li><strong>' . esc_html( ucwords( str_replace( '_', ' ', $field ) ) ) . ':</strong> ' . esc_html( $value ) . '</li>';
		}
		echo '</ul>';
	}

	/**
	 * Add "Last Message" column to the address book
	 */
	public function acafs_address_book_columns( $columns ) {
		$columns['last_message'] = __( 'Last Message', 'ac-advanced-flamingo-settings' );
		return $columns;
	}

	/**
	 * Render "Last Message" column content
	 */
	public function acafs_render_address_book_column( $column, $post_id ) {
		if ( $column !== 'last_message' ) {
			return;
		}

		$email = sanitize_email( get_post_meta( $post_id, '_email', true ) );
		if ( empty( $email ) ) {
			echo '<em>' . esc_html__( 'No messages found', 'ac-advanced-flamingo-settings' ) . '</em>';
			return;
		}

		$args = array(
			'post_type'      => 'flamingo_inbound',
			'posts_per_page' => 1,
			'meta_query'     => array(
				array(
					'key'     => '_from_email',
					'value'   => $email,
					'compare' => '=',
				),
			),
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		$messages = get_posts( $args );

		if ( empty( $messages ) ) {
			echo '<em>' . esc_html__( 'No messages found', 'ac-advanced-flamingo-settings' ) . '</em>';
			return;
		}

		$last_id = $messages[0]->ID;
		$message = get_post_meta( $last_id, '_field_your-message', true );
		$preview = $message ? wp_trim_words( $message, 10, '...' ) : esc_html__( 'Message unavailable', 'ac-advanced-flamingo-settings' );

		$view_url = admin_url( 'admin.php?page=flamingo_inbound&post=' . $last_id . '&action=edit' );

		echo '<div style="display:flex; flex-direction:column; gap:5px;">';
		echo '<span style="font-size: 12px; color: #666;">' . esc_html( $preview ) . '</span>';
		echo '<a href="' . esc_url( $view_url ) . '" class="button button-small">' . esc_html__( 'View Last Message', 'ac-advanced-flamingo-settings' ) . '</a>';
		echo '</div>';
	}
}
