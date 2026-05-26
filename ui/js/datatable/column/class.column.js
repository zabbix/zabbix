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


class CDataTableColumn {

	static CHECKBOX = 'checkbox';
	static TABLE_OPTIONS = 'table_options';

	static RENDERER_HTML = 'html';
	static RENDERER_ELEMENT = 'trusted_html';
	static RENDERER_TEXT = 'text';

	/**
	 * @type {number|null}
	 */
	#column_index = null;

	/**
	 * @type {Object}
	 */
	#column_options = {};

	/**
	 * @type {Object}
	 */
	#header_cell = null;

	/**
	 * @type {Object[]}
	 */
	#data_cells = [];

	/**
	 * @type {string}
	 */
	#options_popup_handle_icon = ZBX_ICON_CONTEXT;

	/**
	 * @type {string|null}
	 */
	#options_popup_handler = null;

	/**
	 * @type {CDataTableColumn|null}
	 */
	#defaults = null;

	/**
	 * @type {number|null}
	 */
	#duplicate_column_index = null;

	/**
	 * @type {string}
	 */
	#renderer = CDataTableColumn.RENDERER_HTML;

	/**
	 * @type {boolean}
	 */
	#duplicate = false;

	/**
	 * @type {boolean}
	 */
	#duplicatable = false;

	/**
	 * @type {string[]}
	 */
	#fields = [];

	/**
	 * @type {string}
	 */
	#id;

	/**
	 * @type {string}
	 */
	#name;

	/**
	 * @type {boolean}
	 */
	#only_header = false;

	/**
	 * @type {number}
	 */
	#order = 0;

	/**
	 * @type {Object}
	 */
	#overrides = {};

	/**
	 * @type {boolean}
	 */
	#renamable = false;

	/**
	 * @type {boolean}
	 */
	#resized = false;

	/**
	 * @type {boolean}
	 */
	#resizable = true;

	/**
	 * @type {boolean}
	 */
	#show_in_table_options = true;

	/**
	 * @type {boolean}
	 */
	#sortable = false;

	/**
	 * @type {string|null}
	 */
	#sort_field = null;

	/**
	 * @type {number}
	 */
	#span = 1;

	/**
	 * @type {boolean}
	 */
	#sticky = false;

	/**
	 * @type {boolean}
	 */
	#togglable = true;

	/**
	 * @type {boolean}
	 */
	#visible = true;

	/**
	 * @type {string|number|undefined}
	 */
	#width = 'max-content';

	constructor(id, name) {
		this.#id = id;
		this.#name = name;
	}

	/**
	 * @returns {number|null}
	 */
	getColumnIndex() {
		return this.#column_index;
	}

	/**
	 * @param {number} column_index
	 * @returns {CDataTableColumn}
	 */
	setColumnIndex(column_index) {
		this.#column_index = column_index;

		return this;
	}

	/**
	 * @returns {Object}
	 */
	getHeaderCell() {
		return this.#header_cell;
	}

	/**
	 * @param {Object} header_cell
	 * @returns {CDataTableColumn}
	 */
	setHeaderCell(header_cell) {
		this.#header_cell = header_cell;

		return this;
	}

	/**
	 * @returns {Object[]}
	 */
	getDataCells() {
		return this.#data_cells;
	}

	/**
	 * @param {Object[]} data_cells
	 * @returns {CDataTableColumn}
	 */
	setDataCells(data_cells) {
		this.#data_cells = data_cells;

		return this;
	}

	/**
	 * @returns {string}
	 */
	getOptionsPopupHandleIcon() {
		return this.#options_popup_handle_icon;
	}

	/**
	 * @param {string} context_popup_handle_icon
	 * @returns {CDataTableColumn}
	 */
	setOptionsPopupHandleIcon(context_popup_handle_icon) {
		this.#options_popup_handle_icon = context_popup_handle_icon;

		return this;
	}

	/**
	 * @returns {Object}
	 */
	getColumnOptions() {
		return this.#column_options;
	}

	/**
	 * @param {Object} column_options
	 * @returns {CDataTableColumn}
	 */
	setColumnOptions(column_options) {
		this.#column_options = column_options;

		return this;
	}

	/**
	 * @returns {string|null}
	 */
	getOptionsPopupHandler() {
		return this.#options_popup_handler;
	}

	/**
	 * @param {string} options_popup_handler
	 * @returns {CDataTableColumn}
	 */
	setOptionsPopupHandler(options_popup_handler) {
		this.#options_popup_handler = options_popup_handler;

		return this;
	}

	getDefaults() {
		return this.#defaults;
	}

	/**
	 * @param {CDataTableColumn} defaults
	 * @returns {CDataTableColumn}
	 */
	setDefaults(defaults) {
		this.#defaults = defaults;

		return this;
	}

	/**
	 * @returns {number|null}
	 */
	getDuplicateColumnIndex() {
		return this.#duplicate_column_index;
	}

	/**
	 * @param {number|null} duplicate_column_index
	 * @returns {CDataTableColumn}
	 */
	setDuplicateColumnIndex(duplicate_column_index) {
		this.#duplicate_column_index = duplicate_column_index;

		return this;
	}

	/**
	 * @returns {string}
	 */
	getRenderer() {
		return this.#renderer;
	}

	/**
	 * @param {string} renderer
	 * @returns {CDataTableColumn}
	 */
	setRenderer(renderer) {
		this.#renderer = renderer;

		return this;
	}

	/**
	 * @returns {boolean}
	 */
	isDuplicate() {
		return this.#duplicate;
	}

	/**
	 * @param {boolean} duplicate
	 */
	setDuplicate(duplicate) {
		this.#duplicate = duplicate;

		return this;
	}

	/**
	 * @returns {boolean}
	 */
	isDuplicatable() {
		return this.#duplicatable;
	}

	/**
	 * @param {boolean} duplicatable
	 * @returns {CDataTableColumn}
	 */
	setDuplicatable(duplicatable) {
		this.#duplicatable = duplicatable;

		return this;
	}

	/**
	 * @returns {string[]}
	 */
	getFields() {
		return this.#fields;
	}

	/**
	 * @param {array|string} fields
	 * @returns {CDataTableColumn}
	 */
	setFields(fields) {
		this.#fields = fields;

		return this;
	}

	/**
	 * @returns {string}
	 */
	getId() {
		return this.#id;
	}

	/**
	 * @returns {string}
	 */
	getName() {
		return this.#name;
	}

	/**
	 * @param {string} name
	 * @returns {CDataTableColumn}
	 */
	setName(name) {
		this.#name = name;

		return this;
	}

	/**
	 * @returns {boolean}
	 */
	isOnlyHeader() {
		return this.#only_header;
	}

	/**
	 * @param {boolean} only_header
	 * @returns {CDataTableColumn}
	 */
	setOnlyHeader(only_header) {
		this.#only_header = only_header;

		return this;
	}

	/**
	 * @returns {number}
	 */
	getOrder() {
		return this.#order;
	}

	/**
	 * @param {number} order
	 * @returns {CDataTableColumn}
	 */
	setOrder(order) {
		this.#order = order;

		return this;
	}

	/**
	 * @returns {Object}
	 */
	getOverrides() {
		return this.#overrides;
	}

	/**
	 * @param {Object} overrides
	 * @returns {CDataTableColumn}
	 */
	setOverrides(overrides) {
		this.#overrides = overrides;

		return this;
	}

	/**
	 * @returns {boolean}
	 */
	isRenamable() {
		return this.#renamable;
	}

	/**
	 * @param {boolean} renamable
	 * @returns {CDataTableColumn}
	 */
	setRenamable(renamable) {
		this.#renamable = renamable;

		return this;
	}

	/**
	 * @returns {boolean}
	 */
	isResized() {
		return this.#resized;
	}

	/**
	 * @param {boolean} resized
	 * @returns {CDataTableColumn}
	 */
	setResized(resized) {
		this.#resized = resized;

		return this;
	}

	/**
	 * @returns {boolean}
	 */
	isResizable() {
		return this.#resizable;
	}

	/**
	 * @param {boolean} resizable
	 * @returns {CDataTableColumn}
	 */
	setResizable(resizable) {
		this.#resizable = resizable;

		return this;
	}

	/**
	 * @returns {boolean}
	 */
	isShowInTableOptions() {
		return this.#show_in_table_options;
	}

	/**
	 * @param {boolean} show_in_table_options
	 * @returns {CDataTableColumn}
	 */
	setShowInTableOptions(show_in_table_options) {
		this.#show_in_table_options = show_in_table_options;

		return this;
	}

	/**
	 * @returns {boolean}
	 */
	isSortable() {
		return this.#sortable;
	}

	/**
	 * @param {boolean} sortable
	 * @returns {CDataTableColumn}
	 */
	setSortable(sortable) {
		this.#sortable = sortable;

		return this;
	}

	/**
	 * @returns {string|null}
	 */
	getSortField() {
		return this.#sort_field;
	}

	/**
	 * @param {string} sort_field
	 * @returns {CDataTableColumn}
	 */
	setSortField(sort_field) {
		this.#sort_field = sort_field;

		return this;
	}

	/**
	 * @returns {number}
	 */
	getSpan() {
		return this.#span;
	}

	/**
	 * @param {number} span
	 * @returns {CDataTableColumn}
	 */
	setSpan(span) {
		this.#span = Math.max(1, span);

		return this;
	}

	/**
	 * @returns {boolean}
	 */
	isSticky() {
		return this.#sticky;
	}

	/**
	 * @param {boolean} sticky
	 * @returns {CDataTableColumn}
	 */
	setSticky(sticky) {
		this.#sticky = sticky;

		return this;
	}

	/**
	 * @returns {boolean}
	 */
	isTogglable() {
		return this.#togglable;
	}

	/**
	 * @param {boolean} togglable
	 * @returns {CDataTableColumn}
	 */
	setTogglable(togglable) {
		this.#togglable = togglable;

		return this;
	}

	/**
	 * @returns {boolean}
	 */
	isVisible() {
		return this.#visible;
	}

	/**
	 * @param {boolean} visible
	 * @returns {CDataTableColumn}
	 */
	setVisible(visible) {
		this.#visible = visible;

		return this;
	}

	/**
	 * @returns {string}
	 */
	getWidth() {
		return this.#width;
	}

	/**
	 * @param {string} width
	 * @returns {CDataTableColumn}
	 */
	setWidth(width) {
		this.#width = width;

		return this;
	}

	/**
	 * @param {string|null} width
	 * @returns {CDataTableColumn}
	 */
	resetWidth(width = null) {
		this.#resized = false;
		this.#width = width ?? this.#defaults.getWidth();

		return this;
	}

	/**
	 * @returns {CDataTableColumn}
	 */
	clone() {
		return new this.constructor(this.#id, this.#name).merge(this.toObject());
	}

	/**
	 * @returns {Object}
	 */
	diff() {
		const difference = this.toObject();
		const defaults = this.#defaults.toObject();
		const omitted_properties = new Set(['column_index', 'span']);

		for (const property of Object.keys(defaults)) {
			// "id" is the only property that should always be present.
			if (['id'].includes(property)) {
				continue;
			}

			if (deepCompare(defaults[property], difference[property])) {
				omitted_properties.add(property);
			}
		}

		omitted_properties.forEach(omitted_property => delete difference[omitted_property]);

		return difference;
	}

	/**
	 * @param {Object} params
	 * @returns {CDataTableColumn}
	 */
	merge(params) {
		Object.keys(params).forEach(property => {
			const setter = 'set' + property.replace(/(?:^|_)([a-z])/g, (match, char) => char.toUpperCase());

			this[setter]?.call(this, params[property]);
		});

		return this;
	}

	/**
	 * @returns {Object}
	 */
	toObject() {
		return {
			column_index: this.#column_index,
			column_options: this.#column_options,
			options_popup_handler: this.#options_popup_handler,
			options_popup_handle_icon: this.#options_popup_handle_icon,
			duplicate_column_index: this.#duplicate_column_index,
			duplicate: this.#duplicate,
			duplicatable: this.#duplicatable,
			fields: this.#fields,
			id: this.#id,
			name: this.#name,
			only_header: this.#only_header,
			order: this.#order,
			overrides: this.#overrides,
			renderer: this.#renderer,
			resized: this.#resized,
			resizable: this.#resizable,
			show_in_table_options: this.#show_in_table_options,
			sortable: this.#sortable,
			sort_field: this.#sort_field,
			span: this.#span,
			sticky: this.#sticky,
			togglable: this.#togglable,
			visible: this.#visible,
			width: this.#width
		}
	}
}
