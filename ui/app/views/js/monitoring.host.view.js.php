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
		host_view_form: null,
		filter: null,
		refresh_url: null,
		refresh_simple_url: null,
		refresh_interval: null,
		refresh_counters: null,
		running: false,
		timeout: null,
		deferred: null,
		applied_filter_groupids: [],
		_refresh_message_box: null,
		_popup_message_box: null,

		init({filter_options, refresh_url, refresh_interval, applied_filter_groupids}) {
			this.refresh_url = new Curl(refresh_url);
			this.refresh_interval = refresh_interval;
			this.applied_filter_groupids = applied_filter_groupids;

			const url = new Curl('zabbix.php');
			url.setArgument('action', 'host.view.refresh');
			this.refresh_simple_url = url.getUrl();

			this.initTabFilter(filter_options);
			this.initEvents();
			this.initPopupListeners();

			this.host_view_form = $('form[name=host_view]');
			this.running = true;
			this.refresh();
		},

		initTabFilter(filter_options) {
			if (!filter_options) {
				return;
			}

			this.refresh_counters = this.createCountersRefresh(1);
			this.filter = new CTabFilter($('#monitoring_hosts_filter')[0], filter_options);
			this.filter.on(TABFILTER_EVENT_URLSET, () => {
				this.reloadPartialAndTabCounters();
			});
		},

		initEvents() {
			document.querySelector('.js-create-host')?.addEventListener('click', () => {
				ZABBIX.PopupManager.open('host.edit', {groupids: this.applied_filter_groupids});
			});
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

					if ('success' in data.submit) {
						this._addPopupMessage(
							makeMessageBox('good', data.submit.success.messages, data.submit.success.title)
						);
					}

					this.reloadPartialAndTabCounters();
				}
			});
		},

		createCountersRefresh(timeout) {
			if (this.refresh_counters) {
				clearTimeout(this.refresh_counters);
				this.refresh_counters = null;
			}

			return setTimeout(() => this.getFiltersCounters(), timeout);
		},

		getFiltersCounters() {
			return $.post(this.refresh_simple_url, {
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

		reloadPartialAndTabCounters() {
			this.refresh_url = new Curl('');

			this.unscheduleRefresh();
			this.refresh();

			// Filter is not present in Kiosk mode.
			if (this.filter) {
				const filter_item = this.filter._active_item;

				if (this.filter._active_item.hasCounter()) {
					$.post(this.refresh_simple_url, {
						filter_counters: 1,
						counter_index: filter_item._index
					}).done((json) => {
						if (json.filter_counters) {
							filter_item.updateCounter(json.filter_counters.pop());
						}
					});
				}
			}
		},

		_addRefreshMessage(messages) {
			this._removeRefreshMessage();

			this._refresh_message_box = $($.parseHTML(messages));
			addMessage(this._refresh_message_box);
		},

		_removeRefreshMessage() {
			if (this._refresh_message_box !== null) {
				this._refresh_message_box.remove();
				this._refresh_message_box = null;
			}
		},

		_addPopupMessage(message_box) {
			this._removePopupMessage();

			this._popup_message_box = message_box;
			addMessage(this._popup_message_box);
		},

		_removePopupMessage() {
			if (this._popup_message_box !== null) {
				this._popup_message_box.remove();
				this._popup_message_box = null;
			}
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

		setLoading() {
			this.host_view_form.addClass('is-loading is-loading-fadein delayed-15s');
		},

		clearLoading() {
			this.host_view_form.removeClass('is-loading is-loading-fadein delayed-15s');
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
			this._removeRefreshMessage();
			this.host_view_form.replaceWith(response.body);
			this.host_view_form = $('form[name=host_view]');

			if ('groupids' in response) {
				this.applied_filter_groupids = response.groupids;
			}

			if ('messages' in response) {
				this._addRefreshMessage(response.messages);
			}
		},

		onDataFail(jqXHR) {
			// Ignore failures caused by page unload.
			if (jqXHR.status == 0) {
				return;
			}

			this.clearLoading();

			const messages = $(jqXHR.responseText).find('.<?= ZBX_STYLE_MSG_GLOBAL ?>');

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
