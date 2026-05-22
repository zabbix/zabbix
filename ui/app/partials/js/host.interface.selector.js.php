<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


/**
 * @var CPartial $this
 */
?>

var HostInterfaceSelector = class {
	#container;
	#interface_types;
	#optional_interfaces;
	#type_interfaceids;
	#type;

	constructor({container, host_interfaces, interface_types, type}) {
		this.#container = container;
		this.#interface_types = interface_types;
		this.#optional_interfaces = [];
		this.#type_interfaceids = [];

		for (const type in interface_types) {
			if (interface_types[type] == <?= INTERFACE_TYPE_OPT ?>) {
				this.#optional_interfaces.push(parseInt(type, 10));
			}
		}

		for (const host_interface of Object.values(host_interfaces)) {
			if (host_interface.type in this.#type_interfaceids) {
				this.#type_interfaceids[host_interface.type].push(host_interface.interfaceid);
			}
			else {
				this.#type_interfaceids[host_interface.type] = [host_interface.interfaceid];
			}
		}

		this.setType(type);
	}

	setType(type) {
		this.#type = type;
		this.#update();
	}

	#update() {
		const interface_optional = this.#optional_interfaces.indexOf(this.#type) != -1;

		this.#container.querySelector('[name="interfaceid"]')?.toggleAttribute('aria-required', !interface_optional);
		this.#container.querySelector('[for="interfaceid"]')?.classList
			.toggle('<?= ZBX_STYLE_FIELD_LABEL_ASTERISK ?>', !interface_optional);

		this.#organizeInterfaces();
	}

	#organizeInterfaces() {
		const interface_select = $(this.#container.querySelector('.<?=ZBX_STYLE_ZSELECT_HOST_INTERFACE ?>'));
		const interface_select_node = interface_select.get(0);

		if (!interface_select_node) {
			return;
		}

		const interface_type = this.#interface_types[this.#type];
		const available_interfaceids = this.#type_interfaceids[interface_type];
		const select_options = interface_select_node.getOptions();

		/**
		 * Hide interface select, show "requires host interfaces" message.
		 */
		function noRequiredInterfacesFound() {
			interface_select_node.disabled = true;
			interface_select.hide();
		}

		// If no interface is required.
		if (typeof interface_type === 'undefined') {
			interface_select_node.disabled = true;
			interface_select.hide();
		}
		// If any interface type allowed, enable all options.
		else if (select_options.length && interface_type == <?= INTERFACE_TYPE_OPT ?>) {
			interface_select_node.disabled = false;
			select_options.map(opt => opt.disabled = false);
			interface_select.show();
		}
		// If none of required interfaces found.
		else if (!available_interfaceids) {
			noRequiredInterfacesFound();
		}
		// Enable required interfaces, disable other interfaces.
		else {
			interface_select_node.disabled = false;
			select_options.map((option) => option.disabled = (
				typeof available_interfaceids === 'undefined' || !available_interfaceids.includes(option.value)
			));
			interface_select.show();
		}

		const allowed_opt_interface = (interface_type == <?= INTERFACE_TYPE_OPT ?>);

		$(interface_select_node.getOptionByValue(0)).attr('disabled', !allowed_opt_interface);
		interface_select.find('li[value="0"]')
			.toggle(allowed_opt_interface)
			.parents('li[optgroup]:first')
			.toggle(allowed_opt_interface);

		// If value current option is disabled, select first available interface.
		const selected_option = interface_select_node.getOptionByValue(interface_select_node.value);

		if (!selected_option || selected_option.disabled) {
			for (let option of select_options) {
				if (!option.disabled) {
					interface_select_node.value = option.value;
					break;
				}
			}
		}

		if (typeof interface_type !== 'undefined' && !select_options.some((option) => !option.disabled)) {
			noRequiredInterfacesFound();
		}
	}
};
