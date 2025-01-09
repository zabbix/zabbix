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


(function($) {
	'use strict';

	function updateProperties(obj, $elm) {
		obj.$hidden.prop('disabled', obj.options.disabled ? true : null);
		obj.$input
			.prop('disabled', obj.options.disabled ? true : null)
			.prop('readonly', obj.options.disabled ? null : true)
			.prop('title', obj.options.disabled ? '' : obj.options.hint);
		obj.$button
			.prop('disabled', obj.options.disabled ? true : null)
			.prop('title', obj.options.disabled ? '' : obj.options.hint);

		$elm
			.toggleClass('multilineinput-readonly', (obj.options.readonly && !obj.options.disabled))
			.toggleClass('multilineinput-disabled', obj.options.disabled);
	}

	function setDisabled(e) {
		if ($(this)[0] !== e.target) {
			return;
		}

		var obj = e.data,
			$elm = $(this);

		obj.options.disabled = (e.type === 'disable');
		updateProperties(obj, $elm);
	}

	function setReadOnly(e) {
		if ($(this)[0] !== e.target) {
			return;
		}

		var obj = e.data,
			$elm = $(this);

		obj.options.readonly = (e.type === 'readonly');
		updateProperties(obj, $elm);
	}

	function openModal(e) {
		e.preventDefault();

		var obj = e.data;

		if (obj.options.disabled) {
			return;
		}

		function updateCharCount(count) {
			$('span', $footer).text(count);
		}

		function updateLineNumbers(lines_count) {
			var min_rows = 3,
				line_height = 18,
				diff = lines_count - $line_numbers[0].childElementCount,
				li = '';

			switch (obj.options.grow) {
				case 'fixed':
					$content.height(obj.options.rows * line_height + 2);
					break;
				case 'auto':
					var rows = Math.max(min_rows, lines_count);
					rows = obj.options.rows == 0 ? rows : Math.min(rows, obj.options.rows)
					$content.height(rows * line_height + 2);
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
				$line_numbers[0].querySelector('li:last-child').remove();
				diff++;
			}

			$line_numbers.css('top', -Math.floor($textarea.prop('scrollTop') - 1));
			$textarea.css('margin-left', $line_numbers.outerWidth());
		}

		var height_offset = 220,
			$content = $('<div>', {class: 'multilineinput-container'}),
			monospace_font = obj.options.monospace_font ? ' monospace-font' : '',
			$textarea = $('<textarea>', {
				class: 'multilineinput-textarea' + monospace_font,
				text: obj.$hidden.val(),
				readonly: obj.options.readonly ? true : null,
				placeholder: obj.options.placeholder_textarea,
				spellcheck: false
			}).attr('wrap', 'off'),
			$line_numbers = $('<ul>', {class: 'multilineinput-line-numbers' + monospace_font}).append('<li>'),
			$footer = $('<div>', {class: 'multilineinput-char-count'});

		if ('maxlength' in obj.options) {
			$textarea.attr('maxlength', obj.options.maxlength);
			$footer.html(sprintf(t('S_N_CHAR_COUNT_REMAINING'), '<span>0</span>'));
		}
		else {
			$footer.html(sprintf(t('S_N_CHAR_COUNT'), '<span>0</span>'));
		}

		overlayDialogue({
			'title': obj.options.title,
			'class': 'modal-popup multilineinput-modal',
			'content': $content,
			'footer': $footer,
			'buttons': [
				{
					title: t('S_APPLY'),
					action: function() {
						obj.$node.multilineInput('value', $textarea.val());
					},
					enabled: !obj.options.readonly
				},
				{
					title: t('S_CANCEL'),
					class: 'btn-alt',
					action: function() {}
				}
			]
		}, obj.$button);

		if (obj.options.label_before.length) {
			height_offset += $('<div>', {class: 'multilineinput-label' + monospace_font})
				.html(obj.options.label_before)
				.insertBefore($content)
				.height();
		}

		if (obj.options.label_after.length) {
			height_offset += $('<div>', {class: 'multilineinput-label' + monospace_font})
				.html(obj.options.label_after)
				.insertAfter($content)
				.height();
		}

		$content
			.append(obj.options.line_numbers ? $line_numbers : '', $textarea)
			.css('max-height', 'calc(100vh - ' + (height_offset + 2) + 'px)');

		$textarea[0].setSelectionRange(0, 0);

		if (obj.options.use_tab) {
			$textarea[0].addEventListener('keydown', e => {
				if (e.key === 'Tab' && !e.shiftKey) {
					e.preventDefault();

					const input = e.target;
					const value = input.value;

					if (value.length < obj.options.maxlength) {
						const startSelection = input.selectionStart;

						input.value = value.substring(0, startSelection) + "\t" + value.substring(input.selectionEnd);

						input.selectionStart = input.selectionEnd;
						input.selectionEnd = startSelection + 1;
					}
				}
			});
		}

		$textarea
			.on('change contextmenu keydown keyup paste scroll', function() {
				var value = $(this).val();

				updateCharCount(('maxlength' in obj.options) ? obj.options.maxlength - value.length : value.length);

				if (obj.options.line_numbers) {
					updateLineNumbers(value.split("\n").length);
				}
			})
			.trigger('change')
			.focus();
	}

	var methods = {
		init: function(options) {
			return this.each(function() {
				var $this = $(this),
					obj = {
						$node: $this,
						options: $.extend({
							title: '',
							hint: t('S_CLICK_TO_VIEW_OR_EDIT'),
							value: '',
							placeholder: '',
							placeholder_textarea: '',
							label_before: '',
							label_after: '',
							rows: 20,
							grow: 'fixed',
							readonly: false,
							disabled: false,
							autofocus: false,
							line_numbers: true,
							monospace_font: true,
							use_tab: true
						}, options)
					};

				obj.$hidden = $('<input>', {
					type: 'hidden',
					name: $this.data('name')
				});

				obj.$input = $('<input>', {
					type: 'text',
					placeholder: obj.options.placeholder,
					title: obj.options.hint,
					tabindex: -1
				})
					.toggleClass('monospace-font', obj.options.monospace_font)
					.prop('readonly', obj.options.disabled ? null : true)
					.on('mousedown', obj, openModal);

				obj.$button = $('<button>', {
					class: ZBX_ICON_PENCIL,
					type: 'button',
					title: obj.options.hint,
					autofocus: obj.options.autofocus || null
				}).on('click', obj, openModal);

				$this
					.data('multilineInput', obj)
					.append(obj.$hidden, obj.$input, obj.$button)
					.on('disable enable', obj, setDisabled)
					.on('readonly readwrite', obj, setReadOnly)
					.trigger(obj.options.disabled ? 'disable' : 'enable');

				methods.value.call($this, obj.options.value);
			});
		},
		/**
		 * @param {string|undefined}  value  Set field value. Without parameter - get field value
		 *
		 * @returns {string|object}
		 */
		value: function(value) {
			if (typeof value === 'undefined') {
				return this.data('multilineInput').$hidden.val();
			}
			else {
				return this.each(function() {
					var $this = $(this),
						obj = $this.data('multilineInput'),
						value_lines = $.trim(value).split("\n");

					obj.$hidden.val(value);
					obj.$input.val(value_lines.length > 1
						? value_lines[0] + String.fromCharCode(8230) // U+2026 Horizontal ellipsis character.
						: value_lines[0]
					);
					$this.trigger('change');
				});
			}
		},
		disable: function() {
			return this.each(function() {
				$(this).trigger('disable');
			});
		},
		enable: function() {
			return this.each(function() {
				$(this).trigger('enable');
			});
		},
		setReadOnly: function() {
			return this.each(function() {
				$(this).trigger('readonly');
			});
		},
		unsetReadOnly: function() {
			return this.each(function() {
				$(this).trigger('readwrite');
			});
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
