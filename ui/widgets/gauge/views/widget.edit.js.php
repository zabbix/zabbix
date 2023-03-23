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
		const threshold_row_selector = '#' + $thresholds_table.attr('id') + ' .form_row';

		this._form = document.getElementById('widget-dialogue-form');
		this._advanced_configuration = document.getElementById('adv_conf');
		this._value_arc = document.getElementById('value_arc');
		this._units_show = document.getElementById('units_show');
		this._needle_show = document.getElementById('needle_show');
		this._minmax_show = document.getElementById('minmax_show');
		this._th_show_arc = document.getElementById('th_show_arc');
		this._th_rows_count = document.querySelectorAll(threshold_row_selector).length;

		jQuery('#itemid').on('change', () => this.updateWarningIcon());

		for (const colorpicker of this._form.querySelectorAll('.<?= ZBX_STYLE_COLOR_PICKER ?> input')) {
			$(colorpicker).colorpicker({
				appendTo: '.overlay-dialogue-body',
				use_default: !colorpicker.name.includes('thresholds')
			});
		}

		const checkboxes = [
			this._advanced_configuration,
			this._units_show,
			this._needle_show,
			this._minmax_show,
			this._th_show_arc,
			this._value_arc
		];

		for (const checkbox of checkboxes) {
			checkbox.addEventListener('change', () => this.updateForm());
		}

		$thresholds_table
			.on('afteradd.dynamicRows', () => {
				this._th_rows_count = document.querySelectorAll(threshold_row_selector).length;
				this.updateForm();
			})
			.on('afterremove.dynamicRows', () => {
				this._th_rows_count = document.querySelectorAll(threshold_row_selector).length;
				this.updateForm();
			});

		colorPalette.setThemeColors(thresholds_colors);

		this.updateForm();
	}

	updateForm() {
		// It is best to place most dependent inputs at the end.

		for (const element of this._form.querySelectorAll('.fields-group-description, .fields-group-value,' +
				' .fields-group-needle, .fields-group-minmax, .js-row-empty-color, .js-row-bg-color,' +
				' .fields-group-thresholds')) {
			element.style.display = this._advanced_configuration.checked ? '' : 'none';

			// Disable all advanced configuration elements if, advanced configuration is disabled.
			for (const input of element.querySelectorAll('input, textarea')) {
				input.disabled = !this._advanced_configuration.checked;
			}
		}

		// Value arc size can only be changed if value arc is enabled and selected.
		this.toggleGoup(document.querySelectorAll('#value_arc_size'), this._value_arc.checked);

		/*
		 * "Min/Max show", "Min/Max size" and "Min/Max show units" depend on whether the value or threshold arcs are is
		 * enabled. Either one of them will suffice. However, "Min/Max size" and "Min/Max show units" also depend on the
		 * "Min/Max show" checkbox. But enable threshold arc checkbox, the list of thresholds cannot be
		 * empty.
		 */
		this.toggleGoup(document.querySelectorAll('#minmax_show, #minmax_size, #minmax_show_units'),
			this._th_rows_count && this._th_show_arc.checked || this._value_arc.checked
		);
		this.toggleGoup(document.querySelectorAll('#minmax_size, #minmax_show_units'), this._minmax_show.checked,
			this._th_rows_count && this._th_show_arc.checked || this._value_arc.checked
		);

		/*
		 * "Min/Max show units" depends on all three factors: the "Min/Max" block should be enabled, "Units" from value
		 * block should be enabled and at least one of the arcs value or threhsold should be enabled and selected.
		 */
		this.toggleGoup(document.querySelectorAll('#minmax_show_units'), this._units_show.checked,
			this._minmax_show.checked, this._th_rows_count && this._th_show_arc.checked || this._value_arc.checked
		);

		// All units block inputs depend on whether the "Show units" is enabled.
		this.toggleGoup(document.querySelectorAll('#units, #units_pos, #units_size, #units_bold, #units_color'),
			this._units_show.checked
		);

		/*
		 * Adding and removing thresholds, enables or disables other threshold controls. There must be at least one
		 * threshold to enable other controls. Treshold "Arc size" depends on both, number of threshold rows and
		 * if "Show arc" is selected. However, "Show labels" depends on either value or threshold arc.
		 */
		this.toggleGoup(document.querySelectorAll('#th_show_arc, #th_arc_size'), this._th_rows_count);
		this.toggleGoup(document.querySelectorAll('#th_arc_size'), this._th_rows_count, this._th_show_arc.checked);
		this.toggleGoup(document.querySelectorAll('#th_show_labels'), this._th_rows_count,
			this._th_show_arc.checked || this._value_arc.checked
		);

		// "Show needle" and "Needle color" inputs depend whether there are value or threshold arcs.
		this.toggleGoup(document.querySelectorAll('#needle_show, #needle_color'),
			this._th_rows_count && this._th_show_arc.checked || this._value_arc.checked
		);

		// "Needle color" depends on "Show needle" and whether there are value or threshold arcs.
		this.toggleGoup(document.querySelectorAll('#needle_color'), this._needle_show.checked,
			this._th_rows_count && this._th_show_arc.checked || this._value_arc.checked
		);
	}

	updateWarningIcon() {
		const thresholds_warning = document.getElementById('gauge-thresholds-warning');
		const ms_item_data = $('#itemid').multiSelect('getData');

		thresholds_warning.style.display = 'none';

		if (ms_item_data.length > 0) {
			const curl = new Curl('jsrpc.php');

			curl.setArgument('method', 'item_value_type.get');
			curl.setArgument('type', <?= PAGE_TYPE_TEXT_RETURN_JSON ?>);
			curl.setArgument('itemid', ms_item_data[0].id);

			fetch(curl.getUrl())
				.then((response) => response.json())
				.then((response) => {
					switch (response.result) {
						case '<?= ITEM_VALUE_TYPE_FLOAT ?>':
						case '<?= ITEM_VALUE_TYPE_UINT64 ?>':
							thresholds_warning.style.display = 'none';
							break;
						default:
							thresholds_warning.style.display = '';
					}
				})
				.catch((exception) => {
					console.log('Could not get value data type of the item:', exception);
				});
		}
	}

	/**
	 * Enables or disables elements, depeding on infinite amount of arguments. First argument is removed, since it is
	 * the list of elements. Other arguments are booleans. So elements can depend on one or more checkboxes. All of the
	 * checkboxes should be selected to enable the element.
	 *
	 * @param {NodeList} elements  List of elements to enable/disable.
	 */
	toggleGoup(elements) {
		const args = Array.from(arguments);

		let dependencies = true;

		args.shift();

		for (const arg of args) {
			dependencies = dependencies && !!arg;
		}

		for (const element of elements) {
			element.disabled = !this._advanced_configuration.checked || !dependencies;
		}
	}
};
