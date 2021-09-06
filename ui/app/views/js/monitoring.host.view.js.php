<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
		host_view_form: null,
		filter: null,
		refresh_url: null,
		refresh_interval: null,
		refresh_counters: null,
		running: false,
		timeout: null,
		deferred: null,

		init({filter_options, refresh_url, refresh_interval}) {
			this.refresh_url = refresh_url;
			this.refresh_interval = refresh_interval;

			this.initTabFilter(filter_options);

			this.host_view_form = $('form[name=host_view]');
			this.running = true;
			this.refresh();

			host_popup.init();
		},

		initTabFilter(filter_options) {
			if (filter_options) {
				this.refresh_counters = this.createCountersRefresh(1);
				this.filter = new CTabFilter($('#monitoring_hosts_filter')[0], filter_options);
				this.filter.on(TABFILTER_EVENT_URLSET, () => {
					const url = new Curl('', false);
					url.setArgument('action', 'host.view.refresh');
					this.refresh_url = url.getUrl();

					this.unscheduleRefresh();
					this.refresh();

					const filter_item = this.filter._active_item;

					if (this.filter._active_item.hasCounter()) {
						$.post('zabbix.php', {
							action: 'host.view.refresh',
							filter_counters: 1,
							counter_index: filter_item._index
						}).done((json) => {
							if (json.filter_counters) {
								filter_item.updateCounter(json.filter_counters.pop());
							}
						});
					}
				});
			}
		},

		createCountersRefresh(timeout) {
			if (this.refresh_counters) {
				clearTimeout(this.refresh_counters);
				this.refresh_counters = null;
			}

			return setTimeout(() => this.getFiltersCounters(), timeout);
		},

		getFiltersCounters() {
			return $.post('zabbix.php', {
				action: 'host.view.refresh',
				filter_counters: 1
			})
			.done((json) => {
				if (json.filter_counters) {
					this.filter.updateCounters(json.filter_counters);
				}
			})
			.always(() => {
				if (this.refresh_interval > 0) {
					this.refresh_counters = this.createCountersRefresh(this.refresh_interval);
				}
			});
		},

		addMessages(messages) {
			$('.wrapper main').before(messages);
		},

		removeMessages() {
			$('.wrapper .msg-bad').remove();
		},

		refresh() {
			this.setLoading();

			this.deferred = $.getJSON(this.refresh_url);

			return this.bindDataEvents(this.deferred);
		},

		setLoading() {
			this.host_view_form.addClass('is-loading is-loading-fadein delayed-15s');
		},

		clearLoading() {
			this.host_view_form.removeClass('is-loading is-loading-fadein delayed-15s');
		},

		/**
		 * Popuplates data-attribute used to prefill new host.
		 *
		 * @param {array} groupids Filtered host group IDs.
		 */
		updateCreateHostButton(groupids) {
			$('.'+host_popup.ZBX_STYLE_ZABBIX_HOST_POPUPCREATE).attr('data-hostgroups', JSON.stringify(groupids));
		},

		bindDataEvents(deferred) {
			deferred
				.done((response) => {
					this.onDataDone.call(this, response);
				})
				.fail((jqXHR) => {
					this.onDataFail.call(this, jqXHR);
				})
				.always(this.onDataAlways.bind(this));

			return deferred;
		},

		onDataDone(response) {
			this.clearLoading();
			this.removeMessages();
			this.host_view_form.replaceWith(response.body);
			this.host_view_form = $('form[name=host_view]');

			if ('groupids' in response) {
				this.updateCreateHostButton(response.groupids);
			}

			if ('messages' in response) {
				this.addMessages(response.messages);
			}
		},

		onDataFail(jqXHR) {
			// Ignore failures caused by page unload.
			if (jqXHR.status == 0) {
				return;
			}

			this.clearLoading();

			const messages = $(jqXHR.responseText).find('.msg-global');

			if (messages.length) {
				this.host_view_form.html(messages);
			}
			else {
				this.host_view_form.html(jqXHR.responseText);
			}
		},

		onDataAlways() {
			if (this.running) {
				this.deferred = null;
				this.scheduleRefresh();
			}
		},

		scheduleRefresh() {
			this.unscheduleRefresh();

			if (this.refresh_interval > 0) {
				this.timeout = setTimeout((function () {
					this.timeout = null;
					this.refresh();
				}).bind(this), this.refresh_interval);
			}
		},

		unscheduleRefresh() {
			if (this.timeout !== null) {
				clearTimeout(this.timeout);
				this.timeout = null;
			}

			if (this.deferred) {
				this.deferred.abort();
			}
		}
	};
</script>
