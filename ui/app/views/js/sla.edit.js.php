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

window.sla_edit_popup = new class {

	init({rules, slaid, service_tags, excluded_downtimes}) {
		this.slaid = slaid;

		this.overlay = overlays_stack.getById('sla.edit');
		this.dialogue = this.overlay.$dialogue[0];
		this.footer = this.overlay.$dialogue.$footer[0];

		this.form_element = this.overlay.$dialogue.$body[0].querySelector('form');
		this.form = new CForm(this.form_element, rules);

		this.excluded_downtime_template = new Template(
			this.form_element.querySelector('#excluded-downtime-tmpl').innerHTML
		);

		const return_url = new URL('zabbix.php', location.href);
		return_url.searchParams.set('action', 'sla.list');
		ZABBIX.PopupManager.setReturnUrl(return_url.href);

		for (const excluded_downtime of excluded_downtimes) {
			this._addExcludedDowntime(excluded_downtime);
		}

		// Update form field state according to the form data.
		document
			.querySelectorAll('#schedule_mode input[type="radio"], #schedule input[type="checkbox"]')
			.forEach(element => {
				element.addEventListener('change', () => this._updateScheduleFields());
			});

		// Setup Problem tags.
		const tag_table = document.getElementById('service-tags');

		jQuery(tag_table).dynamicRows({
			template: '#service-tag-row-tmpl',
			rows: service_tags,
			allow_empty: true
		});

		tag_table.addEventListener('click', e => {
			if (e.target.classList.contains('element-table-add')) {
				this.form.validateSubmit(this.form.getAllValues());
			}
		});

		// Setup Excluded downtimes.
		document
			.getElementById('excluded-downtimes')
			.addEventListener('click', (e) => {
				if (e.target.classList.contains('js-add')) {
					this._editExcludedDowntime();
				}
				else if (e.target.classList.contains('js-edit')) {
					this._editExcludedDowntime(e.target.closest('tr'));
				}
				else if (e.target.classList.contains('js-remove')) {
					e.target.closest('tr').remove();
				}
			});

		this._updateScheduleFields();

		this.form_element.style.display = '';
		this.overlay.recoverFocus();
	}

	_updateScheduleFields() {
		const schedule = document.getElementById('schedule');
		const schedule_mode = document.querySelector('#schedule_mode input:checked').value;

		schedule.style.display = schedule_mode == <?= CSlaHelper::SCHEDULE_MODE_CUSTOM ?> ? '' : 'none';

		schedule.querySelectorAll('input[type="checkbox"]').forEach((element, i) => {
			schedule
				.querySelector(`input[name="schedule[schedule_period_${i}]"]`)
				.disabled = element.checked ? '' : 'disabled';
		});
	}

	_editExcludedDowntime(row = null) {
		let popup_params;

		if (row !== null) {
			const row_index = row.dataset.row_index;

			popup_params = {
				edit: '1',
				row_index,
				name: row.querySelector(`[name="excluded_downtimes[${row_index}][name]"`).value,
				period_from: row.querySelector(`[name="excluded_downtimes[${row_index}][period_from]"`).value,
				period_to: row.querySelector(`[name="excluded_downtimes[${row_index}][period_to]"`).value
			};
		}
		else {
			let row_index = 0;

			while (document.querySelector(`#excluded-downtimes [data-row_index="${row_index}"]`) !== null) {
				row_index++;
			}

			popup_params = {row_index};
		}

		const overlay = PopUp('popup.sla.excludeddowntime.edit', popup_params, {
			dialogueid: 'sla_excluded_downtime_edit'
		});

		overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => {
			if (row !== null) {
				this._updateExcludedDowntime(row, e.detail)
			}
			else {
				this._addExcludedDowntime(e.detail);
			}
		});
	}

	_addExcludedDowntime(excluded_downtime) {
		document
			.querySelector('#excluded-downtimes tbody')
			.insertAdjacentHTML('beforeend', this.excluded_downtime_template.evaluate(excluded_downtime));
	}

	_updateExcludedDowntime(row, excluded_downtime) {
		row.insertAdjacentHTML('afterend', this.excluded_downtime_template.evaluate(excluded_downtime));
		row.remove();
	}

	clone({title, buttons, rules}) {
		this.slaid = null;

		this.form.reload(rules);

		this.overlay.unsetLoading();
		this.overlay.setProperties({title, buttons});
		this.overlay.recoverFocus();
		this.overlay.containFocus();
	}

	delete() {
		this._removePopupMessages();
		this.overlay.setLoading();

		const curl = new Curl('zabbix.php');
		curl.setArgument('action', 'sla.delete');
		curl.setArgument(CSRF_TOKEN_NAME, <?= json_encode(CCsrfTokenHelper::get('sla')) ?>);

		this._post(curl.getUrl(), {slaids: [this.slaid]}, (response) => {
			overlayDialogueDestroy(this.overlay.dialogueid);

			this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
		});
	}

	submit() {
		this._removePopupMessages();

		const fields = this.form.getAllValues();
		this.overlay.setLoading();

		if (this.slaid !== null) {
			fields.slaid = this.slaid;
		}

		Object.keys(fields.schedule).forEach(key => {
			['schedule_enabled_', 'schedule_period_'].forEach(prefix => {
				if (key.startsWith(prefix) && fields.schedule[key] === null) {
					delete fields.schedule[key];
				}
			});
		});

		if ('service_tags' in fields) {
			for (const key in fields.service_tags) {
				const service_tag = fields.service_tags[key];

				if (service_tag.tag === '' && service_tag.value === '') {
					delete fields.service_tags[key];
				}
			}
		}

		const curl = new Curl(this.form_element.getAttribute('action'));

		this.form.validateSubmit(fields)
			.then((result) => {
				if (!result) {
					this.overlay.unsetLoading();

					return;
				}

				this._post(curl.getUrl(), fields, (response) => {
					if ('form_errors' in response) {
						this.form.setErrors(response.form_errors, true, true);
						this.form.renderErrors();

						return;
					}

					overlayDialogueDestroy(this.overlay.dialogueid);

					this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
				});
			});
	}

	_post(url, data, success_callback) {
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

				return response;
			})
			.then(success_callback)
			.catch((exception) => {
				this._ajaxExceptionHandler(exception)
			})
			.finally(() => {
				this.overlay.unsetLoading();
			});
	}

	_removePopupMessages() {
		for (const el of this.form_element.parentNode.children) {
			if (el.matches('.msg-good, .msg-bad, .msg-warning')) {
				el.parentNode.removeChild(el);
			}
		}
	}

	_ajaxExceptionHandler(exception) {
		let title;
		let messages;

		if (typeof exception === 'object' && 'error' in exception) {
			title = exception.error.title;
			messages = exception.error.messages;
		}
		else {
			messages = [<?= json_encode(_('Unexpected server error.')) ?>];
		}

		this._addMessageBox(makeMessageBox('bad', messages, title)[0]);
	}

	_addMessageBox(message_box) {
		this._removeMessageBoxes();

		const step_form = this.dialogue.querySelector('.step-form');

		step_form.parentNode.insertBefore(message_box, step_form);
	}

	_removeMessageBoxes() {
		this.dialogue.querySelectorAll('.overlay-dialogue-body .msg-bad').forEach(message_box => message_box.remove());
	}
};

//# sourceURL=media.test.edit.js.php
