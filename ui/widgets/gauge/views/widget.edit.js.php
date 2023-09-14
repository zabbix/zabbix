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


use Widgets\Gauge\Widget;

?>

window.widget_gauge_form = new class {

	init({thresholds_colors}) {
		this._form = document.getElementById('widget-dialogue-form');

		this._show_description = document.getElementById(`show_${<?= Widget::SHOW_DESCRIPTION ?>}`);
		this._show_value = document.getElementById(`show_${<?= Widget::SHOW_VALUE ?>}`);
		this._show_needle = document.getElementById(`show_${<?= Widget::SHOW_NEEDLE ?>}`);
		this._show_scale = document.getElementById(`show_${<?= Widget::SHOW_SCALE ?>}`);

		this._value_arc = document.getElementById('value_arc');
		this._units_show = document.getElementById('units_show');
		this._th_show_arc = document.getElementById('th_show_arc');

		const show = [this._show_description, this._show_value, this._show_needle, this._show_scale];

		for (const checkbox of show) {
			checkbox.addEventListener('change', (e) => {
				if (show.filter((checkbox) => checkbox.checked && !checkbox.disabled).length > 0) {
					this.updateForm();
				}
				else {
					e.target.checked = true;
				}
			});
		}

		const checkboxes = [this._value_arc, this._units_show, this._th_show_arc];

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
		for (const element of this._form.querySelectorAll('.fields-group-description')) {
			element.style.display = this._show_description.checked ? '' : 'none';

			for (const input of element.querySelectorAll('input, textarea')) {
				input.disabled = !this._show_description.checked;
			}
		}

		for (const element of this._form.querySelectorAll('.fields-group-value')) {
			element.style.display = this._show_value.checked ? '' : 'none';

			for (const input of element.querySelectorAll('input')) {
				if (input.id === 'value_arc_size') {
					input.disabled = !this._value_arc.checked || !this._show_value.checked;
				}
				else {
					input.disabled = !this._show_value.checked;
				}
			}
		}

		for (const element of
			document.querySelectorAll('#units, #units_pos, #units_size, #units_bold, #units_color')) {
			element.disabled = !this._show_value.checked || !this._units_show.checked;
		}

		for (const element of this._form.querySelectorAll('.fields-group-needle')) {
			element.style.display = this._show_needle.checked ? '' : 'none';
		}

		document.getElementById('needle_color').disabled = !this._show_needle.checked
			|| ((!this._th_show_arc.checked || this._th_show_arc.disabled) && (!this._value_arc.checked || this._value_arc.disabled));

		for (const element of this._form.querySelectorAll('.fields-group-scale')) {
			element.style.display = !this._show_scale.checked ? 'none' : '';

			for (const input of element.querySelectorAll('input')) {
				if (input.id === 'scale_show_units') {
					input.disabled = !this._units_show.checked || !this._show_scale.checked
						|| ((!this._th_show_arc.checked || this._th_show_arc.disabled)
							&& (!this._value_arc.checked || this._value_arc.disabled));
				}
				else {
					input.disabled = !this._show_scale.checked
						|| ((!this._th_show_arc.checked || this._th_show_arc.disabled)
							&& (!this._value_arc.checked || this._value_arc.disabled));
				}
			}
		}

		const filled_thresholds_count = this.countFilledThresholds();

		for (const element of document.querySelectorAll('#th_show_arc, #th_arc_size')) {
			element.disabled = filled_thresholds_count === 0;
		}

		document.getElementById('th_arc_size').disabled = filled_thresholds_count === 0
			|| (!this._th_show_arc.checked || this._th_show_arc.disabled);

		document.getElementById('th_show_labels').disabled = filled_thresholds_count === 0
			|| ((!this._th_show_arc.checked || this._th_show_arc.disabled)
				&& (!this._value_arc.checked || this._value_arc.disabled));

		this._show_needle.disabled = (!this._th_show_arc.checked || this._th_show_arc.disabled)
			&& (!this._value_arc.checked || this._value_arc.disabled);

		this._show_scale.disabled = (!this._th_show_arc.checked || this._th_show_arc.disabled)
			&& (!this._value_arc.checked || this._value_arc.disabled);
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
