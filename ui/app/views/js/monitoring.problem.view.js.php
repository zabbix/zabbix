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
		#filter_defaults = null;
		#filter = null;
		#active_filter = null;
		#global_timerange = null;
		#opened_eventids = [];
		#datatable = null;
		#csrf_token = null;
		#show_problems_hidden_column_ids = ['recovery', 'status'];
		#refresh_message_box = null;

		init({
			csrf_token,
			default_sort_field,
			default_sort_order,
			filter,
			filter_defaults,
			filter_options,
			layout_mode,
			page,
			refresh_interval,
			severities,
			sort_field,
			sort_order,
			storage_idx,
			user_configs
		}) {
			this.#layout_mode = layout_mode;
			this.#refresh_interval = refresh_interval;
			this.#filter_defaults = filter_defaults;
			this.#csrf_token = csrf_token;

			this.#initFilter(filter_options);
			this.#initDataTable({page, filter, default_sort_field, default_sort_order, sort_field, sort_order,
				storage_idx, user_configs, severities});

			$.subscribe('event.rank_change', () => this.#refresh());

			this.#initEvents();
			this.#initPopupListeners();
			this.#scheduleRefresh();

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
		}

		#initDataTable({page, filter, default_sort_field, default_sort_order, sort_field, sort_order, storage_idx,
				user_configs, severities}) {

			const data_provider_url = new URL('zabbix.php', location.href);
			data_provider_url.searchParams.set('action', 'problem.view.data');
			data_provider_url.searchParams.set(CSRF_TOKEN_NAME, this.#csrf_token);

			const data_provider = new CDefaultDataProvider(data_provider_url.toString());

			if (!filter.filter_custom_time) {
				filter.from = this.#global_timerange.from;
				filter.to = this.#global_timerange.to;
			}

			this.#datatable = new CDataTable(document.getElementById('problems'), data_provider)
				.setColumns([
					new CDataTableColumn('time', <?= json_encode(_('Time')); ?>)
						.setColumnOptions({
							show_timeline: '1'
						})
						.setOptionsPopupHandler('time')
						.setFields(['time', 'eventid', 'objectid'])
						.setRenderer('time')
						.setSortField('clock')
						.setSortable(true),
					new CDataTableColumn('severity', <?= json_encode(_('Severity')); ?>)
						.setFields(['severity'])
						.setRenderer('severity')
						.setSortable(true),
					new CDataTableColumn('recovery', <?= json_encode(_('Recovery time')); ?>)
						.setFields(['recovery']),
					new CDataTableColumn('status', <?= json_encode(_('Status')); ?>)
						.setFields(['status']),
					new CDataTableColumn('info', <?= json_encode(_('Info')); ?>)
						.setFields(['info']),
					new CDataTableColumn('host', <?= json_encode(_('Host')); ?>)
						.setFields(['host'])
						.setRenderer('host')
						.setSortable(true),
					new CDataTableColumn('problem', <?= json_encode(_('Problem')); ?>)
						.setColumnOptions({
							show_opdata: '0',
							details: '0'
						})
						.setOptionsPopupHandler('problem')
						.setFields(['description'])
						.setSortField('name')
						.setSortable(true)
						.setTogglable(false)
						.setWidth('auto'),
					new CDataTableColumn('duration', <?= json_encode(_('Duration')); ?>)
						.setFields(['duration']),
					new CDataTableColumn('update', <?= json_encode(_('Update')); ?>)
						.setFields(['can_be_closed', 'eventid'])
						.setRenderer('update'),
					new CDataTableColumn('actions', <?= json_encode(_('Actions')); ?>)
						.setFields(['actions']),
					new CDataTableColumn('opdata', <?= json_encode(_('Operational data')); ?>)
						.setFields(['opdata'])
						.setVisible(false),
					new CDataTableColumnTags('tags', <?= json_encode(_('Tags')); ?>),
					new CDataTableColumnTagValue('tagvalue', <?= json_encode(_('Tag value')); ?>),
					new CDataTableColumnCustomText('custom_text', <?= json_encode(_('Custom text')); ?>)
				])
				.setOption('compact_view', <?= json_encode(_('Compact view')); ?>, {
					onRender: option => {
						this.#datatable.getElement().classList.toggle('compact-view', option.checked);
					},
					onChange: (e, option) => {
						this.#datatable.updateOption(option.id, { checked: e.target.checked });

						this.#datatable.dispatchEvent(CDataTable.EVENT_INIT);
						this.#datatable.dispatchEvent(CDataTable.EVENT_SAVE);
					}
				})
				.setOption('highlight_row', <?= json_encode(_('Highlight whole row')); ?>, {
					onRender: option => {
						this.#datatable.getElement().classList.toggle('has-highlighted-rows', option.checked);
					},
					onChange: (e, option) => {
						this.#datatable
							.updateOption(option.id, { checked: e.target.checked })
							.getData()
							.then(response => {
								this.#datatable.dispatchEvent(CDataTable.EVENT_RENDER, {response});
								this.#datatable.dispatchEvent(CDataTable.EVENT_SAVE);
							});
					}
				})
				.setPage(page)
				.setFilter(filter)
				.setTabFilterItem(this.#active_filter)
				.setSelectable('problem', 'eventids', ['eventid', 'nested', 'symptom_count', 'cause_eventid',
					'severity'])
				.setDefaultSortField(default_sort_field)
				.setDefaultSortOrder(default_sort_order)
				.setSortField(sort_field)
				.setSortOrder(sort_order)
				.setStorageIdx(storage_idx)
				.setStickyHeader(true)
				.setStickyFooter(true)
				.setCellRenderer(CDataTableColumn.CHECKBOX, ({column, cell_data, row, row_index, cell, cell_inner,
						response}) => {

					const [eventid, nested, symptom_count, cause_eventid, severity] = cell_data;

					if (!eventid) {
						return;
					}

					const input_id = `${column.getId()}_${eventid}`;

					const checkbox = document.createElement('input');
					checkbox.classList.add(ZBX_STYLE_CHECKBOX_RADIO);
					checkbox.setAttribute('type', 'checkbox');
					checkbox.setAttribute('id', input_id);
					checkbox.setAttribute('name', `${column.getId()}[${eventid}]`);
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

					const filter = this.#datatable.getFilter();
					const show_symptoms = filter.show_symptoms == 1;
					const {show_two_columns, show_three_columns} = response;

					if (!column.isResized()) {
						column.setResized(true);

						if (show_three_columns) {
							column.setWidth('78px');
						}
						else if (show_two_columns) {
							column.setWidth('72px');
						}
						else {
							column.setWidth('37px');
						}
					}

					if (show_two_columns || show_three_columns) {
						const symptoms = document.createElement('div');
						symptoms.classList.add('symptoms');

						if (cause_eventid == 0) {
							if (symptom_count > 0) {
								const symptom_counter = document.createElement('span');
								symptom_counter.classList.add('entity-count');
								symptom_counter.textContent = symptom_count;

								const symptoms_left = document.createElement('span');
								symptoms_left.classList.add('symptoms-left');
								symptoms_left.appendChild(symptom_counter);

								const show_symptoms_button = document.createElement('button');
								show_symptoms_button.classList.add(ZBX_STYLE_BTN_ICON, ZBX_ICON_CHEVRON_DOWN,
									ZBX_STYLE_COLLAPSED);
								show_symptoms_button.setAttribute('type', 'button');
								show_symptoms_button.setAttribute('title', <?= json_encode(_('Expand')); ?>);
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
								symptom_icon.setAttribute('title', <?= json_encode(_('Symptom')); ?>);

								symptoms.append(symptom_icon);
							}
						}

						cell.appendChild(symptoms);
					}

					requestAnimationFrame(() => {
						const highlight_row = this.#datatable.getOption('highlight_row');

						if (highlight_row.checked) {
							const severity_data = severities.find(data => data.value == severity);
							if (!severity_data) {
								return;
							}

							for (const data_cell of this.#datatable.findDataCells({row_index})) {
								data_cell.target.classList.add(CDataTable.ZBX_STYLE_CELL_BG_HOVER, severity_data.style);
							}

							const row_spacer = this.#datatable.findRowSpacer(row);
							if (row_spacer) {
								row_spacer.classList.add(severity_data.style);
							}
						}
					});
				})
				.setCellRenderer('time', ({column, cell_data, cell, cell_inner}) => {
					const [clock, eventid, triggerid] = cell_data;

					if (clock && eventid && triggerid) {
						const url = new URL('tr_events.php', location.href);
						url.searchParams.set('triggerid', triggerid);
						url.searchParams.set('eventid', eventid);

						const clock_link = document.createElement('a');
						clock_link.setAttribute('href', url.toString());
						clock_link.textContent = clock;

						const timeline = document.createElement('div');
						timeline.classList.add('timeline-date');

						timeline.appendChild(clock_link);

						cell_inner.append(timeline);
					}

					const compact_view = this.#datatable.getOption('compact_view');
					const column_options = column.getColumnOptions();

					if (column_options.show_timeline == 1 && !compact_view.checked
							&& this.#datatable.getSortField() == 'clock') {

						const axis = document.createElement('div');
						axis.classList.add('timeline-axis', 'timeline-dot');

						const td = document.createElement('div');
						td.classList.add('timeline-td');

						cell.classList.add('cell-timeline');
						cell.append(axis, td);
					}
				})
				.setCellRenderer('breakpoint', ({column, cell_data, cell, cell_inner}) => {
					const [breakpoint] = cell_data;

					const breakpoint_header = document.createElement('h4');
					breakpoint_header.textContent = breakpoint;

					const timeline = document.createElement('div');
					timeline.classList.add('timeline-date');

					timeline.appendChild(breakpoint_header);

					cell_inner.appendChild(timeline);

					const compact_view = this.#datatable.getOption('compact_view');
					const column_options = column.getColumnOptions();

					if (column_options.show_timeline == 1 && !compact_view.checked) {
						const axis = document.createElement('div');
						axis.classList.add('timeline-axis', 'timeline-dot-big');

						const td = document.createElement('div');
						td.classList.add('timeline-td');

						cell.classList.add('cell-timeline');
						cell.append(axis, td);
					}
				})
				.setCellRenderer('severity', ({cell_data, cell, cell_inner}) => {
					const [severity] = cell_data;
					const severity_data = severities.find(data => data.value == severity);

					cell.classList.add(CDataTable.ZBX_STYLE_CELL_BG, severity_data.style);

					cell_inner.textContent = severity_data.label;
				})
				.setCellRenderer('host', ({cell_data, cell_inner}) => {
					const [hosts] = cell_data;

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
						host_link.textContent = name;

						cell_inner.appendChild(host_link);

						if (maintenance_status == HOST_MAINTENANCE_STATUS_ON) {
							let maintenance_name, maintenance_description;

							if ('maintenance' in host) {
								maintenance_name = host.maintenance.name;
								maintenance_description = host.maintenance.description;
							}
							else {
								maintenance_name = <?= json_encode(_('Inaccessible maintenance')); ?>;
								maintenance_description = '';
							}

							let hint = `${escapeHtml(maintenance_name)} [${maintenance_type
								? <?= json_encode(_('Maintenance without data collection')); ?>
								: <?= json_encode(_('Maintenance with data collection')); ?>}]`;

							if (maintenance_description) {
								hint += `\n${escapeHtml(maintenance_description)}`;
							}

							const maintenance_icon = document.createElement('button');
							maintenance_icon.setAttribute('type', 'button');
							maintenance_icon.classList.add(ZBX_STYLE_BTN_ICON, ZBX_ICON_WRENCH_ALT_SMALL,
								ZBX_STYLE_COLOR_WARNING, ZBX_STYLE_NO_INDENT);
							maintenance_icon.setAttribute('data-hintbox-html', hint)
							maintenance_icon.setAttribute('data-hintbox', '1');
							maintenance_icon.setAttribute('data-hintbox-class', ZBX_STYLE_HINTBOX_WRAP);
							maintenance_icon.setAttribute('data-hintbox-static', '1');
							maintenance_icon.setAttribute('aria-expanded', 'false');

							cell_inner.appendChild(maintenance_icon);
						}
					}
				})
				.setCellRenderer('update', ({cell_data, cell_inner, response}) => {
					const [can_be_closed, eventid] = cell_data;

					if (!eventid) {
						return;
					}

					const {allowed} = response;
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

					update_link.textContent = <?= json_encode(_('Update')); ?>;

					cell_inner.appendChild(update_link);
				})
				.setCellRenderer('symptom_limit', ({cell, cell_inner, cell_data}) => {
					const [paging] = cell_data;

					const table_stats = document.createElement('div');
					table_stats.classList.add(ZBX_STYLE_TABLE_STATS, 'table-stats-small');
					table_stats.textContent = paging;

					const paging_container = document.createElement('div');
					paging_container.classList.add(ZBX_STYLE_PAGING_BTN_CONTAINER);
					paging_container.appendChild(table_stats);

					cell.classList.add(CDataTable.ZBX_STYLE_CELL_STICKY);
					cell_inner.appendChild(paging_container);
				})
				.setRowRenderer('nested_symptom', ({columns, row, row_index, data_fields, row_data, response}) => {
					const column = this.#datatable.getCheckboxColumn();
					const cell_data = this.#datatable.collectColumnData(column, column.getFields(), row_data);
					const [, , , cause_eventid, severity] = cell_data;

					row.classList.add('nested', 'nested-small', 'hidden');
					row.setAttribute('data-cause-eventid', cause_eventid);

					this.#datatable.renderDataCells({columns, row, row_index, data_fields, row_data, response});

					requestAnimationFrame(() => {
						const highlight_row = this.#datatable.getOption('highlight_row');

						if (highlight_row.checked) {
							const severity_data = severities.find(data => data.value == severity);
							if (!severity_data) {
								return;
							}

							for (const data_cell of this.#datatable.findDataCells({row_index})) {
								data_cell.target.classList.add(CDataTable.ZBX_STYLE_CELL_BG_HOVER, severity_data.style);
							}

							const row_spacer = this.#datatable.findRowSpacer(row);
							if (row_spacer) {
								row_spacer.classList.add(severity_data.style);
							}
						}
					});
				})
				.setRowRenderer('symptom_limit', ({columns, row, row_index, data_fields, row_data, response}) => {
					const [cause_eventid, symptom_limit] = row_data;

					row.classList.add(CDataTable.ZBX_STYLE_ROW_DISABLED, 'hidden');
					row.setAttribute('data-cause-eventid', cause_eventid);

					const visible_columns = columns.filter(column => column.isVisible());

					const data_cells = [];
					const column_index = 3;

					for (const column of visible_columns.slice(0, column_index + 1)) {
						const column_clone = column.clone();

						if (column.getColumnIndex() == column_index) {
							column_clone.setSpan(visible_columns.length - column_index);
						}
						else {
							column_clone.setSpan(1);
						}

						const data_cell = this.#datatable.createDataCell(column_clone);

						if (column.getId() == 'time') {
							this.#datatable.renderDataCellContents(column_clone, row, row_index, data_cell, data_fields,
								[null], response);
						}
						else if (column.getColumnIndex() == column_index) {
							column_clone.setRenderer('symptom_limit');

							this.#datatable.renderDataCellContents(column_clone, row, row_index, data_cell, data_fields,
								[symptom_limit], response);
						}

						data_cells.push(data_cell.target);
					}

					row.append(...data_cells);
				})
				.setRowRenderer('breakpoint', ({columns, row, row_index, data_fields, row_data, response}) => {
					const compact_view = this.#datatable.getOption('compact_view');
					if (compact_view.checked) {
						return;
					}

					row.classList.add(CDataTable.ZBX_STYLE_ROW_DISABLED);

					const data_cells = [];
					const visible_columns = columns.filter(column => column.isVisible());
					const column_index = visible_columns.findIndex(column => column.getId() == 'time');

					if (column_index < 0) {
						return;
					}

					for (let i = 0; i < visible_columns.length; i++) {
						const column_clone = visible_columns[i].clone();
						const data_cell = this.#datatable.createDataCell(column_clone);

						if (i == column_index) {
							column_clone.setRenderer('breakpoint');

							this.#datatable.renderDataCellContents(column_clone, row, row_index, data_cell, data_fields,
								row_data, response);
						}

						data_cells.push(data_cell.target);
					}

					row.append(...data_cells);

					if (this.#datatable.isCustomizable()) {
						this.#datatable.createRowSpacer(row);
					}
				})
				.setOptionsHandler('time', CDataTableOptionsPopupMonitoringProblemsTime)
				.setOptionsHandler('problem', CDataTableOptionsPopupMonitoringProblemsProblem)
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
				.on(CDataTable.EVENT_BEFORE_RENDER, () => {
					const filter = this.#datatable.getFilter();

					this.#handleShowProblemsFilter(filter);
				})
				.on(CDataTable.EVENT_RENDER, e => {
					const response = e.detail.response;

					this.#refreshCounters(response);

					if ('debug' in response) {
						this.#refreshDebug(response.debug);
					}

					requestAnimationFrame(() => this.#initExpandables());
				})
				.on(CDataTable.EVENT_COLUMN_TOGGLE, e => {
					const {column_index} = e.detail;

					const column = this.#datatable.getColumn(column_index);
					if (!column) {
						return;
					}

					const filter = this.#datatable.getFilter();
					if (filter.show != <?= TRIGGERS_OPTION_IN_PROBLEM; ?>) {
						return;
					}

					if (this.#show_problems_hidden_column_ids.includes(column.getId())) {
						e.preventDefault();

						column.setVisible(false);

						this.#datatable.updateUserConfig();
					}
				})
				.on(CDataTable.EVENT_DATA_SORT, () => this.#scheduleRefresh())
				.on(CDataTable.EVENT_OPTIONS_POPUP_OPEN, () => this.#unscheduleRefresh())
				.on(CDataTable.EVENT_OPTIONS_POPUP_CLOSE, () => this.#scheduleRefresh())
				.on(CDataTable.EVENT_COLUMN_RESIZE_START, () => this.#unscheduleRefresh())
				.on(CDataTable.EVENT_COLUMN_RESIZE_END, () => this.#scheduleRefresh())
				.init(user_configs);
		}

		/**
		 * @param {{ timeselector: object }} filter_options
		 */
		#initFilter(filter_options) {
			/** @type {HTMLElement} */
			const filter = document.getElementById('monitoring_problem_filter');

			this.#filter = new CTabFilter(filter, filter_options);
			this.#active_filter = this.#filter._active_item;

			this.#global_timerange = {
				from: filter_options.timeselector.from,
				to: filter_options.timeselector.to
			};

			/**
			 * Update on filter changes.
			 */
			this.#filter.on(TABFILTER_EVENT_URLSET, () => {
				chkbxRange.clearSelectedOnFilterChange();

				const tabfilter_changed = this.#active_filter !== this.#filter._active_item;

				if (tabfilter_changed) {
					this.#active_filter = this.#filter._active_item;
					chkbxRange.checkObjectAll(chkbxRange.pageGoName, false);

					this.#datatable.setTabFilterItem(this.#active_filter);
				}

				this.#scheduleRefresh();
				this.#refresh(tabfilter_changed);
			});

			$.subscribe('timeselector.rangeupdate', (e, data) => {
				if (data.idx === 'web.monitoring.problem') {
					this.#global_timerange.from = data.from;
					this.#global_timerange.to = data.to;
				}

				this.#scheduleRefresh();
				this.#refresh();
			});
		}

		#initExpandables() {
			const table = this.#datatable.getElement();
			const expandable_buttons = table.querySelectorAll('button[data-action="show_symptoms"]');

			expandable_buttons.forEach(btn => {
				['click','keydown'].forEach((type) => {
					btn.addEventListener(type, (e) => {
						if (e.type === 'click' || e.which === 13) {
							this.#showSymptoms(btn);
						}
					});
				});

				// Check if cause events were opened. If so, after (not full) refresh open them again.
				if (this.#opened_eventids.includes(btn.dataset.eventid)) {
					const rows = table.querySelectorAll(
						`.${CDataTable.ZBX_STYLE_ROW}[data-cause-eventid="${btn.dataset.eventid}"]`);

					[...rows].forEach((row) => row.classList.remove('hidden'));

					btn.classList.remove(ZBX_ICON_CHEVRON_DOWN, ZBX_STYLE_COLLAPSED);
					btn.classList.add(ZBX_ICON_CHEVRON_UP);
					btn.title = <?= json_encode(_('Collapse')); ?>;
				}
			});
		}

		#initEvents() {
			document.addEventListener('click', e => {
				if (e.target.classList.contains('js-massupdate-problem')) {
					this.#massupdate({eventids: Object.keys(chkbxRange.getSelectedIds())});
				}
			});
		}

		#massupdate({eventids}) {
			ZABBIX.PopupManager.open('acknowledge.edit', {eventids}, {supports_standalone: true});
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
						CMessageHelper.success(this.#datatable.getElement(), data.submit.success.messages,
							data.submit.success.title);
					}

					chkbxRange.checkObjectAll('eventids', false);
					chkbxRange.update('eventids');

					this.#refresh();
				}
			});
		}

		#showSymptoms(btn) {
			// Prevent multiple clicking by first disabling button.
			btn.disabled = true;

			const rows = this.#datatable.getElement()
				.querySelectorAll(`.${CDataTable.ZBX_STYLE_ROW}[data-cause-eventid="${btn.dataset.eventid}"]`);

			// Show symptom rows for the current cause.
			if (rows[0].classList.contains('hidden')) {
				btn.classList.remove(ZBX_ICON_CHEVRON_DOWN, ZBX_STYLE_COLLAPSED);
				btn.classList.add(ZBX_ICON_CHEVRON_UP);
				btn.title = <?= json_encode(_('Collapse')); ?>;

				this.#opened_eventids.push(btn.dataset.eventid);

				[...rows].forEach((row) => row.classList.remove('hidden'));
			}
			else {
				btn.classList.remove(ZBX_ICON_CHEVRON_UP);
				btn.classList.add(ZBX_ICON_CHEVRON_DOWN, ZBX_STYLE_COLLAPSED);
				btn.title = <?= json_encode(_('Expand')); ?>;

				this.#opened_eventids = this.#opened_eventids.filter((id) => id !== btn.dataset.eventid);

				[...rows].forEach((row) => row.classList.add('hidden'));
			}

			// When complete enable button again.
			btn.disabled = false;
		}

		getCurrentDebugBlock() {
			return document.querySelector('.wrapper > .debug-output');
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

		#refreshDebug(debug) {
			const debug_output = document
				.querySelector('.wrapper > main > .<?= ZBX_STYLE_DEBUG_OUTPUT_TABLE_REFRESH ?>');

			if (debug_output) {
				debug_output.classList.add('<?= ZBX_STYLE_DEBUG_OUTPUT ?>');
				debug_output.innerHTML = new DOMParser().parseFromString(debug, 'text/html')
					.querySelector('.<?= ZBX_STYLE_DEBUG_OUTPUT ?>').innerHTML;
			}
		}

		#refresh(tabfilter_changed = false) {
			if (isUserInteracting()) {
				return;
			}

			const search_params = new URLSearchParams(location.search.substring(1));
			const url_filter = searchParamsToObject(search_params);
			const filter = {...this.#filter_defaults, ...url_filter};
			const current_filter = this.#datatable.getFilter();

			if (filter.filter_custom_time == 0) {
				filter.from = this.#global_timerange.from;
				filter.to = this.#global_timerange.to;
			}

			if (current_filter.show != filter.show) {
				this.#handleShowProblemsFilter(filter);
			}

			if (!tabfilter_changed) {
				this.#datatable.updateUserConfig();
			}

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

		#handleShowProblemsFilter(filter) {
			for (const id of this.#show_problems_hidden_column_ids) {
				const column = this.#datatable.getColumnById(id);
				if (!column) {
					continue;
				}

				if (filter.show != <?= TRIGGERS_OPTION_IN_PROBLEM; ?>) {
					const overrides = column.getOverrides();

					column.setVisible(overrides?.visible ?? column.getDefaults().isVisible());
				}
				else {
					column.setVisible(false);
				}
			}
		}

		#onDataDone(response) {
			this.#removeRefreshMessage();

			if ('messages' in response) {
				this.#addRefreshMessage(response.messages);
			}

			this.#refreshCounters(response);
		}

		getDataTable() {
			return this.#datatable;
		}
	};
</script>
