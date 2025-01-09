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

		this.#form.addEventListener('change', e => {
			this.#updateForm();

			if (e.target.id === 'primary_label_size_type_<?= WidgetForm::LABEL_SIZE_CUSTOM ?>') {
				document.getElementById('primary_label_size').focus();
			}

			if (e.target.id === 'secondary_label_size_type_<?= WidgetForm::LABEL_SIZE_CUSTOM ?>') {
				document.getElementById('secondary_label_size').focus();
			}
		});

		$thresholds_table.on('afterremove.dynamicRows', () => this.#updateForm());

		this._thresholds_table = $thresholds_table[0];

		this._thresholds_table.addEventListener('input', e => {
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

		this.#updateForm();
	}

	#updateForm() {
		const show_primary_label = document.getElementById(`show_${<?= WidgetForm::SHOW_PRIMARY_LABEL ?>}`).checked;
		const show_secondary_label = document.getElementById(`show_${<?= WidgetForm::SHOW_SECONDARY_LABEL ?>}`).checked;

		this.#updateLabelFields('fields-group-primary-label', show_primary_label);
		this.#updateLabelFields('fields-group-secondary-label', show_secondary_label);

		const interpolation = document.getElementById('interpolation');

		interpolation.disabled = this.#countFilledThresholds() < 2;

		if (this.#countFilledThresholds() < 2) {
			interpolation.checked = false;
		}
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

	#updateLabelFields(row_class, show) {
		const fields_group_label = this.#form.querySelector(
			`.<?= CFormGrid::ZBX_STYLE_FIELDS_GROUP_LABEL ?>.${row_class}`
		);
		const fields_group = this.#form.querySelector(`.<?= CFormGrid::ZBX_STYLE_FIELDS_GROUP ?>.${row_class}`);

		fields_group_label.style.display = show ? '' : 'none';
		fields_group.style.display = show ? '' : 'none';

		fields_group.querySelectorAll('input, textarea').forEach(element => element.disabled = !show);

		if (!show) {
			return;
		}

		const label_type_value = parseInt(fields_group.querySelector('.js-label-type input:checked').value);
		const label_size_type_value = parseInt(fields_group.querySelector('.js-label-size-type input:checked').value);
		const label_units_show_checked =
			fields_group.querySelector('.js-label-units-show input[type="checkbox"]').checked;

		fields_group.querySelectorAll('.js-label').forEach(
			element => element.style.display = label_type_value === <?= WidgetForm::LABEL_TYPE_TEXT ?> ? '' : 'none'
		);
		fields_group.querySelector('.js-label textarea').disabled =
			label_type_value !== <?= WidgetForm::LABEL_TYPE_TEXT ?>;

		fields_group.querySelectorAll('.js-label-decimal-places').forEach(
			element => element.style.display = label_type_value === <?= WidgetForm::LABEL_TYPE_VALUE ?> ? '' : 'none'
		);
		fields_group.querySelector('.js-label-decimal-places input').disabled =
			label_type_value !== <?= WidgetForm::LABEL_TYPE_VALUE ?>;

		fields_group.querySelector('.js-label-size').style.display =
			label_size_type_value === <?= WidgetForm::LABEL_SIZE_CUSTOM ?> ? '' : 'none';

		fields_group.querySelector('.js-label-size').nextSibling.textContent =
			label_size_type_value === <?= WidgetForm::LABEL_SIZE_CUSTOM ?> ? '%' : '';

		fields_group.querySelectorAll('.js-label-units-hr, .js-label-units-show, .js-label-units, .js-label-units-pos')
			.forEach(element => {
				element.style.display = label_type_value === <?= WidgetForm::LABEL_TYPE_VALUE ?>
					? ''
					: 'none';
			});

		fields_group.querySelectorAll('.js-label-units input, .js-label-units-pos z-select')
			.forEach(element => element.disabled = !label_units_show_checked);
	}
}
