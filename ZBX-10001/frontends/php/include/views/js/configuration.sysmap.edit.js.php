<script type="text/javascript">
	function toggleAdvancedLabels(toggle) {
		var inputs = ['label_type_hostgroup', 'label_type_host', 'label_type_trigger', 'label_type_map', 'label_type_image'];

		jQuery.each(inputs, function() {
			jQuery('#' + this).parentsUntil('ul').toggle(toggle);
		});

		jQuery('#label_type').parentsUntil('ul').toggle(!toggle);
	}

	function toggleCustomLabel(e) {
		jQuery(e.target).parent().find('textarea').toggle(e.target.options[e.target.selectedIndex].value.toString() == '<?php echo MAP_LABEL_TYPE_CUSTOM; ?>');
	}

	jQuery(document).ready(function() {
		jQuery('#label_format').click(function() {
			toggleAdvancedLabels(jQuery('#label_format:checked').length != 0);
		});

		var inputs = ['label_type_hostgroup', 'label_type_host', 'label_type_trigger', 'label_type_map', 'label_type_image'];

		jQuery.each(inputs, function() {
			jQuery('#' + this).change(toggleCustomLabel);
		});

		toggleAdvancedLabels(jQuery('#label_format:checked').length != 0);

		// clone button
		jQuery('#clone').click(function() {
			jQuery('#sysmapid, #delete, #clone').remove();
			jQuery('#update')
				.text(<?= CJs::encodeJson(_('Add')) ?>)
				.attr({id: 'add', name: 'add'});
			jQuery('#name').focus();
		});
	});
</script>
