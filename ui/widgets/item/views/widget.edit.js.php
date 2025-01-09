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


use Widgets\Item\Widget;

?>

window.widget_item_form = new class {

	#is_item_numeric = false;

	/**
	 * @type {HTMLFormElement}
	 */
	#form;

	init({thresholds_colors}) {
		this.#form = document.getElementById('widget-dialogue-form');

		this._show_description = document.getElementById(`show_${<?= Widget::SHOW_DESCRIPTION ?>}`);
		this._show_value = document.getElementById(`show_${<?= Widget::SHOW_VALUE ?>}`);
		this._show_time = document.getElementById(`show_${<?= Widget::SHOW_TIME ?>}`);
		this._show_change_indicator = document.getElementById(`show_${<?= Widget::SHOW_CHANGE_INDICATOR ?>}`);
		this._show_sparkline = document.getElementById(`show_${<?= Widget::SHOW_SPARKLINE ?>}`);

		this._units_show = document.getElementById('units_show');

		jQuery('#itemid').on('change', () => {
			this.#promiseGetItemType()
				.then((type) => {
					if (this.#form.isConnected) {
						this.#is_item_numeric = type !== null && this.#isItemValueTypeNumeric(type);
						this.updateForm();
					}
				});
		});

		const colorpickers = this.#form
			.querySelectorAll('.<?= ZBX_STYLE_COLOR_PICKER ?> input:not([name="sparkline[color]"])');

		for (const colorpicker of colorpickers) {
			$(colorpicker).colorpicker({
				appendTo: ".overlay-dialogue-body",
				use_default: !colorpicker.name.includes('thresholds'),
				onUpdate: ['up_color', 'down_color', 'updown_color'].includes(colorpicker.name)
					? (color) => this.setIndicatorColor(colorpicker.name, color)
					: null
			});
		}

		const show = [this._show_description, this._show_value, this._show_time, this._show_change_indicator,
			this._show_sparkline
		];

		for (const checkbox of show) {
			checkbox.addEventListener('change', () => this.updateForm());
		}

		document.getElementById('units_show').addEventListener('change', () => this.updateForm());
		document.getElementById('aggregate_function').addEventListener('change', () => this.updateForm());
		document.getElementById('history').addEventListener('change', () => this.updateForm());

		colorPalette.setThemeColors(thresholds_colors);

		this.updateForm();

		this.#promiseGetItemType()
			.then((type) => {
				if (this.#form.isConnected) {
					this.#is_item_numeric = type !== null && this.#isItemValueTypeNumeric(type);
					this.updateForm();
				}
			});
	}

	updateForm() {
		const aggregate_function = document.getElementById('aggregate_function');

		for (const element of this.#form.querySelectorAll('.fields-group-description')) {
			element.style.display = this._show_description.checked ? '' : 'none';

			for (const input of element.querySelectorAll('input, textarea')) {
				input.disabled = !this._show_description.checked;
			}
		}

		for (const element of this.#form.querySelectorAll('.fields-group-value')) {
			element.style.display = this._show_value.checked ? '' : 'none';

			for (const input of element.querySelectorAll('input')) {
				input.disabled = !this._show_value.checked;
			}
		}

		for (const element of document.querySelectorAll('#units, #units_pos, #units_size, #units_bold, #units_color')) {
			element.disabled = !this._show_value.checked || !document.getElementById('units_show').checked;
		}

		for (const element of this.#form.querySelectorAll('.fields-group-time')) {
			element.style.display = this._show_time.checked ? '' : 'none';

			for (const input of element.querySelectorAll('input')) {
				input.disabled = !this._show_time.checked;
			}
		}

		for (const element of this.#form.querySelectorAll('.fields-group-change-indicator')) {
			element.style.display = this._show_change_indicator.checked ? '' : 'none';

			for (const input of element.querySelectorAll('input')) {
				input.disabled = !this._show_change_indicator.checked;
			}
		}

		for (const element of this.#form.querySelectorAll('.js-sparkline-row')) {
			element.style.display = this._show_sparkline.checked ? '' : 'none';

			for (const input of element.querySelectorAll('input')) {
				input.disabled = !this._show_sparkline.checked;
			}
		}

		this.#form.fields['sparkline[time_period]'].disabled = !this._show_sparkline.checked;

		this.#form.fields.time_period.hidden = aggregate_function.value == <?= AGGREGATE_NONE ?>;

		const aggregate_warning_functions = [<?= AGGREGATE_AVG ?>, <?= AGGREGATE_MIN ?>, <?= AGGREGATE_MAX ?>,
			<?= AGGREGATE_SUM ?>
		];

		const history_data_trends = document.querySelector('#history input[name="history"]:checked')
			.value == <?= Widget::HISTORY_DATA_TRENDS ?>;

		document.getElementById('item-history-data-warning').style.display =
				history_data_trends && !this.#is_item_numeric
			? ''
			: 'none';

		document.getElementById('item-aggregate-function-warning').style.display =
				aggregate_warning_functions.includes(parseInt(aggregate_function.value)) && !this.#is_item_numeric
			? ''
			: 'none';

		document.getElementById('item-thresholds-warning').style.display = this.#is_item_numeric ? 'none' : '';
	}

	/**
	 * Fetch type of currently selected item.
	 *
	 * Will return null if outer data source (widget) is selected instead of item.
	 *
	 * @return {Promise<any>}  Resolved promise will contain item type, or null if item type can't be established.
	 */
	#promiseGetItemType() {
		const ms_item_data = $('#itemid').multiSelect('getData');

		// The ID is empty string for unavailable widgets.
		if (ms_item_data.length === 0 || ms_item_data[0].id === '') {
			return Promise.resolve(null);
		}

		const {reference} = CWidgetBase.parseTypedReference(ms_item_data[0].id);

		if (reference !== '') {
			return Promise.resolve(null);
		}

		const curl = new Curl('jsrpc.php');

		curl.setArgument('method', 'item_value_type.get');
		curl.setArgument('type', <?= PAGE_TYPE_TEXT_RETURN_JSON ?>);
		curl.setArgument('itemid', ms_item_data[0].id);

		return fetch(curl.getUrl())
			.then((response) => response.json())
			.then((response) => {
				if ('error' in response) {
					throw {error: response.error};
				}

				return parseInt(response.result);
			})
			.catch((exception) => {
				console.log('Could not get item type', exception);

				return null;
			});
	}

	/**
	 * Check if item value type is numeric.
	 *
	 * @param {int} type  Item value type.
	 */
	#isItemValueTypeNumeric(type) {
		return type == <?= ITEM_VALUE_TYPE_FLOAT ?> || type == <?= ITEM_VALUE_TYPE_UINT64 ?>;
	}

	/**
	 * Set color of the specified indicator.
	 *
	 * @param {string} name   Indicator name.
	 * @param {string} color  Color number.
	 */
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
