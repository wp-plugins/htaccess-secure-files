jQuery(document).ready(function() {
	jQuery('.hsf_toggle').click(function() {
		var id = jQuery(this).attr('id').substr(11);
		if (jQuery('#hsf_toggle_div_' + id).is(':visible')) {
			jQuery('#hsf_toggle_div_' + id).hide(150);
		} else {
			jQuery('#hsf_toggle_div_' + id).show(150);
		}
	});
	jQuery('#hsf_tabs li').click(function() {
		if (!jQuery(this).hasClass('hsf_tab_active')) {
			var tab_id = jQuery(this).attr('id').substr(8);
			jQuery('#hsf_tabs li').each(function() {
				if (jQuery(this).hasClass('hsf_tab_active')) {
					var id = jQuery(this).attr('id').substr(8);
					jQuery('#hsf_tab_content_' + id).hide();
					jQuery(this).removeClass('hsf_tab_active');
				}
			});
			jQuery('#hsf_tab_content_' + tab_id).show(150);
			jQuery('#hsf_tab_' + tab_id).addClass('hsf_tab_active');
		}
	});
});