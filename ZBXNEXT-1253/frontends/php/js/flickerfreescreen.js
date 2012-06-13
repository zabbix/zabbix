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


var flickerfreeScreen = (function ($) {
	'use strict';

	function refresh(screenitemid, mode, resourcetype) {
		jQuery('#flickerfreescreen_' + screenitemid)
			.load('jsrpc.php?type=9&method=flickerfreeScreen.get&screenitemid=' + screenitemid + '&mode=' + mode); // 9 - PAGE_TYPE_TEXT (simple text)

		if (resourcetype == 0 || resourcetype == 1) {
			timeControl.refreshObject('graph_' + screenitemid);
		}
	}

	return function(screenitemid, refreshInterval, mode, resourcetype) {
		refresh(screenitemid, mode, resourcetype);

		window.setTimeout(function() { flickerfreeScreen(screenitemid, refreshInterval, mode, resourcetype); }, refreshInterval * 300);
	}
}(jQuery));
