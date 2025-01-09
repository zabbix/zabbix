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


class CWidgetFieldHostSections {

	/**
	 * @type {HTMLTableElement};
	 */
	#table;

	/**
	 * @type {string};
	 */
	#field_name;

	/**
	 * @type {Array};
	 */
	#field_value;

	constructor({field_name, field_value}) {
		this.#table = document.getElementById(`${field_name}-table`);
		this.#field_name = field_name;
		this.#field_value = field_value;

		this.#init();
		this.#update();
	}

	#init() {
		jQuery(this.#table)
			.dynamicRows({
				template: `#${this.#field_name}-row-tmpl`,
				allow_empty: true,
				rows: this.#field_value.map((type) => ({['section']: type})),
				sortable: true,
				sortable_options: {
					target: 'tbody',
					selector_handle: `.${ZBX_STYLE_DRAG_ICON}`,
					freeze_end: 1
				}
			})
			.on('tableupdate.dynamicRows', () => this.#update())
			.on('afteradd.dynamicRows', () => this.#selectNextSection());

		jQuery(this.#table).on('change', () => this.#toggleSelectedSections());

		this.#toggleSelectedSections();
	}

	#update() {
		this.#table.querySelectorAll('.form_row').forEach((row, index) => {
			for (const field of row.querySelectorAll(`[name^="${this.#field_name}["]`)) {
				field.id = field.id.replace(/_\d+/g, `_${index}`);
				field.name = field.name.replace(/\[\d+]/g, `[${index}]`);
			}
		});

		this.#toggleSelectedSections();
	}

	#toggleSelectedSections() {
		const sections = this.#table.querySelectorAll('z-select');
		const selected_sections = [];

		for (const section of sections) {
			const value = parseInt(section.value);

			if (!selected_sections.includes(value)) {
				selected_sections.push(value);
			}
		}

		for (const element of sections) {
			const options = element.getOptions();
			const disabled_sections = selected_sections.filter(section => section !== parseInt(element.value));

			options.forEach((option, index) => {
				options[index].disabled = disabled_sections.includes(index);
			});
		}
	}

	#selectNextSection() {
		const sections = this.#table.querySelectorAll('z-select');
		const selected_sections = [];

		for (let i = 0; i < sections.length - 1; i++) {
			const value = parseInt(sections[i].value);

			if (!selected_sections.includes(value)) {
				selected_sections.push(value);
			}
		}

		const last_section = sections[sections.length - 1];
		const enabled_options = last_section.getOptions().filter((option, index) => !selected_sections.includes(index));

		if (enabled_options.length) {
			last_section.selectedIndex = enabled_options[0].value;
		}
	}
}
