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
		#layout_mode = null;
		#refresh_interval = 0;
		#refresh_interval_id = null;
		#global_timerange = null;
		#datatable = null;
		#csrf_token = null;
		#refresh_message_box = null;
		#apply_filter_button = null;
		#filter_form_element = null;
		#filter_form = null;
		#side_drawer = null;
		#metric_view_page = null;
		#rows_data = new Map();
		#selected_row_index = null;
		#metric_types = null;

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
			metric_types
		}) {
			this.#layout_mode = layout_mode;
			this.#refresh_interval = refresh_interval;
			this.#csrf_token = csrf_token;

			this.#metric_types = metric_types;

			this.#filter_form_element = document.querySelector('[name="zbx_filter"]');
			this.#filter_form = new CForm(this.#filter_form_element, filter_validation_rules);
			this.#apply_filter_button = this.#filter_form_element?.querySelector('[name="filter_set"]');

			this.#validateFormChanges();

			this.#initEvents(filter_options);
			this.#initFilter(filter_options);
			this.#initDataTable({page, filter, default_sort_field, default_sort_order, sort_field, sort_order,
				storage_idx, user_configs});

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
					this.#global_timerange.from = data.from;
					this.#global_timerange.to = data.to;
				}

				this.#refresh();
			});
		}

		#initFilter(filter_options) {
			this.#global_timerange = {
				from: filter_options.timeselector.from,
				to: filter_options.timeselector.to
			};

			$('#filter-attributes')
				.dynamicRows({template: '#filter-attributes-row-tmpl'})
				.on('afteradd.dynamicRows', function () {
					const rows = this.querySelectorAll('.form_row');
					new CApmAttrFilterItem(rows[rows.length - 1]);
				});

			document.querySelectorAll(`#filter-attributes .${ZBX_STYLE_FORM_ROW}`).forEach(row => {
				new CApmAttrFilterItem(row);
			});

			$('#filter-resource-attributes')
				.dynamicRows({template: '#filter-resource-attributes-row-tmpl'})
				.on('afteradd.dynamicRows', function () {
					const rows = this.querySelectorAll('.form_row');
					new CApmAttrFilterItem(rows[rows.length - 1]);
				});

			document.querySelectorAll(`#filter-resource-attributes .${ZBX_STYLE_FORM_ROW}`).forEach(row => {
				new CApmAttrFilterItem(row);
			});

			$('#filter-scope-attributes')
				.dynamicRows({template: '#filter-scope-attributes-row-tmpl'})
				.on('afteradd.dynamicRows', function () {
					const rows = this.querySelectorAll('.form_row');
					new CApmAttrFilterItem(rows[rows.length - 1]);
				});

			document.querySelectorAll(`#filter-scope-attributes .${ZBX_STYLE_FORM_ROW}`).forEach(row => {
				new CApmAttrFilterItem(row);
			});
		}

		#initDataTable({page, filter, default_sort_field, default_sort_order, sort_field, sort_order, storage_idx,
				user_configs}) {

			const data_provider_url = zabbixUrl({
				action: 'apm.metric.list.data',
				[CSRF_TOKEN_NAME]: this.#csrf_token
			});

			const data_provider = new CDefaultDataProvider(data_provider_url);

			this.#datatable = new CDataTable(document.getElementById('datatable-metrics'), data_provider)
				.setColumns([
					new CDataTableColumn('metric_name', <?= json_encode(_('Metric name')); ?>)
						.setFields(['metric_name'])
						.setRenderer(['name'])
						.setSortable(true)
						.setWidth('auto'),
					new CDataTableColumn('type', <?= json_encode(_('Type')); ?>)
						.setFields(['type'])
						.setRenderer('type')
						.setWidth('auto'),
					new CDataTableColumn('metric_unit', <?= json_encode(_('Unit')); ?>)
						.setFields(['metric_unit'])
						.setWidth('auto'),
					new CDataTableColumn('service_name', <?= json_encode(_('Service name')); ?>)
						.setFields(['service_name'])
						.setRenderer(['name'])
						.setWidth('auto'),
					new CDataTableColumn('scope_name', <?= json_encode(_('Scope name')); ?>)
						.setFields(['scope_name'])
						.setRenderer(['name'])
						.setVisible(false)
						.setWidth('auto'),
					new CDataTableColumn('start_time_unix', <?= json_encode(_('Start time')); ?>)
						.setFields(['start_time_unix'])
						.setSortable(true)
						.setRenderer('start_time_unix')
						.setWidth('auto'),
					new CDataTableColumn('value', <?= json_encode(_('Sum/Value')); ?>)
						.setFields(['value'])
						.setWidth('auto'),
					new CDataTableColumn('count', <?= json_encode(_('Count')); ?>)
						.setFields(['count'])
						.setWidth('auto')
				])
				.setPage(page)
				.setFilter({
					...filter,
					from: this.#global_timerange.from,
					to: this.#global_timerange.to,
				})
				.setDefaultSortField(default_sort_field)
				.setDefaultSortOrder(default_sort_order)
				.setSortField(sort_field)
				.setSortOrder(sort_order)
				.setStickyHeader(true)
				.setStickyFooter(true)
				.setStorageIdx(storage_idx)
				.setRowRenderer('metric', ({columns, data_fields, row, row_data, row_index, response}) => {
					row.dataset.rowIndex = row_index;

					const row_data_o = Object.create(null);

					for (const [index, key] of data_fields.entries()) {
						row_data_o[key] = row_data[index];
					}

					this.#rows_data.set(row_index, row_data_o);

					this.#datatable.renderDataCells({columns, data_fields, row, row_data, row_index, response});
				})
				.setCellRenderer('name', ({cell, cell_data}) => {
					const [data] = cell_data;

					const name = document.createElement('div');
					name.classList.add(ZBX_STYLE_OVERFLOW_ELLIPSIS);
					name.textContent = data;

					const flex_wrapper = document.createElement('div');
					flex_wrapper.classList.add(ZBX_STYLE_FLEX_WRAPPER);
					flex_wrapper.appendChild(name);

					cell.appendChild(flex_wrapper);
				})
				.setCellRenderer('start_time_unix', ({cell, cell_data}) => {
					const [start_time_unix] = cell_data;

					/** @type {HTMLDivElement} */
					const wordbreak = document.createElement('div');
					wordbreak.classList.add(ZBX_STYLE_WORDBREAK, 'wordbreak-clamp');
					wordbreak.style.setProperty('--line-clamp', '2');
					wordbreak.textContent = start_time_unix;

					cell.appendChild(wordbreak);
				})
				.setCellRenderer('type', ({cell, cell_data}) => {
					const [type] = cell_data;

					const content = this.#metric_types[type].label;

					/** @type {HTMLDivElement} */
					const wordbreak = document.createElement('div');
					wordbreak.classList.add(ZBX_STYLE_WORDBREAK, 'wordbreak-clamp');
					wordbreak.style.setProperty('--line-clamp', '2');
					wordbreak.textContent = content;

					cell.appendChild(wordbreak);
				})
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

					if ('debug' in response) {
						this.#refreshDebug(response.debug);
					}
				})
				.on(CDataTable.EVENT_DATA_SORT, () => this.#scheduleRefresh())
				.on(CDataTable.EVENT_OPTIONS_POPUP_OPEN, () => this.#unscheduleRefresh())
				.on(CDataTable.EVENT_OPTIONS_POPUP_CLOSE, () => this.#scheduleRefresh())
				.on(CDataTable.EVENT_COLUMN_RESIZE_START, () => this.#unscheduleRefresh())
				.on(CDataTable.EVENT_COLUMN_RESIZE_END, () => this.#scheduleRefresh())
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
				.init(user_configs);
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

			this.#datatable
				.setFilter({
					...this.#datatable.getFilter(),
					from: this.#global_timerange.from,
					to: this.#global_timerange.to,
				})
				.updateUserConfig()
				.dispatchEvent(CDataTable.EVENT_INIT, {
					check_changes: false,
					force_load: true,
					loading_fadein,
					onSuccess: response => this.#onSuccess(response),
					onFinally: () => this.#scheduleRefresh()
				});
		}

		#openSideDrawer(data) {
			if (this.#side_drawer === null) {
				const container = document.querySelector(`.${ZBX_STYLE_LAYOUT_WRAPPER}`);

				this.#side_drawer = new CSideDrawer(container, {content_pane_class: ZBX_STYLE_LAYOUT_WRAPPER});
				this.#side_drawer.on(CSideDrawer.EVENT_OPEN, e => this.#onSideDrawerOpen(e));
				this.#side_drawer.on(CSideDrawer.EVENT_CLOSE, e => this.#onSideDrawerClose(e));
			}

			this.#side_drawer.open(Promise.resolve(data));
		}

		#onSideDrawerOpen = e => {
			const element = this.#side_drawer.getElement();

			this.#metric_view_page = new CDetailsPanel(element, this.#prepareDetailsData(e.detail.response));

			this.#unscheduleRefresh();
		}

		#onSideDrawerClose = () => {
			const datatable_element = this.#datatable.getElement();

			const row_selected = datatable_element.querySelector(`.${CDataTable.ZBX_STYLE_ROW_SELECTED}`);
			row_selected?.classList.remove(CDataTable.ZBX_STYLE_ROW_SELECTED);

			this.#selected_row_index = null;
			this.#metric_view_page = null;

			this.#scheduleRefresh();
		}

		#validateFormChanges() {
			const values = this.#filter_form?.getAllValues() ?? {};

			this.#filter_form?.validateChanges(Object.keys(values), true);
		}

		#onFilterSet = e => {
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

		#prepareDetailsData(data) {
			const details = {
				title: data.metric_name,
				groups: [
					{
						title: <?= json_encode(_('Basic information')) ?>,
						items: [
							{
								name: <?= json_encode(_('Metric name')) ?>,
								value: data.metric_name
							},
							{
								name: <?= json_encode(_('Type')) ?>,
								value: this.#metric_types[data.type].label
							},
							{
								name: <?= json_encode(_('Unit')) ?>,
								value: data.metric_unit
							},
							{
								name: <?= json_encode(_('Service name')) ?>,
								value: data.service_name
							},
							{
								name: <?= json_encode(_('Start time')) ?>,
								value: data.start_time_unix
							},
							{
								name: <?= json_encode(_('Sum/Value')) ?>,
								value: data.value ?? data.sum
							},
							{
								name: <?= json_encode(_('Min')) ?>,
								value: data.min
							},
							{
								name: <?= json_encode(_('Max')) ?>,
								value: data.max
							},
							{
								name: <?= json_encode(_('Count')) ?>,
								value: data.count
							},
							{
								name: <?= json_encode(_('Zero count')) ?>,
								value: data.zero_count
							},
							{
								name: <?= json_encode(_('Scale')) ?>,
								value: data.scale
							},
							{
								name: <?= json_encode(_('Metric description')) ?>,
								value: data.metric_description
							},
							{
								name: <?= json_encode(_('Time')) ?>,
								value: data.time_unix
							},
							{
								name: <?= json_encode(_('Resource schema url')) ?>,
								value: data.resource_schema_url
							},
							{
								name: <?= json_encode(_('Bucket counts')) ?>,
								value: data.bucket_counts
							},
							{
								name: <?= json_encode(_('Positive offset')) ?>,
								value: data.positive_offset
							},
							{
								name: <?= json_encode(_('Positive bucket counts')) ?>,
								value: data.positive_bucket_counts
							},
							{
								name: <?= json_encode(_('Negative offset')) ?>,
								value: data.negative_offset
							},
							{
								name: <?= json_encode(_('Negative bucket counts')) ?>,
								value: data.negative_bucket_counts
							},
							{
								name: <?= json_encode(_('Aggregation temporality')) ?>,
								value: data.aggregation_temporality
							},
							{
								name: <?= json_encode(_('Monotonic')) ?>,
								value: data.is_monotonic ? 'true' : 'false'
							}
						]
					},
					{
						title: <?= json_encode(_('Attributes')) ?>,
						items: Object.entries(data.attributes).map(
							([name, value]) => Object.fromEntries([['name', name], ['value', value]])
						)
					},
					{
						title: <?= json_encode(_('Resource attributes')) ?>,
						items: Object.entries(data.resource_attributes).map(
							([name, value]) => Object.fromEntries([['name', name], ['value', value]])
						)
					},
					{
						title: <?= json_encode(_('Scope attributes')) ?>,
						items: Object.entries(data.scope_attributes).map(
							([name, value]) => Object.fromEntries([['name', name], ['value', value]])
						)
					},
					{
						title: <?= json_encode(_('Scope')) ?>,
						items: [
							{
								name: <?= json_encode(_('name')) ?>,
								value: data.scope_name
							},
							{
								name: <?= json_encode(_('version')) ?>,
								value: data.scope_version
							},
							{
								name: <?= json_encode(_('schema url')) ?>,
								value: data.scope_schema_url
							},
						]
					}
				]
			}

			return details;
		}
	}
</script>
