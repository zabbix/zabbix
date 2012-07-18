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

	refresh: function(id) {
		var screen = this.screens[id];
		if (empty(screen.resourcetype)) {
			return;
		}

		var url = new Curl('jsrpc.php');
		url.setArgument('type', 9); // PAGE_TYPE_TEXT
		url.setArgument('method', 'screen.get');
		url.setArgument('mode', screen.mode);
		url.setArgument('flickerfreeScreenId', id);
		url.setArgument('screenitemid', screen.screenitemid);
		url.setArgument('profileIdx', !empty(screen.profileIdx) ? screen.profileIdx : null);
		url.setArgument('period', !empty(screen.period) ? screen.period : null);
		url.setArgument('stime', !empty(screen.stime) ? screen.stime : null);

		// SCREEN_RESOURCE_GRAPH
		// SCREEN_RESOURCE_SIMPLE_GRAPH
		if (screen.resourcetype == 0 || screen.resourcetype == 1) {
			url.setArgument('mode', 3); // SCREEN_MODE_JS
			url.setArgument('hostid', screen.hostid);

			jQuery.getScript(url.getUrl(), function() {
				timeControl.refreshObject(id);
			});
		}

		// SCREEN_RESOURCE_MAP
		else if (screen.resourcetype == 2) {
			jQuery('<div>').load(url.getUrl(), function() {
				jQuery(this).find('img').each(function() {
					var mapId = '#' + jQuery(this).attr('id');

					jQuery('<img />', {
						id: jQuery(this).attr('id') + '_tmp',
						calss: jQuery(mapId).attr('class'),
						border: jQuery(mapId).attr('border'),
						usemap: jQuery(mapId).attr('usemap'),
						alt: jQuery(mapId).attr('alt'),
						name: jQuery(mapId).attr('name')
					}).attr('src', jQuery(this).attr('src')).load(function() {
						var mapId = jQuery(this).attr('id').substring(0, jQuery(this).attr('id').indexOf('_tmp'));

						jQuery(this).attr('id', mapId);
						jQuery('#' + mapId).replaceWith(jQuery(this));
					});
				});
			});
		}

		// SCREEN_RESOURCE_DATA_OVERVIEW
		else if (screen.resourcetype == 10) {
			jQuery('<div>').load(url.getUrl(), function() {
				jQuery(this).find('img').each(function() {
					var workImage = jQuery(this);
					var doId = '#' + jQuery(this).attr('id');

					jQuery('<img />', {
						id: jQuery(this).attr('id') + '_tmp',
						border: jQuery(doId).attr('border'),
						alt: jQuery(doId).attr('alt'),
						name: jQuery(doId).attr('name')
					}).attr('src', jQuery(this).attr('src')).load(function() {
						var doId = jQuery(this).attr('id').substring(0, jQuery(this).attr('id').indexOf('_tmp'));

						jQuery(this).attr('id', doId);
						jQuery(workImage).replaceWith(jQuery(this));
					});
				});

				jQuery('#flickerfreescreen_' + id).replaceWith(jQuery('div', this));
			});
		}

		// SCREEN_RESOURCE_CLOCK
		// SCREEN_RESOURCE_URL
		else if (screen.resourcetype == 7 || screen.resourcetype == 11) {
			// don't refresh screen
		}

		// SCREEN_RESOURCE_HISTORY
		else if (screen.resourcetype == 17) {
			url.setArgument('resourcetype', !empty(screen.resourcetype) ? screen.resourcetype : null);
			url.setArgument('itemid', !empty(screen.data.itemid) ? screen.data.itemid : null);
			url.setArgument('action', !empty(screen.data.action) ? screen.data.action : null);
			url.setArgument('filter', !empty(screen.data.filter) ? screen.data.filter : null);
			url.setArgument('filter_task', !empty(screen.data.filterTask) ? screen.data.filterTask : null);
			url.setArgument('mark_color', !empty(screen.data.markColor) ? screen.data.markColor : null);

			if (screen.data.action == 'showgraph') {
				url.setArgument('mode', 3); // SCREEN_MODE_JS

				jQuery.getScript(url.getUrl(), function() {
					timeControl.refreshObject(id);
				});
			}
			else {
				jQuery('#flickerfreescreen_' + id).load(url.getUrl());
			}
		}

		// SCREEN_RESOURCE_CHART
		else if (screen.resourcetype == 18) {
			url.setArgument('resourcetype', !empty(screen.resourcetype) ? screen.resourcetype : null);
			url.setArgument('graphid', !empty(screen.data.graphid) ? screen.data.graphid : null);
			url.setArgument('mode', 3); // SCREEN_MODE_JS

			jQuery.getScript(url.getUrl(), function() {
				timeControl.refreshObject(id);
			});
		}

		else {
			jQuery('#flickerfreescreen_' + id).load(url.getUrl());
		}

		if (screen.refreshInterval > 0) {
			this.screens[id].timeout = window.setTimeout(function() { flickerfreeScreen.refresh(id); }, screen.refreshInterval);
		}
	},

	refreshAll: function(period, stime) {
		for (var id in this.screens) {
			if (empty(this.screens[id]) || empty(this.screens[id].resourcetype)) {
				continue;
			}

			this.screens[id].period = period;
			this.screens[id].stime = stime;

			// restart global refresh time planing starting from now
			clearTimeout(this.screens[id].timeout);
			this.refresh(id);
		}
	},

	add: function(screen) {
		timeControl.refreshPage = false;

		this.screens[screen.id] = {
			'screenitemid': screen.screenitemid,
			'screenid': screen.screenid,
			'hostid': screen.hostid,
			'period': screen.period,
			'stime': screen.stime,
			'mode': screen.mode,
			'resourcetype': screen.resourcetype,
			'profileIdx': screen.profileIdx,
			'data': screen.data
		};

		if (screen.refreshInterval > 0) {
			this.screens[screen.id].refreshInterval = screen.refreshInterval * 1000;
			this.screens[screen.id].timeout = window.setTimeout(function() { flickerfreeScreen.refresh(screen.id); }, this.screens[screen.id].refreshInterval);
		}
	}
};
