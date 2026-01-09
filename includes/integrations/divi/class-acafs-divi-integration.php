<?php
defined( 'ABSPATH' ) || exit;

class ACAFS_Divi_Integration {
	/**
	 * Prevent duplicate capture when both the Divi action and POST fallback run.
	 *
	 * @var bool
	 */
	private $captured = false;

	/**
	 * Channel slug for Flamingo filtering.
	 *
	 * @var string
	 */
	private $channel = 'divi-contact-form';

	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'register_hooks' ), 20 );
	}

	/**
	 * Check if the Divi theme is active.
	 *
	 * @return bool
	 */
	public static function is_divi_active() {
		$theme      = wp_get_theme();
		$stylesheet = strtolower( $theme->get_stylesheet() );
		$template   = strtolower( $theme->get_template() );
		$name       = strtolower( $theme->get( 'Name' ) );

		return in_array( 'divi', array( $stylesheet, $template ), true ) || false !== strpos( $name, 'divi' );
	}

	/**
	 * Register hooks after plugins_loaded to ensure Flamingo is available.
	 */
	public function register_hooks() {
		if ( ! $this->is_flamingo_available() ) {
			add_action( 'admin_notices', array( $this, 'render_missing_flamingo_notice' ) );
			return;
		}

		// Divi action signature: do_action( 'et_pb_contact_form_submit', $processed_fields_values, $et_contact_error, $contact_form_info ).
		add_action( 'et_pb_contact_form_submit', array( $this, 'capture_from_action' ), 10, 3 );

		// Divi often submits via XHR/POST to the same URL, so we also inspect POST on the front end.
		add_action( 'wp_loaded', array( $this, 'capture_from_post' ), 9 );
	}

	/**
	 * Capture submissions from the Divi Contact Form action.
	 *
	 * @param array $processed_fields_values Processed field values keyed by original field IDs.
	 * @param mixed $et_contact_error Error object or message from Divi.
	 * @param array $contact_form_info Contextual info about the contact form.
	 */
	public function capture_from_action( $processed_fields_values, $et_contact_error, $contact_form_info ) {
		if ( $this->captured || ! empty( $et_contact_error ) ) {
			return;
		}

		$fields = $this->flatten_processed_fields( $processed_fields_values );
		if ( empty( $fields ) ) {
			return;
		}

		$this->create_inbound( $this->map_core_fields( $fields ), $contact_form_info );
	}

	/**
	 * Fallback capture for XHR/POST submissions that do not trigger the action hook.
	 */
	public function capture_from_post() {
		if ( $this->captured || is_admin() || empty( $_POST ) ) {
			return;
		}

		$form_id = $this->detect_form_id_from_post();
		if ( '' === $form_id ) {
			return;
		}

		$nonce_key = '_wpnonce-et-pb-contact-form-submitted-' . $form_id;
		if ( empty( $_POST[ $nonce_key ] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST[ $nonce_key ] ) );
		if ( ! wp_verify_nonce( $nonce, 'et-pb-contact-form-submit' ) ) {
			return;
		}

		$fields = $this->extract_post_fields( $form_id );
		if ( empty( $fields ) ) {
			return;
		}

		$this->create_inbound( $this->map_core_fields( $fields ), array() );
	}

	/**
	 * Detect the form instance from the hidden submit marker.
	 *
	 * @return string
	 */
	private function detect_form_id_from_post() {
		foreach ( array_keys( $_POST ) as $key ) {
			if ( preg_match( '/^et_pb_contactform_submit_(\d+)$/', $key, $matches ) ) {
				return $matches[1];
			}
		}

		return '';
	}

	/**
	 * Flatten Divi processed fields into a simple associative array.
	 *
	 * @param array $processed_fields_values Processed fields data.
	 * @return array
	 */
	private function flatten_processed_fields( $processed_fields_values ) {
		$fields = array();

		foreach ( (array) $processed_fields_values as $key => $field ) {
			$value = $field;

			if ( is_array( $field ) && array_key_exists( 'value', $field ) ) {
				$value = $field['value'];
			}

			if ( ! is_scalar( $value ) ) {
				continue;
			}

			$fields[ sanitize_key( $key ) ] = $value;
		}

		return $this->sanitize_fields( $fields );
	}

	/**
	 * Extract raw POST fields for the given form instance.
	 *
	 * @param string $form_id Divi form instance ID.
	 * @return array
	 */
	private function extract_post_fields( $form_id ) {
		$fields = array();

		foreach ( $_POST as $key => $value ) {
			if ( ! is_string( $key ) ) {
				continue;
			}

			if ( preg_match( '/^et_pb_contact_(.+)_' . preg_quote( (string) $form_id, '/' ) . '$/', $key, $matches ) ) {
				$field_key = sanitize_key( $matches[1] );
				if ( '' === $field_key || ! is_scalar( $value ) ) {
					continue;
				}

				$fields[ $field_key ] = $value;
			}
		}

		return $this->sanitize_fields( $fields );
	}

	/**
	 * Sanitize field values for storage in Flamingo.
	 *
	 * @param array $fields Raw field values.
	 * @return array
	 */
	private function sanitize_fields( $fields ) {
		$sanitized = array();

		foreach ( (array) $fields as $key => $value ) {
			if ( ! is_scalar( $value ) ) {
				continue;
			}

			$value = wp_unslash( $value );

			switch ( $key ) {
				case 'email':
					$sanitized[ $key ] = sanitize_email( $value );
					break;
				case 'message':
					$sanitized[ $key ] = sanitize_textarea_field( $value );
					break;
				default:
					$sanitized[ $key ] = sanitize_text_field( $value );
					break;
			}
		}

		return array_filter(
			$sanitized,
			function ( $value ) {
				return '' !== $value && null !== $value;
			}
		);
	}

	/**
	 * Map Divi core fields to CF7-style keys while preserving any extras.
	 *
	 * @param array $fields Sanitized field values.
	 * @return array
	 */
	private function map_core_fields( $fields ) {
		$mapped = $fields;

		$core_map = array(
			'name'    => 'your-name',
			'email'   => 'your-email',
			'subject' => 'your-subject',
			'message' => 'your-message',
		);

		foreach ( $core_map as $source_key => $target_key ) {
			if ( isset( $fields[ $source_key ] ) ) {
				$mapped[ $target_key ] = $fields[ $source_key ];
				unset( $mapped[ $source_key ] );
			}
		}

		return $mapped;
	}

	/**
	 * Create an inbound Flamingo message from sanitized fields.
	 *
	 * @param array $fields Sanitized field values.
	 * @param array $contact_form_info Divi contact form info.
	 */
	private function create_inbound( $fields, $contact_form_info ) {
		$args = $this->build_inbound_args( $fields, $contact_form_info );
		if ( empty( $args ) ) {
			return;
		}

		$result = null;
		if ( function_exists( 'flamingo_add_inbound' ) ) {
			$result = flamingo_add_inbound( $args );
		} elseif ( class_exists( 'Flamingo_Inbound_Message' ) ) {
			$result = Flamingo_Inbound_Message::add( $args );
		}

		if ( $result ) {
			$this->captured = true;
		}
	}

	/**
	 * Build the args array Flamingo expects.
	 *
	 * @param array $fields Sanitized fields.
	 * @param array $contact_form_info Divi contact form info.
	 * @return array
	 */
	private function build_inbound_args( $fields, $contact_form_info ) {
		$name    = isset( $fields['your-name'] ) ? $fields['your-name'] : '';
		$email   = isset( $fields['your-email'] ) ? $fields['your-email'] : '';
		$subject = isset( $fields['your-subject'] ) ? $fields['your-subject'] : '';
		$message = isset( $fields['your-message'] ) ? $fields['your-message'] : '';

		if ( '' === $subject ) {
			$subject = esc_html__( 'Divi Contact Form submission', 'ac-advanced-flamingo-settings' );
			$fields['your-subject'] = $subject;
		}

		$from = '';
		if ( $email && $name ) {
			$from = sprintf( '%1$s <%2$s>', $name, $email );
		} elseif ( $email ) {
			$from = $email;
		}

		$meta = $this->build_meta( $contact_form_info );

		return array(
			'channel'    => $this->channel,
			'subject'    => $subject,
			'from'       => $from,
			'from_name'  => $name,
			'from_email' => $email,
			'message'    => $message,
			'fields'     => $fields,
			'meta'       => $meta,
		);
	}

	/**
	 * Build meta values for the inbound message.
	 *
	 * @param array $contact_form_info Divi contact form info.
	 * @return array
	 */
	private function build_meta( $contact_form_info ) {
		$meta = array();

		if ( function_exists( 'flamingo_get_remote_ip' ) ) {
			$meta['remote_ip'] = sanitize_text_field( flamingo_get_remote_ip() );
		}

		if ( function_exists( 'flamingo_get_user_agent' ) ) {
			$meta['user_agent'] = sanitize_text_field( flamingo_get_user_agent() );
		}

		$request_url = $this->resolve_request_url();
		if ( $request_url ) {
			$meta['source_url'] = $request_url;
			$post_id            = url_to_postid( $request_url );
			if ( $post_id ) {
				$meta['source_post_id'] = $post_id;
			}
		}

		if ( is_array( $contact_form_info ) && isset( $contact_form_info['post_id'] ) ) {
			$post_id = absint( $contact_form_info['post_id'] );
			if ( $post_id ) {
				$meta['source_post_id'] = $post_id;
			}
		}

		return array_filter(
			$meta,
			function ( $value ) {
				return '' !== $value && null !== $value;
			}
		);
	}

	/**
	 * Resolve the current request URL if possible.
	 *
	 * @return string
	 */
	private function resolve_request_url() {
		if ( function_exists( 'flamingo_request_url' ) ) {
			$request_url = flamingo_request_url();
			return $request_url ? esc_url_raw( $request_url ) : '';
		}

		if ( empty( $_SERVER['REQUEST_URI'] ) ) {
			return '';
		}

		$request_uri = wp_unslash( $_SERVER['REQUEST_URI'] );
		return esc_url_raw( home_url( $request_uri ) );
	}

	/**
	 * Check Flamingo availability.
	 *
	 * @return bool
	 */
	private function is_flamingo_available() {
		return function_exists( 'flamingo_add_inbound' ) || class_exists( 'Flamingo_Inbound_Message' );
	}

	/**
	 * Show an admin notice when Flamingo is missing.
	 */
	public function render_missing_flamingo_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		echo '<div class="notice notice-warning">';
		echo '<p>' . esc_html__( 'Divi Contact Form → Flamingo capture is enabled, but Flamingo is not available.', 'ac-advanced-flamingo-settings' ) . '</p>';
		echo '</div>';
	}
}
