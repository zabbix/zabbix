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

	if ($('#search').length) {
		createSuggest('search');
	}

	if (IE || KQ) {
		setTimeout(function () { $('[autofocus]').focus(); }, 10);
	}

	/**
	 * Build menu popup for given elements.
	 */
	$(document).on('click', '[data-menupopup]', function(event) {
		var obj = $(this),
			data = obj.data('menupopup'),
			labels = {
				'Cancel': t('Cancel'),
				'Execute': t('Execute'),
				'Execution confirmation': t('Execution confirmation')
			};

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

		obj.menuPopup(data, labels, event);

		return false;
	});
});
