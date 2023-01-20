<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

<script type="text/javascript">
// Item type and interface.
(() => {
	const item_interface_types = <?= json_encode(itemTypeInterface()) ?>;
	const interface_ids_by_types = <?= json_encode(interfaceIdsByType($data['interfaces'])) ?>;

	let item_type_element = document.getElementById('type');

	if (item_type_element === null) {
		return false;
	}

	if (item_type_element.tagName === 'SPAN') {
		item_type_element = item_type_element.originalObject;
	}

	const interface_change_handler = (e) => {
		let item_type = parseInt(item_type_element.value, 10);

		if (!document.getElementById('visible_type').checked) {
			item_type = <?= json_encode($data['initial_item_type']) ?>;
		}

		return organizeInterfaces(interface_ids_by_types, item_interface_types, item_type);
	};

	item_type_element.addEventListener('change', interface_change_handler);
	item_type_element.dispatchEvent(new CustomEvent('change'));

	document.getElementById('visible_type').addEventListener('click', interface_change_handler);

	if (document.getElementById('visible_interfaceid') !== null) {
		document.getElementById('visible_interfaceid').addEventListener('click', interface_change_handler);
	}
})();

// History mode.
(() => {
	const history_toggle = document.getElementById('history_mode');

	if (!history_toggle) {
		return false;
	}

	history_toggle.addEventListener('change', () => {
		const history_input = document.getElementById('history');

		if (document.getElementById('history_mode_<?= ITEM_STORAGE_OFF ?>').checked) {
			history_input.style.display = 'none';
			history_input.disabled = true;
		}
		else {
			history_input.style.display = '';
			history_input.disabled = false;
		}
	});

	history_toggle.dispatchEvent(new CustomEvent('change'));
})();

// Trends mode.
(() => {
	const trends_toggle = document.getElementById('trends_mode');

	if (!trends_toggle) {
		return false;
	}

	trends_toggle.addEventListener('change', () => {
		const trends_input = document.getElementById('trends');

		if (document.getElementById('trends_mode_<?= ITEM_STORAGE_OFF ?>').checked) {
			trends_input.disabled = true;
			trends_input.style.display = 'none';
		}
		else {
			trends_input.disabled = false;
			trends_input.style.display = '';
		}
	});

	trends_toggle.dispatchEvent(new CustomEvent('change'));
})();

// Custom intervals.
(() => {
	const custom_elem = document.querySelector('#update_interval_div');

	if (!custom_elem) {
		return false;
	}

	let obj = custom_elem;
	if (custom_elem.tagName === 'SPAN') {
		obj = custom_elem.originalObject;
	}

	obj
		.querySelector('#custom_intervals')
		.addEventListener('click', (event) => {
			if (event.target.tagName != 'INPUT' || event.target.type != 'radio') {
				return false;
			}
			var num = event.target.id.split('_')[2];

			if (event.target.value == <?= ITEM_DELAY_FLEXIBLE; ?>) {
				obj.querySelector(`#delay_flex_${num}_schedule`).style.display = 'none';
				obj.querySelector(`#delay_flex_${num}_delay`).style.display = '';
				obj.querySelector(`#delay_flex_${num}_period`).style.display = '';
			}
			else {
				obj.querySelector(`#delay_flex_${num}_schedule`).style.display = '';
				obj.querySelector(`#delay_flex_${num}_delay`).style.display = 'none';
				obj.querySelector(`#delay_flex_${num}_period`).style.display = 'none';
			}
		});

	$(obj.querySelector('#custom_intervals')).dynamicRows({
		template: '#custom-intervals-tmpl'
	});
})();
</script>
