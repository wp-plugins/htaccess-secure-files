=== Htaccess Secure Files ===
Contributors: isaacchapman
Tags: secure, htaccess, media
Requires at least: 3.2.1
Tested up to: 3.2.1
Stable tag: 0.1

Allows securing files in WP's media library to be only accessible to users with specific roles and/or capabilities.

== Description ==

The Htaccess Secure Files plugin allows for setting specific files to be accessible only to visitors who have a set <a title="WordPress role or capbility" href="http://codex.wordpress.org/Roles_and_Capabilities">WordPress role or capability</a>. By using <a title=".htaccess files" href="http://en.wikipedia.org/wiki/Htaccess">.htaccess files</a> to secure the content instead of a separate directory outside the web root, WordPress's native media library functionality can be used to upload secure files and link to them from within the visual editor.

By default all built-in WordPress roles will be allowed to access content that is marked as secure. The Settings -> Secure Files admin screen controls which roles and capabilities are allowed to view or download secured files. If a custom role or capability is desired, there are several <a title="WordPress plugins" href="http://wordpress.org/extend/plugins/search.php?q=roles+capabilities">WordPress plugins</a> capable of creating and editing roles and capabilities.

**The Htaccess Secure Files plugin can only be activated on Apache web servers with mod_rewrite enabled.**

This plugin works by creating a .htaccess files in the directory of each secured file. If you manually edit the .htaccess file and it becomes corrupt (a 500 Internal Server Error is the most likely symptom), I recommend deleting the .htaccess file and then edit and save each secured item in the media library.

== Installation ==

1. Upload the htaccess-secure-files directory to your /wp-content/plugins/ directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Select which roles and capabilities should have access to secure files on the 'Settings' -> 'Secure Files' admin screen.
4. To secure individual files edit the file on the 'Media' admin screen and change the 'Secured File' setting to 'Yes'.

== Changelog ==

= 0.1 =
* Initial version
