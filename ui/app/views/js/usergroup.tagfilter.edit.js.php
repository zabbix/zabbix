<?php declare(strict_types = 0);
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


/**
 * @var CView $this
 */
?>

window.tag_filter_edit = new class {

	init({rules, tag_filters, groupid}) {
		this.overlay = overlays_stack.getById('tag-filter-edit');
		this.dialogue = this.overlay.$dialogue[0];
		this.form_element = this.overlay.$dialogue.$body[0].querySelector('form');
		this.tag_filters = tag_filters;
		this.rules = rules;
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

		for (const tag of this.group_tag_filters) {
			this.#addTagFilterRow(tag);
		}

		if (this.group_tag_filters.length == 0) {
			this.#addTagFilterRow();
		}

		document.querySelector('.js-add-tag-filter-row').addEventListener('click', () => this.#addTagFilterRow());

		document.getElementById('tag-filter-add-form').addEventListener('click', event => {
			if (event.target.classList.contains('js-remove-table-row')) {
				event.target.closest('tr').remove();
			}
		});

		const ms = document.getElementById('new_tag_groups_');

		$(ms).multiSelect();

		$(ms).on('change', (event) => {
			const groupids = [...event.target.querySelectorAll('input[name^="new_tag_groups["]')]
				.map(input => input.value);
			$(ms).multiSelect('setDisabledEntries', groupids);
		});

		this.form = new CForm(this.form_element, rules);
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

	submit() {
		this.overlay.setLoading();
		this.#removePopupMessages();

		const fields = this.form.getAllValues();
		fields.tag_filters = this.tag_filters;
		fields.groupid = this.groupid;

		fields.new_tag_filters = Object.values(fields.new_tag_filters)
			.filter(tag => tag.tag !== '' || tag.value !== '');

		this.form.validateSubmit(fields)
			.then((result) => {
				if (!result) {
					this.overlay.unsetLoading();
					return;
				}

				var curl = new Curl('zabbix.php');
				curl.setArgument('action', 'usergroup.tagfilter.check');

				fetch(curl.getUrl(), {
					method: 'POST',
					headers: {'Content-Type': 'application/json'},
					body: JSON.stringify(fields)
				})
					.then((response) => response.json())
					.then((response) => {
						if ('error' in response) {
							throw {error: response.error};
						}

						if ('form_errors' in response) {
							this.form.setErrors(response.form_errors, true, true);
							this.form.renderErrors();
							this.overlay.unsetLoading();

							return;
						}

						overlayDialogueDestroy(this.overlay.dialogueid);

						this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
					})
					.catch((exception) => this.#ajaxExceptionHandler(exception))
					.finally(() => this.overlay.unsetLoading());
			})
	}

	#removePopupMessages() {
		for (const el of this.form_element.parentNode.children) {
			if (el.matches('.msg-good, .msg-bad, .msg-warning')) {
				el.parentNode.removeChild(el);
			}
		}
	}

	#ajaxExceptionHandler(exception) {
		let title, messages;

		if (typeof exception === 'object' && 'error' in exception) {
			title = exception.error.title;
			messages = exception.error.messages;
		}
		else {
			messages = [<?= json_encode(_('Unexpected server error.')) ?>];
		}

		const message_box = makeMessageBox('bad', messages, title)[0];

		this.form_element.parentNode.insertBefore(message_box, this.form_element);
	}
}
