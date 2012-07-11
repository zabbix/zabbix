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

	refresh: function(id, isSelfRefresh) {
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
		url.setArgument('sort', !empty(screen.sort) ? screen.sort : null);
		url.setArgument('sortorder', !empty(screen.sortorder) ? screen.sortorder : null);

		// SCREEN_RESOURCE_GRAPH
		// SCREEN_RESOURCE_SIMPLE_GRAPH
		if (screen.resourcetype == 0 || screen.resourcetype == 1) {
			url.setArgument('mode', 3); // SCREEN_MODE_JS
			url.setArgument('hostid', screen.hostid);

			jQuery.getScript(url.getUrl(), function(data, textStatus, jqxhr) {
				timeControl.refreshObject(id);
			});
		}

		// SCREEN_RESOURCE_MAP
		else if (screen.resourcetype == 2) {
			jQuery('<div>').load(url.getUrl(), function() {
				jQuery(this).find('img').each(function() {
					var id = '#' + jQuery(this).attr('id');

					jQuery('<img />', {
						id: jQuery(this).attr('id') + '_tmp',
						calss: jQuery(id).attr('class'),
						border: jQuery(id).attr('border'),
						usemap: jQuery(id).attr('usemap'),
						alt: jQuery(id).attr('alt'),
						name: jQuery(id).attr('name')
					}).attr('src', jQuery(this).attr('src')).load(function() {
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

				jQuery.getScript(url.getUrl(), function(data, textStatus, jqxhr) {
					timeControl.refreshObject(id);
				});
			}
			else {
				jQuery('#flickerfreescreen_' + id).load(url.getUrl());
			}
		}

		else {
			jQuery('#flickerfreescreen_' + id).load(url.getUrl());
		}

		if (isSelfRefresh && screen.refreshInterval > 0) {
			window.setTimeout(function() { flickerfreeScreen.refresh(id, true); }, screen.refreshInterval);
		}
	},

	refreshAll: function(period, stime) {
		for (var id in this.screens) {
			if (empty(this.screens[id])) {
				continue;
			}

			this.screens[id].period = period;
			this.screens[id].stime = stime;

			this.refresh(id, false);
		}
	},

	refreshWithSorting: function(id, sort, sortorder) {
		this.screens[id].sort = sort;
		this.screens[id].sortorder = sortorder;

		this.refresh(id, false);
	},

	add: function(screen) {
		timeControl.refreshPage = false;

		this.screens[screen.id] = {
			'screenitemid': screen.screenitemid,
			'screenid': screen.screenid,
			'hostid': screen.hostid,
			'period': screen.period,
			'stime': screen.stime,
			'sort': screen.sort,
			'sortorder': screen.sortorder,
			'mode': screen.mode,
			'resourcetype': screen.resourcetype,
			'profileIdx': screen.profileIdx,
			'data': screen.data
		};

		if (screen.refreshInterval > 0) {
			this.screens[screen.id].refreshInterval = screen.refreshInterval * 1000;
			window.setTimeout(function() { flickerfreeScreen.refresh(screen.id, true); }, this.screens[screen.id].refreshInterval);
		}
	},
};
