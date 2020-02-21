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
		function Chart(chart, timeline, $tmpl) {
			this.$el = $tmpl.clone();
			this.$img = this.$el.find('img');

			if (!this.$img.length) {
				throw 'Template element must contain <img /> element.';
			}

			var domid = 'chart-' + chart.chartid;
			this.chartid = chart.chartid;
			this.$el.attr('id', domid);

			this.setTime(timeline);
			this.setDimensions(chart.dimensions);

			this.curl = new Curl(chart.src, false);
			this.curl.setArgument('graphid', chart.chartid);

			this.use_sbox = !! chart.sbox;
			this.refresh_interval = 0;
		}

		Chart.prototype.setLoading = function() {
			this.$img.parent().addClass('is-loading');
		};

		Chart.prototype.unsetLoading = function() {
			this.$img.parent().removeClass('is-loading');
		};

		Chart.prototype.destruct = function() {
			this.setRefreshInterval(0);
			this.$img.off();
			this.$el.off();
			this.$el.remove();
		};

		/**
		 * @param {number} seconds  If zero given, removes refresh interval.
		 */
		Chart.prototype.setRefreshInterval = function(seconds) {
			// TODO: maintain own loop, to control when img load is finished, and to not to refresh while refresh is in progress.
			this.refresh_interval = seconds;

			if (this.refresh_intervalid) {
				clearInterval(this.refresh_intervalid);
			}

			if (this.refresh_interval <= 0) {
				return;
			}

			this.refresh_intervalid = setInterval(function() {
				this.refresh();
			}.bind(this), this.refresh_interval * 1000);
		};

		Chart.prototype.setTime = function(timeline) {
			this.timeline = timeline;
		};

		Chart.prototype.setDimensions = function(dimensions) {
			this.dimensions = dimensions;
		};

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

		Chart.prototype.refresh = function() {
			this.setLoading();

			this.curl.setArgument('from', this.timeline.from);
			this.curl.setArgument('to', this.timeline.to);
			this.curl.setArgument('height', this.dimensions.graphHeight);
			/* this.curl.setArgument('width', get_bodywidth()); */

			// TODO: this.dimensions.shiftXright in case of pie graph has to be zero. Must be fixed server side.
			// TODO: why 23px from gtlc?
			var width = Math.max(
				get_bodywidth() - (this.dimensions.shiftXright + this.dimensions.shiftXleft + 23),
				1000
			);

			this.curl.setArgument('width', width);
			this.curl.setArgument('profileIdx', 'web.graphs.filter');
			this.curl.setArgument('_', (+new Date).toString(34));

			this.$img.one('error', function() {
				this.unsetLoading();
			}.bind(this));

			this.$img.one('load', function() {
				this.unsetLoading();
				this.refreshSbox();
			}.bind(this));

			// TODO: prefetch src url
			this.$img.attr('src', this.curl.getUrl());
		};

		// Initial list or new list when pattern select fetched refresh.
		var data = JSON.parse('<?= json_encode($data) ?>');

		var $table = $('#charts');
		/* const $tmpl_row = $('<tr />', {class: '<?= ZBX_STYLE_CENTER ?>'}).append($('<img />')); */
		const $tmpl_row = $('<tr />').append(
			// flickerfreescreen class is used only for position relative << TODO: remove
			// so sbox would happen to work, also double nested divs are due to sbox
			$('<div />', {class: 'flickerfreescreen'}).append(
				$('<div />', {class: '<?= ZBX_STYLE_CENTER ?>'}).append(
					$('<img />')
				)
			)
		);

		window.app = {
			timeline: data.timeline,
			charts: [],
			config: data.config,
		};

		function updateList() {
			var curl = new Curl('zabbix.php');
			curl.setArgument('action', 'charts.view.json');
			// TODO: timeselector and filter must be sent

			$.getJSON(curl.getUrl())
				.then(function(res) {
					app.config = res.config;
					app.timeline = res.timeline;

					app.charts.map(function(chart) {
						chart.destruct();
					});

					// TODO: do list merge: update only changes, not all
					app.charts = res.charts
						.map(function(chart) {
							return new Chart(chart, app.timeline, $tmpl_row);
						})
						.map(function(chart) {
							$table.append(chart.$el);
							chart.setRefreshInterval(app.config.refresh_interval);
							chart.refresh();

							return chart;
						});
				});
		}
		app.updateList = updateList;

		if (app.config.refresh_interval && app.config.refresh_list) {
			setInterval(updateList, app.config.refresh_interval);
		}

		// TODO: below
		var w = $(window).width();
		$(window).on('resize', function(e) {
			if ($(window).width() == w) return;
			w = $(window).width();
			app.charts.forEach(function(chart) {
				chart.setTime(app.timeline);
				chart.refresh();
			});
		});

		$.subscribe('timeselector.rangeupdate', function(e, data) {
			app.timeline.from_ts = data.from_ts;
			app.timeline.to_ts = data.to_ts;
			app.timeline.from = data.from;
			app.timeline.to = data.to;

			app.charts.forEach(function(chart) {
				chart.setTime(app.timeline);
				// Reset existing interval timer.
				chart.setRefreshInterval(chart.refresh_interval);
				chart.refresh();
			});
		});

		app.charts = data.charts
			.map(function(chart) {
				return new Chart(chart, data.timeline, $tmpl_row);
			})
			.map(function(chart) {
				$table.append(chart.$el);
				chart.setRefreshInterval(data.config.refresh_interval);
				chart.refresh();

				return chart;
			});
	});
</script>
