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


class CDataTableOptionsPopupMonitoringProblemsTime extends CDataTableOptionsPopup {

	getFields() {
		const show_timeline = this.getElement().querySelector('[name="show_timeline"]');

		return {show_timeline};
	}

	getTemplate() {
		return `
			<template>
				<div class="${ZBX_STYLE_FORM_FIELD}">
					<input type="checkbox" id="show_timeline" name="show_timeline" value="1"
						class="${ZBX_STYLE_CHECKBOX_RADIO}" data-field-type="checkbox">
					<label for="show_timeline"><span></span>${t('Show timeline')}</label>
				</div>
			</template>
		`;
	}

	getFieldData() {
		const show_timeline = this.getField('show_timeline').checked ? '1' : '0';

		return {show_timeline};
	}

	getDefaultData() {
		return {
			show_timeline: '0'
		}
	}

	getValidatedData(data) {
		const defaults = this.getDefaultData();

		data = {...defaults, ...data};

		if (data.show_timeline < 0 || data.show_timeline > 1) {
			data.show_timeline = defaults.show_timeline;
		}

		return data;
	}

	onInit() {
		super.onInit();

		const {show_timeline} = this.getColumnConfig().getColumnOptions();
		const compact_view = this.getDataTable().getOption('compact_view');

		const show_timeline_field = this.getField('show_timeline');
		show_timeline_field.checked = show_timeline == 1;
		show_timeline_field.disabled = compact_view.checked;
		show_timeline_field.addEventListener('input', e => {
			e.stopPropagation();

			const column_options = this.getColumnConfig().getColumnOptions();

			this.getColumnConfig().setColumnOptions({
				...column_options,
				show_timeline: e.target.checked ? '1' : '0'
			});

			this.getDataTable().updateUserConfig();

			this.getDataTable().dispatchEvent(CDataTable.EVENT_INIT);
		});
	}
}
