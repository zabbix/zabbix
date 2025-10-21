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

window.widget_form = new class extends CWidgetForm {

	#is_item_numeric = false;

	/**
	 * @type {HTMLFormElement}
	 */
	#form;

	/**
	 * @type {Record<int, HTMLInputElement>}
	 */
	#show = {};

	init({thresholds_colors}) {
		this.#form = this.getForm();

		[<?= Widget::SHOW_DESCRIPTION ?>, <?= Widget::SHOW_VALUE ?>, <?= Widget::SHOW_TIME ?>,
				<?= Widget::SHOW_CHANGE_INDICATOR ?>, <?= Widget::SHOW_SPARKLINE ?>]
			.forEach(show_value => {
				const checkbox = document.getElementById(`show_${show_value}`);
				checkbox.addEventListener('change', () => this.updateForm());

				this.#show[show_value] = checkbox;
			});

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

		document.getElementById('units_show').addEventListener('change', () => this.updateForm());
		document.getElementById('aggregate_function').addEventListener('change', () => this.updateForm());
		document.getElementById('history').addEventListener('change', () => this.updateForm());

		for (const change_indicator of ['up_color', 'down_color', 'updown_color']) {
			this.#form.querySelector(`.${ZBX_STYLE_COLOR_PICKER}[color-field-name="${change_indicator}"]`)
				?.addEventListener('change', e => this.setIndicatorColor(change_indicator, e.target.color));
		}

		colorPalette.setThemeColors(thresholds_colors);

		this.updateForm();

		this.#promiseGetItemType()
			.then((type) => {
				if (this.#form.isConnected) {
					this.#is_item_numeric = type !== null && this.#isItemValueTypeNumeric(type);
					this.updateForm();
				}
			})
			.finally(() => this.ready());
	}

	updateForm() {
		const show_description = this.#show[<?= Widget::SHOW_DESCRIPTION ?>].checked;
		const show_value = this.#show[<?= Widget::SHOW_VALUE ?>].checked;
		const show_time = this.#show[<?= Widget::SHOW_TIME ?>].checked;
		const show_change_indicator = this.#show[<?= Widget::SHOW_CHANGE_INDICATOR ?>].checked;
		const show_sparkline = this.#show[<?= Widget::SHOW_SPARKLINE ?>].checked;

		/** @type {HTMLSelectElement} */
		const aggregate_function = document.getElementById('aggregate_function');

		for (const element of this.#form.querySelectorAll('.fields-group-description')) {
			element.style.display = show_description ? '' : 'none';

			for (const input of element.querySelectorAll('input, textarea')) {
				input.disabled = !show_description;
			}
		}

		for (const element of this.#form.querySelectorAll('.fields-group-value')) {
			element.style.display = show_value ? '' : 'none';

			for (const input of element.querySelectorAll('input')) {
				input.disabled = !show_value;
			}
		}

		for (const element of this.#form.querySelectorAll(`#units, #units_pos, #units_size, #units_bold,
			.${ZBX_STYLE_COLOR_PICKER}[color-field-name="units_color"]`
		)) {
			element.disabled = !show_value || !document.getElementById('units_show').checked;
		}

		for (const element of this.#form.querySelectorAll('.fields-group-time')) {
			element.style.display = show_time ? '' : 'none';

			for (const input of element.querySelectorAll('input')) {
				input.disabled = !show_time;
			}
		}

		for (const element of this.#form.querySelectorAll('.fields-group-change-indicator')) {
			element.style.display = show_change_indicator ? '' : 'none';

			for (const input of element.querySelectorAll('input')) {
				input.disabled = !show_change_indicator;
			}
		}

		for (const element of this.#form.querySelectorAll('.js-sparkline-row')) {
			element.style.display = show_sparkline ? '' : 'none';

			for (const input of element.querySelectorAll('input')) {
				input.disabled = !show_sparkline;
			}
		}

		this.getField('sparkline[time_period]').disabled = !show_sparkline;

		const aggregate_function_none = aggregate_function.value == <?= AGGREGATE_NONE ?>;

		this.getField('time_period').hidden = aggregate_function_none;

		const history_data_trends = document.querySelector('#history input[name="history"]:checked')
			.value == <?= Widget::HISTORY_DATA_TRENDS ?>;

		document.getElementById('item-history-data-warning').style.display =
				history_data_trends && !this.#is_item_numeric
			? ''
			: 'none';

		/** @type {HTMLButtonElement} */
		const aggregate_function_warning = document.getElementById('item-aggregate-function-warning');
		const aggregate_warning_functions = [<?= AGGREGATE_AVG ?>, <?= AGGREGATE_MIN ?>, <?= AGGREGATE_MAX ?>,
			<?= AGGREGATE_SUM ?>
		];

		const show_numeric_warning = aggregate_warning_functions.includes(parseInt(aggregate_function.value))
				&& !this.#is_item_numeric;
		const show_sparkline_warning = !aggregate_function_none && show_sparkline;

		if (show_numeric_warning || show_sparkline_warning) {
			const numeric_warning = aggregate_function_warning.getAttribute('data-warning');
			const sparkline_warning = aggregate_function_warning.getAttribute('data-sparkline-warning');

			aggregate_function_warning.setAttribute('data-hintbox-contents', show_sparkline_warning
				? (show_numeric_warning ? `${numeric_warning}<br>${sparkline_warning}` : sparkline_warning)
				: numeric_warning);

			aggregate_function_warning.style.display = '';
		}
		else {
			aggregate_function_warning.style.display = 'none';
		}

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
