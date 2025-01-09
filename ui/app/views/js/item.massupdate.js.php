<?php declare(strict_types = 0);
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

// Headers.
jQuery('#headers-table')
	.dynamicRows({
		template: '#item-header-row-tmpl',
		rows: <?= json_encode($data['headers']) ?>,
		allow_empty: true,
		sortable: true,
		sortable_options: {
			target: 'tbody',
			selector_handle: 'div.<?= ZBX_STYLE_DRAG_ICON ?>',
			freeze_end: 1
		}
	})
	.on('tableupdate.dynamicRows', (e) => {
		e.target.querySelectorAll('.form_row').forEach((row, index) => {
			for (const field of row.querySelectorAll('[name^="headers["]')) {
				field.name = field.name.replace(/\[\d+]/g, `[${index}]`);
			}
		});
	});

// Timeout.
(() => {
	const custom_timeout = document.getElementById('custom_timeout');

	if (!custom_timeout) {
		return false;
	}

	custom_timeout.addEventListener('change', () => {
		const timeout = document.getElementById('timeout');

		if (custom_timeout.querySelector(':checked').value == <?= ZBX_ITEM_CUSTOM_TIMEOUT_DISABLED ?>) {
			timeout.style.display = 'none';
			timeout.disabled = true;
		}
		else {
			timeout.style.display = '';
			timeout.disabled = false;
		}
	});

	custom_timeout.dispatchEvent(new CustomEvent('change'));
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
	const ITEM_DELAY_FLEXIBLE = <?= ITEM_DELAY_FLEXIBLE ?>;
	const ZBX_STYLE_DISPLAY_NONE = <?= json_encode(ZBX_STYLE_DISPLAY_NONE) ?>;
	const custom_elem = document.querySelector('#update_interval');

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

			const row = event.target.closest('.form_row');
			const flexible = row.querySelector('[name$="[type]"]:checked').value == ITEM_DELAY_FLEXIBLE;

			row.querySelector('[name$="[delay]"]').classList.toggle(ZBX_STYLE_DISPLAY_NONE, !flexible);
			row.querySelector('[name$="[schedule]"]').classList.toggle(ZBX_STYLE_DISPLAY_NONE, flexible);
			row.querySelector('[name$="[period]"]').classList.toggle(ZBX_STYLE_DISPLAY_NONE, !flexible);
		});

	$(obj.querySelector('#custom_intervals')).dynamicRows({template: '#custom-intervals-tmpl', allow_empty: true});
})();

document.querySelectorAll('[name="preprocessing_action"]').forEach((button) => button.addEventListener('click', () =>
	document.getElementById('preprocessing').style.display = button.value == <?= ZBX_ACTION_REPLACE ?> ? '' : 'none')
);

document.querySelector('#visible_preprocessing').addEventListener('change', () => {
	const preprocessing = document.querySelector('#preprocessing');

	if (preprocessing?.querySelectorAll('.preprocessing-list-item').length == 0) {
		preprocessing.querySelector('.element-table-add')?.click();
	}
});
</script>
