<?php
/**
 * Admin screens: settings page, bulk conversion, Media Library column and restore action.
 *
 * @package MediaWebPCompressor
 */

namespace MediaWebPCompressor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires the plugin into wp-admin.
 */
class Admin {

	const PAGE           = 'geniusbrain-media-webp-compressor';
	const BULK_ACTION    = 'media_webp_compressor_bulk';
	const RESTORE_ACTION = 'media_webp_compressor_restore';

	/**
	 * Registers admin hooks.
	 */
	public static function init(): void {
		add_action( 'admin_init', array( Settings::class, 'register' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_upgrade' ) );
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'wp_ajax_' . self::BULK_ACTION, array( __CLASS__, 'ajax_bulk' ) );
		add_action( 'admin_post_' . self::RESTORE_ACTION, array( __CLASS__, 'handle_restore' ) );
		add_action( 'admin_notices', array( __CLASS__, 'restore_notice' ) );
		add_filter( 'manage_media_columns', array( __CLASS__, 'add_column' ) );
		add_action( 'manage_media_custom_column', array( __CLASS__, 'render_column' ), 10, 2 );
		add_filter( 'media_row_actions', array( __CLASS__, 'row_actions' ), 10, 2 );
		add_filter( 'plugin_action_links_' . plugin_basename( MEDIA_WEBP_COMPRESSOR_FILE ), array( __CLASS__, 'plugin_links' ) );
	}

	/**
	 * Runs one-time repairs after the plugin is updated.
	 */
	public static function maybe_upgrade(): void {
		$installed = (string) get_option( Settings::VERSION, '' );
		if ( MEDIA_WEBP_COMPRESSOR_VERSION === $installed ) {
			return;
		}
		update_option( Settings::VERSION, MEDIA_WEBP_COMPRESSOR_VERSION );
		if ( '' === $installed || version_compare( $installed, '1.0.1', '<' ) ) {
			$repaired = Converter::repair_broken_conversions();
			if ( $repaired > 0 ) {
				update_option( Settings::REPAIRED, $repaired, false );
			}
		}
	}

	/**
	 * Adds the settings page under Settings, listed with the full product name.
	 */
	public static function add_page(): void {
		add_options_page(
			__( 'GeniusBrain - Media WebP Compressor', 'geniusbrain-media-webp-compressor' ),
			__( 'GeniusBrain - Media WebP Compressor', 'geniusbrain-media-webp-compressor' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Adds a Settings link on the Plugins screen.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public static function plugin_links( array $links ): array {
		$url = admin_url( 'options-general.php?page=' . self::PAGE );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'geniusbrain-media-webp-compressor' ) . '</a>' );
		return $links;
	}

	/**
	 * Loads the bulk conversion script on the settings page only.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue( string $hook ): void {
		if ( 'settings_page_' . self::PAGE !== $hook ) {
			return;
		}
		$url = plugin_dir_url( MEDIA_WEBP_COMPRESSOR_FILE );
		wp_enqueue_style( 'geniusbrain-media-webp-compressor-admin', $url . 'assets/admin.css', array(), MEDIA_WEBP_COMPRESSOR_VERSION );
		wp_enqueue_script( 'geniusbrain-media-webp-compressor-admin', $url . 'assets/admin.js', array(), MEDIA_WEBP_COMPRESSOR_VERSION, true );
		wp_localize_script(
			'geniusbrain-media-webp-compressor-admin',
			'mediaWebpCompressor',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'action'  => self::BULK_ACTION,
				'nonce'   => wp_create_nonce( self::BULK_ACTION ),
				'i18n'    => array(
					'running' => __( 'Converting… keep this page open. If you leave, conversion continues from where it stopped next time.', 'geniusbrain-media-webp-compressor' ),
					'done'    => __( 'All images are processed.', 'geniusbrain-media-webp-compressor' ),
					'error'   => __( 'Conversion stopped because of an error. Try again.', 'geniusbrain-media-webp-compressor' ),
				),
			)
		);
	}

	/**
	 * Renders the settings page.
	 */
	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$stats     = self::stats();
		$supported = Converter::is_supported();
		?>
		<div class="wrap geniusbrain-media-webp-compressor">
			<h1><?php esc_html_e( 'GeniusBrain - Media WebP Compressor', 'geniusbrain-media-webp-compressor' ); ?></h1>

			<?php if ( ! $supported ) : ?>
				<div class="notice notice-error inline"><p>
					<?php esc_html_e( 'This server cannot create WebP images (the GD or Imagick PHP extension with WebP support is missing). Nothing is converted until your host enables it.', 'geniusbrain-media-webp-compressor' ); ?>
				</p></div>
			<?php endif; ?>

			<?php
			$repaired = (int) get_option( Settings::REPAIRED, 0 );
			if ( $repaired > 0 ) :
				delete_option( Settings::REPAIRED );
				?>
				<div class="notice notice-warning inline"><p>
					<?php
					printf(
						/* translators: %s: number of images. */
						esc_html( _n( '%s image had an empty WebP file from an earlier version and was restored to its original. Click "Convert all images" to convert it again.', '%s images had empty WebP files from an earlier version and were restored to their originals. Click "Convert all images" to convert them again.', $repaired, 'geniusbrain-media-webp-compressor' ) ),
						esc_html( number_format_i18n( $repaired ) )
					);
					?>
				</p></div>
			<?php endif; ?>

			<p><?php esc_html_e( 'New JPEG, PNG and GIF uploads are converted to WebP automatically, including every thumbnail size. An image stays in its original format when the WebP copy would be larger, and animated GIFs are never converted.', 'geniusbrain-media-webp-compressor' ); ?></p>

			<form method="post" action="options.php">
				<?php settings_fields( Settings::GROUP ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="geniusbrain-media-webp-compressor-quality"><?php esc_html_e( 'WebP quality', 'geniusbrain-media-webp-compressor' ); ?></label></th>
						<td>
							<input type="number" id="geniusbrain-media-webp-compressor-quality" class="small-text" name="<?php echo esc_attr( Settings::QUALITY ); ?>" value="<?php echo esc_attr( (string) Settings::quality() ); ?>" min="1" max="100" step="1" />
							<p class="description">
								<?php
								printf(
									/* translators: %d: default quality. */
									esc_html__( 'From 1 to 100. Lower values make smaller files with less detail. Default: %d. Applies to images converted from now on.', 'geniusbrain-media-webp-compressor' ),
									(int) Settings::DEFAULT_QUALITY
								);
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Original files', 'geniusbrain-media-webp-compressor' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( Settings::KEEP_ORIGINAL ); ?>" value="1" <?php checked( Settings::keep_original() ); ?> />
								<?php esc_html_e( 'Keep the original file', 'geniusbrain-media-webp-compressor' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'The site always shows the WebP image. Kept originals let you restore an image from the Media Library list.', 'geniusbrain-media-webp-compressor' ); ?></p>
							<p class="description geniusbrain-media-webp-compressor-warning"><?php esc_html_e( 'When unchecked, originals are deleted after conversion and cannot be restored. During bulk conversion, image links inside posts and pages are updated to the WebP files.', 'geniusbrain-media-webp-compressor' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<h2><?php esc_html_e( 'Convert existing images', 'geniusbrain-media-webp-compressor' ); ?></h2>
			<p>
				<?php
				printf(
					/* translators: 1: processed images, 2: all images, 3: saved size such as "12 MB". */
					esc_html__( '%1$s of %2$s images processed. Saved so far: %3$s.', 'geniusbrain-media-webp-compressor' ),
					'<strong id="geniusbrain-media-webp-compressor-done">' . esc_html( number_format_i18n( $stats['done'] ) ) . '</strong>',
					'<strong id="geniusbrain-media-webp-compressor-total">' . esc_html( number_format_i18n( $stats['total'] ) ) . '</strong>',
					'<strong id="geniusbrain-media-webp-compressor-saved">' . esc_html( size_format( $stats['saved'], 1 ) ) . '</strong>'
				);
				?>
			</p>
			<div class="geniusbrain-media-webp-compressor-progress" aria-hidden="true">
				<div class="geniusbrain-media-webp-compressor-bar" style="width: <?php echo esc_attr( $stats['total'] ? (string) round( 100 * $stats['done'] / $stats['total'] ) : '0' ); ?>%"></div>
			</div>
			<p>
				<button type="button" class="button button-primary" id="geniusbrain-media-webp-compressor-start" <?php disabled( ! $supported || 0 === $stats['pending'] ); ?>>
					<?php esc_html_e( 'Convert all images', 'geniusbrain-media-webp-compressor' ); ?>
				</button>
				<span class="geniusbrain-media-webp-compressor-status" id="geniusbrain-media-webp-compressor-status" role="status"><?php echo 0 === $stats['pending'] ? esc_html__( 'All images are processed.', 'geniusbrain-media-webp-compressor' ) : ''; ?></span>
			</p>
		</div>
		<?php
	}

	/**
	 * Converts the next batch of unprocessed images.
	 */
	public static function ajax_bulk(): void {
		check_ajax_referer( self::BULK_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( null, 403 );
		}
		if ( ! Converter::is_supported() ) {
			wp_send_json_error( null, 400 );
		}

		$keep = Settings::keep_original();
		foreach ( self::pending_ids( Settings::BATCH_SIZE ) as $attachment_id ) {
			$result = Converter::convert( $attachment_id );
			if ( ! $keep && 'converted' === $result['status'] ) {
				Content::replace_urls( $result['urls'] );
			}
		}

		$stats = self::stats();
		wp_send_json_success(
			array(
				'done'    => $stats['done'],
				'total'   => $stats['total'],
				'pending' => $stats['pending'],
				'doneText'  => number_format_i18n( $stats['done'] ),
				'totalText' => number_format_i18n( $stats['total'] ),
				'saved'   => size_format( $stats['saved'], 1 ),
			)
		);
	}

	/**
	 * Adds the WebP column to the Media Library list view.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public static function add_column( array $columns ): array {
		$columns['media_webp_compressor'] = __( 'WebP', 'geniusbrain-media-webp-compressor' );
		return $columns;
	}

	/**
	 * Renders the WebP column.
	 *
	 * @param string $column        Column key.
	 * @param int    $attachment_id Attachment ID.
	 */
	public static function render_column( string $column, int $attachment_id ): void {
		if ( 'media_webp_compressor' !== $column ) {
			return;
		}
		$state = get_post_meta( $attachment_id, Converter::STATE, true );
		if ( ! is_array( $state ) ) {
			echo '—';
			return;
		}
		switch ( $state['status'] ) {
			case 'converted':
				$saving = $state['before'] > 0 ? round( 100 * ( $state['before'] - $state['after'] ) / $state['before'] ) : 0;
				/* translators: %s: percentage saved, such as 63. */
				echo esc_html( sprintf( __( 'Converted (−%s%%)', 'geniusbrain-media-webp-compressor' ), number_format_i18n( $saving ) ) );
				break;
			case 'restored':
				esc_html_e( 'Original restored', 'geniusbrain-media-webp-compressor' );
				break;
			default:
				echo esc_html( self::skip_label( $state['reason'] ?? '' ) );
		}
	}

	/**
	 * Adds "Restore original" to converted images whose originals were kept.
	 *
	 * @param array    $actions Row actions.
	 * @param \WP_Post $post    Attachment.
	 * @return array
	 */
	public static function row_actions( array $actions, $post ): array {
		$state = get_post_meta( $post->ID, Converter::STATE, true );
		if ( is_array( $state ) && ! empty( $state['backup'] ) && current_user_can( 'edit_post', $post->ID ) ) {
			$url = wp_nonce_url(
				admin_url( 'admin-post.php?action=' . self::RESTORE_ACTION . '&attachment_id=' . $post->ID ),
				self::RESTORE_ACTION . '_' . $post->ID
			);
			$actions['media_webp_compressor_restore'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Restore original', 'geniusbrain-media-webp-compressor' ) . '</a>';
		}
		return $actions;
	}

	/**
	 * Restores an image and returns to the Media Library.
	 */
	public static function handle_restore(): void {
		$attachment_id = isset( $_GET['attachment_id'] ) ? absint( $_GET['attachment_id'] ) : 0;
		check_admin_referer( self::RESTORE_ACTION . '_' . $attachment_id );
		if ( ! $attachment_id || ! current_user_can( 'edit_post', $attachment_id ) ) {
			wp_die( esc_html__( 'You are not allowed to restore this image.', 'geniusbrain-media-webp-compressor' ), 403 );
		}
		$result = Converter::restore( $attachment_id );
		$back   = wp_get_referer() ? wp_get_referer() : admin_url( 'upload.php?mode=list' );
		wp_safe_redirect( add_query_arg( 'media_webp_compressor_restored', is_wp_error( $result ) ? '0' : '1', $back ) );
		exit;
	}

	/**
	 * Shows the outcome of a restore.
	 */
	public static function restore_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		if ( ! isset( $_GET['media_webp_compressor_restored'] ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		if ( '1' === sanitize_key( wp_unslash( $_GET['media_webp_compressor_restored'] ) ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'The original image was restored.', 'geniusbrain-media-webp-compressor' ) . '</p></div>';
		} else {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'The original image could not be restored.', 'geniusbrain-media-webp-compressor' ) . '</p></div>';
		}
	}

	/**
	 * Human label for a skip reason.
	 *
	 * @param string $reason Reason key.
	 */
	private static function skip_label( string $reason ): string {
		switch ( $reason ) {
			case 'larger':
				return __( 'Kept original (WebP was larger)', 'geniusbrain-media-webp-compressor' );
			case 'animated':
				return __( 'Kept original (animated GIF)', 'geniusbrain-media-webp-compressor' );
			case 'missing':
				return __( 'Skipped (file missing)', 'geniusbrain-media-webp-compressor' );
			default:
				return __( 'Skipped (conversion failed)', 'geniusbrain-media-webp-compressor' );
		}
	}

	/**
	 * IDs of source images that were never processed.
	 *
	 * @param int $limit Maximum number of IDs.
	 * @return int[]
	 */
	private static function pending_ids( int $limit ): array {
		$query = new \WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => Settings::SOURCE_MIME_TYPES,
				'posts_per_page' => $limit,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'no_found_rows'  => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- runs only from the bulk screen.
				'meta_query'     => array(
					array(
						'key'     => Converter::STATE,
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);
		return array_map( 'intval', $query->posts );
	}

	/**
	 * Processed, pending and total image counts plus bytes saved.
	 *
	 * @return array{done:int,pending:int,total:int,saved:int}
	 */
	private static function stats(): array {
		global $wpdb;

		$mimes        = Settings::SOURCE_MIME_TYPES;
		$placeholders = implode( ',', array_fill( 0, count( $mimes ), '%s' ) );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- counts for the settings screen.
		$pending = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s
				WHERE p.post_type = 'attachment' AND p.post_mime_type IN ($placeholders) AND m.meta_id IS NULL",
				array_merge( array( Converter::STATE ), $mimes )
			)
		);
		$states  = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT m.meta_value FROM {$wpdb->postmeta} m
				INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id AND p.post_type = 'attachment'
				WHERE m.meta_key = %s",
				Converter::STATE
			)
		);
		// phpcs:enable

		$saved = 0;
		foreach ( $states as $value ) {
			$state = maybe_unserialize( $value );
			if ( is_array( $state ) && 'converted' === $state['status'] ) {
				$saved += max( 0, (int) $state['before'] - (int) $state['after'] );
			}
		}
		$done = count( $states );

		return array(
			'done'    => $done,
			'pending' => $pending,
			'total'   => $done + $pending,
			'saved'   => $saved,
		);
	}
}
