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
		/** @type {CDataTable|null} */
		#datatable = null;
		/** @type {HTMLFormElement|null} */
		#filter_form_element = null;
		/** @type {CForm|null} */
		#filter_form = null;
		/** @type {HTMLButtonElement|null} */
		#apply_filter_button = null;
		#csrf_token = null;
		#refresh_message_box = null;
		/** @type {CSideDrawer|null} */
		#side_drawer = null;
		/** @type {TraceViewPage|null} */
		#trace_view_page = null;
		/** @type {string|null} */
		#selected_traceid = null;

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
			user_configs
		}) {
			this.#layout_mode = layout_mode;
			this.#refresh_interval = refresh_interval;
			this.#csrf_token = csrf_token;

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
					new CDataTableColumn('service_name', <?= json_encode(_('Service name')); ?>)
						.setFields(['service_name', 'span_count', 'error_count'])
						.setRenderer('service_name')
						.setWidth('auto'),
					new CDataTableColumn('operation_name', <?= json_encode(_('Operation name')); ?>)
						.setFields(['span_name'])
						.setRenderer('operation_name')
						.setWidth('auto'),
					new CDataTableColumn('timestamp', <?= json_encode(_('Start time')); ?>)
						.setFields(['timestamp'])
						.setSortable(true)
						.setRenderer('timestamp')
						.setWidth('auto'),
					new CDataTableColumn('attributes', <?= json_encode(_('Attributes')); ?>)
						.setColumnOptions({
							number_of_attributes: 3
						})
						.setFields(['span_attributes'])
						.setRenderer('attributes'),
					new CDataTableColumn('duration', <?= json_encode(_('Duration')); ?>)
						.setFields(['duration_time_units', 'duration_percentage'])
						.setRenderer('duration')
						.setWidth('auto')
				])
				.setPage(page)
				.setFilter(filter)
				.setDefaultSortField(default_sort_field)
				.setDefaultSortOrder(default_sort_order)
				.setSortField(sort_field)
				.setSortOrder(sort_order)
				.setStorageIdx(storage_idx)
				.setStickyHeader(true)
				.setStickyFooter(true)
				.setCellRenderer('service_name', ({cell, cell_data}) => {
					const [service_name, span_count, error_count] = cell_data;

					const flex_wrapper = document.createElement('div');
					flex_wrapper.classList.add(ZBX_STYLE_FLEX_WRAPPER);

					const name = document.createElement('div');
					name.classList.add(ZBX_STYLE_OVERFLOW_ELLIPSIS);
					name.textContent = service_name;

					const spans = document.createElement('div');
					spans.classList.add(ZBX_STYLE_SPAN_COUNT);
					spans.textContent = span_count;

					flex_wrapper.append(name, spans);

					if (error_count > 0) {
						const errors = document.createElement('div');
						errors.classList.add(ZBX_STYLE_ERROR_COUNT);
						errors.textContent = error_count;

						flex_wrapper.appendChild(errors);
					}

					cell.appendChild(flex_wrapper);
				})
				.setCellRenderer('operation_name', ({cell, cell_data}) => {
					const [operation_name] = cell_data;

					const name = document.createElement('div');
					name.classList.add(ZBX_STYLE_OVERFLOW_ELLIPSIS);
					name.textContent = operation_name;

					const flex_wrapper = document.createElement('div');
					flex_wrapper.classList.add(ZBX_STYLE_FLEX_WRAPPER);
					flex_wrapper.appendChild(name);

					cell.appendChild(flex_wrapper);
				})
				.setCellRenderer('timestamp', ({cell, cell_data}) => {
					const [timestamp] = cell_data;

					/** @type {HTMLDivElement} */
					const wordbreak = document.createElement('div');
					wordbreak.classList.add(ZBX_STYLE_WORDBREAK, 'wordbreak-clamp');
					wordbreak.style.setProperty('--line-clamp', '2');
					wordbreak.textContent = timestamp;

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
						const span_attribute_label = document.createElement('span');
						span_attribute_label.classList.add(ZBX_STYLE_TAG);
						span_attribute_label.textContent = `${attr_name}: ${attr_value}`;

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
						more_attributes.setAttribute('data-hintbox-class', `${ZBX_STYLE_HINTBOX_WRAP} ${ZBX_STYLE_TAGS_WRAPPER}`);
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

							const row_selected = datatable_element.querySelector(`.${CDataTable.ZBX_STYLE_ROW_SELECTED}`);
							row_selected?.classList.remove(CDataTable.ZBX_STYLE_ROW_SELECTED);

							row.classList.add(CDataTable.ZBX_STYLE_ROW_SELECTED);

							const wrapper = document.querySelector(`.${ZBX_STYLE_LAYOUT_WRAPPER}`);

							this.#selected_traceid = traceid;

							this.#openSideDrawer(wrapper, traceid);
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

		#openSideDrawer(container, traceid) {
			const url = zabbixUrl({action: 'apm.trace.list.split.view'});

			if (this.#side_drawer === null) {
				this.#side_drawer = new CSideDrawer(container, {content_pane_class: ZBX_STYLE_LAYOUT_WRAPPER});
				this.#side_drawer.on(CSideDrawer.EVENT_OPEN, e => this.#onSideDrawerOpen(e));
				this.#side_drawer.on(CSideDrawer.EVENT_CLOSE, e => this.#onSideDrawerClose(e));
			}

			this.#side_drawer
				.open(url, {
					method: 'POST',
					headers: {'Content-Type': 'application/json'},
					body: JSON.stringify({traceid})
				})
				.catch(error => {
					if (error.name === 'AbortError') {
						return;
					}

					throw error;
				});
		}

		#onSideDrawerOpen = e => {
			const {response} = e.detail;
			const {trace_view, trace_view_data} = response;

			const element = this.#side_drawer.getElement();
			element.innerHTML = trace_view;

			this.#trace_view_page?.destroy();
			this.#trace_view_page = new TraceViewPage(element, trace_view_data);

			this.#unscheduleRefresh();
		}

		#onSideDrawerClose = () => {
			const datatable_element = this.#datatable.getElement();

			const row_selected = datatable_element.querySelector(`.${CDataTable.ZBX_STYLE_ROW_SELECTED}`);
			row_selected?.classList.remove(CDataTable.ZBX_STYLE_ROW_SELECTED);

			this.#trace_view_page?.destroy();
			this.#trace_view_page = null;

			this.#selected_traceid = null;
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

			if (filter.filter_custom_time === 0) {
				filter.from = this.#global_timerange.from;
				filter.to = this.#global_timerange.to;
			}

			this.#datatable
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
