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


(function($) {
	'use strict';

	var methods = {
		init: function(options) {
			this.options = $.extend({
				'modal_title': '',
				'title': '',
				'value': '',
				'placeholder': '',
				'maxlength': 65535,
				'readonly': false,
				'disabled': false,
				'line_numbers': true,
				'monospace_font': true
			}, options);

			this.$hidden = $('<input>', {type: 'hidden', name: this.data('name')});
			this.$input = $('<input>', {
				type: 'text',
				placeholder: options.placeholder,
				title: this.options.title,
				tabindex: -1
			})
				.toggleClass('monospace-font', this.options.monospace_font)
				.prop('readonly', this.options.readonly ? true : null)
				.on('click', this, openModal);
			this.$button = $('<button>', {type: 'button'})
				.text(t('S_OPEN'))
				.on('click', this, openModal);

			this.on('disable enable', this, setDisabled);

			methods.value.call(this, this.options.value || '');

			return this
				.append(this.$hidden, this.$input, this.$button)
				.trigger(this.options.disabled ? 'disable' : 'enable');
		},
		open: function() {
			this.$button.trigger('click');

			return this;
		},
		value: function(value) {
			if (typeof value === 'undefined') {
				return this.$hidden.val();
			}
			else {
				value = $.trim(value);
				this.$hidden.val(value);
				this.$input.val(value.split("\n")[0]);
				this.trigger('change');

				return this;
			}
		},
		destroy: function() {}
	};

	function setDisabled(e) {
		var disabled = (e.type === 'disable');

		e.data.options.disabled = disabled;
		e.data.$hidden.prop('disabled', disabled ? true : null);
		e.data.$input.prop('disabled', disabled ? true : null);
		e.data.$button.prop('disabled', disabled ? true : null);
	}

	function openModal(e) {
		e.preventDefault();

		if (e.data.options.disabled) {
			return;
		}

		var $textarea = $('<textarea>', {
				class: 'multilineinput-textarea',
				text: e.data.$hidden.val(),
				maxlength: e.data.options.maxlength,
				readonly: e.data.options.readonly ? true : null
			})
				.attr('wrap', 'off')
				.toggleClass('monospace-font', e.data.options.monospace_font),
			$line_numbers = $('<ul>', {class: 'multilineinput-line-numbers'}).append('<li>'),
			$footer = $('<div>', {class: 'multilineinput-symbols-remaining'})
				.html(sprintf(t('S_N_SYMBOLS_REMAINING'), '<span>0</span>'));

		function updateSymbolsRemaining(count) {
			$('span', $footer).text(count);
		}

		function updateLineNumbers(lines_count) {
			var diff = lines_count - $line_numbers[0].childElementCount,
				li = '';

			while (diff > 0) {
				li += '<li></li>';
				diff--;
			}
			$line_numbers.append(li);

			while (diff < 0) {
				$line_numbers.find('li:eq(0)').remove();
				diff++;
			}

			$textarea.css('margin-left', $line_numbers.outerWidth());
			$line_numbers.css('top', -Math.floor($textarea.prop('scrollTop')));
		}

		overlayDialogue({
			'title': e.data.options.modal_title,
			'class': 'multilineinput-modal',
			'content': $('<div>', {class: 'multilineinput-container'}).append(
				e.data.options.line_numbers ? $line_numbers : '', $textarea
			),
			'footer': $footer,
			'buttons': [
				{
					title: t('S_SAVE'),
					action: function() {
						var value = $.trim($textarea.val());
						e.data.$input.val(value.split("\n")[0]);
						e.data.$hidden.val(value);
					}
				},
				{
					title: t('S_CANCEL'),
					class: 'btn-alt',
					action: function() {}
				}
			]
		}, e.data.$button);

		$textarea[0].setSelectionRange(0, 0);

		$textarea
			.on('change contextmenu keydown keyup paste scroll', function() {
				var value = $(this).val();
				updateSymbolsRemaining($(this).attr('maxlength') - value.length);
				if (e.data.options.line_numbers) {
					updateLineNumbers(value.split("\n").length);
				}
			})
			.trigger('change')
			.focus();
	}

	/**
	 * Multiline input helper.
	 */
	$.fn.multilineInput = function(method) {
		if (methods[method]) {
			return methods[method].apply(this, Array.prototype.slice.call(arguments, 1));
		} else if (typeof method === 'object' || ! method) {
			return methods.init.apply(this, arguments);
		} else {
			$.error('Invalid method "' +  method + '".');
		}
	};
})(jQuery);
