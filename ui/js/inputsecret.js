/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
 * CInputSecret element control.
 */
(function($) {
	'use strict';

	function enableHandle() {
		var $btn_change = $(this),
			$input = $btn_change.siblings('input[type=password]'),
			$btn_undo = $btn_change
				.closest('.macro-input-group')
				.find('.btn-undo');

		$input
			.prop('disabled', false)
			.val('')
			.focus();

		$btn_change.prop('disabled', true);
		$btn_undo.show();
	}

	var methods = {
		init() {
			return this.each(function() {
				$(this).data('is-activated', false);

				$('.btn-change', $(this))
					.off('click', enableHandle)
					.on('click', enableHandle);
			});
		}
	};

	/**
	 * Input secret helper.
	 */
	$.fn.inputSecret = function(method) {
		if (methods[method]) {
			return methods[method].apply(this, Array.prototype.slice.call(arguments, 1));
		}

		return methods.init.apply(this, arguments);
	};
})(jQuery);
