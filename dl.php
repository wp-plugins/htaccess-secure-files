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
global $hsf_allowed_roles, $hsf_allowed_capabilities, $hsf_allowed_ips, $current_user;
if (!isset($current_user) || !isset($hsf_allowed_roles) || !isset($hsf_allowed_capabilities) || !isset($hsf_allowed_ips)) {
	header('Status: 500 Internal Server Error', true, 500);
	echo ('Error 500: Htaccess Secure Files plugin error (possibly deactivated)');
	hsf_error('configuration error - missing global variables');
	exit();
}

if (!is_array($hsf_allowed_roles) || !is_array($hsf_allowed_capabilities) || !is_array($hsf_allowed_ips) || !is_object($current_user)) {
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
	header('Status: 403 Forbidden', true, 403);
	echo ('Error 403: Access forbidden');
	exit();
}

// Check if the file is there
$file = ABSPATH . substr($_SERVER['REQUEST_URI'], 1);
if (!file_exists($file)) {
	header('Status: 404 Not Found', true, 404);
	echo ('Error 404: Resource not found');
	exit();
}

// Set the headers
$filetype = wp_check_filetype($_SERVER['REQUEST_URI']);
$filestat = stat($file);
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $filestat['mtime']) . ' GMT');
header('Etag: ' . sprintf('"%x-%x-%s"', $filestat['ino'], $filestat['size'], base_convert(str_pad($filestat['mtime'], 16, '0'), 10, 16)));
header('Content-Length: ' . $filestat['size']);
if (!$filetype || !is_array($filetype) || !isset($filetype['type']) || !$filetype['type']) {
	header('Content-Type: application/octet-stream');
} else {
	header('Content-Type: '	. $filetype['type']);
}

// Send the file
ob_clean();
flush();
readfile($file);
?>