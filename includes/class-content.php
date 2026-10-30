<?php
/**
 * Post content URL rewriting.
 *
 * @package MediaWebPCompressor
 */

namespace MediaWebPCompressor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rewrites image URLs inside post content when files change format.
 */
class Content {

	/**
	 * Replaces every old URL with its new URL in all post content.
	 *
	 * @param array<string,string> $map Old URL => new URL.
	 */
	public static function replace_urls( array $map ): void {
		global $wpdb;

		foreach ( $map as $old => $new ) {
			if ( '' === $old || $old === $new ) {
				continue;
			}
			$like = '%' . $wpdb->esc_like( $old ) . '%';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- bulk content rewrite has no API equivalent.
			$post_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_content LIKE %s", $like ) );
			if ( ! $post_ids ) {
				continue;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- bulk content rewrite has no API equivalent.
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->posts} SET post_content = REPLACE(post_content, %s, %s) WHERE post_content LIKE %s",
					$old,
					$new,
					$like
				)
			);
			foreach ( $post_ids as $post_id ) {
				clean_post_cache( (int) $post_id );
			}
		}
	}
}
