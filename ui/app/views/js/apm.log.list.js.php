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
		/** @type {string|null} */
		#selected_row_hash = null;

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
			side_drawer_position
		}) {
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
						.setRenderer('text_field')
						.setWidth('auto'),
					new CDataTableColumn('severity_text', <?= json_encode(_('Severity text')) ?>)
						.setFields(['severity_text', 'severity_number'])
						.setSortable(true)
						.setRenderer('severity_text')
						.setWidth('auto'),
					new CDataTableColumn('severity_number', <?= json_encode(_('Severity number')) ?>)
						.setFields(['severity_number'])
						.setSortable(true)
						.setWidth('auto'),
					new CDataTableColumn('traceid', <?= json_encode(_('Trace ID')) ?>)
						.setFields(['traceid'])
						.setSortable(true)
						.setRenderer('traceid')
						.setWidth('auto')
						.setVisible(false),
					new CDataTableColumn('spanid', <?= json_encode(_('Span ID')) ?>)
						.setFields(['spanid', 'traceid'])
						.setSortable(true)
						.setRenderer('spanid')
						.setWidth('auto')
						.setVisible(false),
					new CDataTableColumn('trace_flags', <?= json_encode(_('Flags')) ?>)
						.setFields(['trace_flags'])
						.setSortable(true)
						.setRenderer('trace_flags')
						.setWidth('auto')
						.setVisible(false),
					new CDataTableColumn('service_name', <?= json_encode(_('Service name')) ?>)
						.setFields(['service_name'])
						.setSortable(true)
						.setRenderer('text_field')
						.setWidth('auto')
						.setVisible(false),
					new CDataTableColumn('scope_name', <?= json_encode(_('Scope name')) ?>)
						.setFields(['scope_name'])
						.setSortable(true)
						.setRenderer('text_field')
						.setWidth('auto')
						.setVisible(false),
					new CDataTableColumn('scope_version', <?= json_encode(_('Scope version')) ?>)
						.setFields(['scope_version'])
						.setSortable(true)
						.setRenderer('text_field')
						.setWidth('auto')
						.setVisible(false),
					new CDataTableColumn('event_name', <?= json_encode(_('Event name')) ?>)
						.setFields(['event_name'])
						.setSortable(true)
						.setRenderer('text_field')
						.setWidth('auto')
						.setVisible(false),
					new CDataTableColumn('log_attributes', <?= json_encode(_('Log attributes')); ?>)
						.setFields(['attributes'])
						.setVisible(false)
						.setRenderer('attributes')
						.setColumnOptions({
							number_of_attributes: 3
						})
						.setWidth('auto'),
					new CDataTableColumn('resource_attributes', <?= json_encode(_('Resource attributes')); ?>)
						.setFields(['resource_attributes'])
						.setVisible(false)
						.setRenderer('attributes')
						.setColumnOptions({
							number_of_attributes: 3
						})
						.setWidth('auto'),
					new CDataTableColumn('scope_attributes', <?= json_encode(_('Scope attributes')); ?>)
						.setFields(['scope_attributes'])
						.setVisible(false)
						.setRenderer('attributes')
						.setColumnOptions({
							number_of_attributes: 3
						})
						.setWidth('auto')
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
				.setCellRenderer('text_field', ({cell, cell_data}) => {
					const [data] = cell_data;

					cell.appendChild(this.#prepareTextCell(data));
				})
				.setCellRenderer('traceid', ({cell, cell_data}) => {
					const [traceid] = cell_data;

					if (traceid === '') {
						return;
					}

					const link = document.createElement('a');
					link.href = zabbixUrl({
						action: 'apm.trace.list',
						filter_traceid: traceid,
						filter_set: '1'
					});
					link.textContent = traceid;

					cell.appendChild(link);
				})
				.setCellRenderer('spanid', ({cell, cell_data}) => {
					const [spanid, traceid] = cell_data;

					if (spanid === '') {
						return;
					}

					const link = document.createElement('a');
					link.href = zabbixUrl({
						action: 'apm.trace.list',
						filter_traceid: traceid,
						filter_spanid: spanid,
						filter_set: '1'
					});
					link.textContent = spanid;

					cell.appendChild(link);
				})
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

					if (severity_text === '') {
						return;
					}

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
				.setCellRenderer('trace_flags', ({cell, cell_data}) => {
					const [trace_flags] = cell_data;

					cell.textContent = this.#decodeTraceFlags(trace_flags);
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
						const label = document.createElement('span');
						label.classList.add(ZBX_STYLE_TAG);
						label.textContent = content;
						label.dataset.hintbox = '1';
						label.dataset.hintboxStatic = '1';
						label.dataset.hintboxHtml = content;
						label.ariaExpanded = 'false';

						attribute_labels.push(label);

						if (count > 0) {
							tags_wrapper.appendChild(label);

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
						more_attributes.ariaLabel = <?= json_encode(_('Show all attributes')) ?>;

						tags_wrapper.appendChild(more_attributes);
					}

					cell.appendChild(tags_wrapper);
				})
				.setRowRenderer('log', ({columns, data_fields, row, row_data, row_index, response}) => {
					const row_hash = this.#hashRowData(row_data);

					row.dataset.rowHash = row_hash;

					const row_data_o = Object.create(null);

					for (const [index, key] of data_fields.entries()) {
						row_data_o[key] = row_data[index];
					}

					this.#rows_data.set(row_hash, row_data_o);

					this.#datatable.renderDataCells({columns, data_fields, row, row_data, row_index, response});
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

					this.#rows_data.clear();
				})
				.on(CDataTable.EVENT_AFTER_RENDER, () => {
					const datatable_element = this.#datatable.getElement();
					const rows = datatable_element.querySelectorAll(`.${CDataTable.ZBX_STYLE_ROW}`);

					for (const row of rows) {
						const row_hash = row.dataset.rowHash;

						if (row_hash === this.#selected_row_hash) {
							row.classList.add(CDataTable.ZBX_STYLE_ROW_SELECTED);
						}

						row.addEventListener('click', e => {
							/** @type {HTMLElement} */
							const target = e.target;
							const selection = window.getSelection();

							if (target.hasAttribute('data-hintbox') || (selection && selection.toString().length > 0)) {
								return;
							}

							if (this.#selected_row_hash === row_hash) {
								this.#side_drawer.close();

								return;
							}

							datatable_element.querySelector(`.${CDataTable.ZBX_STYLE_ROW_SELECTED}`)
								?.classList.remove(CDataTable.ZBX_STYLE_ROW_SELECTED);

							row.classList.add(CDataTable.ZBX_STYLE_ROW_SELECTED);

							this.#selected_row_hash = row_hash;

							this.#openSideDrawer(this.#rows_data.get(this.#selected_row_hash));
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
			this.#selected_row_hash = null;

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
							value: this.#decodeTraceFlags(row_data.trace_flags)
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
							value: row_data.scope_name
						},
						{
							name: <?= json_encode(_('Scope version')) ?>,
							value: row_data.scope_version
						},
						{
							name: <?= json_encode(_('Event name')) ?>,
							value: row_data.event_name
						}
					]
				},
				{
					title: <?= json_encode(_('Log attributes')) ?>,
					items: Object.entries(row_data.log_attributes).map(
						([name, value]) => Object.fromEntries([['name', name], ['value', value]])
					)
				},
				{
					title: <?= json_encode(_('Resource attributes')) ?>,
					items: Object.entries(row_data.resource_attributes).map(
						([name, value]) => Object.fromEntries([['name', name], ['value', value]])
					)
				},
				{
					title: <?= json_encode(_('Scope attributes')) ?>,
					items: Object.entries(row_data.scope_attributes).map(
						([name, value]) => Object.fromEntries([['name', name], ['value', value]])
					)
				}
			];
		}

		#decodeTraceFlags(trace_flags) {
			const flags_decoded = [];

			if ((trace_flags & 1) === 1) {
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

		#prepareTextCell(value) {
			const content = document.createElement('div');
			content.classList.add(ZBX_STYLE_OVERFLOW_ELLIPSIS);
			content.textContent = value;
			content.title = value;

			const flex_wrapper = document.createElement('div');
			flex_wrapper.classList.add(ZBX_STYLE_FLEX_WRAPPER);
			flex_wrapper.appendChild(content);

			return flex_wrapper;
		}

		#hashRowData(row_data) {
			const row_data_str = JSON.stringify(row_data);

			let hash = 5381;

			for (let i = 0; i < row_data_str.length; i++) {
				hash = ((hash << 5) + hash) + row_data_str.charCodeAt(i);
			}

			return (hash >>> 0).toString(16);
		}
	}
</script>
