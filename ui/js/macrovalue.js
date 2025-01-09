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


/**
 * CMacroValue element control.
 */
(function($) {
	'use strict';

	const ZBX_MACRO_TYPE_TEXT = 0;
	const ZBX_MACRO_TYPE_SECRET = 1;
	const ZBX_MACRO_TYPE_VAULT = 2;

	const ZBX_STYLE_MACRO_VALUE_TEXT = 'macro-value-text';
	const ZBX_STYLE_MACRO_VALUE_SECRET = 'macro-value-secret';
	const ZBX_STYLE_MACRO_VALUE_VAULT = 'macro-value-vault';

	const ZBX_STYLE_ICON_SECRET = 'icon-secret';

	function btnUndoFocusEventHandle() {
		$(this)
			.closest('.macro-input-group')
			.find('.btn-undo')
			.toggleClass('is-focused');
	}

	function btnUndoClickEventHandle() {
		var $this = $(this),
			$container = $this.closest('.macro-input-group'),
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

		$('.btn-dropdown-container button', $container)
			.removeClass([ZBX_ICON_TEXT, ZBX_ICON_LOCK])
			.addClass(['btn-alt', 'btn-dropdown-toggle', ZBX_ICON_EYE_OFF]);
		$this.hide();
	}

	function getCurrentValueType($container) {
		if ($container.hasClass(ZBX_STYLE_MACRO_VALUE_VAULT)) {
			return ZBX_MACRO_TYPE_VAULT;
		}
		else if ($container.hasClass(ZBX_STYLE_MACRO_VALUE_SECRET)) {
			return ZBX_MACRO_TYPE_SECRET;
		}
		else {
			return ZBX_MACRO_TYPE_TEXT;
		}
	}

	function inputDropdownValueChangeEventHandle() {
		var $this = $(this),
			value_type = $this.val(),
			$container = $this.closest('.macro-input-group'),
			curr_value_type = getCurrentValueType($container);

		if (value_type == curr_value_type) {
			return false;
		}

		if (curr_value_type == ZBX_MACRO_TYPE_SECRET) {
			$container.removeClass(ZBX_STYLE_MACRO_VALUE_SECRET);

			var $curr_control = $('.input-secret', $container),
				$input = $('input[type=password]', $curr_control);
		}
		else {
			$container.removeClass(ZBX_STYLE_MACRO_VALUE_TEXT + ' ' + ZBX_STYLE_MACRO_VALUE_VAULT);

			var $curr_control = $('.textarea-flexible', $container),
				$input = $curr_control;
		}

		if (value_type == ZBX_MACRO_TYPE_SECRET) {
			$container.addClass(ZBX_STYLE_MACRO_VALUE_SECRET);

			$curr_control.replaceWith($('<div>')
				.addClass('input-secret')
				.append(
					$('<input>')
						.attr({
							id: $input.attr('id'),
							name: $input.attr('name'),
							type: 'password',
							value: $input.val(),
							placeholder: t('value'),
							maxlength: $input.attr('maxlength'),
							autocomplete: 'off',
							style: 'width: 100%;'
						})
						.on('focus blur', btnUndoFocusEventHandle)
				)
				.inputSecret()
			);
		}
		else {
			$container.addClass((value_type == ZBX_MACRO_TYPE_VAULT)
				? ZBX_STYLE_MACRO_VALUE_VAULT
				: ZBX_STYLE_MACRO_VALUE_TEXT
			);

			if (!$curr_control.data('is-activated')) {
				$('.btn-undo', $container).show();
				$curr_control.data('is-activated', true);
			}

			$curr_control.replaceWith($('<textarea>')
				.addClass('textarea-flexible')
				.attr({
					id: $input.attr('id'),
					name: $input.attr('name'),
					placeholder: t('value'),
					maxlength: $input.attr('maxlength'),
					spellcheck: false
				})
				.text($input.is(':disabled') ? '' : $input.val())
				.on('focus blur', btnUndoFocusEventHandle)
			);

			$('.textarea-flexible', $container).textareaFlexible();
		}
	}

	var methods = {
		init() {
			return this.each(function () {
				$('.input-secret input, .macro-input-group .textarea-flexible', $(this))
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
