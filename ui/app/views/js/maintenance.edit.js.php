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
?>


window.maintenance_edit = new class {

	/**
	 * @type {HTMLFormElement}
	 */
	form_element;

	/**
	 * @type {CForm}
	 */
	form;

	init({rules, timeperiods, tags, allowed_edit}) {
		this._overlay = overlays_stack.getById('maintenance.edit');
		this._dialogue = this._overlay.$dialogue[0];
		this.form_element = this._overlay.$dialogue.$body[0].querySelector('form');
		this.form = new CForm(this.form_element, rules);
		this._allowed_edit = allowed_edit;

		const return_url = new URL('zabbix.php', location.href);

		return_url.searchParams.set('action', 'maintenance.list');
		ZABBIX.PopupManager.setReturnUrl(return_url.href);

		timeperiods.forEach((timeperiod, row_index) => this.#addTimePeriod({row_index, ...timeperiod}));

		// Setup Tags.
		jQuery(document.getElementById('tags')).dynamicRows({
			template: '#tag-row-tmpl',
			rows: tags,
			allow_empty: true
		});

		if (allowed_edit) {
			// Setup Time periods.
			document.getElementById('timeperiods').addEventListener('click', (e) => {
				if (e.target.classList.contains('js-add')) {
					this.#editTimePeriod();
				}
				else if (e.target.classList.contains('js-edit')) {
					this.#editTimePeriod(e.target.closest('tr'));
				}
				else if (e.target.classList.contains('js-remove')) {
					e.target.closest('tr').remove();
				}
			});

			// Preselect already selected data in multiselect popups.

			const $groupids = $('#groupids_');

			$groupids.on('change', () => this.#updateMultiselect($groupids));
			this.#updateMultiselect($groupids);

			const $hostids = $('#hostids_');

			$hostids.on('change', () => this.#updateMultiselect($hostids));
			this.#updateMultiselect($hostids);

			// Update form field state according to the form data.
			document.getElementById('maintenance_type').addEventListener('change', () => this.#update());
		}

		this.#update();

		this.form_element.style.display = '';
		this._overlay.recoverFocus();
	}

	#update() {
		const tags_enabled = this.form_element
			.querySelector('[name="maintenance_type"]:checked').value == <?= MAINTENANCE_TYPE_NORMAL ?>;
		const tags_container = document.getElementById('tags');

		tags_container.querySelectorAll('[name$="[tag]"], [name$="[value]"]').forEach((text_input) => {
			text_input.disabled = !tags_enabled;

			const field = this.form.findFieldByName(text_input.name);

			if (field && text_input.disabled) {
				field.unsetErrors();
			}
		});

		const tags_evaltypes = this.form_element.querySelectorAll('[name="tags_evaltype"]');
		const tags_operators = tags_container.querySelectorAll('[name$="[operator]"]');

		[...tags_evaltypes, ...tags_operators].forEach((radio_button) =>
			radio_button.disabled = !tags_enabled || !this._allowed_edit
		);

		tags_container.querySelectorAll('.element-table-add, .element-table-remove').forEach((button) =>
			button.disabled = !tags_enabled || !this._allowed_edit
		);

		tags_container.querySelectorAll('[name$="[tag]"]').forEach((tag_text_input) =>
			tag_text_input.placeholder = tags_enabled ? <?= json_encode(_('tag')) ?> : ''
		);

		tags_container.querySelectorAll('[name$="[value]"]').forEach((value_text_input) =>
			value_text_input.placeholder = tags_enabled ? <?= json_encode(_('value')) ?> : ''
		);
	}

	#editTimePeriod(row = null) {
		let popup_params;

		if (row !== null) {
			const row_index = row.dataset.row_index;

			popup_params = {
				edit: '1',
				row_index,
				timeperiod_type: row.querySelector(`[name="timeperiods[${row_index}][timeperiod_type]"`).value,
				every: row.querySelector(`[name="timeperiods[${row_index}][every]"`).value,
				month: row.querySelector(`[name="timeperiods[${row_index}][month]"`).value,
				dayofweek: row.querySelector(`[name="timeperiods[${row_index}][dayofweek]"`).value,
				day: row.querySelector(`[name="timeperiods[${row_index}][day]"`).value,
				start_time: row.querySelector(`[name="timeperiods[${row_index}][start_time]"`).value,
				period: row.querySelector(`[name="timeperiods[${row_index}][period]"`).value,
				start_date: row.querySelector(`[name="timeperiods[${row_index}][start_date]"`).value
			};
		}
		else {
			let row_index = 0;

			while (this.form_element.querySelector(`#timeperiods [data-row_index="${row_index}"]`) !== null) {
				row_index++;
			}

			popup_params = {row_index};
		}

		const overlay = PopUp('maintenance.timeperiod.edit', popup_params, {
			dialogueid: 'maintenance-timeperiod-edit',
			dialogue_class: 'modal-popup-medium'
		});

		overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => {
			if (row !== null) {
				this.#updateTimePeriod(row, e.detail)
			}
			else {
				this.#addTimePeriod(e.detail);
			}
		});

		overlay.$dialogue[0].addEventListener('dialogue.close', () =>
			this.form.validateChanges(['timeperiods'])
		);
	}

	#addTimePeriod(timeperiod) {
		const template = new Template(document.getElementById('timeperiod-row-tmpl').innerHTML);

		this.form_element
			.querySelector('#timeperiods tbody')
			.insertAdjacentHTML('beforeend', template.evaluate(timeperiod));
	}

	#updateTimePeriod(row, timeperiod) {
		const template = new Template(document.getElementById('timeperiod-row-tmpl').innerHTML);

		row.insertAdjacentHTML('afterend', template.evaluate(timeperiod));
		row.remove();
	}

	clone({rules, title, buttons}) {
		document.getElementById('maintenanceid').remove();
		this.form.reload(rules);

		this._overlay.unsetLoading();
		this._overlay.setProperties({title, buttons});
		this._overlay.recoverFocus();
		this._overlay.containFocus();
	}

	delete() {
		const post_data = {
			maintenanceids: [document.getElementById('maintenanceid').value],
			[CSRF_TOKEN_NAME]: <?= json_encode(CCsrfTokenHelper::get('maintenance')) ?>
		};

		const curl = new Curl('zabbix.php');

		curl.setArgument('action', 'maintenance.delete');

		this.#post(curl.getUrl(), post_data, (response) => {
			overlayDialogueDestroy(this._overlay.dialogueid);

			this._dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
		});
	}

	submit() {
		const fields = this.form.getAllValues();

		this.form.validateSubmit(fields).then((result) => {
			if (!result) {
				this._overlay.unsetLoading();

				return;
			}

			const curl = new Curl('zabbix.php');

			curl.setArgument('action',
				document.getElementById('maintenanceid') !== null
					? 'maintenance.update'
					: 'maintenance.create'
			);

			this.#post(curl.getUrl(), fields, (response) => {
				overlayDialogueDestroy(this._overlay.dialogueid);

				this._dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
			});
		});
	}

	#post(url, data, success_callback) {
		this._overlay.setLoading();
		this.#clearMessages();

		fetch(url, {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify(data)
		})
			.then((response) => response.json())
			.then((response) => {
				if ('form_errors' in response) {
					this.form.setErrors(response.form_errors, true, true);
					this.form.renderErrors();

					return;
				}
				else if ('error' in response) {
					throw {error: response.error};
				}

				success_callback(response);
			})
			.catch((exception) => this.#ajaxExceptionHandler(exception))
			.finally(() => this._overlay.unsetLoading());
	}

	#updateMultiselect($ms) {
		$ms.multiSelect('setDisabledEntries', [...$ms.multiSelect('getData').map((entry) => entry.id)]);
	}

	#clearMessages() {
		for (const element of this.form_element.parentNode.children) {
			if (element.matches('.msg-good, .msg-bad, .msg-warning')) {
				element.parentNode.removeChild(element);
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
			messages = t('Unexpected server error.');
		}

		const message_box = makeMessageBox('bad', messages, title)[0];

		this.form_element.parentNode.insertBefore(message_box, this.form_element);
	}
};
