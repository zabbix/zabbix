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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * @param {object} interface_ids_by_types  Iterface ids grouped by interface type.
 * @param {object} item_interface_types    Item type to interface type map.
 * @param {number|null} item_type          Current item type.
 */
function organizeInterfaces(interface_ids_by_types, item_interface_types, item_type) {
	const $interface_select = jQuery('#interface-select'),
		interface_select_node = $interface_select.get(0),
		selected_interfaceid = +jQuery('#selectedInterfaceId').val();

	if (jQuery('#visible_interfaceid').data('multipleInterfaceTypes') && !jQuery('#visible_type').is(':checked')) {
		jQuery('#interface_not_defined').html(t('To set a host interface select a single item type for all items')).show();
		interface_select_node.disabled = true;
		$interface_select.hide();

		return;
	}

	if (!interface_select_node) {
		return;
	}

	const iterface_type = item_interface_types[item_type];
	const available_interfaceids = interface_ids_by_types[iterface_type];

	// If no interface is required.
	if (iterface_type === undefined) {
		interface_select_node.disabled = true;
		$interface_select.hide();
		jQuery('#interface_not_defined').html(t('Item type does not use interface')).show();
	}
	// If any interface type allowed, enable all options.
	else if (iterface_type == -1) {
		interface_select_node.disabled = false;
		interface_select_node.getOptions().map(opt => opt.disabled = false)
		$interface_select.show();
		jQuery('#interface_not_defined').hide();
	}
	// If none of required interfaces found.
	else if (!available_interfaceids) {
		interface_select_node.disabled = true;
		$interface_select.hide();
		jQuery('#interface_not_defined').html(t('No interface found')).show();
	}
	// Enable required interfaces, disable other interfaces.
	else {
		interface_select_node.disabled = false;
		interface_select_node.getOptions().map(opt => opt.disabled = !available_interfaceids.includes(opt.value))
		$interface_select.show();
		jQuery('#interface_not_defined').hide();
	}

	interface_select_node.value = selected_interfaceid;
	// If value current option is disabled, select first available interface.
	const selected_option = interface_select_node.getOptionByValue(interface_select_node.value);
	if (!selected_option || selected_option.disabled) {
		for (let opt of interface_select_node.getOptions()) {
			if (!opt.disabled) {
				interface_select_node.value = opt.value;
				break;
			}
		}
	}
}
