<?php
/**
 * Synchronous access to the real admin background import worker for CLI tests.
 *
 * @package AC_Advanced_Flamingo_Settings
 */

defined( 'ABSPATH' ) || exit;

/** Exercise admin batches without HTTP dispatch or installing plugins. */
class ACAFS_Channel_Import_Test_Worker extends \background\ACAFS_Background_Import {
	/**
	 * Process a queued batch synchronously.
	 *
	 * @param array $messages Portable messages.
	 * @return false Completed item marker.
	 */
	public function run_batch( array $messages ) {
		return $this->task( $messages );
	}

	/** Finish the real worker and its result reporting. */
	public function finish() {
		$this->complete();
	}
}
