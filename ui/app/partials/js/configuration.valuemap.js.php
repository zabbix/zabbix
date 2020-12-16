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

<script type="text/x-jquery-tmpl" id="mapping-row-tmpl">
	<?= (new CRow([
			(new CTextBox('mappings[#{rowNum}][key]', '', false, 64))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH),
			'&rArr;',
			(new CTextBox('mappings[#{rowNum}][value]', '', false, 64))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				->setAriaRequired(),
			(new CButton('mappings[#{rowNum}][remove]', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-remove')
		]))
			->addClass('form_row')
			->toString()
	?>
</script>

<script type="text/javascript">
let valuemap_number = 0;
class AddValueMap {

	constructor(data, edit = null) {
		this.data = data;

		this.row = document.createElement('tr');
		this.row.classList.add(`valuemap-row-${valuemap_number}`);
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
		link.href = 'javascript:void(0);';
		link.addEventListener('click',
			() => PopUp('popup.valuemap.edit', Object.assign(this.data, {'edit': 1}), null, this)
		);

		cell.appendChild(this.createHiddenInput('[name]', this.data.name))
		cell.appendChild(link);

		return cell;
	}

	createRemoveCell() {
		const cell = document.createElement('td');
		const btn = document.createElement('button');
		btn.type = 'button';
		btn.classList.add('btn-link', 'element-table-remove');
		btn.innerHTML = t('Remove');
		btn.addEventListener('click', () => this.row.remove());

		cell.appendChild(btn);

		return cell;
	}

	createMappingCell() {
		let i = 0;
		const cell = document.createElement('td');
		for (let value of this.data.mappings) {
			cell.append(`${value.key} ⇒ ${value.value}`, document.createElement('br'));

			cell.appendChild(this.createHiddenInput(`[mappings][${i}][key]`, value.key));
			cell.appendChild(this.createHiddenInput(`[mappings][${i}][value]`, value.value));
			i++;
		}

		return cell;
	}

	createHiddenInput(name, value) {
		const input = document.createElement('input');
		input.type = 'hidden';
		input.name = `valuemap[${valuemap_number}]${name}`;
		input.value = value;

		return input;
	}
}
</script>
