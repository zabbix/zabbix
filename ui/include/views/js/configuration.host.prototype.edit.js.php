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


/**
 * @var CView $this
 */
?>

<script type="text/x-jquery-tmpl" id="groupPrototypeRow">
	<tr class="form_row">
		<td>
			<input name="group_prototypes[#{i}][name]" type="text" value="#{name}" style="width: <?= ZBX_TEXTAREA_STANDARD_WIDTH ?>px" placeholder="{#MACRO}" maxlength="255" />
		</td>
		<td class="<?= ZBX_STYLE_NOWRAP ?>">
			<button class="<?= ZBX_STYLE_BTN_LINK ?> group-prototype-remove" type="button" name="remove"><?= _('Remove') ?></button>
			<input type="hidden" name="group_prototypes[#{i}][group_prototypeid]" value="#{group_prototypeid}" />
		</td>
	</tr>
</script>

<script type="text/javascript">
	class InterfaceSourceSwitcher extends CBaseComponent {
		constructor(target, source_input, add_button, data) {
			super(target);

			this._source_input = source_input;
			this._add_button = add_button;
			this._data = data;

			this._target_clone = this._target.cloneNode(true);

			this.register_events();
		}

		register_events() {
			switch (this.getSourceValue()) {
				case '<?= HOST_PROT_INTERFACES_INHERIT ?>':
					this.initInherit();
					this._target.customInterfaces = null;
					break;
				case '<?= HOST_PROT_INTERFACES_CUSTOM ?>':
					this.initCustom();
					this._target.inheritInterfaces = null;
					break;
			}

			this._source_input.addEventListener('change', () => {
				this.switchTo(this.getSourceValue());
			});
		}

		getSourceValue() {
			return this._source_input.querySelector('input[name=custom_interfaces]:checked').value;
		}

		initInherit() {
			const hostInterfaceManagerInherit = new HostInterfaceManager(this._data.inherited_interfaces,
				JSON.parse(this._data.interface_manager_options)
			);
			hostInterfaceManagerInherit.setAllowEmptyMessage(!this._data.parent_is_template);
			hostInterfaceManagerInherit.render();
			hostInterfaceManagerInherit.makeReadonly();
		}

		initCustom() {
			// This is in global space, as Add functions uses it.
			window.hostInterfaceManager = new HostInterfaceManager(this._data.custom_interfaces,
				JSON.parse(this._data.interface_manager_options)
			);
			hostInterfaceManager.render();

			if (this._data.is_templated) {
				hostInterfaceManager.makeReadonly();
			}
		}

		switchTo(source) {
			switch (source) {
				case '<?= HOST_PROT_INTERFACES_INHERIT ?>':
					this._add_button.style.display = 'none';

					if (!('inheritInterfaces' in this._target)) {
						// Do nothing.
					}
					else if (this._target.inheritInterfaces === null) {
						this._target.inheritInterfaces = this._target_clone;
						this._target_clone = null;
						this.switchToInherit();
						this.initInherit();
					}
					else {
						this.switchToInherit();
					}
					break;
				case '<?= HOST_PROT_INTERFACES_CUSTOM ?>':
					if (!('customInterfaces' in this._target)) {
						// Do nothing.
					}
					else if (this._target.customInterfaces === null) {
						this._target.customInterfaces = this._target_clone;
						this._target_clone = null;
						this.switchToCustom();
						this.initCustom();
					}
					else {
						this.switchToCustom();
					}

					this._add_button.style.display = 'inline-block';

					break;
			}
		}

		switchToInherit() {
			var obj_inherit = this._target.inheritInterfaces;
			obj_inherit.customInterfaces = this._target;

			this._target.replaceWith(obj_inherit);
			this._target = obj_inherit;
		}

		switchToCustom() {
			var obj_custom = this._target.customInterfaces;
			obj_custom.inheritInterfaces = this._target;

			this._target.replaceWith(obj_custom);
			this._target = obj_custom;
		}
	}

	function addGroupPrototypeRow(groupPrototype) {
		var addButton = jQuery('#group_prototype_add');

		var rowTemplate = new Template(jQuery('#groupPrototypeRow').html());
		groupPrototype.i = addButton.data('group-prototype-count');
		jQuery('#row_new_group_prototype').before(rowTemplate.evaluate(groupPrototype));

		addButton.data('group-prototype-count', addButton.data('group-prototype-count') + 1);
	}

	jQuery(function() {
		'use strict';

		const interface_source_switcher = new InterfaceSourceSwitcher(
			document.getElementById('interfaces-table'),
			document.getElementById('custom_interfaces'),
			document.getElementById('interface-add'),
			<?= json_encode([
				'parent_is_template' => $parentHost['status'] == HOST_STATUS_TEMPLATE,
				'is_templated' => $hostPrototype['templateid'] != 0,
				'inherited_interfaces' => array_values($parentHost['interfaces']),
				'custom_interfaces' => array_values($hostPrototype['interfaces']),
				'interface_manager_options' => json_encode([
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
				])
			]) ?>
		);

		jQuery('#group_prototype_add')
			.data('group-prototype-count', jQuery('#tbl_group_prototypes').find('.group-prototype-remove').length)
			.click(function() {
				addGroupPrototypeRow({})
			});

		jQuery('#tbl_group_prototypes').on('click', '.group-prototype-remove', function() {
			jQuery(this).closest('.form_row').remove();
		});

		<?php if (!$hostPrototype['groupPrototypes']): ?>
			addGroupPrototypeRow({'name': '', 'group_prototypeid': ''});
		<?php endif ?>
		<?php foreach ($hostPrototype['groupPrototypes'] as $i => $groupPrototype): ?>
			addGroupPrototypeRow(<?= json_encode([
				'name' => $groupPrototype['name'],
				'group_prototypeid' => isset($groupPrototype['group_prototypeid']) ? $groupPrototype['group_prototypeid'] : null
			]) ?>);
		<?php endforeach ?>

		<?php if ($hostPrototype['templateid']): ?>
			jQuery('#tbl_group_prototypes').find('input').prop('readonly', true);
			jQuery('#tbl_group_prototypes').find('button').prop('disabled', true);
		<?php endif ?>

		jQuery('#tls_connect, #tls_in_psk, #tls_in_cert').change(function() {
			jQuery('#tls_issuer, #tls_subject').closest('li').toggle(jQuery('#tls_in_cert').is(':checked')
					|| jQuery('input[name=tls_connect]:checked').val() == <?= HOST_ENCRYPTION_CERTIFICATE ?>);

			jQuery('#tls_psk, #tls_psk_identity, .tls_psk').closest('li').toggle(jQuery('#tls_in_psk').is(':checked')
					|| jQuery('input[name=tls_connect]:checked').val() == <?= HOST_ENCRYPTION_PSK ?>);
		});

		jQuery('input[name=inventory_mode]').click(function() {
			// Action depending on which button was clicked.
			const $inventory_fields = jQuery('#inventorylist :input:gt(2)');

			switch (this.value) {
				case '<?= HOST_INVENTORY_DISABLED ?>':
					$inventory_fields.prop('disabled', true);
					jQuery('.populating_item').hide();
					break;
				case '<?= HOST_INVENTORY_MANUAL ?>':
					$inventory_fields.prop('disabled', false);
					jQuery('.populating_item').hide();
					break;
				case '<?= HOST_INVENTORY_AUTOMATIC ?>':
					$inventory_fields.prop('disabled', false);
					$inventory_fields.filter('.linked_to_item').prop('disabled', true);
					jQuery('.populating_item').show();
					break;
			}
		});

		// Refresh field visibility on document load.
		let tls_accept = jQuery('#tls_accept').val();

		if ((tls_accept & <?= HOST_ENCRYPTION_NONE ?>) == <?= HOST_ENCRYPTION_NONE ?>) {
			jQuery('#tls_in_none').prop('checked', true);
		}
		if ((tls_accept & <?= HOST_ENCRYPTION_PSK ?>) == <?= HOST_ENCRYPTION_PSK ?>) {
			jQuery('#tls_in_psk').prop('checked', true);
		}
		if ((tls_accept & <?= HOST_ENCRYPTION_CERTIFICATE ?>) == <?= HOST_ENCRYPTION_CERTIFICATE ?>) {
			jQuery('#tls_in_cert').prop('checked', true);
		}

		jQuery('input[name=tls_connect]').trigger('change');
	});
</script>
