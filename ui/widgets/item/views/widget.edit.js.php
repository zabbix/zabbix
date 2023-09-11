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

		this._units_show = document.getElementById('units_show');
		this._item_time = document.getElementById('item_time');
		this._aggregate_function = document.getElementById('aggregate_function');
		this._aggregate_warning = document.getElementById('item_value_aggregate_warning');

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

		this._aggregate_warning.style.display = Number(this._aggregate_function.value) === <?= AGGREGATE_AVG ?>
			|| Number(this._aggregate_function.value) === <?= AGGREGATE_MIN ?>
			|| Number(this._aggregate_function.value) === <?= AGGREGATE_MAX ?>
			|| Number(this._aggregate_function.value) === <?= AGGREGATE_SUM ?> ? '' : 'none';

		for (const element of this._form.querySelectorAll('.js-row-override-time')) {
			element.style.display = Number(this._aggregate_function.value) === 0 ? 'none' : '';
		}

		this._units_show.addEventListener('change', () => this.updateForm());
		this._item_time.addEventListener('change', () => this.updateForm());

		this._aggregate_function.addEventListener('change', () => {
			this._item_time.dispatchEvent(new Event('change'));
		});

		this._aggregate_function.dispatchEvent(new Event('change'));

		colorPalette.setThemeColors(thresholds_colors);

		this.updateForm();
	}

	updateForm() {
		const override_fields = this._form.querySelectorAll('.js-row-override-time');
		const aggregate_options = document.getElementById('aggregate_function');
		const aggregate_warning = document.getElementById('item_value_aggregate_warning')

		for (const element of this._form.querySelectorAll('.fields-group-description')) {
			element.style.display = this._show_description.checked ? '' : 'none';

			for (const input of element.querySelectorAll('input, textarea')) {
				input.disabled = !this._show_description.checked;
			}
		}

		for (const element of this._form.querySelectorAll('.fields-group-value')) {
			element.style.display = this._show_value.checked ? '' : 'none';

			for (const input of element.querySelectorAll('input')) {
				input.disabled = !this._show_value.checked;
			}
		}

		for (const element of document.querySelectorAll('#units, #units_pos, #units_size, #units_bold, #units_color')) {
			element.disabled = !this._show_value.checked || !this._units_show.checked;
		}

		for (const element of this._form.querySelectorAll('.fields-group-time')) {
			element.style.display = this._show_time.checked ? '' : 'none';

			for (const input of element.querySelectorAll('input')) {
				input.disabled = !this._show_time.checked;
			}
		}

		for (const element of this._form.querySelectorAll('.fields-group-change-indicator')) {
			element.style.display = this._show_change_indicator.checked ? '' : 'none';

			for (const input of element.querySelectorAll('input')) {
				input.disabled = !this._show_change_indicator.checked;
			}
		}

		this._item_time.value = this._aggregate_function.value != <?= AGGREGATE_NONE ?> && this._item_time.checked
			? 1
			: 0;

		const time_period_fields = [
			'#time_from',
			'#time_from_calendar',
			'#time_to',
			'#time_to_calendar'
		];

		if (this._aggregate_function.value == <?= AGGREGATE_NONE ?>) {
			time_period_fields.push('#item_time');

			for (const element of document.querySelectorAll(time_period_fields)) {
				element.disabled = true;
			}
		}
		else {
			document.querySelector('#item_time').disabled = false;

			for (const element of document.querySelectorAll(time_period_fields)) {
				element.disabled = !this._item_time.checked;
			}
		}

		aggregate_options.addEventListener('change', function() {
			for (const element of override_fields) {
				element.style.display = (Number(this.value) === <?= AGGREGATE_NONE ?>) ? 'none' : 'block';
			}

			aggregate_warning.style.display = Number(this.value) === <?= AGGREGATE_AVG ?>
				|| Number(this.value) === <?= AGGREGATE_MIN ?> || Number(this.value) === <?= AGGREGATE_MAX ?>
				|| Number(this.value) === <?= AGGREGATE_SUM ?> ? '' : 'none';
		});
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
