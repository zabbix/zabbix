<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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


ob_start(); ?>

(function ($) {
	var trigger_row_tmpl = new Template($('#tmpl_expressions_list_row').html()),
		expr_part_row_tmpl = new Template($('#tmpl_expressions_part_list_row').html()),
		$expr_table = $('#expressions_list'),
		$expr_parts_table = $('#key_list'),
		$expr_input = $('#logexpr'),
		$iregexp_checkbox = $('#iregexp'),
		$and_button = $('#add_key_and'),
		$or_button = $('#add_key_or'),
		$expt_type = $('#expr_type'),
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
		data.each(function (row_data) {
			$expr_table.find('tbody').append(trigger_row_tmpl.evaluate(row_data));
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
		cursor: IE ? 'move' : 'grabbing',
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
			$inputs = $('[name^="keys["]'),
			$keywords = $inputs.filter('[name$="[value]"]');

		$inputs.filter('[name$="[type]"]').each(function (i, el) {
			expression.push(el.value + '(' + $keywords[i].value + ')');
			$(el).closest('tr').remove();
		});

		if ($expr_input.val() != '') {
			expression.push(($iregexp_checkbox.is(':checked') ? 'i' : '') + 'regexp(' + $expr_input.val() + ')');
			$expr_input.val('');
		}

		if ($expr_table.find('tbody > tr').length > 0) {
			$expr_table.sortable('enable');
		}

		if (expression.length) {
			$expr_table.find('tbody').append(trigger_row_tmpl.evaluate({
				expression: expression.join($and_button.is(':enabled') ? ' and ' : ' or '),
				type_label: $('option:selected', $expt_type).text(),
				type: $('option:selected', $expt_type).val()
			}));

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
			type_label: ($iregexp_checkbox.is(':checked') ? 'i' : '') + 'regexp'
		}));

		if ($(this).is($and_button)) {
			$or_button.prop('disabled', true);
		}
		else {
			$and_button.prop('disabled', true);
		}

		$expr_input.val('');
	}
})(jQuery);

/**
 * Submit trigger wizard form to save.
 *
 * @param {string} formname		Form name that is sent to server.
 * @param {string} dialogueid	(optional) id of overlay dialogue.
 */
function validateTriggerWizard(formname, dialogueid) {
	var form = window.document.forms[formname],
		url = new Curl(jQuery(form).attr('action')),
		dialogueid = dialogueid || null;

	jQuery(form).trimValues(['#description', '#logexpr']);

	url.setArgument('save', 1);

	jQuery.ajax({
		url: url.getUrl(),
		data: jQuery(form).serialize(),
		success: function(ret) {
			jQuery(form).parent().find('.<?= ZBX_STYLE_MSG_BAD ?>, .<?= ZBX_STYLE_MSG_GOOD ?>').remove();

			if (typeof ret.errors !== 'undefined') {
				jQuery(ret.errors).insertBefore(jQuery(form));
			}
			else if (dialogueid) {
				overlayDialogueDestroy(dialogueid);
				window.location.replace(window.location.href);
			}
		},
		dataType: 'json',
		type: 'post'
	});
}

<?php return ob_get_clean(); ?>
