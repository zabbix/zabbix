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


?>

window.widget_itemhistory_form = new class {

	/**
	 * Widget form.
	 *
	 * @type {HTMLFormElement}
	 */
	#form;

	/**
	 * Template id.
	 *
	 * @type {string}
	 */
	#templateid;

	/**
	 * Column list container.
	 *
	 * @type {HTMLElement}
	 */
	#list_columns;

	/**
	 * Column index.
	 *
	 * @type {number}
	 */
	#column_index;

	init({templateid}) {
		this.#form = document.getElementById('widget-dialogue-form');
		this.#templateid = templateid;

		this.#list_columns = document.getElementById('list_columns');

		new CSortable(this.#list_columns.querySelector('tbody'), {
			selector_handle: 'div.<?= ZBX_STYLE_DRAG_ICON ?>',
			freeze_end: 1
		});

		this.#list_columns.addEventListener('click', (e) => this.#processColumnsAction(e));
	}

	#processColumnsAction(e) {
		const target = e.target;

		let column_popup;

		switch (target.getAttribute('name')) {
			case 'add':
				this.#column_index = this.#list_columns.querySelectorAll('tr').length;

				column_popup = PopUp(
					'widget.itemhistory.column.edit',
					{templateid: this.#templateid},
					{
						dialogueid: 'item-history-column-edit-overlay',
						dialogue_class: 'modal-popup-generic'
					}
				).$dialogue[0];

				column_popup.addEventListener('dialogue.submit', (e) => this.#updateColumns(e));
				column_popup.addEventListener('dialogue.close', this.#removeColorpicker);
				break;

			case 'edit':
				const form_fields = getFormFields(this.#form);

				this.#column_index = target.closest('tr').querySelector('[name="sort_order[columns][]"]').value;

				column_popup = PopUp(
					'widget.itemhistory.column.edit',
					{
						...form_fields.columns[this.#column_index],
						edit: 1,
						templateid: this.#templateid
					}, {
						dialogueid: 'item-history-column-edit-overlay',
						dialogue_class: 'modal-popup-generic'
					}
				).$dialogue[0];

				column_popup.addEventListener('dialogue.submit', (e) => this.#updateColumns(e));
				column_popup.addEventListener('dialogue.close', this.#removeColorpicker);
				break;

			case 'remove':
				target.closest('tr').remove();
				ZABBIX.Dashboard.reloadWidgetProperties();
				break;
		}
	}

	#updateColumns(e) {
		const data = e.detail;

		if (!data.edit) {
			this.#addVar('sort_order[columns][]', this.#column_index);
		}

		for (const [data_key, data_value] of Object.entries(data)) {
			switch (data_key) {
				case 'edit':
					this.#list_columns.querySelectorAll(`[name^="columns[${this.#column_index}]["]`)
						.forEach((node) => node.remove());

					break;

				case 'thresholds':
					for (const [key, value] of Object.entries(data.thresholds)) {
						this.#addVar(`columns[${this.#column_index}][thresholds][${key}][color]`, value.color);
						this.#addVar(`columns[${this.#column_index}][thresholds][${key}][threshold]`, value.threshold);
					}

					break;

				case 'highlights':
					for (const [key, value] of Object.entries(data.highlights)) {
						this.#addVar(`columns[${this.#column_index}][highlights][${key}][color]`, value.color);
						this.#addVar(`columns[${this.#column_index}][highlights][${key}][pattern]`, value.pattern);
					}

					break;

				default:
					this.#addVar(`columns[${this.#column_index}][${data_key}]`, data_value);

					break;
			}
		}

		ZABBIX.Dashboard.reloadWidgetProperties();
	}

	#addVar(name, value) {
		const input = document.createElement('input');
		input.setAttribute('type', 'hidden');
		input.setAttribute('name', name);
		input.setAttribute('value', value);
		this.#form.appendChild(input);
	}

	// Need to remove function after sub-popups auto close.
	#removeColorpicker() {
		$('#color_picker').hide();
	}
};
