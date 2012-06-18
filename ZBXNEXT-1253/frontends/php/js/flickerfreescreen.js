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


var flickerfreeScreen = {

	screens: [],

	refresh: function(screenitemid) {
		var screen = this.screens[screenitemid];
		if (empty(screen.resourcetype)) {
			return;
		}

		var url = new Curl('jsrpc.php');
		url.setArgument('type', 9); // PAGE_TYPE_TEXT
		url.setArgument('method', 'flickerfreeScreen.get');
		url.setArgument('mode', screen.mode);
		url.setArgument('screenitemid', screenitemid);

		// SCREEN_RESOURCE_GRAPH && SCREEN_RESOURCE_SIMPLE_GRAPH
		if (screen.resourcetype == 0 || screen.resourcetype == 1) {
			var graphId = 'graph_' + screenitemid + '_' + screen.screenid;

			url.setArgument('mode', 3); // SCREEN_MODE_JS
			url.setArgument('period', !empty(screen.period) ? screen.period : timeControl.getPeriod(graphId));
			url.setArgument('stime', !empty(screen.stime) ? screen.stime : timeControl.getSTime(graphId));

			jQuery.getScript(url.getUrl(), function(data, textStatus, jqxhr) {
				timeControl.refreshObject(graphId);
			});
		}
		// SCREEN_RESOURCE_MAP
		else if (screen.resourcetype == 2) {
			jQuery('<div>').load(url.getUrl(), function() {
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
		// SCREEN_RESOURCE_URL
		else if (screen.resourcetype == 7 || screen.resourcetype == 11) {
			// don't refresh screen
		}
		else {
			jQuery('#flickerfreescreen_' + screenitemid).load(url.getUrl());
		}
	},

	refreshAll: function(period, stime) {
		for (var screenitemid in this.screens) {
			if (empty(this.screens[screenitemid])) {
				continue;
			}

			this.screens[screenitemid].period = period;
			this.screens[screenitemid].stime = stime;

			this.refresh(screenitemid);
		}
	},

	add: function(screenitemid, screenid, resourcetype, mode, refreshInterval) {
		timeControl.refreshPage = false;
		this.screens[screenitemid] = {
			'screenid': screenid,
			'resourcetype': resourcetype,
			'mode': mode,
			'period': null,
			'stime': null
		};
		this.refresh(screenitemid);

		window.setInterval(function() { flickerfreeScreen.refresh(screenitemid); }, refreshInterval * 1000);
	}
};
