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
			this._th_show_arc
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
		for (const element of this._form.querySelectorAll('.fields-group-description, .fields-group-value,' +
				' .fields-group-needle, .fields-group-minmax, .js-row-empty-color, .js-row-bg-color,' +
				' .fields-group-thresholds')) {
			element.style.display = this._advanced_configuration.checked ? '' : 'none';

			// Disable all advanced configuration elements if, advanced configuration is disabled.
			for (const input of element.querySelectorAll('input, textarea')) {
				input.disabled = !this._advanced_configuration.checked;
			}
		}

		/*
		 * Disabling "Show units" should also disable "Min/max show units". But when enabled, "Min/max show units"
		 * depends on the main "Min/Max" checkbox.
		 */
		this.toggleGoup(document.querySelectorAll('#minmax_size, #minmax_show_units'), this._minmax_show.checked);
		this.toggleGoup(document.querySelectorAll('#minmax_show_units'), this._units_show.checked,
			this._minmax_show.checked
		);
		this.toggleGoup(
			document.querySelectorAll('#units, #units_pos, #units_size, #units_bold, #units_color'),
			this._units_show.checked
		);
		this.toggleGoup(document.querySelectorAll('#needle, #needle_color'), this._needle_show.checked);

		/*
		 * Adding and removing thresholds, enables or disables other threshold controls. There must be at least one
		 * threshold to enable other controls. Treshold "Arc size" depends on both, number of threshold rows and
		 * if "Show arc" is selected.
		 */
		this.toggleGoup(document.querySelectorAll('#th_show_labels, #th_show_arc, #th_arc_size'), this._th_rows_count);
		this.toggleGoup(document.querySelectorAll('#th_arc_size'), this._th_show_arc.checked, this._th_rows_count);
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
	 * the list of elements. Other arguments are booleans. So elements can depend on one or more checkboxes. Both
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
