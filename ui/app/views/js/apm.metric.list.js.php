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

		init({
			csrf_token,
			default_sort_field,
			default_sort_order,
			filter,
			filter_options,
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
			this.#csrf_token = csrf_token;

			this.#initEvents(filter_options);
			this.#initFilter(filter_options);
			this.#initDataTable({page, filter, default_sort_field, default_sort_order, sort_field, sort_order,
				storage_idx, user_configs});

			this.#scheduleRefresh();
		}

		#initEvents(filter_options) {
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
				action: 'apm.metric.list.data',
				[CSRF_TOKEN_NAME]: this.#csrf_token
			});

			const data_provider = new CDefaultDataProvider(data_provider_url);

			this.#datatable = new CDataTable(document.getElementById('datatable-metrics'), data_provider)
				.setColumns([
					new CDataTableColumn('metric_name', <?= json_encode(_('Metric name')); ?>)
						.setFields(['metric_name'])
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
				.setFilter(filter)
				.setDefaultSortField(default_sort_field)
				.setDefaultSortOrder(default_sort_order)
				.setSortField(sort_field)
				.setSortOrder(sort_order)
				.setStickyHeader(true)
				.setStickyFooter(true)
				.setStorageIdx(storage_idx)
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

					const types = <?= json_encode(CApmMetricHelper::getTypes()) ?>;

					const content = types[type].label;

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
