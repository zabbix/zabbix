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

			$('#filter-resource-attributes')
				.dynamicRows({template: '#filter-resource-attributes-row-tmpl'})
				.on('afteradd.dynamicRows', function () {
					const rows = this.querySelectorAll('.form_row');
					new CApmAttrFilterItem(rows[rows.length - 1]);
				});

			document.querySelectorAll(`#filter-resource-attributes .${ZBX_STYLE_FORM_ROW}`).forEach(row => {
				new CApmAttrFilterItem(row);
			});
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
						.setFields(['timestamp'])
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
				.setStorageIdx(storage_idx)
				.setStickyHeader(true)
				.setStickyFooter(true)
				.setCellRenderer('timestamp', ({cell, cell_data}) => {
					const [timestamp] = cell_data;

					/** @type {HTMLDivElement} */
					const wordbreak = document.createElement('div');
					wordbreak.classList.add(ZBX_STYLE_WORDBREAK, 'wordbreak-clamp');
					wordbreak.style.setProperty('--line-clamp', '2');
					wordbreak.textContent = timestamp;

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


			this.#datatable.setFilter({
				...this.#datatable.getFilter(),
				from: this.#global_timerange.from,
				to: this.#global_timerange.to,
			});


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
