=== Htaccess Secure Files ===
Contributors: isaacchapman
Tags: secure, htaccess, media
Requires at least: 3.2.1
Tested up to: 3.3.2
Stable tag: 0.5

Allows securing files in WP's media library to be only accessible to users with specific roles, capabilities, or IP addresses.

== Description ==

**The Htaccess Secure Files plugin can only be activated on Apache web servers with mod_rewrite enabled, and will automatically raise an error upon activation if this is not the case.**

The Htaccess Secure Files plugin allows for setting files to be accessible only to visitors who have a specified IP address or <a title="WordPress role or capbility" href="http://codex.wordpress.org/Roles_and_Capabilities">WordPress role or capability</a>. By using <a title=".htaccess files" href="http://en.wikipedia.org/wiki/Htaccess">.htaccess files</a> to secure the content instead of a separate directory outside the web root, WordPress's native media library functionality can be used to upload secure files and link to them from within the visual editor.

By default all built-in WordPress roles will be allowed to access content that is marked as secure. The Settings -> Secure Files admin screen controls which roles, capabilities, and IP addresses are allowed to view or download secured files. If a custom role or capability is desired, there are several <a title="WordPress plugins" href="http://wordpress.org/extend/plugins/search.php?q=roles+capabilities">WordPress plugins</a> capable of creating and editing roles and capabilities.

**Any visitor that matches any selected role, capability, or IP address will be allowed to access secured files.**

This plugin works by creating a .htaccess files in the directory of each secured file. If you manually edit the .htaccess file and it becomes corrupt (a 500 Internal Server Error is the most likely symptom), I recommend deleting the .htaccess file and then edit and save each secured item in the media library.

== Installation ==

1. Unzip the zip archive and upload the htaccess-secure-files directory to your /wp-content/plugins/ directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. On the 'Settings' -> 'Secure Files' admin screen select which roles, capabilities, and IP addresses will be allowed to access secure files and what the server response should be for those denied access.
4. To secure individual files edit the file on the 'Media' admin screen and change the 'Secured File' setting to 'Yes'.

== Changelog ==

= 0.5 =
* Adding smarter detection when WordPress is installed in a sub-directory of a site.

= 0.4 =
* MIME/Content-type detection routine expanded (in order of priority): 1) Use WordPress's built-in (or plugin modified) MIME types. 2) Use Fileinfo PECL extension if installed. 3) Check with mime_content_type (deprecated). 4) Fallback to 'application/octet-stream'.

= 0.3 =
* "Denied access response" is now customizable: WordPress login, 403 Forbidden, 404 Not Found, or custom redirect.

= 0.2 =
* Added "Secure" column to media manager list table
* Added simple IP address whitelisting (may add more complexity in a later version)
* Added the capability to hide/disable the admin interface with a define statement
* Added screenshots

= 0.1 =
* Initial version

== Screenshots ==

1. Change the "Secured File" value to Yes on the Edit Media screen to secure a file.
2. Select the user roles that can access secured files.
3. Select the user capabilities that can access secured files.
4. Select which IP addresses can access secured files.
