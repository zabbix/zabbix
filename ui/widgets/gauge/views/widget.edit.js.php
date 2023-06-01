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
		document.getElementById('value_arc_size').disabled = !this._value_arc.checked;

		for (const element of document.querySelectorAll('#minmax_show, #minmax_size, #minmax_show_units')) {
			element.disabled = this._th_rows_count === 0 || (!this._th_show_arc.checked && !this._value_arc.checked);
		}

		for (const element of document.querySelectorAll('#minmax_size, #minmax_show_units')) {
			element.disabled = !this._minmax_show.checked || this._th_rows_count === 0 || (!this._th_show_arc.checked
				&& !this._value_arc.checked);
		}

		document.getElementById('minmax_show_units').disabled = !this._units_show.checked || !this._minmax_show.checked
			|| this._th_rows_count === 0 || (!this._th_show_arc.checked && !this._value_arc.checked);

		for (const element of
				document.querySelectorAll('#units, #units_pos, #units_size, #units_bold, #units_color')) {
			element.disabled = !this._units_show.checked;
		}

		for (const element of document.querySelectorAll('#th_show_arc, #th_arc_size')) {
			element.disabled = this._th_rows_count === 0;
		}

		document.getElementById('th_arc_size').disabled = this._th_rows_count === 0 || !this._th_show_arc.checked;

		document.getElementById('th_show_labels').disabled = this._th_rows_count === 0 || (!this._th_show_arc.checked
			&& !this._value_arc.checked);

		for (const element of document.querySelectorAll('#needle_show, #needle_color')) {
			element.disabled = !this._th_show_arc.checked && !this._value_arc.checked;
		}

		document.getElementById('needle_color').disabled = !this._needle_show.checked || (!this._th_show_arc.checked
			&& !this._value_arc.checked);
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
};
