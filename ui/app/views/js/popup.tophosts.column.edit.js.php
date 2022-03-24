<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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


window.tophosts_column_edit_form = new class {

	init({form_name, thresholds, thresholds_colors}) {
		this._$widget_form = $(`form[name="${form_name}"]`);
		this._$thresholds_table = this._$widget_form.find('#thresholds_table');

		$('[name="data"], [name="aggregate_function"], [name="display"]', this._$widget_form).on('change', () => {
			this._update();
		});

		colorPalette.setThemeColors(thresholds_colors);

		this._$thresholds_table.dynamicRows({
			rows: thresholds,
			template: '#thresholds-row-tmpl',
			dataCallback: (row_data) => {
				if (!('color' in row_data)) {
					row_data.color = colorPalette.getNextColor();
				}
			}
		});

		$('tr.form_row input[name$="[color]"]', this._$thresholds_table).each((i, colorpicker) => {
			$(colorpicker).colorpicker({appendTo: $(colorpicker).closest('.input-color-picker')});
		});

		this._$thresholds_table
			.on('afteradd.dynamicRows', e => {
				const $colorpicker = $('tr.form_row:last input[name$="[color]"]', e.target);

				$colorpicker.colorpicker({appendTo: $colorpicker.closest('.input-color-picker')});

				this._update();
			})
			.on('afterremove.dynamicRows', () => this._update());

		this._$widget_form.on('process.form', (e, overlay) => {
			this.handleFormSubmit(e, overlay);
		});

		// Initialize form elements accessibility.
		this._update();

		this._$widget_form[0].style.display = '';
		this._$widget_form[0].querySelector('[name="name"]').focus();
	}

	_update() {
		const display_as_is = ($('[name="display"]:checked').val() == <?= CWidgetFieldColumnsList::DISPLAY_AS_IS ?>);
		const data_item_value = ($('[name="data"]').val() == <?= CWidgetFieldColumnsList::DATA_ITEM_VALUE ?>);
		const data_text = ($('[name="data"]').val() == <?= CWidgetFieldColumnsList::DATA_TEXT ?>);
		const no_aggregate_function = $('[name="aggregate_function"]').val() == <?= AGGREGATE_NONE ?>;

		$('#item', this._$widget_form).multiSelect(data_item_value ? 'enable' : 'disable');
		$('[name="aggregate_function"],[name="timeshift"]', this._$widget_form).attr('disabled', !data_item_value);
		$('[name="aggregate_interval"]', this._$widget_form).attr('disabled', !data_item_value || no_aggregate_function);
		$('[name="display"],[name="history"]', this._$widget_form).attr('disabled', !data_item_value);
		$('[name="text"]', this._$widget_form).attr('disabled', !data_text);
		$('[name="min"],[name="max"]', this._$widget_form).attr('disabled', display_as_is || !data_item_value);
		this._$thresholds_table.toggleClass('disabled', !data_item_value);
		$('[name$="[color]"],[name$="[threshold]"],button', this._$thresholds_table).attr('disabled', !data_item_value);

		// Toggle warning icons for non-numeric items settings.
		if (data_item_value) {
			document.getElementById('tophosts-column-aggregate-function-warning').style.display = no_aggregate_function
				? 'none'
				: '';
			document.getElementById('tophosts-column-display-warning').style.display = display_as_is ? 'none' : '';
			document.getElementById('tophosts-column-thresholds-warning').style.display =
				this._$thresholds_table[0].rows.length > 2 ? '' : 'none';
		}

		// Toggle visibility of disabled form elements.
		$('.form-grid > label', this._$widget_form).each((i, elm) => {
			const form_field = $(elm).next();
			const is_visible = (form_field.find(':disabled,.disabled').length == 0);

			$(elm).toggle(is_visible);
			form_field.toggle(is_visible);
		});
	}

	handleFormSubmit(e, overlay) {
		fetch(new Curl(e.target.getAttribute('action')).getUrl(), {
			method: 'POST',
			body: new URLSearchParams(new FormData(e.target))
		})
			.then(response => response.json())
			.then(response => {
				if ('errors' in response) {
					throw {html_string: response.errors};
				}

				overlayDialogueDestroy(overlay.dialogueid);

				overlay.$dialogue[0].dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
			})
			.catch((error) => {
				overlay.$dialogue.find('.msg-bad, .msg-good').remove();

				let message_box;

				if (typeof error === 'object' && 'html_string' in error) {
					message_box = new DOMParser().parseFromString(error.html_string, 'text/html').body.
						firstElementChild;
				}
				else {
					const error = <?= json_encode(_('Unexpected server error.')) ?>;

					message_box = makeMessageBox('bad', [], error, true, false)[0];
				}

				this._$widget_form.before(message_box, this);
			})
			.finally(() => {
				overlay.unsetLoading();
			});
	}
}();
