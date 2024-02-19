<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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

	init({templateid}) {
		this._form = document.getElementById('widget-dialogue-form');
		this._templateid = templateid;

		this._list_columns = document.getElementById('list_columns');

		new CSortable(this._list_columns.querySelector('tbody'), {
			freeze_end: 1
		});

		this._list_columns.addEventListener('click', (e) => this.processColumnsAction(e));
	}

	processColumnsAction(e) {
		const target = e.target;

		let column_popup;

		switch (target.getAttribute('name')) {
			case 'add':
				this._column_index = this._list_columns.querySelectorAll('tr').length;

				column_popup = PopUp(
					'widget.tophosts.column.edit',
					{templateid: this._templateid},
					{dialogue_class: 'modal-popup-generic'}
				).$dialogue[0];
				column_popup.addEventListener('dialogue.submit', (e) => this.updateColumns(e));
				column_popup.addEventListener('dialogue.close', this.removeColorpicker);
				break;

			case 'edit':
				const form_fields = getFormFields(this._form);

				this._column_index = target.closest('tr').querySelector('[name="sortorder[columns][]"]').value;

				column_popup = PopUp('widget.tophosts.column.edit',
					{...form_fields.columns[this._column_index], edit: 1, templateid: this._templateid}).$dialogue[0];
				column_popup.addEventListener('dialogue.submit', (e) => this.updateColumns(e));
				column_popup.addEventListener('dialogue.close', this.removeColorpicker);
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

		if (data.time_period) {
			for (const [key, value] of Object.entries(data.time_period)) {
				input.setAttribute('name', `columns[${this._column_index}][time_period][${key}]`);
				input.setAttribute('value', value);
				this._form.appendChild(input.cloneNode());
			}

			delete data.time_period;
		}

		for (const [key, value] of Object.entries(data)) {
			input.setAttribute('name', `columns[${this._column_index}][${key}]`);
			input.setAttribute('value', value);
			this._form.appendChild(input.cloneNode());
		}

		ZABBIX.Dashboard.reloadWidgetProperties();
	}

	// Need to remove function after sub-popups auto close.
	removeColorpicker() {
		$('#color_picker').hide();
	}
};
