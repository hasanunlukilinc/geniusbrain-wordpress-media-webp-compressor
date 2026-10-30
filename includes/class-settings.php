<?php
/**
 * Plugin settings.
 *
 * @package MediaWebPCompressor
 */

namespace MediaWebPCompressor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads and registers the plugin options.
 */
class Settings {

	const GROUP              = 'media_webp_compressor';
	const KEEP_ORIGINAL      = 'media_webp_compressor_keep_original';
	const QUALITY            = 'media_webp_compressor_quality';
	const VERSION            = 'media_webp_compressor_version';
	const REPAIRED           = 'media_webp_compressor_repaired';
	const DEFAULT_QUALITY    = 80;
	const BATCH_SIZE         = 10;
	const SOURCE_MIME_TYPES  = array( 'image/jpeg', 'image/png', 'image/gif' );

	/**
	 * Whether original files are kept next to the WebP copies (default: yes).
	 */
	public static function keep_original(): bool {
		return '1' === (string) get_option( self::KEEP_ORIGINAL, '1' );
	}

	/**
	 * WebP quality from 1 to 100 (default 80).
	 */
	public static function quality(): int {
		return self::sanitize_quality( get_option( self::QUALITY, self::DEFAULT_QUALITY ) );
	}

	/**
	 * Clamps a quality value to 1–100, falling back to the default.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_quality( $value ): int {
		$quality = absint( $value );
		return $quality >= 1 && $quality <= 100 ? $quality : self::DEFAULT_QUALITY;
	}

	/**
	 * Registers the options with the Settings API.
	 */
	public static function register(): void {
		register_setting(
			self::GROUP,
			self::KEEP_ORIGINAL,
			array(
				'type'              => 'string',
				'default'           => '1',
				'sanitize_callback' => static function ( $value ) {
					return empty( $value ) ? '0' : '1';
				},
			)
		);
		register_setting(
			self::GROUP,
			self::QUALITY,
			array(
				'type'              => 'integer',
				'default'           => self::DEFAULT_QUALITY,
				'sanitize_callback' => array( __CLASS__, 'sanitize_quality' ),
			)
		);
	}
}
