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
 * @event resize - Event fired on textarea size change.
 */
(function($) {
	'use strict';

	function update(e) {
		if (e.which === 13) {
			this.closest('form').dispatchEvent(new SubmitEvent('submit', {
				bubbles: true,
				cancelable: true,
				submitter: this
			}));

			return false;
		}

		/**
		 * Simulate input behaviour by replacing newlines with space character.
		 * NB! WebKit based browsers add a newline character to textarea when translating content to the next line.
		 */
		const $textarea = $(this);

		var old_value = $textarea.val(),
			new_value = old_value
				.replace(/\r?\n+$/g, '')
				.replace(/\r?\n/g, ' ');

		const scrollable = getScrollableParent($textarea[0]);
		const scrollable_pos = scrollable !== null ? scrollable.scrollTop : 0;

		if (old_value !== new_value) {
			var pos = $textarea[0].selectionStart;

			$textarea.val(new_value);
			$textarea[0].setSelectionRange(pos, pos);
		}

		updateHeight($textarea);

		// Fire event.
		$textarea.trigger('resize');

		if (scrollable !== null) {
			scrollable.scrollTop = scrollable_pos;
		}
	}

	function getScrollableParent(element) {
		while (element !== null) {
			if (['auto', 'scroll'].includes(getComputedStyle(element).overflowY)) {
				return element;
			}

			element = element.parentElement;
		}

		return null;
	}

	// Update textarea height.
	function updateHeight($textarea) {
		if ($textarea.val() === '' && $textarea.attr('placeholder') !== '') {
			// Calculation of scrollHeight property in firefox do not count placeholder dimension when value is empty.
			const $clone = $textarea.clone()
				.css('position', 'absolute')
				.insertAfter($textarea)
				.height(0)
				.val($textarea.attr('placeholder'));

			$textarea.innerHeight($clone[0].scrollHeight);
			$clone.remove();

			return;
		}

		$textarea
			.height(0)
			.innerHeight($textarea[0].scrollHeight);
	}

	var methods = {
		init: function() {
			return this.each(function() {
				var $textarea = $(this);

				$textarea
					.off('input keydown paste', update)
					.on('input keydown paste', update)
					.trigger('input');

				const intersection = new IntersectionObserver(entries => {
					if (entries[0].isIntersecting) {
						$textarea.trigger('input');
						intersection.disconnect();
					}
				});

				intersection.observe(this);
			});
		},
		updateHeight: function() {
			return this.each(function() {
				const $textarea = $(this);

				updateHeight($textarea);
			});
		},
		clean: function() {
			return this.each(function() {
				var $textarea = $(this);

				$textarea
					.val('')
					.css('height', '');
			});
		}
	};

	/**
	 * Flexible textarea helper.
	 */
	$.fn.textareaFlexible = function(method) {
		if (methods[method]) {
			return methods[method].apply(this, Array.prototype.slice.call(arguments, 1));
		}

		return methods.init.apply(this, arguments);
	};
})(jQuery);
