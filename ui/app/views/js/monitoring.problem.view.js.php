<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
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


/**
 * @var CView $this
 */

?>
<script>
	const view = {
		refresh_url: null,
		refresh_simple_url: null,
		refresh_interval: null,
		filter_defaults: null,
		filter: null,
		global_timerange: null,
		active_filter: null,
		refresh_timer: null,
		filter_counter_fetch: null,
		running: false,
		timeout: null,
		deferred: null,
		opened_eventids: [],

		init({filter_options, refresh_url, refresh_interval, filter_defaults}) {
			this.refresh_url = new Curl(refresh_url);
			this.refresh_interval = refresh_interval;
			this.filter_defaults = filter_defaults;

			const url = new Curl('zabbix.php');
			url.setArgument('action', 'problem.view.refresh');
			this.refresh_simple_url = url.getUrl();

			this.initFilter(filter_options);
			$.subscribe('event.rank_change', () => view.refreshNow());

			this.initExpandables();
			this.initEvents();
			this.initPopupListeners();

			if (this.refresh_interval != 0) {
				this.running = true;
				this.scheduleRefresh();
			}

			$(document).on({
				mouseenter: function() {
					if ($(this)[0].scrollWidth > $(this)[0].offsetWidth) {
						$(this).attr({title: $(this).text()});
					}
				},
				mouseleave: function() {
					if ($(this).is('[title]')) {
						$(this).removeAttr('title');
					}
				}
			}, 'table.<?= ZBX_STYLE_COMPACT_VIEW ?> a.<?= ZBX_STYLE_LINK_ACTION ?>');

			// Activate blinking.
			jqBlink.blink();
		},

		initFilter(filter_options) {
			if (!filter_options) {
				return;
			}

			this.filter = new CTabFilter($('#monitoring_problem_filter')[0], filter_options);
			this.active_filter = this.filter._active_item;
			this.global_timerange = {
				from: filter_options.timeselector.from,
				to: filter_options.timeselector.to
			};

			/**
			 * Update on filter changes.
			 */
			this.filter.on(TABFILTER_EVENT_URLSET, () => {
				const url = new Curl();
				url.setArgument('action', 'problem.view.csv');
				$('#export_csv').attr('data-url', url.getUrl());

				this.refresh_url.setArgument('page', '1');

				this.refreshResults();
				this.refreshCounters();
				chkbxRange.clearSelectedOnFilterChange();

				if (this.active_filter !== this.filter._active_item) {
					this.active_filter = this.filter._active_item;
					chkbxRange.checkObjectAll(chkbxRange.pageGoName, false);
				}
			});

			/**
			 * Update filter item counter when filter settings updated.
			 */
			this.filter.on(TABFILTER_EVENT_UPDATE, (e) => {
				if (!this.filter._active_item.hasCounter() || e.detail.filter_property !== 'properties') {
					return;
				}

				if (this.filter_counter_fetch) {
					this.filter_counter_fetch.abort();
				}

				this.filter_counter_fetch = new AbortController();
				const filter_item = this.filter._active_item;

				fetch(this.refresh_simple_url, {
					method: 'POST',
					signal: this.filter_counter_fetch.signal,
					body: new URLSearchParams({filter_counters: 1, counter_index: filter_item._index})
				})
					.then(response => response.json())
					.then(response => {
						filter_item.updateCounter(response.filter_counters.pop());
					});
			});

			this.refreshCounters();

			// Keep timeselector changes in global_timerange.
			$.subscribe('timeselector.rangeupdate', (e, data) => {
				if (data.idx === '<?= CControllerProblem::FILTER_IDX ?>') {
					this.global_timerange.from = data.from;
					this.global_timerange.to = data.to;
				}

				this.refresh_url.setArgument('page', 1);
				this.refreshResults();
			});
		},

		initExpandables() {
			const table = this.getCurrentResultsTable();
			const expandable_buttons = table.querySelectorAll("button[data-action='show_symptoms']");

			expandable_buttons.forEach((btn, idx, array) => {
				['click','keydown'].forEach((type) => {
					btn.addEventListener(type, (e) => {
						if (e.type === 'click' || e.which === 13) {
							this.showSymptoms(btn, idx, array);
						}
					});
				});

				// Check if cause events were opened. If so, after (not full) refresh open them again.
				if (this.opened_eventids.includes(btn.dataset.eventid)) {
					const rows = table.querySelectorAll("tr[data-cause-eventid='" + btn.dataset.eventid + "']");

					[...rows].forEach((row) => row.classList.remove('hidden'));

					btn.classList.remove(ZBX_ICON_CHEVRON_DOWN, ZBX_STYLE_COLLAPSED);
					btn.classList.add(ZBX_ICON_CHEVRON_UP);
					btn.title = <?= json_encode(_('Collapse')) ?>;
				}
			});

			// Fix last row border depending if it is opened or closed.
			const rows = table.querySelectorAll('.problem-row');

			if (rows.length > 0) {
				const row = [...rows].pop();
				const btn = row.querySelector('button[data-action="show_symptoms"]');
				const is_collapsed = btn !== null && btn.classList.contains(ZBX_STYLE_COLLAPSED);

				[...row.children].forEach((td) => td.style.borderBottomStyle = is_collapsed ? 'hidden' : 'solid');
			}
		},

		initEvents() {
			document.addEventListener('click', e => {
				if (e.target.classList.contains('js-massupdate-problem')) {
					this.massupdate({eventids: Object.keys(chkbxRange.getSelectedIds())});
				}
			});
		},

		massupdate({eventids}) {
			ZABBIX.PopupManager.open('acknowledge.edit', {eventids}, {supports_standalone: true});
		},

		initPopupListeners() {
			ZABBIX.EventHub.subscribe({
				require: {
					context: CPopupManager.EVENT_CONTEXT,
					event: CPopupManagerEvent.EVENT_OPEN
				},
				callback: () => this.unscheduleRefresh()
			});

			ZABBIX.EventHub.subscribe({
				require: {
					context: CPopupManager.EVENT_CONTEXT,
					event: CPopupManagerEvent.EVENT_CANCEL
				},
				callback: () => this.scheduleRefresh()
			});

			ZABBIX.EventHub.subscribe({
				require: {
					context: CPopupManager.EVENT_CONTEXT,
					event: CPopupManagerEvent.EVENT_SUBMIT
				},
				callback: ({data, event}) => {
					event.preventDefault();

					clearMessages();

					if ('success' in data.submit) {
						addMessage(makeMessageBox('good', data.submit.success.messages, data.submit.success.title));
					}

					chkbxRange.checkObjectAll('eventids', false);
					chkbxRange.update('eventids');

					this.refreshResults();
					this.refreshCounters();
				}
			});
		},

		showSymptoms(btn, idx, array) {
			// Prevent multiple clicking by first disabling button.
			btn.disabled = true;

			const table = this.getCurrentResultsTable();
			let rows = table.querySelectorAll("tr[data-cause-eventid='" + btn.dataset.eventid + "']");

			// Show symptom rows for current cause. Sliding animations are not supported on table rows.
			if (rows[0].classList.contains('hidden')) {
				btn.classList.remove(ZBX_ICON_CHEVRON_DOWN, ZBX_STYLE_COLLAPSED);
				btn.classList.add(ZBX_ICON_CHEVRON_UP);
				btn.title = <?= json_encode(_('Collapse')) ?>;

				this.opened_eventids.push(btn.dataset.eventid);

				[...rows].forEach((row) => row.classList.remove('hidden'));
			}
			else {
				btn.classList.remove(ZBX_ICON_CHEVRON_UP);
				btn.classList.add(ZBX_ICON_CHEVRON_DOWN, ZBX_STYLE_COLLAPSED);
				btn.title = <?= json_encode(_('Expand')) ?>;

				this.opened_eventids = this.opened_eventids.filter((id) => id !== btn.dataset.eventid);

				[...rows].forEach((row) => row.classList.add('hidden'));
			}

			// Fix last row border depending if it is opened or closed.
			rows = table.querySelectorAll('.problem-row');

			if (rows.length > 0) {
				const row = [...rows].pop();
				const is_collapsed = btn.classList.contains(ZBX_STYLE_COLLAPSED);

				[...row.children].forEach((td) => td.style.borderBottomStyle = is_collapsed ? 'hidden' : 'solid');
			}

			// When complete enable button again.
			btn.disabled = false;
		},

		getCurrentResultsTable() {
			return document.getElementById('flickerfreescreen_problem');
		},

		getCurrentDebugBlock() {
			return document.querySelector('.wrapper > .debug-output');
		},

		setLoading() {
			this.getCurrentResultsTable().classList.add('is-loading', 'is-loading-fadein', 'delayed-15s');
		},

		clearLoading() {
			this.getCurrentResultsTable().classList.remove('is-loading', 'is-loading-fadein', 'delayed-15s');
		},

		refreshBody(body) {
			this.getCurrentResultsTable().replaceWith(
				new DOMParser().parseFromString(body, 'text/html').body.firstElementChild
			);
			chkbxRange.init();
			this.initExpandables();
		},

		refreshDebug(debug) {
			this.getCurrentDebugBlock().replaceWith(
				new DOMParser().parseFromString(debug, 'text/html').body.firstElementChild
			);
		},

		refresh() {
			this.setLoading();

			const params = this.refresh_url.getArgumentsObject();
			const exclude = ['action', 'filter_src', 'filter_show_counter', 'filter_custom_time', 'filter_name'];
			const post_data = Object.keys(params)
				.filter(key => !exclude.includes(key))
				.reduce((post_data, key) => {
					post_data[key] = (typeof params[key] === 'object')
						? [...params[key]].filter(i => i)
						: params[key];
					return post_data;
				}, {});

			this.deferred = $.ajax({
				url: this.refresh_simple_url,
				data: post_data,
				type: 'post',
				dataType: 'json'
			});

			return this.bindDataEvents(this.deferred);
		},

		refreshNow() {
			this.unscheduleRefresh();
			this.refresh();
		},

		scheduleRefresh() {
			this.unscheduleRefresh();
			this.timeout = setTimeout((function () {
				this.timeout = null;
				this.refresh();
			}).bind(this), this.refresh_interval);
		},

		unscheduleRefresh() {
			if (this.timeout !== null) {
				clearTimeout(this.timeout);
				this.timeout = null;
			}
		},

		bindDataEvents(deferred) {
			const that = this;

			deferred
				.done(function(response) {
					that.onDataDone.call(that, response);
				})
				.always(this.onDataAlways.bind(this));

			return deferred;
		},

		onDataAlways() {
			if (this.running) {
				this.deferred = null;
				this.scheduleRefresh();
			}
		},

		onDataDone(response) {
			this.clearLoading();
			this.refreshBody(response.body);

			if ('messages' in response) {
				clearMessages();
				addMessage(makeMessageBox('good', [], response.messages, true, false));
			}

			('debug' in response) && this.refreshDebug(response.debug);
		},

		/**
		 * Refresh results table.
		 */
		refreshResults() {
			const url = new Curl();
			const refresh_url = new Curl('zabbix.php');
			const data = Object.assign({}, this.filter_defaults, this.global_timerange, url.getArgumentsObject());

			// Modify filter data.
			data.inventory = data.inventory
				? data.inventory.filter(inventory => 'value' in inventory && inventory.value !== '')
				: data.inventory;
			data.tags = data.tags
				? data.tags.filter(tag => !(tag.tag === '' && tag.value === ''))
				: data.tags;
			data.severities = data.severities
				? data.severities.filter((value, key) => value == key)
				: data.severities;
			data.page = this.refresh_url.getArgument('page') ?? 1;

			if (!data.filter_custom_time) {
				data.from = this.global_timerange.from;
				data.to = this.global_timerange.to;
			}

			Object.entries(data).forEach(([key, value]) => {
				if (['filter_show_counter', 'filter_custom_time', 'action'].indexOf(key) !== -1) {
					return;
				}

				refresh_url.setArgument(key, value);
			});

			refresh_url.setArgument('action', 'problem.view.refresh');
			this.refresh_url = refresh_url;
			this.refreshNow();
		},

		refreshCounters() {
			clearTimeout(this.refresh_timer);

			fetch(this.refresh_simple_url, {
				method: 'POST',
				body: new URLSearchParams({filter_counters: 1})
			})
				.then(response => response.json())
				.then(response => {
					if (response.filter_counters) {
						this.filter.updateCounters(response.filter_counters);
					}

					if (this.refresh_interval > 0) {
						this.refresh_timer = setTimeout(() => this.refreshCounters(), this.refresh_interval);
					}
				})
				.catch(() => {
					/*
					 * On error restart refresh timer.
					 * If refresh interval is set to 0 (no refresh) schedule initialization request after 5 sec.
					 */
					this.refresh_timer = setTimeout(() => this.refreshCounters(),
						this.refresh_interval > 0 ? this.refresh_interval : 5000
					);
				});
		}
	};
</script>
