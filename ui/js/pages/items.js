/*
** Copyright (C) 2001-2025 Zabbix SIA
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
 * Toggle visibility and access to interface select options.
 *
 * @param {object} interface_ids_by_types  Interface ids grouped by interface type.
 * @param {object} item_interface_types    Item type to interface type map.
 * @param {number|null} item_type          Current item type.
 */
function organizeInterfaces(interface_ids_by_types, item_interface_types, item_type) {
	const  INTERFACE_TYPE_ANY = -1,
		INTERFACE_TYPE_OPT = -2,
		$interface_select = $('#interface-select'),
		interface_select_node = $interface_select.get(0);

	if (!interface_select_node) {
		return;
	}

	if ($('#visible_interfaceid').data('multipleInterfaceTypes') && !$('#visible_type').is(':checked')) {
		$('#interface_not_defined')
			.html(t('To set a host interface select a single item type for all items'))
			.show();
		interface_select_node.disabled = true;
		$interface_select.hide();

		return;
	}

	const interface_type = item_interface_types[item_type],
		available_interfaceids = interface_ids_by_types[interface_type],
		select_options = interface_select_node.getOptions();

	/**
	 * Hide interface select, show "requires host interfaces" message.
	 */
	function noRequiredInterfacesFound() {
		interface_select_node.disabled = true;
		$interface_select.hide();

		$('#interface_not_defined')
			.html(t('No interface found'))
			.show();
	}

	// If no interface is required.
	if (typeof interface_type === 'undefined') {
		interface_select_node.disabled = true;
		$interface_select.hide();
		$('#interface_not_defined').
			html(t('Item type does not use interface'))
			.show();
	}
	// If any interface type allowed, enable all options.
	else if (select_options.length && (interface_type == INTERFACE_TYPE_ANY || interface_type == INTERFACE_TYPE_OPT)) {
		interface_select_node.disabled = false;
		select_options.map(opt => opt.disabled = false);
		$interface_select.show();
		$('#interface_not_defined').hide();
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
		$interface_select.show();
		$('#interface_not_defined').hide();
	}

	const allowed_opt_interface = (interface_type == INTERFACE_TYPE_OPT);

	$(interface_select_node.getOptionByValue(0)).attr('disabled', !allowed_opt_interface);
	$interface_select.find('li[value="0"]')
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
