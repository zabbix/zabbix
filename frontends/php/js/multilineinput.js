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

	function setDisabled(e) {
		if (e.data[0] !== e.target) {
			return;
		}

		e.data.options.disabled = (e.type === 'disable');
		e.data.$hidden.prop('disabled', e.data.options.disabled ? true : null);
		e.data.$input
			.prop('disabled', e.data.options.disabled ? true : null)
			.prop('readonly', e.data.options.disabled ? null : true);
		e.data.$button.prop('disabled', e.data.options.disabled ? true : null);

		$(this)
			.toggleClass('multilineinput-readonly', (e.data.options.readonly && !e.data.options.disabled))
			.toggleClass('multilineinput-disabled', e.data.options.disabled);
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
			'title': e.data.options.title,
			'class': 'multilineinput-modal',
			'content': $('<div>', {class: 'multilineinput-container'}).append(
				e.data.options.line_numbers ? $line_numbers : '', $textarea
			),
			'footer': $footer,
			'buttons': [
				{
					title: t('S_APPLY'),
					action: function() {
						var value = $.trim($textarea.val());
						e.data.$input.val(value.split("\n")[0]);
						e.data.$hidden.val(value);
					},
					enabled: !e.data.options.readonly
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

	var methods = {
		init: function(options) {
			this.options = $.extend({
				title: '',
				hint: t('S_CLICK_TO_VIEW_OR_EDIT'),
				value: '',
				placeholder: '',
				maxlength: 255,
				readonly: false,
				disabled: false,
				line_numbers: true,
				monospace_font: true
			}, options);

			this.$hidden = $('<input>', {
				type: 'hidden',
				name: this.data('name')
			});

			this.$input = $('<input>', {
				type: 'text',
				placeholder: options.placeholder,
				title: this.options.hint,
				tabindex: -1
			})
				.toggleClass('monospace-font', this.options.monospace_font)
				.prop('readonly', this.options.disabled ? null : true)
				.on('click', this, openModal);

			this.$button = $('<button>', {
				type: 'button',
				title: this.options.hint
			}).on('click', this, openModal);

			this.on('disable enable', this, setDisabled);

			methods.value.call(this, this.options.value);

			return this
				.append(this.$hidden, this.$input, this.$button)
				.trigger(this.options.disabled ? 'disable' : 'enable');
		},
		/**
		 * @param {string|undefined}  value  Set field value. Without parameter - get field value
		 *
		 * @returns {string|object}
		 */
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
		disable: function() {
			//jQuery(this).options.disabled = true;
			this.trigger('disable');

			return this;
		},
		enable: function() {
			//jQuery(this).options.disabled = false;
			this.trigger('enable');

			return this;
		},
		destroy: function() {}
	};

	/**
	 * Multiline input helper.
	 */
	$.fn.multilineInput = function(method) {
		if (methods[method]) {
			return methods[method].apply(this, Array.prototype.slice.call(arguments, 1));
		}

		return methods.init.apply(this, arguments);
	};
})(jQuery);
