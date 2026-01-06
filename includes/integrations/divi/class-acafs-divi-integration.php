<?php
/**
 * Divi Contact Form integration for Flamingo.
 *
 * Hooks into Divi's contact form submission flow to capture inbound messages
 * in Flamingo, similar to Contact Form 7 submissions.
 */

defined( 'ABSPATH' ) || exit;

class ACAFS_Divi_Integration {

	const OPTION_ENABLED = 'acafs_enable_divi_contact_capture';
	const CHANNEL        = 'divi-contact-form';

	/**
	 * Register hooks.
	 */
	public function __construct() {
		if ( ! $this->is_flamingo_available() ) {
			add_action( 'admin_notices', array( $this, 'render_missing_flamingo_notice' ) );
			return;
		}

		// Divi triggers this action inside its contact form processing.
		add_action( 'et_pb_contact_form_submit', array( $this, 'capture_from_action' ), 10, 3 );
	}

	/**
	 * Check if Divi theme is active.
	 */
	public static function is_divi_active() {
		$theme = wp_get_theme();
		if ( ! $theme instanceof WP_Theme ) {
			return false;
		}

		$stylesheet = strtolower( $theme->get_stylesheet() );
		$template   = strtolower( $theme->get_template() );
		$name       = strtolower( $theme->get( 'Name' ) );

		return in_array( 'divi', array( $stylesheet, $template, $name ), true );
	}

	/**
	 * Capture submission data from Divi's contact form action.
	 *
	 * @param array $posted_data Submitted form data.
	 * @param mixed $context     Optional context provided by Divi.
	 * @param mixed $result      Optional result object from Divi.
	 */
	public function capture_from_action( $posted_data, $context = null, $result = null ) {
		$data = $this->sanitize_payload( $posted_data );

		if ( empty( $data ) ) {
			$data = $this->sanitize_payload( $_POST );
		}

		$this->create_flamingo_inbound( $data, $context, $result );
	}

	/**
	 * Build and send an inbound message to Flamingo.
	 *
	 * @param array $data    Sanitized form data.
	 * @param mixed $context Optional context from Divi.
	 * @param mixed $result  Optional result from Divi.
	 */
	private function create_flamingo_inbound( $data, $context = null, $result = null ) {
		if ( empty( $data ) || ! $this->is_flamingo_available() ) {
			return;
		}

		$mapped = $this->map_core_fields( $data );

		$meta = $this->build_meta_fields( $data, $mapped );
		if ( ! empty( $context ) ) {
			$meta['divi_context'] = $this->normalize_field_value( $context );
		}
		if ( ! empty( $result ) ) {
			$meta['divi_result'] = $this->normalize_field_value( $result );
		}

		$from = '';
		if ( ! empty( $mapped['name'] ) && ! empty( $mapped['email'] ) ) {
			$from = sprintf( '%1$s <%2$s>', $mapped['name'], $mapped['email'] );
		} elseif ( ! empty( $mapped['email'] ) ) {
			$from = $mapped['email'];
		} elseif ( ! empty( $mapped['name'] ) ) {
			$from = $mapped['name'];
		}

		$args = array(
			'channel'  => self::CHANNEL,
			'subject'  => $mapped['subject'],
			'from'     => $from,
			'message'  => $mapped['message'],
			'meta'     => $meta,
		);

		if ( function_exists( 'flamingo_add_inbound' ) ) {
			flamingo_add_inbound( $args );
			return;
		}

		if ( class_exists( 'Flamingo_Inbound_Message' ) ) {
			Flamingo_Inbound_Message::add( $args );
		}
	}

	/**
	 * Map core fields from the payload.
	 *
	 * @param array $data Sanitized payload data.
	 * @return array
	 */
	private function map_core_fields( $data ) {
		$name = $this->get_first_value(
			$data,
			array(
				'et_pb_contact_name',
				'name',
				'your-name',
			)
		);
		$email = $this->get_first_value(
			$data,
			array(
				'et_pb_contact_email',
				'email',
				'your-email',
			)
		);
		$subject = $this->get_first_value(
			$data,
			array(
				'et_pb_contact_subject',
				'subject',
				'your-subject',
			)
		);
		$message = $this->get_first_value(
			$data,
			array(
				'et_pb_contact_message',
				'message',
				'your-message',
			)
		);

		return array(
			'name'       => sanitize_text_field( $name ),
			'email'      => sanitize_email( $email ),
			'subject'    => sanitize_text_field( $subject ),
			'message'    => sanitize_textarea_field( $message ),
			'post_id'    => $this->extract_post_id( $data ),
			'source_url' => $this->extract_source_url( $data ),
			'remote_ip'  => $this->get_remote_ip(),
			'user_agent' => $this->get_user_agent(),
		);
	}

	/**
	 * Build meta fields for Flamingo.
	 *
	 * @param array $data   Sanitized payload data.
	 * @param array $mapped Core mapped fields.
	 * @return array
	 */
	private function build_meta_fields( $data, $mapped ) {
		$meta = array();

		if ( ! empty( $mapped['name'] ) ) {
			$meta['your-name'] = $mapped['name'];
		}
		if ( ! empty( $mapped['email'] ) ) {
			$meta['your-email'] = $mapped['email'];
		}
		if ( ! empty( $mapped['subject'] ) ) {
			$meta['your-subject'] = $mapped['subject'];
		}
		if ( ! empty( $mapped['message'] ) ) {
			$meta['your-message'] = $mapped['message'];
		}
		if ( ! empty( $mapped['source_url'] ) ) {
			$meta['source_url'] = $mapped['source_url'];
		}
		if ( ! empty( $mapped['post_id'] ) ) {
			$meta['source_post_id'] = $mapped['post_id'];
		}
		if ( ! empty( $mapped['remote_ip'] ) ) {
			$meta['remote_ip'] = $mapped['remote_ip'];
		}
		if ( ! empty( $mapped['user_agent'] ) ) {
			$meta['user_agent'] = $mapped['user_agent'];
		}

		$skip_keys = array(
			'et_pb_contact_name',
			'et_pb_contact_email',
			'et_pb_contact_subject',
			'et_pb_contact_message',
			'action',
			'et_pb_contact_nonce',
			'et_fb_process_nonce',
			'et_pb_contact_captcha',
			'et_pb_contact_form_num',
			'et_pb_contact_form_id',
			'et_pb_contact_form_identifier',
			'form_id',
			'post_id',
			'page_id',
			'page_url',
			'referrer',
		);

		foreach ( $data as $key => $value ) {
			if ( in_array( $key, $skip_keys, true ) ) {
				continue;
			}

			$normalized = $this->normalize_field_value( $value );
			if ( '' === $normalized ) {
				continue;
			}

			$meta_key = $this->normalize_field_key( $key );
			if ( empty( $meta_key ) || isset( $meta[ $meta_key ] ) ) {
				continue;
			}

			$meta[ $meta_key ] = $normalized;
		}

		return $meta;
	}

	/**
	 * Normalize the payload into a sanitized array.
	 *
	 * @param mixed $payload Raw payload data.
	 * @return array
	 */
	private function sanitize_payload( $payload ) {
		if ( empty( $payload ) || ! is_array( $payload ) ) {
			return array();
		}

		return wp_unslash( $payload );
	}

	/**
	 * Normalize field values.
	 *
	 * @param mixed $value Raw field value.
	 * @return string
	 */
	private function normalize_field_value( $value ) {
		if ( is_array( $value ) ) {
			$value = array_map( 'sanitize_text_field', $value );
			return implode( ', ', array_filter( $value ) );
		}
		if ( is_scalar( $value ) ) {
			return sanitize_text_field( $value );
		}

		return '';
	}

	/**
	 * Normalize field keys into safe meta keys.
	 *
	 * @param string $key Field key.
	 * @return string
	 */
	private function normalize_field_key( $key ) {
		$key = sanitize_key( $key );
		$key = preg_replace( '/^et_pb_contact_/', '', $key );
		return $key;
	}

	/**
	 * Get the first non-empty value from a list of keys.
	 *
	 * @param array $data Input data.
	 * @param array $keys Keys to check.
	 * @return string
	 */
	private function get_first_value( $data, $keys ) {
		foreach ( $keys as $key ) {
			if ( ! empty( $data[ $key ] ) ) {
				return $data[ $key ];
			}
		}

		return '';
	}

	/**
	 * Extract post ID from payload.
	 *
	 * @param array $data Payload data.
	 * @return int
	 */
	private function extract_post_id( $data ) {
		$potential = $this->get_first_value(
			$data,
			array(
				'post_id',
				'page_id',
				'et_pb_contact_form_id',
			)
		);

		return $potential ? absint( $potential ) : 0;
	}

	/**
	 * Extract source URL from payload.
	 *
	 * @param array $data Payload data.
	 * @return string
	 */
	private function extract_source_url( $data ) {
		$potential = $this->get_first_value(
			$data,
			array(
				'page_url',
				'referrer',
			)
		);

		return $potential ? esc_url_raw( $potential ) : '';
	}

	/**
	 * Get the remote IP address in a Flamingo-friendly way if available.
	 *
	 * @return string
	 */
	private function get_remote_ip() {
		if ( function_exists( 'flamingo_get_remote_ip' ) ) {
			return sanitize_text_field( flamingo_get_remote_ip() );
		}

		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	}

	/**
	 * Get the user agent string.
	 *
	 * @return string
	 */
	private function get_user_agent() {
		if ( function_exists( 'flamingo_get_user_agent' ) ) {
			return sanitize_text_field( flamingo_get_user_agent() );
		}

		return isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
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
	 * Render admin notice when Flamingo is missing.
	 */
	public function render_missing_flamingo_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		echo '<div class="notice notice-warning"><p>';
		echo esc_html__( 'Divi Contact Form capture is enabled, but Flamingo is not active. Please activate Flamingo to store Divi submissions.', 'ac-advanced-flamingo-settings' );
		echo '</p></div>';
	}
}
