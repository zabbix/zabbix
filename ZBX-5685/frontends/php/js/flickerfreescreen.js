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


jQuery(function($) {
	'use strict';

	window.flickerfreeScreen = {

		screens: [],

		refresh: function(id, isSelfRefresh) {
			var screen = this.screens[id];
			if (empty(screen.resourcetype)) {
				return;
			}

			var ajaxUrl = new Curl('jsrpc.php');
			ajaxUrl.setArgument('type', 9); // PAGE_TYPE_TEXT
			ajaxUrl.setArgument('method', 'screen.get');
			ajaxUrl.setArgument('mode', screen.mode);
			ajaxUrl.setArgument('flickerfreeScreenId', id);
			ajaxUrl.setArgument('pageFile', screen.pageFile);
			ajaxUrl.setArgument('screenid', screen.screenid);
			ajaxUrl.setArgument('screenitemid', screen.screenitemid);
			ajaxUrl.setArgument('groupid', screen.groupid);
			ajaxUrl.setArgument('hostid', screen.hostid);
			ajaxUrl.setArgument('profileIdx', !empty(screen.profileIdx) ? screen.profileIdx : null);
			ajaxUrl.setArgument('profileIdx2', !empty(screen.profileIdx2) ? screen.profileIdx2 : null);
			ajaxUrl.setArgument('updateProfile', !empty(screen.updateProfile) ? +screen.updateProfile : null);
			ajaxUrl.setArgument('period', !empty(screen.timeline.period) ? screen.timeline.period : null);
			ajaxUrl.setArgument('stime', this.getCalculatedSTime(screen));

			// SCREEN_RESOURCE_GRAPH
			// SCREEN_RESOURCE_SIMPLE_GRAPH
			if (screen.resourcetype == 0 || screen.resourcetype == 1) {
				if (this.isRefreshAllowed(screen, isSelfRefresh)) {
					this.refreshImg(id, function() {
						$('#flickerfreescreen_' + id).find('a').each(function() {
							var chartUrl = new Curl($(this).attr('href'));
							chartUrl.setArgument('period', !empty(screen.timeline.period) ? screen.timeline.period : null);
							chartUrl.setArgument('stime', window.flickerfreeScreen.getCalculatedSTime(screen));
							$(this).attr('href', chartUrl.getUrl());
						});
					});
				}
			}

			// SCREEN_RESOURCE_MAP
			else if (screen.resourcetype == 2) {
				this.refreshImg(id);
			}

			// SCREEN_RESOURCE_CHART
			else if (screen.resourcetype == 18) {
				if (this.isRefreshAllowed(screen, isSelfRefresh)) {
					this.refreshImg(id);
				}
			}

			// SCREEN_RESOURCE_HISTORY
			else if (screen.resourcetype == 17) {
				if (this.isRefreshAllowed(screen, isSelfRefresh)) {
					if (screen.data.action == 'showgraph') {
						this.refreshImg(id);
					}
					else {
						ajaxUrl.setArgument('resourcetype', !empty(screen.resourcetype) ? screen.resourcetype : null);
						ajaxUrl.setArgument('itemid', !empty(screen.data.itemid) ? screen.data.itemid : null);
						ajaxUrl.setArgument('action', !empty(screen.data.action) ? screen.data.action : null);
						ajaxUrl.setArgument('filter', !empty(screen.data.filter) ? screen.data.filter : null);
						ajaxUrl.setArgument('filter_task', !empty(screen.data.filterTask) ? screen.data.filterTask : null);
						ajaxUrl.setArgument('mark_color', !empty(screen.data.markColor) ? screen.data.markColor : null);

						this.refreshHtml(id, ajaxUrl);
					}
				}
			}

			// SCREEN_RESOURCE_CLOCK
			else if (screen.resourcetype == 7) {
				// don't refresh anything
			}

			// SCREEN_RESOURCE_SCREEN
			else if (screen.resourcetype == 8) {
				this.refreshProfile(id, ajaxUrl);
			}

			// SCREEN_RESOURCE_PLAIN_TEXT
			else if (screen.resourcetype == 3) {
				if (this.isRefreshAllowed(screen, isSelfRefresh)) {
					this.refreshHtml(id, ajaxUrl);
				}
			}

			// others
			else {
				this.refreshHtml(id, ajaxUrl);
			}

			// set next refresh execution time
			if (screen.isFlickerfree && screen.refreshInterval > 0) {
				clearTimeout(screen.timeout);
				screen.timeout = window.setTimeout(function() { window.flickerfreeScreen.refresh(id, true); }, screen.refreshInterval);

				// refresh time
				clearTimeout(timeControl.timeRefreshTimeout);
				timeControl.refreshTime();
			}
		},

		refreshAll: function(period, stime, isNow) {
			for (var id in this.screens) {
				if (empty(this.screens[id]) || empty(this.screens[id].resourcetype)) {
					continue;
				}

				this.screens[id].timeline.period = period;
				this.screens[id].timeline.stime = stime;
				this.screens[id].timeline.isNow = isNow;

				// restart refresh execution starting from now
				clearTimeout(this.screens[id].timeout);
				this.refresh(id, false);
			}
		},

		refreshHtml: function(id, ajaxUrl) {
			var screen = this.screens[id];

			if (screen.isRefreshing) {
				screen.isReRefreshRequire = true;
			}
			else {
				screen.isRefreshing = true;

				var ajaxRequest = $.ajax({
					url: ajaxUrl.getUrl(),
					type: 'post',
					data: {},
					dataType: 'html',
					success: function(data) {
						$('#flickerfreescreen_' + id).html(data);

						screen.isRefreshing = false;
					},
					error: function(jqXHR, textStatus, errorThrown) {
						screen.isRefreshing = false;
					}
				});

				$.when(ajaxRequest).always(function() {
					if (screen.isReRefreshRequire) {
						screen.isReRefreshRequire = false;
						window.flickerfreeScreen.refresh(id, false);
					}
				});
			}
		},

		refreshImg: function(id, successAction) {
			var screen = this.screens[id];

			if (screen.isRefreshing) {
				screen.isReRefreshRequire = true;
			}
			else {
				screen.isRefreshing = true;

				$('#flickerfreescreen_' + id).find('img').each(function() {
					var workImg = $(this);
					var chartUrl = new Curl(workImg.attr('src'));
					chartUrl.setArgument('screenid', !empty(screen.screenid) ? screen.screenid : null);
					chartUrl.setArgument('updateProfile', (typeof(screen.updateProfile) != 'undefined') ? + screen.updateProfile : null);
					chartUrl.setArgument('period', !empty(screen.timeline.period) ? screen.timeline.period : null);
					chartUrl.setArgument('stime', window.flickerfreeScreen.getCalculatedSTime(screen));
					chartUrl.setArgument('curtime', new CDate().getTime());

					// img
					$('<img />', {
						id: workImg.attr('id') + '_tmp',
						'class': workImg.attr('class'),
						border: workImg.attr('border'),
						usemap: workImg.attr('usemap'),
						alt: workImg.attr('alt'),
						name: workImg.attr('name')
					})
					.attr('src', chartUrl.getUrl())
					.load(function() {
						var elem = $(this);
						elem.attr('id', elem.attr('id').substring(0, elem.attr('id').indexOf('_tmp')));

						workImg.replaceWith(elem);

						if (typeof(successAction) !== 'undefined') {
							successAction();
						}

						// rebuild listener
						if (!empty(ZBX_SBOX[id])) {
							ZBX_SBOX[id].addListeners();
						}

						screen.isRefreshing = false;

						if (screen.isReRefreshRequire) {
							screen.isReRefreshRequire = false;
							window.flickerfreeScreen.refresh(id, false);
						}
					});
				});
			}
		},

		refreshProfile: function(id, ajaxUrl) {
			var screen = this.screens[id];

			if (screen.isRefreshing) {
				screen.isReRefreshRequire = true;
			}
			else {
				screen.isRefreshing = true;
				var ajaxRequest = $.ajax({
					url: ajaxUrl.getUrl(),
					type: 'post',
					data: {},
					success: function(data) {
						screen.isRefreshing = false;
					},
					error: function(jqXHR, textStatus, errorThrown) {
						screen.isRefreshing = false;
					}
				});

				$.when(ajaxRequest).always(function() {
					if (screen.isReRefreshRequire) {
						screen.isReRefreshRequire = false;
						window.flickerfreeScreen.refresh(id, false);
					}
				});
			}
		},

		isRefreshAllowed: function (screen, isSelfRefresh) {
			if (isSelfRefresh == false || empty(timeControl.timeline)) {
				return true;
			}

			var isNow = timeControl.timeline.isNow();
			if (!is_null(isNow)) {
				return isNow;
			}
			else if (screen.timeline.isNow || screen.timeline.isNow == 1) {
				return true;
			}

			return false;
		},

		getCalculatedSTime: function(screen) {
			if (!empty(timeControl.timeline) && screen.timeline.period >= timeControl.timeline.maxperiod) {
				return new CDate(timeControl.timeline.starttime() * 1000).getZBXDate();
			}

			return (screen.timeline.isNow || screen.timeline.isNow == 1)
				? new CDate((new CDate().setZBXDate(screen.timeline.stime) / 1000 + 31536000) * 1000).getZBXDate() // 31536000 = 86400 * 365 = 1 year
				: screen.timeline.stime;
		},

		submitForm: function(formName) {
			var period, stime;

			for (var id in this.screens) {
				if (!empty(this.screens[id])) {
					period = this.screens[id].timeline.period;
					stime = this.getCalculatedSTime(this.screens[id]);
					break;
				}
			}

			$('form[name=' + formName + ']').append('<input type="hidden" name="period" value="' + period + '" />');
			$('form[name=' + formName + ']').append('<input type="hidden" name="stime" value="' + stime + '" />');
			$('form[name=' + formName + ']').submit();
		},

		add: function(screen) {
			timeControl.refreshPage = false;

			this.screens[screen.id] = screen;
			this.screens[screen.id].refreshInterval = (screen.refreshInterval > 0) ? screen.refreshInterval * 1000 : 0;
			this.screens[screen.id].isRefreshing = false;
			this.screens[screen.id].isReRefreshRequire = false;

			if (screen.isFlickerfree && screen.refreshInterval > 0) {
				this.screens[screen.id].timeout = window.setTimeout(function() { window.flickerfreeScreen.refresh(screen.id, true); }, this.screens[screen.id].refreshInterval);
			}
		}
	};
});
