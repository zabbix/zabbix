/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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


jQuery(function($) {

	$.fn.menuPopup = function() {
		var obj = $(this),
			isActive = true;

		if (obj.children().length == 0) {
			return;
		}

		// load
		$('.menu', obj).menu();
		obj.data('isLoaded', true);
		obj.fadeIn(0);

		// close
		obj.mouseenter(function() {
			isActive = true;
		})
		.mouseleave(function() {
			isActive = false;

			setTimeout(function() {
				if (!isActive) {
					obj.fadeOut(50);
				}
			}, 500);
		});

		// execute script
		$('li', obj).each(function() {
			var item = $(this);

			if (!empty(item.data('scriptid'))) {
				item.click(function() {
					obj.fadeOut(50);

					executeScript(item.data('hostid'), item.data('scriptid'), item.data('confirmation'));
				});
			}
		});
	};
});
