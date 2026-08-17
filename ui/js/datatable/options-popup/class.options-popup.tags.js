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

	getFields() {
		const number_of_tags = Array.from(this.getElement().querySelectorAll('[name="number_of_tags"]'));
		const tag_name_display = Array.from(this.getElement().querySelectorAll('[name="tag_name_display"]'));
		const tag_display_priority = this.getElement().querySelector('[name="tag_display_priority"]');

		return {number_of_tags, tag_name_display, tag_display_priority};
	}

	getTemplate() {
		return `
			<template>
				<label for="number_of_tags" class="${ZBX_STYLE_FORM_LABEL}">${t('Number of tags')}</label>
				<div class="${ZBX_STYLE_FORM_FIELD}">
					<ul id="number_of_tags" data-field-type="radio-list" class="${ZBX_STYLE_RADIO_LIST_CONTROL}">
						<li>
							<input type="radio" id="number_of_tags_0" name="number_of_tags" value="${SHOW_TAGS_1}">
							<label for="number_of_tags_0">${SHOW_TAGS_1}</label>
						</li>
						<li>
							<input type="radio" id="number_of_tags_1" name="number_of_tags" value="${SHOW_TAGS_2}">
							<label for="number_of_tags_1">${SHOW_TAGS_2}</label>
						</li>
						<li>
							<input type="radio" id="number_of_tags_2" name="number_of_tags" value="${SHOW_TAGS_3}">
							<label for="number_of_tags_2">${SHOW_TAGS_3}</label>
						</li>
					</ul>
				</div>
				<label for="tag_name_display" class="${ZBX_STYLE_FORM_LABEL}">${t('Tag name display')}</label>
				<div class="${ZBX_STYLE_FORM_FIELD}">
					<ul id="tag_name_display" data-field-type="radio-list" class="${ZBX_STYLE_RADIO_LIST_CONTROL}">
						<li>
							<input type="radio" id="tag_name_display_0" name="tag_name_display"
								value="${TAG_NAME_FULL}">
							<label for="tag_name_display_0">${t('Full')}</label>
						</li>
						<li>
							<input type="radio" id="tag_name_display_1" name="tag_name_display"
								value="${TAG_NAME_SHORTENED}">
							<label for="tag_name_display_1">${t('Shortened')}</label>
						</li>
						<li>
							<input type="radio" id="tag_name_display_2" name="tag_name_display"
								value="${TAG_NAME_NONE}">
							<label for="tag_name_display_2">${t('None')}</label>
						</li>
					</ul>
				</div>
				<label for="tag_display_priority" class="${ZBX_STYLE_FORM_LABEL}">${t('Tag display priority')}</label>
				<div class="${ZBX_STYLE_FORM_FIELD}">
					<input type="text" id="tag_display_priority" name="tag_display_priority" maxlength="255"
						data-field-type="text-box" placeholder="${t('comma-separated list')}">
				</div>
			</template>
		`;
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

		const number_of_tags_fields = this.getField('number_of_tags');

		for (const number_of_tags_field of number_of_tags_fields) {
			if (number_of_tags_field.value == number_of_tags) {
				number_of_tags_field.setAttribute('checked', 'true');
			}

			number_of_tags_field.addEventListener('change', e => {
				for (const number_of_tags_field of number_of_tags_fields) {
					number_of_tags_field.removeAttribute('checked');
				}

				if (e.target.value == number_of_tags_field.value) {
					number_of_tags_field.setAttribute('checked', 'true');
				}
			});
		}

		const tag_name_display_fields = this.getField('tag_name_display');

		for (const tag_name_display_field of tag_name_display_fields) {
			if (tag_name_display_field.value == tag_name_display) {
				tag_name_display_field.setAttribute('checked', 'true');
			}

			tag_name_display_field.addEventListener('change', e => {
				for (const tag_name_display_field of tag_name_display_fields) {
					tag_name_display_field.removeAttribute('checked');
				}

				if (e.target.value == tag_name_display_field.value) {
					tag_name_display_field.setAttribute('checked', 'true');
				}
			});
		}

		this.getField('tag_display_priority').value = tag_display_priority;
	}
}
