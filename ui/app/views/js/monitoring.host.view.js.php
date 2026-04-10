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


/**
 * @var CView $this
 */

?>
<script>
	const view = {
		layout_mode: null,
		refresh_interval: null,
		refresh_interval_id: null,
		filter_defaults: {},
		filter: null,
		active_filter: null,
		applied_filter_groupids: [],
		datatable: null,
		_refresh_message_box: null,
		_popup_message_box: null,

		init({
			layout_mode,
			filter_defaults,
			filter_options,
			refresh_interval,
			applied_filter_groupids,
			filter,
			page,
			sort_field,
			sort_order,
			storage_idx,
			user_configs
		}) {
			this.layout_mode = layout_mode;
			this.refresh_interval = refresh_interval;
			this.filter_defaults = filter_defaults;
			this.applied_filter_groupids = applied_filter_groupids;

			this.initTabFilter(filter_options);
			this.initEvents();
			this.initPopupListeners();
			this.initDataTable({filter, page, sort_field, sort_order, storage_idx, user_configs});

			if (this.refresh_interval != 0) {
				this.scheduleRefresh();
			}
		},

		initTabFilter(filter_options) {
			/** @type {HTMLElement} */
			const filter = document.getElementById('monitoring_hosts_filter');

			this.filter = new CTabFilter(filter, filter_options);
			this.active_filter = this.filter._active_item;

			this.filter.on(TABFILTER_EVENT_URLSET, () => {
				if (this.active_filter !== this.filter._active_item) {
					this.active_filter = this.filter._active_item;

					this.datatable.setTabFilterItem(this.active_filter);
				}

				this.scheduleRefresh();
				this.refresh();
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

					this.refresh();
				}
			});
		},

		initDataTable({filter, page, sort_field, sort_order, storage_idx, user_configs}) {
			const data_provider_url = new URL('zabbix.php', location.href);
			data_provider_url.searchParams.set('action', 'host.view.data');

			const data_provider = new CDefaultDataProvider(data_provider_url.toString());

			this.datatable = new CDataTable(document.getElementById('hosts'), data_provider)
				.setColumns([
					new CDataTableColumn('name', <?= json_encode(_('Name')); ?>)
						.setFields(['hostid', 'name', 'status', 'maintenance', 'maintenanceid', 'maintenance_type',
							'maintenance_status'])
						.setRenderer('name')
						.setSortable(true)
						.setTogglable(false),
					new CDataTableColumn('interface', <?= json_encode(_('Interface')); ?>)
						.setFields(['interface']),
					new CDataTableColumn('availability', <?= json_encode(_('Availability')); ?>)
						.setFields(['availability', 'active_available'])
						.setRenderer('availability'),
					new CDataTableColumnTags('tags', <?= json_encode(_('Tags')); ?>),
					new CDataTableColumnTagValue('tagvalue', <?= json_encode(_('Tag value')); ?>),
					new CDataTableColumn('status', <?= json_encode(_('Status')); ?>)
						.setFields(['status'])
						.setRenderer('status'),
					new CDataTableColumn('latest_data', <?= json_encode(_('Latest data')); ?>)
						.setFields(['hostid', 'items_count'])
						.setRenderer('latest_data'),
					new CDataTableColumn('problems', <?= json_encode(_('Problems')); ?>)
						.setColumnOptions({
							show_suppressed: false
						})
						.setOptionsPopupHandler('problems')
						.setFields(['problems']),
					new CDataTableColumn('graphs', <?= json_encode(_('Graphs')); ?>)
						.setFields(['hostid', 'graphs'])
						.setRenderer('graphs'),
					new CDataTableColumn('dashboards', <?= json_encode(_('Dashboards')); ?>)
						.setFields(['hostid', 'dashboards'])
						.setRenderer('dashboards'),
					new CDataTableColumn('web', <?= json_encode(_('Web')); ?>)
						.setFields(['hostid', 'httpTests'])
						.setRenderer('web')
				])
				.setPage(page)
				.setFilter(filter)
				.setSortField(sort_field)
				.setSortOrder(sort_order)
				.setStorageIdx(storage_idx)
				.setTabFilterItem(this.active_filter)
				.setStickyHeader(true)
				.setStickyFooter(true)
				.setRenderer('name', ({column_data, cell_inner}) => {
					const [hostid, name, status, maintenance] = column_data;

					const url = new URL('zabbix.php', location.href);
					url.searchParams.set('action', 'popup');
					url.searchParams.set('popup', 'host.edit');
					url.searchParams.set('hostid', hostid);

					const name_link = document.createElement('a');
					name_link.classList.add(ZBX_STYLE_LINK_ACTION);
					name_link.setAttribute('data-menu-popup', JSON.stringify({
						type: 'host',
						data: {hostid}
					}));
					name_link.setAttribute('aria-expanded', 'false');
					name_link.setAttribute('aria-haspopup', 'true');
					name_link.setAttribute('role', 'button');
					name_link.setAttribute('href', 'javascript:void(0);');
					name_link.innerText = name;

					cell_inner.appendChild(name_link);

					if (maintenance && status == HOST_STATUS_MONITORED) {
						if (maintenance.status == HOST_MAINTENANCE_STATUS_ON) {
							let hint = `${maintenance.name} [${maintenance.type
								? <?= json_encode(_('Maintenance without data collection')); ?>
								: <?= json_encode(_('Maintenance with data collection')); ?>}]`;

							if (maintenance.description != '') {
								hint += "\n" + maintenance.description;
							}

							const maintenance_icon = document.createElement('button');
							maintenance_icon.classList.add(ZBX_STYLE_BTN_ICON, ZBX_ICON_WRENCH_ALT_SMALL,
								ZBX_STYLE_COLOR_WARNING, ZBX_STYLE_NO_INDENT);
							maintenance_icon.setAttribute('type', 'button');
							maintenance_icon.setAttribute('role', 'button');
							maintenance_icon.setAttribute('data-hintbox-contents', hint);
							maintenance_icon.setAttribute('data-hintbox', '1');

							cell_inner.appendChild(maintenance_icon);
						}
						else {
							const maintenance_icon = document.createElement('button');
							maintenance_icon.classList.add(ZBX_STYLE_BTN_ICON, ZBX_ICON_WRENCH_ALT_SMALL,
								ZBX_STYLE_COLOR_WARNING, ZBX_STYLE_NO_INDENT);
							maintenance_icon.setAttribute('type', 'button');
							maintenance_icon.setAttribute('role', 'button');
							maintenance_icon.setAttribute('data-hintbox-contents', <?= json_encode(_('Inaccessible maintenance')); ?>);
							maintenance_icon.setAttribute('data-hintbox', '1');

							cell_inner.appendChild(maintenance_icon);
						}
					}
				})
				.setRenderer('availability', ({column_data, cell_inner}) => {
					const [availability] = column_data;

					cell_inner.innerHTML = availability;
				})
				.setRenderer('status', ({column_data, cell_inner}) => {
					const [status] = column_data;

					const indicator = document.createElement('span');

					if (status == HOST_STATUS_MONITORED) {
						indicator.classList.add(ZBX_STYLE_GREEN);
						indicator.innerText = <?= json_encode(_('Enabled')); ?>;
					}
					else {
						indicator.classList.add(ZBX_STYLE_RED);
						indicator.innerText = <?= json_encode(_('Disabled')); ?>;
					}

					cell_inner.appendChild(indicator);
				})
				.setRenderer('latest_data', ({column_data, cell_inner}) => {
					const [hostid, items_count] = column_data;

					this.datatable.getData().then(response => {
						const {allowed_ui_latest_data} = response;

						if (allowed_ui_latest_data) {
							const url = new URL('zabbix.php', location.href);
							url.searchParams.set('action', 'latest.view');
							url.searchParams.set('hostids[0]', hostid);
							url.searchParams.set('filter_set', '1');

							const latest_data_link = document.createElement('a');
							latest_data_link.setAttribute('href', url.toString());
							latest_data_link.innerText = <?= json_encode(_('Latest data')); ?>;

							cell_inner.appendChild(latest_data_link);
						}
						else {
							const latest_data_link = document.createElement('span');
							latest_data_link.classList.add(ZBX_STYLE_DISABLED);
							latest_data_link.innerText = <?= json_encode(_('Latest data')); ?>;

							cell_inner.appendChild(latest_data_link);
						}

						if (items_count > 0) {
							const count = document.createElement('sup');
							count.innerText = items_count;

							cell_inner.innerHTML += ' ';
							cell_inner.appendChild(count);
						}
					});
				})
				.setRenderer('graphs', ({column_data, cell_inner}) => {
					const [hostid, graphs] = column_data;

					if (graphs > 0) {
						const url = new URL('zabbix.php', location.href);
						url.searchParams.set('action', 'charts.view');
						url.searchParams.set('filter_hostids[0]', hostid);
						url.searchParams.set('filter_set', '1');

						const graphs_link = document.createElement('a');
						graphs_link.setAttribute('href', url.toString());
						graphs_link.innerText = <?= json_encode(_('Graphs')); ?>;

						cell_inner.appendChild(graphs_link);

						const count = document.createElement('sup');
						count.innerText = graphs;

						cell_inner.innerHTML += ' ';
						cell_inner.appendChild(count);
					}
				})
				.setRenderer('dashboards', ({column_data, cell_inner}) => {
					const [hostid, dashboards] = column_data;

					if (dashboards > 0) {
						const url = new URL('zabbix.php', location.href);
						url.searchParams.set('action', 'host.dashboard.view');
						url.searchParams.set('hostid', hostid);

						const dashboards_link = document.createElement('a');
						dashboards_link.setAttribute('href', url.toString());
						dashboards_link.innerText = <?= json_encode(_('Dashboards')); ?>;

						cell_inner.appendChild(dashboards_link);

						const count = document.createElement('sup');
						count.innerText = dashboards;

						cell_inner.innerHTML += ' ';
						cell_inner.appendChild(count);
					}
				})
				.setRenderer('web', ({column_data, cell_inner}) => {
					const [hostid, httpTests] = column_data;

					if (httpTests > 0) {
						const url = new URL('zabbix.php', location.href);
						url.searchParams.set('action', 'web.view');
						url.searchParams.set('filter_set', '1');
						url.searchParams.set('filter_hostids[0]', hostid);

						const web_link = document.createElement('a');
						web_link.setAttribute('href', url.toString());
						web_link.innerText = <?= json_encode(_('Web')); ?>;

						cell_inner.appendChild(web_link);

						const count = document.createElement('sup');
						count.innerText = httpTests;

						cell_inner.innerHTML += ' ';
						cell_inner.appendChild(count);
					}
				})
				.setOptionsHandler('problems', 'CDataTableOptionsPopupMonitoringHostProblems')
				.on(CMessageHelper.EVENT_MESSAGE, event => {
					event.stopPropagation();

					const {type, title, messages} = event.detail;

					clearMessages();
					addMessage(makeMessageBox(type, messages, title));
				})
				.on(CPager.EVENT_SELECT, () => this.scheduleRefresh())
				.on(CPager.EVENT_STATE_CHANGE, event => {
					const {page} = event.detail;

					new CState().setParams({page});
				})
				.on(CDataTable.EVENT_RENDER, () => {
					this.datatable.getData().then(response => this.refreshCounters(response));
				})
				.on(CDataTable.EVENT_DATA_SORT, () => this.scheduleRefresh())
				.on(CDataTable.EVENT_OPTIONS_POPUP_OPEN, () => this.unscheduleRefresh())
				.on(CDataTable.EVENT_OPTIONS_POPUP_CLOSE, () => this.scheduleRefresh())
				.on(CDataTable.EVENT_COLUMN_RESIZE_START, () => this.unscheduleRefresh())
				.on(CDataTable.EVENT_COLUMN_RESIZE_END, () => this.scheduleRefresh())
				.init(user_configs);
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
			if (isUserInteracting()) {
				return;
			}

			const search_params = new URLSearchParams(location.search.substring(1));
			const current_filter = searchParamsToObject(search_params);
			const filter = {...this.filter_defaults, ...current_filter};

			this.datatable.setFilter(filter)
				.dispatchEvent(CDataTable.EVENT_INIT, {
					check_changes: false,
					force_load: true,
					onSuccess: response => this.onDataDone(response)
				});
		},

		refreshCounters(response) {
			if (this.layout_mode == <?= ZBX_LAYOUT_KIOSKMODE ?>) {
				return;
			}

			if ('filter_counters' in response) {
				this.filter.updateCounters(response.filter_counters);
			}
		},

		onDataDone(response) {
			this._removeRefreshMessage();

			if ('groupids' in response) {
				this.applied_filter_groupids = response.groupids;
			}

			this.refreshCounters(response);

			if ('messages' in response) {
				this._addRefreshMessage(response.messages);
			}
		},

		scheduleRefresh() {
			this.unscheduleRefresh();
			this.refresh_interval_id = setInterval(() => this.refresh(), this.refresh_interval);
		},

		unscheduleRefresh() {
			clearInterval(this.refresh_interval_id);
			this.refresh_interval_id = null;
		},
	};
</script>
