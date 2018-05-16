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

		refresh: function(id, is_self_refresh) {
			var screen = this.screens[id];

			if (empty(screen.id)) {
				return;
			}

			/**
			 * 17   SCREEN_RESOURCE_HISTORY
			 * 21   SCREEN_RESOURCE_HTTPTEST_DETAILS
			 * 22   SCREEN_RESOURCE_DISCOVERY
			 * 23   SCREEN_RESOURCE_HTTPTEST
			 * 24   SCREEN_RESOURCE_PROBLEM
			 */
			var type_params = {
					'17': ['mode', 'resourcetype', 'pageFile', 'page'],
					'21': ['mode', 'resourcetype', 'profileIdx2'],
					'22': ['mode', 'resourcetype', 'data'],
					'23': ['mode', 'groupid', 'hostid', 'resourcetype', 'data', 'page'],
					'24': ['mode', 'resourcetype', 'data', 'page'],
					'default': ['mode', 'screenid', 'groupid', 'hostid', 'pageFile', 'profileIdx', 'profileIdx2',
						'updateProfile', 'screenitemid'
					]
				},
				params_index = type_params[screen.resourcetype] ? screen.resourcetype : 'default';
				ajax_url = new Curl('jsrpc.php'),
				refresh = (!empty(is_self_refresh) || !empty(screen.timeline.refreshable)),
				self = this;

			ajax_url.setArgument('type', 9); // PAGE_TYPE_TEXT
			ajax_url.setArgument('method', 'screen.get');
			// TODO: remove, do not use timestamp passing to server and back to ensure newest content will be shown.
			ajax_url.setArgument('timestamp', screen.timestampActual);

			$.each(type_params[params_index], function (i, name) {
				ajax_url.setArgument(name, empty(screen[name]) ? null : screen[name]);
			});

			// set actual timestamp
			screen.timestampActual = new CDate().getTime();

			// timeline params
			// SCREEN_RESOURCE_HTTPTEST_DETAILS, SCREEN_RESOURCE_DISCOVERY, SCREEN_RESOURCE_HTTPTEST
			if (jQuery.inArray(screen.resourcetype, [21, 22, 23]) === -1) {
				ajax_url.setArgument('from', screen.timeline.from);
				ajax_url.setArgument('to', screen.timeline.to);
			}

			switch (parseInt(screen.resourcetype, 10)) {
				// SCREEN_RESOURCE_GRAPH
				case 0:
					// falls through

				// SCREEN_RESOURCE_SIMPLE_GRAPH
				case 1:
					if (refresh) {
						self.refreshImg(id, function() {
							$('a', '#flickerfreescreen_' + id).each(function() {
									var obj = $(this),
									url = new Curl(obj.attr('href'));

									url.setArgument('from', screen.timeline.from);
									url.setArgument('to', screen.timeline.to);

									obj.attr('href', url.getUrl());
								});
							});
					}
					break;

				// SCREEN_RESOURCE_MAP
				case 2:
					self.refreshMap(id);
					break;

				// SCREEN_RESOURCE_PLAIN_TEXT
				case 3:
					if (refresh) {
						self.refreshHtml(id, ajax_url);
					}
					break;

				// SCREEN_RESOURCE_CLOCK
				case 7:
					// don't refresh anything
					break;

				// SCREEN_RESOURCE_SCREEN
				case 8:
					self.refreshProfile(id, ajax_url);
					break;

				// SCREEN_RESOURCE_HISTORY
				case 17:
					if (refresh) {
						if (screen.data.action == 'showgraph') {
							self.refreshImg(id);
						}
						else {
							if ('itemids' in screen.data) {
								$.each(screen.data.itemids, function (i, value) {
									if (!empty(value)) {
										ajax_url.setArgument('itemids[' + value + ']', value);
									}
								});
							}
							else {
								ajax_url.setArgument('graphid', screen.data.graphid);
							}

							$.each({
								'filter': screen.data.filter,
								'filter_task': screen.data.filterTask,
								'mark_color': screen.data.markColor,
								'page': screen.data.page,
								'action': screen.data.action
							}, function (ajax_key, value) {
								if (!empty(value)) {
									ajax_url.setArgument(ajax_key, value);
								}
							});

							self.refreshHtml(id, ajax_url);
						}
					}
					break;

				// SCREEN_RESOURCE_CHART
				case 18:
					if (refresh) {
						self.refreshImg(id);
					}
					break;

				// SCREEN_RESOURCE_LLD_SIMPLE_GRAPH
				case 19:
					// falls through

				// SCREEN_RESOURCE_LLD_GRAPH
				case 20:
					self.refreshProfile(id, ajax_url);
					break;

				default:
					self.refreshHtml(id, ajax_url);
					break;
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

		refreshAll: function(time_object) {
			for (var id in this.screens) {
				var screen = this.screens[id];

				if (!empty(screen.id) && typeof screen.timeline !== 'undefined') {
					screen.timeline = jQuery.extend(screen.timeline, {
						from: time_object.from,
						to: time_object.to,
						from_ts: time_object.from_ts,
						to_ts: time_object.to_ts,
						refreshable: time_object.refreshable
					});

					// restart refresh execution starting from Now
					clearTimeout(screen.timeoutHandler);
					this.refresh(id, true);
				}
			}
		},

		refreshHtml: function(id, ajaxUrl) {
			var screen = this.screens[id],
				request_start = new CDate().getTime();

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
						var html = $(html);

						// Replace existing markup with server response.
						if (request_start > screen.timestamp) {
							screen.timestamp = request_start;
							screen.isRefreshing = false;

							$('main .msg-bad').remove();
							$('#flickerfreescreen_' + id).replaceWith(html);
							$('main .msg-bad').insertBefore('main > :first-child');

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
			var screen = this.screens[id],
				request_start = new CDate().getTime();

			if (screen.isRefreshing) {
				this.calculateReRefresh(id);
			}
			else {
				screen.isRefreshing = true;
				screen.error = 0;
				screen.timestampResponsiveness = new CDate().getTime();

				window.flickerfreeScreenShadow.start(id);

				$('img', '#flickerfreescreen_' + id).each(function() {
					var domImg = $(this),
						url = new Curl(domImg.attr('src')),
						on_dashboard = timeControl.objectList[id].onDashboard;

					url.setArgument('screenid', empty(screen.screenid) ? null : screen.screenid);
					url.setArgument('from', screen.timeline.from);
					url.setArgument('to', screen.timeline.to);

					if (typeof screen.updateProfile === 'undefined') {
						url.setArgument('updateProfile', 0);
					}

					// Create temp image in buffer.
					var	img = $('<img/>')
						.error(function() {
							screen.error++;
							window.flickerfreeScreen.calculateReRefresh(id);
						})
						.on('load', function() {
							if (screen.error > 0) {
								return;
							}

							screen.isRefreshing = false;

							if (request_start > screen.timestamp) {
								screen.timestamp = request_start;

								// Set opacity state.
								if (window.flickerfreeScreenShadow.isShadowed(id)) {
									img.fadeTo(0, 0.6);
								}

								domImg.attr('src', img.attr('src'));

								// Callback function on success.
								if (!empty(successAction)) {
									successAction();
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

					var async = flickerfreeScreen.getImageSboxHeight(url, function (height) {
						// 'src' should be added only here to trigger load event after new height is received.
						var zbx_sbox = domImg.data('zbx_sbox');

						domImg.data('zbx_sbox', jQuery.extend(zbx_sbox, {
							height: parseInt(height, 10),
							from: screen.timeline.from,
							from_ts: screen.timeline.from_ts,
							to: screen.timeline.to,
							to_ts: screen.timeline.to_ts
						}));

						// Prevent image caching.
						url.setArgument('_', request_start.toString(34));
						img.attr('src', url.getUrl());
					});
					if (async === null) {
						img.attr('src', url.getUrl());
					}
				});
			}
		},

		/**
		 * Getting shadow box height of graph image, asynchronious. Only for line graphs on dashboard.
		 * Will return xhr request for line graphs.
		 *
		 * @param {Curl}     url  Curl object for image request.
		 *                        Endpoint should support returning height via HTTP header.
		 * @param {function} cb   Callable, will be called with value of shadow box height.
		 *
		 * @return {object|null}
		 */
		getImageSboxHeight: function (url, cb) {
			if (['chart.php', 'chart2.php', 'chart3.php'].indexOf(url.getPath()) > -1) {
				// Prevent request caching.
				url.setArgument('_', (new Date).getTime().toString(34));

				return $.get(url.getUrl(), {'onlyHeight': 1}, 'json')
					.success(function(response, status, xhr) {
						cb(xhr.getResponseHeader('X-ZBX-SBOX-HEIGHT'))
					});
			}

			return null;
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

		cleanAll: function() {
			for (var id in this.screens) {
				var screen = this.screens[id];

				if (!empty(screen.id)) {
					clearTimeout(screen.timeoutHandler);
				}
			}

			this.screens = [];

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
