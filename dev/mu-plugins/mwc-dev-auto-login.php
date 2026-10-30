<?php
/**
 * Local development only: logs every visitor in as the first administrator so the
 * browser never needs a password. Mounted by dev/docker-compose.yml, never shipped.
 */

function mwc_dev_auto_login() {
	if ( wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) || wp_doing_ajax() ) {
		return;
	}
	if ( wp_validate_auth_cookie( '', 'logged_in' ) ) {
		return;
	}
	$admins = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
	if ( ! $admins ) {
		return;
	}
	wp_set_auth_cookie( $admins[0]->ID, true );
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$target = isset( $_GET['redirect_to'] ) ? wp_unslash( $_GET['redirect_to'] ) : ( 'wp-login.php' === $GLOBALS['pagenow'] ? admin_url() : home_url( $_SERVER['REQUEST_URI'] ) );
	wp_safe_redirect( $target );
	exit;
}

add_action( 'init', 'mwc_dev_auto_login', 1 );
