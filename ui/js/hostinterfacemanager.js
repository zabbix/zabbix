/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


class HostInterfaceManager {

	static INTERFACE_TYPE_AGENT = 1;
	static INTERFACE_TYPE_SNMP = 2;
	static INTERFACE_TYPE_IPMI = 3;
	static INTERFACE_TYPE_JMX = 4;
	static SNMP_V1 = 1;
	static SNMP_V2C = 2;
	static SNMP_V3 = 3;
	static SNMP_BULK_ENABLED = 1;
	static INTERFACE_SECONDARY = 0;
	static INTERFACE_PRIMARY = 1;
	static INTERFACE_USE_IP = 1;
	static ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV = 0;
	static ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV = 1;
	static ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV = 2;
	static ITEM_SNMPV3_AUTHPROTOCOL_MD5 = 0;
	static ITEM_SNMPV3_PRIVPROTOCOL_DES = 0;

	static ZBX_STYLE_HOST_INTERFACE_BTN_MAIN_INTERFACE = 'interface-btn-main-interface';
	static ZBX_STYLE_HOST_INTERFACE_BTN_REMOVE = 'interface-btn-remove';
	static ZBX_STYLE_HOST_INTERFACE_BTN_TOGGLE = 'interface-btn-toggle';
	static ZBX_STYLE_HOST_INTERFACE_CELL_DETAILS = 'interface-cell-details';
	static ZBX_STYLE_HOST_INTERFACE_CELL_USEIP = 'interface-cell-useip';
	static ZBX_STYLE_HOST_INTERFACE_CONTAINER = 'interface-container';
	static ZBX_STYLE_HOST_INTERFACE_CONTAINER_HEADER = 'interface-container-header';
	static ZBX_STYLE_HOST_INTERFACE_INPUT_EXPAND = 'interface-input-expand';
	static ZBX_STYLE_HOST_INTERFACE_ROW = 'interface-row';
	static ZBX_STYLE_HOST_NO_INTERFACE = 'no-interface';
	static ZBX_STYLE_LIST_ACCORDION_ITEM = 'list-accordion-item';
	static ZBX_STYLE_LIST_ACCORDION_ITEM_OPENED = 'list-accordion-item-opened';

	constructor(data, host_interface_row_tmpl) {
		// Constants.
		this.TEMPLATE = new Template(host_interface_row_tmpl);
		this.DEFAULT_PORTS = {
			agent: 10050,
			snmp: 161,
			jmx: 12345,
			ipmi: 623
		};
		this.CONTAINER_IDS = {
			[HostInterfaceManager.INTERFACE_TYPE_AGENT]: '#agentInterfaces',
			[HostInterfaceManager.INTERFACE_TYPE_SNMP]: '#SNMPInterfaces',
			[HostInterfaceManager.INTERFACE_TYPE_JMX]: '#JMXInterfaces',
			[HostInterfaceManager.INTERFACE_TYPE_IPMI]: '#IPMIInterfaces'
		};
		this.INTERFACE_TYPES = {
			'agent': HostInterfaceManager.INTERFACE_TYPE_AGENT,
			'snmp': HostInterfaceManager.INTERFACE_TYPE_SNMP,
			'jmx': HostInterfaceManager.INTERFACE_TYPE_JMX,
			'ipmi': HostInterfaceManager.INTERFACE_TYPE_IPMI
		};
		this.INTERFACE_NAMES = {
			[HostInterfaceManager.INTERFACE_TYPE_AGENT]: t('Agent'),
			[HostInterfaceManager.INTERFACE_TYPE_SNMP]: t('SNMP'),
			[HostInterfaceManager.INTERFACE_TYPE_JMX]: t('JMX'),
			[HostInterfaceManager.INTERFACE_TYPE_IPMI]: t('IPMI')
		};

		this.allow_empty_message = true;

		this.$noInterfacesMsg = jQuery('<div>', {
			html: t('No interfaces are defined.'),
			class: HostInterfaceManager.ZBX_STYLE_HOST_NO_INTERFACE
		})
			.insertAfter(jQuery('.' + HostInterfaceManager.ZBX_STYLE_HOST_INTERFACE_CONTAINER_HEADER));

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
			.forEach(([_, value]) => {
				if (!('interfaceid' in value)) {
					value.interfaceid = this.generateId();
				}

				this.interfaces[value.interfaceid] = value;
			});
	}

	/**
	 * Getter for interface store.
	 */
	get data() {
		return this.interfaces;
	}

	setAllowEmptyMessage(value) {
		this.allow_empty_message = value;
	}

	setSnmpFields(elem, iface) {
		if (iface.type != HostInterfaceManager.INTERFACE_TYPE_SNMP) {
			return elem
				.querySelector('.' + HostInterfaceManager.ZBX_STYLE_HOST_INTERFACE_CELL_DETAILS)
				.remove();
		}

		elem
			.querySelector(`#interfaces_${iface.interfaceid}_details_version`)
			.value = iface.details.version;

		if (iface.details.securitylevel !== undefined) {
			elem
				.querySelector(`#interfaces_${iface.interfaceid}_details_securitylevel`)
				.value = iface.details.securitylevel;
		}

		if (iface.details.privprotocol !== undefined) {
			elem.querySelector(`[name="interfaces[${iface.interfaceid}][details][privprotocol]"]`).value
				= iface.details.privprotocol;
		}

		if (iface.details.authprotocol !== undefined) {
			elem.querySelector(`[name="interfaces[${iface.interfaceid}][details][authprotocol]"]`).value
				= iface.details.authprotocol;
		}

		if (iface.details.bulk == HostInterfaceManager.SNMP_BULK_ENABLED) {
			elem
				.querySelector(`#interfaces_${iface.interfaceid}_details_bulk`)
				.checked = true;
		}

		new CViewSwitcher(`interfaces_${iface.interfaceid}_details_version`, 'change',
			{
				[HostInterfaceManager.SNMP_V1]: [
					`snmp_community_label_${iface.interfaceid}`,
					`snmp_community_field_${iface.interfaceid}`
				],
				[HostInterfaceManager.SNMP_V2C]: [
					`snmp_community_label_${iface.interfaceid}`,
					`snmp_community_field_${iface.interfaceid}`
				],
				[HostInterfaceManager.SNMP_V3]: [
					`snmpv3_contextname_label_${iface.interfaceid}`,
					`snmpv3_contextname_field_${iface.interfaceid}`,
					`snmpv3_securityname_label_${iface.interfaceid}`,
					`snmpv3_securityname_field_${iface.interfaceid}`,
					`snmpv3_securitylevel_label_${iface.interfaceid}`,
					`snmpv3_securitylevel_field_${iface.interfaceid}`,
					`snmpv3_authprotocol_label_${iface.interfaceid}`,
					`snmpv3_authprotocol_field_${iface.interfaceid}`,
					`snmpv3_authpassphrase_label_${iface.interfaceid}`,
					`snmpv3_authpassphrase_field_${iface.interfaceid}`,
					`snmpv3_privprotocol_label_${iface.interfaceid}`,
					`snmpv3_privprotocol_field_${iface.interfaceid}`,
					`snmpv3_privpassphrase_label_${iface.interfaceid}`,
					`snmpv3_privpassphrase_field_${iface.interfaceid}`
				]
			}
		);

		jQuery(`#interfaces_${iface.interfaceid}_details_version`).on('change', (e) => {
			jQuery(`#interfaces_${iface.interfaceid}_details_securitylevel`).off('change');

			if (e.target.value == HostInterfaceManager.SNMP_V3) {
				new CViewSwitcher(`interfaces_${iface.interfaceid}_details_securitylevel`, 'change',
					{
						[HostInterfaceManager.ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV]: [],
						[HostInterfaceManager.ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV]: [
							`snmpv3_authprotocol_label_${iface.interfaceid}`,
							`snmpv3_authprotocol_field_${iface.interfaceid}`,
							`snmpv3_authpassphrase_label_${iface.interfaceid}`,
							`snmpv3_authpassphrase_field_${iface.interfaceid}`
						],
						[HostInterfaceManager.ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV]: [
							`snmpv3_authprotocol_label_${iface.interfaceid}`,
							`snmpv3_authprotocol_field_${iface.interfaceid}`,
							`snmpv3_authpassphrase_label_${iface.interfaceid}`,
							`snmpv3_authpassphrase_field_${iface.interfaceid}`,
							`snmpv3_privprotocol_label_${iface.interfaceid}`,
							`snmpv3_privprotocol_field_${iface.interfaceid}`,
							`snmpv3_privpassphrase_label_${iface.interfaceid}`,
							`snmpv3_privpassphrase_field_${iface.interfaceid}`
						]
					}
				);
			}
		}).trigger('change');
	}

	generateId() {
		const keys = Object.keys(this.data).map(Number);
		const max_key = Math.max(0, ...keys);

		return max_key+1;
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
				version: HostInterfaceManager.SNMP_V2C,
				community: '{$SNMP_COMMUNITY}',
				bulk: HostInterfaceManager.SNMP_BULK_ENABLED,
				securitylevel: HostInterfaceManager.ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV,
				authprotocol: HostInterfaceManager.ITEM_SNMPV3_AUTHPROTOCOL_MD5,
				privprotocol: HostInterfaceManager.ITEM_SNMPV3_PRIVPROTOCOL_DES
			}
		};
	}

	getInterfaces() {
		let types = {
			[HostInterfaceManager.INTERFACE_TYPE_AGENT]: {main: null, all: []},
			[HostInterfaceManager.INTERFACE_TYPE_SNMP]: {main: null, all: []},
			[HostInterfaceManager.INTERFACE_TYPE_JMX]: {main: null, all: []},
			[HostInterfaceManager.INTERFACE_TYPE_IPMI]: {main: null, all: []}
		};

		Object
			.entries(this.data)
			.forEach(([_, value]) => {
				types[value.type].all.push(value.interfaceid);

				if (value.main == HostInterfaceManager.INTERFACE_PRIMARY) {
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
		const disabled = (typeof iface.items !== 'undefined' && iface.items > 0);

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
				.querySelector('.' + HostInterfaceManager.ZBX_STYLE_HOST_INTERFACE_BTN_REMOVE)
				.disabled = true;
		}

		this.setSnmpFields(elem, iface);

		// Set onclick actions.
		elem
			.querySelector('.' + HostInterfaceManager.ZBX_STYLE_HOST_INTERFACE_BTN_REMOVE)
			.addEventListener('click', () => this.removeById(iface.interfaceid));

		elem
			.querySelector('.' + HostInterfaceManager.ZBX_STYLE_HOST_INTERFACE_BTN_MAIN_INTERFACE)
			.addEventListener('click', () => this.setMainInterfaceById(iface.interfaceid));

		[...elem.querySelectorAll('.' + HostInterfaceManager.ZBX_STYLE_HOST_INTERFACE_CELL_USEIP + ' input')].map(
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
		this.renderLayout();

		return true;
	}

	createRowByTypeName(type) {
		const new_data = this.getNewData(type);
		let data = {};

		data[new_data.interfaceid] = new_data;

		this.data = data;
		this.renderRow(new_data);

		this.resetMainInterfaces();
		this.renderLayout();

		if (new_data.type == HostInterfaceManager.INTERFACE_TYPE_SNMP) {
			const elem = document.getElementById(`interface_row_${new_data.interfaceid}`);
			const index = [...elem.parentElement.children].indexOf(elem)

			jQuery(this.CONTAINER_IDS[HostInterfaceManager.INTERFACE_TYPE_SNMP])
				.zbx_vertical_accordion('expandNth', index);
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
					if (this.data[type_interfaces.all[i]].main == HostInterfaceManager.INTERFACE_PRIMARY) {
						interfaces[type].main = this.data[type_interfaces.all[i]].interfaceid;
					}
				}

				if (!type_interfaces.main) {
					type_interfaces.main = type_interfaces.all[0];
					this.data[type_interfaces.main].main = HostInterfaceManager.INTERFACE_PRIMARY;
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
			this.data[id].main = HostInterfaceManager.INTERFACE_PRIMARY;
			this.data[old].main = HostInterfaceManager.INTERFACE_SECONDARY;
		}

		return true;
	}

	setUseIp(elem, use_ip) {
		const interfaceid = elem.dataset.interfaceid;

		this.data[interfaceid].useip = use_ip;

		[...elem.querySelectorAll('input[name$="[ip]"], input[name$="[dns]"]')].map((el) =>
			el.removeAttribute('aria-required')
		);

		elem
			.querySelector((use_ip == HostInterfaceManager.INTERFACE_USE_IP) ? '[name$="[ip]"]' : '[name$="[dns]"]')
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
		this.renderLayout();

		// Add accordion functionality to SNMP interfaces.
		jQuery(this.CONTAINER_IDS[HostInterfaceManager.INTERFACE_TYPE_SNMP])
			.zbx_vertical_accordion({handler: '.' + HostInterfaceManager.ZBX_STYLE_HOST_INTERFACE_BTN_TOGGLE});

		// Add event to expand SNMP interface accordion if focused or clicked on inputs.
		jQuery(this.CONTAINER_IDS[HostInterfaceManager.INTERFACE_TYPE_SNMP])
				.on("focus", "." + HostInterfaceManager.ZBX_STYLE_LIST_ACCORDION_ITEM + ":not(." + HostInterfaceManager.ZBX_STYLE_LIST_ACCORDION_ITEM_OPENED + ") ." + HostInterfaceManager.ZBX_STYLE_HOST_INTERFACE_INPUT_EXPAND, (event) => {
			const index = jQuery(event.currentTarget)
				.closest('.' + HostInterfaceManager.ZBX_STYLE_LIST_ACCORDION_ITEM)
				.index();

			jQuery(this.CONTAINER_IDS[HostInterfaceManager.INTERFACE_TYPE_SNMP])
				.zbx_vertical_accordion("expandNth", index);
		});

		return true;
	}

	renderLayout() {
		if (Object.keys(this.data).length > 0) {
			jQuery('.' + HostInterfaceManager.ZBX_STYLE_HOST_INTERFACE_CONTAINER).show();
			this.$noInterfacesMsg.hide();
		}
		else {
			jQuery('.' + HostInterfaceManager.ZBX_STYLE_HOST_INTERFACE_CONTAINER).hide();
			this.$noInterfacesMsg.toggle(this.allow_empty_message);
		}
	}

	/**
	 * Converts form field to readonly.
	 *
	 * @param {Element} el  Native JavaScript element for form field.
	 */
	setReadonly(el) {
		const tag_name = el.tagName;

		if (tag_name === 'INPUT') {
			const type = el.getAttribute('type');

			switch (type) {
				case 'text':
					el.readOnly = true;
					break;

				case 'radio':
				case 'checkbox':
					const {checked, name, value} = el;
					el.disabled = true;

					if (checked) {
						const input = document.createElement('input');
						input.type = 'hidden';
						input.name = name;
						input.value = value;

						el.insertAdjacentElement('beforebegin', input);
					}
					break;
			}
		}
		else if (tag_name === 'Z-SELECT') {
			el.readOnly = true;
		}
	}

	makeReadonly() {
		[...document.querySelectorAll('.' + HostInterfaceManager.ZBX_STYLE_HOST_INTERFACE_ROW)].forEach((row) => {
			[...row.querySelectorAll('input, z-select')].map((el) => {
				this.setReadonly(el);
			});

			[...row.querySelectorAll('.' + HostInterfaceManager.ZBX_STYLE_HOST_INTERFACE_BTN_REMOVE)]
				.map((el) => el.remove());
		});

		return true;
	}
}
