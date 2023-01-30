<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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


use Widgets\Clock\Widget;

?>

window.widget_clock_form = new class {

	init() {
		this._form = document.getElementById('widget-dialogue-form');
		this._time_type = document.getElementById('time_type');
		this._clock_type = document.getElementById('clock_type');

		this._show_date = document.getElementById('show_1');
		this._show_time = document.getElementById('show_2');
		this._show_tzone = document.getElementById('show_3');

		this._advanced_configuration = document.getElementById('adv_conf');

		for (const colorpicker of this._form.querySelectorAll('.<?= ZBX_STYLE_COLOR_PICKER ?> input')) {
			$(colorpicker).colorpicker({
				appendTo: '.overlay-dialogue-body',
				use_default: true,
				onUpdate: window.setIndicatorColor
			});
		}

		this._time_type.addEventListener('change', () => {
			ZABBIX.Dashboard.reloadWidgetProperties();
			this.updateForm();
		});

		for (const checkbox of this._clock_type.querySelectorAll('input')) {
			checkbox.addEventListener('change', () => this.updateForm());
		}

		const show = [this._show_date, this._show_time, this._show_tzone];

		for (const checkbox of show) {
			checkbox.addEventListener('change', (e) => {
				if (show.filter((checkbox) => checkbox.checked).length > 0) {
					this.updateForm();
				}
				else {
					e.target.checked = true;
				}
			});
		}

		this._advanced_configuration.addEventListener('change', () => this.updateForm());

		this.updateForm();
	}

	updateForm() {
		const is_digital = this._clock_type.querySelector('input:checked').value == <?= Widget::TYPE_DIGITAL ?>;

		const show_date_row = is_digital && this._advanced_configuration.checked && this._show_date.checked;
		const show_time_row = is_digital && this._advanced_configuration.checked && this._show_time.checked;
		const show_tzone_row = is_digital && this._advanced_configuration.checked && this._show_tzone.checked;

		for (const element of this._form.querySelectorAll('.js-row-show, .js-row-adv-conf')) {
			element.style.display = is_digital ? '' : 'none';
		}

		for (const element of this._form.querySelectorAll('.js-row-bg-color')) {
			element.style.display = is_digital && this._advanced_configuration.checked ? '' : 'none';
		}

		for (const element of this._form.querySelectorAll('.fields-group-date')) {
			element.style.display = show_date_row ? '' : 'none';
		}

		for (const element of this._form.querySelectorAll('.fields-group-time')) {
			element.style.display = show_time_row ? '' : 'none';
		}

		for (const element of this._form.querySelectorAll('.fields-group-tzone')) {
			element.style.display = show_tzone_row ? '' : 'none';
		}

		for (const element of this._form.querySelectorAll('.field-tzone-timezone, .field-tzone-format')) {
			element.style.display = this._time_type.value != <?= TIME_TYPE_HOST ?> ? '' : 'none';
		}
	}
};
