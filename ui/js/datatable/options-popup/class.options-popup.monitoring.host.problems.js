/*
** Copyright (C) 2001-2026 Zabbix SIA
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


class CDataTableOptionsPopupMonitoringHostProblems extends CDataTableOptionsPopup {

	getFields() {
		const show_suppressed = this.getElement().querySelector('[name="show_suppressed"]');

		return {show_suppressed};
	}

	getTemplate() {
		return `
			<template>
				<div class="${ZBX_STYLE_FORM_FIELD}">
					<input type="checkbox" id="show_suppressed" name="show_suppressed" value="1"
						class="${ZBX_STYLE_CHECKBOX_RADIO}" data-field-type="checkbox">
					<label for="show_suppressed"><span></span>${t('Show suppressed problems')}</label>
				</div>
			</template>
		`;
	}

	getFieldData() {
		const show_suppressed = this.getField('show_suppressed').checked ? 1 : 0;

		return {show_suppressed};
	}

	getDefaultData() {
		return {
			show_suppressed: 0
		}
	}

	getValidatedData(data) {
		const defaults = this.getDefaultData();

		for (const key in Object.keys(data)) {
			if (!defaults.hasOwnProperty(key)) {
				delete data[key];

				continue;
			}

			if (data[key] < 0 || data[key] > 1) {
				data[key] = defaults[key];
			}
		}

		return {...defaults, ...data};
	}

	onInit() {
		super.onInit();

		const column_options = this.getColumnConfig().getColumnOptions();

		const input = this.getField('show_suppressed');
		input.checked = column_options.show_suppressed == 1;
		input.addEventListener('input', e => {
			e.stopPropagation();

			const column_options = {
				...this.getColumnConfig().getColumnOptions(),
				show_suppressed: e.target.checked ? 1 : 0
			};

			this.getColumnConfig().setColumnOptions(column_options);

			this.getDataTable().updateUserConfig();

			this.getDataTable().dispatchEvent(CDataTable.EVENT_INIT);
			this.getDataTable().dispatchEvent(CDataTable.EVENT_SAVE);
		});
	}
}
