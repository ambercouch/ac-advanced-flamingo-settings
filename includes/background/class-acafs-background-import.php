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

	/**
	 * Reusable synchronous import service.
	 *
	 * @var \ACAFS_Import_Service
	 */
	protected $import_service;

	public function __construct( \ACAFS_Import_Service $import_service ) {
		$this->import_service = $import_service;
		parent::__construct(); // Call WP_Background_Process constructor
	}

	/**
	 * Process a single message import.
	 */
	protected function task( $messages_batch ) {
		if ( ! is_array( $messages_batch ) || empty( $messages_batch ) ) {
			return false;
		}

		$result = $this->import_service->import_messages( $messages_batch );

		if ( is_wp_error( $result ) ) {
			error_log( 'ACAFS import failed: ' . $result->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		return false;
	}


	protected function complete() {
		delete_transient( 'acafs_import_started' ); // Clear in-progress marker
		set_transient( 'acafs_import_success', 'completed' );
		parent::complete();
	}
}
