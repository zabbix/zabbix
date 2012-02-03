<script type="text/javascript">
	jQuery(document).ready(function() {
		var valueTypeSwitcher = new CViewSwitcher('value_type', 'change',
			<?php echo zbx_jsvalue($this->data['valueTypeVisibility'], true); ?>
		);
		var authTypeSwitcher = new CViewSwitcher('authtype', 'change',
			<?php echo zbx_jsvalue($this->data['authTypeVisibility'], true); ?>
		);
		var typeSwitcher = new CViewSwitcher('type', 'change',
			<?php echo zbx_jsvalue($this->data['typeVisibility'], true).(!empty($this->data['itemid']) ? ', true' : ''); ?>
		);
		var securityLevelSwitcher = new CViewSwitcher('snmpv3_securitylevel', 'change',
			<?php echo zbx_jsvalue($this->data['securityLevelVisibility'], true); ?>
		);

		var multpStat = document.getElementById('multiplier');
		if (multpStat && multpStat.onclick) {
			multpStat.onclick();
		}

		var mnFrmTbl = document.getElementById('web.items.item.php');
		if (mnFrmTbl) {
			mnFrmTbl.style.visibility = 'visible';
		}

		var maxReached = <?php echo $this->data['maxReached'] ? 'true' : 'false'; ?>;
		if (maxReached) {
			jQuery('#row_new_delay_flex').css('display', 'none');
		}
	});

	function removeDelayFlex(index) {
		jQuery('#delayFlex_' + index).remove();
		jQuery('#delay_flex_' + index + '_delay').remove();
		jQuery('#delay_flex_' + index + '_period').remove();

		if (jQuery('#delayFlexTable tr').length <= 7) {
			jQuery('#row_new_delay_flex').css('display', 'block');
		}
		else {
			jQuery('#row_new_delay_flex').css('display', 'none');
		}
	}

	function organizeInterfaces() {
		var interfaceType = itemTypeInterface(parseInt(jQuery('#type').val()));
		var selectedInterfaceId = jQuery('#selectedInterfaceId').val();
		var isSelected = false;
		var isInterfaceExist = false;

		if (interfaceType > 0) {
			jQuery('#interface_row option').each(function() {
				if (jQuery(this).data('interfacetype') == interfaceType) {
					isInterfaceExist = true;
				}
			});

			if (isInterfaceExist) {
				jQuery('#interfaceid').css('display', 'inline');
				jQuery('#interface_not_defined').css('display', 'none');

				jQuery('#interface_row option').each(function() {
					if (jQuery(this).is('[selected]')) {
						jQuery(this).removeAttr('selected');
					}
				});

				jQuery('#interface_row option').each(function() {
					if (jQuery(this).data('interfacetype') == interfaceType) {
						jQuery(this).css('display', 'block');
						if (!isSelected) {
							if (jQuery(this).val() == selectedInterfaceId) {
								jQuery(this).attr('selected', 'selected');
								isSelected = true;
							}
						}
					}
					else {
						jQuery(this).css('display', 'none');
					}
				});

				// select first available option if we previously don't selected it by interfaceid
				if (!isSelected) {
					jQuery('#interface_row option').each(function() {
						if (jQuery(this).data('interfacetype') == interfaceType) {
							if (!isSelected) {
								jQuery(this).attr('selected', 'selected');
								isSelected = true;
							}
						}
					});
				}
			}
			else {
				jQuery('#interfaceid').css('display', 'none');
				jQuery('#interface_not_defined').css('display', 'inline');
			}
		}
		else {
			jQuery('#interfaceid').css('display', 'inline');
			jQuery('#interface_not_defined').css('display', 'none');

			jQuery('#interface_row option').each(function() {
				jQuery(this).css('display', 'block');
			});
		}
	}

	function displayKeyButton() {
		var interfaceType = itemTypeInterface(parseInt(jQuery('#type').val()));
		switch (interfaceType) {
			case 2: // INTERFACE_TYPE_SNMP
			case 3: // INTERFACE_TYPE_IPMI
				jQuery('#keyButton').attr('disabled', 'disabled');
				break;
			default:
				jQuery('#keyButton').removeAttr('disabled');
		}
	}

	function setAuthTypeLabel() {
		if (jQuery('#authtype').val() == 1) {
			jQuery('#row_password label').html('<?php echo _('Key passphrase'); ?>');
		}
		else {
			jQuery('#row_password label').html('<?php echo _('Password'); ?>');
		}
	}

	jQuery(document).ready(function() {
		jQuery('#type').bind('change', function() {
			organizeInterfaces();
			displayKeyButton();
		});
		organizeInterfaces();
		displayKeyButton();

		jQuery('#authtype').bind('change', function() {
			setAuthTypeLabel();
		});
		setAuthTypeLabel();
	});
</script>
