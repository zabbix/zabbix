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

	/**
	 * @type {HTMLFormElement}
	 */
	#form;

	init({thresholds_colors}) {
		this.#form = document.getElementById('widget-dialogue-form');

		jQuery(this.#form)
			.on('change', 'input', (e) => this.#updateForm(e.target));

		$thresholds_table.on('afterremove.dynamicRows', () => this.#updateForm());

		for (const colorpicker of this.#form.querySelectorAll('.<?= ZBX_STYLE_COLOR_PICKER ?> input')) {
			$(colorpicker).colorpicker({
				appendTo: '.overlay-dialogue-body',
				use_default: !colorpicker.name.includes('thresholds')
			});
		}

		colorPalette.setThemeColors(thresholds_colors);

		this.#updateForm();
	}

	#updateForm(trigger = null) {
		const show_description = document.getElementById(`show_${<?= Widget::SHOW_DESCRIPTION ?>}`);
		const show_value = document.getElementById(`show_${<?= Widget::SHOW_VALUE ?>}`);
		const show_needle = document.getElementById(`show_${<?= Widget::SHOW_NEEDLE ?>}`);
		const show_scale = document.getElementById(`show_${<?= Widget::SHOW_SCALE ?>}`);
		const value_arc = document.getElementById('value_arc');
		const units_show = document.getElementById('units_show');
		const th_show_arc = document.getElementById('th_show_arc');
		const filled_thresholds_count = this.#countFilledThresholds();

		for (const element of document.querySelectorAll('#th_show_arc, #th_arc_size')) {
			element.disabled = filled_thresholds_count === 0;
		}

		show_needle.disabled = (!th_show_arc.checked || th_show_arc.disabled)
			&& (!value_arc.checked || !show_value.checked);

		show_scale.disabled = (!th_show_arc.checked || th_show_arc.disabled)
			&& (!value_arc.checked || !show_value.checked);

		if ([show_description, show_value, show_needle, show_scale].filter((checkbox) =>
				checkbox.checked && !checkbox.disabled).length == 0) {
			trigger.checked = true;
			this.#updateForm(trigger);
		}

		for (const element of this.#form.querySelectorAll('.fields-group-description')) {
			element.style.display = show_description.checked ? '' : 'none';

			for (const input of element.querySelectorAll('input, textarea')) {
				input.disabled = !show_description.checked;
			}
		}

		for (const element of this.#form.querySelectorAll('.fields-group-value')) {
			element.style.display = show_value.checked ? '' : 'none';

			for (const input of element.querySelectorAll('input')) {
				if (input.id === 'value_arc_size') {
					input.disabled = !value_arc.checked || !show_value.checked;
				}
				else {
					input.disabled = !show_value.checked;
				}
			}
		}

		for (const element of
			document.querySelectorAll('#units, #units_pos, #units_size, #units_bold, #units_color')) {
			element.disabled = !show_value.checked || !units_show.checked;
		}

		for (const element of this.#form.querySelectorAll('.fields-group-needle')) {
			element.style.display = show_needle.checked ? '' : 'none';
		}

		document.getElementById('needle_color').disabled = !show_needle.checked
			|| ((!th_show_arc.checked || th_show_arc.disabled) && (!value_arc.checked || value_arc.disabled));

		for (const element of this.#form.querySelectorAll('.fields-group-scale')) {
			element.style.display = !show_scale.checked ? 'none' : '';

			for (const input of element.querySelectorAll('input')) {
				if (input.id === 'scale_show_units') {
					input.disabled = !units_show.checked || !show_scale.checked
						|| ((!th_show_arc.checked || th_show_arc.disabled)
							&& (!value_arc.checked || value_arc.disabled));
				}
				else {
					input.disabled = !show_scale.checked
						|| ((!th_show_arc.checked || th_show_arc.disabled)
							&& (!value_arc.checked || value_arc.disabled));
				}
			}
		}

		document.getElementById('th_arc_size').disabled = filled_thresholds_count === 0
			|| (!th_show_arc.checked || th_show_arc.disabled);

		document.getElementById('th_show_labels').disabled = filled_thresholds_count === 0
			|| ((!th_show_arc.checked || th_show_arc.disabled)
				&& (!value_arc.checked || value_arc.disabled));
	}

	#countFilledThresholds() {
		let count = 0;

		for (const threshold of $thresholds_table[0].querySelectorAll('.form_row input[name$="[threshold]"')) {
			if (threshold.value.trim() !== '') {
				count++;
			}
		}

		return count;
	}
};
