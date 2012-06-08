<?php
// Load minimal WordPress functionality (needed to validate allowed roles and capabilities)
$wp_load = '';

// Walk the directory tree looking for the wp-load.php file. 
$dir = dirname(__FILE__);
while($dir != '/' && is_dir($dir) && $wp_load == '') {
	if (file_exists($dir . '/wp-load.php')) {
		$wp_load = $dir . '/wp-load.php';
		break;
	}
	$dir = dirname($dir);
}
// Could not find wp-load.php file???
if ($wp_load == '') {
	header('Status: 500 Internal Server Error', true, 500);
	echo ('<h1>Error 500: wp-load.php could not be found</h1>');
	hsf_error('wp-load.php could not be found');
	exit();	
}
require($wp_load);

// Ensure the plugin is initialized properly
global $hsf_allowed_roles, $hsf_allowed_capabilities, $hsf_allowed_ips, $current_user, $hsf_denied_response;
if (!isset($current_user) || !isset($hsf_allowed_roles) || !isset($hsf_allowed_capabilities) || !isset($hsf_allowed_ips) || !isset($hsf_denied_response)) {
	header('Status: 500 Internal Server Error', true, 500);
	echo ('Error 500: Htaccess Secure Files plugin error (possibly deactivated)');
	hsf_error('configuration error - missing global variables');
	exit();
}

if (!is_array($hsf_allowed_roles) || !is_array($hsf_allowed_capabilities) || !is_array($hsf_allowed_ips) || !is_object($current_user) || !is_array($hsf_denied_response)) {
	header('Status: 500 Internal Server Error', true, 500);
	echo ('Error 500: Htaccess Secure Files plugin configuration error');
	hsf_error('configuration error');
	exit();	
}

// Can the visitor view/download the file?
$can_view = false;

// Check the IP address 
if (count($hsf_allowed_ips) && in_array($_SERVER['REMOTE_ADDR'], $hsf_allowed_ips)) {
	$can_view = true;
}

// Check the roles
if (!$can_view && count($hsf_allowed_roles) && isset($current_user->roles) && is_array($current_user->roles) && count($current_user->roles) && count(array_intersect($hsf_allowed_roles, $current_user->roles))) {
	$can_view = true;
}

// Check the capabilities
if (!$can_view && count($hsf_allowed_capabilities) && isset($current_user->allcaps) && is_array($current_user->allcaps) && count($current_user->allcaps)) {
	foreach ($current_user->allcaps as $cap => $on) {
		if ($on && in_array($cap, $hsf_allowed_capabilities)) {
			$can_view = true; 
			break;	
		}
	}
}

if (!$can_view) {
	$visitor = 'anon';
	if (isset($current_user) && isset($current_user->ID) && ($current_user->ID)) {
		$visitor = 'user';	
	}
	if (!isset($hsf_denied_response[$visitor])) {
		header('Status: 403 Forbidden', true, 403);
		echo ('Error 403: Access forbidden - access denial action invalid');
		exit();	
	}
	switch ($hsf_denied_response[$visitor]) {
		case 'login':
			header('Location: ' . wp_login_url($_SERVER['REQUEST_URI']), true, 302);
			break;
		case 'custom':
			if (!isset($hsf_denied_response[$visitor . '_custom_url']) || !strlen($hsf_denied_response[$visitor . '_custom_url'])) {
				header('Status: 500 Internal Server Error', true, 500);
				echo ('Error 500: Htaccess Secure Files - ' . $visitor . '_custom_url redirection not set');
				hsf_error('configuration error');
				exit();	
			}
			$login_url = $hsf_denied_response[$visitor . '_custom_url'];
			if (strlen($hsf_denied_response[$visitor . '_custom_url']) >= 10 && false !== strpos($hsf_denied_response[$visitor . '_custom_url'], '%file_url%')) {
				$file_url = 'http';
				if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') {
					$file_url .= 's';	
				}
				$file_url .= '://' . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
				$login_url = str_replace('%file_url%', urlencode($file_url), $login_url);
			}
			header('Location: ' . $login_url, true, 302);
			break;
		case '403':
			header('Status: 403 Forbidden', true, 403);
			echo ('Error 403: Access forbidden');
			break;
		case '404':
			header('Status: 404 Not Found', true, 404);
			echo ('Error 404: Not Found');
			break;
		default:
			header('Status: 403 Forbidden', true, 403);
			echo ('Error 403: Access forbidden - access denial action not set');
			break;
	}
	exit();
}

// Check if the file is there
$file = substr(ABSPATH, 0, strlen(ABSPATH) - strlen(hsf_get_home_root())) . $_SERVER['REQUEST_URI'];
if (!file_exists($file)) {
	header('Status: 404 Not Found', true, 404);
	echo ('Error 404: Resource not found');
	exit();
}

// Set the headers
$filetype = wp_check_filetype($_SERVER['REQUEST_URI']);
$content_type = $filetype['type'];
if (!$content_type) {
	// The content type could not be set using WordPress's built-in (or plugin edited) MIME types, get from PHP if available
	if (function_exists('finfo_open')) {
		// Fileinfo PECL extension installed
		$finfo = finfo_open(FILEINFO_MIME_TYPE);
		$mime_type = finfo_file($finfo, $file);
		finfo_close($finfo);
		if ($mime_type && is_string($mime_type) && !empty($mime_type)) {
			$content_type = $mime_type;	
		}
	}
	if (!$content_type && function_exists('mime_content_type')) {
		// mime_content_type is deprecated
		$mime_type = mime_content_type($file);
		if ($mime_type && is_string($mime_type) && !empty($mime_type)) {
			$content_type = $mime_type;	
		}
	}
	if (!$content_type) {
		$content_type = 'application/octet-stream';	
	}
}

$filestat = stat($file);
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $filestat['mtime']) . ' GMT');
header('Etag: ' . sprintf('"%x-%x-%s"', $filestat['ino'], $filestat['size'], base_convert(str_pad($filestat['mtime'], 16, '0'), 10, 16)));
header('Content-Length: ' . $filestat['size']);
header('Content-Type: ' . $content_type);

// Send the file
ob_clean();
flush();
readfile($file);
?>
