<script type="text/javascript">
	jQuery(function() {
		jQuery('#mass_replace_tpls').on('change', function() {
			jQuery('#mass_clear_tpls').prop('disabled', !this.checked);
		}).change();
	});
</script>
