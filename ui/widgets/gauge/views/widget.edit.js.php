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
		this._advanced_configuration = document.getElementById('adv_conf');
		this._units_show = document.getElementById('units_show');
		this._needle_show = document.getElementById('needle_show');
		this._minmax_show = document.getElementById('minmax_show');
		this._th_show_arc = document.getElementById('th_show_arc');

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

		colorPalette.setThemeColors(thresholds_colors);

		this.updateForm();
	}

	updateForm() {
		for (const element of this._form.querySelectorAll('.fields-group-description, .fields-group-value,' +
				' .fields-group-needle, .fields-group-minmax, .js-row-empty-color, .js-row-bg-color,' +
				' .fields-group-thresholds')) {
			element.style.display = this._advanced_configuration.checked ? '' : 'none';

			for (const input of element.querySelectorAll('input, textarea')) {
				input.disabled = !this._advanced_configuration.checked;
			}
		}

		this.toggleGoup(document.querySelectorAll('#units, #units_pos, #units_size, #units_bold, #units_color'),
			this._units_show
		);
		this.toggleGoup(document.querySelectorAll('#needle, #needle_color'), this._needle_show);
		this.toggleGoup(document.querySelectorAll('#minmax, #minmax_size, #minmax_show_units'), this._minmax_show);
		this.toggleGoup(document.querySelectorAll('#th_arc_size'), this._th_show_arc);
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

	toggleGoup(elements, checkbox) {
		for (const element of elements) {
			element.disabled = !this._advanced_configuration.checked || !checkbox.checked;
		}
	}
};
