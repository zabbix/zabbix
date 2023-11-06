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


use Widgets\Honeycomb\Includes\WidgetForm;

?>

window.widget_honeycomb_form = new class {

	/**
	 * @type {HTMLFormElement}
	 */
	#form;

	init({thresholds_colors}) {
		this.#form = document.getElementById('widget-dialogue-form');

		colorPalette.setThemeColors(thresholds_colors);

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
				appendTo: ".overlay-dialogue-body",
				use_default: !colorpicker.name.includes('thresholds')
			});
		}

		this.#updateForm();
	}

	#updateForm() {
		const primary_show = document.getElementById(`show_${<?= WidgetForm::SHOW_PRIMARY ?>}`);
		const secondary_show = document.getElementById(`show_${<?= WidgetForm::SHOW_SECONDARY ?>}`);

		this.#updateLabelFields('primary', primary_show);
		this.#updateLabelFields('secondary', secondary_show)

		const filled_thresholds_count = this.#countFilledThresholds();

		document.getElementById('interpolation').disabled = filled_thresholds_count < 2;
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

	#updateLabelFields(type, show) {
		const is_label_size_custom = this.#form
			.querySelector(`[name="${type}_label_size_type"]:checked`).value == <?= WidgetForm::SIZE_CUSTOM ?>;
		const is_label_type_value = this.#form
			.querySelector(`[name="${type}_label_type"]:checked`).value == <?= WidgetForm::LABEL_TYPE_VALUE ?>;
		const units_show = document.getElementById(`${type}_label_units_show`);

		for (const element of this.#form.querySelectorAll(`.fields-group-${type}-label`)) {
			element.style.display = show.checked ? '' : 'none';

			for (const input of element.querySelectorAll('input, textarea')) {
				if (input.id === `${type}_label_custom_size`) {
					input.style.display = is_label_size_custom && show.checked ? '' : 'none';
					input.nextSibling.nodeValue = is_label_size_custom && show.checked ? '%' : '';
					input.disabled = !is_label_size_custom || !show.checked;
				}
				else {
					input.disabled = !show.checked;
				}
			}
		}

		for (const element of this.#form.querySelectorAll(`.js-${type}-text-field`)) {
			element.style.display = !is_label_type_value && show.checked ? '' : 'none';
		}

		document.getElementById(`${type}_label`).disabled = is_label_type_value || !show.checked;

		for (const element of this.#form.querySelectorAll(`.js-${type}-value-field`)) {
			element.style.display = is_label_type_value && show.checked ? '' : 'none';

			for (const input of element.querySelectorAll('input, z-select')) {
				if (input.id === `${type}_label_units` || input.id === `${type}_label_units_pos`) {
					input.disabled = !is_label_type_value || !show.checked || !units_show.checked;
				}
				else {
					input.disabled = !is_label_type_value || !show.checked;
				}
			}
		}

		if (document.activeElement === document.getElementById(`${type}_label_size_type_1`)) {
			document.getElementById(`${type}_label_custom_size`).focus();
		}
	}
}
