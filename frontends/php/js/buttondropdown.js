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
 * CButtonDropdown control.
 */
(function($) {
	'use strict';

	var methods = {
		/**
		 * Change hidden input value.
		 *
		 * @param {element} elem
		 * @param {object}  value
		 */
		change(elem, value) {
			$(elem)
				.removeClass()
				.addClass(['btn-alt', 'btn-dropdown-toggle', value.class].join(' '));

			$('input[type=hidden]', $(elem).parent())
				.val(value.value)
				.trigger('change');
		}
	};

	/**
	 * Button dropdown helper.
	 */
	$.fn.buttonDropdown = function (method) {
		if (methods[method]) {
			return methods[method].apply(this, Array.prototype.slice.call(arguments, 1));
		}

		return this;
	};
})(jQuery);
