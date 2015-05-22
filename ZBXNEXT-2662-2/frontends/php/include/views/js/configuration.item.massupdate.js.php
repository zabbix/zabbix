<script type="text/javascript">
	jQuery(document).ready(function() {
		var multpStat = document.getElementById('multiplier');
		if (multpStat && multpStat.onclick) {
			multpStat.onclick();
		}

		jQuery('#visible_type, #visible_interface').click(function() {
			// if no item type is selected, reset the interfaces to default
			if (!jQuery('#visible_type').is(':checked')) {
				var itemIntefaceTypes = <?php echo CJs::encodeJson(itemTypeInterface()); ?>;
				organizeInterfaces(itemIntefaceTypes[<?php echo CJs::encodeJson($data['initial_item_type']) ?>]);
			}
			else {
				jQuery('#type').trigger('change');
			}
		});

		jQuery('#type')
			.change(function() {
				// update the interface select with each item type change
				var itemIntefaceTypes = <?php echo CJs::encodeJson(itemTypeInterface()); ?>;
				organizeInterfaces(itemIntefaceTypes[parseInt(jQuery(this).val())]);
			})
			.trigger('change');

		if (jQuery('#visible_delay_flex').length != 0) {
			displayNewDeleyFlexInterval();

			jQuery('#visible_delay_flex').click(function() {
				displayNewDeleyFlexInterval();
			});
		}

		// create jquery buttonset object when authprotocol visible box is switched on
		jQuery('#visible_authprotocol').one('click', function() {
			jQuery('#authprotocol_div').buttonset();
		});

		// create jquery buttonset object when privprotocol visible box is switched on
		jQuery('#visible_privprotocol').one('click', function() {
			jQuery('#privprotocol_div').buttonset();
		});

		var maxReached = <?php echo $this->data['maxReached'] ? 'true' : 'false'; ?>;
		if (maxReached) {
			jQuery('#row-new-delay-flex-fields').hide();
			jQuery('#row-new-delay-flex-max-reached').show();
		}
	});
</script>
