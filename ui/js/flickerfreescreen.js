/*
 ** Zabbix
 ** Copyright (C) 2001-2022 Zabbix SIA
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
		responsiveness: 10000,

		/**
		 * Set or reset UI in progress state for element with id.
		 *
		 * @param {boolean} in_progress
		 * @param {string}  id
		 */
		setElementProgressState: function(id, in_progress) {
			var elm = $('#flickerfreescreen_'+id);

			if (in_progress) {
				elm.addClass('is-loading is-loading-fadein delayed-15s');
			}
			else {
				elm.removeClass('is-loading is-loading-fadein delayed-15s');
			}
		},

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

		remove: function(screen) {
			if (typeof screen.id !== 'undefined' && typeof this.screens[screen.id] !== 'undefined') {
				if (typeof this.screens[screen.id].timeoutHandler !== 'undefined') {
					window.clearTimeout(this.screens[screen.id].timeoutHandler);
				}

				delete this.screens[screen.id];
			}
		},

		refresh: function(id) {
			var screen = this.screens[id];

			if (empty(screen.id)) {
				return;
			}

			// Do not update screen if displaying static hintbox.
			if ($('#flickerfreescreen_' + id + ' [data-expanded="true"]').length) {
				if (screen.isFlickerfree && screen.interval > 0) {
					clearTimeout(screen.timeoutHandler);
					screen.timeoutHandler = setTimeout(() => flickerfreeScreen.refresh(id), 1000);
				}

				return;
			}

			/**
			 * 17   SCREEN_RESOURCE_HISTORY
			 * 21   SCREEN_RESOURCE_HTTPTEST_DETAILS
			 * 22   SCREEN_RESOURCE_DISCOVERY
			 * 23   SCREEN_RESOURCE_HTTPTEST
			 */
			var type_params = {
					'17': ['mode', 'resourcetype', 'pageFile', 'page'],
					'21': ['mode', 'resourcetype', 'profileIdx2'],
					'22': ['mode', 'resourcetype', 'data'],
					'23': ['mode', 'resourcetype', 'data', 'page'],
					'default': ['mode', 'screenid', 'groupid', 'hostid', 'pageFile', 'profileIdx', 'profileIdx2',
						'screenitemid'
					]
				},
				params_index = type_params[screen.resourcetype] ? screen.resourcetype : 'default',
				self = this;

			const ajax_url = new Curl('jsrpc.php', false);
			const post_data = {
				type: 9, // PAGE_TYPE_TEXT
				method: 'screen.get',

				// TODO: remove, do not use timestamp passing to server and back to ensure newest content will be shown.
				timestamp: screen.timestampActual
			};

			$.each(type_params[params_index], function (i, name) {
				if (!empty(screen[name])) {
					post_data[name] = screen[name];
				}
			});

			// set actual timestamp
			screen.timestampActual = new CDate().getTime();

			// timeline params
			// SCREEN_RESOURCE_HTTPTEST_DETAILS, SCREEN_RESOURCE_DISCOVERY, SCREEN_RESOURCE_HTTPTEST
			if ($.inArray(screen.resourcetype, [21, 22, 23]) === -1) {
				post_data.from = screen.timeline.from;
				post_data.to = screen.timeline.to;
			}

			switch (parseInt(screen.resourcetype, 10)) {
				// SCREEN_RESOURCE_GRAPH
				// SCREEN_RESOURCE_SIMPLE_GRAPH
				case 0:
				case 1:
					self.refreshImg(id, function() {
						$('a', '#flickerfreescreen_' + id).each(function() {
								var obj = $(this),
								url = new Curl(obj.attr('href'), false);

								url.setArgument('from', screen.timeline.from);
								url.setArgument('to', screen.timeline.to);

								obj.attr('href', url.getUrl());
							});
						});
					break;

				// SCREEN_RESOURCE_MAP
				case 2:
					self.refreshMap(id);
					break;

				// SCREEN_RESOURCE_HISTORY
				case 17:
					if (screen.data.action == 'showgraph') {
						self.refreshImg(id);
					}
					else {
						if ('itemids' in screen.data) {
							$.each(screen.data.itemids, function (i, value) {
								if (!empty(value)) {
									post_data['itemids[' + value + ']'] = value;
								}
							});
						}
						else {
							post_data['graphid'] = screen.data.graphid;
						}

						$.each({
							'filter': screen.data.filter,
							'filter_task': screen.data.filterTask,
							'mark_color': screen.data.markColor,
							'action': screen.data.action
						}, function (ajax_key, value) {
							if (!empty(value)) {
								post_data[ajax_key] = value;
							}
						});

						self.refreshHtml(id, ajax_url, post_data);
					}
					break;

				default:
					self.refreshHtml(id, ajax_url, post_data);
					break;
			}

			// set next refresh execution time
			if (screen.isFlickerfree && screen.interval > 0) {
				clearTimeout(screen.timeoutHandler);
				screen.timeoutHandler = setTimeout(() => flickerfreeScreen.refresh(id), screen.interval);
			}
		},

		refreshAll: function(time_object) {
			for (var id in this.screens) {
				var screen = this.screens[id];

				if (!empty(screen.id) && typeof screen.timeline !== 'undefined') {
					screen.timeline = $.extend(screen.timeline, {
						from: time_object.from,
						to: time_object.to,
						from_ts: time_object.from_ts,
						to_ts: time_object.to_ts
					});

					// Reset pager on time range update (SCREEN_RESOURCE_HISTORY).
					if (screen.resourcetype == 17) {
						screen.page = 1;
					}

					// restart refresh execution starting from Now
					clearTimeout(screen.timeoutHandler);
					this.refresh(id);
				}
			}
		},

		refreshHtml: function(id, ajaxUrl, post_data = {}) {
			var screen = this.screens[id],
				request_start = new CDate().getTime();

			if (screen.isRefreshing) {
				this.calculateReRefresh(id);
			}
			else {
				screen.isRefreshing = true;
				screen.timestampResponsiveness = new CDate().getTime();
				this.setElementProgressState(id, true);

				var ajaxRequest = $.ajax({
					url: ajaxUrl.getUrl(),
					type: 'post',
					cache: false,
					data: post_data,
					dataType: 'html',
					success: function(html) {
						var html = $(html);

						// Replace existing markup with server response.
						if (request_start > screen.timestamp) {
							screen.timestamp = request_start;
							screen.isRefreshing = false;

							$('.wrapper > .msg-bad').remove();
							$('#flickerfreescreen_' + id).replaceWith(html);
							html.filter('.msg-bad').insertBefore('.wrapper main');

							window.flickerfreeScreen.setElementProgressState(id, false);
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
						window.flickerfreeScreen.refresh(id);
					}
				});
			}
		},

		refreshMap: function(id) {
			var screen = this.screens[id];

			if (screen.isRefreshing) {
				this.calculateReRefresh(id);
			}
			else {
				screen.isRefreshing = true;
				screen.error = 0;
				screen.timestampResponsiveness = new CDate().getTime();

				this.setElementProgressState(id, true);

				var url = new Curl(screen.data.options.refresh);
				url.setArgument('curtime', new CDate().getTime());

				$.ajax({
					'url': url.getUrl()
				})
				.fail(function() {
					screen.error++;
					window.flickerfreeScreen.calculateReRefresh(id);
				})
				.done(function(data) {
					data.show_timestamp = screen.data.options.show_timestamp;
					screen.isRefreshing = false;
					screen.data.update(data);
					$(screen.data.container).attr('aria-label', data.aria_label);
					screen.timestamp = screen.timestampActual;
					window.flickerfreeScreen.setElementProgressState(id, false);
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

				this.setElementProgressState(id, true);

				$('img', '#flickerfreescreen_' + id).each(function() {
					var domImg = $(this),
						url = new Curl(domImg.attr('src'), false),
						zbx_sbox = domImg.data('zbx_sbox');

					if (zbx_sbox && zbx_sbox.prevent_refresh) {
						screen.isRefreshing = false;
						window.flickerfreeScreen.setElementProgressState(id, false);
						return;
					}

					url.setArgument('screenid', empty(screen.screenid) ? null : screen.screenid);
					url.setArgument('from', screen.timeline.from);
					url.setArgument('to', screen.timeline.to);
					// Prevent image caching.
					url.setArgument('_', request_start.toString(34));

					// Create temp image in buffer.
					var	img = $('<img>', {
							class: domImg.attr('class'),
							id: domImg.attr('id'),
							name: domImg.attr('name'),
							border: domImg.attr('border'),
							usemap: domImg.attr('usemap'),
							alt: domImg.attr('alt')
						})
						.on('error', function() {
							screen.error++;
							window.flickerfreeScreen.calculateReRefresh(id);
						})
						.on('load', function() {
							if (screen.error > 0) {
								return;
							}

							screen.isRefreshing = false;
							window.flickerfreeScreen.setElementProgressState(id, false);

							if (request_start > screen.timestamp) {
								screen.timestamp = request_start;

								domImg.replaceWith(img);

								// Callback function on success.
								if (!empty(successAction)) {
									successAction();
								}
							}

							if (screen.isReRefreshRequire) {
								screen.isReRefreshRequire = false;
								window.flickerfreeScreen.refresh(id);
							}
						});

					var async = flickerfreeScreen.getImageSboxHeight(url, function(height) {
							zbx_sbox.height = parseInt(height, 10);
							// 'src' should be added only here to trigger load event after new height is received.
							img.data('zbx_sbox', zbx_sbox)
								.attr('src', url.getUrl());
						});

					if (async === null) {
						img.attr('src', url.getUrl());
					}

					if (zbx_sbox) {
						img.data('zbx_sbox', $.extend(zbx_sbox, {
							from: screen.timeline.from,
							from_ts: screen.timeline.from_ts,
							to: screen.timeline.to,
							to_ts: screen.timeline.to_ts
						}));
					}
				});
			}
		},

		/**
		 * Getting shadow box height of graph image, asynchronous. Only for line graphs on dashboard.
		 * Will return xhr request for line graphs.
		 *
		 * @param {Curl}     url  Curl object for image request.
		 *                        Endpoint should support returning height via HTTP header.
		 * @param {function} cb   Callable, will be called with value of shadow box height.
		 *
		 * @return {object|null}
		 */
		getImageSboxHeight: function (url, cb) {
			if (['chart.php', 'chart2.php', 'chart3.php'].indexOf(url.getPath()) > -1
					&& url.getArgument('outer') === '1') {
				// Prevent request caching.
				url.setArgument('_', (new Date).getTime().toString(34));

				return $.get(url.getUrl(), {'onlyHeight': 1}, 'json')
					.done(function(response, status, xhr) {
						cb(xhr.getResponseHeader('X-ZBX-SBOX-HEIGHT'))
					});
			}

			return null;
		},

		calculateReRefresh: function(id) {
			var screen = this.screens[id],
				time = new CDate().getTime();

			if (screen.timestamp + this.responsiveness < time
					&& screen.timestampResponsiveness + this.responsiveness < time) {
				// take of busy flags
				screen.isRefreshing = false;
				screen.isReRefreshRequire = false;

				// refresh anyway
				window.flickerfreeScreen.refresh(id);
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
				if (timeControl.objectList.hasOwnProperty(id)) {
					timeControl.removeObject(id);
				}
			}
		}
	};
}(jQuery));
