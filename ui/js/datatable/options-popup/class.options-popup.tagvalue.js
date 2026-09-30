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


class CDataTableOptionsPopupTagValue extends CDataTableOptionsPopup {

	getFields() {
		const tag_name = this.getElement().querySelector('[name="tag_name"]');

		return {tag_name};
	}

	getTemplate() {
		return `
			<template>
				<label for="tag_name" class="${ZBX_STYLE_FORM_LABEL}">${t('Tag name')}</label>
				<div class="${ZBX_STYLE_FORM_FIELD}">
					<input type="text" id="tag_name" name="tag_name" maxlength="255" data-field-type="text-box">
				</div>
			</template>
		`;
	}

	getFieldData() {
		const tag_name = this.getField('tag_name').value;

		return {tag_name};
	}

	getDefaultData() {
		return {tag_name: ''};
	}

	onInit() {
		super.onInit();

		const {tag_name} = this.getData();

		this.getField('tag_name').value = tag_name;
	}
}
