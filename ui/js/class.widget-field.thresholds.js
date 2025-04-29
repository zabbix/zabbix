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


class CWidgetFieldThresholds extends CWidgetField {

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
				const rows = thresholds_table.querySelectorAll('.form_row');
				const colors = this.getForm().querySelectorAll(`.${ZBX_STYLE_COLOR_PICKER} input`);
				const used_colors = [];
				for (const color of colors) {
					if (color.value !== '' && color.name.includes('thresholds')) {
						used_colors.push(color.value);
					}
				}

				jQuery('.color-picker input', rows[rows.length - 1])
					.val(colorPalette.getNextColor(used_colors))
					.colorpicker({
						appendTo: '.overlay-dialogue-body'
					});
			})
			.on('tableupdate.dynamicRows', () => this.dispatchUpdateEvent());

		const observer = new MutationObserver(records => {
			if (records.some(record => record.type === 'attributes' &&record.target.value !== record.oldValue)) {
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
