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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * @var CPartial $this
 */
?>

<script type="text/javascript">
(() => {
	document.querySelectorAll('#valuemap-table .element-table-add').forEach((elm) => elm.addEventListener('click',
		(event) => openAddPopup(event))
	);

	function openAddPopup(event) {
		let valuemap_names = [];
		let valuemap_table = event.target.closest('table');

		valuemap_table.querySelectorAll('[name$="[name]"]').forEach((elm) => valuemap_names.push(elm.value));
		PopUp("popup.valuemap.edit", {valuemap_names: valuemap_names}, null, event.target);
	}
})();
</script>
<script type="text/javascript">
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
			return edit.replaceWith(this.row);
		}

		return document
			.querySelector('#valuemap-table tbody')
			.append(this.row);
	}

	createNameCell() {
		const cell = document.createElement('td');
		const link = document.createElement('a');
		link.innerHTML = this.data.name;
		link.classList.add('wordwrap');
		link.href = 'javascript:void(0);';
		link.addEventListener('click',
			(event) => PopUp('popup.valuemap.edit', Object.assign(this.data, {'edit': 1}), null, event.target)
		);

		cell.appendChild(this.createHiddenInput('[name]', this.data.name));
		cell.appendChild(link);

		return cell;
	}

	createRemoveCell() {
		const cell = document.createElement('td');
		const btn = document.createElement('button');
		btn.type = 'button';
		btn.classList.add('btn-link', 'element-table-remove');
		btn.innerHTML = <?= json_encode(_('Remove')) ?>;
		btn.addEventListener('click', () => this.row.remove());

		cell.appendChild(btn);

		return cell;
	}

	createMappingCell() {
		let i = 0;
		const cell = document.createElement('td');
		const hellip = document.createElement('span');
		hellip.innerHTML = '&hellip;';

		cell.classList.add('wordwrap');
		for (let value of this.data.mappings) {
			if (i <= this.MAX_MAPPINGS) {
				cell.append((i < this.MAX_MAPPINGS)
					? `${value.value} ⇒ ${value.newvalue}`
					: hellip,
					document.createElement('br')
				);
			}

			cell.appendChild(this.createHiddenInput(`[mappings][${i}][value]`, value.value));
			cell.appendChild(this.createHiddenInput(`[mappings][${i}][newvalue]`, value.newvalue));
			i++;
		}

		return cell;
	}

	createHiddenInput(name, value) {
		const input = document.createElement('input');
		input.type = 'hidden';
		input.name = `valuemaps[${valuemap_number}]${name}`;
		input.value = value;

		return input;
	}
}

// Initialize value maps from data array.
var valuemaps = <?= json_encode($data['valuemaps']) ?>;

valuemaps.forEach((valuemap) => new AddValueMap(valuemap));
</script>
