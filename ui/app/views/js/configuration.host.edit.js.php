<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/
?>


<?php if (false): ?><script><?php endif; // Cheat for code highlight. ?>
'use strict';

jQuery(document).ready(function() {
	'use strict';

	jQuery('#tls_connect, #tls_in_psk, #tls_in_cert').change(function() {
		// If certificate is selected or checked.
		if (jQuery('input[name=tls_connect]:checked').val() == <?= HOST_ENCRYPTION_CERTIFICATE ?>
				|| jQuery('#tls_in_cert').is(':checked')) {
			jQuery('#tls_issuer, #tls_subject').closest('li').show();
		}
		else {
			jQuery('#tls_issuer, #tls_subject').closest('li').hide();
		}

		// If PSK is selected or checked.
		if (jQuery('input[name=tls_connect]:checked').val() == <?= HOST_ENCRYPTION_PSK ?>
				|| jQuery('#tls_in_psk').is(':checked')) {
			jQuery('#tls_psk, #tls_psk_identity, .tls_psk').closest('li').show();
		}
		else {
			jQuery('#tls_psk, #tls_psk_identity, .tls_psk').closest('li').hide();
		}
	});

	// radio button of inventory modes was clicked
	jQuery('input[name=inventory_mode]').click(function() {
		// action depending on which button was clicked
		var inventoryFields = jQuery('#inventorylist :input:gt(2)');

		switch (jQuery(this).val()) {
			case '<?= HOST_INVENTORY_DISABLED ?>':
				inventoryFields.prop('disabled', true);
				jQuery('.populating_item').hide();
				break;
			case '<?= HOST_INVENTORY_MANUAL ?>':
				inventoryFields.prop('disabled', false);
				jQuery('.populating_item').hide();
				break;
			case '<?= HOST_INVENTORY_AUTOMATIC ?>':
				inventoryFields.prop('disabled', false);
				inventoryFields.filter('.linked_to_item').prop('disabled', true);
				jQuery('.populating_item').show();
				break;
		}
	});

	// Refresh field visibility on document load.
	if ((jQuery('#tls_accept').val() & <?= HOST_ENCRYPTION_NONE ?>) == <?= HOST_ENCRYPTION_NONE ?>) {
		jQuery('#tls_in_none').prop('checked', true);
	}
	if ((jQuery('#tls_accept').val() & <?= HOST_ENCRYPTION_PSK ?>) == <?= HOST_ENCRYPTION_PSK ?>) {
		jQuery('#tls_in_psk').prop('checked', true);
	}
	if ((jQuery('#tls_accept').val() & <?= HOST_ENCRYPTION_CERTIFICATE ?>) == <?= HOST_ENCRYPTION_CERTIFICATE ?>) {
		jQuery('#tls_in_cert').prop('checked', true);
	}

	jQuery('input[name=tls_connect]').trigger('change');

	window.hostInterfaceManager = new HostInterfaceManager(
		<?= json_encode($data['host']['interfaces']) ?>,
		<?= json_encode([
			'interface_types' => [
				'AGENT' => INTERFACE_TYPE_AGENT,
				'SNMP' => INTERFACE_TYPE_SNMP,
				'JMX' => INTERFACE_TYPE_JMX,
				'IPMI' => INTERFACE_TYPE_IPMI
			],
			'interface_properties' => [
				'SNMP_V1' => SNMP_V1,
				'SNMP_V2C' => SNMP_V2C,
				'SNMP_V3' => SNMP_V3,
				'BULK_ENABLED' => SNMP_BULK_ENABLED,
				'INTERFACE_PRIMARY' => INTERFACE_PRIMARY,
				'INTERFACE_SECONDARY' => INTERFACE_SECONDARY,
				'INTERFACE_USE_IP' => INTERFACE_USE_IP,
				'SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV' => ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV,
				'SNMPV3_SECURITYLEVEL_AUTHNOPRIV' => ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV,
				'SNMPV3_SECURITYLEVEL_AUTHNOPRIV' => ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV,
				'SNMPV3_AUTHPROTOCOL_MD5' => ITEM_SNMPV3_AUTHPROTOCOL_MD5,
				'SNMPV3_PRIVPROTOCOL_DES' => ITEM_SNMPV3_PRIVPROTOCOL_DES
			],
			'styles' => [
				'ZBX_STYLE_HOST_INTERFACE_BTN_REMOVE' => ZBX_STYLE_HOST_INTERFACE_BTN_REMOVE,
				'ZBX_STYLE_HOST_INTERFACE_CONTAINER' => ZBX_STYLE_HOST_INTERFACE_CONTAINER,
				'ZBX_STYLE_HOST_INTERFACE_CONTAINER_HEADER' => ZBX_STYLE_HOST_INTERFACE_CONTAINER_HEADER,
				'ZBX_STYLE_HOST_INTERFACE_CELL_DETAILS' => ZBX_STYLE_HOST_INTERFACE_CELL_DETAILS,
				'ZBX_STYLE_HOST_INTERFACE_BTN_MAIN_INTERFACE' => ZBX_STYLE_HOST_INTERFACE_BTN_MAIN_INTERFACE,
				'ZBX_STYLE_HOST_INTERFACE_CELL_USEIP' => ZBX_STYLE_HOST_INTERFACE_CELL_USEIP,
				'ZBX_STYLE_HOST_INTERFACE_BTN_TOGGLE' => ZBX_STYLE_HOST_INTERFACE_BTN_TOGGLE,
				'ZBX_STYLE_LIST_ACCORDION_ITEM' => ZBX_STYLE_LIST_ACCORDION_ITEM,
				'ZBX_STYLE_LIST_ACCORDION_ITEM_OPENED' => ZBX_STYLE_LIST_ACCORDION_ITEM_OPENED,
				'ZBX_STYLE_HOST_INTERFACE_INPUT_EXPAND' => ZBX_STYLE_HOST_INTERFACE_INPUT_EXPAND,
				'ZBX_STYLE_HOST_INTERFACE_ROW' => ZBX_STYLE_HOST_INTERFACE_ROW,
				'ZBX_STYLE_HOST_INTERFACE_BTN_REMOVE' => ZBX_STYLE_HOST_INTERFACE_BTN_REMOVE,
			],
			'templates' => [
				'interface_row' => (new CPartial('configuration.host.interface.row'))->getOutput(),
				'no_interface_msg' => (new CDiv(_('No interfaces are defined.')))
					->addClass(ZBX_STYLE_GREY)
					->toString()
			]
		]) ?>
	);

	hostInterfaceManager.render();

	<?php if ((int) $data['host']['flags'] === ZBX_FLAG_DISCOVERY_CREATED): ?>
		hostInterfaceManager.makeReadonly();
	<?php endif; ?>
});
