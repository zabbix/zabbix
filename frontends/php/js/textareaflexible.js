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
			var settings = $.extend({}, options);

			return this.each(function() {
				var $textarea = $(this);

				$textarea
					.on('input keydown paste blur', function(e) {
						if (e.which === 13) {
							var $submit = $(this).closest('form').find(':submit');

							if ($submit.length === 0) {
								$(this).closest('form').submit();
							}
							else if ($submit.length === 1) {
								$submit[0].click();
							}
							else {
								$submit.filter('[name = update]').click();
							}

							return false;
						}

						var old_value = $textarea.val(),
							new_value = old_value.replace(/\r?\n/gi, ''),
							scrollsave = $(window).scrollTop();

						if (old_value.length !== new_value.length) {
							$textarea.val(new_value);
						}

						$textarea.height(0).innerHeight($textarea[0].scrollHeight);
						$(window).scrollTop(scrollsave);
					})
					.trigger('input');
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
