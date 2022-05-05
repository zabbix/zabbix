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

	init() {
		this.time_type = document.getElementById('time_type');

		this.show_date = document.getElementById('show_1');
		this.show_time = document.getElementById('show_2');
		this.show_tzone = document.getElementById('show_3');

		this.advance_configuration = document.getElementById('adv_conf');

		document.querySelectorAll('#widget-dialogue-form .<?= ZBX_STYLE_COLOR_PICKER ?> input')
			.forEach((colorpicker) => {
				$(colorpicker).colorpicker({
					appendTo: '.overlay-dialogue-body',
					use_default: true,
					onUpdate: window.setIndicatorColor
				});
			});

		this.time_type.addEventListener('change', () => {
			ZABBIX.Dashboard.reloadWidgetProperties();
			this.updateForm();
		});

		for (const checkbox of document.getElementById('clock_type').querySelectorAll('input')) {
			checkbox.addEventListener('change', () => this.updateForm());
		}

		this.advance_configuration.addEventListener('change', () => this.updateForm());

		const show = [this.show_date, this.show_time, this.show_tzone];

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

		this.updateForm();
	}

	updateForm() {
		const is_digital = document.querySelector('#clock_type input:checked').value == <?= WIDGET_CLOCK_TYPE_DIGITAL ?>;

		const show_date_row = is_digital && this.advance_configuration.checked && this.show_date.checked;
		const show_time_row = is_digital && this.advance_configuration.checked && this.show_time.checked;
		const show_tzone_row = is_digital && this.advance_configuration.checked && this.show_tzone.checked;

		document.getElementById('show-row').classList.toggle('<?= ZBX_STYLE_DISPLAY_NONE ?>', !is_digital);
		document.getElementById('adv-conf-row').classList.toggle('<?= ZBX_STYLE_DISPLAY_NONE ?>', !is_digital);
		document.getElementById('bg-color-row').classList.toggle('<?= ZBX_STYLE_DISPLAY_NONE ?>', !is_digital
			|| !this.advance_configuration.checked);

		document.getElementById('date-row').classList.toggle('<?= ZBX_STYLE_DISPLAY_NONE ?>', !show_date_row);
		document.getElementById('time-row').classList.toggle('<?= ZBX_STYLE_DISPLAY_NONE ?>', !show_time_row);

		const tzone_row = document.getElementById('tzone-row');

		tzone_row.classList.toggle('<?= ZBX_STYLE_DISPLAY_NONE ?>', !show_tzone_row);

		const timezone_settings = tzone_row
			.querySelectorAll('label[for="tzone_timezone"], .field-timezone, label[for="tzone_format"], .field-format');

		for (const element of timezone_settings) {
			element.classList.toggle('<?= ZBX_STYLE_DISPLAY_NONE ?>', this.time_type.value == <?= TIME_TYPE_HOST ?>);
		}
	}
};
