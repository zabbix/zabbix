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


use Widgets\TopItems\Includes\CWidgetFieldColumnsList;

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
				'[name="display_value_as"], [name="aggregate_function"], [name="display"], [name="history"]'
			)
			.forEach(element => {
				element.addEventListener('change', () => this.#updateForm());
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
		const display_value_as = this.#form.querySelector('[name=display_value_as]:checked').value;
		const display = this.#form.querySelector('[name=display]:checked').value;

		// Display.
		const display_show = display_value_as == <?= CWidgetFieldColumnsList::DISPLAY_VALUE_AS_NUMERIC ?>;
		for (const element of this.#form.querySelectorAll('.js-display-row')) {
			element.style.display = display_show ? '' : 'none';

			for (const input of element.querySelectorAll('input')) {
				input.disabled = !display_show;
			}
		}

		// Sparkline.
		const sparkline_show = display_value_as == <?= CWidgetFieldColumnsList::DISPLAY_VALUE_AS_NUMERIC ?>
			&& display == <?= CWidgetFieldColumnsList::DISPLAY_SPARKLINE ?>;

		for (const element of this.#form.querySelectorAll('.js-sparkline-row')) {
			element.style.display = sparkline_show ? '' : 'none';

			for (const input of element.querySelectorAll('input')) {
				input.disabled = !sparkline_show;
			}
		}

		this.#form.fields['sparkline[time_period]'].disabled = !sparkline_show;

		// Min/Max.
		const min_max_show = display_value_as == <?= CWidgetFieldColumnsList::DISPLAY_VALUE_AS_NUMERIC ?>  && [
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
		const thresholds_show = display_value_as == <?= CWidgetFieldColumnsList::DISPLAY_VALUE_AS_NUMERIC ?>;
		for (const element of this.#form.querySelectorAll('.js-thresholds-row')) {
			element.style.display = thresholds_show ? '' : 'none';

			for (const input of element.querySelectorAll('input')) {
				input.disabled = !thresholds_show;
			}
		}

		// Decimal places.
		const decimals_show = display_value_as == <?= CWidgetFieldColumnsList::DISPLAY_VALUE_AS_NUMERIC ?>;
		for (const element of this.#form.querySelectorAll('.js-decimals-row')) {
			element.style.display = decimals_show ? '' : 'none';

			for (const input of element.querySelectorAll('input')) {
				input.disabled = !decimals_show;
			}
		}

		// Aggregation function.
		const aggregation_function_select = this.#form.querySelector('z-select[name=aggregate_function]');
		[<?= AGGREGATE_MIN ?>, <?= AGGREGATE_MAX ?>, <?= AGGREGATE_AVG ?>, <?= AGGREGATE_SUM ?>].forEach(option => {
			aggregation_function_select.getOptionByValue(option).disabled =
				display_value_as != <?= CWidgetFieldColumnsList::DISPLAY_VALUE_AS_NUMERIC ?>;
			aggregation_function_select.getOptionByValue(option).hidden =
				display_value_as != <?= CWidgetFieldColumnsList::DISPLAY_VALUE_AS_NUMERIC ?>;

			if (aggregation_function_select.value == option
					&& display_value_as != <?= CWidgetFieldColumnsList::DISPLAY_VALUE_AS_NUMERIC ?>) {
				aggregation_function_select.value = <?= AGGREGATE_NONE ?>;
			}
		});

		// Time period.
		const time_period_show = parseInt(document.getElementById('aggregate_function').value) != <?= AGGREGATE_NONE ?>;
		this.#form.fields.time_period.disabled = !time_period_show;
		this.#form.fields.time_period.hidden = !time_period_show;

		// History data.
		const history_show = display_value_as == <?= CWidgetFieldColumnsList::DISPLAY_VALUE_AS_NUMERIC ?>;
		for (const element of this.#form.querySelectorAll('.js-history-row')) {
			element.style.display = history_show ? '' : 'none';

			for (const input of element.querySelectorAll('input')) {
				input.disabled = !history_show;
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
