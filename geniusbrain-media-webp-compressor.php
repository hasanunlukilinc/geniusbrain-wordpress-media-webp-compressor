<?php
/**
 * Plugin Name:       GeniusBrain - Media WebP Compressor
 * Description:       Converts JPEG, PNG and GIF images in the Media Library to WebP on upload and in bulk, with an option to keep the originals.
 * Version:           1.0.2
 * Requires at least: 6.5
 * Tested up to:      7.1
 * Requires PHP:      7.4
 * Author:            Genius Brain
 * Author URI:        https://geniusbrain.io
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       geniusbrain-media-webp-compressor
 *
 * @package MediaWebPCompressor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MEDIA_WEBP_COMPRESSOR_VERSION', '1.0.2' );
define( 'MEDIA_WEBP_COMPRESSOR_FILE', __FILE__ );
define( 'MEDIA_WEBP_COMPRESSOR_DIR', plugin_dir_path( __FILE__ ) );

require_once MEDIA_WEBP_COMPRESSOR_DIR . 'includes/class-settings.php';
require_once MEDIA_WEBP_COMPRESSOR_DIR . 'includes/class-content.php';
require_once MEDIA_WEBP_COMPRESSOR_DIR . 'includes/class-converter.php';
require_once MEDIA_WEBP_COMPRESSOR_DIR . 'includes/class-admin.php';

add_filter( 'wp_generate_attachment_metadata', array( 'MediaWebPCompressor\Converter', 'on_generate_metadata' ), 20, 3 );
add_filter( 'wp_content_img_tag', array( 'MediaWebPCompressor\Converter', 'filter_content_img_tag' ), 10, 3 );
add_filter( 'rest_prepare_attachment', array( 'MediaWebPCompressor\Converter', 'filter_rest_attachment' ), 10, 2 );

if ( is_admin() ) {
	MediaWebPCompressor\Admin::init();
}
