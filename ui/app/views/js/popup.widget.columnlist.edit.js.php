<?php
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


/**
 * @var CView $this
 */
?>
(function () {
	let $widget_form = $('form[name="<?= $data['form'] ?>"]');
	let $thresholds_table = $widget_form.find('#thresholds_table');

	$('[name="display"],[name="data"],[name="aggregate_function"]', $widget_form).change(updateAccessability);

	$thresholds_table.dynamicRows({
		rows: <?= json_encode($data['thresholds']) ?>,
		template: '#thresholds-row-tmpl',
		dataCallback: row_data => row_data.color = colorPalette.getNextColor()
	});
	$('tr.form_row input[name$="[color]"]', $thresholds_table).each((i, colorpicker) => {
		$(colorpicker).colorpicker({appendTo: $(colorpicker).closest('.input-color-picker')});
	});
	$thresholds_table.on('afteradd.dynamicRows', e => {
		let $colorpicker = $('tr.form_row:last input[name$="[color]"]', e.target);

		$colorpicker.colorpicker({appendTo: $colorpicker.closest('.input-color-picker')});
	});
	$thresholds_table.on('blur afterremove.dynamicRows', 'input[name$="[threshold]"]', sortThresholdsTable);
	$widget_form.on('process.form', handleFormSubmit);

	function sortThresholdsTable() {
		let rows = [];

		$thresholds_table.find('tr.form_row').each((i, row) => {
			let color = $('input[name$="[color]"]', row).val();
			let threshold = $('input[name$="[threshold]"]', row).val();

			if (isNaN(+threshold) || $.trim(threshold) === '') {
				rows = [];

				return false;
			}

			rows[rows.length] = {color: color, threshold: threshold};
		});

		if (!rows.length) {
			return;
		}

		rows = rows.sort((a, b) => a.threshold - b.threshold);

		$thresholds_table.find('tr.form_row').each((i, row) => {
			let $colorinput = $('input[name$="[color]"]', row);

			$.colorpicker('set_color_by_id', $colorinput.attr('id'), rows[i].color);
			$('input[name$="[threshold]"]', row).val(rows[i].threshold);
		});
	}

	function updateAccessability() {
		let display_as_is = ($('[name="display"]:checked').val() == <?= CWidgetFieldColumnsList::DISPLAY_AS_IS ?>);
		let data_item_value = ($('[name="data"]').val() == <?= CWidgetFieldColumnsList::DATA_ITEM_VALUE ?>);
		let data_text = ($('[name="data"]').val() == <?= CWidgetFieldColumnsList::DATA_TEXT ?>);
		let no_aggregate_function = $('[name="aggregate_function"]').val() == <?= CWidgetFieldColumnsList::FUNC_NONE ?>;

		$('#item', $widget_form).multiSelect(data_item_value ? 'enable' : 'disable');
		$('[name="aggregate_function"],[name="timeshift"]', $widget_form).attr('disabled', !data_item_value);
		$('[name="aggregate_interval"]', $widget_form).attr('disabled', !data_item_value || no_aggregate_function);
		$('[name="display"],[name="history"]', $widget_form).attr('disabled', !data_item_value);
		$('[name="text"]', $widget_form).attr('disabled', !data_text);
		$('[name="min"],[name="max"]', $widget_form).attr('disabled', display_as_is || !data_item_value);
		$thresholds_table.toggleClass('disabled', !data_item_value);
		$('[name$="[color]"],[name$="[threshold]"],button', $thresholds_table).attr('disabled', !data_item_value);
	}

	function handleFormSubmit(e, overlay) {
		let url = new Curl(e.target.getAttribute('action'));
		let body_selector = `.overlay-dialogue[data-dialogueid='${overlay.dialogueid}'] .overlay-dialogue-body`;

		fetch(url.getUrl(), {
				method: 'POST',
				body: new URLSearchParams(new FormData(e.target))
			})
			.then(response => response.json())
			.then(response => {
				overlay.$dialogue.find('.msg-bad, .msg-good').remove();

				if (response.errors) {
					document
						.querySelector(body_selector)
						.prepend($(response.errors).get(0));
					overlay.unsetLoading();

					return;
				}

				$(overlay.element).trigger('data.ready', [response]);
				overlayDialogueDestroy(overlay.dialogueid);
			})
			.catch((e) => {
				document
					.querySelector(body_selector)
					.prepend(makeMessageBox('bad', e, null)[0]);
				overlay.unsetLoading();
			});
	}

	// Initialize form elements accessibility.
	updateAccessability();
})();
