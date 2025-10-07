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
		this._overlay = overlays_stack.getById('maintenance-timeperiod-edit');
		this._dialogue = this._overlay.$dialogue[0];
		this.form_element = this._overlay.$dialogue.$body[0].querySelector('form');
		this.form = new CForm(this.form_element, rules);

		// Update form field state according to the form data.
		this.form_element.querySelectorAll('[name="timeperiod_type"], [name="month_date_type"]').forEach((element) =>
			element.addEventListener('change', () => this.#update())
		);

		this.#update();

		document.getElementById('maintenance-timeperiod-form').style.display = '';
		this.form_element.querySelector('[name="timeperiod_type"]').focus();
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

	submit() {
		const fields = this.form.getAllValues();

		this.form.validateSubmit(fields)
			.then((result) => {
				if (!result) {
					this._overlay.unsetLoading();

					return;
				}

				const curl = new Curl('zabbix.php');

				curl.setArgument('action', 'maintenance.timeperiod.check');

				this.#post(curl.getUrl(), fields, (response) => {
					if ('form_errors' in response) {
						this.form.setErrors(response.form_errors, true, true);
						this.form.renderErrors();
					}
					else if ('error' in response) {
						throw {error: response.error};
					}
					else {
						overlayDialogueDestroy(this._overlay.dialogueid);
						this._dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response.body}));
					}
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
				if ('error' in response) {
					throw {error: response.error};
				}

				return response;
			})
			.then(success_callback)
			.catch((exception) => this.#ajaxExceptionHandler(exception))
			.finally(() => this._overlay.unsetLoading());
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
