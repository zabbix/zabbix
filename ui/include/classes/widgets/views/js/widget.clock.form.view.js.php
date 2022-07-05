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


window.widget_clock_form = new class {

	init() {
		this.form = document.getElementById('widget-dialogue-form');
		this.time_type = document.getElementById('time_type');
		this.clock_type = document.getElementById('clock_type');

		this.show_date = document.getElementById('show_1');
		this.show_time = document.getElementById('show_2');
		this.show_tzone = document.getElementById('show_3');

		this.advanced_configuration = document.getElementById('adv_conf');

		for (const colorpicker of this.form.querySelectorAll('.<?= ZBX_STYLE_COLOR_PICKER ?> input')) {
			$(colorpicker).colorpicker({
				appendTo: '.overlay-dialogue-body',
				use_default: true,
				onUpdate: window.setIndicatorColor
			});
		}

		this.time_type.addEventListener('change', () => {
			ZABBIX.Dashboard.reloadWidgetProperties();
			this.updateForm();
		});

		for (const checkbox of this.clock_type.querySelectorAll('input')) {
			checkbox.addEventListener('change', () => this.updateForm());
		}

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

		this.advanced_configuration.addEventListener('change', () => this.updateForm());

		this.updateForm();
	}

	updateForm() {
		const is_digital = this.clock_type.querySelector('input:checked').value == <?= WIDGET_CLOCK_TYPE_DIGITAL ?>;

		const show_date_row = is_digital && this.advanced_configuration.checked && this.show_date.checked;
		const show_time_row = is_digital && this.advanced_configuration.checked && this.show_time.checked;
		const show_tzone_row = is_digital && this.advanced_configuration.checked && this.show_tzone.checked;

		for (const element of this.form.querySelectorAll('.js-row-show, .js-row-adv-conf')) {
			element.style.display = is_digital ? '' : 'none';
		}

		for (const element of this.form.querySelectorAll('.js-row-bg-color')) {
			element.style.display = is_digital && this.advanced_configuration.checked ? '' : 'none';
		}

		for (const element of this.form.querySelectorAll('.js-row-date')) {
			element.style.display = show_date_row ? '' : 'none';
		}

		for (const element of this.form.querySelectorAll('.js-row-time')) {
			element.style.display = show_time_row ? '' : 'none';
		}

		for (const element of this.form.querySelectorAll('.js-row-tzone')) {
			element.style.display = show_tzone_row ? '' : 'none';
		}

		for (const element of this.form.querySelectorAll('.js-row-tzone-timezone, .js-row-tzone-format')) {
			element.style.display = this.time_type.value != <?= TIME_TYPE_HOST ?> ? '' : 'none';
		}
	}
};
