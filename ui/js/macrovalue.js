/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
 * CMacroValue element control.
 */
(function($) {
	'use strict';

	const ZBX_MACRO_TYPE_TEXT = 0;
	const ZBX_MACRO_TYPE_SECRET = 1;

	function btnUndoFocusEventHandle() {
		$(this)
			.closest('.input-group')
			.find('.btn-undo')
			.toggleClass('is-focused');
	}

	function btnUndoClickEventHandle() {
		var $this = $(this),
			$container = $this.closest('.input-group'),
			$input_container = $('.input-secret, .textarea-flexible', $container),
			$input = $('.input-secret input[type=password], .textarea-flexible', $container),
			$dropdown_value = $('.dropdown-value', $container);

		$input_container.replaceWith(
			$('<div>')
				.addClass('input-secret')
				.append(
					$('<input>')
						.attr({
							id: $input.attr('id'),
							name: $input.attr('name'),
							type: 'password',
							value: '******',
							placeholder: $input.attr('placeholder'),
							maxlength: $input.attr('maxlength'),
							disabled: true
						})
						.on('focus blur', btnUndoFocusEventHandle)
				)
				.append($('<button>').attr({
					type: 'button',
					id: $input.attr('name').replace(/\[/g, '_').replace(/\]/g, '') + '_btn',
					class: 'btn-change'
				}).text(t('Set new value')))
				.inputSecret()
		);

		$dropdown_value
			.val(ZBX_MACRO_TYPE_SECRET)
			.trigger('change');

		$('.btn-dropdown-container button', $container).addClass('btn-alt btn-dropdown-toggle icon-secret');

		$this.hide();
	}

	function inputDropdownValueChangeEventHandle() {
		var $this = $(this),
			value_type = $this.val(),
			$container = $this.closest('.input-group'),
			$input_container = $('.input-secret', $container),
			$textarea = $('.textarea-flexible', $container);

		if ((value_type == ZBX_MACRO_TYPE_TEXT && $textarea.length)
				|| (value_type == ZBX_MACRO_TYPE_SECRET && $input_container.length)) {
			return false;
		}

		if (value_type == ZBX_MACRO_TYPE_TEXT) {
			var $input = $('input[type=password]', $input_container);

			if (!$input_container.data('is-activated')) {
				$('.btn-undo', $container).show();
				$input_container.data('is-activated', true);
			}

			$input_container.replaceWith(
				$('<textarea>')
					.addClass('textarea-flexible')
					.attr({
						id: $input.attr('id'),
						name: $input.attr('name'),
						placeholder: $input.attr('placeholder'),
						maxlength: $input.attr('maxlength')
					})
					.text($input.is(':disabled') ? '' : $input.val())
					.on('focus blur', btnUndoFocusEventHandle)
			);

			$('.textarea-flexible', $container).textareaFlexible();
		}
		else {
			$textarea.replaceWith(
				$('<div>')
					.addClass('input-secret')
					.append(
						$('<input>')
							.attr({
								id: $textarea.attr('id'),
								name: $textarea.attr('name'),
								type: 'password',
								value: $textarea.val(),
								placeholder: $textarea.attr('placeholder'),
								maxlength: $textarea.attr('maxlength'),
								autocomplete: 'off'
							})
							.on('focus blur', btnUndoFocusEventHandle)
					)
					.inputSecret()
			);
		}
	}

	var methods = {
		init() {
			return this.each(function () {
				$('.input-secret input, .input-group .textarea-flexible', $(this))
					.off('focus blur', btnUndoFocusEventHandle)
					.on('focus blur', btnUndoFocusEventHandle);
				$('.btn-undo', $(this))
					.off('click', btnUndoClickEventHandle)
					.on('click', btnUndoClickEventHandle);
				$('.dropdown-value', $(this))
					.off('change', inputDropdownValueChangeEventHandle)
					.on('change', inputDropdownValueChangeEventHandle);
				$('.textarea-flexible', $(this)).textareaFlexible();
			});
		}
	};

	/**
	 * MacroValue helper.
	 */
	$.fn.macroValue = function(method) {
		if (methods[method]) {
			return methods[method].apply(this, Array.prototype.slice.call(arguments, 1));
		}

		return methods.init.apply(this, arguments);
	};
})(jQuery);
