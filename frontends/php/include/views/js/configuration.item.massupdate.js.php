<script type="text/javascript">
	jQuery(document).ready(function() {
		var multpStat = document.getElementById('multiplier');
		if (multpStat && multpStat.onclick) {
			multpStat.onclick();
		}

		jQuery('#visible_type, #visible_interface').click(function() {
			// if no item type is selected, reset the interfaces to default
			if (!jQuery('#visible_type').is(':checked')) {
				var itemInterfaceTypes = <?php echo CJs::encodeJson(itemTypeInterface()); ?>;
				organizeInterfaces(itemInterfaceTypes[<?php echo CJs::encodeJson($data['initial_item_type']) ?>]);
			}
			else {
				jQuery('#type').trigger('change');
			}
		});

		jQuery('#type')
			.change(function() {
				// update the interface select with each item type change
				var itemInterfaceTypes = <?php echo CJs::encodeJson(itemTypeInterface()); ?>;
				organizeInterfaces(itemInterfaceTypes[parseInt(jQuery(this).val())]);
			})
			.trigger('change');

		if (jQuery('#visible_delay_flex').length != 0) {
			displayNewDeleyFlexInterval();

			jQuery('#visible_delay_flex').click(function() {
				displayNewDeleyFlexInterval();
			});
		}

		var maxReached = <?php echo $this->data['maxReached'] ? 'true' : 'false'; ?>;
		if (maxReached) {
			jQuery('#row-new-delay-flex-fields').hide();
			jQuery('#row-new-delay-flex-max-reached').show();
		}
	});
</script>
