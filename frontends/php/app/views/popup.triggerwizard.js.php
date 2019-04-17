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


return <<<'JAVASCRIPT'
(function ($) {
	var trigger_row_tmpl = new Template($('#tmpl_expressions_list_row').html()),
		expr_part_row_tmpl = new Template($('#tmpl_expressions_part_list_row').html()),
		elements = {
			expressions_table: $('#expressions_list'),
			expression_part_table: $('#key_list'),
			input: $('#logexpr'),
			iregexp: $('#iregexp'),
			and_button: $('#add_key_and'),
			or_button: $('#add_key_or'),
			type: $('#expr_type'),
			add_button: $('#add_exp')
		},
		data = elements.expressions_table.data('rows')||[];

	// Expression parts table.
	elements.expression_part_table.on('click', '.nowrap button', function () {
		var row = $(this).closest('tr');

		if (!row.siblings().length) {
			elements.and_button.attr('disabled', false);
			elements.or_button.attr('disabled', false);
		}

		row.remove();
	});

	// Button AND, OR click handler.
	elements.and_button.click(addKeywordButtonsClick);
	elements.or_button.click(addKeywordButtonsClick);

	// Expression sortable table rows initialization.
	if (data) {
		data.each(function (row_data) {
			elements.expressions_table.find('tbody').append(trigger_row_tmpl.evaluate(row_data));
		});
	}

	// Expression sortable table.
	elements.expressions_table.sortable({
		disabled: data.length < 2,
		items: 'tbody tr.sortable',
		axis: 'y',
		cursor: 'move',
		handle: 'div.drag-icon',
		containment: '#expressions_list tbody',
		tolerance: 'pointer',
		opacity: 0.6
	}).on('click', '.nowrap button', function () {
		var row = $(this).closest('tr');

		if (row.siblings().length == 1) {
			elements.expressions_table.sortable('disable');
		}

		row.remove();
	});

	// Button Add click handler.
	elements.add_button.click(function () {
		var expression = [],
			inputs = $('[name^="keys["]'),
			keywords = inputs.filter('[name$="[value]"]');

		inputs.filter('[name$="[type]"]').each(function (i, el) {
			expression.push(el.value + '(' + keywords[i].value + ')');
			$(el).closest('tr').remove();
		});

		if (elements.input.val() != '') {
			expression.push((elements.iregexp.is(':checked') ? 'i' : '') + 'regexp(' + elements.input.val() + ')');
			elements.input.val('');
		}

		if (elements.expressions_table.find('tbody > tr').length > 0) {
			elements.expressions_table.sortable('enable');
		}

		if (expression.length) {
			elements.expressions_table.find('tbody').append(trigger_row_tmpl.evaluate({
				expression: expression.join(elements.and_button.is(':enabled') ? ' and ' : ' or '),
				type_label: $('option:selected', elements.type).text(),
				type: $('option:selected', elements.type).val()
			}));

			elements.and_button.attr('disabled', false);
			elements.or_button.attr('disabled', false);
		}
	});

	/**
	 * Click handler for 'AND' and 'OR' buttons.
	 */
	function addKeywordButtonsClick() {
		if (elements.input.val() == '') {
			return;
		}

		elements.expression_part_table.find('tbody').append(expr_part_row_tmpl.evaluate({
			keyword: elements.input.val(),
			type_label: (elements.iregexp.is(':checked') ? 'i' : '') + 'regexp'
		}));

		if ($(this).is(elements.and_button)) {
			elements.or_button.attr('disabled', true);
		}
		else {
			elements.and_button.attr('disabled', true);
		}

		elements.input.val('');
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
			jQuery(form).parent().find('.msg-bad, .msg-good').remove();

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
JAVASCRIPT;
