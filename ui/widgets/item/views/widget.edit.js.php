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


use Widgets\Item\Widget;

?>

window.widget_item_form = new class {

	init({thresholds_colors}) {
		this._form = document.getElementById('widget-dialogue-form');

		this._show_description = document.getElementById(`show_${<?= Widget::SHOW_DESCRIPTION ?>}`);
		this._show_value = document.getElementById(`show_${<?= Widget::SHOW_VALUE ?>}`);
		this._show_time = document.getElementById(`show_${<?= Widget::SHOW_TIME ?>}`);
		this._show_change_indicator = document.getElementById(`show_${<?= Widget::SHOW_CHANGE_INDICATOR ?>}`);

		this._advance_configuration = document.getElementById('adv_conf');
		this._units_show = document.getElementById('units_show');

		jQuery('#itemid').on('change', () => this.updateWarningIcon());

		for (const colorpicker of this._form.querySelectorAll('.<?= ZBX_STYLE_COLOR_PICKER ?> input')) {
			$(colorpicker).colorpicker({
				appendTo: ".overlay-dialogue-body",
				use_default: !colorpicker.name.includes('thresholds'),
				onUpdate: ['up_color', 'down_color', 'updown_color'].includes(colorpicker.name)
					? (color) => this.setIndicatorColor(colorpicker.name, color)
					: null
			});
		}

		const show = [this._show_description, this._show_value, this._show_time, this._show_change_indicator];

		for (const checkbox of show) {
			checkbox.addEventListener('change', (e) => {
				if (show.filter((checkbox) => checkbox.checked).length > 0) {
					this.updateForm();
				}
				else {
					e.target.checked = true;
				}
			});
		}

		for (const checkbox of [this._advance_configuration, this._units_show]) {
			checkbox.addEventListener('change', () => this.updateForm());
		}

		colorPalette.setThemeColors(thresholds_colors);

		this.updateForm();
	}

	updateForm() {
		const show_description_row = this._advance_configuration.checked && this._show_description.checked;
		const show_value_row = this._advance_configuration.checked && this._show_value.checked;
		const show_time_row = this._advance_configuration.checked && this._show_time.checked;
		const show_change_indicator_row = this._advance_configuration.checked && this._show_change_indicator.checked;
		const show_bg_color_row = this._advance_configuration.checked;
		const show_thresholds_row = this._advance_configuration.checked;

		for (const element of this._form.querySelectorAll('.fields-group-description')) {
			element.style.display = show_description_row ? '' : 'none';

			for (const input of element.querySelectorAll('input, textarea')) {
				input.disabled = !show_description_row;
			}
		}

		for (const element of this._form.querySelectorAll('.fields-group-value')) {
			element.style.display = show_value_row ? '' : 'none';

			for (const input of element.querySelectorAll('input')) {
				input.disabled = !show_value_row;
			}
		}
		for(const element of document.querySelectorAll('#units, #units_pos, #units_size, #units_bold, #units_color')) {
			element.disabled = !show_value_row || !this._units_show.checked;
		}

		for (const element of this._form.querySelectorAll('.fields-group-time')) {
			element.style.display = show_time_row ? '' : 'none';

			for (const input of element.querySelectorAll('input')) {
				input.disabled = !show_time_row;
			}
		}

		for (const element of this._form.querySelectorAll('.fields-group-change-indicator')) {
			element.style.display = show_change_indicator_row ? '' : 'none';

			for (const input of element.querySelectorAll('input')) {
				input.disabled = !show_change_indicator_row;
			}
		}

		for (const element of this._form.querySelectorAll('.js-row-bg-color')) {
			element.style.display = show_bg_color_row ? '' : 'none';

			for (const input of element.querySelectorAll('input')) {
				input.disabled = !show_bg_color_row;
			}
		}

		for (const element of this._form.querySelectorAll('.js-row-thresholds')) {
			element.style.display = show_thresholds_row ? '' : 'none';

			for (const input of element.querySelectorAll('input')) {
				input.disabled = !show_thresholds_row;
			}
		}
	}

	updateWarningIcon() {
		const thresholds_warning = document.getElementById('item-value-thresholds-warning');
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

	setIndicatorColor(name, color) {
		const indicator_ids = {
			up_color: 'change-indicator-up',
			down_color: 'change-indicator-down',
			updown_color: 'change-indicator-updown'
		};

		document.getElementById(indicator_ids[name])
			.querySelector("polygon").style.fill = (color !== '') ? `#${color}` : '';
	}
};
