<?php
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


/**
 * @var CView $this
 */
?>

<script type="text/javascript">
	window.addEventListener('load', e => {
		/**
		 * On timeselector change only existing list of charts is updated.
		 * Loading indicator is delayed for 3 seconds if refresh was instantiated by page refresh interval.
		 *
		 * Next chart update is scheduled since previous request completed.
		 * In case of pattern select, next list update is scheduled since previous list update request completed and
		 * each of list items (charts) has completed their individual request.
		 */
		var data = JSON.parse('<?= json_encode($data) ?>'),
			$table = $('#charts'),
			$tmpl_row = $('<tr>').append(
				$('<div>', {class: 'flickerfreescreen'}).append(
					$('<div>', {class: '<?= ZBX_STYLE_CENTER ?>', style: 'min-height: 100px;'}).append(
						$('<img>')
					)
				)
			);

		/**
		 * @var {number}  App will only show loading indicator, if loading takes more than DELAY_LOADING seconds.
		 */
		Chart.DELAY_LOADING = 3;

		/**
		 * Represents chart, it can be refreshed.
		 *
		 * @param {object} chart     Chart object prepared in server.
		 * @param {object} timeline  Timeselector data.
		 * @param {jQuery} $tmpl     Template object to be used for new chart $el.
		 * @param {Node}   wrapper   Dom node in respect to which resize must be done.
		 */
		function Chart(chart, timeline, $tmpl, wrapper) {
			this.$el = $tmpl.clone();
			this.$img = this.$el.find('img');

			if (!this.$img.length) {
				throw 'Template element must contain <img /> element.';
			}

			this.chartid = chart.chartid;

			this.timeline = timeline;
			this.dimensions = chart.dimensions;

			this.curl = new Curl(chart.src, false);
			this.curl.setArgument('graphid', chart.chartid);

			this.use_sbox = !!chart.sbox;
			this.wrapper = wrapper;
		}

		/**
		 * Set visual indicator of "loading state".
		 *
		 * @param {number} delay_loading  (optional) Add loader only when request exceeds this many seconds.
		 *
		 * @return {function}  Function that would cancel scheduled indicator or remove existing.
		 */
		Chart.prototype.setLoading = function(delay_loading) {
			delay_loading = delay_loading || 0;

			var timeoutid = setTimeout(function(){
				this.$img.parent().addClass('is-loading')
			}.bind(this), delay_loading  * 1000);

			return function() {
				clearTimeout(timeoutid);
				this.unsetLoading();
			}.bind(this);
		};

		/**
		 * Remove visual indicator of "loading state".
		 */
		Chart.prototype.unsetLoading = function() {
			this.$img.parent().removeClass('is-loading');
		};

		/**
		 * Remove chart.
		 */
		Chart.prototype.destruct = function() {
			this.$img.off();
			this.$el.off();
			this.$el.remove();
		};

		/**
		 * Updates image $.data for gtlc.js to handle selection box.
		 */
		Chart.prototype.refreshSbox = function() {
			if (this.use_sbox) {
				this.$img.data('zbx_sbox', {
					left: this.dimensions.shiftXleft,
					right: this.dimensions.shiftXright,
					top: this.dimensions.shiftYtop,
					height: this.dimensions.graphHeight,

					from_ts: this.timeline.from_ts,
					to_ts: this.timeline.to_ts,
					from: this.timeline.from,
					to: this.timeline.to
				});
			}
		};

		/**
		 * Update chart.
		 *
		 * @param {number} delay_loading  (optional) Add "loading indicator" only when request exceeds delay.
		 *
		 * @return {Promise}
		 */
		Chart.prototype.refresh = function(delay_loading) {
			const width = this.wrapper.clientWidth - (this.dimensions.shiftXright + this.dimensions.shiftXleft + 23);

			this.curl.setArgument('from', this.timeline.from);
			this.curl.setArgument('to', this.timeline.to);
			this.curl.setArgument('height', this.dimensions.graphHeight);
			this.curl.setArgument('width', Math.max(1000, width));
			this.curl.setArgument('profileIdx', 'web.charts.filter');
			this.curl.setArgument('_', (+new Date).toString(34));

			const unsetLoading = this.setLoading(delay_loading);

			const promise = new Promise((resolve, reject) => {
					this.$img.one('error', e => reject());
					this.$img.one('load', e => resolve());
				})
				.catch(_ => this.setLoading())
				.finally(unsetLoading)
				.then(_ => this.refreshSbox());

			this.$img.attr('src', this.curl.getUrl());

			return promise;
		};

		/**
		 * Start or pause timeout based Chart refresh.
		 *
		 * @param {number} seconds  Seconds to wait before reschedule. Zero seconds will pause schedule.
		 * @param {number} delay_loading  (optional) Add "loading indicator" only when request exceeds delay.
		 */
		Chart.prototype.scheduleRefresh = function(seconds, delay_loading) {
			if (this._timeoutid) {
				clearTimeout(this._timeoutid);
			}

			if (!seconds) {
				return;
			}

			this.refresh(delay_loading)
				.finally(_ => {
					this._timeoutid = setTimeout(_ => this.scheduleRefresh(seconds), seconds * 1000);
				});
		};

		/**
		 * @param {jQuery} $el       A container where charts are maintained.
		 * @param {object} timeline  Timecontrol object.
		 * @param {object} config
		 * @param {Node}   wrapper   Dom node in respect to which resize must be done.
		 */
		function ChartList($el, timeline, config, wrapper) {
			this.curl = new Curl('zabbix.php');
			this.curl.setArgument('action', 'charts.view.json');

			this.$el = $el;
			this.timeline = timeline;

			this.charts = [];
			this.charts_map = {};
			this.config = config;
			this.wrapper = wrapper;
		}

		/**
		 * Update currently listed charts.
		 *
		 * @return {Promise}  Resolves once all charts are refreshed.
		 */
		ChartList.prototype.updateCharts = function() {
			const updates = [];

			for (const chart of this.charts) {
				chart.timeline = this.timeline;
				updates.push(chart.refresh());
			}

			return Promise.all(updates);
		};

		/**
		 * Fetches, then sets new list, then updates each chart.
		 *
		 * @param {number} delay_loading  (optional) Add "loading indicator" only when request exceeds delay.
		 *
		 * @return {Promise}  Resolves once list is fetched and each of new charts is fetched.
		 */
		ChartList.prototype.updateListAndCharts = function(delay_loading) {
			return this.fetchList()
				.then(list => {
					this.setCharts(list);
					return this.charts;
				})
				.then(new_charts => {
					const loading_charts = [];
					for (const chart of new_charts) {
						loading_charts.push(chart.refresh(delay_loading));
					}
					return Promise.all(loading_charts)
				});
		};

		/**
		 * Fetches new list of charts.
		 *
		 * @return {Promise}
		 */
		ChartList.prototype.fetchList = function() {
			// Timeselector.
			this.curl.setArgument('from', this.timeline.from);
			this.curl.setArgument('to', this.timeline.to);

			// Filter.
			this.curl.setArgument('filter_hostids', this.config.filter_hostids);
			this.curl.setArgument('filter_graph_patterns', this.config.filter_graph_patterns);

			return fetch(this.curl.getUrl())
				.then(resp => resp.json())
				.then(resp_obj => {
					this.timeline = resp_obj.timeline;
					return resp_obj.charts;
				});
		};

		/**
		 * Update app state according with configuration. Either update individual chart item schedulers or re-fetch
		 * list and update list scheduler.
		 *
		 * @param {number} delay_loading  (optional) Add "loading indicator" only when request exceeds delay.
		 */
		ChartList.prototype.refresh = function(delay_loading) {
			const {refresh_interval, refresh_list} = this.config;

			if (this._timeoutid) {
				clearTimeout(this._timeoutid);
			}

			if (refresh_list) {
				for (const chart of this.charts) {
					chart.scheduleRefresh(0);
				}

				this.updateListAndCharts(delay_loading)
					.finally(_ => {
						if (refresh_interval) {
							this._timeoutid = setTimeout(_ => this.refresh(Chart.DELAY_LOADING),
								refresh_interval * 1000
							);
						}
					})
					.catch(_ => {
						for (const chart of this.charts) {
							chart.setLoading();
						}
					});
			}
			else {
				for (const chart of this.charts) {
					if (refresh_interval) {
						chart.scheduleRefresh(refresh_interval, delay_loading);
					}
					else {
						chart.scheduleRefresh(0);
						chart.refresh(delay_loading);
					}
				}
			}
		};

		/**
		 * Constructs new charts and removes missing, reorders existing charts.
		 *
		 * @param {array} raw_charts
		 */
		ChartList.prototype.setCharts = function(raw_charts) {
			var charts = [];
			var charts_map = {};

			raw_charts.forEach(function(chart) {
				var chart = this.charts_map[chart.chartid]
					? this.charts_map[chart.chartid]
					: new Chart(chart, this.timeline, $tmpl_row, this.wrapper);

				// Existing chart nodes are assured to be in correct order.
				this.$el.append(chart.$el);

				charts_map[chart.chartid] = chart;
				charts.push(chart);
			}.bind(this));

			// Charts that was not in new list are to be deleted.
			this.charts.forEach(function(chart) {
				!charts_map[chart.chartid] && chart.destruct();
			});

			this.charts = charts;
			this.charts_map = charts_map;
		};

		/**
		 * A response to horizontal window resize is to refresh charts (body min width is taken into account).
		 * Chart update is debounced for a half second.
		 */
		ChartList.prototype.onWindowResize = function() {
			var width = this.wrapper.clientWidth;

			if (this._resize_timeoutid) {
				clearTimeout(this._resize_timeoutid);
			}

			if (this._prev_width != width) {
				this._resize_timeoutid = setTimeout(_ => this.updateCharts(), 500);
			}

			this._prev_width = width;
		};

		var app = new ChartList($table, data.timeline, data.config, document.querySelector('.wrapper'));

		window.addEventListener('resize', app.onWindowResize.bind(app));

		app.setCharts(data.charts);
		app.refresh();

		$.subscribe('timeselector.rangeupdate', function(e, data) {
			app.timeline = data;
			app.updateCharts();
		});

		$('#filter_search_type').change(e => {
			if (e.target.value === '<?= ZBX_SEARCH_TYPE_STRICT ?>') {
				$('#ms_graph_patterns').addClass('<?= ZBX_STYLE_DISPLAY_NONE ?>');
				$('#ms_graphids').removeClass('<?= ZBX_STYLE_DISPLAY_NONE ?>');
				$('#filter_graphids_, #filter_graph_patterns_').multiSelect('clean');
			}
			else {
				$('#ms_graph_patterns').removeClass('<?= ZBX_STYLE_DISPLAY_NONE ?>');
				$('#ms_graphids').addClass('<?= ZBX_STYLE_DISPLAY_NONE ?>');
				$('#filter_graphids_, #filter_graph_patterns_').multiSelect('clean');
			}
		});

		$('#view-as').change(() => {
			document.forms['main_filter'].submit();
		});
	});
</script>
