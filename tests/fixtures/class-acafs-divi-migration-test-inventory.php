<?php
/**
 * Plugin inventory fixture for Divi migration regressions.
 *
 * @package AC_Advanced_Flamingo_Settings
 */

defined( 'ABSPATH' ) || exit;

/** Model inventory states without moving files or activating plugins. */
class ACAFS_Divi_Migration_Test_Inventory extends ACAFS_Divi_Migration_Safety {
	/** @var bool Simulated installed state. */
	public $installed = true;
	/** @var bool Simulated activation state. */
	public $active = true;
	/** @return bool Installed state. */
	protected function is_installed() {
		return $this->installed;
	}
	/** @return bool Active state. */
	protected function is_active() {
		return $this->active;
	}
}
