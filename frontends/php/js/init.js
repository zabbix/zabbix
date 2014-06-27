/*
 ** Zabbix
 ** Copyright (C) 2001-2014 Zabbix SIA
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

	if ($('#search').length) {
		createSuggest('search');
	}

	if (IE || KQ) {
		setTimeout(function () { $('[autofocus]').focus(); }, 10);
	}

	/**
	 * Change combobox color according selected option.
	 */
	$('.input.select').each(function() {
		var comboBox = $(this),
			changeClass = function(obj) {
				if (obj.find('option.not-monitored:selected').length > 0) {
					obj.addClass('not-monitored');
				}
				else {
					obj.removeClass('not-monitored');
				}
			};

		comboBox.change(function() {
			changeClass($(this));
		});

		changeClass(comboBox);
	});

	/**
	 * Build menu popup for given elements.
	 */
	$(document).on('click', '[data-menu-popup]', function(event) {
		var obj = $(this),
			data = obj.data('menu-popup');

		switch (data.type) {
			case 'host':
				data = getMenuPopupHost(data);
				break;

			case 'trigger':
				data = getMenuPopupTrigger(data);
				break;

			case 'history':
				data = getMenuPopupHistory(data);
				break;

			case 'map':
				data = getMenuPopupMap(data);
				break;
		}

		obj.menuPopup(data, event);

		return false;
	});

	$('.print-link').click(function() {
		printLess(true);

		return false;
	});

	/*
	 * add.popup event
	 */
	$(document).on('add.popup', function(e, data) {
		// multiselect check
		if ($('#' + data.parentId).hasClass('multiselect')) {
			for (var i = 0; i < data.values.length; i++) {
				if (typeof data.values[i].id !== 'undefined') {
					var item = {
						'id': data.values[i].id,
						'name': data.values[i].name,
						'prefix': data.values[i].prefix
					};
					jQuery('#' + data.parentId).multiSelect.addData(item, data.parentId);
				}
			}
		}
		else {
			// execute function if they exist
			if (typeof addPopupValues !== 'undefined') {
				addPopupValues(data);
			}
		}
	});
});
