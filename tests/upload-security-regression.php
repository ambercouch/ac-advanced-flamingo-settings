<?php
/**
 * Upload containment and deletion regression tests; isolated local WordPress only.
 *
 * @package AC_Advanced_Flamingo_Settings
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}
require_once dirname( __DIR__ ) . '/includes/features/class-acafs-uploaded-files.php';

/**
 * Check a security invariant.
 *
 * @param bool   $condition Expected result.
 * @param string $message Description.
 */
function acafs_upload_assert( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( esc_html( $message ) );
	}
	WP_CLI::log( 'PASS: ' . $message );
}

$acafs_temp = get_temp_dir() . 'acafs-security-' . wp_generate_uuid4();
wp_mkdir_p( $acafs_temp . '/managed/nested' );
wp_mkdir_p( $acafs_temp . '/outside' );
require_once ABSPATH . 'wp-admin/includes/file.php';
WP_Filesystem();
global $wp_filesystem;
$wp_filesystem->put_contents( $acafs_temp . '/outside/keep.txt', 'outside' );
$wp_filesystem->put_contents( $acafs_temp . '/managed/nested/test.txt', 'inside' );
$acafs_object = new ACAFS_Uploaded_Files();
$acafs_path   = new ReflectionMethod( 'ACAFS_Uploaded_Files', 'get_valid_file_path' );
$acafs_one    = new ReflectionMethod( 'ACAFS_Uploaded_Files', 'delete_single_file' );
$acafs_all    = new ReflectionMethod( 'ACAFS_Uploaded_Files', 'delete_all_files' );
$acafs_path->setAccessible( true );
$acafs_one->setAccessible( true );
$acafs_all->setAccessible( true );
try {
	$acafs_base = $acafs_temp . '/managed';
	acafs_upload_assert( false !== $acafs_path->invoke( $acafs_object, $acafs_base, 'nested/test.txt' ), 'Contained download accepted.' );
	foreach ( array( '', '../outside/keep.txt', '..\\outside\\keep.txt', 'missing.txt', 'nested' ) as $acafs_relative ) {
		acafs_upload_assert( false === $acafs_path->invoke( $acafs_object, $acafs_base, $acafs_relative ), 'Unsafe or non-file download rejected: ' . $acafs_relative );
	}
	acafs_upload_assert( false === $acafs_one->invoke( $acafs_object, $acafs_base, '../outside/keep.txt' ), 'Traversal deletion rejected.' );
	// Symlinks are test fixtures; production code never creates them.
	acafs_upload_assert( symlink( $acafs_temp . '/outside', $acafs_base . '/link' ), 'External symlink fixture created.' );
	acafs_upload_assert( false === $acafs_path->invoke( $acafs_object, $acafs_base, 'link/keep.txt' ), 'External symlink download rejected.' );
	acafs_upload_assert( false === $acafs_one->invoke( $acafs_object, $acafs_base, 'link/keep.txt' ), 'External symlink deletion rejected.' );
	$acafs_block = static function () {
		return '';
	};
	add_filter( 'wp_delete_file', $acafs_block );
	acafs_upload_assert( false === $acafs_one->invoke( $acafs_object, $acafs_base, 'nested/test.txt' ), 'Blocked deletion reports failure.' );
	remove_filter( 'wp_delete_file', $acafs_block );
	acafs_upload_assert( true === $acafs_one->invoke( $acafs_object, $acafs_base, 'nested/test.txt' ), 'Contained single deletion succeeds.' );
	$wp_filesystem->put_contents( $acafs_base . '/nested/new.txt', 'new' );
	acafs_upload_assert( true === $acafs_all->invoke( $acafs_object, $acafs_base ), 'Bulk deletion removes nested files and symlink without following it.' );
	acafs_upload_assert( is_file( $acafs_temp . '/outside/keep.txt' ), 'External file survives bulk deletion.' );
	acafs_upload_assert( is_dir( $acafs_base ) && ! is_dir( $acafs_base . '/nested' ) && ! is_link( $acafs_base . '/link' ), 'Bulk deletion preserves managed root and clears its contents.' );
	acafs_upload_assert( false === $acafs_all->invoke( $acafs_object, $acafs_temp . '/absent' ), 'Missing bulk directory reports failure.' );
} finally {
	$wp_filesystem->delete( $acafs_temp, true );
}
WP_CLI::success( 'Upload security regressions passed.' );
