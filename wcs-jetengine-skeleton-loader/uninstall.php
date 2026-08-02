<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$wcs_skeleton_bootstrap = trailingslashit( WPMU_PLUGIN_DIR ) . 'wcs-jetengine-skeleton-loader-bootstrap.php';
if ( file_exists( $wcs_skeleton_bootstrap ) ) {
	$wcs_skeleton_content = file_get_contents( $wcs_skeleton_bootstrap );
	if ( false !== $wcs_skeleton_content && false !== strpos( $wcs_skeleton_content, 'WCS_JETENGINE_SKELETON_LOADER_BOOTSTRAP' ) ) {
		unlink( $wcs_skeleton_bootstrap );
	}
}
