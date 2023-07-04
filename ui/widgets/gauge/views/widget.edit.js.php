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


?>

window.widget_gauge_form = new class {

	init({thresholds_colors}) {
		this._form = document.getElementById('widget-dialogue-form');

		this._value_arc = document.getElementById('value_arc');
		this._units_show = document.getElementById('units_show');
		this._needle_show = document.getElementById('needle_show');
		this._scale_show = document.getElementById('scale_show');
		this._th_show_arc = document.getElementById('th_show_arc');

		const checkboxes = [
			this._value_arc,
			this._units_show,
			this._needle_show,
			this._scale_show,
			this._th_show_arc
		];

		for (const checkbox of checkboxes) {
			checkbox.addEventListener('change', () => this.updateForm());
		}

		$thresholds_table.on('afterremove.dynamicRows', () => this.updateForm());

		this._thresholds_table = $thresholds_table[0];

		this._thresholds_table.addEventListener('input', (e) => {
			if (e.target.matches('input[name$="[threshold]"')) {
				this.updateForm();
			}
		});

		for (const colorpicker of this._form.querySelectorAll('.<?= ZBX_STYLE_COLOR_PICKER ?> input')) {
			$(colorpicker).colorpicker({
				appendTo: '.overlay-dialogue-body',
				use_default: !colorpicker.name.includes('thresholds')
			});
		}

		colorPalette.setThemeColors(thresholds_colors);

		this.updateForm();
	}

	updateForm() {
		const filled_thresholds_count = this.countFilledThresholds();

		for (const element of document.querySelectorAll('#th_show_arc, #th_arc_size')) {
			element.disabled = filled_thresholds_count === 0;
		}

		document.getElementById('value_arc_size').disabled = !this._value_arc.checked;

		document.getElementById('scale_show').disabled = (!this._th_show_arc.checked || this._th_show_arc.disabled)
			&& !this._value_arc.checked;

		document.getElementById('scale_show_units').disabled = !this._units_show.checked || !this._scale_show.checked
			|| this._scale_show.disabled;

		for (const element of document.querySelectorAll('#scale_size, #scale_decimal_places')) {
			element.disabled = !this._scale_show.checked || this._scale_show.disabled;
		}

		for (const element of
				document.querySelectorAll('#units, #units_pos, #units_size, #units_bold, #units_color')) {
			element.disabled = !this._units_show.checked;
		}

		document.getElementById('th_arc_size').disabled = filled_thresholds_count === 0
			|| (!this._th_show_arc.checked || this._th_show_arc.disabled);

		document.getElementById('th_show_labels').disabled = filled_thresholds_count === 0
			|| ((!this._th_show_arc.checked || this._th_show_arc.disabled) && !this._value_arc.checked);

		for (const element of document.querySelectorAll('#needle_show, #needle_color')) {
			element.disabled = (!this._th_show_arc.checked || this._th_show_arc.disabled) && !this._value_arc.checked;
		}

		document.getElementById('needle_color').disabled = !this._needle_show.checked || this._needle_show.disabled
			|| ((!this._th_show_arc.checked || this._th_show_arc.disabled) && !this._value_arc.checked);
	}

	countFilledThresholds() {
		let count = 0;

		for (const threshold of this._thresholds_table.querySelectorAll('.form_row input[name$="[threshold]"')) {
			if (threshold.value.trim() !== '') {
				count++;
			}
		}

		return count;
	}
};
