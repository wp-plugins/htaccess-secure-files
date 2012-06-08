<?php
/*
Plugin Name: Htaccess Secure Files
Version: 0.5
Plugin URI: http://isaacchapman.com/wordpress-plugins/htaccess-secure-files/
Description: Allows securing media library uploaded files to be vieweable to only users with specified capabilities. A different <a href="http://wordpress.org/extend/plugins/search.php?q=roles+capabilities&sort=" title="WordPress plugins repository">WordPress plugin</a> will be needed if custom <a href="http://codex.wordpress.org/Roles_and_Capabilities" title="Roles and Capabilities">roles and capabilities</a> need to created. <strong>Requires Apache with mod_rewrite enabled!</strong> 
Author: Isaac Chapman
Author URI: http://isaacchapman.com/
*/


/*  Copyright 2011 Isaac Chapman (isaac@isaacchapman.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

define('HSF_VERSION', '0.5.0');

// If the existing settings are to be used and shouldn't be changed through the admin interface HSF_HIDE_ADMIN should be defined as false in the wp-config.php file. For example:
// define('HSF_HIDE_ADMIN', true); 

// The default roles and capabilities needed to access secured content
define('HSF_DEFAULT_ALLOWED_ROLES', 'subscriber,contributor,author,editor,administrator');
define('HSF_DEFAULT_ALLOWED_CAPABILITIES', '');
define('HSF_DEFAULT_ALLOWED_IP', '');
define('HSF_DEFAULT_DENIED_RESPONSE', 'login');

// .htaccess file delimiters (DO NOT USE MULTIPLE ADJACENT SPACE CHARACTERS IF THESE ARE MODIFIED)
define('HSF_HTACCESS_NOTICE','#### DO NOT EDIT BELOW (Htaccess Secure Files plugin created content) ####');
define('HSF_HTACCESS_ENTRIES_START', '#### Start of Htaccess Secure Files plugin created entries ####');
define('HSF_HTACCESS_ENTRIES_END', '#### End of Htaccess Secure Files plugin created entries ####');

// Other constants
define('HSF_ALLOWED_ROLES', 'hsf_allowed_roles');
define('HSF_ALLOWED_CAPABILITIES', 'hsf_allowed_capabilities');
define('HSF_ALLOWED_IPS', 'hsf_allowed_ips');
define('HSF_DENIED_RESPONSE', 'hsf_denied_response');
define('HSF_POST_META_KEY', '_hsf_secured');
define('HSF_SECURED_LABEL', 'Secured File');
define('HSF_REQUIRED_ADMIN_CAPABILITY', 'manage_options');

/**** Plugin activation ****/
register_activation_hook(__FILE__, 'hsf_activate');
function hsf_activate() {
	// This plugin is only set to work on Apache with mod_rewrite enabled. 
	global $is_apache;
	if (!isset($is_apache) || !$is_apache) {
		trigger_error('The Htaccess Secure Files plugin requires Apache', E_USER_ERROR);
		return false;
	}
	$apache_modules = @apache_get_modules();
	if (!isset($apache_modules) || !is_array($apache_modules) || !count($apache_modules)) {
		trigger_error('The Htaccess Secure Files plugin could not determine which Apache modules are active.', E_USER_ERROR);
		return false;
	}
	if (!in_array('mod_rewrite', $apache_modules)) {
		trigger_error('The Htaccess Secure Files plugin requires the mod_rewrite Apache module to be installed and active.', E_USER_ERROR);
		return false;
	}
	return true;
}

/**** Plugin init ****/
add_action('init', 'hsf_init');
function hsf_init() {
	// Load/set defaults (these need to be global so they can be used by dl.php)
	global $hsf_allowed_roles, $hsf_allowed_capabilities, $hsf_allowed_ips, $hsf_denied_response;
	if (!($hsf_allowed_roles = get_option(HSF_ALLOWED_ROLES))) {
		$hsf_allowed_roles = explode(',', HSF_DEFAULT_ALLOWED_ROLES);
		if(count($hsf_allowed_roles) == 1 && $hsf_allowed_roles[0] == '') { $hsf_allowed_roles = array(); }
	}
	if (!($hsf_allowed_capabilities = get_option(HSF_ALLOWED_CAPABILITIES))) {
		$hsf_allowed_capabilities = explode(',', HSF_DEFAULT_ALLOWED_CAPABILITIES);	
		if(count($hsf_allowed_capabilities) == 1 && $hsf_allowed_capabilities[0] == '') { $hsf_allowed_capabilities = array(); }
	}
	if (!($hsf_allowed_ips = get_option(HSF_IP_ALLOWED))) {
		$hsf_allowed_ips = explode(',', HSF_DEFAULT_ALLOWED_IP);	
		if(count($hsf_allowed_ips) == 1 && $hsf_allowed_ips[0] == '') { $hsf_allowed_ips = array(); }
	}
	if (!($hsf_denied_response = get_option(HSF_DENIED_RESPONSE)) || !is_array($hsf_denied_response) || !isset($hsf_denied_response['user']) || !isset($hsf_denied_response['anon'])) {
		$hsf_denied_response = array('user' => HSF_DEFAULT_DENIED_RESPONSE, 'anon' => HSF_DEFAULT_DENIED_RESPONSE);
	}
}



/**** Admin screen ****/
// Should the admin functionality be loaded?
if (!defined('HSF_HIDE_ADMIN') || HSF_HIDE_ADMIN != true) {
	add_action('admin_menu', 'hsf_admin_menu');
}
function hsf_admin_menu() {
	add_submenu_page('options-general.php', 'Secure Files', 'Secure Files', HSF_REQUIRED_ADMIN_CAPABILITY, plugin_basename(__FILE__), 'hsf_admin_screen');
}

add_action('admin_head', 'hsf_admin_head');
function hsf_admin_head() {
	if (basename($_SERVER['SCRIPT_FILENAME']) == 'options-general.php' && isset($_GET['page']) && $_GET['page'] == plugin_basename(__FILE__)) {
		echo ('<link rel="stylesheet" type="text/css" href="' . WP_PLUGIN_URL . '/' . dirname(plugin_basename(__FILE__)) . '/admin.css?ver=' . HSF_VERSION . '" />');
		echo ('<script language="javascript" src="' . WP_PLUGIN_URL . '/' . dirname(plugin_basename(__FILE__)) . '/admin.js?ver=' . HSF_VERSION . '"></script>');
	}
}

function hsf_admin_screen() {
	if (!current_user_can(HSF_REQUIRED_ADMIN_CAPABILITY)) {
		wp_die( __('You do not have sufficient permissions to access this page.') );
	}
	
	global $hsf_allowed_roles, $hsf_allowed_capabilities, $hsf_allowed_ips, $wp_roles, $hsf_denied_response;
	
	// Create array of capabilities
	$all_capabilities = array();
	foreach($wp_roles->role_objects as $role => $role_object) {
		foreach ($role_object->capabilities as $capability => $bool) {
			if (!isset($all_capabilities[$capability])) {
				$all_capabilities[$capability] = array();
			}
			if ($bool) {
				$all_capabilities[$capability][] = $wp_roles->role_names[$role];
			}
		}
	}
	ksort($all_capabilities);
	
	if (!empty($_POST) && isset($_POST['hsf_submit'])) {
		switch($_POST['hsf_submit']) {
			case "Save Settings":
				if (!wp_verify_nonce($_POST['hsf_save_settings'], 'hsf_save_settings')) {
					echo ('<div id="message" class="error fade"><p><strong>Invalid nonce</strong></p></div>');
				} else {
					// Whitelisted roles
					$hsf_allowed_roles = array();
					foreach($wp_roles->role_names as $role => $name) {
						if (isset($_POST['role_' . $role]) && $_POST['role_' . $role]) {
							$hsf_allowed_roles[] = $role;
						}
					}
					update_option(HSF_ALLOWED_ROLES, $hsf_allowed_roles);
					
					// Whitelisted capabilities
					$hsf_allowed_capabilities = array();
					foreach ($all_capabilities as $capability => $roles) {
						if (isset($_POST['capability_' . $capability]) && $_POST['capability_' . $capability]) {
							$hsf_allowed_capabilities[] = $capability;
						}
					}
					update_option(HSF_ALLOWED_CAPABILITIES, $hsf_allowed_capabilities);
					
					// Whitelisted ip addresses
					$hsf_allowed_ips = array();
					if (isset($_POST['hsf_allowed_ips']) && $_POST['hsf_allowed_ips']) {
						$hsf_allowed_ips = explode(',', $_POST['hsf_allowed_ips']);
					}
					update_option(HSF_ALLOWED_IPS, asort($hsf_allowed_ips));

					// Denied access responses
					foreach ($hsf_denied_response as $key => $value) {
						if (isset($_POST['hsf_dr_' . $key]) && trim($_POST['hsf_dr_' . $key])) {
							$value = trim($_POST['hsf_dr_' . $key]);
							$hsf_denied_response[$key] = $value;
							if ($value == 'custom' && isset($_POST['hsf_dr_' . $key . '_custom_url']) && trim($_POST['hsf_dr_' . $key . '_custom_url'])) {
								$hsf_denied_response[$key . '_custom_url'] = trim($_POST['hsf_dr_' . $key . '_custom_url']);
							}
						}
					}
					update_option(HSF_DENIED_RESPONSE, $hsf_denied_response);
					
					// And we are done...
					echo ('<div id="message" class="updated fade"><p><strong>' .  __('Options saved.') . '</strong></p></div>');
				}
				break;
			case "Reset All Htaccess Secure Files": 
			
				break;
			default:
				echo ('<div id="message" class="error fade"><p><strong>Unhandled action</strong></p></div>');
				break;
		}
		
	}
	if (count($hsf_allowed_ips)) {
		echo ('<script language="javascript">var hsf_allowed_ips = new Array("' . implode('","', $hsf_allowed_ips) . '");</script>');
	} else {
		echo ('<script language="javascript">var hsf_allowed_ips = new Array();</script>');
	}
	?>
	<div class="wrap">
		<?php screen_icon(); ?>
		<h2>Htaccess Secure Files Settings</h2>
		
		<h3>Any visitor who matches any of the below selected roles, capabilities, or IP addresses will be allowed to access secured files</h3>
		<p>Other <a href="http://wordpress.org/extend/plugins/search.php?q=roles+capabilities&sort=" title="WordPress plugins repository">WordPress plugins</a> can be used to create end edit <a href="http://codex.wordpress.org/Roles_and_Capabilities" title="Roles and Capabilities">roles and capabilities</a>.</p>
		<form method="post">
			<input type="hidden" name="hsf_allowed_ips" id="hsf_allowed_ips" value="<?php echo(implode(',', $hsf_allowed_ips)); ?>" />
			<?php 
			wp_nonce_field('hsf_save_settings','hsf_save_settings');
			?>
			<div id="hsf_access_tabs_wrap">
				<ul class="hsf_tabs" id="hsf_access_tabs">
					<li id="hsf_tab_roles" class="hsf_tab_active">Roles</li>
					<li id="hsf_tab_capabilities">Capabilities</li>
					<li id="hsf_tab_ip4_addresses">IPv4 Addresses</li>
				</ul>
			</div>
			<table id="hsf_tab_content_roles" class="hsf_tab_content widefat">
				<thead>
					<tr>
						<th>Allow</th>
						<th>Role</th>
					</tr>
				</thead>
				<tbody>
					<?php 
					$tr_class = '';
					foreach($wp_roles->role_names as $role => $name) { 
						$tr_class = ( $tr_class == '' ? ' class="alternate"' : '' );
						?>
						<tr <?php echo($tr_class); ?>>
							<th class="check-column" scope="row"><input name="role_<?php echo($role); ?>" type="checkbox" class="hsf_checkbox" value="on" <?php if (in_array($role, $hsf_allowed_roles)) { echo ('checked="checked"'); } ?> /></th>
							<td>
								<div class="hsf_toggle hsf_role" id="hsf_toggle_<?php echo($role); ?>"><?php echo($name); ?> <span class="hsf_toggle_text">click to show/hide capability listing</span></div>
								<div id="hsf_toggle_div_<?php echo($role); ?>" style="display:none;">
									<ul class="hsf_capability_listing">
										<li style="font-weight:bold; margin-left: 0;">Capabilities:</li>
										<?php 
											foreach($wp_roles->roles[$role]['capabilities'] as $capability => $bool) {
												if ($bool) {
													echo ('<li>' . $capability . '</li>');
												}
											}
										?>
									</ul>
								</div>
							</td>
						</tr>
					<?php } ?>
				</tbody>
			</table>
			<table id="hsf_tab_content_capabilities" class="hsf_tab_content widefat" style="display:none;">
				<thead>
					<tr>
						<th>Allow</th>
						<th>Capability</th>
						<th>Applicable Roles</th>
					</tr>
				</thead>
				<tbody>
					<?php 
					$tr_class = '';
					foreach($all_capabilities as $capability => $roles) { 
						$tr_class = ( $tr_class == '' ? ' class="alternate"' : '' );
						?>
						<tr <?php echo($tr_class); ?>>
							<th class="check-column" scope="row"><input name="capability_<?php echo($capability); ?>" type="checkbox" class="hsf_checkbox" <?php if (in_array($capability, $hsf_allowed_capabilities)) { echo ('checked="checked"'); } ?> /></th>
							<td><strong><?php echo ($capability); ?></strong></td>
							<td><?php echo(implode(', ', $roles)); ?></td>
						</tr>
					<?php } ?>
				</tbody>
			</table>
			<table id="hsf_tab_content_ip4_addresses" class="hsf_tab_content widefat" style="display:none;">
				<thead>
					<tr>
						<th colspan="2">Whitelisted IPv4 Addresses</th>
					<tr>
				<thead>
				<tbody>
					<?php
					$tr_class = '';
					foreach ($hsf_allowed_ips as $ip) {
						$tr_class = ( $tr_class == '' ? ' class="alternate"' : '' );
						echo ('<tr ' . $tr_class . ' id="hsf_ip_tr_' . str_replace('.', '_', $ip) . '">');
						echo ('<td>' . $ip . '</td>');
						echo ('<td class="hsf_button_cell"><input type="button" id="hsf_delete_ip_' . str_replace('.', '_', $ip) . '" class="button-secondary hsf_delete_ip" value="Delete" /></td>');
						echo ('</tr>');
					}
					?>
				</tbody>
				<tfoot>
					<tr>
						<td id="hsf_add_ip_label"><strong>Add IPv4 Address:</strong></td>
						<td>
							<input type="text" maxlength="15" id="hsf_add_ip_text" />
							<input type="button" id="hsf_add_ip_button" class="button-secondary" value="Add IP Address" />
						</td>
					</tr>
				</tfoot>
			</table>
			<br />
			<h3>Denied access response</h3>
			<p>What should be the response for non-authorized attempts to access secured files?</p>
			<div>
				<ul class="hsf_tabs" id="hsf_dr_tabs">
					<li id="hsf_tab_anon" class="hsf_tab_active">Anonymous visitors</li>
					<li id="hsf_tab_user">Logged in users</li>
				</ul>
			</div>
			<table id="hsf_tab_content_anon" class="hsf_tab_content form-table">
				<tbody>
					<tr>
						<th><input type="radio" class="tog" value="login" name="hsf_dr_anon" id="hsf_dr_anon_login" <?php if ($hsf_denied_response['anon'] == 'login') { echo('checked="checked"'); } ?> /> <label for="hsf_dr_anon_login">Redirect to login</label></th>
						<td><code><?php echo(wp_login_url()); ?>?redirect_to=%file_url%</code></td>
					</tr>
					<tr>
						<th><input type="radio" class="tog" value="403" name="hsf_dr_anon" id="hsf_dr_anon_403" <?php if ($hsf_denied_response['anon'] == '403') { echo('checked="checked"'); } ?> /> <label for="hsf_dr_anon_403">Forbidden error</label></th>
						<td><a href="http://en.wikipedia.org/wiki/HTTP_403" title="HTTP 403 Status Code">Status: 403 Forbidden</a></td>
					</tr>
					<tr>
						<th><input type="radio" class="tog" value="404" name="hsf_dr_anon" id="hsf_dr_anon_404" <?php if ($hsf_denied_response['anon'] == '404') { echo('checked="checked"'); } ?> /> <label for="hsf_dr_anon_404">Not found error</label></th>
						<td><a href="http://en.wikipedia.org/wiki/HTTP_404" title="HTTP 404 Status Code">Status: 404 Not Found</a></td>
					</tr>
					<tr>
						<th><input type="radio" class="tog" value="custom" name="hsf_dr_anon" id="hsf_dr_anon_custom" <?php if ($hsf_denied_response['anon'] == 'custom') { echo('checked="checked"'); } ?> /> <label for="hsf_dr_anon_custom">Custom redirect</label></th>
						<td>
							<input type="text" class="regular-text code hsf_dr_custom_url" value="<?php if(isset($hsf_denied_response['anon_custom_url'])) { echo($hsf_denied_response['anon_custom_url']); } ?>" id="hsf_dr_anon_custom_url" name="hsf_dr_anon_custom_url">
							<code>%file_url%</code> can be used in the redirect.
						</td>
					</tr>
				</tbody>
			</table>
			<table id="hsf_tab_content_user" class="hsf_tab_content form-table" style="display:none;">
				<tbody>
					<tr>
						<th><input type="radio" class="tog" value="login" name="hsf_dr_user" id="hsf_dr_user_login" <?php if ($hsf_denied_response['user'] == 'login') { echo('checked="checked"'); } ?> /> <label for="hsf_dr_user_login">Redirect to login</label></th>
						<td><code><?php echo(wp_login_url()); ?>?redirect_to=%file_url%</code></td>
					</tr>
					<tr>
						<th><input type="radio" class="tog" value="403" name="hsf_dr_user" id="hsf_dr_user_403" <?php if ($hsf_denied_response['user'] == '403') { echo('checked="checked"'); } ?> /> <label for="hsf_dr_user_403">Forbidden error</label></th>
						<td><a href="http://en.wikipedia.org/wiki/HTTP_403" title="HTTP 403 Status Code">Status: 403 Forbidden</a></td>
					</tr>
					<tr>
						<th><input type="radio" class="tog" value="404" name="hsf_dr_user" id="hsf_dr_user_404" <?php if ($hsf_denied_response['user'] == '404') { echo('checked="checked"'); } ?> /> <label for="hsf_dr_user_404">Not found error</label></th>
						<td><a href="http://en.wikipedia.org/wiki/HTTP_404" title="HTTP 404 Status Code">Status: 404 Not Found</a></td>
					</tr>
					<tr>
						<th><input type="radio" class="tog" value="custom" name="hsf_dr_user" id="hsf_dr_user_custom" <?php if ($hsf_denied_response['user'] == 'custom') { echo('checked="checked"'); } ?> /> <label for="hsf_dr_user_custom">Custom redirect</label></th>
						<td>
							<input type="text" class="regular-text code hsf_dr_custom_url" value="<?php if(isset($hsf_denied_response['user_custom_url'])) { echo($hsf_denied_response['user_custom_url']); } ?>" id="hsf_dr_user_custom_url" name="hsf_dr_user_custom_url">
							<code>%file_url%</code> can be used in the redirect.
						</td>
					</tr>
				</tbody>
			</table>
			<br />
			<input type="submit" name="hsf_submit" value="Save Settings" class="button-primary" />
		</form>
	</div>
	<?php
}

/**** Media manager ****/
add_filter('manage_media_columns', 'hsf_manage_media_columns');
function hsf_manage_media_columns($columns) {
	// Create a global array of secured files so they do not have to be loaded one at a time by hsf_manage_media_custom_column
	global $hsf_secured_attachment_ids, $wpdb;
	$sql = "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '" . $wpdb->escape(HSF_POST_META_KEY) . "'";
	if (!($hsf_secured_attachment_ids = $wpdb->get_col($sql))) {
		$hsf_secured_attachment_ids = array();
	}
	// Add 'Secured' column to the media list table before the 'date' item
	$new_columns = array();
	foreach ($columns as $key => $value) {
		if ($key == 'date') {
			$new_columns['hsf_secured'] = 'Secured';
		}
		$new_columns[$key] = $value;
	}
	return $new_columns;
}
add_filter('manage_media_custom_column', 'hsf_manage_media_custom_column', 10, 2);
function hsf_manage_media_custom_column($column_name, $attachment_id) {
	if ($column_name == 'hsf_secured') {
		global $hsf_secured_attachment_ids;
		if (in_array($attachment_id, $hsf_secured_attachment_ids)) {
			echo('Yes');
		} else {
			echo('No');
		}
	}
}

/**** Media upload/edit/delete ****/
add_filter('attachment_fields_to_edit', 'hsf_attachment_fields_to_edit', 10, 2);
function hsf_attachment_fields_to_edit($form_fields, $post) {
	$secured_file = false;
	if ($hsf_secured = get_post_meta($post->ID, HSF_POST_META_KEY, true)) {
		$secured_file = true;	
	}
	if ($secured_file) {
		$html = '<input type="radio" name="attachments[' . $post->ID . '][hsf_secured]" id="yes_attachments[' . $post->ID . '][iabc_members_only]" value="1" checked="checked">';
		$html .= '<label for="yes_attachments[' . $post->ID . '][hsf_secured]">Yes</label>';
		$html .= '&nbsp;&nbsp;&nbsp;';
		$html .= '<input type="radio" name="attachments[' . $post->ID . '][hsf_secured]" id="no_attachments[' . $post->ID . '][iabc_members_only]" value="0">';
		$html .= '<label for="no_attachments[' . $post->ID . '][hsf_secured]">No</label>';
	} else {
		$html = '<input type="radio" name="attachments[' . $post->ID . '][hsf_secured]" id="yes_attachments[' . $post->ID . '][iabc_members_only]" value="1">';
		$html .= '<label for="yes_attachments[' . $post->ID . '][hsf_secured]">Yes</label>';
		$html .= '&nbsp;&nbsp;&nbsp;';
		$html .= '<input type="radio" name="attachments[' . $post->ID . '][hsf_secured]" id="no_attachments[' . $post->ID . '][iabc_members_only]" value="0" checked="checked">';
		$html .= '<label for="no_attachments[' . $post->ID . '][hsf_secured]">No</label>';
	}
	$form_fields[] = array('label' => HSF_SECURED_LABEL, 'input' => 'html', 'html' => $html);
	return $form_fields;
}

add_filter('attachment_fields_to_save', 'hsf_attachment_fields_to_save', 10, 2);
function hsf_attachment_fields_to_save($post, $attachement) {
	if (isset($attachement['hsf_secured']) && $attachement['hsf_secured']) {
		update_post_meta($post['ID'], HSF_POST_META_KEY, 1);
		hsf_secure_file($post['ID']);
	} else {
		delete_post_meta($post['ID'], HSF_POST_META_KEY);
		hsf_remove_file_security($post['ID']);
	}
	return $post;
}

add_action('delete_attachment', 'hsf_delete_attachment');
function hsf_delete_attachment($post_id) {
	hsf_remove_file_security($post_id);
}

/**** .htaccess file manipulation functions ****/
function hsf_secure_file($post_id) {
	$file = get_attached_file($post_id, true);
	if (is_dir($file)) {
		// get_attached_file returns the upload directory when there is no matching attachment
		return true;	
	}
	$dir = dirname($file);
	$htaccess_file = $dir . '/.htaccess';
	
	if (!is_dir($dir)) {
		hsf_error('.htaccess file cannot be written to ' . $dir . ' because the directory does not exist');
		return false;
	}
	
	// Which files need to be secured?
	$files = array(basename($file));
	if ($related_files = hsf_get_related_files($post_id)) {
		$files = array_merge($files, $related_files);	
	}

	// If the .htaccess file does not exist, create it
	if (!file_exists($htaccess_file)) {
		hsf_append_htaccess($htaccess_file, $files);
		hsf_notice('.htaccess file created to secure ' . basename($file));
		return true;
	}
	
	// Load .htaccess file
	$file_lines = file($htaccess_file);

	// Check to see if the .htaccess file is properly setup
	$line_numbers = array();
	for ($i = 0; $i < count($file_lines); $i++) {
		switch(hsf_trim($file_lines[$i])) {
			case HSF_HTACCESS_NOTICE: $line_numbers['notice'] = $i; break;
			case HSF_HTACCESS_ENTRIES_START: $line_numbers['start'] = $i; break;
			case HSF_HTACCESS_ENTRIES_END: $line_numbers['end'] = $i; break;
		}
	}
	if (!isset($line_numbers['notice']) || !isset($line_numbers['start']) || !isset($line_numbers['end'])) {
		// The .htaccess file is not setup properly
		hsf_append_htaccess($htaccess_file, $files);
		hsf_notice('.htaccess file appended to secure ' . basename($file));
		return true;
	}
	
	// Create new .htaccess file
	$matched_files = array();
	$write_lines = array();
	for ($i = 0; $i <= $line_numbers['start']; $i++) {
		if (preg_replace('/\s+/', '', $file_lines[$i]) != '') {
			$write_lines[] = $file_lines[$i];
		}
	}
	for ($i = $line_numbers['start'] + 1; $i < $line_numbers['end']; $i++) {
		if (preg_replace('/\s+/', '', $file_lines[$i]) != '') {
			foreach($files as $file) {
				if (hsf_trim($file_lines[$i]) == 'RewriteRule ^(' . hsf_rewrite_escape_filename($file) . ') ' . hsf_get_dl_file() . '?f=$1 [L]') {
					$matched_files[] = $file;	
				}
			}
			$write_lines[] = $file_lines[$i];
		}
	}
	foreach($files as $file) {
		if (!in_array($file, $matched_files)) {
			$write_lines[] = 'RewriteRule ^(' . hsf_rewrite_escape_filename($file) . ') ' . hsf_get_dl_file() . '?f=$1 [L]' . "\n";
		}
	}
	for ($i = $line_numbers['end']; $i < count($file_lines); $i++) {
		if (preg_replace('/\s+/', '', $file_lines[$i]) != '') {
			$write_lines[] = $file_lines[$i];
		}
	}
	if (!file_put_contents($htaccess_file, $write_lines)) {
		hsf_error('could not write/replace ' . $htaccess_file);
		return false;
	}
	hsf_notice('.htaccess file modified to secure ' . basename($file));
	return true;
}

function hsf_remove_file_security($post_id) {
	$file = get_attached_file($post_id, true);
	if (is_dir($file)) {
		// get_attached_file returns the upload directory when there is no matching attachment
		return true;	
	}
	$htaccess_file = dirname($file) . '/.htaccess';
	
	if (!file_exists($htaccess_file)) {
		return ture;
	}
	
	// Which files need to be removed from the listing?
	$files = array(basename($file));
	if ($related_files = hsf_get_related_files($post_id)) {
		$files = array_merge($files, $related_files);	
	}

	// Check if there are files to remove
	if (!count($files)) {
		return false;
	}

	// Load .htaccess file
	$file_lines = file($htaccess_file);
	if (!count($file_lines)) { 
		hsf_error('.htaccess file is empty: ' . $htaccess_file);
		return false;
	}

	// Check to see if the .htaccess file is properly setup
	$line_numbers = array();
	for ($i = 0; $i < count($file_lines); $i++) {
		switch(hsf_trim($file_lines[$i])) {
			case HSF_HTACCESS_NOTICE: $line_numbers['notice'] = $i; break;
			case HSF_HTACCESS_ENTRIES_START: $line_numbers['start'] = $i; break;
			case HSF_HTACCESS_ENTRIES_END: $line_numbers['end'] = $i; break;
		}
	}
	if (!isset($line_numbers['notice']) || !isset($line_numbers['start']) || !isset($line_numbers['end'])) {
		hsf_error('.htaccess file is not formatted properly: ' . $htaccess_file);
		return false;
	}
	
	// Are there any secured files listed?
	if ($line_numbers['start'] + 1 == $line_numbers['end']) { return true; }
	
	// Create new .htaccess file
	$write_lines = array();
	for ($i = 0; $i <= $line_numbers['start']; $i++) {
		if (preg_replace('/\s+/', '', $file_lines[$i]) != '') {
			$write_lines[] = $file_lines[$i];
		}
	}
	for ($i = $line_numbers['start'] + 1; $i < $line_numbers['end']; $i++) {
		if (preg_replace('/\s+/', '', $file_lines[$i]) != '') {
			$match = false;
			foreach($files as $file) {
				if (hsf_trim($file_lines[$i]) == 'RewriteRule ^(' . hsf_rewrite_escape_filename($file) . ') ' . hsf_get_dl_file() . '?f=$1 [L]') {
					$match = true;	
				}
			}
			if (!$match) {
				$write_lines[] = $file_lines[$i];
			}
		}
	}
	for ($i = $line_numbers['end']; $i < count($file_lines); $i++) {
		if (preg_replace('/\s+/', '', $file_lines[$i]) != '') {
			$write_lines[] = $file_lines[$i];
		}	
	}
	if (!file_put_contents($htaccess_file, $write_lines)) {
		hsf_error('could not write/replace ' . $htaccess_file);
		return false;
	}
	
	hsf_notice(basename($file) . ' is not secured by the .htaccess file');
	return true;
}

function hsf_append_htaccess($htaccess_file, $files) {
	if (!$handle = fopen($htaccess_file, "a")) {
		hsf_error('.htaccess file cannot be created/appended: ' . $htaccess_file);
		return false;
	}
	
//	$hsf_home_root = hsf_get_home_root();
		
	// Write the initial ruleset
	fwrite($handle, HSF_HTACCESS_NOTICE . "\n");
	fwrite($handle, "RewriteEngine On\n");
//	fwrite($handle, "RewriteBase " . $hsf_home_root . "\n");
//	fwrite($handle, "# Skip requests for index.php\n");
//	fwrite($handle, "RewriteRule ^index\.php$ - [L]\n");
	fwrite($handle, "# For files that do not exist use WordPress' root index.php\n");
	fwrite($handle, "RewriteCond %{SCRIPT_FILENAME} !-f\n");
	fwrite($handle, "RewriteRule . " . $hsf_home_root . "index.php [L]\n");
	fwrite($handle, "# For files that do exist see if they are secured\n");
	fwrite($handle, "RewriteCond %{SCRIPT_FILENAME} -f\n");
	fwrite($handle, HSF_HTACCESS_ENTRIES_START . "\n");
	foreach ($files as $file) {
		fwrite($handle, 'RewriteRule ^(' . hsf_rewrite_escape_filename($file) . ') ' . hsf_get_dl_file() . '?f=$1 [L]' . "\n");
	}
	fwrite($handle, HSF_HTACCESS_ENTRIES_END . "\n");
	fclose($handle);
}

/**** File/path lookup functions ****/
function hsf_get_home_root() {
	global $hsf_home_root;
	if (isset($hsf_home_root) && $hsf_home_root) { return $hsf_home_root; }
	
	$hsf_home_root = parse_url(get_option('siteurl'));
	if ( isset( $hsf_home_root['path'] ) ) {
		$hsf_home_root = trailingslashit($hsf_home_root['path']);
	} else {
		$hsf_home_root = '/';
	}
	return $hsf_home_root;
}


function hsf_get_dl_file() {
	global $hsf_dl_file;
	if (isset($hsf_dl_file) && $hsf_dl_file) { return $hsf_dl_file; }
	$hsf_dl_file = substr(plugin_dir_path(__FILE__), strlen(ABSPATH) - strlen(hsf_get_home_root())) . 'dl.php';
	return $hsf_dl_file;
}

function hsf_get_related_files($post_id) {
	// Uploaded images can have multiple sizes created and each needs its own line in .htaccess
	if (!($img_meta = get_post_meta($post_id, '_wp_attachment_metadata', true))) { return false; }
	if (!isset($img_meta['sizes'])) { return false; }
	$related_files = array();
	foreach ($img_meta['sizes'] as $image_sizes) {
		if (isset($image_sizes['file'])) {
			$related_files[] = $image_sizes['file'];
		}
	}
	return $related_files;
}

/**** Error/notice handling ****/
function hsf_error($error) {
	if (!session_id()) {
		@session_start();
	}
	error_log('Htaccess Secure Files Plugin error: ' . $error);
	$_SESSION['hsf_notice_message'] = 'Htaccess Secure Files Plugin error: ' . $error;
	$_SESSION['hsf_notice_class'] = 'error';
}

function hsf_notice($message) {
	if (!session_id()) {
		@session_start();
	}
	$_SESSION['hsf_notice_message'] = $message;
	$_SESSION['hsf_notice_class'] = 'updated';
}

add_action('admin_footer', 'hsf_admin_footer');
function hsf_admin_footer() {
	// This action only needs to be run if we are on the media library admin screen
	if (basename($_SERVER['SCRIPT_FILENAME']) != 'upload.php' || basename(dirname($_SERVER['SCRIPT_FILENAME'])) != 'wp-admin') {
		return;	
	}
	if (!session_id()) {
		@session_start();	// Supress the error as the headers have already been set
	}
	if (isset($_SESSION['hsf_notice_message'])) {
		$message = '<div id="message" class="' . $_SESSION['hsf_notice_class'] . '"><p>' . $_SESSION['hsf_notice_message'] . '</p></div>';
		?>
		<script language="javascript">
		jQuery(document).ready(function() {
			jQuery('.subsubsub').before('<?php echo($message); ?>');
		});
		</script>
		<?php
		unset($_SESSION['hsf_notice_message']);
	}
}

/**** Other functions ****/
function hsf_trim($string) {
	return trim(preg_replace('/\s+/', ' ', $string));
}

function hsf_rewrite_escape_filename($filename) {
	$search = array('*', '.', '$', '+');
	$replace = array('\*', '\.', '\$', '\+');
	return str_replace($search, $replace, $filename);
}
?>
