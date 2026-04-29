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

	init({rules, clone_rules, timeperiods, tags, allowed_edit}) {
		this.overlay = overlays_stack.getById('maintenance.edit');
		this.dialogue = this.overlay.$dialogue[0];
		this.footer = this.overlay.$dialogue.$footer[0];
		this.form_element = this.overlay.$dialogue.$body[0].querySelector('form');
		this.form = new CForm(this.form_element, rules);
		this._allowed_edit = allowed_edit;
		this.clone_rules = clone_rules;

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
			this.form.findFieldByName('timeperiods').setButtonOnBlur('js-add', 'maintenance-timeperiod-edit');
		}

		this.#update();

		this.footer.querySelector('.js-submit').addEventListener('click', () => this.#submit());
		this.footer.querySelector('.js-clone')?.addEventListener('click', () => this.#clone());
		this.footer.querySelector('.js-delete')?.addEventListener('click', () => this.#delete());

		this.form_element.style.display = '';
		this.overlay.recoverFocus();
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

	#clone() {
		this.form_element.querySelector('[name=maintenanceid]').remove();

		const title = <?= json_encode(_('New maintenance period')) ?>;
		const buttons = [
			{
				title: <?= json_encode(_('Add')) ?>,
				class: 'js-submit',
				keepOpen: true,
				isSubmit: true
			},
			{
				title: <?= json_encode(_('Cancel')) ?>,
				class: ZBX_STYLE_BTN_ALT,
				cancel: true,
				action: ''
			}
		];

		this.overlay.unsetLoading();
		this.overlay.setProperties({title, buttons});

		this.footer.querySelector('.js-submit').addEventListener('click', () => this.#submit());

		this.overlay.recoverFocus();
		this.overlay.containFocus();
		this.form.reload(this.clone_rules);
	}

	#delete() {
		if (window.confirm(<?= json_encode(_('Delete maintenance period?')) ?>)) {
			this.#removePopupMessages();

			const post_data = {
				maintenanceids: [this.form.findFieldByName('maintenanceid').getValue()],
				[CSRF_TOKEN_NAME]: <?= json_encode(CCsrfTokenHelper::get('maintenance')) ?>
			};

			const curl = new Curl('zabbix.php');

			curl.setArgument('action', 'maintenance.delete');

			this.#post(curl.getUrl(), post_data, (response) => {
				overlayDialogueDestroy(this.overlay.dialogueid);

				this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
			});
		}
		else {
			this.overlay.unsetLoading();
		}
	}

	#submit() {
		this.#removePopupMessages();
		const fields = this.form.getAllValues();

		this.form.validateSubmit(fields).then((result) => {
			if (!result) {
				this.overlay.unsetLoading();

				return;
			}

			const curl = new Curl('zabbix.php');

			curl.setArgument('action',
				document.getElementById('maintenanceid') !== null
					? 'maintenance.update'
					: 'maintenance.create'
			);

			this.#post(curl.getUrl(), fields, (response) => {
				overlayDialogueDestroy(this.overlay.dialogueid);

				this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
			});
		});
	}

	#post(url, data, success_callback) {
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

				if ('form_errors' in response) {
					this.form.setErrors(response.form_errors, true, true);
					this.form.renderErrors();

					return;
				}

				success_callback(response);
			})
			.catch((exception) => this.#ajaxExceptionHandler(exception))
			.finally(() => this.overlay.unsetLoading());
	}

	#updateMultiselect($ms) {
		$ms.multiSelect('setDisabledEntries', [...$ms.multiSelect('getData').map((entry) => entry.id)]);
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
};
