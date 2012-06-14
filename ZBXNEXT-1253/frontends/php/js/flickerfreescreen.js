/*
 ** Zabbix
 ** Copyright (C) 2000-2012 Zabbix SIA
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
		// SCREEN_RESOURCE_GRAPH && SCREEN_RESOURCE_SIMPLE_GRAPH
		if (resourcetype == 0 || resourcetype == 1) {
			jQuery.getScript('jsrpc.php?type=3&method=flickerfreeScreen.get&mode=3&screenitemid=' + screenitemid, function(data, textStatus, jqxhr) {
				timeControl.refreshObject('graph_' + screenitemid);
			});
		}
		// SCREEN_RESOURCE_MAP
		else if (resourcetype == 2) {
			jQuery('<div>').load('jsrpc.php?type=9&method=flickerfreeScreen.get&screenitemid=' + screenitemid + '&mode=' + mode, function() {
				jQuery(this).find('img').each(function() {
					jQuery('<img />', {id: jQuery(this).attr('id') + '_tmp'}).attr('src', jQuery(this).attr('src')).load(function() {
						var id = jQuery(this).attr('id').substring(0, jQuery(this).attr('id').indexOf('_tmp'));

						jQuery(this).attr('id', id);
						jQuery('#' + id).replaceWith(jQuery(this));
					});
				});
			});
		}
		// SCREEN_RESOURCE_CLOCK
		else if (resourcetype == 7) {
			// don't refresh screen
		}
		else {
			jQuery('#flickerfreescreen_' + screenitemid).load('jsrpc.php?type=9&method=flickerfreeScreen.get&screenitemid=' + screenitemid + '&mode=' + mode);
		}
	}

	return function(screenitemid, refreshInterval, mode, resourcetype) {
		refresh(screenitemid, mode, resourcetype);

		window.setTimeout(function() { flickerfreeScreen(screenitemid, refreshInterval, mode, resourcetype); }, refreshInterval * 200);
	}
}(jQuery));
