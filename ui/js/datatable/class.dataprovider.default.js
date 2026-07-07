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
	#last_data_fields = [];

	/**
	 * @type {Object}
	 */
	#last_filter = {};

	/**
	 * @type {Object}
	 */
	#last_column_options = {};

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
	 * @param {CDataTableColumn[]} columns         Column configurations
	 * @param {Object}             filter          Filters
	 * @param {Object}             column_options  Column options
	 * @param {Object}             options         Table options
	 * @param {number}             page            The current page
	 * @param {string}             sort_field      Sort field
	 * @param {string}             sort_order      Sort order (ASC/DESC)
	 * @param {boolean}            check_changes   Check against cached state to prevent redundant requests
	 * @param {boolean}            force_load      Bypasses all checks and guarantees a new request
	 * @param {string}             export_file     If provided, triggers file generation server-side
	 */
	getData({columns, filter, column_options, options, page, sort_field, sort_order, check_changes, force_load,
			export_file}) {
		const data_fields = [
			...new Set(columns.flatMap(column => column.isVisible() ? column.getFields() : []))
		];

		filter = Object.fromEntries(
			Object.entries(filter).filter(([key]) => {
				return !['filter_src', 'filter_view_data', 'filter_show_counter', 'filter_custom_time'].includes(key);
			})
		);

		if (this.#last_response !== null && !force_load) {
			if (!check_changes) {
				return Promise.resolve(this.#last_response);
			}

			const fields_set_changed = !data_fields.every(field => this.#last_data_fields.includes(field));
			const filter_changed = !deepCompare(this.#last_filter, filter);
			const options_changed = !deepCompare(this.#last_options, options);
			const column_options_changed = !deepCompare(this.#last_column_options, column_options);
			const page_changed = this.#last_page !== page;
			const sort_changed = this.#last_sort_field !== sort_field || this.#last_sort_order !== sort_order;

			if (!fields_set_changed && !column_options_changed && !filter_changed && !options_changed
				&& !page_changed && !sort_changed
			) {
				return Promise.resolve(this.#last_response);
			}
		}

		this.#last_data_fields = data_fields;
		this.#last_filter = filter;
		this.#last_options = options;
		this.#last_column_options = column_options;
		this.#last_page = page;
		this.#last_sort_field = sort_field;
		this.#last_sort_order = sort_order;

		const abort_controller = new AbortController();

		this.#abort_controller?.abort();
		this.#abort_controller = abort_controller;

		return fetch(this.#url, {
			method: 'POST',
			headers: [
				['Content-Type', 'application/json']
			],
			body: JSON.stringify({
				data_fields,
				options: {...options, columns: column_options},
				filter,
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
				if (this.#abort_controller === abort_controller) {
					this.#abort_controller = null;
				}
			});
	}
}
