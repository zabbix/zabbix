<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2024 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
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
		const input = document.createElement('input');

		input.setAttribute('type', 'hidden');

		if (data.edit) {
			this.#list_columns.querySelectorAll(`[name^="columns[${this.#column_index}][`)
				.forEach((node) => node.remove());

			delete data.edit;
		}
		else {
			input.setAttribute('name', `sort_order[columns][]`);
			input.setAttribute('value', this.#column_index);
			this.#form.appendChild(input.cloneNode());
		}

		if (data.thresholds) {
			for (const [key, value] of Object.entries(data.thresholds)) {
				input.setAttribute('name', `columns[${this.#column_index}][thresholds][${key}][color]`);
				input.setAttribute('value', value.color);
				this.#form.appendChild(input.cloneNode());
				input.setAttribute('name', `columns[${this.#column_index}][thresholds][${key}][threshold]`);
				input.setAttribute('value', value.threshold);
				this.#form.appendChild(input.cloneNode());
			}

			delete data.thresholds;
		}

		if (data.highlights) {
			for (const [key, value] of Object.entries(data.highlights)) {
				input.setAttribute('name', `columns[${this.#column_index}][highlights][${key}][color]`);
				input.setAttribute('value', value.color);
				this.#form.appendChild(input.cloneNode());
				input.setAttribute('name', `columns[${this.#column_index}][highlights][${key}][pattern]`);
				input.setAttribute('value', value.pattern);
				this.#form.appendChild(input.cloneNode());
			}

			delete data.highlights;
		}

		for (const [key, value] of Object.entries(data)) {
			input.setAttribute('name', `columns[${this.#column_index}][${key}]`);
			input.setAttribute('value', value);
			this.#form.appendChild(input.cloneNode());
		}

		ZABBIX.Dashboard.reloadWidgetProperties();
	}

	// Need to remove function after sub-popups auto close.
	#removeColorpicker() {
		$('#color_picker').hide();
	}
};
