<?php
/**
 * Removes the plugin settings. Converted files and per-image state stay untouched.
 *
 * @package MediaWebPCompressor
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'media_webp_compressor_keep_original' );
delete_option( 'media_webp_compressor_quality' );
delete_option( 'media_webp_compressor_version' );
delete_option( 'media_webp_compressor_repaired' );
