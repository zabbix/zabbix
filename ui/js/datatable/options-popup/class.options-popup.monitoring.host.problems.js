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
		return document.querySelector('template#problems');
	}

	getFieldData() {
		const show_suppressed = this.getField('show_suppressed').checked ? '1' : '0';

		return {show_suppressed};
	}

	getDefaultData() {
		return {
			show_suppressed: '0'
		}
	}

	getValidatedData(data) {
		const defaults = this.getDefaultData();

		data = {...defaults, ...data};

		if (typeof data.show_suppressed !== 'boolean') {
			data.show_suppressed = defaults.show_suppressed;
		}

		return data;
	}

	onInit() {
		super.onInit();

		const column_options = this.getColumnConfig().getColumnOptions();
		const {show_suppressed} = column_options;

		const input = this.getField('show_suppressed');
		input.checked = show_suppressed == 1;
		input.addEventListener('input', event => {
			event.stopPropagation();

			const column_options = {
				...this.getColumnConfig().getColumnOptions(),
				show_suppressed: event.target.checked ? '1' : '0'
			};

			this.getColumnConfig().setColumnOptions(column_options);

			this.getDataTable().dispatchEvent(CDataTable.EVENT_INIT, {force_load: true});
			this.getDataTable().dispatchEvent(CDataTable.EVENT_SAVE);
		});
	}
}
