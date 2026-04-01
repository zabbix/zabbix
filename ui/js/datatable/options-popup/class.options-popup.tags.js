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


class CDataTableOptionsPopupTags extends CDataTableOptionsPopup {
	static  = null;

	getFields() {
		const number_of_tags = Array.from(this.getElement().querySelectorAll('[name="number_of_tags"]'));
		const tag_name_display = Array.from(this.getElement().querySelectorAll('[name="tag_name_display"]'));
		const tag_display_priority = this.getElement().querySelector('[name="tag_display_priority"]');

		return {number_of_tags, tag_name_display, tag_display_priority};
	}

	getTemplate() {
		return document.querySelector('template#tags');
	}

	getFieldData() {
		const number_of_tags = this.getField('number_of_tags').filter(field => field.checked).at(0).value;
		const tag_name_display = this.getField('tag_name_display').filter(field => field.checked).at(0).value;
		const tag_display_priority = this.getField('tag_display_priority').value;

		return {number_of_tags, tag_name_display, tag_display_priority};
	}

	getDefaultData() {
		return {
			number_of_tags: SHOW_TAGS_3,
			tag_name_display: TAG_NAME_FULL,
			tag_display_priority: ''
		};
	}

	getValidatedData(data) {
		const defaults = this.getDefaultData();

		data = {...defaults, ...data};

		const number_of_tags = parseInt(data.number_of_tags.toString());

		if (![SHOW_TAGS_1, SHOW_TAGS_2, SHOW_TAGS_3].includes(number_of_tags)) {
			data.number_of_tags = defaults.number_of_tags;
		}

		const tag_name_display = parseInt(data.tag_name_display.toString());

		if (![TAG_NAME_FULL, TAG_NAME_SHORTENED, TAG_NAME_NONE].includes(tag_name_display)) {
			data.tag_name_display = defaults.tag_name_display;
		}

		return data;
	}

	onInit() {
		super.onInit();

		const {number_of_tags, tag_name_display, tag_display_priority} = this.getData();

		this.getField('number_of_tags').forEach(field => field.checked = field.value == number_of_tags);
		this.getField('tag_name_display').forEach(field => field.checked = field.value == tag_name_display);
		this.getField('tag_display_priority').value = tag_display_priority;
	}
}
