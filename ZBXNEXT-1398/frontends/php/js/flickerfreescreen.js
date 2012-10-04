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

		add: function(screen) {
			// switch off time control refreshing using full page refresh
			timeControl.refreshPage = false;

			// init screen item
			this.screens[screen.id] = screen;
			this.screens[screen.id].interval = (screen.interval > 0) ? screen.interval * 1000 : 0;
			this.screens[screen.id].timestamp = 0;
			this.screens[screen.id].timestampResponsiveness = 0;
			this.screens[screen.id].isRefreshing = false;
			this.screens[screen.id].isReRefreshRequire = false;
			this.screens[screen.id].error = 0;

			// init refresh plan
			if (screen.isFlickerfree && screen.interval > 0) {
				this.screens[screen.id].timeoutHandler = window.setTimeout(function() { window.flickerfreeScreen.refresh(screen.id); }, this.screens[screen.id].interval);
			}
		},

		refresh: function(id, isSelfRefresh) {
			var screen = this.screens[id];

			if (empty(screen.id)) {
				return;
			}

			if (empty(isSelfRefresh)) {
				isSelfRefresh = false;
			}

			var ajaxUrl = new Curl('jsrpc.php');
			ajaxUrl.setArgument('type', 9); // PAGE_TYPE_TEXT
			ajaxUrl.setArgument('method', 'screen.get');
			ajaxUrl.setArgument('mode', screen.mode);
			ajaxUrl.setArgument('timestamp', new CDate().getTime());
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
				if (isSelfRefresh || this.isRefreshAllowed(screen)) {
					this.refreshImg(id, function() {
						$('#flickerfreescreen_' + id).find('a').each(function() {
							var url = new Curl($(this).attr('href'));
							url.setArgument('period', !empty(screen.timeline.period) ? screen.timeline.period : null);
							url.setArgument('stime', window.flickerfreeScreen.getCalculatedSTime(screen));
							$(this).attr('href', url.getUrl());
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
				if (isSelfRefresh || this.isRefreshAllowed(screen)) {
					this.refreshImg(id);
				}
			}

			// SCREEN_RESOURCE_HISTORY
			else if (screen.resourcetype == 17) {
				if (isSelfRefresh || this.isRefreshAllowed(screen)) {
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
				if (isSelfRefresh || this.isRefreshAllowed(screen)) {
					this.refreshHtml(id, ajaxUrl);
				}
			}

			// others
			else {
				this.refreshHtml(id, ajaxUrl);
			}

			// set next refresh execution time
			if (screen.isFlickerfree && screen.interval > 0) {
				clearTimeout(screen.timeoutHandler);
				screen.timeoutHandler = window.setTimeout(function() { window.flickerfreeScreen.refresh(id); }, screen.interval);

				// refresh time control actual time
				clearTimeout(timeControl.timeRefreshTimeoutHandler);
				timeControl.refreshTime();
			}
		},

		refreshAll: function(period, stime, isNow) {
			for (var id in this.screens) {
				var screen = this.screens[id];

				if (!empty(screen.id)) {
					screen.timeline.period = period;
					screen.timeline.stime = stime;
					screen.timeline.isNow = isNow;

					// restart refresh execution starting from Now
					clearTimeout(screen.timeoutHandler);
					this.refresh(id, true);
				}
			}
		},

		refreshHtml: function(id, ajaxUrl) {
			var screen = this.screens[id];

			if (screen.isRefreshing) {
				this.calculateReRefresh(id);
			}
			else {
				screen.isRefreshing = true;
				screen.timestampResponsiveness = new CDate().getTime();

				window.flickerfreeScreenShadow.start(id);

				var ajaxRequest = $.ajax({
					url: ajaxUrl.getUrl(),
					type: 'post',
					data: {},
					dataType: 'html',
					success: function(html) {
						if ($('#flickerfreescreen_' + id).data('timestamp') < $(html).data('timestamp')) {
							$('#flickerfreescreen_' + id).replaceWith(html);

							screen.isRefreshing = false;
							screen.timestamp = $(html).data('timestamp');

							window.flickerfreeScreenShadow.end(id);
						}
					},
					error: function(jqXHR, textStatus, errorThrown) {
						screen.isRefreshing = false;
					}
				});

				$.when(ajaxRequest).always(function() {
					if (screen.isReRefreshRequire) {
						screen.isReRefreshRequire = false;
						window.flickerfreeScreen.refresh(id, true);
					}
				});
			}
		},

		refreshImg: function(id, successAction) {
			var screen = this.screens[id];

			if (screen.isRefreshing) {
				this.calculateReRefresh(id);
			}
			else {
				screen.isRefreshing = true;
				screen.error = 0;
				screen.timestampResponsiveness = new CDate().getTime();

				window.flickerfreeScreenShadow.start(id);

				$('#flickerfreescreen_' + id).find('img').each(function() {
					var domImg = $(this);

					var url = new Curl(domImg.attr('src'));
					url.setArgument('screenid', !empty(screen.screenid) ? screen.screenid : null);
					url.setArgument('updateProfile', (typeof(screen.updateProfile) != 'undefined') ? + screen.updateProfile : null);
					url.setArgument('period', !empty(screen.timeline.period) ? screen.timeline.period : null);
					url.setArgument('stime', window.flickerfreeScreen.getCalculatedSTime(screen));
					url.setArgument('curtime', new CDate().getTime());

					// create temp image in buffer
					$('<img />', {
						id: domImg.attr('id') + '_tmp',
						'class': domImg.attr('class'),
						border: domImg.attr('border'),
						usemap: domImg.attr('usemap'),
						alt: domImg.attr('alt'),
						name: domImg.attr('name'),
						'data-timestamp': new CDate().getTime()
					})
					.attr('src', url.getUrl())
					.error(function() {
						screen.error++;
						screen.isRefreshing = false;

						// retry load image
						if (screen.error < 10) {
							window.flickerfreeScreen.refresh(id, true);
						}
					})
					.load(function(request) {
						if (screen.error > 0) {
							return;
						}

						screen.isRefreshing = false;

						// re-refresh image
						if (screen.isReRefreshRequire) {
							screen.isReRefreshRequire = false;
							window.flickerfreeScreen.refresh(id, true);
						}
						else {
							var bufferImg = $(this);

							if (bufferImg.data('timestamp') > screen.timestamp) {
								screen.timestamp = bufferImg.data('timestamp');

								// set id
								bufferImg.attr('id', bufferImg.attr('id').substring(0, bufferImg.attr('id').indexOf('_tmp')));

								// set loaded image from buffer to dom
								domImg.replaceWith(bufferImg);

								// callback function on success
								if (!empty(successAction)) {
									successAction();
								}

								// rebuild timeControl sbox listeners
								if (!empty(ZBX_SBOX[id])) {
									ZBX_SBOX[id].addListeners();
								}

								window.flickerfreeScreenShadow.end(id);
							}
						}
					});
				});
			}
		},

		refreshProfile: function(id, ajaxUrl) {
			var screen = this.screens[id];

			if (screen.isRefreshing) {
				this.calculateReRefresh(id);
			}
			else {
				screen.isRefreshing = true;
				screen.timestampResponsiveness = new CDate().getTime();

				var ajaxRequest = $.ajax({
					url: ajaxUrl.getUrl(),
					type: 'post',
					data: {},
					success: function(data) {
						screen.timestamp = new CDate().getTime();
						screen.isRefreshing = false;
					},
					error: function(jqXHR, textStatus, errorThrown) {
						screen.isRefreshing = false;
					}
				});

				$.when(ajaxRequest).always(function() {
					if (screen.isReRefreshRequire) {
						screen.isReRefreshRequire = false;
						window.flickerfreeScreen.refresh(id, true);
					}
				});
			}
		},

		calculateReRefresh: function(id) {
			var screen = this.screens[id];

			if (screen.timestamp + window.flickerfreeScreenShadow.responsiveness < new CDate().getTime()
					&& screen.timestampResponsiveness + window.flickerfreeScreenShadow.responsiveness < new CDate().getTime()) {
				// take of beasy flags
				screen.isRefreshing = false;
				screen.isReRefreshRequire = false;

				// refresh anyway
				window.flickerfreeScreen.refresh(id, true);
			}
			else {
				screen.isReRefreshRequire = true;
			}
		},

		isRefreshAllowed: function(screen) {
			return !empty(timeControl.timeline) ? timeControl.timeline.isNow() : true;
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
			var period = '',
				stime = '';

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
		}
	};

	window.flickerfreeScreenShadow = {

		timeout: 30000,
		responsiveness: 10000,
		timers: [],

		start: function(id) {
			if (empty(this.timers[id])) {
				this.timers[id] = {};
				this.timers[id].timeoutHandler = null;
				this.timers[id].ready = false;
			}

			clearTimeout(this.timers[id].timeoutHandler);
			this.timers[id].timeoutHandler = window.setTimeout(function() { window.flickerfreeScreenShadow.validate(id); }, this.timeout);
		},

		end: function(id) {
			clearTimeout(this.timers[id].timeoutHandler);
			this.removeShadow(id);
		},

		validate: function(id) {
			var screen = window.flickerfreeScreen.screens[id];

			if (screen.isRefreshing) {
				this.createShadow(id);
				this.start(id);
			}
			else {
				this.removeShadow(id);
				this.end(id);
			}
		},

		createShadow: function(id) {
			var elem = $('#flickerfreescreen_' + id),
				item = window.flickerfreeScreenShadow.findScreenItem(elem),
				timer = this.timers[id];
			if (empty(item)) {
				return;
			}

			// don't show shadow if image not loaded first time with the page
			if (item.prop('nodeName') == 'IMG' && !timer.ready && typeof(item.get(0).complete) == 'boolean') {
				if (!item.get(0).complete) {
					return;
				}
				else {
					timer.ready = true;
				}
			}

			// create shadow
			if (elem.find('.shadow').length == 0) {
				item.css({position: 'relative', zIndex: 2});

				elem.append($('<div>', {'class': 'shadow'})
					.html('&nbsp;')
					.css({
						top: item.position().top,
						left: item.position().left,
						width: item.width(),
						height: item.height(),
						position: 'absolute',
						zIndex: 1
					})
				);

				// fade screen
				var itemNode = elem.find(item.prop('nodeName'));
				if (!empty(itemNode)) {
					itemNode = (itemNode.length > 0) ? $(itemNode[0]) : itemNode;
					itemNode.fadeTo(2000, 0.6);
				}

				// show loading icon..
				elem.append($('<div>', {'class': 'loading'})
					.css({
						width: '24px',
						height: '24px',
						position: 'absolute',
						zIndex: 3,
						top: item.position().top + Math.round(item.height() / 2) - 12,
						left: item.position().left + Math.round(item.width() / 2) - 12
					})
				);
				elem.find('.loading').activity({
					segments: 12,
					steps: 3,
					opacity: 0.3,
					width: 2,
					space: 0,
					length: 5,
					color: '#0b0b0b'
				});
			}
		},

		removeShadow: function(id) {
			var elem = $('#flickerfreescreen_' + id),
				item = window.flickerfreeScreenShadow.findScreenItem(elem);
			if (empty(item)) {
				return;
			}

			elem.find(item.prop('nodeName')).fadeIn(0);
			elem.find('.loading').remove();
			elem.find('.shadow').remove();
		},

		moveShadows: function() {
			$('.flickerfreescreen').each(function() {
				var elem = $(this),
					item = window.flickerfreeScreenShadow.findScreenItem(elem);
				if (empty(item)) {
					return;
				}

				var shadows = elem.find('.shadow');
				if (shadows.length == 1) {
					shadows.css({
						top: item.position().top,
						left: item.position().left,
						width: item.width(),
						height: item.height()
					});
				}
			});
		},

		findScreenItem: function(elem) {
			var item = elem.children().eq(0),
				tag;

			if (!empty(item)) {
				tag = item.prop('nodeName');

				if (tag == 'MAP') {
					item = elem.children().eq(1);
					tag = item.prop('nodeName');
				}

				if (tag == 'TABLE' || tag == 'DIV' || tag == 'IMG') {
					return item;
				}
				else {
					item = item.find('img');

					return (item.length > 0) ? $(item[0]) : null;
				}
			}
			else {
				return null;
			}
		}
	};

	$(window).resize(function() {
		window.flickerfreeScreenShadow.moveShadows();
	});
});
