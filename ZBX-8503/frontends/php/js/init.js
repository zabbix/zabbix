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


jQuery(function() {

	cookie.init();

	// search
	if (jQuery('#search').length) {
		createSuggest('search');
	}

	/**
	 * Handles host pop up menus.
	 */
	jQuery(document).on('click', '.menu-host', function(event) {
		var menuData = jQuery(this).data('menu');
		var menu = [];

		// add scripts
		if (menuData.scripts.length) {
			menu.push(createMenuHeader(t('Scripts')));
			jQuery.each(menuData.scripts, function(i, script) {
				menu.push(createMenuItem(script.name, function () {
					executeScript(menuData.hostid, script.scriptid, script.confirmation);
					return false;
				}));
			});
		}

		// add go to links
		menu.push(createMenuHeader(t('Go to')));
		menu.push(createMenuItem(t('Latest data'), 'latest.php?hostid=' + menuData.hostid));
		if (menuData.hasInventory) {
			menu.push(createMenuItem(t('Host inventories'), 'hostinventories.php?hostid=' + menuData.hostid));
		}
		if (menuData.hasScreens) {
			menu.push(createMenuItem(t('Host screens'), 'host_screen.php?hostid=' + menuData.hostid));
		}

		// render the menu
		show_popup_menu(event, menu, 180);

		return false;
	});
});
