<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
<script type="text/x-jquery-tmpl" id="host-interface-row-tmpl">
<div class="<?= ZBX_STYLE_HOST_INTERFACE_ROW ?> <?= ZBX_STYLE_LIST_ACCORDION_ITEM ?> <?= ZBX_STYLE_LIST_ACCORDION_ITEM_CLOSED ?>" id="interface_row_#{iface.interfaceid}" data-type="#{iface.type}" data-interfaceid="#{iface.interfaceid}">
	<input type="hidden" name="interfaces[#{iface.interfaceid}][items]" value="#{iface.items}" />
	<input type="hidden" name="interfaces[#{iface.interfaceid}][locked]" value="#{iface.locked}" />
	<input type="hidden" name="interfaces[#{iface.interfaceid}][isNew]" value="#{iface.isNew}" />
	<input type="hidden" name="interfaces[#{iface.interfaceid}][interfaceid]" value="#{iface.interfaceid}" />
	<input type="hidden" id="interface_type_#{iface.interfaceid}" name="interfaces[#{iface.interfaceid}][type]" value="#{iface.type}" />

	<div class="<?= ZBX_STYLE_HOST_INTERFACE_CELL ?> <?= ZBX_STYLE_HOST_INTERFACE_CELL_ICON ?>">
		<button type="button" class="<?= ZBX_STYLE_HOST_INTERFACE_BTN_TOGGLE ?>"></button>
	</div>
	<div class="<?= ZBX_STYLE_HOST_INTERFACE_CELL ?> <?= ZBX_STYLE_HOST_INTERFACE_CELL_TYPE ?>">
		#{iface.type_name}
	</div>
	<div class="<?= ZBX_STYLE_HOST_INTERFACE_CELL ?>">
		<?= (new CTextBox('interfaces[#{iface.interfaceid}][ip]', '#{iface.ip}', false, DB::getFieldLength('interface', 'ip')))
				->addClass(ZBX_STYLE_HOST_INTERFACE_INPUT_EXPAND)
				->setWidth(ZBX_TEXTAREA_INTERFACE_IP_WIDTH)
		?>
	</div>
	<div class="<?= ZBX_STYLE_HOST_INTERFACE_CELL ?>">
		<?= (new CTextBox('interfaces[#{iface.interfaceid}][dns]', '#{iface.dns}', false, DB::getFieldLength('interface', 'dns')))
				->addClass(ZBX_STYLE_HOST_INTERFACE_INPUT_EXPAND)
				->setWidth(ZBX_TEXTAREA_INTERFACE_DNS_WIDTH)
		?>
	</div>
	<div class="<?= ZBX_STYLE_HOST_INTERFACE_CELL ?>">
		<?= (new CRadioButtonList('interfaces[#{iface.interfaceid}][useip]', null))
				->addValue(_('IP'), INTERFACE_USE_IP, 'interfaces[#{iface.interfaceid}][useip]['.INTERFACE_USE_IP.']')
				->addValue(_('DNS'), INTERFACE_USE_DNS, 'interfaces[#{iface.interfaceid}][useip]['.INTERFACE_USE_DNS.']')
				->addClass(ZBX_STYLE_HOST_INTERFACE_CELL_USEIP.' '.ZBX_STYLE_HOST_INTERFACE_INPUT_EXPAND)
				->setModern(true)
		?>
	</div>
	<div class="<?= ZBX_STYLE_HOST_INTERFACE_CELL ?>">
		<?= (new CTextBox('interfaces[#{iface.interfaceid}][port]', '#{iface.port}', false, DB::getFieldLength('interface', 'port')))
				->setWidth(ZBX_TEXTAREA_INTERFACE_PORT_WIDTH)
				->addClass(ZBX_STYLE_HOST_INTERFACE_INPUT_EXPAND)
				->setAriaRequired()
		?>
	</div>
	<div class="<?= ZBX_STYLE_HOST_INTERFACE_CELL ?>">
		<input type="radio" class="<?= ZBX_STYLE_CHECKBOX_RADIO ?> <?= ZBX_STYLE_HOST_INTERFACE_BTN_MAIN_INTERFACE ?> <?= ZBX_STYLE_HOST_INTERFACE_INPUT_EXPAND ?>" id="interface_main_#{iface.interfaceid}" name="mainInterfaces[#{iface.type}]" value="#{iface.interfaceid}">
		<label class="checkboxLikeLabel" for="interface_main_#{iface.interfaceid}" style="height: 16px; width: 16px;"><span></span></label>
	</div>
	<div class="<?= ZBX_STYLE_HOST_INTERFACE_CELL ?>">
		<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?> <?= ZBX_STYLE_HOST_INTERFACE_BTN_REMOVE ?>"><?= _('Remove') ?></button>
	</div>
	<div class="<?= ZBX_STYLE_HOST_INTERFACE_CELL ?> <?= ZBX_STYLE_HOST_INTERFACE_CELL_DETAILS ?> <?= ZBX_STYLE_LIST_ACCORDION_ITEM_BODY ?>">
		<?= (new CFormList('snmp_details_#{iface.interfaceid}'))
				->cleanItems()
				->addRow((new CLabel(_('SNMP version'), 'interfaces[#{iface.interfaceid}][details][version]'))->setAsteriskMark(),
					new CComboBox('interfaces[#{iface.interfaceid}][details][version]', SNMP_V2C, null, [SNMP_V1 => _('SNMPv1'), SNMP_V2C => _('SNMPv2'), SNMP_V3 => _('SNMPv3')])
				)
				->addRow((new CLabel(_('SNMP community'), 'interfaces[#{iface.interfaceid}][details][community]'))->setAsteriskMark(),
					(new CTextBox('interfaces[#{iface.interfaceid}][details][community]', '#{iface.details.community}', false, DB::getFieldLength('interface_snmp', 'community')))
						->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
						->setAriaRequired(),
					'row_snmp_community_#{iface.interfaceid}'
				)
				->addRow(new CLabel(_('Context name'), 'interfaces[#{iface.interfaceid}][details][contextname]'),
					(new CTextBox('interfaces[#{iface.interfaceid}][details][contextname]', '#{iface.details.contextname}', false, DB::getFieldLength('interface_snmp', 'contextname')))
						->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
					'row_snmpv3_contextname_#{iface.interfaceid}'
				)
				->addRow(new CLabel(_('Security name'), 'interfaces[#{iface.interfaceid}][details][securityname]'),
					(new CTextBox('interfaces[#{iface.interfaceid}][details][securityname]', '#{iface.details.securityname}', false, DB::getFieldLength('interface_snmp', 'securityname')))
						->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
					'row_snmpv3_securityname_#{iface.interfaceid}'
				)
				->addRow(new CLabel(_('Security level'), 'interfaces[#{iface.interfaceid}][details][securitylevel]'),
					new CComboBox('interfaces[#{iface.interfaceid}][details][securitylevel]', ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV, null, [
						ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV => 'noAuthNoPriv',
						ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV => 'authNoPriv',
						ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV => 'authPriv'
					]),
					'row_snmpv3_securitylevel_#{iface.interfaceid}'
				)
				->addRow(new CLabel(_('Authentication protocol'), 'interfaces[#{iface.interfaceid}][details][authprotocol]'),
					(new CRadioButtonList('interfaces[#{iface.interfaceid}][details][authprotocol]', ITEM_AUTHPROTOCOL_MD5))
						->addValue(_('MD5'), ITEM_AUTHPROTOCOL_MD5, 'snmpv3_authprotocol_#{iface.interfaceid}_'.ITEM_AUTHPROTOCOL_MD5)
						->addValue(_('SHA'), ITEM_AUTHPROTOCOL_SHA, 'snmpv3_authprotocol_#{iface.interfaceid}_'.ITEM_AUTHPROTOCOL_SHA)
						->setModern(true),
					'row_snmpv3_authprotocol_#{iface.interfaceid}'
				)
				->addRow(new CLabel(_('Authentication passphrase'), 'interfaces[#{iface.interfaceid}][details][authpassphrase]'),
					(new CTextBox('interfaces[#{iface.interfaceid}][details][authpassphrase]', '#{iface.details.authpassphrase}', false, DB::getFieldLength('interface_snmp', 'authpassphrase')))
						->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
					'row_snmpv3_authpassphrase_#{iface.interfaceid}'
				)
				->addRow(new CLabel(_('Privacy protocol'), 'interfaces[#{iface.interfaceid}][details][privprotocol]'),
					(new CRadioButtonList('interfaces[#{iface.interfaceid}][details][privprotocol]', ITEM_PRIVPROTOCOL_DES))
						->addValue(_('DES'), ITEM_PRIVPROTOCOL_DES, 'snmpv3_privprotocol_#{iface.interfaceid}_'.ITEM_PRIVPROTOCOL_DES)
						->addValue(_('AES'), ITEM_PRIVPROTOCOL_AES, 'snmpv3_privprotocol_#{iface.interfaceid}_'.ITEM_PRIVPROTOCOL_AES)
						->setModern(true),
					'row_snmpv3_privprotocol_#{iface.interfaceid}'
				)
				->addRow((new CLabel(_('Privacy passphrase'), 'interfaces[#{iface.interfaceid}][details][privpassphrase]'))->setAsteriskMark(),
					(new CTextBox('interfaces[#{iface.interfaceid}][details][privpassphrase]', '#{iface.details.privpassphrase}', false, DB::getFieldLength('interface_snmp', 'privpassphrase')))
						->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
						->setAriaRequired(),
					'row_snmpv3_privpassphrase_#{iface.interfaceid}'
				)
				->addRow('', (new CCheckBox('interfaces[#{iface.interfaceid}][details][bulk]', SNMP_BULK_ENABLED))->setLabel(_('Use bulk requests'), 'interfaces[#{iface.interfaceid}][details][bulk]'));
		?>
	</div>
</div>
</script>

<script type="text/javascript">
	'use strict';

	/**
	 * Class to manage interface rows.
	 */
	var hostInterfaceManager = (function($) {
		var TEMPLATE = new Template(document.querySelector('#host-interface-row-tmpl').innerHTML),
			DEFAULT_PORTS = {
				agent: 10050,
				snmp: 161,
				jmx: 12345,
				ipmi: 623
			},
			AGENT_CONTAINER_ID = '#agentInterfaces',
			SNMP_CONTAINER_ID = '#SNMPInterfaces',
			JMX_CONTAINER_ID = '#JMXInterfaces',
			IPMI_CONTAINER_ID = '#IPMIInterfaces',
			data = {};

		var helper = {
			getContainerId: function(type) {
				switch (type) {
					case '<?= INTERFACE_TYPE_AGENT ?>':
						return AGENT_CONTAINER_ID;
					case '<?= INTERFACE_TYPE_SNMP ?>':
						return SNMP_CONTAINER_ID;
					case '<?= INTERFACE_TYPE_JMX ?>':
						return JMX_CONTAINER_ID;
					case '<?= INTERFACE_TYPE_IPMI ?>':
						return IPMI_CONTAINER_ID;
					default:
						throw new Error('Unknown host interface type.');
				}
			},
			getTypeByName: function(typeName) {
				switch (typeName) {
					case 'agent':
						return '<?= INTERFACE_TYPE_AGENT ?>';
					case 'snmp':
						return '<?= INTERFACE_TYPE_SNMP ?>';
					case 'jmx':
						return '<?= INTERFACE_TYPE_JMX ?>';
					case 'ipmi':
						return '<?= INTERFACE_TYPE_IPMI ?>';
					default:
						throw new Error('Unknown host interface type name.');
				}
			},
			getNameByType: function(type) {
				switch (type) {
					case '<?= INTERFACE_TYPE_AGENT ?>':
						return 'Agent';
					case '<?= INTERFACE_TYPE_SNMP ?>':
						return 'SNMP';
					case '<?= INTERFACE_TYPE_JMX ?>':
						return 'JMX';
					case '<?= INTERFACE_TYPE_IPMI ?>':
						return 'IPMI';
					default:
						throw new Error('Unknown host interface type.');
				}
			},
			setSnmpFields: function(elem, iface) {
				if (iface.type != '<?= INTERFACE_TYPE_SNMP ?>') {
					$('.<?= ZBX_STYLE_HOST_INTERFACE_CELL_DETAILS ?>', elem).remove();
					return false;
				}

				$('#interfaces_' + iface.interfaceid + '_details_version').val(iface.details.version);
				$('#interfaces_' + iface.interfaceid + '_details_securitylevel').val(iface.details.securitylevel);

				if (iface.details.privprotocol == '<?= ITEM_PRIVPROTOCOL_AES ?>') {
					$('#snmpv3_privprotocol_' + iface.interfaceid + '_1').prop('checked', true);
				}
				if (iface.details.authprotocol == '<?= ITEM_AUTHPROTOCOL_SHA ?>') {
					$('#snmpv3_authprotocol_' + iface.interfaceid + '_1').prop('checked', true);
				}
				if (iface.details.bulk == '<?= SNMP_BULK_ENABLED ?>') {
					$('#interfaces_' + iface.interfaceid + '_details_bulk').prop('checked', true);
				}

				new CViewSwitcher('interfaces_' + iface.interfaceid + '_details_version', 'change',
					{
						<?= SNMP_V1 ?>: ['row_snmp_community_' + iface.interfaceid],
						<?= SNMP_V2C ?>: ['row_snmp_community_' + iface.interfaceid],
						<?= SNMP_V3 ?>: [
							'row_snmpv3_contextname_' + iface.interfaceid,
							'row_snmpv3_securityname_' + iface.interfaceid,
							'row_snmpv3_securitylevel_' + iface.interfaceid,
							'row_snmpv3_authprotocol_' + iface.interfaceid,
							'row_snmpv3_authpassphrase_' + iface.interfaceid,
							'row_snmpv3_privprotocol_' + iface.interfaceid,
							'row_snmpv3_privpassphrase_' + iface.interfaceid
						]
					}
				);

				$('#interfaces_' + iface.interfaceid + '_details_version').on('change', function() {
					$('#interfaces_' + iface.interfaceid + '_details_securitylevel').off('change');

					if ($(this).val() == '<?= SNMP_V3 ?>') {
						new CViewSwitcher('interfaces_' + iface.interfaceid + '_details_securitylevel', 'change',
							{
								<?= ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV ?>: [],
								<?= ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV ?>: [
									'row_snmpv3_authprotocol_' + iface.interfaceid,
									'row_snmpv3_authpassphrase_' + iface.interfaceid,
								],
								<?= ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV ?>: [
									'row_snmpv3_authprotocol_' + iface.interfaceid,
									'row_snmpv3_authpassphrase_' + iface.interfaceid,
									'row_snmpv3_privprotocol_' + iface.interfaceid,
									'row_snmpv3_privpassphrase_' + iface.interfaceid
								]
							}
						);
					}
				}).trigger('change');
			},
			getNewId: function() {
				var id = 1;

				while (data[id] !== undefined) {
					id++;
				}

				return id;
			},
			getNewData: function(type) {
				return {
					interfaceid: helper.getNewId(),
					isNew: true,
					useip: 1,
					type: helper.getTypeByName(type),
					type_name: helper.getNameByType(helper.getTypeByName(type)),
					port: DEFAULT_PORTS[type],
					ip: '127.0.0.1',
					main: '0',
					details: {
						version: <?= SNMP_V2C ?>,
						bulk: <?= SNMP_BULK_ENABLED ?>,
						securitylevel: <?= ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV ?>,
						authprotocol: <?= ITEM_AUTHPROTOCOL_MD5 ?>,
						privprotocol: <?= ITEM_PRIVPROTOCOL_DES ?>
					}
				};
			},
			getInterfaces: function() {
				var types = {
						'<?= INTERFACE_TYPE_AGENT ?>': {main: null, all: []},
						'<?= INTERFACE_TYPE_SNMP ?>': {main: null, all: []},
						'<?= INTERFACE_TYPE_JMX ?>': {main: null, all: []},
						'<?= INTERFACE_TYPE_IPMI ?>': {main: null, all: []}
					};

				for (var i in data) {
					if (data.hasOwnProperty(i)) {
						var iface = data[i];

						types[iface.type].all.push(i);
						if (iface.main == '1') {
							if (types[iface.type].main !== null) {
								throw new Error('Multiple default interfaces for same type.');
							}

							types[iface.type].main = i;
						}
					}
				}

				return types;
			}
		};

		function setData(new_data) {
			if (typeof new_data  !== 'object') {
				throw new Error('Incorrect data.');
			}

			for (var i in new_data) {
				if (new_data.hasOwnProperty(i)) {
					data[new_data[i]['interfaceid']] = new_data[i];
				}
			}

			return true;
		}

		function render(iface) {
			var container = document.querySelector(helper.getContainerId(iface.type)),
				disabled = (iface.items > 0),
				locked = (iface.locked > 0);

			iface.type_name = helper.getNameByType(iface.type);

			/*
			 * New line break css selector :empty. Trim used to avoid this.
			 * Template added with new line. Because template <script> tag it contain for code readability.
			 */
			$(container).append(TEMPLATE.evaluate({iface: iface}).trim());

			var elem = document.querySelector('#interface_row_' + iface.interfaceid);

			// Select proper use ip radio element.
			$('#interfaces_' + iface.interfaceid + '_useip_' + iface.useip).prop('checked', true);

			if (disabled) {
				$('.<?= ZBX_STYLE_HOST_INTERFACE_BTN_REMOVE ?>', elem).attr('disabled', 'disabled');
			}

			helper.setSnmpFields(elem, iface);

			// Set onclick actions.
			$('.<?= ZBX_STYLE_HOST_INTERFACE_BTN_REMOVE ?>', elem).on('click', function() {
				return remove(iface.interfaceid);
			});
			$('.<?= ZBX_STYLE_HOST_INTERFACE_BTN_MAIN_INTERFACE ?>', elem).on('click', function() {
				return setMainInterface(iface.interfaceid);
			});
			$('.<?= ZBX_STYLE_HOST_INTERFACE_CELL_USEIP ?> input', elem).on('click', function() {
				return setUseIp(elem, $(this).val());
			});

			resetMainInterfaces();

			return true;
		}

		function remove(id) {
			var elem = document.querySelector('#interface_row_' + id);
			if (!elem) {
				return false;
			}

			elem.remove();
			delete data[id];

			resetMainInterfaces();

			return true;
		}

		function addNewDataByTypeName(type) {
			var new_data = helper.getNewData(type),
				data = {};
			data[new_data.interfaceid] = new_data;

			setData(data);
			render(new_data);

			if (new_data.type == <?= INTERFACE_TYPE_SNMP ?>) {
				var index = $('#interface_row_' + new_data.interfaceid).index();
				$(SNMP_CONTAINER_ID).zbx_vertical_accordion('expandNth', index);
			}

			return true;
		}

		function resetMainInterfaces() {
			var interfaces = helper.getInterfaces();

			for (var type in interfaces) {
				if (!interfaces.hasOwnProperty(type)) {
					continue;
				}

				var type_interfaces = interfaces[type];

				if (!type_interfaces.main && type_interfaces.all.length) {
					for (var i = 0; i < type_interfaces.all.length; i++) {
						if (data[type_interfaces.all[i]].main == '<?= INTERFACE_PRIMARY ?>') {
							type_interfaces.main = data[type_interfaces.all[i]].interfaceid;
						}
					}

					if (!type_interfaces.main) {
						type_interfaces.main = type_interfaces.all[0];
						data[type_interfaces.main].main = '<?= INTERFACE_PRIMARY ?>';
					}
				}
			}

			for (var type in interfaces) {
				if (!interfaces.hasOwnProperty(type)) {
					continue;
				}

				type_interfaces = interfaces[type];

				if (type_interfaces.main) {
					$('#interface_main_' + type_interfaces.main).prop('checked', true);
				}
			}

			return true;
		}

		function setMainInterface(id) {
			var interfaces = helper.getInterfaces(),
				type = data[id].type,
				old = interfaces[type].main;

			if (id != old) {
				data[id].main = '1';
				data[old].main = '0';
			}

			return true;
		}

		function setUseIp(elem, use_ip) {
			var interfaceid = $(elem).data('interfaceid');

			data[interfaceid].useip = use_ip;

			$('input', elem)
				.filter('[name$="[ip]"],[name$="[dns]"]')
				.removeAttr('aria-required')
				.filter((use_ip == <?= INTERFACE_USE_IP ?>) ? '[name$="[ip]"]' : '[name$="[dns]"]')
				.attr('aria-required', true);

			return true;
		}

		return {
			addAgent: function() {
				addNewDataByTypeName('agent');
			},
			addSnmp: function() {
				addNewDataByTypeName('snmp');
			},
			addJmx: function() {
				addNewDataByTypeName('jmx');
			},
			addIpmi: function() {
				addNewDataByTypeName('ipmi');
			},
			render: function(data) {
				setData(data);

				for (var i in data) {
					if (data.hasOwnProperty(i)) {
						render(data[i]);
					}
				}

				// Add accordion functionality to SNMP interfaces.
				$(SNMP_CONTAINER_ID).zbx_vertical_accordion({handler: ".<?= ZBX_STYLE_HOST_INTERFACE_BTN_TOGGLE ?>"});
				// Expend first SNMP interface.
				$(SNMP_CONTAINER_ID).zbx_vertical_accordion("expandNth", 0);
				// Add event to expand SNMP interface accordion if focused or clicked on inputs.
				$(SNMP_CONTAINER_ID).on("focus", ".<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>:not(.<?= ZBX_STYLE_LIST_ACCORDION_ITEM_OPENED ?>) .<?= ZBX_STYLE_HOST_INTERFACE_INPUT_EXPAND ?>", function() {
					var index = $(this).closest('.<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>').index();

					$(SNMP_CONTAINER_ID).zbx_vertical_accordion("expandNth", index);
				});
			},
			disableEdit: function() {
				$('.<?= ZBX_STYLE_HOST_INTERFACE_ROW ?>')
					.find('input, select')
					.removeAttr('id')
					.removeAttr('name');
				$('.<?= ZBX_STYLE_HOST_INTERFACE_ROW ?>').find('input[type="text"]').prop('readonly', true);
				$('.<?= ZBX_STYLE_HOST_INTERFACE_ROW ?>').find('input[type="radio"], input[type="checkbox"], select').prop('disabled', true);
				$('.<?= ZBX_STYLE_HOST_INTERFACE_ROW ?>').find('.<?= ZBX_STYLE_HOST_INTERFACE_BTN_REMOVE ?>').remove();

				// Change select to input
				[...document.querySelectorAll('.<?= ZBX_STYLE_HOST_INTERFACE_ROW ?> select')].map((elem) => {
					const value = elem.options[elem.selectedIndex].text;

					// Create new input[type=text]
					const input = document.createElement('input')
					input.type = 'text';
					input.disabled = true;
					input.value = value;

					// Replace select with created input.
					elem.replace(input);
				});
			}
		};
	})(jQuery);

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
				jQuery('#tls_psk, #tls_psk_identity').closest('li').show();
			}
			else {
				jQuery('#tls_psk, #tls_psk_identity').closest('li').hide();
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

		// Depending on checkboxes, create a value for hidden field 'tls_accept'.
		jQuery('#hostsForm').submit(function() {
			var tls_accept = 0x00;

			if (jQuery('#tls_in_none').is(':checked')) {
				tls_accept |= <?= HOST_ENCRYPTION_NONE ?>;
			}
			if (jQuery('#tls_in_psk').is(':checked')) {
				tls_accept |= <?= HOST_ENCRYPTION_PSK ?>;
			}
			if (jQuery('#tls_in_cert').is(':checked')) {
				tls_accept |= <?= HOST_ENCRYPTION_CERTIFICATE ?>;
			}

			jQuery('#tls_accept').val(tls_accept);
		});
	});
</script>
