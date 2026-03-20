<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/
?>


<script>
	const view = {
		_app: null,
		_filter_form: null,
		_data: null,
		_resize_observer: null,
		_container: null,
		_init_filter_values: null,

		init({filter_form_name, data, timeline}) {
			this._filter_form = document.querySelector(`[name="${filter_form_name}"]`);
			this._container = document.querySelector('main');
			this._data = data;

			this.initCharts();
			this.initEvents();

			timeControl.refreshPage = false;

			timeControl.addObject('charts_view', timeline, {
				id: 'timeline_1',
				domid: 'charts_view',
				loadSBox: 0,
				loadImage: 0,
				dynamic: 0
			});

			timeControl.processObjects();
			this._init_filter_values = this.getInitFilterValues();
		},

		initEvents() {
			this._filter_form.addEventListener('click', (e) => {
				const target = e.target;

				if (target.matches('.link-action') && target.closest('.subfilter') !== null) {
					const filters = {...this._init_filter_values, subfilter_set: 1};
					const subfilter = target.closest('.subfilter');

					const key_parts = [...target.getAttribute('data-name').matchAll(/[^\[\]]+|\[\]/g)];

					const update_filter = (current_filter, key_parts, i, value, remove) => {
						const key_name = key_parts[i][0];

						if (i == key_parts.length - 1) {
							if (remove) {
								current_filter.forEach((value, idx) => {
									if (value == target.getAttribute('data-value')) {
										delete current_filter[idx];
									}
								});
							}
							else {
								current_filter.push(value);
							}

							return current_filter;
						}
						else {
							if (!(key_name in current_filter)) {
								current_filter[key_name] = [];
							}

							update_filter(current_filter[key_name], key_parts, i + 1, value, remove);

							if (i == key_parts.length - 2) {
								current_filter[key_name] = Object.values(current_filter[key_name]);
							}
							else {
								current_filter[key_name] = Object.assign({}, current_filter[key_name]);
							}

							if (Object.values(current_filter[key_name]).length == 0) {
								delete current_filter[key_name];
							}
						}
					};

					update_filter(filters, key_parts, 0, target.getAttribute('data-value'),
						subfilter.matches('.subfilter-enabled')
					);

					this.withTimelineRangeFilters(filters);

					location.href = zabbixUrl(filters);
				}
			});

			this._filter_form.addEventListener('submit', e => {
				e.preventDefault();

				const filters = {...getFormFields(e.target), filter_set: 1};

				Object.keys(filters).forEach(key => {
					if (key.startsWith('subfilter_')) {
						delete filters[key];
					}
				});

				this.withTimelineRangeFilters(filters);

				location.href = zabbixUrl(filters);
			});
		},

		initCharts() {
			this._$tmpl_row = $('<tr>').append(
				$('<div>', {class: 'flickerfreescreen'}).append(
					$('<div>', {class: '<?= ZBX_STYLE_CENTER ?>', style: 'min-height: 300px;'}).append(
						$('<img>')
					)
				)
			);

			this._app = new ChartList( $('#charts'), this._data.timeline, this._data.config, this._container);
			this._app.setCharts(this._data.charts);
			this._app.refresh();

			this._resize_observer = new ResizeObserver(this._app.onResize.bind(this._app));
			this._resize_observer.observe(this._container);

			$.subscribe('timeselector.rangeupdate', (e, data) => {
				this._app.timeline = data;
				this._app.updateCharts();
			});
		},

		getInitFilterValues() {
			const filter_values = Object.keys(this._data.config).reduce((filtered, filter_key) => {
				if (filter_key.includes('filter_')) {
					filtered[filter_key] = this._data.config[filter_key];
				}

				return filtered;
			}, []);

			filter_values.action = 'charts.view';
			filter_values.from = this._data.timeline.from;
			filter_values.to = this._data.timeline.to;

			const clear_keys = ['filter_set', 'filter_rst', 'page', 'subfilter_set'];

			clear_keys.forEach(key => delete filter_values[key]);

			return filter_values;
		},

		replacePaging(paging) {
			document.querySelector('.<?= ZBX_STYLE_TABLE_PAGING ?>').outerHTML = paging;

			document.querySelectorAll('.<?= ZBX_STYLE_TABLE_PAGING ?> .paging-btn-container a').forEach(link => {
				link.addEventListener('click', e => {
					e.preventDefault();

					const search_params = new URLSearchParams(e.currentTarget.href);

					const filters = {
						...this._init_filter_values,
						subfilter_set: 1,
						page: search_params.get('page') ?? 1
					};

					this.withTimelineRangeFilters(filters);

					location.href = zabbixUrl(filters);
				});
			});
		},

		replaceSubfilter(subfilter) {
			if (document.getElementById('subfilter') !== null) {
				document.getElementById('subfilter').outerHTML = subfilter;
			}
		},

		withTimelineRangeFilters(filters) {
			const {from, to} = timeControl.objectList['charts_view'].timeline ?? {};

			if (from && to) {
				Object.assign(filters, {from, to});
			}
		}
	};
</script>

<script type="text/javascript">

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

		this.chartid = chart.chartid;

		this.timeline = timeline;
		this.dimensions = chart.dimensions;

		this.curl = new Curl(chart.src);

		if ('graphid' in chart) {
			this.curl.setArgument('graphid', chart.graphid);
		}
		else {
			this.curl.setArgument('itemids', [chart.itemid]);
		}

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
		const timeoutid = setTimeout(function(){
			this.$img.parent().addClass('is-loading')
		}.bind(this), delay_loading * 1000);

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
		let width = this.wrapper.clientWidth - 20;

		if (this.use_sbox) {
			width -= this.dimensions.shiftXright + this.dimensions.shiftXleft + 1;
		}

		this.curl.setArgument('from', this.timeline.from);
		this.curl.setArgument('to', this.timeline.to);
		this.curl.setArgument('height', this.dimensions.graphHeight);
		this.curl.setArgument('width', Math.max(1000, width));
		this.curl.setArgument('profileIdx', 'web.charts.filter');
		this.curl.setArgument('resolve_macros', 1);
		this.curl.setArgument('_', (+new Date).toString(34));

		const unsetLoading = this.setLoading(delay_loading);

		const promise = new Promise((resolve, reject) => {
				this.$img.one('error', () => reject());
				this.$img.one('load', () => resolve());
			})
			.catch(() => this.setLoading(0))
			.finally(unsetLoading)
			.then(() => this.refreshSbox());

		this.$img.attr('src', this.curl.getUrl());

		return promise;
	};

	/**
	 * @param {jQuery} $el       A container where charts are maintained.
	 * @param {object} timeline  Time control object.
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

	ChartList.prototype.updateSubfilters = function(subfilter_tagnames, subfilter_tags) {
		this.config.subfilter_tagnames = subfilter_tagnames;
		this.config.subfilter_tags = subfilter_tags;
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
		this.curl.setArgument('filter_name', this.config.filter_name);
		this.curl.setArgument('filter_show', this.config.filter_show);

		this.curl.setArgument('subfilter_tagnames', this.config.subfilter_tagnames);
		this.curl.setArgument('subfilter_tags', this.config.subfilter_tags);

		this.curl.setArgument('page', this.config.page);

		return fetch(this.curl.getUrl())
			.then((response) => response.json())
			.then((response) => {
				this.timeline = response.timeline;
				view.replaceSubfilter(response.subfilter);
				view.replacePaging(response.paging);

				return response.charts;
			});
	};

	/**
	 * Update app state according with configuration. Either update individual chart item schedulers or re-fetch
	 * list and update list scheduler.
	 *
	 * @param {number} delay_loading  (optional) Add "loading indicator" only when request exceeds delay.
	 */
	ChartList.prototype.refresh = function(delay_loading) {
		const {refresh_interval} = this.config;

		if (this._timeoutid) {
			clearTimeout(this._timeoutid);
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
	};

	/**
	 * Constructs new charts and removes missing, reorders existing charts.
	 *
	 * @param {array} raw_charts
	 */
	ChartList.prototype.setCharts = function(raw_charts) {
		const charts = [];
		const charts_map = {};

		raw_charts.forEach(function(chart) {
			chart = this.charts_map[chart.chartid]
				? this.charts_map[chart.chartid]
				: new Chart(chart, this.timeline, view._$tmpl_row, view._container);

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
	ChartList.prototype.onResize = function() {
		const width = this.wrapper.clientWidth;

		if (this._prev_width === undefined) {
			this._prev_width = width;

			return;
		}

		clearTimeout(this._resize_timeoutid);

		if (this._prev_width != width) {
			this._resize_timeoutid = setTimeout(() => {
				this._prev_width = width;
				this.updateCharts();
			}, 500);
		}
	};
</script>
