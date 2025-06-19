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


class ItemNavigator_CWidgetFieldItemGrouping extends CWidgetField {

	static GROUP_BY_HOST_TAG_VALUE = 2;
	static GROUP_BY_ITEM_TAG_VALUE = 3;

	/**
	 * @type {HTMLTableElement}
	 */
	#table;

	/**
	 * @type {Array}
	 */
	#value;

	/**
	 * type {number}
	 */
	#max_rows;

	constructor({name, form_name, value, max_rows}) {
		super({name, form_name});

		this.#value = value;
		this.#max_rows = max_rows;
		this.#table = document.getElementById(`${name}-table`);

		this.#initField();
		this.#update();
	}

	#initField() {
		jQuery(this.#table)
			.dynamicRows({
				template: `#${this.getName()}-row-tmpl`,
				allow_empty: true,
				rows: this.#value,
				sortable: true,
				sortable_options: {
					target: 'tbody',
					selector_handle: `.${ZBX_STYLE_DRAG_ICON}`,
					freeze_end: 1
				}
			})
			.on('afteradd.dynamicRows, tableupdate.dynamicRows', () => this.#update())
			.on('tableupdate.dynamicRows', () => this.dispatchUpdateEvent());

		this.#table.addEventListener('input', () => this.dispatchUpdateEvent());
		this.#table.addEventListener('change', () => {
			this.#update();
			this.dispatchUpdateEvent();
		});
	}

	#update() {
		const rows = this.#table.querySelectorAll('.form_row');

		rows.forEach((row, index) => {
			for (const field of row.querySelectorAll(`[name^="${this.getName()}["]`)) {
				field.name = field.name.replace(/\[\d+]/g, `[${index}]`);
			}

			const attribute_value = row.querySelector('[name$="[attribute]"]').value;

			const is_tag_value = attribute_value == ItemNavigator_CWidgetFieldItemGrouping.GROUP_BY_HOST_TAG_VALUE
				|| attribute_value == ItemNavigator_CWidgetFieldItemGrouping.GROUP_BY_ITEM_TAG_VALUE;
			const tag_name_input = row.querySelector('input[name$="[tag_name]"]');

			tag_name_input.style.display = is_tag_value ? '' : 'none';
			tag_name_input.disabled = !is_tag_value;
		});

		this.#table.querySelector('#add-row').disabled = rows.length == this.#max_rows;
	}
}
