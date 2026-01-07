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


class CDataTableContextPopupMonitoringLatestName extends CDataTableContextPopup {
	getFields() {
		const show_item_key = this.getPopup().querySelector('[name="show_item_key"]');

		return {show_item_key};
	}

	getTemplate() {
		return document.querySelector('template#name');
	}

	getFieldData() {
		const show_item_key = this.getField('show_item_key').checked;

		return {show_item_key};
	}

	getDefaultData() {
		return {
			show_item_key: false
		}
	}

	getValidatedData(data) {
		const defaults = this.getDefaultData();

		data = {...defaults, ...data};

		if (typeof data.show_item_key !== 'boolean') {
			data.show_item_key = defaults.show_item_key;
		}

		return data;
	}

	onInit() {
		super.onInit();

		const context_popup_data = this.getColumnConfig().getContextPopupData();
		const {show_item_key} = context_popup_data;

		const input = this.getField('show_item_key');
		input.checked = show_item_key == 1;
		input.addEventListener('input', event => {
			event.stopPropagation();

			this.getColumnConfig().setContextPopupData({
				...context_popup_data,
				show_item_key: event.target.checked
			});

			this.getDataTable().dispatchEvent(CDataTable.EVENT_RENDER);
		});
	}
}
