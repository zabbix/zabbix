<?php declare(strict_types = 0);
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
		refresh_interval: null,
		refresh_interval_id: null,
		filter_defaults: {},
		filter: null,
		active_filter: null,
		layout_mode: null,
		checkbox_object: null,
		datatable: null,
		_refresh_message_box: null,
		_popup_message_box: null,

		init({
			refresh_interval,
			filter_defaults,
			filter_options,
			checkbox_object,
			filter_set,
			layout_mode,
			filter,
			page,
			sort_field,
			sort_order,
			storage_idx,
			user_configs
		}) {
			this.refresh_interval = refresh_interval;
			this.filter_defaults = filter_defaults;
			this.checkbox_object = checkbox_object;
			this.filter_set = filter_set;
			this.layout_mode = layout_mode;

			this.initTabFilter(filter_options);
			this.initExpandableSubfilter();
			this.initListActions();
			this.initPopupListeners();
			this.initDataTable({filter, page, sort_field, sort_order, storage_idx, user_configs});

			if (this.refresh_interval != 0 && this.filter_set) {
				this.scheduleRefresh();
			}
		},

		initTabFilter(filter_options) {
			const filter = document.getElementById('monitoring_latest_filter');

			this.filter = new CTabFilter(filter, filter_options);
			this.active_filter = this.filter._active_item;

			if (this.layout_mode == <?= ZBX_LAYOUT_KIOSKMODE ?>) {
				filter.style.display = 'none';
			}

			this.filter.on(TABFILTER_EVENT_URLSET, () => {
				chkbxRange.clearSelectedOnFilterChange();

				if (this.active_filter !== this.filter._active_item) {
					this.active_filter = this.filter._active_item;
					chkbxRange.checkObjectAll(chkbxRange.pageGoName, false);

					this.datatable.setTabFilterItem(this.active_filter);
				}

				this.refresh();
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

		initDataTable({filter, page, sort_field, sort_order, storage_idx, user_configs}) {
			const data_provider_url = new URL('zabbix.php', location.href);
			data_provider_url.searchParams.set('action', 'latest.view.data');

			const data_provider = new CDefaultDataProvider(data_provider_url.toString());

			CDataTableColumnTags.object_type = ZBX_TAG_OBJECT_ITEM;

			this.datatable = new CDataTable(document.getElementById('latest'), data_provider)
				.setColumns([
					new CDataTableColumn('host', <?= json_encode(_('Host')); ?>)
						.setFields(['host', 'maintenance', 'maintenanceid', 'maintenance_type', 'maintenance_status'])
						.setRenderer('host')
						.setSortable(true),
					new CDataTableColumn('name', <?= json_encode(_('Name')); ?>)
						.setContextPopupData({
							show_item_key: filter.show_item_key == 1
						})
						.setContextPopupHandler('name')
						.setFields(['itemid', 'description_expanded', 'name', 'key_expanded'])
						.setRenderer('name')
						.setSortable(true)
						.setTogglable(false),
					new CDataTableColumn('interval', <?= json_encode(_('Interval')); ?>)
						.setFields(['interval'])
						.setVisible(false),
					new CDataTableColumn('history', <?= json_encode(_('History')); ?>)
						.setFields(['history'])
						.setVisible(false),
					new CDataTableColumn('trends', <?= json_encode(_('Trends')); ?>)
						.setFields(['trends'])
						.setVisible(false),
					new CDataTableColumn('type', <?= json_encode(_('Type')); ?>)
						.setFields(['type', 'state'])
						.setRenderer('type')
						.setVisible(false),
					new CDataTableColumn('last_check', <?= json_encode(_('Last check')); ?>)
						.setFields(['last_check'])
						.setWidth('max-content'),
					new CDataTableColumn('last_value', <?= json_encode(_('Last value')); ?>)
						.setFields(['last_value']),
					new CDataTableColumn('change', <?= json_encode(_('Change')); ?>)
						.setFields(['change']),
					new CDataTableColumnTags('tags', <?= json_encode(_('Tags')); ?>)
						.setRenderer('tags'),
					new CDataTableColumn('actions', '')
						.setFields(['itemid', 'is_graph', 'keep_history', 'keep_trends'])
						.setRenderer('actions')
						.setShowInCustomizeTable(false)
						.setWidth('max-content'),
					new CDataTableColumn('info', <?= json_encode(_('Info')); ?>)
						.setFields(['item_icons'])
						.setWidth('max-content')
				])
				.setPage(page)
				.setFilter(filter)
				.setSelectable('items', 'itemids', ['itemid', 'data_actions'])
				.setSortField(sort_field)
				.setSortOrder(sort_order)
				.setStorageIdx(storage_idx)
				.setTabFilterItem(this.active_filter)
				.setRenderer('host', ({column_data, cell_inner}) => {
					const [host, maintenance] = column_data;

					const host_link = document.createElement('a');
					host_link.classList.add(ZBX_STYLE_LINK_ACTION);
					host_link.setAttribute('data-menu-popup', JSON.stringify({
						type: 'host',
						data: {
							hostid: host.hostid
						}
					}));
					host_link.setAttribute('aria-expanded', 'false');
					host_link.setAttribute('aria-haspopup', 'true');
					host_link.setAttribute('role', 'button');
					host_link.setAttribute('href', 'javascript:void(0);');
					host_link.innerText = host.name;

					cell_inner.appendChild(host_link);

					if (maintenance && host.status == HOST_STATUS_MONITORED) {
						if (host.maintenance_status == HOST_MAINTENANCE_STATUS_ON) {
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
				.setRenderer('name', ({column_config, column_data, cell_inner}) => {
					const [itemid, description_expanded, name, key_expanded] = column_data;

					const url_params = objectToSearchParams({action: 'latest.view', context: 'host'});

					const action_container = document.createElement('div');
					action_container.classList.add(ZBX_STYLE_ACTION_CONTAINER);

					const name_link = document.createElement('a');
					name_link.classList.add(ZBX_STYLE_LINK_ACTION);
					name_link.setAttribute('data-menu-popup', JSON.stringify({
						type: 'item',
						data: {
							itemid,
							backurl: `zabbix.php?${url_params}`,
						},
						context: 'host'
					}));
					name_link.setAttribute('aria-expanded', 'false');
					name_link.setAttribute('aria-haspopup', 'true');
					name_link.setAttribute('role', 'button');
					name_link.setAttribute('href', 'javascript:void(0);');
					name_link.innerText = name;

					action_container.appendChild(name_link);

					if (description_expanded) {
						const description_icon = document.createElement('button');
						description_icon.classList.add(ZBX_STYLE_BTN_ICON, ZBX_ICON_ALERT_WITH_CONTENT);
						description_icon.setAttribute('type', 'button');
						description_icon.setAttribute('role', 'button');
						description_icon.setAttribute('data-content', '?');
						description_icon.setAttribute('data-hintbox-contents', description_expanded);
						description_icon.setAttribute('data-hintbox', '1');

						action_container.innerHTML += ' ';
						action_container.appendChild(description_icon);
					}

					cell_inner.appendChild(action_container);

					const {show_item_key} = column_config.getContextPopupData();

					if (show_item_key) {
						const item_key = document.createElement('span');
						item_key.classList.add(ZBX_STYLE_GREEN);
						item_key.innerText = key_expanded;

						cell_inner.appendChild(item_key);
					}
				})
				.setRenderer('type', ({column_data, cell_inner}) => {
					const [type, state] = column_data;

					if (state == ITEM_STATE_NOTSUPPORTED) {
						const type_container = document.createElement('span');
						type_container.innerText = type;
						type_container.classList.add(ZBX_STYLE_GREY);

						cell_inner.appendChild(type_container);
					}
					else {
						cell_inner.innerText = type;
					}
				})
				.setRenderer('actions', ({column_data, cell_inner}) => {
					const [itemid, is_graph, keep_history, keep_trends] = column_data;

					if (!keep_history && !keep_trends) {
						return;
					}

					const data_link = document.createElement('a');
					if (is_graph) {
						const search_params = objectToSearchParams({action: 'showgraph', itemids: [itemid]});

						data_link.setAttribute('href', `history.php?${search_params}`);
						data_link.innerText = <?= json_encode(_('Graph')); ?>;
					}
					else {
						const search_params = objectToSearchParams({action: 'showvalues', 'itemids[]': itemid});

						data_link.setAttribute('href', `history.php?${search_params}`);
						data_link.innerText = <?= json_encode(_('History')); ?>;
					}

					cell_inner.appendChild(data_link);
				})
				.setRenderer('info', ({column_data, cell_inner}) => {
					const [item_icons] = column_data;

					cell_inner.appendChild(item_icons);
				})
				.setContextPopupHandler('name', 'CDataTableContextPopupMonitoringLatestName')
				.on(CMessageHelper.EVENT_MESSAGE, event => {
					event.stopPropagation();

					const {type, title, messages} = event.detail;

					if (type == CMessageHelper.TYPE_CLEAR) {
						clearMessages();
					}
					else {
						addMessage(makeMessageBox(type, messages, title));
					}
				})
				.on(CPager.EVENT_STATE_CHANGE, event => {
					const {page} = event.detail;

					new CState().setParams({page});
				})
				.on(CDataTable.EVENT_INIT, () => this.refreshCounters())
				.on(CDataTable.EVENT_CONTEXT_POPUP_OPEN, () => this.unscheduleRefresh())
				.on(CDataTable.EVENT_CONTEXT_POPUP_CLOSE, () => this.scheduleRefresh())
				.init(user_configs);
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
			const filter_params = this.active_filter.getFilterParamsObject();

			this.datatable.setFilter({...this.filter_defaults, ...filter_params})
				.dispatchEvent(CDataTable.EVENT_INIT, {
					onSuccess: response => this.onDataDone(response)
				});
		},

		refreshCounters() {
			// Filter is not present in Kiosk mode.
			if (this.layout_mode == <?= ZBX_LAYOUT_KIOSKMODE ?>) {
				return;
			}

			const url = new URL('zabbix.php', location.href);
			url.searchParams.set('action', 'latest.view.refresh');

			fetch(url.toString(), {
				method: 'POST',
				body: objectToSearchParams({filter_counters: 1})
			})
				.then(response => response.json())
				.then(response => {
					if ('filter_counters' in response) {
						this.filter.updateCounters(response.filter_counters);
					}
				})
				.catch(error => {
					CMessageHelper.error(this.datatable.getElement(), [error.message], error.name);
				});
		},

		doRefresh(subfilter = null) {
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

		onDataDone(response) {
			this._removeRefreshMessage();

			this.doRefresh(response.subfilter || null);

			if ('messages' in response) {
				this._addRefreshMessage(response.messages);
			}

			this.initExpandableSubfilter();
		},

		scheduleRefresh() {
			this.unscheduleRefresh();
			this.refresh_interval_id = setInterval(() => this.refresh(), this.refresh_interval);
		},

		unscheduleRefresh() {
			clearInterval(this.refresh_interval_id);
			this.refresh_interval_id = null;
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
					/*
					 * Using postMessageError or postMessageOk would mean that those messages are stored in session
					 * messages and that would mean to reload the page and show them. Also postMessageError would be
					 * displayed right after header is loaded. Meaning message is not inside the page form like that is
					 * in postMessageOk case. Instead show message directly that comes from controller.
					 */
					if ('error' in response) {
						CMessageHelper.error(this.datatable.getElement(), [response.error.messages],
							response.error.title, {show_close_box: true, show_details: true});
					}
					else if ('success' in response) {
						clear_checkboxes = true;

						CMessageHelper.success(this.datatable.getElement(), [], response.success.title,
							{show_close_box: true, show_details: false});
					}
				})
				.catch(() => {
					const title = <?= json_encode(_('Unexpected server error.')) ?>;

					CMessageHelper.error(this.datatable.getElement(), [], title);
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
