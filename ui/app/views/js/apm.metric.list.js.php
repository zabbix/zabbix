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
		#refresh_interval = 0;
		#refresh_interval_id = null;
		#global_timerange = null;
		/** @type {CDataTable|null} */
		#datatable = null;
		#csrf_token = null;
		#refresh_message_box = null;
		#apply_filter_button = null;
		/** @type {string|null} */
		#side_drawer_position = null;
		/** @type {HTMLButtonElement|null} */
		#filter_form_element = null;
		/** @type {HTMLFormElement|null} */
		#filter_form = null;
		/** @type {CSideDrawer|null} */
		#side_drawer = null;
		/** @type {CDetailsPanel|null} */
		#details_panel = null;
		/** @type {Map} */
		#rows_data = new Map();
		#selected_row_index = null;
		#metric_types = null;
		#aggregation_temporality_labels = null;
		#flags_labels = null;

		init({
			csrf_token,
			default_sort_field,
			default_sort_order,
			filter,
			filter_options,
			page,
			refresh_interval,
			filter_validation_rules,
			sort_field,
			sort_order,
			storage_idx,
			user_configs,
			side_drawer_position,
			metric_types
		}) {
			this.#refresh_interval = refresh_interval;
			this.#csrf_token = csrf_token;

			this.#metric_types = metric_types;

			this.#filter_form_element = document.querySelector('[name="zbx_filter"]');
			this.#filter_form = new CForm(this.#filter_form_element, filter_validation_rules);
			this.#apply_filter_button = this.#filter_form_element?.querySelector('[name="filter_set"]');
			this.#side_drawer_position = side_drawer_position;

			this.#aggregation_temporality_labels = {
				[APM_METRIC_AGGREGATION_TEMPORALITY_UNSPECIFIED]: <?= json_encode(_('Unspecified')) ?>,
				[APM_METRIC_AGGREGATION_TEMPORALITY_DELTA]: <?= json_encode(_('Delta')) ?>,
				[APM_METRIC_AGGREGATION_TEMPORALITY_CUMULATIVE]: <?= json_encode(_('Cumulative')) ?>
			};

			this.#flags_labels = {
				[APM_METRIC_FLAG_NONE]: <?= json_encode(_('None')) ?>,
				[APM_METRIC_FLAG_NO_RECODED_VALUE]: <?= json_encode(_('No recoded value')) ?>
			};

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
						.setRenderer(['text_field'])
						.setSortable(true)
						.setWidth('auto'),
					new CDataTableColumn('type', <?= json_encode(_('Type')); ?>)
						.setFields(['type'])
						.setSortable(true)
						.setRenderer(['type'])
						.setWidth('auto'),
					new CDataTableColumn('metric_unit', <?= json_encode(_('Unit')); ?>)
						.setFields(['metric_unit'])
						.setSortable(true)
						.setRenderer(['text_field'])
						.setWidth('auto'),
					new CDataTableColumn('service_name', <?= json_encode(_('Service name')); ?>)
						.setFields(['service_name'])
						.setSortable(true)
						.setRenderer(['text_field'])
						.setWidth('auto'),
					new CDataTableColumn('scope_name', <?= json_encode(_('Scope name')); ?>)
						.setFields(['scope_name'])
						.setSortable(true)
						.setRenderer(['text_field'])
						.setVisible(false)
						.setWidth('auto'),
					new CDataTableColumn('start_time_formatted', <?= json_encode(_('Start time')); ?>)
						.setFields(['start_time_formatted'])
						.setSortable(true)
						.setRenderer(['time_formatted'])
						.setWidth('auto'),
					new CDataTableColumn('value', <?= json_encode(_('Sum/Value')); ?>)
						.setFields(['value'])
						.setRenderer(['value'])
						.setWidth('auto'),
					new CDataTableColumn('count', <?= json_encode(_('Count')); ?>)
						.setFields(['count'])
						.setRenderer(['count'])
						.setWidth('auto'),
				new CDataTableColumn('time_formatted', <?= json_encode(_('Time')); ?>)
						.setFields(['time_formatted'])
						.setSortable(true)
						.setVisible(false)
						.setRenderer(['time_formatted'])
						.setWidth('auto'),
					new CDataTableColumn('flags', <?= json_encode(_('Flags')); ?>)
						.setFields(['flags'])
						.setVisible(false)
						.setRenderer(['flags'])
						.setWidth('auto'),
					new CDataTableColumn('aggregation_temporality', <?= json_encode(_('Aggregation temporality')); ?>)
						.setFields(['aggregation_temporality'])
						.setVisible(false)
						.setRenderer(['aggregation_temporality'])
						.setWidth('auto'),
					new CDataTableColumn('metric_description', <?= json_encode(_('Metric description')); ?>)
						.setFields(['metric_description'])
						.setVisible(false)
						.setRenderer(['metric_description'])
						.setWidth('auto'),
					new CDataTableColumn('attributes', <?= json_encode(_('Attributes')); ?>)
						.setFields(['attributes'])
						.setVisible(false)
						.setRenderer(['attributes'])
						.setColumnOptions({
							number_of_attributes: 3
						})
						.setWidth('auto'),
					new CDataTableColumn('resource_attributes', <?= json_encode(_('Resource attributes')); ?>)
						.setFields(['resource_attributes'])
						.setVisible(false)
						.setRenderer(['attributes'])
						.setColumnOptions({
							number_of_attributes: 3
						})
						.setWidth('auto'),
					new CDataTableColumn('scope_attributes', <?= json_encode(_('Scope attributes')); ?>)
						.setFields(['scope_attributes'])
						.setVisible(false)
						.setRenderer(['attributes'])
						.setColumnOptions({
							number_of_attributes: 3
						})
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
				.setCellRenderer('text_field', ({cell, cell_data}) => {
					const [data] = cell_data;

					cell.appendChild(this.#prepareTextCell(data));
				})
				.setCellRenderer('time_formatted', ({cell, cell_data}) => {
					const [time] = cell_data;

					/** @type {HTMLDivElement} */
					const wordbreak = document.createElement('div');
					wordbreak.classList.add(ZBX_STYLE_WORDBREAK, 'wordbreak-clamp');
					wordbreak.style.setProperty('--line-clamp', '2');
					wordbreak.textContent = time;

					cell.appendChild(wordbreak);
				})
				.setCellRenderer('type', ({cell, cell_data}) => {
					const [type] = cell_data;
					const value = this.#metric_types[type].label;

					cell.appendChild(this.#prepareTextCell(value));
				})
				.setCellRenderer('value', ({row_index, cell}) => {
					const data = this.#rows_data.get(row_index);
					const value = [APM_METRIC_TYPE_HISTOGRAM, APM_METRIC_TYPE_EXPONENTIAL_HISTOGRAM].includes(data.type)
						? data.sum
						: data.value;

					cell.appendChild(this.#prepareTextCell(value, value));
				})
				.setCellRenderer('count', ({row_index, cell}) => {
					const data = this.#rows_data.get(row_index);
					const value = [APM_METRIC_TYPE_HISTOGRAM, APM_METRIC_TYPE_EXPONENTIAL_HISTOGRAM].includes(data.type)
						? data.count
						: '';

					cell.appendChild(this.#prepareTextCell(value, value ?? null));
				})
				.setCellRenderer('aggregation_temporality', ({row_index, cell}) => {
					const data = this.#rows_data.get(row_index);
					const value = this.#aggregation_temporality_labels[data.aggregation_temporality];

					cell.appendChild(this.#prepareTextCell(value));
				})
				.setCellRenderer('flags', ({row_index, cell}) => {
					const data = this.#rows_data.get(row_index);
					const value = data.flags & 1 !== 0
						? this.#flags_labels[APM_METRIC_FLAG_NO_RECODED_VALUE]
						: this.#flags_labels[APM_METRIC_FLAG_NONE];

					cell.appendChild(this.#prepareTextCell(value));
				})
				.setCellRenderer('metric_description', ({cell, cell_data}) => {
					const [metric_description] = cell_data;

					const wordbreak = document.createElement('span');
					wordbreak.classList.add(ZBX_STYLE_WORDBREAK, 'wordbreak-clamp');
					wordbreak.style.setProperty('--line-clamp', '2');
					wordbreak.textContent = metric_description;
					wordbreak.dataset.hintbox = '1';
					wordbreak.dataset.hintboxStatic = '1';
					wordbreak.dataset.hintboxHtml = metric_description;

					cell.appendChild(wordbreak);
				})
				.setCellRenderer('attributes', ({column, cell, cell_data}) => {
					const [attributes] = cell_data;

					if (!attributes || attributes.length === 0) {
						return;
					}

					const attribute_labels = [];

					const tags_wrapper = document.createElement('div');
					tags_wrapper.classList.add(ZBX_STYLE_TAGS_WRAPPER);

					const column_options = column.getColumnOptions();

					let count = column_options.number_of_attributes;

					for (const [attr_name, attr_value] of Object.entries(attributes)) {
						const content = `${attr_name}: ${attr_value}`
						const span_attribute_label = document.createElement('span');
						span_attribute_label.classList.add(ZBX_STYLE_TAG);
						span_attribute_label.textContent = content;
						span_attribute_label.dataset.hintbox = '1';
						span_attribute_label.dataset.hintboxStatic = '1';
						span_attribute_label.dataset.hintboxHtml = content;
						span_attribute_label.ariaExpanded = 'false';

						attribute_labels.push(span_attribute_label);

						if (count > 0) {
							tags_wrapper.appendChild(span_attribute_label);

							count--;
						}
					}

					if (Object.keys(attributes).length > column_options.number_of_attributes) {
						const more_attributes_hintbox = document.createElement('div');

						for (const tag_label of attribute_labels) {
							more_attributes_hintbox.appendChild(tag_label.cloneNode(true));
						}

						const more_attributes = document.createElement('button');
						more_attributes.classList.add(ZBX_STYLE_BTN_ICON, ZBX_ICON_MORE);
						more_attributes.dataset.hintboxHtml = more_attributes_hintbox.innerHTML;
						more_attributes.dataset.hintboxClass = `${ZBX_STYLE_HINTBOX_WRAP} ${ZBX_STYLE_TAGS_WRAPPER}`;
						more_attributes.dataset.hintbox = '1';
						more_attributes.dataset.hintboxStatic = '1';
						more_attributes.ariaExpanded = 'false';
						more_attributes.ariaLabel = t('Show all attributes');

						tags_wrapper.appendChild(more_attributes);
					}

					cell.appendChild(tags_wrapper);
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

						row.addEventListener('click', (e) => {
							if (e.target.hasAttribute('data-hintbox')) {
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

			this.#side_drawer.open(Promise.resolve(data));
		}

		#onSideDrawerOpen = e => {
			const element = this.#side_drawer.getElement();

			this.#details_panel = new CDetailsPanel(element, this.#prepareDetailsData(e.detail.response));

			this.#unscheduleRefresh();
		}

		#onSideDrawerClose = () => {
			const datatable_element = this.#datatable.getElement();

			const row_selected = datatable_element.querySelector(`.${CDataTable.ZBX_STYLE_ROW_SELECTED}`);
			row_selected?.classList.remove(CDataTable.ZBX_STYLE_ROW_SELECTED);

			this.#selected_row_index = null;
			this.#details_panel = null;

			this.#scheduleRefresh();
		}

		#onSideDrawerPosition = e => {
			this.#side_drawer_position = e.detail.position;

			updateUserProfile('web.apm.metric.side_drawer.position', this.#side_drawer_position, [], PROFILE_TYPE_STR);
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

		#prepareTextCell(value, hint = null) {
			const content = document.createElement('div');
			content.classList.add(ZBX_STYLE_OVERFLOW_ELLIPSIS);
			content.textContent = value;

			if (hint !== null) {
				content.dataset.hintbox = '1';
				content.dataset.hintboxStatic = '1';
				content.dataset.hintboxHtml = value;
			}
			else {
				content.title = value;
			}

			const flex_wrapper = document.createElement('div');
			flex_wrapper.classList.add(ZBX_STYLE_FLEX_WRAPPER);
			flex_wrapper.appendChild(content);

			return flex_wrapper;
		}

		#createLink(name, url_params) {
			const link = document.createElement('a');
			link.href = zabbixUrl(url_params);
			link.ariaLabel = name;
			link.innerText = name;

			return link;
		}

		#prepareExemplarsGroups(exemplars) {
			const groups = [];

			exemplars.forEach((exemplar, i) => {
				const traceid_traces_link = this.#createLink(<?= json_encode(_('Traces')) ?>, {
					action: 'apm.trace.list',
					filter_traceid: exemplar.traceid,
					filter_set: 1
				});

				const spanid_traces_link = this.#createLink(<?= json_encode(_('Traces')) ?>, {
					action: 'apm.trace.list',
					filter_spanid: exemplar.spanid,
					filter_set: 1
				});

				const traceid_logs_link =  this.#createLink(<?= json_encode(_('Logs')) ?>, {
					action: 'apm.log.list',
					filter_traceid: exemplar.traceid,
					filter_set: 1
				});

				const spanid_logs_link =  this.#createLink(<?= json_encode(_('Logs')) ?>, {
					action: 'apm.log.list',
					filter_spanid: exemplar.spanid,
					filter_set: 1
				});

				const traceid_div = document.createElement('div');
				traceid_div.append(exemplar.traceid, ' ', traceid_logs_link, ' ', traceid_traces_link);

				const spanid_div = document.createElement('div');
				spanid_div.append(exemplar.spanid, ' ', spanid_logs_link, ' ', spanid_traces_link);

				const group = {
					title: sprintf(<?= json_encode(_('Exemplar  %1$s')) ?>, i + 1),
					items: [
						{
							name: <?= json_encode(_('Timestamp')) ?>,
							value: exemplar.time_ns_formatted
						},
						{
							name: <?= json_encode(_('Value')) ?>,
							value: exemplar.value
						},
						{
							name: <?= json_encode(_('Trace ID')) ?>,
							value: traceid_div
						},
						{
							name: <?= json_encode(_('Span ID')) ?>,
							value: spanid_div
						}
					]
				};

				Object.entries(exemplar.filtered_attributes).forEach(([key, value]) => {
					group.items.push({
						name: key,
						value
					});
				});

				groups.push(group);
			});

			return groups;
		}

		#prepareDetailsData(data) {
			const type = data.type;

			const common_metric_items = [
				{
					name: <?= json_encode(_('Resource schema url')) ?>,
					value: data.resource_schema_url
				},
				{
					name: <?= json_encode(_('Metric description')) ?>,
					value: data.metric_description
				},
				{
					name: <?= json_encode(_('Time')) ?>,
					value: data.time_ns_formatted
				},
				{
					name: <?= json_encode(_('Flags')) ?>,
					value: data.flags & 1 !== 0
						? this.#flags_labels[APM_METRIC_FLAG_NO_RECODED_VALUE]
						: this.#flags_labels[APM_METRIC_FLAG_NONE]
				}
			];

			let metric_items = [];

			switch (type) {
				case APM_METRIC_TYPE_SUM: {
					metric_items = [
						{
							name: <?= json_encode(_('Value')) ?>,
							value: data.value
						},
						{
							name: <?= json_encode(_('Aggregation temporality')) ?>,
							value: this.#aggregation_temporality_labels[data.aggregation_temporality]
						},
						{
							name: <?= json_encode(_('Is monotonic')) ?>,
							value: data.is_monotonic ? 'true' : 'false'
						},
						...common_metric_items
					];

					break;
				}
				case APM_METRIC_TYPE_GAUGE: {
					metric_items = [
						{
							name: <?= json_encode(_('Value')) ?>,
							value: data.value
						},
						...common_metric_items
					];
					break;
				}
				case APM_METRIC_TYPE_HISTOGRAM:
				case APM_METRIC_TYPE_EXPONENTIAL_HISTOGRAM: {
					metric_items = [
						{
							name: <?= json_encode(_('Sum')) ?>,
							value: data.sum
						},
						{
							name: <?= json_encode(_('Aggregation temporality')) ?>,
							value: this.#aggregation_temporality_labels[data.aggregation_temporality]
						},
						{
							name: <?= json_encode(_('Count')) ?>,
							value: data.count
						},
						{
							name: <?= json_encode(_('Min')) ?>,
							value: data.min
						},
						{
							name: <?= json_encode(_('Max')) ?>,
							value: data.max
						},
						...common_metric_items
					];
					break;
				}
			}

			const groups = [
				{
					title: <?= json_encode(_('Basic information')) ?>,
					items: [
						{
							name: <?= json_encode(_('Metric name')) ?>,
							value: data.metric_name
						},
						{
							name: <?= json_encode(_('Type')) ?>,
							value: this.#metric_types[type].label
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
							value: data.start_time_ns_formatted
						}
					]
				}
			];

			if (data.type === APM_METRIC_TYPE_HISTOGRAM) {
				groups.push({
					title: <?= json_encode(_('Histogram')) ?>,
					items: data.histogram_buckets
				});
			}
			else if (data.type === APM_METRIC_TYPE_EXPONENTIAL_HISTOGRAM) {
				groups.push({
					title: <?= json_encode(_('Exponential histogram')) ?>,
					items: data.histogram_buckets
				});
			}

			const details = {
				title: data.metric_name,
				groups: [
					...groups,
					{
						title: <?= json_encode(_('Metric information')) ?>,
						items: metric_items
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
					},
					...this.#prepareExemplarsGroups(data.exemplars)
				]
			};

			return details;
		}
	}
</script>
