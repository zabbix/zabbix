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
			case 'favouriteGraphs':
				data = getMenuPopupFavouriteGraphs(data);
				break;

			case 'favouriteMaps':
				data = getMenuPopupFavouriteMaps(data);
				break;

			case 'favouriteScreens':
				data = getMenuPopupFavouriteScreens(data);
				break;

			case 'history':
				data = getMenuPopupHistory(data);
				break;

			case 'host':
				data = getMenuPopupHost(data);
				break;

			case 'map':
				data = getMenuPopupMap(data);
				break;

			case 'refresh':
				data = getMenuPopupRefresh(data);
				break;

			case 'serviceConfiguration':
				data = getMenuPopupServiceConfiguration(data);
				break;

			case 'trigger':
				data = getMenuPopupTrigger(data);
				break;

			case 'triggerLog':
				data = getMenuPopupTriggerLog(data);
				break;

			case 'triggerMacro':
				data = getMenuPopupTriggerMacro(data);
				break;
		}

		obj.menuPopup(data, event);

		return false;
	});

	$('.print-link').click(function() {
		printLess(true);

		return false;
	});

	// create jquery buttons
	$('input.jqueryinput').button();
	$('div.jqueryinputset').buttonset();

	createPlaceholders();
});
