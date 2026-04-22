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

window.sla_edit_popup = new class {

	init({rules, service_tags, excluded_downtimes}) {
		this.overlay = overlays_stack.getById('sla.edit');
		this.dialogue = this.overlay.$dialogue[0];
		this.footer = this.overlay.$dialogue.$footer[0];

		this.form_element = this.overlay.$dialogue.$body[0].querySelector('form');
		this.form = new CForm(this.form_element, rules);

		this.excluded_downtime_template = new Template(
			document.getElementById('excluded-downtime-tmpl').innerHTML
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
				element.addEventListener('change', () => this._update());
			});

		// Setup Problem tags.
		const tag_table = document.getElementById('service-tags');

		jQuery(tag_table).dynamicRows({
			template: '#service-tag-row-tmpl',
			rows: service_tags,
			allow_empty: true
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
					const row = e.target.closest('tr');

					this._removeRelatedErrorContainer(row);
					row.remove();
				}
			});

		this._update();

		this.form_element.style.display = '';
		this.overlay.recoverFocus();
	}

	_update() {
		const schedule = document.getElementById('schedule');
		const schedule_mode = document.querySelector('#schedule_mode input:checked').value;

		schedule.style.display = schedule_mode == <?= CSlaHelper::SCHEDULE_MODE_CUSTOM ?> ? '' : 'none';

		schedule.querySelectorAll('input[type="checkbox"]').forEach((element, i) => {
			schedule
				.querySelector(`input[name="schedule_periods[${i}][period]"]`)
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
		this._removeRelatedErrorContainer(row);

		row.insertAdjacentHTML('afterend', this.excluded_downtime_template.evaluate(excluded_downtime));
		row.remove();
	}

	_removeRelatedErrorContainer(row) {
		const next_sibling = row.nextElementSibling;

		if (next_sibling && next_sibling.classList.contains('error-container-row')) {
			next_sibling.remove();
		}
	}

	clone({title, buttons, rules}) {
		document.getElementById('slaid').remove();

		this.form.reload(rules);

		this.overlay.unsetLoading();
		this.overlay.setProperties({title, buttons});
		this.overlay.recoverFocus();
		this.overlay.containFocus();
	}

	delete() {
		this.#removePopupMessages();
		this.overlay.setLoading();

		const curl = new Curl('zabbix.php');
		curl.setArgument('action', 'sla.delete');
		curl.setArgument(CSRF_TOKEN_NAME, <?= json_encode(CCsrfTokenHelper::get('sla')) ?>);

		this.#post(curl.getUrl(), {slaids: [document.getElementById('slaid').value]});
	}

	submit() {
		this.#removePopupMessages();

		const fields = this.form.getAllValues();
		this.overlay.setLoading();

		if ('service_tags' in fields) {
			for (const key in fields.service_tags) {
				const service_tag = fields.service_tags[key];

				if (service_tag.tag === '' && service_tag.value === '') {
					delete fields.service_tags[key];
				}
			}
		}

		const curl = new Curl('zabbix.php');
		curl.setArgument('action', 'slaid' in fields ? 'sla.update' : 'sla.create');

		this.form.validateSubmit(fields)
			.then((result) => {
				if (!result) {
					this.overlay.unsetLoading();

					return;
				}

				this.#post(curl.getUrl(), fields);
			});
	}

	#post(url, data) {
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

				overlayDialogueDestroy(this.overlay.dialogueid);

				this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
			})
			.catch((exception) => this.#ajaxExceptionHandler(exception))
			.finally(() => this.overlay.unsetLoading());
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
