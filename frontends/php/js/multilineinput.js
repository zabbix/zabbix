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
			.prop('readonly', e.data.options.disabled ? null : true)
			.prop('title', e.data.options.disabled ? '' : e.data.options.hint);
		e.data.$button
			.prop('disabled', e.data.options.disabled ? true : null)
			.prop('title', e.data.options.disabled ? '' : e.data.options.hint);

		$(this)
			.toggleClass('multilineinput-readonly', (e.data.options.readonly && !e.data.options.disabled))
			.toggleClass('multilineinput-disabled', e.data.options.disabled);
	}

	function openModal(e) {
		e.preventDefault();

		if (e.data.options.disabled) {
			return;
		}

		function updateSymbolsRemaining(count) {
			$('span', $footer).text(count);
		}

		function updateLineNumbers(lines_count) {
			var min_rows = 3,
				line_height = 18,
				diff = lines_count - $line_numbers[0].childElementCount,
				li = '';

			switch (e.data.options.grow) {
				case 'fixed':
					$content.height(e.data.options.rows * line_height + 2);
					break;
				case 'auto':
					$content.height(Math.min(Math.max(lines_count, min_rows), e.data.options.rows) * line_height + 2);
					break;
				default:
					$content.height($content.css('max-height'));
			}

			while (diff > 0) {
				li += '<li></li>';
				diff--;
			}
			$line_numbers.append(li);

			while (diff < 0) {
				$line_numbers.find('li:eq(0)').remove();
				diff++;
			}

			$line_numbers.css('top', -Math.floor($textarea.prop('scrollTop') - 1));
			$textarea.css('margin-left', $line_numbers.outerWidth());
		}

		var height_offset = 190,
			$content = $('<div>', {class: 'multilineinput-container'}),
			$textarea = $('<textarea>', {
				class: 'multilineinput-textarea',
				text: e.data.$hidden.val(),
				maxlength: e.data.options.maxlength,
				readonly: e.data.options.readonly ? true : null,
				placeholder: e.data.options.placeholder_textarea
			}).attr('wrap', 'off'),
			$line_numbers = $('<ul>', {class: 'multilineinput-line-numbers'}).append('<li>'),
			$footer = $('<div>', {class: 'multilineinput-symbols-remaining'})
				.html(sprintf(t('S_N_SYMBOLS_REMAINING'), '<span>0</span>'));

		overlayDialogue({
			'title': e.data.options.title,
			'class': 'multilineinput-modal' + (e.data.options.monospace_font ? ' monospace-font' : ''),
			'content': $content,
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

		if (e.data.options.label_before.length) {
			height_offset += $('<div>', {class: 'multilineinput-label'})
				.html(e.data.options.label_before)
				.insertBefore($content)
				.height();
		}

		if (e.data.options.label_after.length) {
			height_offset += $('<div>', {class: 'multilineinput-label'})
				.html(e.data.options.label_after)
				.insertAfter($content)
				.height();
		}

		$content
			.append(e.data.options.line_numbers ? $line_numbers : '', $textarea)
			.css('max-height', 'calc(100vh - ' + (height_offset + 2) + 'px)');

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
				placeholder_textarea: '',
				label_before: '',
				label_after: '',
				maxlength: 255,
				rows: 20,
				grow: 'fixed',
				readonly: false,
				disabled: false,
				autofocus: false,
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
				.on('mousedown', this, openModal);

			this.$button = $('<button>', {
				type: 'button',
				title: this.options.hint,
				autofocus: this.options.autofocus || null
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
			this.trigger('disable');

			return this;
		},
		enable: function() {
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
