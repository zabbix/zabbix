<?php declare(strict_types = 0);
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
		refresh_data: null,

		refresh_simple_url: null,
		refresh_interval: null,
		refresh_counters: null,

		running: false,
		timeout: null,
		_refresh_message_box: null,
		_popup_message_box: null,
		active_filter: null,
		layout_mode: null,

		checkbox_object: null,

		init({refresh_url, refresh_data, refresh_interval, filter_options, checkbox_object, filter_set, layout_mode}) {
			this.refresh_url = new Curl(refresh_url);
			this.refresh_data = refresh_data;
			this.refresh_interval = refresh_interval;
			this.checkbox_object = checkbox_object;
			this.filter_set = filter_set;
			this.layout_mode = layout_mode;

			const url = new Curl('zabbix.php');
			url.setArgument('action', 'latest.view.refresh');
			this.refresh_simple_url = url.getUrl();

			this.initTabFilter(filter_options);
			this.initExpandableSubfilter();
			this.initListActions();
			this.initPopupListeners();

			if (this.refresh_interval != 0 && this.filter_set) {
				this.running = true;
				this.scheduleRefresh();
			}
		},

		initTabFilter(filter_options) {
			const filter = document.getElementById('monitoring_latest_filter');

			this.refresh_counters = this.createCountersRefresh(1);
			this.filter = new CTabFilter(filter, filter_options);
			this.active_filter = this.filter._active_item;

			if (this.layout_mode == <?= ZBX_LAYOUT_KIOSKMODE ?>) {
				filter.style.display = 'none';
			}

			this.filter.on(TABFILTER_EVENT_URLSET, () => {
				this.reloadPartialAndTabCounters();
				chkbxRange.clearSelectedOnFilterChange();

				if (this.active_filter !== this.filter._active_item) {
					this.active_filter = this.filter._active_item;
					chkbxRange.checkObjectAll(chkbxRange.pageGoName, false);
				}
			});

			// Tags must be activated also using the enter button on keyboard.
			document.addEventListener('keydown', (event) => {
				if (event.which == 13 && event.target.classList.contains('<?= ZBX_STYLE_BTN_TAG ?>')) {
					view.setSubfilter([`subfilter_tags[${encodeURIComponent(event.target.dataset.key)}][]`,
						event.target.dataset.value
					]);
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

		initListActions() {
			let form = this.getCurrentForm().get(0);

			form.querySelector('.js-massexecute-item').addEventListener('click', e => {
				this.executeNow(e.target, {itemids: Object.keys(chkbxRange.getSelectedIds())});
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

					uncheckTableRows('latest');
					this.refresh();
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

		getCurrentForm() {
			return $('form[name=items]');
		},

		getCurrentSubfilter() {
			const latest_data_subfilter = document.getElementById('latest-data-subfilter');

			if (latest_data_subfilter) {
				return latest_data_subfilter;
			}
			else {
				const table = document.createElement('table');

				table.classList.add('list-table', 'tabfilter-subfilter');
				table.id = 'latest-data-subfilter';

				return document.querySelector('.tabfilter-content-container').appendChild(table);
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

			if (this.filter) {
				post_data['subfilters_expanded'] = this.filter.getExpandedSubfilters();
			}

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

		doRefresh(body, subfilter = null) {
			this.getCurrentForm().replaceWith(body);

			const colapsed_tabfilter = document.querySelector('.tabfilter-collapsed');

			if (subfilter !== null) {
				this.getCurrentSubfilter().innerHTML = subfilter;

				if (colapsed_tabfilter !== null) {
					colapsed_tabfilter.classList.remove('display-none');
				}
			}
			else {
				this.getCurrentSubfilter().remove();

				if (colapsed_tabfilter !== null) {
					colapsed_tabfilter.classList.add('display-none');
				}
			}

			chkbxRange.init();
			this.initListActions();
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
			this.doRefresh(response.body, response.subfilter ? response.subfilter : null);

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

			var messages = $(jqXHR.responseText).find('.<?= ZBX_STYLE_MSG_GLOBAL ?>');

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

		executeNow(button, data) {
			if (button instanceof Element) {
				button.classList.add('is-loading');
			}

			let clear_checkboxes = false;
			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'item.execute');
			data[CSRF_TOKEN_NAME] = <?= json_encode(CCsrfTokenHelper::get('item')) ?>;

			fetch(curl.getUrl(), {
				method: 'POST',
				headers: {'Content-Type': 'application/json'},
				body: JSON.stringify(data)
			})
				.then((response) => response.json())
				.then((response) => {
					clearMessages();

					/*
					 * Using postMessageError or postMessageOk would mean that those messages are stored in session
					 * messages and that would mean to reload the page and show them. Also postMessageError would be
					 * displayed right after header is loaded. Meaning message is not inside the page form like that is
					 * in postMessageOk case. Instead show message directly that comes from controller.
					 */
					if ('error' in response) {
						addMessage(makeMessageBox('bad', [response.error.messages], response.error.title, true, true));
					}
					else if('success' in response) {
						clear_checkboxes = true;
						addMessage(makeMessageBox('good', [], response.success.title, true, false));
					}
				})
				.catch(() => {
					const title = <?= json_encode(_('Unexpected server error.')) ?>;
					const message_box = makeMessageBox('bad', [], title)[0];

					clearMessages();
					addMessage(message_box);
				})
				.finally(() => {
					if (!(button instanceof Element)) {
						return;
					}

					if (clear_checkboxes) {
						const uncheckids = Object.keys(chkbxRange.getSelectedIds());
						uncheckTableRows('latest', []);
						chkbxRange.checkObjects(this.checkbox_object, uncheckids, false);
						chkbxRange.update(this.checkbox_object);
					}

					button.classList.remove('is-loading');
					button.blur();
				});
		},

		setSubfilter(field) {
			this.filter.setSubfilter(field[0], field[1]);
		},

		unsetSubfilter(field) {
			this.filter.unsetSubfilter(field[0], field[1]);
		}
	};
</script>
