<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
	$(function() {
		/**
		 * On timeselector change only existing list of charts is updated.
		 * If user has page refresh configured:
		 * - In cese of parren, the list itself is updated.
		 * - Else each chart is updated.
		 * Also each page refresh update is counted since last update (regardless if it was initiated by timeselector).
		 * Loading indicator is delayed for 3 seconds if refresh was instantiated by page refresh interval.
		 */
		var data = JSON.parse('<?= json_encode($data) ?>')
			$table = $('#charts'),
			$tmpl_row = $('<tr />').append(
				$('<div />', {class: 'flickerfreescreen'}).append(
					$('<div />', {class: '<?= ZBX_STYLE_CENTER ?>', style: 'min-height: 100px;'}).append(
						$('<img />')
					)
				)
			);

		/**
		 * Represents chart, it can be refreshed.
		 *
		 * @param {object} chart  Chart object prepared in server.
		 * @param {object} timeline  Timeselector data.
		 * @param {jQuery} $tmpl  Template object to be used for new chart $el.
		 */
		function Chart(chart, timeline, $tmpl) {
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

			this.use_sbox = !! chart.sbox;
			this.refresh_interval = 0;
		}

		/**
		 * Set loading
		 *
		 * @param {number} delay_loading  (optional) Add loader only when request exceeds this many seconds.
		 *
		 * @return {function}
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
		 * Unset loading state.
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
		 * @param {number} delay_loading  (optional) Add loader only when request exceeds this many seconds.
		 *
		 * @return {Promise}
		 */
		Chart.prototype.refresh = function(delay_loading) {
			this.curl.setArgument('from', this.timeline.from);
			this.curl.setArgument('to', this.timeline.to);
			this.curl.setArgument('height', this.dimensions.graphHeight);
			var width = document.body.clientWidth - (this.dimensions.shiftXright + this.dimensions.shiftXleft + 23);
			this.curl.setArgument('width', Math.max(1000, width));
			this.curl.setArgument('profileIdx', 'web.graphs.filter');
			this.curl.setArgument('_', (+new Date).toString(34));

			var unsetLoading = this.setLoading(delay_loading);

			var promise = new Promise(function(resolve, reject) {
				this.$img.one('error', reject.bind(this));
				this.$img.one('load', resolve.bind(this));
			}.bind(this));

			promise.finally(unsetLoading.bind(this));

			promise.then(function() {
				this.refreshSbox();
			}.bind(this));

			this.$img.attr('src', this.curl.getUrl());

			return promise;
		};

		/**
		 * @param {jQuery} $el  A container where charts are maintained.
		 * @param {object} timeline  Timecontrol object.
		 * @param {object} config
		 */
		function ChartList($el, timeline, config) {
			this.curl = new Curl('zabbix.php');
			this.curl.setArgument('action', 'charts.view.json');

			this.$el = $el;
			this.timeline = timeline;

			this.charts = [];
			this.charts_map = {};
			this.config = config;
		}

		/**
		 * Update listed charts.
		 *
		 * @param {number} delay_loading  (optional) Add loader only when request exceeds this many seconds.
		 */
		ChartList.prototype.updateCharts = function(delay_loading) {
			this.charts.forEach(function(chart) {
				chart.timeline = this.timeline;
				chart.refresh(delay_loading);
			}.bind(this));
		};

		/**
		 * Update list, then update listed charts.
		 *
		 * @param {number} delay_loading  (optional) Add loader only when request exceeds this many seconds.
		 */
		ChartList.prototype.updateList = function(delay_loading) {
			// Timeselector.
			this.curl.setArgument('from', this.timeline.from);
			this.curl.setArgument('to', this.timeline.to);

			// Filter.
			this.curl.setArgument('filter_hostids', this.config.filter_hostids);
			this.curl.setArgument('filter_graph_patterns', this.config.filter_graph_patterns);

			$.getJSON(this.curl.getUrl())
				.then(function(resp) {
						this.timeline = resp.timeline;
						this.setCharts(resp.charts);
						this.updateCharts(delay_loading);
					}.bind(this))
				.catch(console.error);
		}

		/**
		 * Clears refresh interval.
		 */
		ChartList.prototype.stopRefreshInterval = function() {
			if (this._refreshid) {
				clearInterval(this._refreshid);
			}
		}

		/**
		 * Starts refresh interval according with page refresh config (no initial refresh).
		 */
		ChartList.prototype.startRefreshInterval = function() {
			this.stopRefreshInterval();

			if (this.config.refresh_list && this.config.refresh_interval) {
				this._refreshid = setInterval(this.updateList.bind(this, 3), this.config.refresh_interval * 1000);
			}
			else if (this.config.refresh_interval) {
				this._refreshid = setInterval(this.updateCharts.bind(this, 3), this.config.refresh_interval * 1000);
			}
		}

		/**
		 * Constructs new charts and removes missing, reorders existing charts. Does not call "update" on any of charts.
		 *
		 * @param {array} raw_charts
		 */
		ChartList.prototype.setCharts = function(raw_charts) {
			var charts = [];
			var charts_map = {};

			raw_charts.forEach(function(chart) {
				var chart = this.charts_map[chart.chartid]
					? this.charts_map[chart.chartid]
					: new Chart(chart, this.timeline, $tmpl_row);

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
		}

		/**
		 * A response to horizontal window resize is to refresh charts (body min width is taken into account).
		 * Chart update is debounced for a half second.
		 */
		ChartList.prototype.onWindowResize = function() {
			var width = document.body.clientWidth;
			if (this._resize_timeoutid) {
				clearTimeout(this._resize_timeoutid);
			}

			if (this._prev_width != width) {
				this._resize_timeoutid = setTimeout(this.updateCharts.bind(this), 500);
			}

			this._prev_width = width;
		};

		var app = new ChartList($table, data.timeline, data.config);

		window.addEventListener('resize', app.onWindowResize.bind(app));

		app.startRefreshInterval();
		app.setCharts(data.charts);
		app.updateCharts();

		$.subscribe('timeselector.rangeupdate', function(e, data) {
			app.timeline = data;
			app.updateCharts();
		});
	});
</script>
