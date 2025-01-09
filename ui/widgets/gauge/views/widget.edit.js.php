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


use Widgets\Gauge\Widget;

?>

window.widget_gauge_form = new class {

	/**
	 * @type {HTMLFormElement}
	 */
	#form;

	init({thresholds_colors}) {
		this.#form = document.getElementById('widget-dialogue-form');

		this.#form.addEventListener('change', () => this.#updateForm());

		$thresholds_table.on('afterremove.dynamicRows', () => this.#updateForm());

		this._thresholds_table = $thresholds_table[0];

		this._thresholds_table.addEventListener('input', (e) => {
			if (e.target.matches('input[name$="[threshold]"')) {
				this.#updateForm();
			}
		});

		for (const colorpicker of this.#form.querySelectorAll('.<?= ZBX_STYLE_COLOR_PICKER ?> input')) {
			$(colorpicker).colorpicker({
				appendTo: '.overlay-dialogue-body',
				use_default: !colorpicker.name.includes('thresholds')
			});
		}

		colorPalette.setThemeColors(thresholds_colors);

		this.#updateForm();
	}

	#updateForm() {
		const description_show = document.getElementById(`show_${<?= Widget::SHOW_DESCRIPTION ?>}`);
		const value_show = document.getElementById(`show_${<?= Widget::SHOW_VALUE ?>}`);
		const needle_show = document.getElementById(`show_${<?= Widget::SHOW_NEEDLE ?>}`);
		const scale_show = document.getElementById(`show_${<?= Widget::SHOW_SCALE ?>}`);
		const value_arc_show = document.getElementById(`show_${<?= Widget::SHOW_VALUE_ARC ?>}`);
		const units_show = document.getElementById('units_show');
		const th_show_arc = document.getElementById('th_show_arc');
		const filled_thresholds_count = this.#countFilledThresholds();

		for (const element of document.querySelectorAll('#th_show_arc, #th_arc_size')) {
			element.disabled = filled_thresholds_count === 0;
		}

		needle_show.disabled = (!th_show_arc.checked || th_show_arc.disabled) && !value_arc_show.checked;

		scale_show.disabled = (!th_show_arc.checked || th_show_arc.disabled) && !value_arc_show.checked;

		for (const element of this.#form.querySelectorAll('.fields-group-description')) {
			element.style.display = description_show.checked ? '' : 'none';

			for (const input of element.querySelectorAll('input, textarea')) {
				input.disabled = !description_show.checked;
			}
		}

		for (const element of this.#form.querySelectorAll('.fields-group-value')) {
			element.style.display = value_show.checked ? '' : 'none';

			for (const input of element.querySelectorAll('input')) {
				input.disabled = !value_show.checked;
			}
		}

		for (const element of
			document.querySelectorAll('#units, #units_pos, #units_size, #units_bold, #units_color')) {
			element.disabled = !value_show.checked || !units_show.checked;
		}

		for (const element of this.#form.querySelectorAll('.fields-group-value-arc')) {
			element.style.display = value_arc_show.checked ? '' : 'none';
		}

		document.getElementById('value_arc_size').disabled = !value_arc_show.checked;

		for (const element of this.#form.querySelectorAll('.fields-group-needle')) {
			element.style.display = needle_show.checked ? '' : 'none';
		}

		document.getElementById('needle_color').disabled = !needle_show.checked
			|| ((!th_show_arc.checked || th_show_arc.disabled) && !value_arc_show.checked);

		for (const element of this.#form.querySelectorAll('.fields-group-scale')) {
			element.style.display = !scale_show.checked ? 'none' : '';

			for (const input of element.querySelectorAll('input')) {
				if (input.id === 'scale_show_units') {
					input.disabled = !units_show.checked || !scale_show.checked
						|| ((!th_show_arc.checked || th_show_arc.disabled) && !value_arc_show.checked);
				}
				else {
					input.disabled = !scale_show.checked
						|| ((!th_show_arc.checked || th_show_arc.disabled) && !value_arc_show.checked);
				}
			}
		}

		document.getElementById('th_arc_size').disabled = filled_thresholds_count === 0
			|| (!th_show_arc.checked || th_show_arc.disabled);

		document.getElementById('th_show_labels').disabled = filled_thresholds_count === 0
			|| ((!th_show_arc.checked || th_show_arc.disabled) && !value_arc_show.checked);
	}

	#countFilledThresholds() {
		let count = 0;

		for (const threshold of this._thresholds_table.querySelectorAll('.form_row input[name$="[threshold]"')) {
			if (threshold.value.trim() !== '') {
				count++;
			}
		}

		return count;
	}
};
