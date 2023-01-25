<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/
?>


window.maintenance_period_edit = new class {

	init() {
		this.overlay = overlays_stack.getById('maintenance_period_edit');
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');

		// Update form fields according to the form data.
		this._updateFields(document.getElementById('timeperiod_type').value);
		document.getElementById('timeperiod_type').addEventListener('change', (e) => {
			this._updateFields(e.target.value);
		});
		document.getElementById('row_timeperiod_date').addEventListener('change', () => {
			this._updateFields(document.getElementById('timeperiod_type').value);
		});
	}

	_updateFields(timeperiod_type) {
		this.form
			.querySelectorAll('.form-field, label')
			.forEach((element) => {
				element.setAttribute('style', 'display: none;');
			});
		this.form
			.querySelectorAll('[id="row_timeperiod_type"], [for="label-timeperiod-type"], ' +
				'[id="row_timeperiod_period_length"], [for="period_days"], [for="label-period-hours"], ' +
				'[for="label-period-minutes"]'
			)
			.forEach((element) => {
				element.removeAttribute('style', 'display: none;');
			});

		switch (timeperiod_type) {
			case '<?= TIMEPERIOD_TYPE_ONETIME ?>':
				this.form
					.querySelectorAll('[id="row_timepreiod_start_date"], [for="start_date"]')
					.forEach((element) => {
						element.removeAttribute('style', 'display: none;');
					});
				break;

			case '<?= TIMEPERIOD_TYPE_DAILY ?>':
				this.form
					.querySelectorAll('[id="row_timeperiod_every_day"], [for="every_day"], ' +
						'[id="row_timeperiod_period_at_hours_minutes"], [for="hour"]'
					)
					.forEach((element) => {
						element.removeAttribute('style', 'display: none;');
					});
				break;

			case '<?= TIMEPERIOD_TYPE_WEEKLY ?>':
				this.form
					.querySelectorAll('[id="row_timeperiod_every_week"], [for="every_week"], ' +
						'[id="row_timeperiod_dayofweek"], [for^="days"], ' +
						'[id="row_timeperiod_period_at_hours_minutes"], [for="hour"]'
					)
					.forEach((element) => {
						element.removeAttribute('style', 'display: none;');
					});
				break;

			case '<?= TIMEPERIOD_TYPE_MONTHLY ?>':
				this.form
					.querySelectorAll('[id="row_timeperiod_months"], [for^="months"], [id="row_timeperiod_date"], ' +
						'[for^="month_date_type"], [id="row_timeperiod_period_at_hours_minutes"], [for="hour"]'
					)
					.forEach((element) => {
						element.removeAttribute('style', 'display: none;');
					});

				var date = this.form.querySelector('[id^=month_date_type_]:checked').value;

				if (date == 0) {
					this.form
						.querySelectorAll('[id="row_timeperiod_day"], [for="day"]')
						.forEach((element) => {
							element.removeAttribute('style', 'display: none;');
						});
				}
				else {
					this.form
						.querySelectorAll('[id="row_timeperiod_week"], [for="label-every-dow"], ' +
							'[id="row_timeperiod_week_days"], [for^="monthly_days_"]'
						)
						.forEach((element) => {
							element.removeAttribute('style', 'display: none;');
						});
				}
				break;
		}
	}

	submit() {
		const fields = getFormFields(this.form);

		fields.name = fields.start_date.trim();

		switch (fields.timeperiod_type) {
			case '<?= TIMEPERIOD_TYPE_ONETIME ?>':
				break;

			case '<?= TIMEPERIOD_TYPE_DAILY ?>':
				fields.every = document.getElementById('every_day').value;
				break;

			case '<?= TIMEPERIOD_TYPE_WEEKLY ?>':
				fields.every = document.getElementById('every_week').value;
				break;

			case '<?= TIMEPERIOD_TYPE_MONTHLY ?>':
				fields.every = document.getElementById('every_dow').value;
				break;
		}

		this.overlay.setLoading();

		const curl = new Curl('zabbix.php', false);
		curl.setArgument('action', 'maintenance.period.check');

		this._post(curl.getUrl(), fields, (response) => {
			overlayDialogueDestroy(this.overlay.dialogueid);

			this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response.body}));
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
			.finally(() => {
				this.overlay.unsetLoading();
			});
	}
};
