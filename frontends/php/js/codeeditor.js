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

	function updateLineNumbers($textarea) {
		var $ln = $textarea.siblings('.line-numbers'),
			ln_count = $textarea.val().split("\n").length,
			li_count = $ln.find('li').length,
			diff = ln_count - li_count;

		if (diff > 0) {
			while (diff > 0) {
				$ln.append($('<li>'));
				diff--;
			}
		}

		while (diff < 0) {
			$ln.find('li:eq(0)').remove();
			diff++;
		}
	}

	var methods = {
		init: function(options) {
			return this.each(function() {
				var $input = $(this)
						.removeAttr('id')
						.attr('tabindex', -1)
						.prop('readonly', true),
					editable = $input.hasClass('editable'),
					maxlength = $input.attr('maxlength'),
					$hidden = $input.siblings('input[type=hidden]'),
					$button = $('<button>')
						.text(t('S_OPEN'))
						.appendTo($input.parent());

				$input.add($button).on('click', function(e) {
					e.preventDefault();

					var $code_editor = $('<div>').addClass('code-editor'),
						$ln = $('<ul>')
							.addClass('line-numbers')
							.append('<li>')
							.appendTo($code_editor),
						$textarea = $('<textarea>', {
							class: 'code-editor-textarea',
							text: $hidden.val(),
							maxlength: maxlength,
							readonly: !editable
						}).appendTo($code_editor);

					overlayDialogue({
						'title': t('S_JAVASCRIPT'),
						'class': 'modal-code-editor',
						'content': $code_editor,
						'buttons': [
							{
								title: t('S_SAVE'),
								enabled: editable,
								action: function() {
									var new_value = $.trim($textarea.val());

									$input.val(new_value.split("\n")[0]);
									$hidden.val(new_value);
								}
							},
							{
								title: t('S_CANCEL'),
								class: 'btn-alt',
								action: function() {}
							}
						]
					}, $button);

					var $counter = $('<div>')
						.addClass('symbols-remaining')
						.html(sprintf(t('S_N_SYMBOLS_REMAINING'),
							'<span>' + (maxlength - $textarea.val().length) + '</span>'
						));
					$('.overlay-dialogue-footer').prepend($counter);

					$textarea[0].setSelectionRange(0, 0);

					$textarea
						.focus()
						.on('change contextmenu keydown keyup paste scroll', function() {
							$counter.find('span').html(maxlength - $(this).val().length);
							updateLineNumbers($(this));
							$ln.prop('scrollTop', $textarea.prop('scrollTop'));
						})
						.trigger('change');
				});
			});
		},
		destroy: function() {
			return this.each(function() {
				var $input = $(this),
					$hidden = $input.siblings('input[type=hidden]');

				$input
					.off()
					.removeAttr('tabindex readonly')
					.attr('id', $hidden.attr('id'))
					.siblings('button').remove();
				$hidden.remove();
			});
		}
	};

	$.fn.codeEditor = function(method) {
		if (methods[method]) {
			return methods[method].apply(this, Array.prototype.slice.call(arguments, 1));
		}
		else if (typeof method === 'object' || !method) {
			return methods.init.apply(this, arguments);
		}
		else {
			$.error('Invalid method "' +  method + '".');
		}
	};
})(jQuery);
