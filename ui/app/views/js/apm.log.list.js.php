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
 * @var array $data
 */
?>

<script>
	const view = new class {
		#severity_classes = [
			'status-amp-severity-trace',
			'status-amp-severity-debug',
			'status-amp-severity-info',
			'status-amp-severity-warn',
			'status-amp-severity-error',
			'status-amp-severity-fatal'
		];

		#layout_mode = null;
		#refresh_interval = 0;
		#refresh_interval_id = null;
		#time_selector = null;
		/** @type {CDataTable|null} */
		#datatable = null;
		/** @type {HTMLFormElement|null} */
		#filter_form_element = null;
		/** @type {CForm|null} */
		#filter_form = null;
		/** @type {HTMLButtonElement|null} */
		#apply_filter_button = null;
		/** @type {string|null} */
		#side_drawer_position = null;
		#csrf_token = null;
		#refresh_message_box = null;
		/** @type {CSideDrawer|null} */
		#side_drawer = null;
		#rows_data = new Map();
		/** @type {number|null} */
		#selected_row_index = null;

		init({
			csrf_token,
			default_sort_field,
			default_sort_order,
			filter,
			filter_options,
			layout_mode,
			page,
			refresh_interval,
			filter_validation_rules,
			sort_field,
			sort_order,
			storage_idx,
			user_configs,
			side_drawer_position
		}) {
			this.#layout_mode = layout_mode;
			this.#refresh_interval = refresh_interval;
			this.#csrf_token = csrf_token;

			this.#filter_form_element = document.querySelector('[name="zbx_filter"]');
			this.#filter_form = new CForm(this.#filter_form_element, filter_validation_rules);
			this.#apply_filter_button = this.#filter_form_element?.querySelector('[name="filter_set"]');
			this.#side_drawer_position = side_drawer_position;

			this.#validateFormChanges();

			this.#initEvents(filter_options);
			this.#initFilter(filter_options);
			this.#initDataTable({page, filter, default_sort_field, default_sort_order, sort_field, sort_order,
				storage_idx, user_configs
			});

			this.#scheduleRefresh();
		}

		#initEvents(filter_options) {
			this.#filter_form_element?.addEventListener('input', () => this.#validateFormChanges());

			this.#filter_form_element?.addEventListener('form.validated', () => {
				const has_errors = this.#filter_form?.hasErrors() ?? false;

				this.#apply_filter_button?.toggleAttribute('disabled', has_errors);
			});

			this.#apply_filter_button?.addEventListener('click', this.#onFilterSet);

			$.subscribe('timeselector.rangeupdate', (e, data) => {
				if (data.idx === filter_options.idx) {
					this.#time_selector.from = data.from;
					this.#time_selector.to = data.to;
				}

				this.#refresh();
			});
		}

		#initFilter(filter_options) {
			this.#time_selector = {
				from: filter_options.timeselector.from,
				to: filter_options.timeselector.to
			};

			for (const type of ['log', 'resource', 'scope']) {
				$(`#filter-${type}-attributes`)
					.dynamicRows({template: `#filter-${type}-attributes-row-tmpl`})
					.on('afteradd.dynamicRows', function () {
						const rows = this.querySelectorAll('.form_row');
						new CApmAttrFilterItem(rows[rows.length - 1]);
					});

				document.querySelectorAll(`#filter-${type}-attributes .${ZBX_STYLE_FORM_ROW}`).forEach(row => {
					new CApmAttrFilterItem(row);
				});
			}
		}

		#initDataTable({page, filter, default_sort_field, default_sort_order, sort_field, sort_order, storage_idx,
				user_configs}) {

			const data_provider_url = zabbixUrl({
				action: 'apm.log.list.data',
				[CSRF_TOKEN_NAME]: this.#csrf_token
			});

			const data_provider = new CDefaultDataProvider(data_provider_url);

			this.#datatable = new CDataTable(document.getElementById('datatable-logs'), data_provider)
				.setColumns([
					new CDataTableColumn('timestamp', <?= json_encode(_('Timestamp')) ?>)
						.setFields(['timestamp_formatted'])
						.setSortable(true)
						.setRenderer('timestamp')
						.setWidth('auto'),
					new CDataTableColumn('body', <?= json_encode(_('Body')) ?>)
						.setFields(['body'])
						.setWidth('auto'),
					new CDataTableColumn('severity_text', <?= json_encode(_('Severity text')) ?>)
						.setFields(['severity_text', 'severity_number'])
						.setRenderer('severity_text')
						.setWidth('auto'),
					new CDataTableColumn('severity_number', <?= json_encode(_('Severity number')) ?>)
						.setFields(['severity_number'])
						.setWidth('auto'),
					new CDataTableColumn('traceid', <?= json_encode(_('Trace ID')) ?>)
						.setFields(['traceid'])
						.setWidth('auto')
						.setVisible(false),
					new CDataTableColumn('spanid', <?= json_encode(_('Span ID')) ?>)
						.setFields(['spanid'])
						.setWidth('auto')
						.setVisible(false),
					new CDataTableColumn('flags', <?= json_encode(_('Flags')) ?>)
						.setFields(['flags'])
						.setRenderer('flags')
						.setWidth('auto')
						.setVisible(false),
					new CDataTableColumn('service_name', <?= json_encode(_('Service name')) ?>)
						.setFields(['service_name'])
						.setWidth('auto')
						.setVisible(false),
					new CDataTableColumn('body', <?= json_encode(_('Body')) ?>)
						.setFields(['body'])
						.setWidth('auto')
						.setVisible(false),
					new CDataTableColumn('scope_name', <?= json_encode(_('Scope name')) ?>)
						.setFields(['scope_name'])
						.setWidth('auto')
						.setVisible(false),
					new CDataTableColumn('scope_version', <?= json_encode(_('Scope version')) ?>)
						.setFields(['scope_version'])
						.setWidth('auto')
						.setVisible(false),
					new CDataTableColumn('event_name', <?= json_encode(_('Event name')) ?>)
						.setFields(['event_name'])
						.setWidth('auto')
						.setVisible(false)
				])
				.setPage(page)
				.setFilter({...filter, ...this.#time_selector})
				.setDefaultSortField(default_sort_field)
				.setDefaultSortOrder(default_sort_order)
				.setSortField(sort_field)
				.setSortOrder(sort_order)
				.setStorageIdx(storage_idx)
				.setStickyHeader(true)
				.setStickyFooter(true)
				.setCellRenderer('timestamp', ({cell, cell_data}) => {
					const [timestamp_formatted] = cell_data;

					/** @type {HTMLDivElement} */
					const wordbreak = document.createElement('div');
					wordbreak.classList.add(ZBX_STYLE_WORDBREAK, 'wordbreak-clamp');
					wordbreak.style.setProperty('--line-clamp', '2');
					wordbreak.textContent = timestamp_formatted;

					cell.appendChild(wordbreak);
				})
				.setCellRenderer('severity_text', ({cell, cell_data}) => {
					const [severity_text, severity_number] = cell_data;

					const severity_class = severity_number >= 1 && severity_number <= 24
						? this.#severity_classes[Math.floor((severity_number - 1) / 4)]
						: this.#severity_classes[0];

					const severity_span = document.createElement('span');
					severity_span.classList.add(severity_class);
					severity_span.innerText = severity_text;

					const container = document.createElement('div');
					container.classList.add(ZBX_STYLE_STATUS_CONTAINER);
					container.appendChild(severity_span);

					cell.appendChild(container);
				})
				.setCellRenderer('flags', ({cell, cell_data}) => {
					const [flags] = cell_data;

					cell.textContent = this.#decodeFlags(flags);
				})
				.setRowRenderer('log', ({columns, data_fields, row, row_data, row_index, response}) => {
					row.dataset.rowIndex = row_index;

					const row_data_o = Object.create(null);

					for (const [index, key] of data_fields.entries()) {
						row_data_o[key] = row_data[index];
					}

					this.#rows_data.set(row_index, row_data_o);

					this.#datatable.renderDataCells({columns, data_fields, row, row_data, row_index, response});
				})
				.on(CMessageHelper.EVENT_MESSAGE, e => {
					e.stopPropagation();

					const {type, title, messages} = e.detail;

					clearMessages();
					addMessage(makeMessageBox(type, messages, title));
				})
				.on(CPager.EVENT_SELECT, () => {
					this.#side_drawer?.close();

					this.#scheduleRefresh();
				})
				.on(CPager.EVENT_STATE_CHANGE, e => {
					const {page} = e.detail;

					new CState().setParams({page});
				})
				.on(CDataTable.EVENT_RENDER, e => {
					const response = e.detail.response;

					if ('debug' in response) {
						this.#refreshDebug(response.debug);
					}

					this.#rows_data.clear();
				})
				.on(CDataTable.EVENT_AFTER_RENDER, () => {
					const datatable_element = this.#datatable.getElement();
					const rows = datatable_element.querySelectorAll(`.${CDataTable.ZBX_STYLE_ROW}`);

					for (const row of rows) {
						const row_index = parseInt(row.getAttribute('data-row-index'));

						if (row_index === this.#selected_row_index) {
							row.classList.add(CDataTable.ZBX_STYLE_ROW_SELECTED);
						}

						row.addEventListener('click', () => {
							if (row.hasAttribute('data-hintbox')) {
								return;
							}

							if (this.#selected_row_index === row_index) {
								this.#side_drawer.close();

								return;
							}

							datatable_element.querySelector(`.${CDataTable.ZBX_STYLE_ROW_SELECTED}`)
								?.classList.remove(CDataTable.ZBX_STYLE_ROW_SELECTED);

							row.classList.add(CDataTable.ZBX_STYLE_ROW_SELECTED);

							this.#selected_row_index = row_index;

							this.#openSideDrawer(this.#rows_data.get(this.#selected_row_index));
						});
					}
				})
				.on(CDataTable.EVENT_DATA_SORT, () => this.#scheduleRefresh())
				.on(CDataTable.EVENT_OPTIONS_POPUP_OPEN, () => this.#unscheduleRefresh())
				.on(CDataTable.EVENT_OPTIONS_POPUP_CLOSE, () => this.#scheduleRefresh())
				.on(CDataTable.EVENT_COLUMN_RESIZE_START, () => this.#unscheduleRefresh())
				.on(CDataTable.EVENT_COLUMN_RESIZE_END, () => this.#scheduleRefresh())
				.init(user_configs);
		}

		#openSideDrawer(row_data) {
			if (this.#side_drawer === null) {
				const container = document.querySelector(`.${ZBX_STYLE_LAYOUT_WRAPPER}`);

				this.#side_drawer = new CSideDrawer(container, {
					content_pane_class: ZBX_STYLE_LAYOUT_WRAPPER,
					position: this.#side_drawer_position,
					position_min: '10%',
					position_max: '90%'
				});
				this.#side_drawer.on(CSideDrawer.EVENT_OPEN, e => this.#onSideDrawerOpen(e));
				this.#side_drawer.on(CSideDrawer.EVENT_CLOSE, e => this.#onSideDrawerClose(e));
				this.#side_drawer.on(CSideDrawer.EVENT_POSITION, e => this.#onSideDrawerPosition(e));
			}

			this.#side_drawer.open(Promise.resolve(row_data));
		}

		#onSideDrawerOpen = e => {
			new CDetailsPanel(this.#side_drawer.getElement(), {
				title: <?= json_encode(_('Log details')) ?>,
				groups: this.#createDetailGroups(e.detail.response)
			});

			this.#unscheduleRefresh();
		}

		#onSideDrawerClose = () => {
			this.#selected_row_index = null;

			const datatable_element = this.#datatable.getElement();

			datatable_element.querySelector(`.${CDataTable.ZBX_STYLE_ROW_SELECTED}`)
				?.classList.remove(CDataTable.ZBX_STYLE_ROW_SELECTED);

			this.#scheduleRefresh();
		}

		#onSideDrawerPosition = e => {
			this.#side_drawer_position = e.detail.position;

			updateUserProfile('web.apm.log.side_drawer.position', this.#side_drawer_position, [], PROFILE_TYPE_STR);
		}

		#validateFormChanges() {
			const values = this.#filter_form?.getAllValues() ?? {};

			this.#filter_form?.validateChanges(Object.keys(values), true);
		}

		#createDetailGroups(row_data) {
			return [
				{
					title: <?= json_encode(_('Basic information')) ?>,
					items: [
						{
							name: <?= json_encode(_('Timestamp')) ?>,
							value: row_data.timestamp_ns_formatted
						},
						{
							name: <?= json_encode(_('Trace ID')) ?>,
							value: row_data.traceid
						},
						{
							name: <?= json_encode(_('Span ID')) ?>,
							value: row_data.spanid
						},
						{
							name: <?= json_encode(_('Flags')) ?>,
							value: this.#decodeFlags(row_data.flags)
						},
						{
							name: <?= json_encode(_('Severity text')) ?>,
							value: row_data.severity_text
						},
						{
							name: <?= json_encode(_('Severity number')) ?>,
							value: row_data.severity_number
						},
						{
							name: <?= json_encode(_('Service name')) ?>,
							value: row_data.service_name
						},
						{
							name: <?= json_encode(_('Body')) ?>,
							value: row_data.body
						},
						{
							name: <?= json_encode(_('Resource schema URL')) ?>,
							value: row_data.resource_schema_url
						},
						{
							name: <?= json_encode(_('Scope schema URL')) ?>,
							value: row_data.scope_schema_url
						},
						{
							name: <?= json_encode(_('Scope name')) ?>,
							value: row_data.resource_name
						},
						{
							name: <?= json_encode(_('Scope version')) ?>,
							value: row_data.resource_name
						},
						{
							name: <?= json_encode(_('Event name')) ?>,
							value: row_data.event_name
						}
					]
				},
				{
					title: 'Log attributes',
					items: Object.entries(row_data.log_attributes).map(
						([name, value]) => Object.fromEntries([['name', name], ['value', value]])
					)
				},
				{
					title: 'Resource attributes',
					items: Object.entries(row_data.resource_attributes).map(
						([name, value]) => Object.fromEntries([['name', name], ['value', value]])
					)
				},
				{
					title: 'Scope attributes',
					items: Object.entries(row_data.scope_attributes).map(
						([name, value]) => Object.fromEntries([['name', name], ['value', value]])
					)
				}
			];
		}

		#decodeFlags(flags) {
			const flags_decoded = [];

			if ((flags & 1) === 1) {
				flags_decoded.push(<?= json_encode(_('Sampled')) ?>);
			}

			return flags_decoded.join(', ');
		}

		#onFilterSet = (e) => {
			e.preventDefault();

			if (!this.#filter_form_element) {
				return false;
			}

			const values = this.#filter_form.getAllValues();

			this.#filter_form.validateSubmit(values).then(result => {
				if (result) {
					chkbxRange.clearSelectedOnFilterChange();

					this.#apply_filter_button?.removeEventListener('click', this.#onFilterSet);
					this.#apply_filter_button?.dispatchEvent(new PointerEvent('click'));
				}
			});

			return false;
		}

		#addRefreshMessage(messages) {
			this.#removeRefreshMessage();

			this.#refresh_message_box = $($.parseHTML(messages));
			addMessage(this.#refresh_message_box);
		}

		#removeRefreshMessage() {
			this.#refresh_message_box?.remove();
			this.#refresh_message_box = null;
		}

		#refreshDebug(debug) {
			const debug_output = document
				.querySelector(`.wrapper > main > .${ZBX_STYLE_DEBUG_OUTPUT_TABLE_REFRESH}`);

			if (debug_output) {
				debug_output.classList.add(ZBX_STYLE_DEBUG_OUTPUT);
				debug_output.innerHTML = new DOMParser().parseFromString(debug, 'text/html')
					.querySelector(`.${ZBX_STYLE_DEBUG_OUTPUT}`).innerHTML;
			}
		}

		#refresh({loading_fadein = false} = {}) {
			if (this.#datatable.isUserInteracting()) {
				return;
			}

			this.#unscheduleRefresh();

			const filter = this.#datatable.getFilter();

			this.#datatable
				.setFilter({...filter, ...this.#time_selector})
				.updateUserConfig()
				.dispatchEvent(CDataTable.EVENT_INIT, {
					check_changes: false,
					force_load: true,
					loading_fadein,
					onSuccess: response => this.#onSuccess(response),
					onFinally: () => this.#scheduleRefresh()
				});
		}

		#scheduleRefresh() {
			if (this.#refresh_interval === 0) {
				return;
			}

			this.#unscheduleRefresh();

			this.#refresh_interval_id = setInterval(
				() => this.#refresh({loading_fadein: true}),
				this.#refresh_interval
			);
		}

		#unscheduleRefresh() {
			clearInterval(this.#refresh_interval_id);
			this.#refresh_interval_id = null;
		}

		#onSuccess(response) {
			this.#removeRefreshMessage();

			if ('messages' in response) {
				this.#addRefreshMessage(response.messages);
			}
		}
	}
</script>
