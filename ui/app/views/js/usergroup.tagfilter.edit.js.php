<?php declare(strict_types = 0);
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


/**
 * @var CView $this
 */
?>

window.tag_filter_edit = new class {

	init({tag_filters, groupid}) {
		this.overlay = overlays_stack.getById('tag-filter-edit');
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');
		this.tag_filters = tag_filters;
		this.group_tag_filters = groupid === 0 ? [] : this.tag_filters[groupid]['tags'];
		this.groupid = groupid;
		this.tag_filter_template = new Template(document.getElementById('tag-filter-row-template').innerHTML);
		this.tag_filter_counter = 0;

		if (typeof this.group_tag_filters === 'object' && !Array.isArray(this.group_tag_filters)) {
			const result = [];

			for (let key in this.group_tag_filters) {
				result.push(this.group_tag_filters[key]);
			}

			this.group_tag_filters = result;
		}

		const indices = Object.keys(this.group_tag_filters);
		const first_index = indices[0];

		if (this.group_tag_filters.length !== 0 && this.group_tag_filters[first_index]['tag'] !== '') {
			const tag_list_option = document.querySelector(`input[name="filter_type"][value='<?= TAG_FILTER_LIST ?>']`);

			tag_list_option.checked = true;

			for (const tag of this.group_tag_filters) {
				this.#addTagFilterRow(tag);
			}
		}
		else {
			this.#addTagFilterRow();
		}

		this.#toggleTagList();

		document.querySelectorAll('[name=filter_type]').forEach((type) =>
			type.addEventListener('change', () => this.#toggleTagList())
		);

		document.querySelector('.js-add-tag-filter-row').addEventListener('click', () => this.#addTagFilterRow());

		document.getElementById('tag-filter-add-form').addEventListener('click', event => {
			if (event.target.classList.contains('js-remove-table-row')) {
				event.target.closest('tr').remove();
			}
		});

		const ms = document.getElementById('ms_new_tag_filter_groupids_');

		$(ms).multiSelect();

		$(ms).on('change', () =>
			$(ms).multiSelect('setDisabledEntries',
				[...this.form.querySelectorAll('input[name^="ms_new_tag_filter[groupids]"]')].map(input => input.value)
			)
		);
	}

	/**
	 * Adds a new row for the tag filter with the specified tag and value.
	 * If no tag is provided, the row will be initialized with empty values.
	 *
	 * @param {array} tag  The tag and value information for the filter row.
	 */
	#addTagFilterRow(tag = []) {
		const rowid = this.tag_filter_counter++;
		const data = {
			'rowid': rowid,
			'tag': tag.length == 0 ? '' : tag.tag,
			'value': tag.length == 0 ? '' : tag.value
		};

		const new_row = this.tag_filter_template.evaluate(data);
		const placeholder_row = document.querySelector('.js-tag-filter-row-placeholder');

		placeholder_row.insertAdjacentHTML('beforebegin', new_row);
	}

	/**
	 * Toggles the visibility of the tag list form fields based on the selected filter type.
	 */
	#toggleTagList() {
		const tag_list_radio = document.querySelector('[name="filter_type"]:checked').value;
		const tags = document.getElementById('tag-list-form-field');
		const tags_label = document.querySelector("label[for='tag_filters']");
		const show_tags = tag_list_radio == '<?= TAG_FILTER_LIST ?>';

		tags.style.display = show_tags ? '' : 'none';
		tags_label.style.display = show_tags ? '' : 'none';
	}

	submit() {
		const curl = new Curl('zabbix.php');

		curl.setArgument('action', 'usergroup.tagfilter.check');

		const fields = getFormFields(this.form);

		fields.tag_filters = this.tag_filters;
		fields.groupid = this.groupid;

		if (fields.filter_type == '<?= TAG_FILTER_ALL ?>') {
			delete fields.new_tag_filter;
		}

		if ('new_tag_filter' in fields) {
			for (const tag_filter of Object.values(fields.new_tag_filter)) {
				tag_filter.tag = tag_filter.tag.trim();
				tag_filter.value = tag_filter.value.trim();
			}
		}

		this.#post(curl.getUrl(), fields);
	}

	/**
	 * Sends a POST request to the specified URL with the provided data.
	 * Handles the response, destroys the overlay, and triggers a custom event to be used for reloading tag filters.
	 *
	 * @param {string}   url               The URL to send the POST request to.
	 * @param {object}   data              The data to be sent in the POST request.
	 * @param {function} success_callback  The callback function to be executed after a successful response.
	 */
	#post(url, data, success_callback) {
		this.overlay.setLoading();

		fetch(url, {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify(data)
		})
			.then((response) => response.json())
			.then((response) => {
				if ('error' in response) {
					throw {error: response.error};
				}
				overlayDialogueDestroy(this.overlay.dialogueid);

				this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
			})
			.then(success_callback)
			.catch((exception) => {
				for (const element of this.form.parentNode.children) {
					if (element.matches('.msg-good, .msg-bad, .msg-warning')) {
						element.parentNode.removeChild(element);
					}
				}

				let title, messages;

				if (typeof exception === 'object' && 'error' in exception) {
					title = exception.error.title;
					messages = exception.error.messages;
				}
				else {
					messages = [<?= json_encode(_('Unexpected server error.')) ?>];
				}

				const message_box = makeMessageBox('bad', messages, title)[0];

				this.form.parentNode.insertBefore(message_box, this.form);
			})
			.finally(() => this.overlay.unsetLoading());
	}
}
