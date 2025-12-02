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


class CWidgetFieldAxisThresholds extends CWidgetField {

	constructor({name, form_name}) {
		super({name, form_name});

		this.#initField();
	}

	#initField() {
		const thresholds_table = document.getElementById(`${this.getName()}-table`);

		window.$thresholds_table = jQuery(thresholds_table);

		window.$thresholds_table
			.dynamicRows({template: `#${this.getName()}-row-tmpl`, allow_empty: true})
			.on('afteradd.dynamicRows', () => {
				const color_pickers = thresholds_table.querySelectorAll(`.${ZBX_STYLE_COLOR_PICKER}`);
				const used_colors = [];
				for (const color_picker of color_pickers) {
					if (color_picker.color !== '') {
						used_colors.push(color_picker.color);
					}
				}
				color_pickers[color_pickers.length - 1].color = colorPalette.getNextColor(used_colors);
			})
			.on('tableupdate.dynamicRows', () => this.dispatchUpdateEvent());

		const observer = new MutationObserver(records => {
			if (records.some(record => record.type === 'attributes' && record.target.value !== record.oldValue)) {
				this.dispatchUpdateEvent();
			}
		});

		observer.observe(thresholds_table, {
			subtree: true,
			attributes: true,
			attributeFilter: ['value'],
			attributeOldValue: true
		});

		thresholds_table.addEventListener('input', () => this.dispatchUpdateEvent());
	}
}
