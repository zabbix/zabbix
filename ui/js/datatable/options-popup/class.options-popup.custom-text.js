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


class CDataTableOptionsPopupCustomText extends CDataTableOptionsPopup {

	getFields() {
		const custom_text = this.getElement().querySelector('[name="custom_text"]');

		return {custom_text};
	}

	getTemplate() {
		const label = t('Custom text');
		const placeholder = t('Text, macros, or combined');

		return `
			<template>
				<label for="custom_text" class="${ZBX_STYLE_FORM_LABEL}">${label}</label>
				<div class="${ZBX_STYLE_FORM_FIELD}">
					<input type="text" id="custom_text" name="custom_text" maxlength="255" placeholder="${placeholder}"
						data-field-type="text-box">
				</div>
			</template>
		`;
	}

	getFieldData() {
		const column_options = this.getColumnConfig().getColumnOptions();

		return {
			...column_options,
			custom_text: this.getField('custom_text').value
		};
	}

	getDefaultData() {
		return {
			custom_text: ''
		};
	}

	onInit() {
		super.onInit();

		const column_options = this.getColumnConfig().getColumnOptions();

		const custom_text_field = this.getField('custom_text');

		if ('custom_text' in column_options) {
			custom_text_field.value = column_options.custom_text ?? '';
		}

		const old_value = custom_text_field.value;

		custom_text_field.addEventListener('input', e => this.setForceLoadOnClose(old_value !== e.target.value));
	}

}
