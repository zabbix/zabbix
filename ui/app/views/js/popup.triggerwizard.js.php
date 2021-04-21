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

(function ($) {
	var trigger_row_tmpl = new Template($('#tmpl_expressions_list_row').html()),
		expr_part_row_tmpl = new Template($('#tmpl_expressions_part_list_row').html()),
		$expr_table = $('#expressions_list'),
		$expr_parts_table = $('#key_list'),
		$expr_input = $('#logexpr'),
		$iregexp_checkbox = $('#iregexp'),
		$and_button = $('#add_key_and'),
		$or_button = $('#add_key_or'),
		expr_type_select = $('z-select#expr_type').get(0),
		$add_button = $('#add_exp'),
		data = $expr_table.data('rows')||[];

	// Expression parts table.
	$expr_parts_table.on('click', '.<?= ZBX_STYLE_BTN_LINK ?>', function () {
		var row = $(this).closest('tr');

		if (!row.siblings().length) {
			$and_button.prop('disabled', false);
			$or_button.prop('disabled', false);
		}

		row.remove();
	});

	// Button AND, OR click handler.
	$and_button.on('click', addKeywordButtonsClick);
	$or_button.on('click', addKeywordButtonsClick);

	// Expression sortable table rows initialization.
	if (data) {
		data.forEach(function (row_data) {
			$expr_table.find('tbody').append(
				$(trigger_row_tmpl.evaluate(row_data)).data('row-details', row_data.details)
			);
		});

		if (data.length == 1) {
			$expr_table.find('td.<?= ZBX_STYLE_TD_DRAG_ICON ?>').addClass('<?= ZBX_STYLE_DISABLED ?>');
		}
	}

	// Expression sortable table.
	$expr_table.sortable({
		disabled: data.length < 2,
		items: 'tbody tr.sortable',
		axis: 'y',
		cursor: 'grabbing',
		handle: 'div.<?= ZBX_STYLE_DRAG_ICON ?>',
		containment: '#expressions_list tbody',
		tolerance: 'pointer',
		opacity: 0.6
	}).on('click', '.<?= ZBX_STYLE_BTN_LINK ?>', function() {
		var row = $(this).closest('tr');

		if (row.siblings().length == 1) {
			$expr_table.sortable('disable');
			$expr_table.find('td.<?= ZBX_STYLE_TD_DRAG_ICON ?>').addClass('<?= ZBX_STYLE_DISABLED ?>');
		}

		row.remove();
	});

	// Button Add click handler.
	$add_button.on('click', function () {
		var expression = [],
			expression_raw = [],
			$inputs = $('[name^="keys["]'),
			$keywords = $inputs.filter('[name$="[value]"]'),
			items = $('#itemid').data('multiSelect').values.selected,
			parts = [],
			query = '',
			operator = '',
			pattern = '';

		for (var x in items) {
			query = items[x].query;
			break;
		}
		if (query === '') {
			return false;
		}

		$inputs.filter('[name$="[type]"]').each(function (i, el) {
			var pattern = '"' + $keywords[i].value + '"';
			var operator = '"' + el.value + '"';

			expression.push('find(' + query + ',,' + operator + ',' + pattern + ')');
			expression_raw.push('find($,,' + operator + ',' + pattern + ')');
			parts.push({operator: operator, pattern: pattern});
			$(el).closest('tr').remove();
		});

		if ($expr_input.val() !== '') {
			operator = $iregexp_checkbox.is(':checked') ? '"iregexp"' : '"regexp"';
			pattern = '"' + $expr_input.val().replace(/"/g, '\\"') + '"';
			expression.push('find(' + query + ',,' + operator + ',' + pattern + ')');
			expression_raw.push('find($,,' + operator + ',' + pattern + ')');
			parts.push({operator: operator, pattern: pattern});
			$expr_input.val('');
		}

		if ($expr_table.find('tbody > tr').length > 0) {
			$expr_table.sortable('enable');
		}

		if (expression.length) {
			const {label, value} = expr_type_select.getOptionByIndex(expr_type_select.selectedIndex);
			var logical_operator = $and_button.is(':enabled') ? ' and ' : ' or ',
				row = $(trigger_row_tmpl.evaluate({
						expression: expression.join(logical_operator),
						expression_raw: expression_raw.join(logical_operator),
						type_label: label,
						type: value
					}))
					.data('row-details', {
						logical_operator: logical_operator,
						parts: parts
					});
			$expr_table.find('tbody').append(row);

			var $icons = $expr_table.find('tbody td.<?= ZBX_STYLE_TD_DRAG_ICON ?>');
			$icons.toggleClass('<?= ZBX_STYLE_DISABLED ?>', $icons.length == 1);

			$and_button.prop('disabled', false);
			$or_button.prop('disabled', false);
		}
	});

	/**
	 * Click handler for 'AND' and 'OR' buttons.
	 */
	function addKeywordButtonsClick() {
		if ($expr_input.val() == '') {
			return;
		}

		$expr_parts_table.find('tbody').append(expr_part_row_tmpl.evaluate({
			keyword: $expr_input.val(),
			type_label: $iregexp_checkbox.is(':checked') ? 'iregexp' : 'regexp'
		}));

		if ($(this).is($and_button)) {
			$or_button.prop('disabled', true);
		}
		else {
			$and_button.prop('disabled', true);
		}

		$expr_input.val('');
	}

	$('#itemid').on('change', function(ev, ms) {
		var query = '';
		for (var x in ms.values.selected) {
			query = ms.values.selected[x].query;
			break;
		}

		if (query !== '') {
			$add_button.prop('disabled', false);
		}
		else {
			$add_button.prop('disabled', true);
			return;
		}

		$expr_table.find('tbody > tr').each((i, tr) => {
			var details = $(tr).data('row-details'),
				expressions = [],
				expressions_raw = [],
				expr_str = '',
				expr_raw = '';

			details.parts.forEach(part => {
				expressions.push('find(' + query + ',,' + part.operator + ',' + part.pattern + ')');
				expressions_raw.push('find($,,' + part.operator + ',' + part.pattern + ')');
			});
			expr_str = expressions.join(details.logical_operator);
			expr_raw = expressions_raw.join(details.logical_operator);

			$(tr).find('div[data-expr]').html(expr_str);
			$(tr).find('[name="expressions[][value]"]').val(expr_raw);
		});
	});

	$('#event_name')
		.textareaFlexible()
		.on('input keydown paste', function() {
			overlays_stack.end().centerDialog();
		});
})(jQuery);

/**
 * Submit trigger wizard form to save.
 *
 * @param {Overlay} overlay
 */
function validateTriggerWizard(overlay) {
	var $form = overlay.$dialogue.find('form'),
		url = new Curl($form.attr('action'));

	$form.trimValues(['#description', '#logexpr']);

	url.setArgument('save', 1);

	overlay.setLoading();
	overlay.xhr = jQuery.ajax({
		url: url.getUrl(),
		data: $form.serialize(),
		complete: function() {
			overlay.unsetLoading();
		},
		success: function(ret) {
			overlay.$dialogue.find('.<?= ZBX_STYLE_MSG_BAD ?>, .<?= ZBX_STYLE_MSG_GOOD ?>').remove();

			if (typeof ret.errors !== 'undefined') {
				jQuery(ret.errors).insertBefore($form);
			}
			else {
				overlayDialogueDestroy(overlay.dialogueid);
				window.location.replace(window.location.href);
			}
		},
		dataType: 'json',
		type: 'POST'
	});
}
