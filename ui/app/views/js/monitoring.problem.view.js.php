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
		filter_defaults: null,
		filter: null,
		active_filter: null,
		global_timerange: null,
		opened_eventids: [],
		datatable: null,

		init({
			filter_options,
			refresh_interval,
			filter_defaults,
			page,
			filter,
			sort_field,
			sort_order,
			storage_idx,
			user_configs,
			severities
		}) {
			this.refresh_interval = refresh_interval;
			this.filter_defaults = filter_defaults;

			this.initFilter(filter_options);
			this.initDataTable({page, filter, sort_field, sort_order, storage_idx, user_configs, severities});

			$.subscribe('event.rank_change', () => view.refresh());

			this.initEvents();
			this.initPopupListeners();

			if (this.refresh_interval != 0) {
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

		initDataTable({page, filter, sort_field, sort_order, storage_idx, user_configs, severities}) {
			const data_provider_url = new URL('zabbix.php', location.href);
			data_provider_url.searchParams.set('action', 'problem.view.data');

			const data_provider = new CDefaultDataProvider(data_provider_url.toString());

			this.datatable = new CDataTable(document.getElementById('problems'), data_provider)
				.setColumns([
					new CDataTableColumn('time', '<?= _('Time'); ?>')
						.setContextPopupData({
							show_timeline: '1'
						})
						.setContextPopupHandler('time')
						.setFields(['clock', 'eventid', 'objectid'])
						.setRenderer('time')
						.setSortField('clock')
						.setSortable(true)
						.setWidth('max-content'),
					new CDataTableColumn('severity', '<?= _('Severity'); ?>')
						.setFields(['severity'])
						.setRenderer('severity')
						.setSortable(true)
						.setWidth('max-content'),
					new CDataTableColumn('recovery', '<?= _('Recovery time'); ?>')
						.setFields(['recovery']),
					new CDataTableColumn('status', '<?= _('Status'); ?>')
						.setFields(['status']),
					new CDataTableColumn('info', '<?= _('Info'); ?>')
						.setFields(['info']),
					new CDataTableColumn('host', '<?= _('Host'); ?>')
						.setFields(['host'])
						.setRenderer('host')
						.setSortable(true)
						.setWidth('max-content'),
					new CDataTableColumn('problem', '<?= _('Problem'); ?>')
						.setContextPopupData({
							show_opdata: '0',
							details: '0',
							show_suppressed: '0'
						})
						.setContextPopupHandler('problem')
						.setFields(['description'])
						.setSortField('name')
						.setSortable(true)
						.setTogglable(false)
						.setWidth('max-content'),
					new CDataTableColumn('duration', '<?= _('Duration'); ?>')
						.setFields(['duration']),
					new CDataTableColumn('update', '<?= _('Update'); ?>')
						.setFields(['can_be_closed', 'eventid'])
						.setRenderer('update')
						.setWidth('max-content'),
					new CDataTableColumn('actions', '<?= _('Actions'); ?>')
						.setFields(['actions']),
					new CDataTableColumn('opdata', '<?= _('Operational data'); ?>')
						.setFields(['opdata'])
						.setVisible(false),
					new CDataTableColumnTags('tags', '<?= _('Tags'); ?>'),
					new CDataTableColumnTagValue('tagvalue', '<?= _('Tag value'); ?>')
				])
				.setOption('compact_view', t('Compact view'), {
					onRender: option => {
						this.datatable.getElement().classList.toggle('compact-view', option.checked);
					},
					onChange: (event, option) => {
						this.datatable.updateOption(option.id, { checked: event.target.checked });

						this.datatable.dispatchEvent(CDataTable.EVENT_INIT);
						this.datatable.dispatchEvent(CDataTable.EVENT_SAVE);
					}
				})
				.setOption('highlight_row', t('Highlight whole row'), {
					onRender: option => {
						this.datatable.getElement().classList.toggle('has-highlighted-rows', option.checked);
					},
					onChange: (event, option) => {
						this.datatable.updateOption(option.id, { checked: event.target.checked });

						this.datatable.dispatchEvent(CDataTable.EVENT_RENDER);
						this.datatable.dispatchEvent(CDataTable.EVENT_SAVE);
					}
				})
				.setPage(page)
				.setFilter(filter)
				.setTabFilterItem(this.active_filter)
				.setSelectable('problem', 'eventids', ['eventid', 'nested', 'symptom_count', 'cause_eventid',
					'severity'])
				.setSortField(sort_field)
				.setSortOrder(sort_order)
				.setStorageIdx(storage_idx)
				.setRenderer(CDataTableColumn.CHECKBOX, ({column_config, column_data, row_index, cell, cell_inner}) => {
					const [eventid, nested, symptom_count, cause_eventid, severity] = column_data;

					if (!eventid) {
						return;
					}

					const input_id = `${column_config.getId()}_${eventid}`;

					const checkbox = document.createElement('input');
					checkbox.classList.add(ZBX_STYLE_CHECKBOX_RADIO);
					checkbox.setAttribute('type', 'checkbox');
					checkbox.setAttribute('id', input_id);
					checkbox.setAttribute('name', `${column_config.getId()}[${eventid}]`);
					checkbox.setAttribute('data-field-type', 'checkbox');
					checkbox.value = eventid.toString();

					const label = document.createElement('label');
					label.setAttribute('for', input_id);
					label.appendChild(document.createElement('span'));

					cell.classList.add(CDataTable.ZBX_STYLE_CELL_CHECKBOX);

					if (nested) {
						label.classList.add('symptoms-left');
					}
					else {
						cell_inner.append(checkbox, label);
					}

					const filter = this.datatable.getFilter();
					const show_symptoms = filter.show_symptoms == 1;
					const {show_two_columns, show_three_columns} = this.datatable.getDataProvider().getLastResponse();

					if (show_two_columns || show_three_columns) {
						const symptoms = document.createElement('div');
						symptoms.classList.add('symptoms');

						if (cause_eventid == 0) {
							if (symptom_count > 0) {
								const symptom_counter = document.createElement('span');
								symptom_counter.classList.add('entity-count');
								symptom_counter.innerText = symptom_count;

								const symptoms_left = document.createElement('span');
								symptoms_left.classList.add('symptoms-left');
								symptoms_left.appendChild(symptom_counter);

								const show_symptoms_button = document.createElement('button');
								show_symptoms_button.classList.add(ZBX_STYLE_BTN_ICON, ZBX_ICON_CHEVRON_DOWN,
									ZBX_STYLE_COLLAPSED);
								show_symptoms_button.setAttribute('type', 'button');
								show_symptoms_button.setAttribute('title', t('Expand'));
								show_symptoms_button.setAttribute('data-eventid', eventid);
								show_symptoms_button.setAttribute('data-action', 'show_symptoms');

								symptoms.append(symptoms_left, show_symptoms_button);
							}
						}
						else {
							if (nested) {
								symptoms.append(checkbox, label);
							}

							if (nested || show_symptoms) {
								const symptom_icon = document.createElement('span');
								symptom_icon.classList.add('icon', 'zi-arrow-top-right',
									nested ? 'symptoms-right' : 'symptoms-left');
								symptom_icon.setAttribute('title', t('Symptom'));

								symptoms.append(symptom_icon);
							}
						}

						cell.appendChild(symptoms);
					}
					else {

					}

					requestAnimationFrame(() => {
						const highlight_row = this.datatable.getOption('highlight_row');

						if (highlight_row.checked) {
							const severity_data = severities.find(data => data.value == severity);

							if (severity_data) {
								this.datatable.findDataCells(null, row_index).forEach(cell => {
									return cell.classList.add(CDataTable.ZBX_STYLE_CELL_BG_HOVER, severity_data.style);
								});
							}
						}
					});
				})
				.setRenderer('time', ({column_config, column_data, cell, cell_inner}) => {
					const [clock, eventid, triggerid] = column_data;

					if (clock && eventid && triggerid) {
						const url = new URL('tr_events.php', location.href);
						url.searchParams.set('triggerid', triggerid);
						url.searchParams.set('eventid', eventid);

						const clock_link = document.createElement('a');
						clock_link.setAttribute('href', url.toString());
						clock_link.innerText = clock;

						const timeline = document.createElement('div');
						timeline.classList.add('timeline-date');

						timeline.appendChild(clock_link);

						cell_inner.append(timeline);
					}

					const compact_view = this.datatable.getOption('compact_view');
					const context_popup_data = column_config.getContextPopupData();

					if (context_popup_data.show_timeline == 1 && !compact_view.checked
							&& this.datatable.getSortField() == 'clock') {

						const axis = document.createElement('div');
						axis.classList.add('timeline-axis', 'timeline-dot');

						const td = document.createElement('div');
						td.classList.add('timeline-td');

						cell.classList.add('cell-timeline');
						cell.append(axis, td);
					}
				})
				.setRenderer('breakpoint', ({column_config, column_data, cell, cell_inner}) => {
					const [breakpoint] = column_data;

					const breakpoint_header = document.createElement('h4');
					breakpoint_header.innerText = breakpoint;

					const timeline = document.createElement('div');
					timeline.classList.add('timeline-date');

					timeline.appendChild(breakpoint_header);

					cell_inner.appendChild(timeline);

					const compact_view = this.datatable.getOption('compact_view');
					const context_popup_data = column_config.getContextPopupData();

					if (context_popup_data.show_timeline == 1 && !compact_view.checked) {
						const axis = document.createElement('div');
						axis.classList.add('timeline-axis', 'timeline-dot-big');

						const td = document.createElement('div');
						td.classList.add('timeline-td');

						cell.classList.add('cell-timeline');
						cell.append(axis, td);
					}
				})
				.setRenderer('severity', ({column_data, cell, cell_inner}) => {
					const [severity] = column_data;
					const severity_data = severities.find(data => data.value == severity);

					cell.classList.add(CDataTable.ZBX_STYLE_CELL_BG, severity_data.style);

					cell_inner.innerText = severity_data.label;
				})
				.setRenderer('host', ({column_data, cell_inner}) => {
					const [hosts] = column_data;

					if (!hosts) {
						return;
					}

					for (const host of hosts) {
						const {hostid, name, maintenance_type, maintenance_status} = host;

						const host_link = document.createElement('a');
						host_link.classList.add(ZBX_STYLE_LINK_ACTION, ZBX_STYLE_WORDBREAK);
						host_link.setAttribute('data-menu-popup', JSON.stringify({
							type: 'host',
							data: {
								hostid
							}
						}));
						host_link.setAttribute('aria-expanded', 'false');
						host_link.setAttribute('aria-haspopup', 'true');
						host_link.setAttribute('role', 'button');
						host_link.setAttribute('href', 'javascript:void(0)');
						host_link.innerText = name;

						cell_inner.appendChild(host_link);

						if (maintenance_status == HOST_MAINTENANCE_STATUS_ON) {
							let maintenance_name, maintenance_description;

							if ('maintenance' in host) {
								maintenance_name = host.maintenance.name;
								maintenance_description = host.maintenance.description;
							}
							else {
								maintenance_name = t('Inaccessible maintenance');
								maintenance_description = '';
							}

							let hint = `${maintenance_name} [${maintenance_type
								? t('Maintenance without data collection')
								: t('Maintenance with data collection')}]`;

							if (maintenance_description) {
								hint += `\n${maintenance_description}`;
							}

							const maintenance = document.createElement('button');
							maintenance.setAttribute('type', 'button');
							maintenance.classList.add(ZBX_STYLE_BTN_ICON, ZBX_ICON_WRENCH_ALT_SMALL,
								ZBX_STYLE_COLOR_WARNING, ZBX_STYLE_NO_INDENT);
							maintenance.setAttribute('data-hintbox-contents', hint)
							maintenance.setAttribute('data-hintbox', '1');
							maintenance.setAttribute('data-hintbox-static', '1');

							cell_inner.appendChild(maintenance);
						}
					}
				})
				.setRenderer('update', ({column_data, cell_inner}) => {
					const [can_be_closed, eventid] = column_data;

					if (!eventid) {
						return;
					}

					/**
					 * @var {{
					 *     add_comments: boolean|undefined,
					 *     change_severity: boolean|undefined,
					 *     acknowledge: boolean|undefined,
					 *     suppress_problems: boolean|undefined,
					 *     rank_change: boolean|undefined
					 * }}
					 */
					const {allowed} = this.datatable.getDataProvider().getLastResponse();

					if (!allowed) {
						return;
					}

					let update_link;

					if (allowed.add_comments || allowed.change_severity || allowed.acknowledge || can_be_closed
						|| allowed.suppress_problems || allowed.rank_change) {

						const url_params = objectToSearchParams({
							action: 'popup',
							popup: 'acknowledge.edit',
							'eventids[]': eventid
						});
						const url = new URL(`zabbix.php?${url_params}`, location.href);

						update_link = document.createElement('a');
						update_link.classList.add(ZBX_STYLE_LINK_ALT);
						update_link.setAttribute('href', url.toString());
					}
					else {
						update_link = document.createElement('span');
					}

					update_link.innerText = t('Update');

					cell_inner.appendChild(update_link);
				})
				.setRenderer('symptom_limit', ({cell, cell_inner, column_data}) => {
					const [paging] = column_data;

					const table_stats = document.createElement('div');
					table_stats.classList.add(ZBX_STYLE_TABLE_STATS, 'table-stats-small');
					table_stats.innerText = paging;

					const paging_container = document.createElement('div');
					paging_container.classList.add(ZBX_STYLE_PAGING_BTN_CONTAINER);
					paging_container.appendChild(table_stats);

					cell.classList.add(CDataTable.ZBX_STYLE_CELL_STICKY);
					cell_inner.appendChild(paging_container);
				})
				.setRowRenderer('default', ({columns, row, row_index, row_data}) => {
					const {show_two_columns, show_three_columns} = this.datatable.getDataProvider().getLastResponse();
					const column_config = this.datatable.getCheckboxColumnConfig();

					if (show_three_columns) {
						column_config.setWidth('93px');
					}
					else if (show_two_columns) {
						column_config.setWidth('72px');
					}
					else {
						column_config.setWidth('37px');
					}

					this.datatable.renderDataCells({columns, row, row_index, row_data});
				})
				.setRowRenderer('nested_symptom', ({columns, row, row_data, row_index}) => {
					const column_config = this.datatable.getCheckboxColumnConfig();
					const column_index = column_config.getColumnIndex();
					const [, , , cause_eventid, severity] = row_data[column_index];

					row.classList.add('nested', 'nested-small', 'hidden');
					row.setAttribute('data-cause-eventid', cause_eventid);

					this.datatable.renderDataCells({columns, row, row_data, row_index});

					requestAnimationFrame(() => {
						const highlight_row = this.datatable.getOption('highlight_row');

						if (highlight_row.checked) {
							const severity_data = severities.find(data => data.value == severity);

							if (severity_data) {
								this.datatable.findDataCells(null, row_index).forEach(cell => {
									cell.classList.add(CDataTable.ZBX_STYLE_CELL_BG, severity_data.style);
								});
							}
						}
					});
				})
				.setRowRenderer('symptom_limit', ({columns, row, row_index, row_data}) => {
					const [cause_eventid, symptom_limit] = row_data;

					row.classList.add(CDataTable.ZBX_STYLE_ROW_DISABLED, 'hidden');
					row.setAttribute('data-cause-eventid', cause_eventid);

					const visible_columns = columns.filter(column_config => column_config.isVisible());

					const data_cells = [];
					const column_index = 3;

					for (const column_config of visible_columns.slice(0, column_index + 1)) {
						const column_config_clone = column_config.clone();

						if (column_config.getColumnIndex() == column_index) {
							column_config_clone.setSpan(visible_columns.length - column_index);
						}
						else {
							column_config_clone.setSpan(1);
						}

						const data_cell = this.datatable.createDataCell(column_config_clone, row_index);

						if (column_config.getId() == 'time') {
							this.datatable.renderDataCellContents(column_config_clone, row, data_cell, [null]);
						}
						else if (column_config.getColumnIndex() == column_index) {
							column_config_clone.setRenderer('symptom_limit');

							this.datatable.renderDataCellContents(column_config_clone, row, data_cell, {
								[column_config.getColumnIndex()]: [symptom_limit]
							});
						}

						data_cells.push(data_cell);
					}

					row.append(...data_cells);
				})
				.setRowRenderer('breakpoint', ({columns, row, row_index, row_data}) => {
					const compact_view = this.datatable.getOption('compact_view');
					if (compact_view.checked) {
						return;
					}

					row.classList.add(CDataTable.ZBX_STYLE_ROW_DISABLED);

					const visible_columns = columns.filter(column_config => column_config.isVisible());

					const data_cells = [];
					const column_index = visible_columns.length > 2 ? 2 : Math.min(2, visible_columns.length - 1);

					for (const column_config of visible_columns.slice(0, column_index + 1)) {
						const column_config_clone = column_config.clone();

						if (column_config.getColumnIndex() == column_index) {
							column_config_clone.setSpan(visible_columns.length - column_index);
						}
						else {
							column_config_clone.setSpan(1);
						}

						const data_cell = this.datatable.createDataCell(column_config_clone, row_index);

						if (column_config.getId() == 'time') {
							column_config_clone.setRenderer('breakpoint');

							this.datatable.renderDataCellContents(column_config_clone, row, data_cell, {
								[column_config.getColumnIndex()]: row_data
							});
						}

						data_cells.push(data_cell);
					}

					row.append(...data_cells);
				})
				.setContextPopupHandler('time', 'CDataTableContextPopupMonitoringProblemsTime')
				.setContextPopupHandler('problem', 'CDataTableContextPopupMonitoringProblemsProblem')
				.setContextPopupHandler('tags', 'CDataTableContextPopupTags')
				.setContextPopupHandler('tagvalue', 'CDataTableContextPopupTagValue')
				.on(CMessageHelper.EVENT_MESSAGE, event => {
					event.stopPropagation();

					const {type, title, messages} = event.detail;

					if (type == 'clear') {
						clearMessages();
					}
					else {
						addMessage(makeMessageBox(type, messages, title));
					}
				})
				.on(CDataTable.EVENT_RENDER, () => {
					this.refreshCounters();

					requestAnimationFrame(() => this.initExpandables());
				})
				.on(CDataTable.EVENT_CONTEXT_POPUP_OPEN, () => this.unscheduleRefresh())
				.on(CDataTable.EVENT_CONTEXT_POPUP_CLOSE, () => this.scheduleRefresh())
				.init(user_configs);
		},

		/**
		 * @param {{ timeselector: object }} filter_options
		 */
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
				chkbxRange.clearSelectedOnFilterChange();

				if (this.active_filter !== this.filter._active_item) {
					this.active_filter = this.filter._active_item;
					chkbxRange.checkObjectAll(chkbxRange.pageGoName, false);

					this.datatable.setTabFilterItem(this.active_filter);
				}

				this.refresh();
			});
		},

		initExpandables() {
			const table = this.datatable.getElement();
			const expandable_buttons = table.querySelectorAll('button[data-action="show_symptoms"]');

			expandable_buttons.forEach(btn => {
				['click','keydown'].forEach((type) => {
					btn.addEventListener(type, (e) => {
						if (e.type === 'click' || e.which === 13) {
							this.showSymptoms(btn);
						}
					});
				});

				// Check if cause events were opened. If so, after (not full) refresh open them again.
				if (this.opened_eventids.includes(btn.dataset.eventid)) {
					const rows = table.querySelectorAll(
						`.${CDataTable.ZBX_STYLE_ROW}[data-cause-eventid="${btn.dataset.eventid}"]`);

					[...rows].forEach((row) => row.classList.remove('hidden'));

					btn.classList.remove(ZBX_ICON_CHEVRON_DOWN, ZBX_STYLE_COLLAPSED);
					btn.classList.add(ZBX_ICON_CHEVRON_UP);
					btn.title = <?= json_encode(_('Collapse')) ?>;
				}
			});
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

					CMessageHelper.clear(this.datatable.getElement());

					if ('success' in data.submit) {
						CMessageHelper.success(this.datatable.getElement(), data.submit.success.messages,
							data.submit.success.title);
					}

					chkbxRange.checkObjectAll('eventids', false);
					chkbxRange.update('eventids');

					this.refreshCounters();
				}
			});
		},

		showSymptoms(btn) {
			// Prevent multiple clicking by first disabling button.
			btn.disabled = true;

			const table = this.datatable.getElement();
			let rows = table.querySelectorAll(
				`.${CDataTable.ZBX_STYLE_ROW}[data-cause-eventid="${btn.dataset.eventid}"]`);

			// Show symptom rows for the current cause.
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

			// When complete enable button again.
			btn.disabled = false;
		},

		getCurrentDebugBlock() {
			return document.querySelector('.wrapper > .debug-output');
		},

		refreshDebug(debug) {
			this.getCurrentDebugBlock().replaceWith(
				new DOMParser().parseFromString(debug, 'text/html').body.firstElementChild
			);
		},

		refresh() {
			const filter_params = this.active_filter.getFilterParamsObject();

			if ('inventory' in filter_params) {
				const inventory = Array.from(filter_params.inventory).filter(inv => 'value' in inv && inv.value !== '');
				if (inventory.length == 0) {
					delete filter_params.inventory;
				}
			}

			if ('tags' in filter_params) {
				const tags = Array.from(filter_params.tags).filter(tag => !(tag.tag === '' && tag.value === ''));
				if (tags.length == 0) {
					delete filter_params.tags;
				}
			}

			if ('severities' in filter_params) {
				if (filter_params.severities.length == 0) {
					delete filter_params.severities;
				}
			}

			this.datatable.setFilter({...this.filter_defaults, ...filter_params})
				.dispatchEvent(CDataTable.EVENT_INIT, {
					onSuccess: response => this.onDataDone(response)
				});
		},

		refreshCounters() {
			if (!this.filter) {
				return;
			}

			const url = new URL('zabbix.php', location.href);
			url.searchParams.set('action', 'problem.view.refresh');

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
					CMessageHelper.clear(this.datatable.getElement());
					CMessageHelper.error(this.datatable.getElement(), [error.message], error.name);
				});
		},

		scheduleRefresh() {
			this.unscheduleRefresh();
			this.refresh_interval_id = setInterval(() => this.refresh(), this.refresh_interval);
		},

		unscheduleRefresh() {
			clearInterval(this.refresh_interval_id);
			this.refresh_interval_id = null;
		},

		onDataDone(response) {
			if ('messages' in response) {
				CMessageHelper.clear(this.datatable.getElement());
				CMessageHelper.success(this.datatable.getElement(), [], response.messages, {show_close_box: true});
			}

			('debug' in response) && this.refreshDebug(response.debug);
		}
	};
</script>
