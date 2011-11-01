jQuery(document).ready(function() {
	// Initial hash and hash changes
	hsf_hash_change();
	jQuery(window).bind('hashchange', function() {
		hsf_hash_change();
	});
	
	// Toggle the expanded content
	jQuery('.hsf_toggle').click(function() {
		var id = jQuery(this).attr('id').substr(11);
		if (jQuery('#hsf_toggle_div_' + id).is(':visible')) {
			jQuery('#hsf_toggle_div_' + id).hide(150);
		} else {
			jQuery('#hsf_toggle_div_' + id).show(150);
		}
	});
	// Handle clicking on tabs
	jQuery('.hsf_tabs li').click(function() {
		window.location.hash = jQuery(this).attr('id').substr(8);
	});
	// Deleteing an IP address
	jQuery('.hsf_delete_ip').live('click', function() {
		var ip_w_underscores = jQuery(this).attr('id').substr(14)
		var ip = ip_w_underscores.replace(/_/g, '.');
		var pos = jQuery.inArray(ip, hsf_allowed_ips);
		if (pos == 0 && hsf_allowed_ips.length == 1) {
			hsf_allowed_ips = new Array();
		} else {
			hsf_allowed_ips.splice(pos, 1);
		}
		jQuery('#hsf_allowed_ips').val(hsf_allowed_ips.join(','));
		jQuery('#hsf_ip_tr_' + ip_w_underscores).remove();
	});
	// Adding an IP address
	jQuery('#hsf_add_ip_button').click(function() {
		var ip = jQuery('#hsf_add_ip_text').val();
		if (!jQuery.trim(ip)) { return; }
		// Regex from http://stackoverflow.com/questions/106179/regular-expression-to-match-hostname-or-ip-address
		if (!ip.match('^(([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])$')) {
			alert("'" + ip + "' is not a valid IPv4 address");
			return;
		} 
		if (jQuery.inArray(ip, hsf_allowed_ips) != -1) {
			alert("'" + ip + "' is already allowed");
			jQuery('#hsf_add_ip_text').val('');
			return;	
		}
		hsf_allowed_ips.push(ip);
		jQuery('#hsf_allowed_ips').val(hsf_allowed_ips.join(','));
		var ip_w_underscores = ip.replace(/\./g, '_');
		var tr_class = 'class="alternate"';
		if (jQuery('#hsf_tab_content_ip4_addresses tbody tr:last').length) {
			if (jQuery('#hsf_tab_content_ip4_addresses tbody tr:last').hasClass('alternate')) { tr_class = ''; }
		}
		jQuery('#hsf_tab_content_ip4_addresses tbody').append('<tr id="hsf_ip_tr_' + ip_w_underscores + '" ' + tr_class + '><td>' + ip + '</td><td class="hsf_button_cell"><input type="button" value="Delete" class="button-secondary hsf_delete_ip" id="hsf_delete_ip_' + ip_w_underscores + '"></td></tr>');
		jQuery('#hsf_add_ip_text').val('');
	});
	jQuery('.hsf_dr_custom_url').change(function() {

	});
});

function hsf_hash_change() {
	if (location.hash && location.hash.length > 1 && jQuery('#hsf_tab_' + location.hash.substr(1)).length) {
		hsf_show_tab(location.hash.substr(1));
	}
}
function hsf_show_tab(tab_id) {
	var tab = jQuery('#hsf_tab_' + tab_id);
	if (!tab.length) { return; }						// tab not found
	if (tab.hasClass('hsf_tab_active')) { return; }		// tab already active
	tab.siblings().each(function() {
		if (jQuery(this).hasClass('hsf_tab_active')) {
			var id = jQuery(this).attr('id').substr(8);
			jQuery('#hsf_tab_content_' + id).hide();
			jQuery(this).removeClass('hsf_tab_active');
		}
	});
	jQuery('#hsf_tab_content_' + tab_id).show(150);
	tab.addClass('hsf_tab_active');
}
