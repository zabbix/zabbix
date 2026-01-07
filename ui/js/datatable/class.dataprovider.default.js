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


class CDefaultDataProvider extends CDataProvider {

	/**
	 * @var {string}
	 */
	#url;

	/**
	 * @var {array}
	 */
	#last_fields = [];

	/**
	 * @type {Object}
	 */
	#last_filter = {};

	/**
	 * @type {Object}
	 */
	#last_context_popup_data = {};

	/**
	 * @type {Object}
	 */
	#last_options = {};

	/**
	 * @type {number}
	 */
	#last_page = 1;

	/**
	 * @type {string|null}
	 */
	#last_sort_field = null;

	/**
	 * @type {string|null}
	 */
	#last_sort_order = null;

	/**
	 * @var {Object|null}
	 */
	#last_response = null;

	/**
	 * @type {boolean}
	 */
	#data_changed = false;

	/**
	 * @var {AbortController|null}
	 */
	#abort_controller = null;

	/**
	 * @param {string} url
	 */
	constructor(url) {
		super();

		this.#url = url;
	}

	/**
	 * @param {array|CDataTableColumn[]} columns
	 * @param {Object} filter
	 * @param {Object} options
	 * @param {number} page
	 * @param {string} sort_field
	 * @param {string} sort_order
	 * @param {boolean} force_load
	 * @param {string}  export_file
	 * @returns {Promise<any>}
	 */
	getData({columns, filter, options, page, sort_field, sort_order, force_load, export_file}) {
		const visible_columns = columns.filter(column_config => {
			return column_config.isVisible() && column_config.getId() != CDataTableColumn.CUSTOMIZE_TABLE;
		});
		const fields = Array.from(new Set(visible_columns.flatMap(column_config => column_config.getFields())));

		let context_popup_data = {};
		for (const column_config of columns) {
			context_popup_data = Object.values(Object.assign(context_popup_data, column_config.getContextPopupData()));
		}

		for (const key of ['filter_src', 'filter_view_data', 'filter_show_counter']) {
			delete filter[key];
		}

		if (this.#last_response && !force_load) {
			const has_all_fields = fields.every(field => this.#last_fields.includes(field));
			const filter_changed = !deepCompare(this.#last_filter, filter);
			const context_popup_data_changed = !deepCompare(this.#last_context_popup_data, context_popup_data);
			const option_changed = !deepCompare(this.#last_options, options);
			const page_changed = this.#last_page !== page;
			const sort_changed = this.#last_sort_field !== sort_field || this.#last_sort_order !== sort_order;

			this.#data_changed = !has_all_fields || context_popup_data_changed || filter_changed || option_changed
				|| page_changed || sort_changed;

			if (!this.#data_changed) {
				return Promise.resolve(this.#last_response);
			}
		}

		this.#last_fields = fields;
		this.#last_filter = filter;
		this.#last_context_popup_data = context_popup_data;
		this.#last_options = options;
		this.#last_page = page;
		this.#last_sort_field = sort_field;
		this.#last_sort_order = sort_order;

		if ('filter_name' in filter) {
			delete filter['filter_name'];
		}

		this.#abort_controller?.abort();
		this.#abort_controller = new AbortController();

		return fetch(this.#url, {
			method: 'POST',
			headers: [
				['Content-Type', 'application/json']
			],
			body: JSON.stringify({
				columns: columns.map(column_config => column_config.toObject()),
				filter,
				options,
				page,
				sort_field,
				sort_order,
				export_file
			}),
			signal: this.#abort_controller.signal
		})
			.then(response => response.json())
			.then(response => {
				if (!export_file) {
					this.#last_response = response;
				}

				return response;
			})
			.finally(() => {
				this.#abort_controller = null;
			});
	}

	/**
	 * @returns {Object|null}
	 */
	getLastResponse() {
		return this.#last_response;
	}
}
