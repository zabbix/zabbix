<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
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


use Widgets\TopItems\Includes\CWidgetFieldColumnsList;
use Widgets\TopItems\Widget;

?>

window.topitems_column_edit_form = new class {

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

	init({form_id, thresholds, highlights, colors}) {
		this.#overlay = overlays_stack.getById('topitems-column-edit-overlay');
		this.#dialogue = this.#overlay.$dialogue[0];
		this.#form = document.getElementById(form_id);

		this.#thresholds_table = document.getElementById('thresholds_table');
		this.#highlights_table = document.getElementById('highlights_table');

		this.#form
			.querySelectorAll(
				'[name="display_value_as"], [name="aggregate_function"], [name="display"], [name="history"], [name="aggregate_columns"]'
			)
			.forEach(element => {
				element.addEventListener('change', () => this.#updateForm());
			});

		this.#form.addEventListener('change', ({target}) => {
			if (target.matches('[type="text"]')) {
				target.value = target.value.trim();
			}
		});

		colorPalette.setThemeColors(colors);

		// Initialize thresholds table.
		$(this.#thresholds_table)
			.dynamicRows({
				rows: thresholds,
				template: '#thresholds-row-tmpl',
				allow_empty: true,
				dataCallback: (row_data) => {
					if (!('color' in row_data)) {
						const color_pickers = this.#form.querySelectorAll(`.${ZBX_STYLE_COLOR_PICKER}`);
						const used_colors = [];

						for (const color_picker of color_pickers) {
							if (color_picker.color !== '') {
								used_colors.push(color_picker.color);
							}
						}

						row_data.color = colorPalette.getNextColor(used_colors);
					}
				}
			})
			.on('afteradd.dynamicRows', () => this.#updateForm())
			.on('afterremove.dynamicRows', () => this.#updateForm());

		// Initialize highlights table.
		$(this.#highlights_table)
			.dynamicRows({
				rows: highlights,
				template: '#highlights-row-tmpl',
				allow_empty: true,
				dataCallback: (row_data) => {
					if (!('color' in row_data)) {
						const color_pickers = this.#form.querySelectorAll(`.${ZBX_STYLE_COLOR_PICKER}`);
						const used_colors = [];

						for (const color_picker of color_pickers) {
							if (color_picker.color !== '') {
								used_colors.push(color_picker.color);
							}
						}

						row_data.color = colorPalette.getNextColor(used_colors);
					}
				}
			})
			.on('afteradd.dynamicRows', () => this.#updateForm())
			.on('afterremove.dynamicRows', () => this.#updateForm());

		// Initialize Advanced configuration collapsible.
		const collapsible = this.#form.querySelector(`fieldset.<?= ZBX_STYLE_COLLAPSIBLE ?>`);
		new CFormFieldsetCollapsible(collapsible);

		// Field trimming.
		this.#form.querySelectorAll('[name="min"], [name="max"]').forEach(element => {
			element.addEventListener('change', (e) => e.target.value = e.target.value.trim(), {capture: true});
		});

		// Initialize form elements accessibility.
		this.#updateForm();

		this.#form.style.display = '';
		this.#overlay.recoverFocus();

		this.#form.addEventListener('submit', () => this.submit());
	}

	/**
	 * Updates widget column configuration form field visibility, enable/disable state and available options.
	 */
	#updateForm() {
		const display_value_as = this.#form.querySelector('[name="display_value_as"]:checked').value;
		const display_value_as_numeric = display_value_as == <?= CWidgetFieldColumnsList::DISPLAY_VALUE_AS_NUMERIC ?>;
		const display = this.#form.querySelector('[name="display"]:checked').value;
		const display_sparkline = display == <?= CWidgetFieldColumnsList::DISPLAY_SPARKLINE ?>;

		// Display.
		for (const element of this.#form.querySelectorAll('.js-display-row')) {
			element.style.display = display_value_as_numeric ? '' : 'none';

			for (const input of element.querySelectorAll('input')) {
				input.disabled = !display_value_as_numeric;
			}
		}

		// Sparkline.
		const sparkline_show = display_value_as_numeric && display_sparkline;

		for (const element of this.#form.querySelectorAll('.js-sparkline-row')) {
			element.style.display = sparkline_show ? '' : 'none';

			for (const input of element.querySelectorAll('input')) {
				input.disabled = !sparkline_show;
			}
		}
		this.#form.fields['sparkline[time_period]'].disabled = !sparkline_show;

		// Min/Max.
		const min_max_show = display_value_as_numeric && [
			'<?= CWidgetFieldColumnsList::DISPLAY_BAR ?>',
			'<?= CWidgetFieldColumnsList::DISPLAY_INDICATORS ?>'
		].includes(display);

		for (const element of this.#form.querySelectorAll('.js-min-max-row')) {
			element.style.display = min_max_show ? '' : 'none';

			for (const input of element.querySelectorAll('input')) {
				input.disabled = !min_max_show;
			}
		}

		// Highlights.
		const highlights_show = display_value_as == <?= CWidgetFieldColumnsList::DISPLAY_VALUE_AS_TEXT ?>;
		for (const element of this.#form.querySelectorAll('.js-highlights-row')) {
			element.style.display = highlights_show ? '' : 'none';

			for (const input of element.querySelectorAll('input')) {
				input.disabled = !highlights_show;
			}
		}

		// Thresholds.
		for (const element of this.#form.querySelectorAll('.js-thresholds-row')) {
			element.style.display = display_value_as_numeric ? '' : 'none';

			for (const input of element.querySelectorAll('input')) {
				input.disabled = !display_value_as_numeric;
			}
		}

		// Decimal places.
		for (const element of this.#form.querySelectorAll('.js-decimals-row')) {
			element.style.display = display_value_as_numeric ? '' : 'none';

			for (const input of element.querySelectorAll('input')) {
				input.disabled = !display_value_as_numeric;
			}
		}

		// Aggregation function.
		const aggregation_function_select = this.#form.querySelector('z-select[name=aggregate_function]');

		for (const option of [<?= AGGREGATE_MIN ?>, <?= AGGREGATE_MAX ?>, <?= AGGREGATE_AVG ?>, <?= AGGREGATE_SUM ?>]) {
			aggregation_function_select.getOptionByValue(option).disabled = !display_value_as_numeric;
			aggregation_function_select.getOptionByValue(option).hidden = !display_value_as_numeric;

			if (!display_value_as_numeric && aggregation_function_select.value == option) {
				aggregation_function_select.value = <?= AGGREGATE_NONE ?>;
			}
		}

		// Time period.
		const time_period_show = parseInt(document.getElementById('aggregate_function').value) != <?= AGGREGATE_NONE ?>;
		this.#form.fields.time_period.disabled = !time_period_show;
		this.#form.fields.time_period.hidden = !time_period_show;

		// History data.
		for (const element of this.#form.querySelectorAll('.js-history-row')) {
			element.style.display = display_value_as_numeric ? '' : 'none';

			for (const input of element.querySelectorAll('input')) {
				input.disabled = !display_value_as_numeric;
			}
		}

		// Combined fields.
		const aggregate_columns = document.querySelector('[name="aggregate_columns"]');
		if (aggregate_columns != null) {
			aggregate_columns.disabled = !display_value_as_numeric || display_sparkline;
		}

		const combined_fields_show = display_value_as_numeric && aggregate_columns.checked && !display_sparkline;

		const column_aggregate_function = document.querySelector('[name="column_aggregate_function"]');
		if (column_aggregate_function != null) {
			column_aggregate_function.disabled = !combined_fields_show;
		}

		const combined_column_name = document.querySelector('[name="combined_column_name"]');
		if (combined_column_name != null) {
			combined_column_name.disabled = !combined_fields_show;
		}

		for (const element of this.#form.querySelectorAll('.js-aggregate-grouping-row')) {
			element.style.display = display_value_as_numeric && !display_sparkline ? '' : 'none';
		}

		for (const element of this.#form.querySelectorAll('.js-combined-row')) {
			element.style.display = combined_fields_show ? '' : 'none';
		}

		const aggregate_function_warning = this.#form.querySelector('.js-aggregate-function-warning');
		if (aggregate_function_warning != null) {
			const warning_show = display_value_as_numeric && display_sparkline
				&& parseInt(document.getElementById('aggregate_function').value) != <?= AGGREGATE_NONE ?>;

			aggregate_function_warning.style.display = warning_show ? '' : 'none';
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
