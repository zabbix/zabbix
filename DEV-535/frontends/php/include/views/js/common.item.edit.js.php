<?php

/*
 * Visibility
 */
$this->data['typeVisibility'] = array();
$i = 0;
foreach ($this->data['delay_flex'] as $delayFlex) {
	if (!isset($delayFlex['delay']) && !isset($delayFlex['period'])) {
		continue;
	}
	foreach ($this->data['types'] as $type => $label) {
		if ($type == ITEM_TYPE_TRAPPER || $type == ITEM_TYPE_ZABBIX_ACTIVE || $type == ITEM_TYPE_SNMPTRAP) {
			continue;
		}
		zbx_subarray_push($this->data['typeVisibility'], $type, 'delay_flex['.$i.'][delay]');
		zbx_subarray_push($this->data['typeVisibility'], $type, 'delay_flex['.$i.'][period]');
	}
	$i++;
	if ($i == 7) {
		break;
	}
}
if (!empty($this->data['interfaces'])) {
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_ZABBIX, 'interface_row');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_ZABBIX, 'interfaceid');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SIMPLE, 'interface_row');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SIMPLE, 'interfaceid');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV1, 'interface_row');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV1, 'interfaceid');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV2C, 'interface_row');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV2C, 'interfaceid');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV3, 'interface_row');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV3, 'interfaceid');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_EXTERNAL, 'interface_row');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_EXTERNAL, 'interfaceid');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_IPMI, 'interface_row');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_IPMI, 'interfaceid');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SSH, 'interface_row');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SSH, 'interfaceid');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_TELNET, 'interface_row');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_TELNET, 'interfaceid');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_JMX, 'interface_row');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_JMX, 'interfaceid');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPTRAP, 'interface_row');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPTRAP, 'interfaceid');
}
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SIMPLE, 'row_username');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SIMPLE, 'username');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SIMPLE, 'row_password');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SIMPLE, 'password');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV1, 'snmp_oid');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV2C, 'snmp_oid');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV3, 'snmp_oid');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV1, 'row_snmp_oid');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV2C, 'row_snmp_oid');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV3, 'row_snmp_oid');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV1, 'snmp_community');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV2C, 'snmp_community');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV1, 'row_snmp_community');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV2C, 'row_snmp_community');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV3, 'snmpv3_contextname');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV3, 'row_snmpv3_contextname');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV3, 'snmpv3_securityname');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV3, 'row_snmpv3_securityname');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV3, 'snmpv3_securitylevel');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV3, 'row_snmpv3_securitylevel');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV1, 'port');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV2C, 'port');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV3, 'port');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV1, 'row_port');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV2C, 'row_port');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV3, 'row_port');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_IPMI, 'ipmi_sensor');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_IPMI, 'row_ipmi_sensor');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SSH, 'authtype');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SSH, 'row_authtype');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SSH, 'username');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SSH, 'row_username');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_TELNET, 'username');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_TELNET, 'row_username');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_DB_MONITOR, 'username');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_DB_MONITOR, 'row_username');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_JMX, 'username');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_JMX, 'row_username');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SSH, 'password');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SSH, 'row_password');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_TELNET, 'password');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_TELNET, 'row_password');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_DB_MONITOR, 'password');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_DB_MONITOR, 'row_password');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_JMX, 'password');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_JMX, 'row_password');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SSH, 'label_executed_script');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_TELNET, 'label_executed_script');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_DB_MONITOR, 'label_params');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_CALCULATED, 'label_formula');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SSH, 'params_script');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SSH, 'row_params');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_TELNET, 'params_script');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_TELNET, 'row_params');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_DB_MONITOR, 'params_dbmonitor');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_DB_MONITOR, 'row_params');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_CALCULATED, 'params_calculted');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_CALCULATED, 'row_params');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_TRAPPER, 'trapper_hosts');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_TRAPPER, 'row_trapper_hosts');
foreach ($this->data['types'] as $type => $label) {
	switch ($type) {
		case ITEM_TYPE_DB_MONITOR:
			zbx_subarray_push($this->data['typeVisibility'], $type, array('id' => 'key', 'defaultValue' => ZBX_DEFAULT_KEY_DB_MONITOR));
			break;
		case ITEM_TYPE_SSH:
			zbx_subarray_push($this->data['typeVisibility'], $type, array('id' => 'key', 'defaultValue' => ZBX_DEFAULT_KEY_SSH));
			break;
		case ITEM_TYPE_TELNET:
			zbx_subarray_push($this->data['typeVisibility'], $type, array('id' => 'key', 'defaultValue' => ZBX_DEFAULT_KEY_TELNET));
			break;
		case ITEM_TYPE_JMX:
			zbx_subarray_push($this->data['typeVisibility'], $type, array('id' => 'key', 'defaultValue' => ZBX_DEFAULT_KEY_JMX));
			break;
		default:
			zbx_subarray_push($this->data['typeVisibility'], $type, array('id' => 'key', 'defaultValue' => ''));
	}
}
foreach ($this->data['types'] as $type => $label) {
	if ($type == ITEM_TYPE_TRAPPER || $type == ITEM_TYPE_ZABBIX_ACTIVE || $type == ITEM_TYPE_SNMPTRAP) {
		continue;
	}
	zbx_subarray_push($this->data['typeVisibility'], $type, 'row_flex_intervals');
	zbx_subarray_push($this->data['typeVisibility'], $type, 'row_new_delay_flex');
	zbx_subarray_push($this->data['typeVisibility'], $type, 'new_delay_flex[delay]');
	zbx_subarray_push($this->data['typeVisibility'], $type, 'new_delay_flex[period]');
	zbx_subarray_push($this->data['typeVisibility'], $type, 'add_delay_flex');
}
foreach ($this->data['types'] as $type => $label) {
	if ($type == ITEM_TYPE_TRAPPER || $type == ITEM_TYPE_SNMPTRAP) {
		continue;
	}
	zbx_subarray_push($this->data['typeVisibility'], $type, 'delay');
	zbx_subarray_push($this->data['typeVisibility'], $type, 'row_delay');
}

// disable dropdown items for calculated and aggregate items
foreach (array(ITEM_TYPE_CALCULATED, ITEM_TYPE_AGGREGATE) as $type) {
	// set to disable character, log and text items in value type
	zbx_subarray_push($this->data['typeDisable'], $type, array(ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_TEXT), 'value_type');

	// disable octal, hexadecimal and boolean items in data_type; Necessary for Numeric (unsigned) value type only
	zbx_subarray_push($this->data['typeDisable'], $type, array(ITEM_DATA_TYPE_OCTAL, ITEM_DATA_TYPE_HEXADECIMAL, ITEM_DATA_TYPE_BOOLEAN), 'data_type');
}

$this->data['securityLevelVisibility'] = array();
zbx_subarray_push($this->data['securityLevelVisibility'], ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV, 'snmpv3_authprotocol');
zbx_subarray_push($this->data['securityLevelVisibility'], ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV, 'row_snmpv3_authprotocol');
zbx_subarray_push($this->data['securityLevelVisibility'], ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV, 'snmpv3_authpassphrase');
zbx_subarray_push($this->data['securityLevelVisibility'], ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV, 'row_snmpv3_authpassphrase');
zbx_subarray_push($this->data['securityLevelVisibility'], ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV, 'snmpv3_authprotocol');
zbx_subarray_push($this->data['securityLevelVisibility'], ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV, 'row_snmpv3_authprotocol');
zbx_subarray_push($this->data['securityLevelVisibility'], ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV, 'snmpv3_authpassphrase');
zbx_subarray_push($this->data['securityLevelVisibility'], ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV, 'row_snmpv3_authpassphrase');
zbx_subarray_push($this->data['securityLevelVisibility'], ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV, 'snmpv3_privprotocol');
zbx_subarray_push($this->data['securityLevelVisibility'], ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV, 'row_snmpv3_privprotocol');
zbx_subarray_push($this->data['securityLevelVisibility'], ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV, 'snmpv3_privpassphrase');
zbx_subarray_push($this->data['securityLevelVisibility'], ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV, 'row_snmpv3_privpassphrase');

$this->data['authTypeVisibility'] = array();
zbx_subarray_push($this->data['authTypeVisibility'], ITEM_AUTHTYPE_PUBLICKEY, 'publickey');
zbx_subarray_push($this->data['authTypeVisibility'], ITEM_AUTHTYPE_PUBLICKEY, 'row_publickey');
zbx_subarray_push($this->data['authTypeVisibility'], ITEM_AUTHTYPE_PUBLICKEY, 'privatekey');
zbx_subarray_push($this->data['authTypeVisibility'], ITEM_AUTHTYPE_PUBLICKEY, 'row_privatekey');

?>

<script type="text/javascript">
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
		<?php
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

		jQuery('#type')
			.change(function() {
				// update the interface select with each item type change
				var itemIntefaceTypes = <?php echo CJs::encodeJson(itemTypeInterface()); ?>;
				organizeInterfaces(itemIntefaceTypes[parseInt(jQuery(this).val())]);

				setAuthTypeLabel();
			})
			.trigger('change');

		jQuery('#authtype').bind('change', function() {
			setAuthTypeLabel();
		});

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
