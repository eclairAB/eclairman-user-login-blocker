<?php
/**
 * Uninstall routine: remove plugin data.
 *
 * @package DisableSpecificUserLogin
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'dnal_blocked_user_ids' );
