/*
 ** Zabbix
 ** Copyright (C) 2001-2018 Zabbix SIA
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


(function($) {

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
			this.screens[screen.id].timestampActual = 0;
			this.screens[screen.id].isRefreshing = false;
			this.screens[screen.id].isReRefreshRequire = false;
			this.screens[screen.id].error = 0;

			// SCREEN_RESOURCE_MAP
			if (screen.resourcetype == 2) {
				this.screens[screen.id].data = new SVGMap(this.screens[screen.id].data);
				$(screen.data.container).attr({'aria-label': screen.data.options.aria_label, 'tabindex': 0})
					.find('svg').attr('aria-hidden', 'true');
			}

			// init refresh plan
			if (screen.isFlickerfree && screen.interval > 0) {
				this.screens[screen.id].timeoutHandler = window.setTimeout(
					function() {
						window.flickerfreeScreen.refresh(screen.id);
					},
					this.screens[screen.id].interval
				);
			}
		},

		refresh: function(id, isSelfRefresh) {
			var screen = this.screens[id], ajaxParams;

			switch (screen.resourcetype) {
				case 17:
					// SCREEN_RESOURCE_HISTORY
					ajaxParams = ['mode', 'pageFile', 'page'];
					break;

				case 21:
					// SCREEN_RESOURCE_HTTPTEST_DETAILS
					ajaxParams = ['mode', 'resourcetype', 'profileIdx2'];
					break;

				case 22:
					// SCREEN_RESOURCE_DISCOVERY
					ajaxParams = ['mode', 'resourcetype', 'data'];
					break;

				case 23:
					// SCREEN_RESOURCE_HTTPTEST
					ajaxParams = ['mode', 'groupid', 'hostid', 'resourcetype', 'data', 'page'];
					break;

				case 24:
					// SCREEN_RESOURCE_PROBLEM
					ajaxParams = ['mode', 'resourcetype', 'data', 'page'];
					break;

				default:
					ajaxParams = ['mode', 'screenid', 'groupid', 'hostid', 'pageFile', 'profileIdx', 'profileIdx2',
						'updateProfile', 'screenitemid'
					];
			}

			if (empty(screen.id)) {
				return;
			}

			if (empty(isSelfRefresh)) {
				isSelfRefresh = false;
			}

			// set actual timestamp
			screen.timestampActual = new CDate().getTime();

			var ajaxUrl = new Curl('jsrpc.php');
			ajaxUrl.setArgument('type', 9); // PAGE_TYPE_TEXT
			ajaxUrl.setArgument('method', 'screen.get');
			ajaxUrl.setArgument('timestamp', screen.timestampActual);

			for (var i = 0; i < ajaxParams.length; i++) {
				ajaxUrl.setArgument(ajaxParams[i], empty(screen[ajaxParams[i]]) ? null : screen[ajaxParams[i]]);
			}

			// timeline params
			// SCREEN_RESOURCE_HTTPTEST_DETAILS, SCREEN_RESOURCE_DISCOVERY, SCREEN_RESOURCE_HTTPTEST
			if (jQuery.inArray(screen.resourcetype, [21, 22, 23]) === -1) {
				if (!empty(timeControl.timeline)) {
					timeControl.timeline.refreshEndtime();
				}
				ajaxUrl.setArgument('period', empty(screen.timeline.period) ? null : this.getCalculatedPeriod(screen));
				ajaxUrl.setArgument('stime', this.getCalculatedSTime(screen));
				if (typeof screen.timeline.isNow !== 'undefined') {
					ajaxUrl.setArgument('isNow', + screen.timeline.isNow);
				}
			}

			// SCREEN_RESOURCE_GRAPH or SCREEN_RESOURCE_SIMPLE_GRAPH
			if (screen.resourcetype == 0 || screen.resourcetype == 1) {
				if (isSelfRefresh || this.isRefreshAllowed(screen)) {
					this.refreshImg(id, function() {
						$('#flickerfreescreen_' + id + ' a').each(function() {
							var obj = $(this),
								url = new Curl(obj.attr('href'));

							url.setArgument('period', empty(screen.timeline.period)
								? null
								: window.flickerfreeScreen.getCalculatedPeriod(screen)
							);
							url.setArgument('stime', window.flickerfreeScreen.getCalculatedSTime(screen));
							if (typeof screen.timeline.isNow !== 'undefined') {
								url.setArgument('isNow', + screen.timeline.isNow);
							}
							obj.attr('href', url.getUrl());
						});
					});
				}
			}

			// SCREEN_RESOURCE_MAP
			else if (screen.resourcetype == 2) {
				this.refreshMap(id);
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
						ajaxUrl.setArgument('resourcetype', empty(screen.resourcetype) ? null : screen.resourcetype);

						if ('itemids' in screen.data) {
							for (var i = 0; i < screen.data.itemids.length; i++) {
								ajaxUrl.setArgument(
									'itemids[' + screen.data.itemids[i] + ']',
									empty(screen.data.itemids[i]) ? null : screen.data.itemids[i]
								);
							}
						}
						else {
							ajaxUrl.setArgument('graphid', screen.data.graphid);
						}

						jQuery.each({
							'filter': screen.data.filter,
							'filter_task': screen.data.filterTask,
							'mark_color': screen.data.markColor,
							'page': screen.data.page,
							'action': screen.data.action
						}, function (ajax_key, value) {
							if (!empty(value)) {
								ajaxUrl.setArgument(ajax_key, value);
							}
						});

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

			// SCREEN_RESOURCE_LLD_GRAPH
			else if (screen.resourcetype == 20) {
				this.refreshProfile(id, ajaxUrl);
			}

			// SCREEN_RESOURCE_LLD_SIMPLE_GRAPH
			else if (screen.resourcetype == 19) {
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

				screen.timeoutHandler = window.setTimeout(
					function() {
						window.flickerfreeScreen.refresh(id);
					},
					screen.interval
				);

				// refresh time control actual time
				clearTimeout(timeControl.timeRefreshTimeoutHandler);
				timeControl.refreshTime();
			}
		},

		refreshAll: function(period, stime, isNow) {
			for (var id in this.screens) {
				var screen = this.screens[id];

				if (!empty(screen.id) && typeof screen.timeline !== 'undefined') {
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
					cache: false,
					data: {},
					dataType: 'html',
					success: function(html) {
						// Get timestamp and error message from HTML.
						var htmlTimestamp = null,
							msg_bad = null;

						$(html).each(function() {
							var obj = $(this);

							if (obj.hasClass('msg-bad')) {
								msg_bad = obj;
							}
							else if (obj.prop('nodeName') === 'DIV') {
								htmlTimestamp = obj.data('timestamp');
							}
						});

						$('main > .msg-bad').remove();

						// set message
						if (msg_bad) {
							$(msg_bad).insertBefore('main > :first-child');
							html = $(html).not('.msg-bad');
						}

						// set html
						if ($('#flickerfreescreen_' + id).data('timestamp') < htmlTimestamp) {
							$('#flickerfreescreen_' + id).replaceWith(html);

							screen.isRefreshing = false;
							screen.timestamp = htmlTimestamp;

							window.flickerfreeScreenShadow.isShadowed(id, false);
							window.flickerfreeScreenShadow.fadeSpeed(id, 0);
							window.flickerfreeScreenShadow.validate(id);
						}
						else if (!html.length) {
							$('#flickerfreescreen_' + id).remove();
						}

						chkbxRange.init();
					},
					error: function() {
						window.flickerfreeScreen.calculateReRefresh(id);
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

		refreshMap: function(id) {
			var screen = this.screens[id], self = this;

			if (screen.isRefreshing) {
				this.calculateReRefresh(id);
			}
			else {
				screen.isRefreshing = true;
				screen.error = 0;
				screen.timestampResponsiveness = new CDate().getTime();

				window.flickerfreeScreenShadow.start(id);

				var url = new Curl(screen.data.options.refresh);
				url.setArgument('curtime', new CDate().getTime());

				jQuery.ajax( {
					'url': url.getUrl()
				})
				.error(function() {
					screen.error++;
					window.flickerfreeScreen.calculateReRefresh(id);
				})
				.done(function(data) {
					data.show_timestamp = screen.data.options.show_timestamp;
					screen.isRefreshing = false;
					screen.data.update(data);
					$(screen.data.container).attr('aria-label', data.aria_label);
					screen.timestamp = screen.timestampActual;
					window.flickerfreeScreenShadow.end(id);
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

				$('#flickerfreescreen_' + id + ' img').each(function() {
					var domImg = $(this),
						url = new Curl(domImg.attr('src')),
						on_dashboard = timeControl.objectList[id].onDashboard;

					url.setArgument('screenid', empty(screen.screenid) ? null : screen.screenid);
					if (typeof screen.updateProfile === 'undefined') {
						url.setArgument('updateProfile', + screen.updateProfile);
					}
					url.setArgument('period', empty(screen.timeline.period)
						? null
						: window.flickerfreeScreen.getCalculatedPeriod(screen)
					);
					url.setArgument('stime', window.flickerfreeScreen.getCalculatedSTime(screen));
					if (typeof screen.timeline.isNow !== 'undefined') {
						url.setArgument('isNow', + screen.timeline.isNow);
					}
					url.setArgument('curtime', new CDate().getTime());

					// Create temp image in buffer.
					var img = $('<img>', {
							'class': domImg.attr('class'),
							'data-timestamp': new CDate().getTime(),
							id: domImg.attr('id') + '_tmp',
							name: domImg.attr('name'),
							border: domImg.attr('border'),
							usemap: domImg.attr('usemap'),
							alt: domImg.attr('alt'),
							css: {
								position: 'relative',
								zIndex: 2
							}
						})
						.error(function() {
							screen.error++;
							window.flickerfreeScreen.calculateReRefresh(id);
						})
						.on('load', function() {
							if (screen.error > 0) {
								return;
							}

							screen.isRefreshing = false;

							// Re-refresh image.
							var bufferImg = $(this);

							if (bufferImg.data('timestamp') > screen.timestamp) {
								screen.timestamp = bufferImg.data('timestamp');

								// Set id.
								bufferImg.attr('id', bufferImg.attr('id').substring(0, bufferImg.attr('id').indexOf('_tmp')));

								// Set opacity state.
								if (window.flickerfreeScreenShadow.isShadowed(id)) {
									bufferImg.fadeTo(0, 0.6);
								}

								if (!empty(bufferImg.data('height'))) {
									timeControl.changeSBoxHeight(id, bufferImg.data('height'));
								}

								// Set loaded image from buffer to dom.
								domImg.replaceWith(bufferImg);

								// Callback function on success.
								if (!empty(successAction)) {
									successAction();
								}

								// Rebuild timeControl sbox listeners.
								if (!empty(ZBX_SBOX[id])) {
									ZBX_SBOX[id].addListeners();
								}

								window.flickerfreeScreenShadow.end(id);
							}

							if (screen.isReRefreshRequire) {
								screen.isReRefreshRequire = false;
								window.flickerfreeScreen.refresh(id, true);
							}

							if (on_dashboard) {
								timeControl.updateDashboardFooter(id);
							}
						});

					if (['chart.php', 'chart2.php', 'chart3.php'].indexOf(url.getPath()) > -1
							&& url.getArgument('outer') === '1') {
						// Getting height of graph inside image. Only for line graphs on dashboard.
						var heightUrl = new Curl(url.getUrl());
						heightUrl.setArgument('onlyHeight', '1');

						$.ajax({
							url: heightUrl.getUrl(),
							success: function(response, status, xhr) {
								// 'src' should be added only here to trigger load event after new height is received.
								img.data('height', +xhr.getResponseHeader('X-ZBX-SBOX-HEIGHT'));
								img.attr('src', url.getUrl());
							}
						});
					}
					else {
						img.attr('src', url.getUrl());
					}
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
					error: function() {
						window.flickerfreeScreen.calculateReRefresh(id);
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
			var screen = this.screens[id],
				time = new CDate().getTime();

			if (screen.timestamp + window.flickerfreeScreenShadow.responsiveness < time
					&& screen.timestampResponsiveness + window.flickerfreeScreenShadow.responsiveness < time) {
				// take of busy flags
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
			return empty(timeControl.timeline) ? true : timeControl.timeline.isNow();
		},

		getCalculatedSTime: function(screen) {
			if (timeControl.timeline && timeControl.timeline.is_selectall_period) {
				return timeControl.timeline.usertime();
			}

			return screen.timeline.stime;
		},

		/**
		 * Return period in seconds for requesting data. Automatically calculates period when 'All' period is selected.
		 *
		 * @property {Object} screen screen object
		 *
		 * @return {int}
		 */
		getCalculatedPeriod: function (screen) {
			return !empty(timeControl.timeline) ? timeControl.timeline.period() : screen.timeline.period;
		},

		cleanAll: function() {
			for (var id in this.screens) {
				var screen = this.screens[id];

				if (!empty(screen.id)) {
					clearTimeout(screen.timeoutHandler);
				}
			}

			this.screens = [];
			ZBX_SBOX = {};

			for (var id in timeControl.objectList) {
				if (id !== 'scrollbar' && timeControl.objectList.hasOwnProperty(id)) {
					delete timeControl.objectList[id];
				}
			}

			window.flickerfreeScreenShadow.cleanAll();
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
				this.timers[id].isShadowed = false;
				this.timers[id].fadeSpeed = 2000;
				this.timers[id].inUpdate = false;
			}

			var timer = this.timers[id];

			if (!timer.inUpdate) {
				this.refresh(id);
			}
		},

		refresh: function(id) {
			var timer = this.timers[id];

			timer.inUpdate = true;

			clearTimeout(timer.timeoutHandler);
			timer.timeoutHandler = window.setTimeout(
				function() {
					window.flickerfreeScreenShadow.validate(id);
				},
				this.timeout
			);
		},

		end: function(id) {
			var screen = window.flickerfreeScreen.screens[id];

			if (typeof this.timers[id] !== 'undefined' && !empty(screen)
					&& (screen.timestamp + this.timeout) >= screen.timestampActual
			) {
				var timer = this.timers[id];
				timer.inUpdate = false;

				clearTimeout(timer.timeoutHandler);
				this.removeShadow(id);
				this.fadeSpeed(id, 2000);
			}
		},

		validate: function(id) {
			var screen = window.flickerfreeScreen.screens[id];

			if (!empty(screen) && (screen.timestamp + this.timeout) < screen.timestampActual) {
				this.createShadow(id);
				this.refresh(id);
			}
			else {
				this.end(id);
			}
		},

		createShadow: function(id) {
			var timer = this.timers[id];

			if (!empty(timer) && !timer.isShadowed) {
				var obj = $('#flickerfreescreen_' + id),
					item = window.flickerfreeScreenShadow.findScreenItem(obj);

				if (empty(item)) {
					return;
				}

				// don't show shadow if image not loaded first time with the page
				if (item.prop('nodeName') == 'IMG' && !timer.ready && typeof item.get(0).complete === 'boolean') {
					if (!item.get(0).complete) {
						return;
					}
					else {
						timer.ready = true;
					}
				}

				// create shadow
				if (obj.find('.shadow').length == 0) {
					item.css({position: 'relative', zIndex: 2});

					obj.append($('<div>', {'class': 'shadow'})
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
					var itemNode = obj.find(item.prop('nodeName'));
					if (!empty(itemNode)) {
						itemNode = (itemNode.length > 0) ? $(itemNode[0]) : itemNode;
						itemNode.fadeTo(timer.fadeSpeed, 0.6);
					}

					// show loading indicator..
					obj.append($('<div>', {'class': 'preloader'})
						.css({
							width: '24px',
							height: '24px',
							position: 'absolute',
							zIndex: 3,
							top: item.position().top + Math.round(item.height() / 2) - 12,
							left: item.position().left + Math.round(item.width() / 2) - 12
						})
					);

					timer.isShadowed = true;
				}
			}
		},

		removeShadow: function(id) {
			var timer = this.timers[id];

			if (!empty(timer) && timer.isShadowed) {
				var obj = $('#flickerfreescreen_' + id),
					item = window.flickerfreeScreenShadow.findScreenItem(obj);
				if (empty(item)) {
					return;
				}

				obj.find('.preloader').remove();
				obj.find('.shadow').remove();
				obj.find(item.prop('nodeName')).fadeTo(0, 1);

				timer.isShadowed = false;
			}
		},

		moveShadows: function() {
			$('.flickerfreescreen').each(function() {
				var obj = $(this),
					item = window.flickerfreeScreenShadow.findScreenItem(obj);

				if (empty(item)) {
					return;
				}

				// shadow
				var shadows = obj.find('.shadow');

				if (shadows.length > 0) {
					shadows.css({
						top: item.position().top,
						left: item.position().left,
						width: item.width(),
						height: item.height()
					});
				}

				// loading indicator
				var preloader = obj.find('.preloader');

				if (preloader.length > 0) {
					preloader.css({
						top: item.position().top + Math.round(item.height() / 2) - 12,
						left: item.position().left + Math.round(item.width() / 2) - 12
					});
				}
			});
		},

		findScreenItem: function(obj) {
			var item = obj.children().eq(0),
				tag;

			if (!empty(item)) {
				tag = item.prop('nodeName');

				if (tag == 'MAP') {
					item = obj.children().eq(1);
					tag = item.prop('nodeName');
				}

				if (tag == 'DIV') {
					var imgItem = item.find('img');

					if (imgItem.length > 0) {
						item = $(imgItem[0]);
						tag = 'IMG';
					}
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
		},

		isShadowed: function(id, isShadowed) {
			var timer = this.timers[id];

			if (!empty(timer)) {
				if (typeof isShadowed !== 'undefined') {
					this.timers[id].isShadowed = isShadowed;
				}

				return this.timers[id].isShadowed;
			}

			return false;
		},

		fadeSpeed: function(id, fadeSpeed) {
			var timer = this.timers[id];

			if (!empty(timer)) {
				if (typeof fadeSpeed !== 'undefined') {
					this.timers[id].fadeSpeed = fadeSpeed;
				}

				return this.timers[id].fadeSpeed;
			}

			return 0;
		},

		cleanAll: function() {
			for (var id in this.timers) {
				var timer = this.timers[id];

				if (!empty(timer.timeoutHandler)) {
					clearTimeout(timer.timeoutHandler);
				}
			}

			this.timers = [];
		}
	};

	$(window).resize(function() {
		window.flickerfreeScreenShadow.moveShadows();
	});
}(jQuery));
