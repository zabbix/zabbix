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

		this.#form.addEventListener('change', (e) => this.#updateForm(e.target));

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

	#updateForm(trigger = null) {
		const primary_show = document.getElementById(`show_${<?= WidgetForm::SHOW_PRIMARY ?>}`);
		const secondary_show = document.getElementById(`show_${<?= WidgetForm::SHOW_SECONDARY ?>}`);

		if ([primary_show, secondary_show].filter((checkbox) =>
			checkbox.checked && !checkbox.disabled).length === 0) {
			trigger.checked = true;
			this.#updateForm(trigger);
		}

		for (const element of this.#form.querySelectorAll('.fields-group-primary')) {
			element.style.display = primary_show.checked ? '' : 'none';

			for (const input of element.querySelectorAll('input, textarea')) {
				input.disabled = !primary_show.checked;
			}
		}

		const is_primary_size_custom = this.#form
			.querySelector('[name="primary_size_type"]:checked').value == <?= WidgetForm::SIZE_CUSTOM ?>;
		const primary_size_input = document.getElementById('primary_custom_input');

		primary_size_input.disabled = !is_primary_size_custom || !primary_show.checked;
		primary_size_input.style.display = is_primary_size_custom && primary_show.checked ? '' : 'none';
		primary_size_input.nextSibling.nodeValue = is_primary_size_custom && primary_show.checked ? ' %' : '';

		if (document.activeElement === document.getElementById('primary_size_type_1')) {
			primary_size_input.focus();
		}

		for (const element of this.#form.querySelectorAll('.fields-group-secondary')) {
			element.style.display = secondary_show.checked ? '' : 'none';

			for (const input of element.querySelectorAll('input, textarea')) {
				input.disabled = !secondary_show.checked;
			}
		}

		const is_secondary_size_custom = this.#form
			.querySelector('[name="secondary_size_type"]:checked').value == <?= WidgetForm::SIZE_CUSTOM ?>;
		const secondary_size_input = document.getElementById('secondary_custom_input');

		secondary_size_input.disabled = !is_secondary_size_custom || !secondary_show.checked;
		secondary_size_input.style.display = is_secondary_size_custom && secondary_show.checked ? '' : 'none';
		secondary_size_input.nextSibling.nodeValue = is_secondary_size_custom && secondary_show.checked ? ' %' : '';

		if (document.activeElement === document.getElementById('secondary_size_type_1')) {
			secondary_size_input.focus();
		}

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
}
