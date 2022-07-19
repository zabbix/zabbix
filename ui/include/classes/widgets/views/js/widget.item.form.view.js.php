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


window.widget_item_form = new class {

	init() {
		this.show_description = document.getElementById(`show_${<?= WIDGET_ITEM_SHOW_DESCRIPTION ?>}`);
		this.show_value = document.getElementById(`show_${<?= WIDGET_ITEM_SHOW_VALUE ?>}`);
		this.show_time = document.getElementById(`show_${<?= WIDGET_ITEM_SHOW_TIME ?>}`);
		this.show_change_indicator = document.getElementById(`show_${<?= WIDGET_ITEM_SHOW_CHANGE_INDICATOR ?>}`);
		this.advance_configuration = document.getElementById('adv_conf');
		this.units_show = document.getElementById('units_show');

		this.description_row = document.getElementById('description-row');
		this.value_row = document.getElementById('value-row');
		this.time_row = document.getElementById('time-row');
		this.change_indicator_row = document.getElementById('change-indicator-row');
		this.bg_color_row = document.getElementById('bg-color-row');

		const show = [this.show_description, this.show_value, this.show_time, this.show_change_indicator];

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

		for (const checkbox of [this.advance_configuration, this.units_show]) {
			checkbox.addEventListener('change', () => {
				this.updateForm();
			});
		}

		document.querySelectorAll('#widget-dialogue-form .<?= ZBX_STYLE_COLOR_PICKER ?> input')
			.forEach((colorpicker) => {
				$(colorpicker).colorpicker({
					appendTo: ".overlay-dialogue-body",
					use_default: true,
					onUpdate: ['up_color', 'down_color', 'updown_color'].includes(colorpicker.name)
						? (color) => this.setIndicatorColor(colorpicker.name, color)
						: null
				});
			});

		this.updateForm();
	}

	updateForm() {
		this.description_row.style.display =
			(this.advance_configuration.checked && this.show_description.checked) ? '' : 'none';

		this.description_row.querySelectorAll('input, textarea').forEach((element) => {
			element.disabled = !this.advance_configuration.checked || !this.show_description.checked;
		});

		this.value_row.style.display = (this.advance_configuration.checked && this.show_value.checked) ? '' : 'none';
		this.value_row.querySelectorAll('input').forEach((element) => {
			element.disabled = !this.advance_configuration.checked || !this.show_value.checked;
		});

		this.time_row.style.display = (this.advance_configuration.checked && this.show_time.checked) ? '' : 'none';
		this.time_row.querySelectorAll('input').forEach((element) => {
			element.disabled = !this.advance_configuration.checked || !this.show_time.checked;
		});

		this.change_indicator_row.style.display =
			(this.advance_configuration.checked && this.show_change_indicator.checked) ? '' : 'none';

		this.change_indicator_row.querySelectorAll('input').forEach((element) => {
			element.disabled = !this.advance_configuration.checked || !this.show_change_indicator.checked;
		});

		this.bg_color_row.style.display = this.advance_configuration.checked ? '' : 'none';
		this.bg_color_row.querySelectorAll('input').forEach((element) => {
			element.disabled = !this.advance_configuration.checked;
		});

		document.querySelectorAll('#units, #units_pos, #units_size, #units_bold, #units_color').forEach((element) => {
			element.disabled = !this.units_show.checked;
		});
	}

	setIndicatorColor(name, color) {
		const indicator_ids = {
			up_color: 'change-indicator-up',
			down_color: 'change-indicator-down',
			updown_color: 'change-indicator-updown'
		};

		document.getElementById(indicator_ids[name])
			.querySelector("polygon").style.fill = (color !== '') ? `#${color}` : '';
	}
};
