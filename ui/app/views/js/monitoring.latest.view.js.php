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
	const view = new class {
		#layout_mode = null;
		#refresh_interval = null;
		#refresh_interval_id = null;
		#filter_defaults = {};
		#filter = null;
		#filter_set = null;
		#active_filter = null;
		#checkbox_object = null;
		#datatable = null;
		#csrf_token = null;
		#refresh_message_box = null;
		#popup_message_box = null;

		init({
			checkbox_object,
			csrf_token,
			default_sort_field,
			default_sort_order,
			filter,
			filter_defaults,
			filter_options,
			filter_set,
			layout_mode,
			page,
			refresh_interval,
			sort_field,
			sort_order,
			storage_idx,
			user_configs
		}) {
			this.#layout_mode = layout_mode;
			this.#refresh_interval = refresh_interval;
			this.#filter_defaults = filter_defaults;
			this.#checkbox_object = checkbox_object;
			this.#filter_set = filter_set;
			this.#csrf_token = csrf_token;

			this.#initTabFilter(filter_options);
			this.#initExpandableSubfilter();
			this.#initListActions();
			this.#initPopupListeners();
			this.#initDataTable({filter, page, default_sort_field, default_sort_order, sort_field, sort_order,
				storage_idx, user_configs});

			this.#scheduleRefresh();
		}

		#initTabFilter(filter_options) {
			/** @type {HTMLElement} */
			const filter = document.getElementById('monitoring_latest_filter');

			this.#filter = new CTabFilter(filter, filter_options);
			this.#active_filter = this.#filter._active_item;

			this.#filter.on(TABFILTER_EVENT_URLSET, () => {
				chkbxRange.clearSelectedOnFilterChange();

				if (this.#active_filter !== this.#filter._active_item) {
					this.#active_filter = this.#filter._active_item;
					chkbxRange.checkObjectAll(chkbxRange.pageGoName, false);

					this.#datatable.setTabFilterItem(this.#active_filter);
				}

				this.#scheduleRefresh();
				this.#refresh();
			});

			// Tags must be activated also using the enter button on keyboard.
			document.addEventListener('keydown', e => {
				if (e.which == 13 && e.target.classList.contains('<?= ZBX_STYLE_BTN_TAG ?>')) {
					this.setSubfilter([`subfilter_tags[${encodeURIComponent(e.target.dataset.key)}][]`,
						e.target.dataset.value
					]);
				}
			});
		}

		#initExpandableSubfilter() {
			document.querySelectorAll('.expandable-subfilter').forEach((element) => {
				const subfilter = new CExpandableSubfilter(element);
				subfilter.on(EXPANDABLE_SUBFILTER_EVENT_EXPAND, (e) => {
					this.#filter.setExpandedSubfilters(e.detail.name);
				});
			});

			const expand_tags = document.getElementById('expand_tag_values');
			if (expand_tags !== null) {
				expand_tags.addEventListener('click', () => {
					document.querySelectorAll('.subfilter-option-grid.display-none').forEach((element) => {
						element.classList.remove('display-none');
					});

					this.#filter.setExpandedSubfilters(expand_tags.dataset['name']);
					expand_tags.remove();
				});
			}
		}

		#initListActions() {
			let form = this.#getCurrentForm().get(0);

			form.querySelector('.js-massexecute-item').addEventListener('click', e => {
				this.executeNow(e.target, {itemids: Object.keys(chkbxRange.getSelectedIds())});
			});
		}

		#initPopupListeners() {
			ZABBIX.EventHub.subscribe({
				require: {
					context: CPopupManager.EVENT_CONTEXT,
					event: CPopupManagerEvent.EVENT_OPEN
				},
				callback: () => this.#unscheduleRefresh()
			});

			ZABBIX.EventHub.subscribe({
				require: {
					context: CPopupManager.EVENT_CONTEXT,
					event: CPopupManagerEvent.EVENT_CANCEL
				},
				callback: () => this.#scheduleRefresh()
			});

			ZABBIX.EventHub.subscribe({
				require: {
					context: CPopupManager.EVENT_CONTEXT,
					event: CPopupManagerEvent.EVENT_SUBMIT
				},
				callback: ({data, event}) => {
					event.preventDefault();

					if ('success' in data.submit) {
						this.#addPopupMessage(
							makeMessageBox('good', data.submit.success.messages, data.submit.success.title)
						);
					}

					uncheckTableRows('latest');
					this.#refresh();
				}
			});
		}

		#initDataTable({filter, page, default_sort_field, default_sort_order, sort_field, sort_order, storage_idx,
				user_configs}) {

			const data_provider_url = new URL('zabbix.php', location.href);
			data_provider_url.searchParams.set('action', 'latest.view.data');
			data_provider_url.searchParams.set(CSRF_TOKEN_NAME, this.#csrf_token);

			const data_provider = new CDefaultDataProvider(data_provider_url.toString());

			this.#datatable = new CDataTable(document.getElementById('datatable-latest'), data_provider)
				.setColumns([
					new CDataTableColumn('host', <?= json_encode(_('Host')); ?>)
						.setFields(['host', 'maintenance', 'maintenanceid', 'maintenance_type', 'maintenance_status'])
						.setRenderer('host')
						.setSortable(true),
					new CDataTableColumn('name', <?= json_encode(_('Name')); ?>)
						.setColumnOptions({
							show_item_key: filter.show_item_key == 1
						})
						.setOptionsPopupHandler('name')
						.setFields(['itemid', 'description_expanded', 'name', 'key_expanded'])
						.setRenderer('name')
						.setSortable(true)
						.setTogglable(false)
						.setWidth('auto'),
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
						.setFields(['last_check']),
					new CDataTableColumn('last_value', <?= json_encode(_('Last value')); ?>)
						.setFields(['last_value'])
						.setWidth('20%'),
					new CDataTableColumn('change', <?= json_encode(_('Change')); ?>)
						.setFields(['change']),
					new CDataTableColumnTags('tags', <?= json_encode(_('Tags')); ?>)
						.setColumnOptions({
							object_type: ZBX_TAG_OBJECT_ITEM
						}),
					new CDataTableColumnTagValue('tagvalue', <?= json_encode(_('Tag value')); ?>),
					new CDataTableColumn('actions', <?= json_encode(_('Actions')); ?>)
						.setFields(['itemid', 'is_graph', 'show_link'])
						.setRenderer('actions'),
					new CDataTableColumn('info', <?= json_encode(_('Info')); ?>)
						.setFields(['item_icons'])
				])
				.setPage(page)
				.setFilter(filter)
				.setSelectable('items', 'itemids', ['itemid', 'data_actions'])
				.setDefaultSortField(default_sort_field)
				.setDefaultSortOrder(default_sort_order)
				.setSortField(sort_field)
				.setSortOrder(sort_order)
				.setStorageIdx(storage_idx)
				.setTabFilterItem(this.#active_filter)
				.setStickyHeader(true)
				.setStickyFooter(true)
				.setCellRenderer('host', ({cell_data, cell_inner}) => {
					const [host, maintenance] = cell_data;

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
					host_link.textContent = host.name;

					cell_inner.appendChild(host_link);

					if (maintenance && host.status == HOST_STATUS_MONITORED) {
						const maintenance_icon = document.createElement('button');
						maintenance_icon.classList.add(ZBX_STYLE_BTN_ICON, ZBX_ICON_WRENCH_ALT_SMALL,
							ZBX_STYLE_COLOR_WARNING, ZBX_STYLE_NO_INDENT);
						maintenance_icon.setAttribute('type', 'button');
						maintenance_icon.setAttribute('role', 'button');

						if (host.maintenance_status == HOST_MAINTENANCE_STATUS_ON) {
							let hint = `${escapeHtml(maintenance.name)} [${maintenance.type
								? <?= json_encode(_('Maintenance without data collection')); ?>
								: <?= json_encode(_('Maintenance with data collection')); ?>}]`;

							if (maintenance.description != '') {
								hint += "\n" + escapeHtml(maintenance.description);
							}

							maintenance_icon.setAttribute('data-hintbox-html', hint);
						}
						else {
							maintenance_icon.setAttribute('data-hintbox-html',
								<?= json_encode(_('Inaccessible maintenance')); ?>);
						}

						maintenance_icon.setAttribute('data-hintbox', '1');
						maintenance_icon.setAttribute('data-hintbox-class', ZBX_STYLE_HINTBOX_WRAP);
						maintenance_icon.setAttribute('data-hintbox-static', '1');
						maintenance_icon.setAttribute('aria-expanded', 'false');

						cell_inner.appendChild(maintenance_icon);
					}
				})
				.setCellRenderer('name', ({column, cell_data, cell_inner}) => {
					const [itemid, description_expanded, name, key_expanded] = cell_data;

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
					name_link.textContent = name;

					action_container.appendChild(name_link);

					if (description_expanded) {
						const description_icon = document.createElement('button');
						description_icon.classList.add(ZBX_STYLE_BTN_ICON, ZBX_ICON_ALERT_WITH_CONTENT);
						description_icon.setAttribute('type', 'button');
						description_icon.setAttribute('role', 'button');
						description_icon.setAttribute('data-content', '?');
						description_icon.setAttribute('data-hintbox-html', description_expanded);
						description_icon.setAttribute('data-hintbox', '1');
						description_icon.setAttribute('data-hintbox-class', ZBX_STYLE_HINTBOX_WRAP);
						description_icon.setAttribute('data-hintbox-static', '1');
						description_icon.setAttribute('aria-expanded', 'false');

						action_container.innerHTML += ' ';
						action_container.appendChild(description_icon);
					}

					cell_inner.appendChild(action_container);

					const {show_item_key} = column.getColumnOptions();

					if (show_item_key) {
						const item_key = document.createElement('span');
						item_key.classList.add(ZBX_STYLE_GREEN);
						item_key.textContent = key_expanded;

						cell_inner.appendChild(item_key);
					}
				})
				.setCellRenderer('type', ({cell_data, cell_inner}) => {
					const [type, state] = cell_data;

					if (state == ITEM_STATE_NOTSUPPORTED) {
						const type_container = document.createElement('span');
						type_container.textContent = type;
						type_container.classList.add(ZBX_STYLE_GREY);

						cell_inner.appendChild(type_container);
					}
					else {
						cell_inner.textContent = type;
					}
				})
				.setCellRenderer('actions', ({cell_data, cell_inner}) => {
					const [itemid, is_graph, show_link] = cell_data;

					if (!show_link) {
						return;
					}

					const data_link = document.createElement('a');
					if (is_graph) {
						const search_params = objectToSearchParams({action: 'showgraph', itemids: [itemid]});

						data_link.setAttribute('href', `history.php?${search_params}`);
						data_link.textContent = <?= json_encode(_('Graph')); ?>;
					}
					else {
						const search_params = objectToSearchParams({action: 'showvalues', 'itemids[]': itemid});

						data_link.setAttribute('href', `history.php?${search_params}`);
						data_link.textContent = <?= json_encode(_('History')); ?>;
					}

					cell_inner.appendChild(data_link);
				})
				.setCellRenderer('info', ({cell_data, cell_inner}) => {
					const [item_icons] = cell_data;

					cell_inner.appendChild(item_icons);
				})
				.setOptionsHandler('name', CDataTableOptionsPopupMonitoringLatestName)
				.on(CMessageHelper.EVENT_MESSAGE, e => {
					e.stopPropagation();

					const {type, title, messages} = e.detail;

					clearMessages();
					addMessage(makeMessageBox(type, messages, title));
				})
				.on(CPager.EVENT_SELECT, () => this.#scheduleRefresh())
				.on(CPager.EVENT_STATE_CHANGE, e => {
					const {page} = e.detail;

					new CState().setParams({page});
				})
				.on(CDataTable.EVENT_RENDER, e => {
					const response = e.detail.response;

					this.#refreshCounters(response);

					if ('debug' in response) {
						this.#refreshDebug(response.debug);
					}
				})
				.on(CDataTable.EVENT_DATA_SORT, () => this.#scheduleRefresh())
				.on(CDataTable.EVENT_OPTIONS_POPUP_OPEN, () => this.#unscheduleRefresh())
				.on(CDataTable.EVENT_OPTIONS_POPUP_CLOSE, () => this.#scheduleRefresh())
				.on(CDataTable.EVENT_COLUMN_RESIZE_START, () => this.#unscheduleRefresh())
				.on(CDataTable.EVENT_COLUMN_RESIZE_END, () => this.#scheduleRefresh())
				.init(user_configs);
		}

		#getCurrentForm() {
			return $('form[name=items]');
		}

		#getCurrentSubfilter() {
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
		}

		#addRefreshMessage(messages) {
			this.#removeRefreshMessage();

			this.#refresh_message_box = $($.parseHTML(messages));
			addMessage(this.#refresh_message_box);
		}

		#removeRefreshMessage() {
			if (this.#refresh_message_box !== null) {
				this.#refresh_message_box.remove();
				this.#refresh_message_box = null;
			}
		}

		#addPopupMessage(message_box) {
			this.#removePopupMessage();

			this.#popup_message_box = message_box;
			addMessage(this.#popup_message_box);
		}

		#removePopupMessage() {
			if (this.#popup_message_box !== null) {
				this.#popup_message_box.remove();
				this.#popup_message_box = null;
			}
		}

		#refreshDebug(debug) {
			const debug_output = document
				.querySelector('.wrapper > main > .<?= ZBX_STYLE_DEBUG_OUTPUT_TABLE_REFRESH ?>');

			if (debug_output) {
				debug_output.classList.add('<?= ZBX_STYLE_DEBUG_OUTPUT ?>');
				debug_output.innerHTML = new DOMParser().parseFromString(debug, 'text/html')
					.querySelector('.<?= ZBX_STYLE_DEBUG_OUTPUT ?>').innerHTML;
			}
		}

		#refresh() {
			if (isUserInteracting()) {
				return;
			}

			const search_params = new URLSearchParams(location.search.substring(1));
			const current_filter = searchParamsToObject(search_params);
			const filter = {...this.#filter_defaults, ...current_filter};

			this.#datatable.setFilter(filter)
				.dispatchEvent(CDataTable.EVENT_INIT, {
					check_changes: false,
					force_load: true,
					onSuccess: response => this.#onDataDone(response)
				});
		}

		#refreshCounters(response) {
			if (this.#layout_mode == <?= ZBX_LAYOUT_KIOSKMODE ?>) {
				return;
			}

			if ('filter_counters' in response) {
				this.#filter.updateCounters(response.filter_counters);
			}
		}

		#doRefresh(subfilter = null) {
			const colapsed_tabfilter = document.querySelector('.tabfilter-collapsed');

			if (subfilter !== null) {
				this.#getCurrentSubfilter().innerHTML = subfilter;

				if (colapsed_tabfilter !== null) {
					colapsed_tabfilter.classList.remove('display-none');
				}
			}
			else {
				this.#getCurrentSubfilter().remove();

				if (colapsed_tabfilter !== null) {
					colapsed_tabfilter.classList.add('display-none');
				}
			}

			chkbxRange.init();
			this.#initListActions();
		}

		#onDataDone(response) {
			this.#removeRefreshMessage();

			this.#doRefresh(response.subfilter || null);

			if ('messages' in response) {
				this.#addRefreshMessage(response.messages);
			}

			this.#refreshCounters(response);

			this.#initExpandableSubfilter();
		}

		#scheduleRefresh() {
			if (this.#refresh_interval == 0) {
				return;
			}

			this.#unscheduleRefresh();
			this.#refresh_interval_id = setInterval(() => this.#refresh(), this.#refresh_interval);
		}

		#unscheduleRefresh() {
			clearInterval(this.#refresh_interval_id);
			this.#refresh_interval_id = null;
		}

		executeNow(button, data) {
			if (button instanceof Element) {
				button.classList.add('is-loading');
			}

			let clear_checkboxes = false;
			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'item.execute');
			data[CSRF_TOKEN_NAME] = <?= json_encode(CCsrfTokenHelper::get('item')); ?>;

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
						CMessageHelper.error(this.#datatable.getElement(), [response.error.messages],
							response.error.title, {show_close_box: true, show_details: true});
					}
					else if ('success' in response) {
						clear_checkboxes = true;

						CMessageHelper.success(this.#datatable.getElement(), [], response.success.title,
							{show_close_box: true, show_details: false});
					}
				})
				.catch(() => {
					const title = <?= json_encode(_('Unexpected server error.')); ?>;

					CMessageHelper.error(this.#datatable.getElement(), [], title);
				})
				.finally(() => {
					if (!(button instanceof Element)) {
						return;
					}

					if (clear_checkboxes) {
						const uncheckids = Object.keys(chkbxRange.getSelectedIds());
						uncheckTableRows('latest', []);
						chkbxRange.checkObjects(this.#checkbox_object, uncheckids, false);
						chkbxRange.update(this.#checkbox_object);
					}

					button.classList.remove('is-loading');
					button.blur();
				});
		}

		setSubfilter(field) {
			this.#filter.setSubfilter(field[0], field[1]);
		}

		unsetSubfilter(field) {
			this.#filter.unsetSubfilter(field[0], field[1]);
		}
	};
</script>
