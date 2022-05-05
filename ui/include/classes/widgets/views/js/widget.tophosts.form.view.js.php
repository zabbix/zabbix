<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
?>


window.widget_tophosts_form = new class {

	init(options) {
		this._form = document.getElementById(options.form_id);
		this._list_columns = document.getElementById('list_columns');
		this.initSortable(this._list_columns);

		this._list_columns.addEventListener('click', (e) => this.processColumnsAction(e));
	}

	initSortable(element) {
		const is_disabled = element.querySelectorAll('tr.sortable').length < 2;

		$(element).sortable({
			disabled: is_disabled,
			items: 'tbody tr.sortable',
			axis: 'y',
			containment: 'parent',
			cursor: 'grabbing',
			handle: 'div.<?= ZBX_STYLE_DRAG_ICON ?>',
			tolerance: 'pointer',
			opacity: 0.6,
			helper: function(e, ui) {
				for (let td of ui.find('>td')) {
					let $td = $(td);
					$td.attr('width', $td.width())
				}

				// when dragging element on safari, it jumps out of the table
				if (SF) {
					// move back draggable element to proper position
					ui.css('left', (ui.offset().left - 2) + 'px');
				}

				return ui;
			},
			stop: function(e, ui) {
				ui.item.find('>td').removeAttr('width');
				ui.item.removeAttr('style');
			},
			start: function(e, ui) {
				$(ui.placeholder).height($(ui.helper).height());
			}
		});

		for (const drag_icon of element.querySelectorAll('div.<?= ZBX_STYLE_DRAG_ICON ?>')) {
			drag_icon.classList.toggle('<?= ZBX_STYLE_DISABLED ?>', is_disabled);
		}
	}

	processColumnsAction(e) {
		const target = e.target;

		let column_popup;

		switch (target.getAttribute('name')) {
			case 'add':
				this._column_index = this._list_columns.querySelectorAll('tr').length;

				column_popup = PopUp('popup.tophosts.column.edit', {}).$dialogue[0];
				column_popup.addEventListener('dialogue.submit', (e) => this.updateColumns(e));
				column_popup.addEventListener('overlay.close', this.removeColorpicker);
				break;

			case 'edit':
				const form_fields = getFormFields(this._form);

				this._column_index = target.closest('tr').querySelector('[name="sortorder[columns][]"]').value;

				column_popup = PopUp('popup.tophosts.column.edit',
					{...form_fields.columns[this._column_index], edit: 1}).$dialogue[0];
				column_popup.addEventListener('dialogue.submit', (e) => this.updateColumns(e));
				column_popup.addEventListener('overlay.close', this.removeColorpicker);
				break;

			case 'remove':
				target.closest('tr').remove();
				ZABBIX.Dashboard.reloadWidgetProperties();
				break;
		}
	}

	updateColumns(e) {
		const data = e.detail;
		const input = document.createElement('input');

		input.setAttribute('type', 'hidden');

		if (data.edit) {
			this._list_columns.querySelectorAll(`[name^="columns[${this._column_index}][`)
				.forEach((node) => node.remove());

			delete data.edit;
		}
		else {
			input.setAttribute('name', `sortorder[columns][]`);
			input.setAttribute('value', this._column_index);
			this._form.appendChild(input.cloneNode());
		}

		if (data.thresholds) {
			for (const [key, value] of Object.entries(data.thresholds)) {
				input.setAttribute('name', `columns[${this._column_index}][thresholds][${key}][color]`);
				input.setAttribute('value', value.color);
				this._form.appendChild(input.cloneNode());
				input.setAttribute('name', `columns[${this._column_index}][thresholds][${key}][threshold]`);
				input.setAttribute('value', value.threshold);
				this._form.appendChild(input.cloneNode());
			}

			delete data.thresholds;
		}

		for (const [key, value] of Object.entries(data)) {
			input.setAttribute('name', `columns[${this._column_index}][${key}]`);
			input.setAttribute('value', value);
			this._form.appendChild(input.cloneNode());
		}

		ZABBIX.Dashboard.reloadWidgetProperties();
	}

	// Need to remove function after subpopups auto close.
	removeColorpicker() {
		$('#color_picker').hide();
	}
}();
