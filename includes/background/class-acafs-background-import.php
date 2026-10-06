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

	/**
	 * Reusable import service.
	 *
	 * @var \ACAFS_Import_Service
	 */
	private $import_service;

	protected $action = 'acafs_import_flamingo';

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

		$this->import_service->import_batch( $messages_batch );

		return false;
	}


	protected function complete() {
		delete_transient( 'acafs_import_started' ); // Clear in-progress marker
		set_transient( 'acafs_import_success', 'completed' );
		parent::complete();
	}
}
