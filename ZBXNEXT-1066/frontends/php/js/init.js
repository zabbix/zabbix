/*
 ** Zabbix
 ** Copyright (C) 2000-2011 Zabbix SIA
 **
 ** This program is free software; you can redistribute it and/or modify
 ** it under the terms of the GNU General Public License as published by
 ** the Free Software Foundation; either version 2 of the License, or
 ** (at your option) any later version.
 **
 ** This program is distributed in the hope that it will be useful,
 ** but WITHOUT ANY WARRANTY; without even the implied warranty of
 ** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 ** GNU General Public License for more details.
 **
 ** You should have received a copy of the GNU General Public License
 ** along with this program; if not, write to the Free Software
 ** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 **/

jQuery(function() {

	/**
	 * Handles host pop up menus.
	 */
	jQuery(document).on('click', '.menu-host', function(event) {
		function createMenuHeader(label) {
			return [label, null, null, { outer: 'pum_oheader', inner: 'pum_iheader' }];
		}

		function createMenuItem(label, action) {
			return [label, action, null, { outer: 'pum_o_item', inner: 'pum_i_item' }];
		}

		var menuData = jQuery(this).data('menu');
		var menu = [];

		// add scripts
		if (menuData.scripts.length) {
			menu.push(createMenuHeader(_('Scripts')));
			jQuery.each(menuData.scripts, function(i, script) {
				var action = 'javascript: executeScript(' + menuData.hostid + ', ' + script.scriptid
					+ ', "' + script.confirmation +'")';
				menu.push(createMenuItem(script.name, action));
			});
		}

		// add go to links
		menu.push(createMenuHeader(_('Go to')));
		menu.push(createMenuItem(_('Latest data'), 'javascript: redirect("latest.php?hostid=' + menuData.hostid + '")'));
		if (menuData.hasInventory) {
			menu.push(createMenuItem(_('Host inventories'), 'javascript: redirect("hostinventories.php?hostid='
				+ menuData.hostid + '")'));
		}
		if (menuData.hasScreens) {
			menu.push(createMenuItem(_('Host screens'), 'javascript: redirect("host_screen.php?hostid='
				+ menuData.hostid + '")'));
		}

		// render the menu
		show_popup_menu(event, menu, 180);

		return false;
	});
});
