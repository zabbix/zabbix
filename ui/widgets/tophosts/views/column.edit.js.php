<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2024 Zabbix SIA
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


use Widgets\TopHosts\Includes\CWidgetFieldColumnsList;

?>

window.tophosts_column_edit_form = new class {

	/**
	 * @type {Overlay}
	 */
	#overlay;

	/**
	 * @type {HTMLElement}
	 */
	#dialogue;

	/**
	 * @type {HTMLFormElement}
	 */
	#form;

	/**
	 * @type {HTMLTableElement}
	 */
	#thresholds_table;

	/**
	 * @type {HTMLTableElement}
	 */
	#highlights_table;

	init({form_id, thresholds, highlights, colors, groupids, hostids}) {
		this.#overlay = overlays_stack.getById('tophosts-column-edit-overlay');
		this.#dialogue = this.#overlay.$dialogue[0];
		this.#form = document.getElementById(form_id);

		this.#thresholds_table = document.getElementById('thresholds_table');
		this.#highlights_table = document.getElementById('highlights_table');

		this.#form.querySelectorAll('[name="data"], [name="aggregate_function"], [name="display"], [name="history"]')
			.forEach(element => {
				element.addEventListener('change', () => this.#updateForm());
			});

		// Initialize item multiselect.
		$('#item').on('change', () => {
			const ms_item_data = jQuery('#item').multiSelect('getData');

			if (ms_item_data.length > 0) {
				this.#overlay.setLoading();

				this.#promiseGetItemType(ms_item_data[0].name, groupids, hostids)
					.then(type => {
						if (this.#form.isConnected) {
							this.#updateFieldDisplayValueAsByType(type);
							this.#updateForm();
						}
					})
					.finally(() => {
						this.#overlay.unsetLoading();
					});
			}
			else {
				this.#updateForm();
			}
		});

		colorPalette.setThemeColors(colors);

		// Initialize Display item value as and Display event listener
		document.getElementById('display_value_as').addEventListener('change', () => this.#updateForm());
		document.getElementById('display').addEventListener('change', () => this.#updateForm());

		// Initialize thresholds table.
		$(this.#thresholds_table)
			.dynamicRows({
				rows: thresholds,
				template: '#thresholds-row-tmpl',
				allow_empty: true,
				dataCallback: (row_data) => {
					if (!('color' in row_data)) {
						const colors = this.#form.querySelectorAll('.<?= ZBX_STYLE_COLOR_PICKER ?> input');
						const used_colors = [];

						for (const color of colors) {
							if (color.value !== '') {
								used_colors.push(color.value);
							}
						}

						row_data.color = colorPalette.getNextColor(used_colors);
					}
				}
			})
			.on('afteradd.dynamicRows', e => {
				const $colorpicker = $('tr.form_row:last input[name$="[color]"]', e.target);

				$colorpicker.colorpicker({appendTo: $colorpicker.closest('.input-color-picker')});

				this.#updateForm();
			})
			.on('afterremove.dynamicRows', () => this.#updateForm())
			.on('change', (e) => e.target.value = e.target.value.trim());

		for (const colorpicker of this.#thresholds_table.querySelectorAll('tr.form_row input[name$="[color]"]')) {
			$(colorpicker).colorpicker({appendTo: $(colorpicker).closest('.input-color-picker')});
		}

		// Initialize highlights table.
		$(this.#highlights_table)
			.dynamicRows({
				rows: highlights,
				template: '#highlights-row-tmpl',
				allow_empty: true,
				dataCallback: (row_data) => {
					if (!('color' in row_data)) {
						const colors = this.#form.querySelectorAll('.<?= ZBX_STYLE_COLOR_PICKER ?> input');
						const used_colors = [];

						for (const color of colors) {
							if (color.value !== '') {
								used_colors.push(color.value);
							}
						}

						row_data.color = colorPalette.getNextColor(used_colors);
					}
				}
			})
			.on('afteradd.dynamicRows', e => {
				const $colorpicker = $('tr.form_row:last input[name$="[color]"]', e.target);

				$colorpicker.colorpicker({appendTo: $colorpicker.closest('.input-color-picker')});

				this.#updateForm();
			})
			.on('afterremove.dynamicRows', () => this.#updateForm());

		for (const colorpicker of this.#highlights_table.querySelectorAll('tr.form_row input[name$="[color]"]')) {
			$(colorpicker).colorpicker({appendTo: $(colorpicker).closest('.input-color-picker')});
		}

		// Initialize Advanced configuration collapsible.
		new CFormFieldsetCollapsible(document.getElementById('advanced-configuration'));

		// Field trimming.
		this.#form.querySelectorAll('[name="name"], [name="min"], [name="max"]').forEach(element => {
			element.addEventListener('change', (e) => e.target.value = e.target.value.trim(), {capture: true});
		});

		// Initialize form elements accessibility.
		this.#updateForm();

		this.#form.style.display = '';
		this.#overlay.recoverFocus();

		this.#form.addEventListener('submit', () => this.submit());
	}

	/**
	 * Fetch type of item by item name.
	 *
	 * @param {string|null} name
	 * @param {array}       groupids  Host group ids from widget main configuration form fields.
	 * @param {array}       hostids   Host ids from widget main configuration form fields.
	 *
	 * @return {Promise<any>}  Resolved promise will contain item type of first found item of such name,
	 *                         or null in case of error or if no item is currently selected.
	 */
	#promiseGetItemType(name, groupids, hostids) {
		if (name === null) {
			return Promise.resolve(null);
		}

		return ApiCall('item.get', {
			output: ['value_type'],
			groupids: groupids.length ? groupids : undefined,
			hostids: hostids.length ? hostids : undefined,
			webitems: true,
			search: {
				name_resolved: name
			},
			limit: 1
		})
			.then(response => response.result.length ? parseInt(response.result[0].value_type) : null)
			.catch(error => {
				console.log(`Could not get item type: ${error.message}`);

				return null;
			});
	}

	#updateFieldDisplayValueAsByType(type) {
		let display_value_as;

		switch (type) {
			case <?= ITEM_VALUE_TYPE_FLOAT ?>:
			case <?= ITEM_VALUE_TYPE_UINT64 ?>:
				display_value_as = <?= CWidgetFieldColumnsList::DISPLAY_VALUE_AS_NUMERIC ?>;
				break;
			case <?= ITEM_VALUE_TYPE_STR ?>:
			case <?= ITEM_VALUE_TYPE_LOG ?>:
			case <?= ITEM_VALUE_TYPE_TEXT ?>:
				display_value_as = <?= CWidgetFieldColumnsList::DISPLAY_VALUE_AS_TEXT ?>;
				break;
			case <?= ITEM_VALUE_TYPE_BINARY ?>:
				display_value_as = <?= CWidgetFieldColumnsList::DISPLAY_VALUE_AS_BINARY ?>;
				break;
		}

		for (const input of document.querySelectorAll('[name="display_value_as"]')) {
			input.checked = input.value == display_value_as;
		}
	}

	/**
	 * Updates widget column configuration form field visibility, enable/disable state and available options.
	 */
	#updateForm() {
		const data_type = document.querySelector('[name="data"]').value;

		const data_type_item_value = data_type == <?= CWidgetFieldColumnsList::DATA_ITEM_VALUE ?>;
		const data_type_text = data_type == <?= CWidgetFieldColumnsList::DATA_TEXT ?>;

		const display_item_value_as = document.querySelector('[name="display_value_as"]:checked').value;

		const display_item_value_as_numeric = data_type_item_value
			&& display_item_value_as == <?= CWidgetFieldColumnsList::DISPLAY_VALUE_AS_NUMERIC ?>;
		const display_item_value_as_text = data_type_item_value
			&& display_item_value_as == <?= CWidgetFieldColumnsList::DISPLAY_VALUE_AS_TEXT ?>;
		const display_item_as_binary = data_type_item_value
			&& display_item_value_as == <?= CWidgetFieldColumnsList::DISPLAY_VALUE_AS_BINARY ?>;

		// Item name.
		for (const element of this.#form.querySelectorAll('.js-item-row')) {
			element.style.display = data_type_item_value ? '' : 'none';
		}

		$('#item').multiSelect(data_type_item_value ? 'enable' : 'disable');

		// Text.
		for (const element of this.#form.querySelectorAll('.js-text-row')) {
			element.style.display = data_type_text ? '' : 'none';

			for (const input of element.querySelectorAll('input')) {
				input.disabled = !data_type_text;
			}
		}

		// Display item value as.
		for (const element of this.#form.querySelectorAll('.js-display-value-as-row')) {
			element.style.display = data_type_item_value ? '' : 'none';

			for (const input of element.querySelectorAll('input')) {
				input.disabled = !data_type_item_value;
			}
		}

		// Display.
		for (const element of this.#form.querySelectorAll('.js-display-row')) {
			element.style.display = display_item_value_as_numeric ? '' : 'none';

			for (const input of element.querySelectorAll('input')) {
				input.disabled = !display_item_value_as_numeric;
			}
		}

		// Min/Max.
		const show_min_max = display_item_value_as_numeric
			&& document.querySelector('[name="display"]:checked').value != <?= CWidgetFieldColumnsList::DISPLAY_AS_IS ?>;

		for (const element of this.#form.querySelectorAll('.js-min-max-row')) {
			element.style.display = show_min_max ? '' : 'none';

			for (const input of element.querySelectorAll('input')) {
				input.disabled = !show_min_max;
			}
		}

		// Thresholds.
		for (const element of this.#form.querySelectorAll('.js-thresholds-row')) {
			element.style.display = display_item_value_as_numeric ? '' : 'none';

			for (const input of element.querySelectorAll('input')) {
				input.disabled = !display_item_value_as_numeric;
			}
		}

		// Decimal places.
		for (const element of this.#form.querySelectorAll('.js-decimals-row')) {
			element.style.display = display_item_value_as_numeric ? '' : 'none';

			for (const input of element.querySelectorAll('input')) {
				input.disabled = !display_item_value_as_numeric;
			}
		}

		// Highlights.
		for (const element of this.#form.querySelectorAll('.js-highlights-row')) {
			element.style.display = display_item_value_as_text ? '' : 'none';

			for (const input of element.querySelectorAll('input')) {
				input.disabled = !display_item_value_as_text;
			}
		}

		// Show thumbnail.
		for (const element of this.#form.querySelectorAll('.js-show-thumbnail-row')) {
			element.style.display = display_item_as_binary ? '' : 'none';

			for (const input of element.querySelectorAll('input')) {
				input.disabled = !display_item_as_binary;
			}
		}

		// Advanced configuration.
		document.getElementById('advanced-configuration').style.display = data_type_item_value ? '' : 'none';


		// Aggregation function.
		const aggregation_function_select = document.getElementById('aggregate_function');

		aggregation_function_select.disabled = !data_type_item_value;

		if (data_type_item_value) {
			const numeric_aggregation_function = [<?= AGGREGATE_MIN ?>, <?= AGGREGATE_MAX ?>, <?= AGGREGATE_AVG ?>,
				<?= AGGREGATE_SUM ?>
			];

			for (const option of aggregation_function_select.getOptions()) {
				if (numeric_aggregation_function.includes(parseInt(option.value))) {
					if (display_item_value_as_text || display_item_as_binary) {
						option.hidden = true;
						aggregation_function_select.value = <?= AGGREGATE_NONE ?>;
					}
					else {
						option.hidden = false;
					}
				}
			}
		}

		// Time period.
		const use_aggregation = aggregation_function_select.value != <?= AGGREGATE_NONE ?>;

		this.#form.fields.time_period.disabled = !data_type_item_value || !use_aggregation;
		this.#form.fields.time_period.hidden = !data_type_item_value || !use_aggregation;

		// History data.
		for (const element of this.#form.querySelectorAll('.js-history-row')) {
			element.style.display = display_item_value_as_numeric ? '' : 'none';

			for (const input of element.querySelectorAll('input')) {
				input.disabled = !display_item_value_as_numeric;
			}
		}
	}

	submit() {
		const curl = new Curl(this.#form.getAttribute('action'));
		const fields = getFormFields(this.#form);

		this.#overlay.setLoading();

		this.#post(curl.getUrl(), fields);
	}

	#post(url, fields) {
		fetch(url, {
			method: 'POST',
			headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
			body: urlEncodeData(fields)
		})
			.then(response => response.json())
			.then(response => {
				if ('error' in response) {
					throw {error: response.error};
				}

				overlayDialogueDestroy(this.#overlay.dialogueid);

				this.#dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
			})
			.catch((exception) => {
				for (const element of this.#form.parentNode.children) {
					if (element.matches('.msg-good, .msg-bad, .msg-warning')) {
						element.parentNode.removeChild(element);
					}
				}

				let title, messages;

				if (typeof exception === 'object' && 'error' in exception) {
					title = exception.error.title;
					messages = exception.error.messages;
				}
				else {
					messages = [<?= json_encode(_('Unexpected server error.')) ?>];
				}

				const message_box = makeMessageBox('bad', messages, title)[0];

				this.#form.parentNode.insertBefore(message_box, this.#form);
			})
			.finally(() => {
				this.#overlay.unsetLoading();
			});
	}
};
