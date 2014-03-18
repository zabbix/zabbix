<script type="text/javascript">
	jQuery(function() {
		if (jQuery('#filter_search').length) {
				createSuggest('filter_search', true);
		}

		jQuery('#checkAll').on('click', function() {
			if (jQuery('#checkallvalue').val() == 0) {
				jQuery('#filter_dns').prop('checked', true);
				jQuery('#filter_dnssec').prop('checked', true);
				jQuery('#filter_rdds').prop('checked', true);
				jQuery('#filter_epp').prop('checked', true);
				jQuery('#checkallvalue').val(1);
			}
			else {
				jQuery('#filter_dns').prop('checked', false);
				jQuery('#filter_dnssec').prop('checked', false);
				jQuery('#filter_rdds').prop('checked', false);
				jQuery('#filter_epp').prop('checked', false);
				jQuery('#checkallvalue').val(0);
			}
		});
	});
</script>
