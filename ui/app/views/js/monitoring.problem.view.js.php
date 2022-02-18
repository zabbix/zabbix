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
<script>
	const view = {
		refresh_url: null,
		refresh_interval: null,
		filter_defaults: null,
		filter: null,
		global_timerange: null,
		active_filter: null,
		refresh_timer: null,
		filter_counter_fetch: null,

		init({filter_options, refresh_interval, filter_defaults}) {
			this.refresh_interval = refresh_interval;
			this.filter_defaults = filter_defaults;

			const url = new Curl('zabbix.php', false);
			url.setArgument('action', 'problem.view.refresh');
			this.refresh_url = url.getUrl();

			this.initFilter(filter_options);
			this.initAcknowledge();

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

				this.refreshResults();
				this.refreshCounters();

				if (this.active_filter !== this.filter._active_item) {
					this.active_filter = this.filter._active_item;
					chkbxRange.checkObjectAll(chkbxRange.pageGoName, false);
					chkbxRange.clearSelectedOnFilterChange();
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

				fetch(this.refresh_url, {
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
			});
		},

		initAcknowledge() {
			$.subscribe('acknowledge.create', function(event, response) {
				// Clear all selected checkboxes in Monitoring->Problems.
				if (chkbxRange.prefix === 'problem') {
					chkbxRange.checkObjectAll(chkbxRange.pageGoName, false);
					chkbxRange.clearSelectedOnFilterChange();
				}

				window.flickerfreeScreen.refresh('problem');

				clearMessages();
				addMessage(makeMessageBox('good', [], response.message, true, false));
			});

			$(document).on('submit', '#problem_form', function(e) {
				e.preventDefault();

				acknowledgePopUp({eventids: chkbxRange.getSelectedIds()}, this);
			});
		},

		/**
		 * Refresh results table via window.flickerfreeScreen.refresh call.
		 */
		refreshResults() {
			const url = new Curl();
			const screen = window.flickerfreeScreen.screens['problem'];
			const data = $.extend(this.filter_defaults, this.global_timerange, url.getArgumentsObject());

			data.inventory = data.inventory
				? data.inventory.filter(inventory => 'value' in inventory && inventory.value !== '')
				: data.inventory;
			data.tags = data.tags
				? data.tags.filter(tag => !(tag.tag === '' && tag.value === ''))
				: data.tags;
			data.severities = data.severities
				? data.severities.filter((value, key) => value == key)
				: data.severities;

			// Modify filter data of flickerfreeScreen object with id 'problem'.
			if (data.page === null) {
				delete data.page;
			}

			if (data.filter_custom_time) {
				screen.timeline.from = data.from;
				screen.timeline.to = data.to;
			}
			else {
				screen.timeline.from = this.global_timerange.from;
				screen.timeline.to = this.global_timerange.to;
			}

			screen.data.filter = data;
			screen.data.sort = data.sort;
			screen.data.sortorder = data.sortorder;

			// Close all opened hint boxes otherwise flicker free screen will not refresh it content.
			for (let i = overlays_stack.length - 1; i >= 0; i--) {
				const hintbox = overlays_stack.getById(overlays_stack.stack[i]);

				if (hintbox.type === 'hintbox') {
					hintBox.hideHint(hintbox.element, true);
					removeFromOverlaysStack(overlays_stack.stack[i]);
				}
			}

			window.flickerfreeScreen.refresh(screen.id);
		},

		refreshCounters() {
			clearTimeout(this.refresh_timer);

			fetch(this.refresh_url, {
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
					/**
					 * On error restart refresh timer.
					 * If refresh interval is set to 0 (no refresh) schedule initialization request after 5 sec.
					 */
					this.refresh_timer = setTimeout(() => this.refreshCounters(),
						this.refresh_interval > 0 ? this.refresh_interval : 5000
					);
				});
		},

		editHost(hostid) {
			this.openHostPopup({hostid});
		},

		openHostPopup(host_data) {
			clearMessages();

			const original_url = location.href;
			const overlay = PopUp('popup.host.edit', host_data, {
				dialogueid: 'host_edit',
				dialogue_class: 'modal-popup-large'
			});

			overlay.$dialogue[0].addEventListener('dialogue.create', this.events.hostSuccess, {once: true});
			overlay.$dialogue[0].addEventListener('dialogue.update', this.events.hostSuccess, {once: true});
			overlay.$dialogue[0].addEventListener('dialogue.delete', this.events.hostDelete, {once: true});
			overlay.$dialogue[0].addEventListener('overlay.close', () => {
				history.replaceState({}, '', original_url);
			}, {once: true});
		},

		events: {
			hostSuccess(e) {
				const data = e.detail;

				if ('success' in data) {
					const title = data.success.title;
					let messages = [];

					if ('messages' in data.success) {
						messages = data.success.messages;
					}

					addMessage(makeMessageBox('good', messages, title));
				}

				view.refreshResults();
				view.refreshCounters();
			},

			hostDelete(e) {
				const data = e.detail;

				if ('success' in data) {
					const title = data.success.title;
					let messages = [];

					if ('messages' in data.success) {
						messages = data.success.messages;
					}

					addMessage(makeMessageBox('good', messages, title));
				}

				uncheckTableRows('problem');
				view.refreshResults();
				view.refreshCounters();
			}
		}
	};
</script>
