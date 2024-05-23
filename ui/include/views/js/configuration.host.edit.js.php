<?php
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
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
<script type="text/x-jquery-tmpl" id="host-interface-row-tmpl">
<div class="<?= ZBX_STYLE_HOST_INTERFACE_ROW ?> <?= ZBX_STYLE_LIST_ACCORDION_ITEM ?> <?= ZBX_STYLE_LIST_ACCORDION_ITEM_CLOSED ?>" id="interface_row_#{iface.interfaceid}" data-type="#{iface.type}" data-interfaceid="#{iface.interfaceid}">
	<input type="hidden" name="interfaces[#{iface.interfaceid}][items]" value="#{iface.items}" />
	<input type="hidden" name="interfaces[#{iface.interfaceid}][locked]" value="#{iface.locked}" />
	<input type="hidden" name="interfaces[#{iface.interfaceid}][isNew]" value="#{iface.isNew}" />
	<input type="hidden" name="interfaces[#{iface.interfaceid}][interfaceid]" value="#{iface.interfaceid}" />
	<input type="hidden" id="interface_type_#{iface.interfaceid}" name="interfaces[#{iface.interfaceid}][type]" value="#{iface.type}" />

	<div class="<?= ZBX_STYLE_HOST_INTERFACE_CELL ?>">
		<button type="button" class="<?= ZBX_STYLE_HOST_INTERFACE_BTN_TOGGLE ?>"></button>
	</div>
	<div class="<?= ZBX_STYLE_HOST_INTERFACE_CELL ?> <?= ZBX_STYLE_HOST_INTERFACE_CELL_TYPE ?>">
		#{iface.type_name}
	</div>
	<div class="<?= ZBX_STYLE_HOST_INTERFACE_CELL ?> <?= ZBX_STYLE_HOST_INTERFACE_CELL_IP ?>">
		<?= (new CTextBox('interfaces[#{iface.interfaceid}][ip]', '#{iface.ip}', false, DB::getFieldLength('interface', 'ip')))
				->addClass(ZBX_STYLE_HOST_INTERFACE_INPUT_EXPAND)
				->setWidth(ZBX_TEXTAREA_INTERFACE_IP_WIDTH)
		?>
	</div>
	<div class="<?= ZBX_STYLE_HOST_INTERFACE_CELL ?> <?= ZBX_STYLE_HOST_INTERFACE_CELL_DNS ?>">
		<?= (new CTextBox('interfaces[#{iface.interfaceid}][dns]', '#{iface.dns}', false, DB::getFieldLength('interface', 'dns')))
				->addClass(ZBX_STYLE_HOST_INTERFACE_INPUT_EXPAND)
				->setWidth(ZBX_TEXTAREA_INTERFACE_DNS_WIDTH)
		?>
	</div>
	<div class="<?= ZBX_STYLE_HOST_INTERFACE_CELL ?> <?= ZBX_STYLE_HOST_INTERFACE_CELL_USEIP ?>">
		<?= (new CRadioButtonList('interfaces[#{iface.interfaceid}][useip]', null))
				->addValue('IP', INTERFACE_USE_IP, 'interfaces[#{iface.interfaceid}][useip]['.INTERFACE_USE_IP.']')
				->addValue('DNS', INTERFACE_USE_DNS, 'interfaces[#{iface.interfaceid}][useip]['.INTERFACE_USE_DNS.']')
				->addClass(ZBX_STYLE_HOST_INTERFACE_CELL_USEIP.' '.ZBX_STYLE_HOST_INTERFACE_INPUT_EXPAND)
				->setModern(true)
		?>
	</div>
	<div class="<?= ZBX_STYLE_HOST_INTERFACE_CELL ?> <?= ZBX_STYLE_HOST_INTERFACE_CELL_PORT ?>">
		<?= (new CTextBox('interfaces[#{iface.interfaceid}][port]', '#{iface.port}', false, DB::getFieldLength('interface', 'port')))
				->setWidth(ZBX_TEXTAREA_INTERFACE_PORT_WIDTH)
				->addClass(ZBX_STYLE_HOST_INTERFACE_INPUT_EXPAND)
				->setAriaRequired()
		?>
	</div>
	<div class="<?= ZBX_STYLE_HOST_INTERFACE_CELL ?> <?= ZBX_STYLE_HOST_INTERFACE_CELL_DEFAULT ?>">
		<input type="radio" class="<?= ZBX_STYLE_CHECKBOX_RADIO ?> <?= ZBX_STYLE_HOST_INTERFACE_BTN_MAIN_INTERFACE ?>" id="interface_main_#{iface.interfaceid}" name="mainInterfaces[#{iface.type}]" value="#{iface.interfaceid}">
		<label class="checkboxLikeLabel" for="interface_main_#{iface.interfaceid}" style="height: 16px; width: 16px;"><span></span></label>
	</div>
	<div class="<?= ZBX_STYLE_HOST_INTERFACE_CELL ?> <?= ZBX_STYLE_HOST_INTERFACE_CELL_ACTION ?>">
		<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?> <?= ZBX_STYLE_HOST_INTERFACE_BTN_REMOVE ?>"><?= _('Remove') ?></button>
	</div>
	<div class="<?= ZBX_STYLE_HOST_INTERFACE_CELL ?> <?= ZBX_STYLE_HOST_INTERFACE_CELL_DETAILS ?> <?= ZBX_STYLE_LIST_ACCORDION_ITEM_BODY ?>">
		<?= (new CFormList('snmp_details_#{iface.interfaceid}'))
				->cleanItems()
				->addRow((new CLabel(_('SNMP version'), 'label_interfaces_#{iface.interfaceid}_details_version'))
						->setAsteriskMark(),
					(new CSelect('interfaces[#{iface.interfaceid}][details][version]'))
						->addOptions(CSelect::createOptionsFromArray([
							SNMP_V1 => _('SNMPv1'),
							SNMP_V2C => _('SNMPv2'),
							SNMP_V3 => _('SNMPv3')
						]))
						->setValue(SNMP_V2C)
						->setFocusableElementId('label_interfaces_#{iface.interfaceid}_details_version')
						->setId('interfaces_#{iface.interfaceid}_details_version'),
					'row_snmp_version_#{iface.interfaceid}'
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
					(new CSelect('interfaces[#{iface.interfaceid}][details][securitylevel]'))
						->addOptions(CSelect::createOptionsFromArray([
							ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV => 'noAuthNoPriv',
							ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV => 'authNoPriv',
							ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV => 'authPriv'
						]))
						->setValue(ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV)
						->setFocusableElementId('label_interfaces_#{iface.interfaceid}_details_securitylevel')
						->setId('interfaces_#{iface.interfaceid}_details_securitylevel'),
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
						->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
						->disableAutocomplete(),
					'row_snmpv3_authpassphrase_#{iface.interfaceid}'
				)
				->addRow(new CLabel(_('Privacy protocol'), 'interfaces[#{iface.interfaceid}][details][privprotocol]'),
					(new CRadioButtonList('interfaces[#{iface.interfaceid}][details][privprotocol]', ITEM_PRIVPROTOCOL_DES))
						->addValue(_('DES'), ITEM_PRIVPROTOCOL_DES, 'snmpv3_privprotocol_#{iface.interfaceid}_'.ITEM_PRIVPROTOCOL_DES)
						->addValue(_('AES'), ITEM_PRIVPROTOCOL_AES, 'snmpv3_privprotocol_#{iface.interfaceid}_'.ITEM_PRIVPROTOCOL_AES)
						->setModern(true),
					'row_snmpv3_privprotocol_#{iface.interfaceid}'
				)
				->addRow(new CLabel(_('Privacy passphrase'), 'interfaces[#{iface.interfaceid}][details][privpassphrase]'),
					(new CTextBox('interfaces[#{iface.interfaceid}][details][privpassphrase]', '#{iface.details.privpassphrase}', false, DB::getFieldLength('interface_snmp', 'privpassphrase')))
						->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
						->disableAutocomplete(),
					'row_snmpv3_privpassphrase_#{iface.interfaceid}'
				)
				->addRow('', (new CCheckBox('interfaces[#{iface.interfaceid}][details][bulk]', SNMP_BULK_ENABLED))->setLabel(_('Use bulk requests'), 'interfaces[#{iface.interfaceid}][details][bulk]'),
					'row_snmp_bulk_#{iface.interfaceid}'
				);
		?>
	</div>
</div>
</script>

<script type="text/javascript">
	'use strict';

	class HostInterfaceManager {
		constructor(data) {
			// Constants.
			this.TEMPLATE = new Template(document.getElementById('host-interface-row-tmpl').innerHTML);
			this.DEFAULT_PORTS = {
				agent: 10050,
				snmp: 161,
				jmx: 12345,
				ipmi: 623
			};
			this.CONTAINER_IDS = {
				<?= INTERFACE_TYPE_AGENT ?>: '#agentInterfaces',
				<?= INTERFACE_TYPE_SNMP ?>: '#SNMPInterfaces',
				<?= INTERFACE_TYPE_JMX ?>: '#JMXInterfaces',
				<?= INTERFACE_TYPE_IPMI ?>: '#IPMIInterfaces'
			};
			this.INTERFACE_TYPES = {
				'agent': '<?= INTERFACE_TYPE_AGENT ?>',
				'snmp': '<?= INTERFACE_TYPE_SNMP ?>',
				'jmx': '<?= INTERFACE_TYPE_JMX ?>',
				'ipmi': '<?= INTERFACE_TYPE_IPMI ?>'
			};
			this.INTERFACE_NAMES = {
				<?= INTERFACE_TYPE_AGENT ?>: '<?= _('Agent') ?>',
				<?= INTERFACE_TYPE_SNMP ?>: '<?= _('SNMP') ?>',
				<?= INTERFACE_TYPE_JMX ?>: '<?= _('JMX') ?>',
				<?= INTERFACE_TYPE_IPMI ?>: '<?= _('IPMI') ?>'
			};

			// Variables.
			this.interfaces = {};

			this.data = data;
		}

		/**
		 * Setter for interface store.
		 *
		 * @param object new_data
		 */
		set data(new_data) {
			if (typeof new_data  !== 'object') {
				throw new Error('Incorrect data.');
			}

			Object
				.entries(new_data)
				.forEach(([_, value]) => this.interfaces[value.interfaceid] = value);

			return this;
		}

		/**
		 * Getter for interface store.
		 */
		get data() {
			return this.interfaces;
		}

		setSnmpFields(elem, iface) {
			if (iface.type != <?= INTERFACE_TYPE_SNMP ?>) {
				return elem
					.querySelector('.<?= ZBX_STYLE_HOST_INTERFACE_CELL_DETAILS ?>')
					.remove();
			}

			elem
				.querySelector(`#interfaces_${iface.interfaceid}_details_version`)
				.value = iface.details.version;

			if (iface.details.securitylevel) {
				elem
					.querySelector(`#interfaces_${iface.interfaceid}_details_securitylevel`)
					.value = iface.details.securitylevel;
			}

			if (iface.details.privprotocol == <?= ITEM_PRIVPROTOCOL_AES ?>) {
				elem
					.querySelector(`#snmpv3_privprotocol_${iface.interfaceid}_1`)
					.checked = true;
			}

			if (iface.details.authprotocol == <?= ITEM_AUTHPROTOCOL_SHA ?>) {
				elem
					.querySelector(`#snmpv3_authprotocol_${iface.interfaceid}_1`)
					.checked = true;
			}

			if (iface.details.bulk == <?= SNMP_BULK_ENABLED ?>) {
				elem
					.querySelector(`#interfaces_${iface.interfaceid}_details_bulk`)
					.checked = true;
			}

			new CViewSwitcher(`interfaces_${iface.interfaceid}_details_version`, 'change',
				{
					<?= SNMP_V1 ?>: [`row_snmp_community_${iface.interfaceid}`],
					<?= SNMP_V2C ?>: [`row_snmp_community_${iface.interfaceid}`],
					<?= SNMP_V3 ?>: [
						`row_snmpv3_contextname_${iface.interfaceid}`,
						`row_snmpv3_securityname_${iface.interfaceid}`,
						`row_snmpv3_securitylevel_${iface.interfaceid}`,
						`row_snmpv3_authprotocol_${iface.interfaceid}`,
						`row_snmpv3_authpassphrase_${iface.interfaceid}`,
						`row_snmpv3_privprotocol_${iface.interfaceid}`,
						`row_snmpv3_privpassphrase_${iface.interfaceid}`,
					]
				}
			);

			jQuery(`#interfaces_${iface.interfaceid}_details_version`).on('change', function() {
				jQuery(`#interfaces_${iface.interfaceid}_details_securitylevel`).off('change');

				if (jQuery(this).val() == <?= SNMP_V3 ?>) {
					new CViewSwitcher(`interfaces_${iface.interfaceid}_details_securitylevel`, 'change',
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
		}

		generateId() {
			let id = 1;

			while (this.data[id] !== undefined) {
				id++;
			}

			return id;
		}

		getNewData(type) {
			return {
				interfaceid: this.generateId(),
				isNew: true,
				useip: 1,
				type: this.INTERFACE_TYPES[type],
				type_name: this.INTERFACE_NAMES[this.INTERFACE_TYPES[type]],
				port: this.DEFAULT_PORTS[type],
				ip: '127.0.0.1',
				main: '0',
				details: {
					version: <?= SNMP_V2C ?>,
					community: '{$SNMP_COMMUNITY}',
					bulk: <?= SNMP_BULK_ENABLED ?>,
					securitylevel: <?= ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV ?>,
					authprotocol: <?= ITEM_AUTHPROTOCOL_MD5 ?>,
					privprotocol: <?= ITEM_PRIVPROTOCOL_DES ?>
				}
			};
		}

		getInterfaces() {
			let types = {
					<?= INTERFACE_TYPE_AGENT ?>: {main: null, all: []},
					<?= INTERFACE_TYPE_SNMP ?>: {main: null, all: []},
					<?= INTERFACE_TYPE_JMX ?>: {main: null, all: []},
					<?= INTERFACE_TYPE_IPMI ?>: {main: null, all: []}
				};

			Object
				.entries(this.data)
				.forEach(([_, value]) => {
					types[value.type].all.push(value.interfaceid);

					if (value.main == <?= INTERFACE_PRIMARY ?>) {
						if (types[value.type].main !== null) {
							throw new Error('Multiple default interfaces for same type.');
						}

						types[value.type].main = value.interfaceid;
					}
				});

			return types;
		}

		renderRow(iface) {
			const container = document.querySelector(this.CONTAINER_IDS[iface.type]);
			const disabled = (iface.items > 0);
			const locked = (iface.locked > 0);

			iface.type_name = this.INTERFACE_NAMES[iface.type];

			/*
			 * New line break css selector :empty. Trim used to avoid this.
			 * Template added with new line. Because template <script> tag it contain for code readability.
			 */
			container.insertAdjacentHTML('beforeend', this.TEMPLATE.evaluate({iface: iface}).trim());

			const elem = document.getElementById(`interface_row_${iface.interfaceid}`);

			// Select proper use ip radio element.
			elem
				.querySelector(`#interfaces_${iface.interfaceid}_useip_${iface.useip}`)
				.checked = true;

			if (disabled) {
				elem
					.querySelector('.<?= ZBX_STYLE_HOST_INTERFACE_BTN_REMOVE ?>')
					.disabled = true;
			}

			this.setSnmpFields(elem, iface);

			// Set onclick actions.
			elem
				.querySelector('.<?= ZBX_STYLE_HOST_INTERFACE_BTN_REMOVE ?>')
				.addEventListener('click', () => this.removeById(iface.interfaceid));

			elem
				.querySelector('.<?= ZBX_STYLE_HOST_INTERFACE_BTN_MAIN_INTERFACE ?>')
				.addEventListener('click', () => this.setMainInterfaceById(iface.interfaceid));

			[...elem.querySelectorAll('.<?= ZBX_STYLE_HOST_INTERFACE_CELL_USEIP ?> input')].map(
				(el) => el.addEventListener('click', (event) => this.setUseIp(elem, event.currentTarget.value))
			);

			return true;
		}

		removeById(id) {
			const elem = document.getElementById(`interface_row_${id}`);

			if (!elem) {
				return false;
			}

			elem.remove();
			delete this.data[id];

			this.resetMainInterfaces();

			return true;
		}

		createRowByTypeName(type) {
			const new_data = this.getNewData(type);
			let data = {};

			data[new_data.interfaceid] = new_data;

			this.data = data;
			this.renderRow(new_data);

			this.resetMainInterfaces();

			if (new_data.type == <?= INTERFACE_TYPE_SNMP ?>) {
				const elem = document.getElementById(`interface_row_${new_data.interfaceid}`);
				const index = [...elem.parentElement.children].indexOf(elem)

				jQuery(this.CONTAINER_IDS[<?= INTERFACE_TYPE_SNMP ?>]).zbx_vertical_accordion('expandNth', index);
			}

			return true;
		}

		resetMainInterfaces() {
			const interfaces = this.getInterfaces();

			for (let type in interfaces) {
				if (!interfaces.hasOwnProperty(type)) {
					continue;
				}

				let type_interfaces = interfaces[type];

				if (!type_interfaces.main && type_interfaces.all.length) {
					for (let i = 0; i < type_interfaces.all.length; i++) {
						if (this.data[type_interfaces.all[i]].main == <?= INTERFACE_PRIMARY ?>) {
							interfaces[type].main = this.data[type_interfaces.all[i]].interfaceid;
						}
					}

					if (!type_interfaces.main) {
						type_interfaces.main = type_interfaces.all[0];
						this.data[type_interfaces.main].main = '<?= INTERFACE_PRIMARY ?>';
					}
				}
			}

			for (let type in interfaces) {
				if (interfaces.hasOwnProperty(type)) {
					let type_interfaces = interfaces[type];

					if (type_interfaces.main) {
						document
							.getElementById(`interface_main_${type_interfaces.main}`)
							.checked = true;
					}
				}
			}

			return true;
		}

		setMainInterfaceById(id) {
			const interfaces = this.getInterfaces();
			const type = this.data[id].type;
			const old = interfaces[type].main;

			if (id != old) {
				this.data[id].main = '<?= INTERFACE_PRIMARY ?>';
				this.data[old].main = '<?= INTERFACE_SECONDARY ?>';
			}

			return true;
		}

		setUseIp(elem, use_ip) {
			const interfaceid = elem.dataset.interfaceid;

			this.data[interfaceid].useip = use_ip;

			[...elem.querySelectorAll('input[name$="[ip]"], input[name$="[dns]"]')].map((el) => {
				el.removeAttribute('aria-required')
				return el;
			});

			elem
				.querySelector((use_ip == <?= INTERFACE_USE_IP ?>) ? '[name$="[ip]"]' : '[name$="[dns]"]')
				.setAttribute('aria-required', true);

			return true;
		}

		addAgent() {
			this.createRowByTypeName('agent');
		}

		addSnmp() {
			this.createRowByTypeName('snmp');
		}

		addJmx() {
			this.createRowByTypeName('jmx');
		}

		addIpmi() {
			this.createRowByTypeName('ipmi');
		}

		render() {
			for (let i in this.data) {
				if (this.data.hasOwnProperty(i)) {
					this.renderRow(this.data[i]);
				}
			}

			this.resetMainInterfaces();

			// Add accordion functionality to SNMP interfaces.
			jQuery(this.CONTAINER_IDS[<?= INTERFACE_TYPE_SNMP ?>])
				.zbx_vertical_accordion({handler: '.<?= ZBX_STYLE_HOST_INTERFACE_BTN_TOGGLE ?>'});

			// Add event to expand SNMP interface accordion if focused or clicked on inputs.
			jQuery(this.CONTAINER_IDS[<?= INTERFACE_TYPE_SNMP ?>]).on("focus", ".<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>:not(.<?= ZBX_STYLE_LIST_ACCORDION_ITEM_OPENED ?>) .<?= ZBX_STYLE_HOST_INTERFACE_INPUT_EXPAND ?>", (event) => {
				var index = jQuery(event.currentTarget).closest('.<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>').index();

				jQuery(this.CONTAINER_IDS[<?= INTERFACE_TYPE_SNMP ?>]).zbx_vertical_accordion("expandNth", index);
			});

			return true;
		}

		static disableEdit() {
			[...document.querySelectorAll('.<?= ZBX_STYLE_HOST_INTERFACE_ROW ?>')].map((row) => {
				[...row.querySelectorAll('input')].map((el) => {
					el.removeAttribute('name');

					if (el.matches('[type=text]')) {
						el.readOnly = true;
					}

					if (el.matches('[type=radio], [type=checkbox]')) {
						el.disabled = true;
					}
				});

				[...row.querySelectorAll('.<?= ZBX_STYLE_HOST_INTERFACE_BTN_REMOVE ?>')].map((el) => el.remove());

				[...row.querySelectorAll('z-select')].map((el) => {
					el.readOnly = true;
				});

				// Change select to input.
				[...row.querySelectorAll('select')].map((el) => {
					const index = el.selectedIndex;
					const value = el.options[index].text;

					// Create new input[type=text].
					const input = document.createElement('input');
					input.type = 'text';
					input.id = el.id;
					input.readOnly = true;
					input.value = value;

					// Replace select with created input.
					el.replaceWith(input);
				});
			});

			return true;
		}
	}

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
