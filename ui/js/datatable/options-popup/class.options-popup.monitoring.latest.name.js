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


class CDataTableOptionsPopupMonitoringLatestName extends CDataTableOptionsPopup {

	getFields() {
		const show_item_key = this.getElement().querySelector('[name="show_item_key"]');

		return {show_item_key};
	}

	getTemplate() {
		return `
			<template>
				<div class="${ZBX_STYLE_FORM_FIELD}">
					<input type="checkbox" id="show_item_key" name="show_item_key" value="1"
						class="${ZBX_STYLE_CHECKBOX_RADIO}" data-field-type="checkbox">
					<label for="show_item_key"><span></span>${t('Show item key')}</label>
				</div>
			</template>
		`;
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

		const column_options = this.getColumn().getColumnOptions();
		const {show_item_key} = column_options;

		const input = this.getField('show_item_key');
		input.checked = show_item_key == 1;
		input.addEventListener('input', e => {
			e.stopPropagation();

			this.getColumn().setColumnOptions({
				...column_options,
				show_item_key: e.target.checked
			});

			this.getDataTable()
				.getData()
				.then(response => {
					this.getDataTable().dispatchEvent(CDataTable.EVENT_RENDER, {response});
				});
		});
	}
}
