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


class CWidgetFieldTags extends CWidgetField {

	/**
	 * @type {HTMLTableElement}
	 */
	#tags_table;

	/**
	 * @type {string}
	 */
	#field_id;

	constructor({name, form_name, field_id}) {
		super({name, form_name});

		this.#tags_table = document.getElementById(`tags_table_${field_id}`);
		this.#field_id = field_id;

		this.#initField();
	}

	#initField() {
		jQuery(this.#tags_table)
			.dynamicRows({template: `#${this.#field_id}-row-tmpl`, allow_empty: true})
			.on('afteradd.dynamicRows', () => {
				const rows = this.#tags_table.querySelectorAll('.form_row');

				new CTagFilterItem(rows[rows.length - 1]);
			})
			.on('afterremove.dynamicRows', () => {
				this.dispatchUpdateEvent();
			});

		// Init existing fields once loaded.
		this.#tags_table.querySelectorAll('.form_row').forEach(row => new CTagFilterItem(row));

		this.#tags_table.addEventListener('input', () => this.dispatchUpdateEvent());
		this.#tags_table.addEventListener('change', () => this.dispatchUpdateEvent());
	}
}
