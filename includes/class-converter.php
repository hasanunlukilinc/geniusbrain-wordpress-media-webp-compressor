<?php
/**
 * WebP conversion of attachments.
 *
 * @package MediaWebPCompressor
 */

namespace MediaWebPCompressor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Converts image attachments (main file, sub-sizes and the unscaled original) to WebP
 * and restores them from the kept originals.
 *
 * Per-attachment state lives in the STATE meta key:
 * - status: converted | skipped | restored
 * - reason: larger | animated | failed | missing (skipped only)
 * - before / after: total bytes of the replaced files and their WebP copies
 * - urls: old URL => new URL for every replaced file
 * - backup: original mime type, attached file and metadata (only when originals are kept)
 */
class Converter {

	const STATE = '_media_webp_compressor';

	/**
	 * Whether the server can write WebP images.
	 */
	public static function is_supported(): bool {
		return wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) );
	}

	/**
	 * Whether the attachment is a source image that was never processed.
	 *
	 * @param int $attachment_id Attachment ID.
	 */
	public static function is_eligible( int $attachment_id ): bool {
		return in_array( get_post_mime_type( $attachment_id ), Settings::SOURCE_MIME_TYPES, true )
			&& '' === get_post_meta( $attachment_id, self::STATE, true );
	}

	/**
	 * Converts new uploads right after WordPress generated their sub-sizes.
	 *
	 * @param mixed  $metadata      Attachment metadata.
	 * @param int    $attachment_id Attachment ID.
	 * @param string $context       'create' for new uploads, 'update' for edits.
	 * @return mixed
	 */
	public static function on_generate_metadata( $metadata, $attachment_id, $context = 'create' ) {
		if ( 'create' !== $context || ! is_array( $metadata ) || ! self::is_supported() || ! self::is_eligible( (int) $attachment_id ) ) {
			return $metadata;
		}
		$result = self::convert( (int) $attachment_id, $metadata, false );
		return $result['metadata'];
	}

	/**
	 * Reports the converted mime type in REST upload responses, which are built from the
	 * attachment as it was before its metadata (and our conversion) was generated.
	 *
	 * @param \WP_REST_Response $response   Response.
	 * @param \WP_Post          $attachment Attachment.
	 * @return \WP_REST_Response
	 */
	public static function filter_rest_attachment( $response, $attachment ) {
		$data = $response->get_data();
		if ( isset( $data['mime_type'] ) ) {
			$data['mime_type'] = get_post_mime_type( $attachment->ID );
			$response->set_data( $data );
		}
		return $response;
	}

	/**
	 * Serves the WebP files for images in post content that still point at the originals.
	 *
	 * @param string $html          Image tag.
	 * @param string $context       Filter context.
	 * @param int    $attachment_id Attachment ID (0 when unknown).
	 * @return string
	 */
	public static function filter_content_img_tag( $html, $context, $attachment_id ) {
		if ( ! $attachment_id ) {
			return $html;
		}
		$state = get_post_meta( (int) $attachment_id, self::STATE, true );
		if ( ! is_array( $state ) || 'converted' !== $state['status'] || empty( $state['urls'] ) ) {
			return $html;
		}
		return strtr( $html, $state['urls'] );
	}

	/**
	 * Converts one attachment.
	 *
	 * @param int        $attachment_id Attachment ID.
	 * @param array|null $metadata      Metadata to work on; read from the database when null.
	 * @param bool       $save_metadata Whether to persist the metadata (false inside the generate filter).
	 * @return array{status:string,reason:string,metadata:mixed,urls:array<string,string>}
	 */
	public static function convert( int $attachment_id, ?array $metadata = null, bool $save_metadata = true ): array {
		$metadata = null === $metadata ? wp_get_attachment_metadata( $attachment_id ) : $metadata;
		$file     = get_attached_file( $attachment_id );
		$mime     = get_post_mime_type( $attachment_id );

		if ( ! is_array( $metadata ) || empty( $metadata['file'] ) || ! $file || ! file_exists( $file ) ) {
			return self::skip( $attachment_id, 'missing', $metadata );
		}
		if ( 'image/gif' === $mime && self::is_animated_gif( $file ) ) {
			return self::skip( $attachment_id, 'animated', $metadata );
		}

		$main = self::encode( $file );
		if ( ! $main['path'] ) {
			return self::skip( $attachment_id, $main['reason'], $metadata );
		}

		$keep      = Settings::keep_original();
		$dir       = dirname( $file );
		$uploads   = wp_get_upload_dir();
		$base_url  = trailingslashit( $uploads['baseurl'] ) . ltrim( dirname( $metadata['file'] ), './' );
		$base_url  = trailingslashit( $base_url );
		$original  = $metadata;
		$replaced  = array( $file );
		$urls      = array( $base_url . wp_basename( $file ) => $base_url . wp_basename( $main['path'] ) );
		$before    = (int) wp_filesize( $file );
		$after     = (int) wp_filesize( $main['path'] );
		$converted = array( wp_basename( $file ) => wp_basename( $main['path'] ) );

		$metadata['file'] = _wp_relative_upload_path( $main['path'] );
		if ( isset( $metadata['filesize'] ) ) {
			$metadata['filesize'] = $after;
		}

		if ( ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
			foreach ( $metadata['sizes'] as $name => $size ) {
				if ( empty( $size['file'] ) ) {
					continue;
				}
				$source = $size['file'];
				if ( ! isset( $converted[ $source ] ) ) {
					$result = self::encode( $dir . '/' . $source );
					if ( ! $result['path'] ) {
						continue; // This size stays in its original format.
					}
					$converted[ $source ]                     = wp_basename( $result['path'] );
					$replaced[]                               = $dir . '/' . $source;
					$urls[ $base_url . $source ]              = $base_url . $converted[ $source ];
					$before                                  += (int) wp_filesize( $dir . '/' . $source );
					$after                                   += (int) wp_filesize( $result['path'] );
				}
				$metadata['sizes'][ $name ]['file']      = $converted[ $source ];
				$metadata['sizes'][ $name ]['mime-type'] = 'image/webp';
				if ( isset( $size['filesize'] ) ) {
					$metadata['sizes'][ $name ]['filesize'] = (int) wp_filesize( $dir . '/' . $converted[ $source ] );
				}
			}
		}

		// The unscaled upload kept by WordPress when a big image was scaled down.
		if ( ! empty( $metadata['original_image'] ) ) {
			$source = $metadata['original_image'];
			$result = self::encode( $dir . '/' . $source );
			if ( $result['path'] ) {
				$metadata['original_image']  = wp_basename( $result['path'] );
				$replaced[]                  = $dir . '/' . $source;
				$urls[ $base_url . $source ] = $base_url . $metadata['original_image'];
			}
		}

		update_attached_file( $attachment_id, $main['path'] );
		self::set_mime_type( $attachment_id, 'image/webp' );

		$state = array(
			'status' => 'converted',
			'before' => $before,
			'after'  => $after,
			'urls'   => $urls,
		);
		if ( $keep ) {
			$state['backup'] = array(
				'mime'     => $mime,
				'file'     => _wp_relative_upload_path( $file ),
				'metadata' => $original,
			);
		} else {
			foreach ( $replaced as $path ) {
				wp_delete_file( $path );
			}
		}
		update_post_meta( $attachment_id, self::STATE, $state );

		if ( $save_metadata ) {
			wp_update_attachment_metadata( $attachment_id, $metadata );
		}

		return array(
			'status'   => 'converted',
			'reason'   => '',
			'metadata' => $metadata,
			'urls'     => $urls,
		);
	}

	/**
	 * Restores the kept original files and removes the WebP copies.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return true|\WP_Error
	 */
	public static function restore( int $attachment_id ) {
		$state = get_post_meta( $attachment_id, self::STATE, true );
		if ( ! is_array( $state ) || empty( $state['backup'] ) ) {
			return new \WP_Error( 'media_webp_compressor_no_backup', __( 'No original file was kept for this image.', 'geniusbrain-media-webp-compressor' ) );
		}

		$uploads  = wp_get_upload_dir();
		$basedir  = trailingslashit( $uploads['basedir'] );
		$baseurl  = trailingslashit( $uploads['baseurl'] );
		$original = $basedir . $state['backup']['file'];
		if ( ! file_exists( $original ) ) {
			return new \WP_Error( 'media_webp_compressor_missing_original', __( 'The original file is no longer on the server.', 'geniusbrain-media-webp-compressor' ) );
		}

		foreach ( $state['urls'] as $new_url ) {
			if ( 0 === strpos( $new_url, $baseurl ) ) {
				wp_delete_file( $basedir . substr( $new_url, strlen( $baseurl ) ) );
			}
		}

		update_attached_file( $attachment_id, $original );
		self::set_mime_type( $attachment_id, $state['backup']['mime'] );
		wp_update_attachment_metadata( $attachment_id, $state['backup']['metadata'] );
		Content::replace_urls( array_flip( $state['urls'] ) );
		update_post_meta( $attachment_id, self::STATE, array( 'status' => 'restored' ) );

		return true;
	}

	/**
	 * Writes a WebP copy next to the source file.
	 *
	 * @param string $path Source image path.
	 * @return array{path:string,reason:string} Empty path with a reason when no copy was kept.
	 */
	private static function encode( string $path ): array {
		if ( ! file_exists( $path ) ) {
			return array(
				'path'   => '',
				'reason' => 'missing',
			);
		}
		$editor = wp_get_image_editor( $path );
		if ( is_wp_error( $editor ) ) {
			return array(
				'path'   => '',
				'reason' => 'failed',
			);
		}
		$dir    = dirname( $path );
		$name   = pathinfo( $path, PATHINFO_FILENAME ) . '.webp';
		$target = $dir . '/' . ( file_exists( $dir . '/' . $name ) ? wp_unique_filename( $dir, $name ) : $name );

		if ( $editor instanceof \WP_Image_Editor_GD && in_array( wp_check_filetype( $path )['type'], array( 'image/png', 'image/gif' ), true ) ) {
			// GD writes an empty WebP file for palette images and still reports success.
			$saved = self::encode_with_gd( $path, $target );
		} else {
			// Saving in another format resets the editor quality to the filtered default, so the
			// setting is applied through the filter for the duration of this save.
			$quality = static function () {
				return Settings::quality();
			};
			add_filter( 'wp_editor_set_quality', $quality, PHP_INT_MAX );
			$result = $editor->save( $target, 'image/webp' );
			remove_filter( 'wp_editor_set_quality', $quality, PHP_INT_MAX );
			$saved = is_wp_error( $result ) || empty( $result['path'] ) ? '' : $result['path'];
		}

		if ( '' === $saved || ! self::is_valid_webp( $saved ) ) {
			if ( '' !== $saved ) {
				wp_delete_file( $saved );
			}
			return array(
				'path'   => '',
				'reason' => 'failed',
			);
		}
		if ( wp_filesize( $saved ) >= wp_filesize( $path ) ) {
			wp_delete_file( $saved );
			return array(
				'path'   => '',
				'reason' => 'larger',
			);
		}
		return array(
			'path'   => $saved,
			'reason' => '',
		);
	}

	/**
	 * Encodes PNG and GIF files with GD directly, converting palette images to true color
	 * first and keeping transparency.
	 *
	 * @param string $path   Source image path.
	 * @param string $target WebP path.
	 * @return string Written path, or '' on failure.
	 */
	private static function encode_with_gd( string $path, string $target ): string {
		if ( ! function_exists( 'imagewebp' ) ) {
			return '';
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file.
		$contents = file_get_contents( $path );
		$image    = false === $contents ? false : imagecreatefromstring( $contents );
		if ( false === $image ) {
			return '';
		}
		if ( ! imageistruecolor( $image ) ) {
			imagepalettetotruecolor( $image );
		}
		imagealphablending( $image, false );
		imagesavealpha( $image, true );
		return imagewebp( $image, $target, Settings::quality() ) ? $target : '';
	}

	/**
	 * Whether a file is a non-empty, readable WebP image.
	 *
	 * @param string $path File path.
	 */
	private static function is_valid_webp( string $path ): bool {
		clearstatcache( true, $path );
		if ( ! file_exists( $path ) || wp_filesize( $path ) <= 0 ) {
			return false;
		}
		$size = wp_getimagesize( $path );
		return is_array( $size ) && isset( $size['mime'] ) && 'image/webp' === $size['mime'];
	}

	/**
	 * Undoes conversions that produced empty or unreadable WebP files (GD palette images in
	 * 1.0.0) when the originals were kept, so bulk conversion processes them again.
	 *
	 * @return int Number of repaired images.
	 */
	public static function repair_broken_conversions(): int {
		global $wpdb;

		$uploads = wp_get_upload_dir();
		$basedir = trailingslashit( $uploads['basedir'] );
		$baseurl = trailingslashit( $uploads['baseurl'] );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time upgrade scan.
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s", self::STATE ) );

		$repaired = 0;
		foreach ( $ids as $attachment_id ) {
			$attachment_id = (int) $attachment_id;
			$state         = get_post_meta( $attachment_id, self::STATE, true );
			if ( ! is_array( $state ) || 'converted' !== $state['status'] || empty( $state['backup'] ) || empty( $state['urls'] ) ) {
				continue;
			}
			$broken = false;
			foreach ( $state['urls'] as $new_url ) {
				if ( 0 === strpos( $new_url, $baseurl ) && ! self::is_valid_webp( $basedir . substr( $new_url, strlen( $baseurl ) ) ) ) {
					$broken = true;
					break;
				}
			}
			if ( $broken && true === self::restore( $attachment_id ) ) {
				delete_post_meta( $attachment_id, self::STATE );
				++$repaired;
			}
		}
		return $repaired;
	}

	/**
	 * Records a skipped attachment so bulk conversion does not retry it.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $reason        Why it was skipped.
	 * @param mixed  $metadata      Unchanged metadata.
	 * @return array{status:string,reason:string,metadata:mixed,urls:array<string,string>}
	 */
	private static function skip( int $attachment_id, string $reason, $metadata ): array {
		update_post_meta(
			$attachment_id,
			self::STATE,
			array(
				'status' => 'skipped',
				'reason' => $reason,
			)
		);
		return array(
			'status'   => 'skipped',
			'reason'   => $reason,
			'metadata' => $metadata,
			'urls'     => array(),
		);
	}

	/**
	 * Changes an attachment's mime type without firing post update hooks.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $mime          New mime type.
	 */
	private static function set_mime_type( int $attachment_id, string $mime ): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- cache is cleaned right after.
		$wpdb->update( $wpdb->posts, array( 'post_mime_type' => $mime ), array( 'ID' => $attachment_id ) );
		clean_post_cache( $attachment_id );
	}

	/**
	 * Detects GIFs with more than one frame.
	 *
	 * @param string $path GIF path.
	 */
	private static function is_animated_gif( string $path ): bool {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file.
		$contents = file_get_contents( $path );
		if ( false === $contents ) {
			return false;
		}
		return preg_match_all( '#\x00\x21\xF9\x04.{4}\x00(\x2C|\x21)#s', $contents ) > 1;
	}
}
