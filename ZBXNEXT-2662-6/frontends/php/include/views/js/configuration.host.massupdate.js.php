<script type="text/javascript">
	jQuery(function() {
		jQuery('#mass_replace_tpls').on('change', function() {
			jQuery('#mass_clear_tpls').prop('disabled', !this.checked);
		}).change();

		jQuery('#inventory_mode').change(function() {
			jQuery('.formrow-inventory').toggle(jQuery(this).val() !== '<?php echo HOST_INVENTORY_DISABLED; ?>');
		}).change();
	});
</script>
