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
		/** @type {AbortController|null} */
		#side_drawer_abort_controller = null;
		/** @type {TraceViewPage|null} */
		#trace_view_page = null;
		/** @type {string|null} */
		#selected_traceid = null;
		/** @type {CDetailsPanel|null} */
		#details_panel = null;

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

			$('#filter-attributes')
				.dynamicRows({template: '#filter-attributes-row-tmpl'})
				.on('afteradd.dynamicRows', function () {
					const rows = this.querySelectorAll('.form_row');
					new CApmAttrFilterItem(rows[rows.length - 1]);
				});

			document.querySelectorAll(`#filter-attributes .${ZBX_STYLE_FORM_ROW}`).forEach(row => {
				new CApmAttrFilterItem(row);
			});
		}

		#initDataTable({page, filter, default_sort_field, default_sort_order, sort_field, sort_order, storage_idx,
				user_configs}) {

			const data_provider_url = zabbixUrl({
				action: 'apm.trace.list.data',
				[CSRF_TOKEN_NAME]: this.#csrf_token
			});

			const data_provider = new CDefaultDataProvider(data_provider_url);

			this.#datatable = new CDataTable(document.getElementById('datatable-traces'), data_provider)
				.setColumns([
					new CDataTableColumn('traceid', <?= json_encode(_('Trace ID')); ?>)
						.setFields(['traceid'])
						.setSortable(true)
						.setVisible(false)
						.setWidth('auto'),
					new CDataTableColumn('spanid', <?= json_encode(_('Span ID')); ?>)
						.setFields(['spanid'])
						.setSortable(true)
						.setVisible(false)
						.setWidth('auto'),
					new CDataTableColumn('trace_state', <?= json_encode(_('Trace state')); ?>)
						.setFields(['trace_state'])
						.setSortable(true)
						.setVisible(false)
						.setWidth('auto'),
					new CDataTableColumn('service_name', <?= json_encode(_('Service name')); ?>)
						.setFields(['service_name', 'span_count', 'error_count'])
						.setRenderer('service_name')
						.setSortable(true)
						.setWidth('auto'),
					new CDataTableColumn('span_name', <?= json_encode(_('Operation name')); ?>)
						.setFields(['span_name'])
						.setRenderer('span_name')
						.setSortable(true)
						.setWidth('auto'),
					new CDataTableColumn('span_kind', <?= json_encode(_('Span kind')); ?>)
						.setFields(['span_kind'])
						.setSortable(true)
						.setVisible(false)
						.setWidth('auto'),
					new CDataTableColumn('scope_name', <?= json_encode(_('Scope name')); ?>)
						.setFields(['scope_name'])
						.setSortable(true)
						.setVisible(false)
						.setWidth('auto'),
					new CDataTableColumn('scope_version', <?= json_encode(_('Scope version')); ?>)
						.setFields(['scope_version'])
						.setSortable(true)
						.setVisible(false)
						.setWidth('auto'),
					new CDataTableColumn('status_code', <?= json_encode(_('Status code')); ?>)
						.setFields(['status_code'])
						.setSortable(true)
						.setVisible(false)
						.setWidth('auto'),
					new CDataTableColumn('status_message', <?= json_encode(_('Status message')); ?>)
						.setFields(['status_message'])
						.setSortable(true)
						.setVisible(false)
						.setWidth('auto'),
					new CDataTableColumn('start_time', <?= json_encode(_('Start time')); ?>)
						.setFields(['timestamp_formatted'])
						.setSortable(true)
						.setRenderer('start_time')
						.setSortField('timestamp')
						.setWidth('auto'),
					new CDataTableColumn('span_attributes', <?= json_encode(_('Span attributes')); ?>)
						.setColumnOptions({
							number_of_attributes: 3
						})
						.setFields(['span_attributes'])
						.setRenderer('attributes')
						.setWidth('auto'),
					new CDataTableColumn('resource_attributes', <?= json_encode(_('Resource attributes')); ?>)
						.setColumnOptions({
							number_of_attributes: 3
						})
						.setFields(['resource_attributes'])
						.setRenderer('attributes')
						.setVisible(false)
						.setWidth('auto'),
					new CDataTableColumn('duration', <?= json_encode(_('Duration')); ?>)
						.setFields(['duration_time_units', 'duration_percentage'])
						.setRenderer('duration')
						.setSortable(true)
						.setWidth('auto'),
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
				.setCellRenderer('service_name', ({cell, cell_data}) => {
					const [service_name, span_count, error_count] = cell_data;

					const flex_wrapper = this.#prepareTextCell(service_name);

					const spans = document.createElement('div');
					spans.classList.add(ZBX_STYLE_SPAN_COUNT);
					spans.textContent = span_count;

					flex_wrapper.appendChild(spans);

					if (error_count > 0) {
						const errors = document.createElement('div');
						errors.classList.add(ZBX_STYLE_ERROR_COUNT);
						errors.textContent = error_count;

						flex_wrapper.appendChild(errors);
					}

					cell.appendChild(flex_wrapper);
				})
				.setCellRenderer('span_name', ({cell, cell_data}) => {
					const [span_name] = cell_data;

					cell.appendChild(this.#prepareTextCell(span_name));
				})
				.setCellRenderer('start_time', ({cell, cell_data}) => {
					const [timestamp_formatted] = cell_data;

					/** @type {HTMLDivElement} */
					const wordbreak = document.createElement('div');
					wordbreak.classList.add(ZBX_STYLE_WORDBREAK, 'wordbreak-clamp');
					wordbreak.style.setProperty('--line-clamp', '2');
					wordbreak.textContent = timestamp_formatted;
					wordbreak.title = timestamp_formatted;

					cell.appendChild(wordbreak);
				})
				.setCellRenderer('attributes', ({column, cell, cell_data}) => {
					const [span_attributes] = cell_data;

					if (!span_attributes || span_attributes.length === 0) {
						return;
					}

					const span_attribute_labels = [];

					const tags_wrapper = document.createElement('div');
					tags_wrapper.classList.add(ZBX_STYLE_TAGS_WRAPPER);

					const column_options = column.getColumnOptions();

					let count = column_options.number_of_attributes;

					for (const [attr_name, attr_value] of Object.entries(span_attributes)) {
						const content = `${attr_name}: ${attr_value}`

						const span_attribute_label = document.createElement('span');
						span_attribute_label.classList.add(ZBX_STYLE_TAG);
						span_attribute_label.textContent = content;
						span_attribute_label.setAttribute('data-hintbox-html', content);
						span_attribute_label.setAttribute('data-hintbox', '1');
						span_attribute_label.setAttribute('data-hintbox-static', '1');
						span_attribute_label.setAttribute('aria-expanded', 'false');

						span_attribute_labels.push(span_attribute_label);

						if (count > 0) {
							tags_wrapper.appendChild(span_attribute_label);

							count--;
						}
					}

					if (Object.keys(span_attributes).length > column_options.number_of_attributes) {
						const more_attributes_hintbox = document.createElement('div');

						for (const tag_label of span_attribute_labels) {
							more_attributes_hintbox.appendChild(tag_label.cloneNode(true));
						}

						const more_attributes = document.createElement('button');
						more_attributes.classList.add(ZBX_STYLE_BTN_ICON, ZBX_ICON_MORE);
						more_attributes.setAttribute('data-hintbox-html', more_attributes_hintbox.innerHTML);
						more_attributes.setAttribute('data-hintbox-class',
							`${ZBX_STYLE_HINTBOX_WRAP} ${ZBX_STYLE_TAGS_WRAPPER}`);
						more_attributes.setAttribute('data-hintbox', '1');
						more_attributes.setAttribute('data-hintbox-static', '1');
						more_attributes.setAttribute('aria-expanded', 'false');
						more_attributes.setAttribute('aria-label', t('Show all span attributes'));

						tags_wrapper.appendChild(more_attributes);
					}

					cell.appendChild(tags_wrapper);
				})
				.setCellRenderer('duration', ({cell, cell_data}) => {
					const [duration_time_units, duration_percentage] = cell_data;

					/** @type {HTMLDivElement} */
					const bar = document.createElement('div');
					bar.classList.add(ZBX_STYLE_DURATION_BAR);
					bar.style.width = `${duration_percentage}%`;

					const overflow_ellipsis = document.createElement('div');
					overflow_ellipsis.classList.add(ZBX_STYLE_OVERFLOW_ELLIPSIS);
					overflow_ellipsis.textContent = duration_time_units;
					overflow_ellipsis.title = duration_time_units;

					/** @type {HTMLDivElement} */
					const time_units = document.createElement('div');
					time_units.classList.add(ZBX_STYLE_DURATION_TIME_UNITS);
					time_units.appendChild(overflow_ellipsis);

					const duration = document.createElement('div');
					duration.classList.add(ZBX_STYLE_DURATION);
					duration.append(bar, time_units);

					cell.classList.add(CDataTable.ZBX_STYLE_CELL_COMPACT);
					cell.appendChild(duration);
				})
				.setRowRenderer('trace', ({columns, data_fields, row, row_data, row_index, response}) => {
					const traceid = data_fields.indexOf('traceid');

					row.setAttribute('data-traceid', row_data[traceid] ?? null);

					this.#datatable.renderDataCells({columns, data_fields, row, row_data, row_index, response});
				})
				.on(CMessageHelper.EVENT_MESSAGE, e => {
					e.stopPropagation();

					const {type, title, messages} = e.detail;

					clearMessages();
					addMessage(makeMessageBox(type, messages, title));
				})
				.on(CPager.EVENT_SELECT, () => {
					this.#side_drawer_abort_controller?.abort();
					this.#side_drawer_abort_controller = null;

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
				})
				.on(CDataTable.EVENT_AFTER_RENDER, () => {
					const datatable_element = this.#datatable.getElement();
					const rows = datatable_element.querySelectorAll(`.${CDataTable.ZBX_STYLE_ROW}`);

					for (const row of rows) {
						const traceid = row.getAttribute('data-traceid');

						if (this.#selected_traceid === traceid) {
							row.classList.add(CDataTable.ZBX_STYLE_ROW_SELECTED);
						}

						row.addEventListener('click', e => {
							/** @type {HTMLElement} */
							const target = e.target;
							if (target.hasAttribute('data-hintbox')) {
								return;
							}

							if (this.#selected_traceid !== traceid) {
								if (this.#selected_traceid !== null) {
									const row_selected = datatable_element
										.querySelector(`.${CDataTable.ZBX_STYLE_ROW_SELECTED}`);
									row_selected?.classList.remove(CDataTable.ZBX_STYLE_ROW_SELECTED);
								}

								this.#selected_traceid = traceid;
								row.classList.add(CDataTable.ZBX_STYLE_ROW_SELECTED);

								this.#openSideDrawer(traceid);
							}
							else {
								this.#selected_traceid = null;
								row.classList.remove(CDataTable.ZBX_STYLE_ROW_SELECTED);

								this.#side_drawer?.close();
							}
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

		#openSideDrawer(traceid) {
			if (this.#side_drawer === null) {
				const container = document.querySelector(`.${ZBX_STYLE_LAYOUT_WRAPPER}`);

				this.#side_drawer = new CSideDrawer(container, {
					content_pane_class: ZBX_STYLE_LAYOUT_WRAPPER,
					position: this.#side_drawer_position,
					position_min: '10%',
					position_max: '90%'
				});
				this.#side_drawer.on(CSideDrawer.EVENT_OPEN, e => this.#onSideDrawerOpen(e));
				this.#side_drawer.on(CSideDrawer.EVENT_BEFORE_CLOSE, () => this.#onSideDrawerBeforeClose())
				this.#side_drawer.on(CSideDrawer.EVENT_CLOSE, () => this.#onSideDrawerClose());
				this.#side_drawer.on(CSideDrawer.EVENT_POSITION, e => this.#onSideDrawerPosition(e));
			}

			this.#side_drawer_abort_controller?.abort();
			this.#side_drawer_abort_controller = new AbortController();

			this.#side_drawer.open(
				fetch(zabbixUrl({action: 'apm.trace.list.split.view'}), {
					method: 'POST',
					headers: {'Content-Type': 'application/json'},
					body: JSON.stringify({traceid}),
					signal: this.#side_drawer_abort_controller.signal
				})
					.then(response => response.json()),
				this.#side_drawer_abort_controller
			);
		}

		#onSideDrawerOpen = e => {
			const {response} = e.detail;
			const {trace_view, trace_view_data} = response;

			const element = this.#side_drawer.getElement();
			element.innerHTML = trace_view;

			this.#bindSideDrawerEvents();
			this.#datatable.unbindWrapperEvents();

			this.#trace_view_page?.destroy();
			this.#trace_view_page = new TraceViewPage(element, trace_view_data);

			this.#datatable.bindWrapperEvents();

			this.#unscheduleRefresh();
		}

		#onSideDrawerPosition = e => {
			this.#side_drawer_position = e.detail.position;

			updateUserProfile('web.apm.trace.side_drawer.position', this.#side_drawer_position, [], PROFILE_TYPE_STR);
		}

		#bindSideDrawerEvents() {
			const element = this.#side_drawer?.getElement();

			element?.addEventListener('span-select', this.#onSideDrawerSpanDetailsOpen);
			element?.addEventListener('close', this.#onSideDrawerSpanDetailsClose);
		}

		#unbindSideDrawerEvents() {
			const element = this.#side_drawer?.getElement();

			element?.removeEventListener('close', this.#onSideDrawerSpanDetailsClose);
			element?.removeEventListener('span-select', this.#onSideDrawerSpanDetailsOpen);
		}

		#onSideDrawerBeforeClose = () => {
			this.#datatable.unbindWrapperEvents();

			this.#details_panel?.destroy();
			this.#details_panel = null;

			this.#trace_view_page?.destroy();
			this.#trace_view_page = null;
		}

		#onSideDrawerClose = () => {
			const datatable_element = this.#datatable.getElement();

			this.#unbindSideDrawerEvents();
			this.#datatable.bindWrapperEvents();

			const row_selected = datatable_element.querySelector(`.${CDataTable.ZBX_STYLE_ROW_SELECTED}`);
			row_selected?.classList.remove(CDataTable.ZBX_STYLE_ROW_SELECTED);

			this.#selected_traceid = null;

			this.#scheduleRefresh();
		}

		#onSideDrawerSpanDetailsOpen = e => {
			const trace_details = this.#side_drawer?.getElement()?.querySelector('[data-trace-details]');
			if (trace_details === null) {
				return;
			}

			const span = e.detail.item.span ?? null;
			if (span === null) {
				return;
			}

			const span_details = document.createElement('div');
			trace_details.appendChild(span_details);

			this.#details_panel?.destroy();
			this.#details_panel = new CDetailsPanel(span_details, {
				title: <?= json_encode(_('Span details')); ?>,
				groups: this.#collectSpanDetailsGroups(span)
			});
		}

		#onSideDrawerSpanDetailsClose = () => {
			this.#details_panel?.destroy();
			this.#details_panel = null;

			const trace_tree_content = this.#side_drawer?.getElement()?.querySelector('.z-navigation-tree-content');
			trace_tree_content?.dispatchEvent(new CustomEvent('deselect'));
		}

		#collectSpanDetailsGroups(span) {
			const groups = [{
				title: <?= json_encode(_('Basic information')); ?>,
				items: [
					{
						name: <?= json_encode(_('Span ID')); ?>,
						value: span.id
					},
					{
						name: <?= json_encode(_('Operation')); ?>,
						value: span.operation
					},
					{
						name: <?= json_encode(_('Service name')); ?>,
						value: span.service_name
					},
					{
						name: <?= json_encode(_('Scope name')); ?>,
						value: span.scope_name
					},
					{
						name: <?= json_encode(_('Duration')); ?>,
						value: span.duration
					},
					{
						name: <?= json_encode(_('Start time')); ?>,
						value: span.timestamp
					}
				]
			}];

			const resource_attributes = Object.entries(span.resource_attributes);
			if (resource_attributes.length > 0) {
				const items = [];
				for (const [name, value] of resource_attributes) {
					items.push({name, value});
				}

				groups.push({title: <?= json_encode(_('Resource attributes')); ?>, items});
			}

			const span_attributes = Object.entries(span.span_attributes);
			if (span_attributes.length > 0) {
				const items = [];
				for (const [name, value] of span_attributes) {
					items.push({name, value});
				}

				groups.push({title: <?= json_encode(_('Span attributes')); ?>, items});
			}

			if (span.events?.length > 0) {
				const items = [];
				for (const span_event of span.events) {
					const attributes = [];
					for (const [name, value] of Object.entries(span_event.attributes)) {
						attributes.push({name, value});
					}

					const event = [
						{name: <?= json_encode(_('Duration')); ?>, value: span_event.duration},
						{title: <?= json_encode(_('Event attributes')); ?>, items: attributes}
					];

					items.push({title: span_event.name, items: event});
				}

				groups.push({title: <?= json_encode(_('Events')); ?>, items});
			}

			return groups;
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

		#validateFormChanges() {
			const values = this.#filter_form?.getAllValues() ?? {};

			this.#filter_form?.validateChanges(Object.keys(values), true);
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
