<?php
/*
Plugin Name: Htaccess Secure Files
Version: 0.1
Plugin URI: http://isaacchapman.com/htaccess-secure-files/
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

// The default roles and capabilities needed to access secured content
define('HSF_DEFAULT_ALLOWED_ROLES', 'subscriber,contributor,author,editor,administrator');
define('HSF_DEFAULT_ALLOWED_CAPABILITIES', '');

// .htaccess file delimiters (DO NOT USE MULTIPLE ADJACENT SPACE CHARACTERS IF THESE ARE MODIFIED)
define('HSF_HTACCESS_NOTICE','#### DO NOT EDIT BELOW (Htaccess Secure Files plugin created content) ####');
define('HSF_HTACCESS_ENTRIES_START', '#### Start of Htaccess Secure Files plugin created entries ####');
define('HSF_HTACCESS_ENTRIES_END', '#### End of Htaccess Secure Files plugin created entries ####');

// Other constants
define('HSF_ALLOWED_ROLES', 'hsf_allowed_roles');
define('HSF_ALLOWED_CAPABILITIES', 'hsf_allowed_capabilities');
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
	global $hsf_roles, $hsf_capabilities;
	if (!($hsf_roles = get_option(HSF_ALLOWED_ROLES))) {
		$hsf_roles = explode(',', HSF_DEFAULT_ALLOWED_ROLES);
		if(count($hsf_roles) == 1 && $hsf_roles[0] == '') { $hsf_roles = array(); }
	}
	if (!($hsf_capabilities = get_option(HSF_ALLOWED_CAPABILITIES))) {
		$hsf_capabilities = explode(',', HSF_DEFAULT_ALLOWED_CAPABILITIES);	
		if(count($hsf_capabilities) == 1 && $hsf_capabilities[0] == '') { $hsf_capabilities = array(); }
	}
}

/**** Admin screen ****/
add_action('admin_menu', 'hsf_admin_menu');
function hsf_admin_menu() {
	add_submenu_page('options-general.php', 'Secure Files', 'Secure Files', HSF_REQUIRED_ADMIN_CAPABILITY, 'hsf-settings', 'hsf_admin_screen');
}

add_action('admin_head', 'hsf_admin_head');
function hsf_admin_head() {
	echo ('<link rel="stylesheet" type="text/css" href="' . WP_PLUGIN_URL . '/' . dirname(plugin_basename(__FILE__)) . '/admin.css" />');
	echo ('<script language="javascript" src="' . WP_PLUGIN_URL . '/' . dirname(plugin_basename(__FILE__)) . '/admin.js"></script>');
}

function hsf_admin_screen() {
	if (!current_user_can(HSF_REQUIRED_ADMIN_CAPABILITY)) {
		wp_die( __('You do not have sufficient permissions to access this page.') );
	}
	
	global $hsf_roles, $hsf_capabilities, $wp_roles;
	
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
					$hsf_roles = array();
					foreach($wp_roles->role_names as $role => $name) {
						if (isset($_POST['role_' . $role]) && $_POST['role_' . $role]) {
							$hsf_roles[] = $role;
						}
					}
					update_option(HSF_ALLOWED_ROLES, $hsf_roles);
					$hsf_capabilities = array();
					foreach ($all_capabilities as $capability => $roles) {
						if (isset($_POST['capability_' . $capability]) && $_POST['capability_' . $capability]) {
							$hsf_capabilities[] = $capability;
						}
					}
					update_option(HSF_ALLOWED_CAPABILITIES, $hsf_capabilities);
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
	?>
	<div class="wrap">
		<?php screen_icon(); ?>
		<h2>Htaccess Secure Files Settings</h2>
		
		<h3>Select which roles and/or capabilities are required to view secured uploads</h3>
		<p>Other <a href="http://wordpress.org/extend/plugins/search.php?q=roles+capabilities&sort=" title="WordPress plugins repository">WordPress plugins</a> can be used to create end edit <a href="http://codex.wordpress.org/Roles_and_Capabilities" title="Roles and Capabilities">roles and capabilities</a>.</p>
		<form method="post">
			<?php 
			wp_nonce_field('hsf_save_settings','hsf_save_settings');
			?>
			<div id="hsf_tab_wrap">
				<ul id="hsf_tabs">
					<li id="hsf_tab_roles" class="hsf_tab_active">Roles</li>
					<li id="hsf_tab_capabilities">Capabilities</li>
					<!--<li id="hsf_tab_users">Users</li>-->
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
							<th class="check-column" scope="row"><input name="role_<?php echo($role); ?>" type="checkbox" class="hsf_checkbox" value="on" <?php if (in_array($role, $hsf_roles)) { echo ('checked="checked"'); } ?> /></th>
							<td>
								<div class="hsf_toggle hsf_role" id="hsf_toggle_<?php echo($role); ?>"><?php echo($name); ?> <span class="hsf_toggle_text">Click to toggle capability listing</span></div>
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
							<th class="check-column" scope="row"><input name="capability_<?php echo($capability); ?>" type="checkbox" class="hsf_checkbox" <?php if (in_array($capability, $hsf_capabilities)) { echo ('checked="checked"'); } ?> /></th>
							<td><strong><?php echo ($capability); ?></strong></td>
							<td><?php echo(implode(', ', $roles)); ?></td>
						</tr>
					<?php } ?>
				</tbody>
			</table>
			<input type="submit" name="hsf_submit" value="Save Settings" class="button-primary" />
		</form>
	</div>
	<?php
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
	
	// Write the initial ruleset
	fwrite($handle, HSF_HTACCESS_NOTICE . "\n");
	fwrite($handle, "RewriteEngine On\n");
	fwrite($handle, "# For files that do not exist use WordPress' root index.php\n");
	fwrite($handle, "RewriteCond %{SCRIPT_FILENAME} !-f\n");
	fwrite($handle, "RewriteRule . " . hsf_get_home_root() . "index.php [L]\n");
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
function hsf_get_dl_file() {
	global $hsf_dl_file;
	if (isset($hsf_dl_file) && $hsf_dl_file) { return $hsf_dl_file; }
	
	$hsf_dl_file = substr(plugin_dir_path(__FILE__), strlen(ABSPATH) - 1) . 'dl.php';
	return $hsf_dl_file;
}

function hsf_get_home_root() {
	global $hsf_home_root;
	if (isset($hsf_home_root) && $hsf_home_root) { return $hsf_home_root; }
	
	// Get the location of the root index file (from function mod_rewrite_rules)
	$home_root = parse_url(home_url());
	if ( isset( $home_root['path'] ) ) {
		$hsf_home_root = trailingslashit($home_root['path']);
	} else {
		$hsf_home_root = '/';
	}
	return $hsf_home_root;
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