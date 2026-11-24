<?php

namespace background;

use WP_Background_Process;

defined( 'ABSPATH' ) || exit;

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

		$result = $this->import_service->import_batch( $messages_batch );
		$totals = get_transient( 'acafs_import_result' );
		if ( ! is_array( $totals ) ) {
			$totals = array();
		}
		foreach ( array( 'processed', 'inserted', 'skipped', 'failed' ) as $key ) {
			$totals[ $key ] = ( isset( $totals[ $key ] ) ? (int) $totals[ $key ] : 0 ) + (int) $result[ $key ];
		}
		set_transient( 'acafs_import_result', $totals, DAY_IN_SECONDS );

		return false;
	}


	protected function complete() {
		delete_transient( 'acafs_import_started' ); // Clear in-progress marker
		$result = get_transient( 'acafs_import_result' );
		if ( is_array( $result ) && ! empty( $result['failed'] ) ) {
			delete_transient( 'acafs_import_success' );
		} else {
			set_transient( 'acafs_import_success', 'completed' );
		}
		parent::complete();
	}
}
