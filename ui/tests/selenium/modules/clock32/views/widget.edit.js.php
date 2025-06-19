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


use Modules\Clock2\Widget;

?>

window.widget_form = new class extends CWidgetForm {

	init() {
		this._form = this.getForm();
		this._time_type = document.getElementById('time_type');
		this._clock_type = document.getElementById('clock_type');

		this._show_date = document.getElementById('show_1');
		this._show_time = document.getElementById('show_2');
		this._show_tzone = document.getElementById('show_3');

		this._time_type.addEventListener('change', () => {
			this.reload();
			this.updateForm();
		});

		for (const checkbox of this._clock_type.querySelectorAll('input')) {
			checkbox.addEventListener('change', () => this.updateForm());
		}

		for (const checkbox of [this._show_date, this._show_time, this._show_tzone]) {
			checkbox.addEventListener('change', () => this.updateForm());
		}

		this.updateForm();
		this.ready();
	}

	updateForm() {
		const is_digital = this._clock_type.querySelector('input:checked').value == <?= Widget::TYPE_DIGITAL ?>;

		for (const element of this._form.querySelectorAll('.js-row-show')) {
			element.style.display = is_digital ? '' : 'none';
		}

		this._form.querySelector('.js-fieldset-adv-conf').style.display = is_digital ? 'contents' : 'none';

		if (is_digital) {
			for (const element of this._form.querySelectorAll('.fields-group-date')) {
				element.style.display = this._show_date.checked ? '' : 'none';
			}

			for (const element of this._form.querySelectorAll('.fields-group-time')) {
				element.style.display = this._show_time.checked ? '' : 'none';
			}

			for (const element of this._form.querySelectorAll('.fields-group-tzone')) {
				element.style.display = this._show_tzone.checked ? '' : 'none';
			}

			for (const element of this._form.querySelectorAll('.field-tzone-timezone, .field-tzone-format')) {
				element.style.display = this._time_type.value != <?= TIME_TYPE_HOST ?> ? '' : 'none';
			}
		}
	}
};
