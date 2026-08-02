<?php
/**
 * Plugin Name: WCS JetEngine Skeleton Loader Bootstrap
 * Description: Loads the Skeleton Loader early enough for JetEngine Elementor controls.
 * WCS_JETENGINE_SKELETON_LOADER_BOOTSTRAP
 */

defined( 'ABSPATH' ) || exit;

$wcs_skeleton_plugin = 'wcs-jetengine-skeleton-loader/wcs-jetengine-skeleton-loader.php';
$wcs_skeleton_active = in_array( $wcs_skeleton_plugin, (array) get_option( 'active_plugins', array() ), true );
$wcs_skeleton_network = (array) get_site_option( 'active_sitewide_plugins', array() );

if ( $wcs_skeleton_active || isset( $wcs_skeleton_network[ $wcs_skeleton_plugin ] ) ) {
	$wcs_skeleton_path = WP_CONTENT_DIR . '/plugins/' . $wcs_skeleton_plugin;
	if ( file_exists( $wcs_skeleton_path ) ) {
		require_once $wcs_skeleton_path;
	}
}
