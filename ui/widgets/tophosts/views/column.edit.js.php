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
	 * @type {string}
	 */
	#thresholds_table;

	/**
	 * @type {string}
	 */
	#highlights_table;

	/**
	 * @type {number|null}
	 */
	#item_value_type;

	init({form_id, thresholds, highlights, colors}) {
		this.#overlay = overlays_stack.getById('tophosts-column-edit-overlay');
		this.#dialogue = this.#overlay.$dialogue[0];
		this.#form = document.getElementById(form_id);

		this.#thresholds_table = document.getElementById('thresholds_table');
		this.#highlights_table = document.getElementById('highlights_table');

		$('[name="data"], [name="aggregate_function"], [name="display"], [name="history"]', this.#form)
			.on('change', () => this.#updateForm());

		// Initialize item multiselect.
		$('#item').on('change', () => {
			const ms_item_data = jQuery('#item').multiSelect('getData');

			if (ms_item_data.length > 0) {
				this.#overlay.setLoading();

				this.#promiseGetItemType(ms_item_data[0].name)
					.then((type) => {
						if (this.#form.isConnected) {
							this.#item_value_type = type;
							this.#updateForm(true);
						}
					})
					.finally(() => {
						this.#overlay.unsetLoading();
					});

			}
			else {
				this.#item_value_type = null;
				this.#updateForm();
			}
		});

		colorPalette.setThemeColors(colors);

		// Initialize Display item value as and Display event listener
		document.getElementById('display_item_as').addEventListener('change', () => this.#updateForm());
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
		for (const name of ['name', 'min', 'max']) {
			this.#form.querySelector(`[name=${name}`)
				.addEventListener('change', (e) => e.target.value = e.target.value.trim(), {capture: true});
		}

		// Initialize form elements accessibility.
		this.#updateForm();

		this.#form.removeAttribute('style');
		this.#overlay.recoverFocus();

		this.#form.addEventListener('submit', () => this.submit());
	}

	/**
	 * Fetch type of item by item name.
	 *
	 * @param {string|null} name
	 * @param {number|null} groupid  Host group id from widget main configuration form fields.
	 * @param {number|null} hostid   Host id from widget main configuration form fields.
	 *
	 * @return {Promise<any>}  Resolved promise will contain item type of first found item of such name,
	 *                         or null in case of error or if no item is currently selected.
	 */
	#promiseGetItemType(name, groupid = null, hostid = null) {
		if (name === null) {
			return Promise.resolve(null);
		}

		const curl = new Curl('jsrpc.php');

		curl.setArgument('method', 'item_value_type_by_name.get');
		curl.setArgument('type', <?= PAGE_TYPE_TEXT_RETURN_JSON ?>);
		curl.setArgument('name', name);
		curl.setArgument('groupid', groupid);
		curl.setArgument('hostid', hostid);

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

	#updateForm(item_type_change = false) {
		const data_type = document.querySelector('[name=data]').value;
		const data_item_value = data_type == <?= CWidgetFieldColumnsList::DATA_ITEM_VALUE ?>;
		const data_text = data_type == <?= CWidgetFieldColumnsList::DATA_TEXT ?>;
		const is_item_type_numeric = [<?= ITEM_VALUE_TYPE_FLOAT ?>, <?= ITEM_VALUE_TYPE_UINT64 ?>]
			.includes(this.#item_value_type);
		const is_item_type_text = [<?= ITEM_VALUE_TYPE_STR ?>, <?= ITEM_VALUE_TYPE_LOG ?>, <?= ITEM_VALUE_TYPE_TEXT ?>]
			.includes(this.#item_value_type);
		const is_item_type_binary = this.#item_value_type == <?= ITEM_VALUE_TYPE_BINARY ?>;
		const display_as_numeric = <?= CWidgetFieldColumnsList::DISPLAY_VALUE_AS_NUMERIC ?>;
		const display_as_text = <?= CWidgetFieldColumnsList::DISPLAY_VALUE_AS_TEXT ?>;
		const display_as_binary = <?= CWidgetFieldColumnsList::DISPLAY_VALUE_AS_BINARY ?>;

		if (item_type_change) {
			document.querySelectorAll('[name=display_item_as]').forEach(element => {
				element.checked = (element.value == display_as_text && is_item_type_text)
					|| (element.value == display_as_binary && is_item_type_binary)
					|| (element.value == display_as_numeric && (is_item_type_numeric
						|| (!is_item_type_text && !is_item_type_binary)));
			});
		}

		const display_item_as = document.querySelector('[name=display_item_as]:checked').value;
		const display_numeric_as_is = document.querySelector('[name=display]:checked')
			.value == <?= CWidgetFieldColumnsList::DISPLAY_AS_IS ?>;
		const show_min_max = data_item_value && display_item_as == display_as_numeric && !display_numeric_as_is;
		const aggregate_function = parseInt(document.getElementById('aggregate_function').value);

		// Update aggregate function options based on item value display type.
		const aggregation_function_select = document.querySelector('z-select[name=aggregate_function]');

		[<?= AGGREGATE_MIN ?>, <?= AGGREGATE_MAX ?>, <?= AGGREGATE_AVG ?>, <?= AGGREGATE_SUM ?>].forEach(option => {
			aggregation_function_select.getOptionByValue(option).disabled = display_item_as != display_as_numeric;
			aggregation_function_select.getOptionByValue(option).hidden = display_item_as != display_as_numeric;

			if (aggregation_function_select.value == option && display_item_as != display_as_numeric) {
				aggregation_function_select.value = <?= AGGREGATE_NONE ?>;
			}
		});

		// Toggle row visibility.
		const rows = {
			'js-text-row': data_text,
			'js-item-row': data_item_value,
			'js-display-as-row': data_item_value,
			'js-display-row': data_item_value && display_item_as == display_as_numeric,
			'js-highlights-row': data_item_value && display_item_as == display_as_text,
			'js-min-row': show_min_max,
			'js-max-row': show_min_max,
			'js-thresholds-row': data_item_value && display_item_as == display_as_numeric,
			'js-decimals-row': data_item_value && display_item_as == display_as_numeric,
			'js-history-row': data_item_value && display_item_as == display_as_numeric,
			'js-display-as-image-row': data_item_value && display_item_as == display_as_binary,
			'js-advanced-configuration-fieldset': data_item_value
		}

		for (const class_name in rows) {
			const row = this.#form.getElementsByClassName(class_name);

			for (const element of row) {
				element.style.display = rows[class_name] ? '' : 'none';
			}
		}

		// Toggle disable/enable of input fields.
		$('#item', this.#form).multiSelect(data_item_value ? 'enable' : 'disable');
		$(this.#thresholds_table).toggleClass('disabled', display_item_as != display_as_numeric);
		$(this.#highlights_table).toggleClass('disabled', display_item_as != display_as_text);

		const inputs = {
			'text': data_text,
			'display_item_value_as': data_item_value,
			'display': display_item_as == display_as_numeric,
			'min': show_min_max,
			'max': show_min_max,
			'decimal_places': display_item_as == display_as_numeric,
			'show_thumbnail': display_item_as == display_as_binary,
			'aggregate_function': data_item_value,
			'history': display_item_as == display_as_numeric
		}

		for (const input_name in inputs) {
			for (const input of this.#form.querySelectorAll(`[name=${input_name}`)) {
				input.disabled = !inputs[input_name];
			}
		}

		// Toggle time period selector visibility and enable/disable state.
		this.#form.fields.time_period.disabled = !data_item_value || aggregate_function == <?= AGGREGATE_NONE ?>;

		document.querySelectorAll('.js-time_period-data-source').forEach(element => {
			element.style.display = data_item_value && aggregate_function != <?= AGGREGATE_NONE ?> ? '' : 'none';
		});
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
