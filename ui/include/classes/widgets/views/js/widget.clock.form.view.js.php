<?php declare(strict_types = 1);
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


window.widget_clock_form = new class {

	init(options) {
		this.form = document.getElementById(options.form_id);

		this.time_type = this.form.querySelector('#time_type');

		this.clock_type_row = this.form.querySelector('#clock_type');

		this.show_row = this.form.querySelector('#show-row');

		this.show_date = this.form.querySelector('#show_1');
		this.show_time = this.form.querySelector('#show_2');
		this.show_tzone = this.form.querySelector('#show_3');

		const show = [this.show_date, this.show_time, this.show_tzone];

		this.advance_configuration_row = this.form.querySelector('#adv-conf-row');
		this.advance_configuration = this.form.querySelector('#adv_conf');

		this.bg_color_row = this.form.querySelector('#bg-color-row');

		this.date_row = this.form.querySelector('#date-row');
		this.time_row = this.form.querySelector('#time-row');
		this.tzone_row = this.form.querySelector('#tzone-row');

		this.form
			.querySelectorAll('.<?= ZBX_STYLE_COLOR_PICKER ?> input')
			.forEach((colorpicker) => {
				$(colorpicker).colorpicker({
					appendTo: '.overlay-dialogue-body',
					use_default: true,
					onUpdate: window.setIndicatorColor
				});
			});

		this.time_type.addEventListener('change', (e) => {
			ZABBIX.Dashboard.reloadWidgetProperties();
			this.updateForm();
		});

		[...this.clock_type_row.querySelectorAll('input')].map((checkbox) => {
			checkbox.addEventListener('change', (e) => this.updateForm());
		});

		this.advance_configuration.addEventListener('change', (e) => this.updateForm());

		show.map((checkbox) => {
			checkbox.addEventListener('change', (e) => {
				if (show.filter((checkbox) => checkbox.checked).length > 0) {
					this.updateForm();
				}
				else {
					e.target.checked = true;
				}
			});
		});

		this.updateForm();
	}

	updateForm() {
		const is_digital = this
							.form
							.querySelector('input[name="clock_type"]:checked')
							.value == <?= WIDGET_CLOCK_TYPE_DIGITAL ?>;;

		const show_date_row = is_digital && this.advance_configuration.checked && this.show_date.checked;
		const show_time_row = is_digital && this.advance_configuration.checked && this.show_time.checked;
		const show_tzone_row = is_digital && this.advance_configuration.checked && this.show_tzone.checked;

		this
			.show_row
			.classList
			.toggle('<?= ZBX_STYLE_DISPLAY_NONE ?>', !is_digital);

		this
			.advance_configuration_row
			.classList
			.toggle('<?= ZBX_STYLE_DISPLAY_NONE ?>', !is_digital);

		this
			.bg_color_row
			.classList
			.toggle('<?= ZBX_STYLE_DISPLAY_NONE ?>', !is_digital || (is_digital && !this.advance_configuration.checked));

		this
			.date_row
			.classList
			.toggle('<?= ZBX_STYLE_DISPLAY_NONE ?>', !show_date_row);

		this
			.time_row
			.classList
			.toggle('<?= ZBX_STYLE_DISPLAY_NONE ?>', !show_time_row);

		this
			.tzone_row
			.classList
			.toggle('<?= ZBX_STYLE_DISPLAY_NONE ?>', !show_tzone_row);

		const is_time_type_host = this.time_type.value == <?= TIME_TYPE_HOST ?>;

		const timezone_settings = this
									.tzone_row
									.querySelectorAll('label[for="tzone_timezone"], .field-timezone, label[for="tzone_format"], .field-format');

		[...timezone_settings].map((elem) => {
			elem.classList.toggle('<?= ZBX_STYLE_DISPLAY_NONE ?>', is_time_type_host);
		});
	}
}();
