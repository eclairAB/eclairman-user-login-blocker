<?php

/**
 * Plugin Name:       Eclairman's User Login Blocker
 * Plugin URI:        https://wordpress.org/plugins/eclairman-user-login-blocker/
 * Description:       Block login for selected users. Manage the blocked users from the "Eclairman's User Login Blocker" admin screen.
 * Version:           3.0.0
 * Requires at least: 5.6
 * Requires PHP:      7.2
 * Author:            eclairman
 * Author URI:        https://profiles.wordpress.org/eclairman/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       eclairman-user-login-blocker
 *
 * @package EclairmanUserLoginBlocker
 */

defined('ABSPATH') || exit;

define('DNAL_VERSION', '3.0.0');
define('DNAL_OPTION_KEY', 'dnal_blocked_user_ids');

/**
 * Get the currently blocked user IDs.
 *
 * @return int[]
 */
function dnal_get_blocked_ids()
{
	$ids = get_option(DNAL_OPTION_KEY, array());

	if (! is_array($ids)) {
		$ids = array();
	}

	return array_values(array_unique(array_map('absint', $ids)));
}

/**
 * Block login for any user whose ID is on the blocked list.
 *
 * @param WP_User|WP_Error|null $user     Resolved user or error from earlier hooks.
 * @param string                $username Submitted username.
 * @param string                $password Submitted password.
 * @return WP_User|WP_Error
 */
function dnal_block_user_login($user, $username, $password)
{
	if (is_wp_error($user) || ! ($user instanceof WP_User)) {
		return $user;
	}

	if (in_array((int) $user->ID, dnal_get_blocked_ids(), true)) {
		return new WP_Error(
			'dnal_user_login_blocked',
			__('Login is currently disabled for this account.', 'eclairman-user-login-blocker')
		);
	}

	return $user;
}
add_filter('authenticate', 'dnal_block_user_login', 999, 3);

/**
 * Redirect blocked users who are already logged in away from wp-admin.
 */
function dnal_redirect_blocked_from_dashboard()
{
	if (! is_admin() || wp_doing_ajax()) {
		return;
	}

	$current_user = wp_get_current_user();

	if ($current_user->exists() && in_array((int) $current_user->ID, dnal_get_blocked_ids(), true)) {
		wp_safe_redirect(home_url());
		exit;
	}
}
add_action('admin_init', 'dnal_redirect_blocked_from_dashboard');

/**
 * Menu icon: a user silhouette with a diagonal "crossed out" slash.
 *
 * @return string Base64 data URI so WordPress can recolor it to match the admin theme.
 */
function dnal_menu_icon()
{
	$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20">'
		. '<path fill="black" d="M10 10a3 3 0 100-6 3 3 0 000 6zm0 1.2c-3.1 0-6 1.5-6 3.9V17h12v-1.9c0-2.4-2.9-3.9-6-3.9z"/>'
		. '<line x1="2.2" y1="2.2" x2="17.8" y2="17.8" stroke="black" stroke-width="2.2" stroke-linecap="round"/>'
		. '</svg>';

	return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

/**
 * Register the top-level admin menu for the plugin.
 */
function dnal_register_settings_page()
{
	add_menu_page(
		__('Blocked Users', 'eclairman-user-login-blocker'),
		__('Blocked Users', 'eclairman-user-login-blocker'),
		'manage_options',
		'eclairman-user-login-blocker',
		'dnal_render_settings_page',
		dnal_menu_icon(),
		70
	);
}
add_action('admin_menu', 'dnal_register_settings_page');

/**
 * Enqueue admin assets only on the plugin's settings screen.
 *
 * @param string $hook Current admin page hook suffix.
 */
function dnal_enqueue_admin_assets($hook)
{
	if ('toplevel_page_eclairman-user-login-blocker' !== $hook) {
		return;
	}

	wp_enqueue_style(
		'dnal-admin',
		plugin_dir_url(__FILE__) . 'assets/admin.css',
		array(),
		DNAL_VERSION
	);

	wp_enqueue_script(
		'dnal-admin',
		plugin_dir_url(__FILE__) . 'assets/admin.js',
		array(),
		DNAL_VERSION,
		true
	);
}
add_action('admin_enqueue_scripts', 'dnal_enqueue_admin_assets');

/**
 * Add a "Settings" link to the plugin's row on the Plugins screen.
 *
 * @param string[] $links Existing action links.
 * @return string[]
 */
function dnal_plugin_action_links($links)
{
	$settings_link = sprintf(
		'<a href="%s">%s</a>',
		esc_url(admin_url('admin.php?page=eclairman-user-login-blocker')),
		esc_html__('Settings', 'eclairman-user-login-blocker')
	);

	array_unshift($links, $settings_link);

	return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'dnal_plugin_action_links');

/**
 * Render the settings page and handle form submissions.
 */
function dnal_render_settings_page()
{
	if (! current_user_can('manage_options')) {
		wp_die(esc_html__('You do not have permission to access this page.', 'eclairman-user-login-blocker'));
	}

	$notice = '';

	if (isset($_POST['dnal_save'])) {
		check_admin_referer('dnal_save_blocked_users', 'dnal_nonce');

		$submitted = isset($_POST['dnal_blocked']) ? array_map('absint', (array) wp_unslash($_POST['dnal_blocked'])) : array();
		$blocked   = array_values(array_unique($submitted));

		update_option(DNAL_OPTION_KEY, $blocked);
		$notice = __('Login restrictions updated.', 'eclairman-user-login-blocker');
	}

	$blocked_ids = dnal_get_blocked_ids();

	$search = isset($_GET['dnal_search']) ? sanitize_text_field(wp_unslash($_GET['dnal_search'])) : '';

	$user_args = array(
		'orderby' => 'display_name',
		'order'   => 'ASC',
	);

	if ('' !== $search) {
		$user_args['search']         = '*' . $search . '*';
		$user_args['search_columns'] = array('user_login', 'user_email', 'display_name');
	}

	$users      = get_users($user_args);
	$role_names = wp_roles()->get_names();
?>
	<div class="wrap">
		<h1><?php esc_html_e('Eclairman\'s User Login Blocker', 'eclairman-user-login-blocker'); ?></h1>
		<p><?php esc_html_e('Check a user to disable their login. Unchecked users can log in normally.', 'eclairman-user-login-blocker'); ?></p>

		<?php if ($notice) : ?>
			<div class="notice notice-success is-dismissible">
				<p><?php echo esc_html($notice); ?></p>
			</div>
		<?php endif; ?>

		<p>
			<strong><?php echo esc_html(count($blocked_ids)); ?></strong>
			<?php esc_html_e('user(s) currently blocked.', 'eclairman-user-login-blocker'); ?>
		</p>

		<form method="get" class="dnal-search-form">
			<input type="hidden" name="page" value="eclairman-user-login-blocker" />
			<label class="screen-reader-text" for="dnal-search-input"><?php esc_html_e('Search users', 'eclairman-user-login-blocker'); ?></label>
			<input type="search" id="dnal-search-input" name="dnal_search" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search name, login or email', 'eclairman-user-login-blocker'); ?>" />
			<?php submit_button(__('Search', 'eclairman-user-login-blocker'), '', '', false); ?>
		</form>

		<form method="post">
			<?php wp_nonce_field('dnal_save_blocked_users', 'dnal_nonce'); ?>

			<div class="dnal-table-wrap">
				<table class="widefat striped dnal-table">
					<thead>
						<tr>
							<td class="check-column"><?php esc_html_e('Block', 'eclairman-user-login-blocker'); ?></td>
							<th class="dnal-sort" data-type="num"><?php esc_html_e('ID', 'eclairman-user-login-blocker'); ?><span class="dnal-arrow"></span></th>
							<th class="dnal-sort" data-type="str"><?php esc_html_e('Name', 'eclairman-user-login-blocker'); ?><span class="dnal-arrow"></span></th>
							<th class="dnal-sort" data-type="str"><?php esc_html_e('Username', 'eclairman-user-login-blocker'); ?><span class="dnal-arrow"></span></th>
							<th class="dnal-sort" data-type="str"><?php esc_html_e('Email', 'eclairman-user-login-blocker'); ?><span class="dnal-arrow"></span></th>
							<th class="dnal-sort" data-type="str"><?php esc_html_e('Role', 'eclairman-user-login-blocker'); ?><span class="dnal-arrow"></span></th>
						</tr>
					</thead>
					<tbody>
						<?php if (empty($users)) : ?>
							<tr>
								<td colspan="6"><?php esc_html_e('No users found.', 'eclairman-user-login-blocker'); ?></td>
							</tr>
						<?php else : ?>
							<?php
							foreach ($users as $u) :
								$user_roles = array();
								foreach ((array) $u->roles as $role_slug) {
									$user_roles[] = isset($role_names[$role_slug]) ? $role_names[$role_slug] : $role_slug;
								}
								$roles_display = $user_roles ? implode(', ', $user_roles) : '—';
							?>
								<tr>
									<th class="check-column" scope="row">
										<input
											type="checkbox"
											name="dnal_blocked[]"
											value="<?php echo esc_attr($u->ID); ?>"
											<?php checked(in_array((int) $u->ID, $blocked_ids, true)); ?> />
									</th>
									<td><?php echo esc_html($u->ID); ?></td>
									<td><?php echo esc_html($u->display_name); ?></td>
									<td><?php echo esc_html($u->user_login); ?></td>
									<td><?php echo esc_html($u->user_email); ?></td>
									<td><?php echo esc_html($roles_display); ?></td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>

			<?php
			// Preserve blocked IDs not shown in the current (filtered) list so a
			// search-filtered save does not silently unblock hidden users.
			$visible_ids = array_map('absint', wp_list_pluck($users, 'ID'));
			foreach ($blocked_ids as $bid) {
				if (! in_array($bid, $visible_ids, true)) {
					printf('<input type="hidden" name="dnal_blocked[]" value="%d" />', (int) $bid);
				}
			}
			?>

			<?php submit_button(__('Save Changes', 'eclairman-user-login-blocker'), 'primary', 'dnal_save'); ?>
		</form>
	</div>
<?php
}
