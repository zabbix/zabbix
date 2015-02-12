<script type="text/javascript">
	function removeDelayFlex(index) {
		jQuery('#delayFlex_' + index).remove();
		jQuery('#delay_flex_' + index + '_delay').remove();
		jQuery('#delay_flex_' + index + '_period').remove();

		displayNewDeleyFlexInterval();
	}

	function displayNewDeleyFlexInterval() {
		// visible_delay_flex is in massupdate, no visible_delay_flex in items
		if (jQuery('#visible_delay_flex').length == 0 || jQuery('#visible_delay_flex').is(':checked')) {
			jQuery('#row_new_delay_flex').show();
		}
		else {
			jQuery('#row_new_delay_flex').hide();
		}

		if (jQuery('#delayFlexTable tr').length <= 7) {
			jQuery('#row-new-delay-flex-fields').show();
			jQuery('#row-new-delay-flex-max-reached').hide();
		}
		else {
			jQuery('#row-new-delay-flex-fields').hide();
			jQuery('#row-new-delay-flex-max-reached').show();
		}
	}

	function itemTypeInterface(type) {
		var result = null;
		var types = <?php echo CJs::encodeJson(itemTypeInterface()); ?>;
		jQuery.each(types, function(itemType, interfaceType) {
			if (type == itemType) {
				result = interfaceType;
				return interfaceType;
			}
		});
		return result;
	}

	function organizeInterfaces(interfaceType) {
		var selectedInterfaceId = +jQuery('#selectedInterfaceId').val();
		var matchingInterfaces = jQuery('#interfaceid option[data-interfacetype="' + interfaceType + '"]');

		var selectedInterfaceOption;
		if (selectedInterfaceId) {
			selectedInterfaceOption = jQuery('#interfaceid option[value="' + selectedInterfaceId + '"]');
		}

		if (jQuery('#visible_interface').data('multipleInterfaceTypes') && !jQuery('#visible_type').is(':checked')) {
			jQuery('#interface_not_defined').html(<?php echo CJs::encodeJson(_('To set a host interface select a single item type for all items')); ?>).show();
			jQuery('#interfaceid').hide();
		}
		else {
			// a specific interface is required
			if (interfaceType > 0) {
				// we have some matching interfaces available
				if (matchingInterfaces.length) {
					jQuery('#interfaceid option')
						.prop('selected', false)
						.prop('disabled', true)
						.filter('[value="0"]').remove();
					matchingInterfaces.prop('disabled', false);

					// select the interface by interfaceid, if it's available
					if (selectedInterfaceId && !selectedInterfaceOption.prop('disabled')) {
						jQuery('#interfaceid').val(selectedInterfaceId);
					}
					// if no interfaceid is given, select the first suitable interface
					else {
						matchingInterfaces.first().prop('selected', true);
					}

					jQuery('#interfaceid').show();
					jQuery('#interface_not_defined').hide();
				}
				// no matching interfaces available
				else {
					// hide combobox and display warning text
					if (!jQuery('#interfaceid option[value="0"]').length) {
						jQuery('#interfaceid').prepend('<option value="0"></option>');
					}
					jQuery('#interfaceid').hide().val(0);
					jQuery('#interface_not_defined').html(<?php echo CJs::encodeJson(_('No interface found')); ?>).show();
				}
			}
			// any interface or no interface
			else {
				// no interface required
				if (interfaceType === null) {
					if (!jQuery('#interfaceid option[value="0"]').length) {
						jQuery('#interfaceid').prepend('<option value="0"></option>');
					}

					jQuery('#interfaceid option')
						.prop('disabled', true)
						.filter('[value="0"]').prop('disabled', false);
					jQuery('#interfaceid').val(0);
				}
				// any interface
				else {
					jQuery('#interfaceid option')
						.prop('disabled', false)
						.filter('[value="0"]').remove();
					if (selectedInterfaceId) {
						selectedInterfaceOption.prop('selected', true);
					}
				}

				jQuery('#interfaceid').show();
				jQuery('#interface_not_defined').hide();
			}
		}
	}

	/*
	 * ITEM_TYPE_ZABBIX: 0
	 * ITEM_TYPE_SNMPTRAP: 17
	 * ITEM_TYPE_SIMPLE: 3
	 */
	function displayKeyButton() {
		var type = parseInt(jQuery('#type').val());

		if (type == 0 || type == 7 || type == 3 || type == 5 || type == 8 || type == 17) {
			jQuery('#keyButton').prop('disabled', false);
		}
		else {
			jQuery('#keyButton').prop('disabled', true);
		}
	}

	function setAuthTypeLabel() {
		if (jQuery('#authtype').val() == <?php echo CJs::encodeJson(ITEM_AUTHTYPE_PUBLICKEY); ?>
				&& jQuery('#type').val() == <?php echo CJs::encodeJson(ITEM_TYPE_SSH); ?>) {
			jQuery('#row_password label').html(<?php echo CJs::encodeJson(_('Key passphrase')); ?>);
		}
		else {
			jQuery('#row_password label').html(<?php echo CJs::encodeJson(_('Password')); ?>);
		}
	}

	jQuery(document).ready(function() {
		// field switchers
		<?php if (!empty($this->data['dataTypeVisibility'])) { ?>
		var dataTypeSwitcher = new CViewSwitcher('data_type', 'change',
			<?php echo zbx_jsvalue($this->data['dataTypeVisibility'], true); ?>);
		<?php } ?>
		<?php
		if (!empty($this->data['valueTypeVisibility'])) { ?>
			var valueTypeSwitcher = new CViewSwitcher('value_type', 'change',
				<?php echo zbx_jsvalue($this->data['valueTypeVisibility'], true); ?>);
		<?php }
		if (!empty($this->data['authTypeVisibility'])) { ?>
			var authTypeSwitcher = new CViewSwitcher('authtype', 'change',
				<?php echo zbx_jsvalue($this->data['authTypeVisibility'], true); ?>);
		<?php }
		if (!empty($this->data['typeVisibility'])) { ?>
			var typeSwitcher = new CViewSwitcher('type', 'change',
				<?php echo zbx_jsvalue($this->data['typeVisibility'], true); ?>,
				<?php echo zbx_jsvalue($this->data['typeDisable'], true); ?>);
		<?php }
		if (!empty($this->data['securityLevelVisibility'])) { ?>
			var securityLevelSwitcher = new CViewSwitcher('snmpv3_securitylevel', 'change',
				<?php echo zbx_jsvalue($this->data['securityLevelVisibility'], true); ?>);
		<?php } ?>

		// multiplier
		var multpStat = document.getElementById('multiplier');

		if (multpStat && multpStat.onclick) {
			multpStat.onclick();
		}

		// type
		jQuery('#type')
			.change(function() {
				// update the interface select with each item type change
				organizeInterfaces(itemTypeInterface(parseInt(jQuery(this).val())));
				displayKeyButton();
				setAuthTypeLabel();
			})
			.trigger('change');

		jQuery('#visible_type, #visible_interface').click(function() {
			// if no item type is selected, reset the interfaces to default
			if (!jQuery('#visible_type').is(':checked')) {
				organizeInterfaces(itemTypeInterface(<?php echo CJs::encodeJson($data['initial_item_type']) ?>));
			}
			else {
				jQuery('#type').trigger('change');
			}

			displayKeyButton();
		});

		// authentication type
		jQuery('#authtype').bind('change', function() {
			setAuthTypeLabel();
		});

		// mass update page
		if (jQuery('#visible_delay_flex').length != 0) {
			displayNewDeleyFlexInterval();

			jQuery('#visible_delay_flex').click(function() {
				displayNewDeleyFlexInterval();
			});
		}

		// mass update page, create jquery buttonset object when authprotocol visible box is switched on
		jQuery('#visible_authprotocol').one('click', function() {
			jQuery('#authprotocol_div').buttonset();
		});

		// mass update page, create jquery buttonset object when privprotocol visible box is switched on
		jQuery('#visible_privprotocol').one('click', function() {
			jQuery('#privprotocol_div').buttonset();
		});

		// flexible interval max reached
		var maxReached = <?php echo $this->data['maxReached'] ? 'true' : 'false'; ?>;

		if (maxReached) {
			jQuery('#row-new-delay-flex-fields').hide();
			jQuery('#row-new-delay-flex-max-reached').show();
		}

		// add flexible interval
		jQuery('#add_delay_flex').click(function() {
			var addDelayFlex = jQuery('<input>', {
				type: 'hidden',
				name: 'add_delay_flex',
				value: 'add'
			});

			jQuery('form[name="itemForm"]').append(addDelayFlex).submit();
		});
	});
</script>
