<?php declare(strict_types = 0);
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
		refresh_data: null,

		refresh_simple_url: null,
		refresh_interval: null,
		refresh_counters: null,

		running: false,
		timeout: null,
		_refresh_message_box: null,
		_popup_message_box: null,
		active_filter: null,

		init({refresh_url, refresh_data, refresh_interval, filter_options}) {
			this.refresh_url = new Curl(refresh_url, false);
			this.refresh_data = refresh_data;
			this.refresh_interval = refresh_interval;

			const url = new Curl('zabbix.php', false);
			url.setArgument('action', 'latest.view.refresh');
			this.refresh_simple_url = url.getUrl();

			this.initTabFilter(filter_options);
			this.initExpandableSubfilter();

			if (this.refresh_interval != 0) {
				this.running = true;
				this.scheduleRefresh();
			}
		},

		initTabFilter(filter_options) {
			if (!filter_options) {
				return;
			}

			this.refresh_counters = this.createCountersRefresh(1);
			this.filter = new CTabFilter(document.getElementById('monitoring_latest_filter'), filter_options);
			this.active_filter = this.filter._active_item;

			this.filter.on(TABFILTER_EVENT_URLSET, () => {
				this.reloadPartialAndTabCounters();

				if (this.active_filter !== this.filter._active_item) {
					this.active_filter = this.filter._active_item;
					chkbxRange.checkObjectAll(chkbxRange.pageGoName, false);
					chkbxRange.clearSelectedOnFilterChange();
				}
			});

			document.addEventListener('click', (event) => {
				if (event.target.classList.contains('<?= ZBX_STYLE_BTN_TAG ?>')) {
					view.setSubfilter(JSON.parse(event.target.dataset.subfilterTag));
				}
			});

			// Tags must be activated also using the enter button on keyboard.
			document.addEventListener('keydown', (event) => {
				if (event.which == 13 && event.target.classList.contains('<?= ZBX_STYLE_BTN_TAG ?>')) {
					view.setSubfilter(JSON.parse(event.target.dataset.subfilterTag));
				}
			});
		},

		initExpandableSubfilter() {
			document.querySelectorAll('.expandable-subfilter').forEach((element) => {
				const subfilter = new CExpandableSubfilter(element);
				subfilter.on(EXPANDABLE_SUBFILTER_EVENT_EXPAND, (e) => {
					this.filter.setExpandedSubfilters(e.detail.name);
				});
			});

			const expand_tags = document.getElementById('expand_tag_values');
			if (expand_tags !== null) {
				expand_tags.addEventListener('click', () => {
					document.querySelectorAll('.subfilter-option-grid.display-none').forEach((element) => {
						element.classList.remove('display-none');
					});

					this.filter.setExpandedSubfilters(expand_tags.dataset['name']);
					expand_tags.remove();
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
			this.refresh_url = new Curl('', false);

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

		getCurrentForm() {
			return $('form[name=items]');
		},

		getCurrentSubfilter() {
			return $('#latest-data-subfilter');
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
					if (key === 'subfilter_tags') {
						post_data[key] = {...params[key]};
					}
					else {
						post_data[key] = (typeof params[key] === 'object')
							? [...params[key]].filter(i => i)
							: params[key];
					}

					return post_data;
				}, {});

			post_data['subfilters_expanded'] = this.filter.getExpandedSubfilters();

			var deferred = $.ajax({
				url: this.refresh_simple_url,
				data: post_data,
				type: 'post',
				dataType: 'json'
			});

			return this.bindDataEvents(deferred);
		},

		setLoading() {
			this.getCurrentForm().addClass('is-loading is-loading-fadein delayed-15s');
		},

		clearLoading() {
			this.getCurrentForm().removeClass('is-loading is-loading-fadein delayed-15s');
		},

		doRefresh(body, subfilter) {
			this.getCurrentForm().replaceWith(body);
			this.getCurrentSubfilter().replaceWith(subfilter);
			chkbxRange.init();
		},

		bindDataEvents(deferred) {
			var that = this;

			deferred
				.done(function(response) {
					that.onDataDone.call(that, response);
				})
				.fail(function(jqXHR) {
					that.onDataFail.call(that, jqXHR);
				})
				.always(this.onDataAlways.bind(this));

			return deferred;
		},

		onDataDone(response) {
			this.clearLoading();
			this._removeRefreshMessage();
			this.doRefresh(response.body, response.subfilter);

			if ('messages' in response) {
				this._addRefreshMessage(response.messages);
			}

			this.initExpandableSubfilter();
		},

		onDataFail(jqXHR) {
			// Ignore failures caused by page unload.
			if (jqXHR.status == 0) {
				return;
			}

			this.clearLoading();

			var messages = $(jqXHR.responseText).find('.msg-global');

			if (messages.length) {
				this.getCurrentForm().html(messages);
			}
			else {
				this.getCurrentForm().html(jqXHR.responseText);
			}
		},

		onDataAlways() {
			if (this.running) {
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
		},

		editHost(hostid) {
			const host_data = {hostid};

			this.openHostPopup(host_data);
		},

		openHostPopup(host_data) {
			this._removePopupMessage();

			const original_url = location.href;
			const overlay = PopUp('popup.host.edit', host_data, {
				dialogueid: 'host_edit',
				dialogue_class: 'modal-popup-large'
			});

			this.unscheduleRefresh();

			overlay.$dialogue[0].addEventListener('dialogue.create', this.events.hostSuccess, {once: true});
			overlay.$dialogue[0].addEventListener('dialogue.update', this.events.hostSuccess, {once: true});
			overlay.$dialogue[0].addEventListener('dialogue.delete', this.events.hostDelete, {once: true});
			overlay.$dialogue[0].addEventListener('overlay.close', () => {
				history.replaceState({}, '', original_url);
				this.scheduleRefresh();
			}, {once: true});
		},

		setSubfilter(field) {
			this.filter.setSubfilter(field[0], field[1]);
		},

		unsetSubfilter(field) {
			this.filter.unsetSubfilter(field[0], field[1]);
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

					view._addPopupMessage(makeMessageBox('good', messages, title));
				}

				view.refresh();
			},

			hostDelete(e) {
				const data = e.detail;

				if ('success' in data) {
					const title = data.success.title;
					let messages = [];

					if ('messages' in data.success) {
						messages = data.success.messages;
					}

					view._addPopupMessage(makeMessageBox('good', messages, title));
				}

				uncheckTableRows('');
				view.refresh();
			}
		}
	};
</script>
