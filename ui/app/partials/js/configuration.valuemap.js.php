<?php
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
 * @var CPartial $this
 * @var array    $data
 */
?>

<script type="text/javascript">
(() => {
	document.querySelectorAll(`#${'<?= $data['table_id'] ?>'} .element-table-add`).forEach((element) =>
		element.addEventListener('click', (event) => openAddPopup(event))
	);

	function openAddPopup(event) {
		let valuemap_names = [];
		let valuemap_table = event.target.closest('table');
		const source = <?= json_encode($data['source']) ?>;

		valuemap_table.querySelectorAll('[name$="[name]"]').forEach((elm) => valuemap_names.push(elm.value));

		PopUp('popup.valuemap.edit', {valuemap_names, source}, {
			dialogue_class: 'modal-popup-generic',
			dialogueid: 'valuemap_edit'
		});

	}
})();

var valuemap_number = 0;

var AddValueMap = class {

	constructor(data, edit = null) {
		this.data = data;
		this.MAX_MAPPINGS = 3;

		this.row = document.createElement('tr');
		this.row.appendChild(this.createNameCell());
		this.row.appendChild(this.createMappingCell());
		this.row.appendChild(this.createRemoveCell());

		if ('valuemapid' in this.data) {
			this.row
				.querySelector('td')
				.appendChild(this.createHiddenInput('[valuemapid]', this.data.valuemapid));
		}

		this.render(edit);

		valuemap_number++;
	}

	render(edit) {
		if (edit instanceof Element) {
			edit.replaceWith(this.row);
			this.row.querySelector('.js-label').focus();
		}
		else {
			document.querySelector(`#${'<?= $data['table_id'] ?>'} tbody`).append(this.row);
		}
	}

	createNameCell() {
		const cell = document.createElement('td');
		const link = document.createElement('a');
		link.textContent = this.data.name;
		link.classList.add('wordbreak', 'js-label');
		link.href = 'javascript:void(0);';
		link.addEventListener('click', e => {
			const valuemap_names = [];
			const valuemap_table = e.target.closest('table');
			const source = <?= json_encode($data['source']) ?>;

			valuemap_table.querySelectorAll('[name$="[name]"]').forEach(element => {
				if (this.data.name !== element.value) {
					valuemap_names.push(element.value);
				}
			});

			PopUp('popup.valuemap.edit', {...this.data, valuemap_names, edit: 1, source}, {
				dialogue_class: 'modal-popup-generic',
				dialogueid: 'valuemap_edit'
			});
		});

		cell.appendChild(this.createHiddenInput('[name]', this.data.name));
		cell.appendChild(link);

		return cell;
	}

	createRemoveCell() {
		const cell = document.createElement('td');
		const btn = document.createElement('button');
		btn.type = 'button';
		btn.classList.add('btn-link', 'element-table-remove');
		btn.textContent = <?= json_encode(_('Remove')) ?>;
		btn.addEventListener('click', () => this.row.remove());

		cell.appendChild(btn);

		return cell;
	}

	createMappingCell() {
		let i = 0;
		let cell = document.createElement('td');
		let hellip = document.createElement('span');
		let arrow_cell = document.createElement('div');
		let mappings_table = document.createElement('div');
		let value_cell, newvalue_cell;

		hellip.innerHTML = '&hellip;';
		arrow_cell.textContent = '⇒';
		mappings_table.classList.add('mappings-table');
		cell.classList.add('wordbreak');

		for (let mapping of this.data.mappings) {
			mapping = {value: '', ...mapping};

			cell.appendChild(this.createHiddenInput(`[mappings][${i}][type]`, mapping.type));
			cell.appendChild(this.createHiddenInput(`[mappings][${i}][value]`, mapping.value));
			cell.appendChild(this.createHiddenInput(`[mappings][${i}][newvalue]`, mapping.newvalue));
			i++;
		}

		for (let mapping of this.data.mappings.slice(0, this.MAX_MAPPINGS)) {
			value_cell = document.createElement('div');
			newvalue_cell = document.createElement('div');
			newvalue_cell.textContent = mapping.newvalue;

			switch (parseInt(mapping.type, 10)) {
				case <?= VALUEMAP_MAPPING_TYPE_EQUAL ?>:
					value_cell.textContent = `=${mapping.value}`;
					break;

				case <?= VALUEMAP_MAPPING_TYPE_GREATER_EQUAL ?>:
					value_cell.textContent = `>=${mapping.value}`;
					break;

				case <?= VALUEMAP_MAPPING_TYPE_LESS_EQUAL ?>:
					value_cell.textContent = `<=${mapping.value}`;
					break;

				case <?= VALUEMAP_MAPPING_TYPE_DEFAULT ?>:
					value_cell = document.createElement('em');
					value_cell.textContent = <?= json_encode(_('default')) ?>;
					break;

				default:
					value_cell.textContent = mapping.value;
			}

			mappings_table.append(value_cell);
			mappings_table.append(arrow_cell.cloneNode(true));
			mappings_table.append(newvalue_cell);
		}

		cell.append(mappings_table);

		if (this.data.mappings.length > this.MAX_MAPPINGS) {
			cell.append(hellip);
		}

		return cell;
	}

	createHiddenInput(name, value) {
		const input = document.createElement('input');
		input.type = 'hidden';
		input.name = `valuemaps[${valuemap_number}]${name}`;
		input.setAttribute('data-field-type', 'hidden');
		input.value = value;

		return input;
	}
}

// Initialize value maps from data array.
var valuemaps = <?= json_encode($data['valuemaps']) ?>;

valuemaps.forEach((valuemap) => new AddValueMap(valuemap));
</script>
