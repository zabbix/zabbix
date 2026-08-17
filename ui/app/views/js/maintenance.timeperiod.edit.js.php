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


window.maintenance_timeperiod_edit = new class {

	/**
	 * @type {HTMLFormElement}
	 */
	form_element;

	/**
	 * @type {CForm}
	 */
	form;

	init({rules}) {
		this.overlay = overlays_stack.getById('maintenance-timeperiod-edit');
		this.dialogue = this.overlay.$dialogue[0];
		this.footer = this.overlay.$dialogue.$footer[0];
		this.form_element = this.overlay.$dialogue.$body[0].querySelector('form');
		this.form = new CForm(this.form_element, rules);

		// Update form field state according to the form data.
		this.form_element.querySelectorAll('[name="timeperiod_type"], [name="month_date_type"]').forEach((element) =>
			element.addEventListener('change', () => this.#update())
		);

		this.#update();

		document.getElementById('maintenance-timeperiod-form').style.display = '';
		this.form_element.querySelector('[name="timeperiod_type"]').focus();

		this.form.findFieldByName('period_hours')._field.onchange = () => {
			this.form.findFieldByName('period_minutes').setChanged();
			this.form.validateChanges(['period_minutes']);
		};

		this.form.findFieldByName('period_days')._field.onchange = () => {
			this.form.findFieldByName('period_minutes').setChanged();
			this.form.validateChanges(['period_minutes']);
		};

		this.footer.querySelector('.js-submit').addEventListener('click', () => this.#submit());
	}

	#update() {
		const timeperiod_type_value = this.form_element.querySelector('[name="timeperiod_type"]').value;
		const month_date_type_value = this.form_element.querySelector('[name="month_date_type"]:checked').value;

		this.form_element.querySelectorAll('.js-every-day').forEach((element) =>
			element.hidden = timeperiod_type_value != <?= TIMEPERIOD_TYPE_DAILY ?>
		);

		this.form_element.querySelectorAll('.js-every-week, .js-weekly-days').forEach((element) =>
			element.hidden = timeperiod_type_value != <?= TIMEPERIOD_TYPE_WEEKLY ?>
		);

		this.form_element.querySelectorAll('.js-months, .js-month-date-type').forEach((element) =>
			element.hidden = timeperiod_type_value != <?= TIMEPERIOD_TYPE_MONTHLY ?>
		);

		this.form_element.querySelectorAll('.js-every-dow, .js-monthly-days').forEach((element) =>
			element.hidden = timeperiod_type_value != <?= TIMEPERIOD_TYPE_MONTHLY ?>
				|| month_date_type_value != 1
		);

		this.form_element.querySelectorAll('.js-day').forEach((element) =>
			element.hidden = timeperiod_type_value != <?= TIMEPERIOD_TYPE_MONTHLY ?>
				|| month_date_type_value != 0
		);

		this.form_element.querySelectorAll('.js-start-date').forEach((element) =>
			element.hidden = timeperiod_type_value != <?= TIMEPERIOD_TYPE_ONETIME ?>
		);

		this.form_element.querySelectorAll('.js-hour-minute').forEach((element) =>
			element.hidden = timeperiod_type_value != <?= TIMEPERIOD_TYPE_DAILY ?>
				&& timeperiod_type_value != <?= TIMEPERIOD_TYPE_WEEKLY ?>
				&& timeperiod_type_value != <?= TIMEPERIOD_TYPE_MONTHLY ?>
		);
	}

	#submit() {
		this.#removePopupMessages();
		const fields = this.form.getAllValues();

		this.form.validateSubmit(fields)
			.then((result) => {
				if (!result) {
					this.overlay.unsetLoading();

					return;
				}

				const curl = new Curl('zabbix.php');

				curl.setArgument('action', 'maintenance.timeperiod.check');

				this.#post(curl.getUrl(), fields, (response) => {
					overlayDialogueDestroy(this.overlay.dialogueid);
					this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response.body}));
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
