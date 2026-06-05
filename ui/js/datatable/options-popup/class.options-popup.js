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


class CDataTableOptionsPopup {

	static COLUMN_NAME_MAXLENGTH = 32;

	static EVENT_INIT = 'init';
	static EVENT_OPEN = 'open';
	static EVENT_CLOSE = 'close';
	static EVENT_RESET = 'reset';
	static EVENT_UPDATE = 'update';
	static EVENT_SAVE = 'save';

	static ZBX_STYLE_OPTIONS_POPUP = 'datatable-options-popup';
	static ZBX_STYLE_OPTIONS_POPUP_LINKS = 'datatable-options-popup-links';
	static ZBX_STYLE_OPTIONS_POPUP_LINK = 'datatable-options-popup-link';

	/**
	 * @type {CDataTable}
	 */
	#datatable;

	/**
	 * @type {CDataTableColumn}
	 */
	#column;

	/**
	 * @type {string}
	 */
	#column_name;

	/**
	 * @type {HTMLElement}
	 */
	#header_cell;

	/**
	 * @type {HTMLElement}
	 */
	#handle;

	/**
	 * @type {HTMLElement|null}
	 */
	#element;

	/**
	 * @type {HTMLElement|null}
	 */
	#template = null;

	/**
	 * @type {number}
	 */
	#offset_top = 0;

	/**
	 * @type {Object<string, any>}
	 */
	#fields = {};

	/**
	 * @type {Object<string, any>}
	 */
	#data = {};

	/**
	 * @type {boolean}
	 */
	#mouse_down_inside = false;

	/**
	 * @type {Object<string, function>}
	 */
	#events = {
		[CDataTableOptionsPopup.EVENT_INIT]: this.onInit,
		[CDataTableOptionsPopup.EVENT_OPEN]: this.onOpen,
		[CDataTableOptionsPopup.EVENT_CLOSE]: this.onClose,
		[CDataTableOptionsPopup.EVENT_RESET]: this.onReset,
		[CDataTableOptionsPopup.EVENT_UPDATE]: this.onUpdate,
		[CDataTableOptionsPopup.EVENT_SAVE]: this.onSave,
	}

	/**
	 * @param {CDataTable}       datatable
	 * @param {CDataTableColumn} column
	 * @param {HTMLElement}      header_cell
	 * @param {HTMLElement}      handle
	 */
	constructor(datatable, column, header_cell, handle) {
		this.#datatable = datatable;
		this.#column = column;
		this.#column_name = column.getName();
		this.#header_cell = header_cell;
		this.#handle = handle;
		this.#offset_top = this.#datatable.getElement().getBoundingClientRect().top;
		this.#element = document.createElement('div');

		this.#bindEvents();

		requestAnimationFrame(() => this.dispatchEvent(CDataTableOptionsPopup.EVENT_INIT));
	}

	/**
	 * @returns {CDataTable}
	 */
	getDataTable() {
		return this.#datatable;
	}

	/**
	 * @returns {CDataTableColumn}
	 */
	getColumnConfig() {
		return this.#column;
	}

	/**
	 * @returns {HTMLElement}
	 */
	getHandle() {
		return this.#handle;
	}

	/**
	 * @param {HTMLElement} handle
	 */
	setHandle(handle) {
		this.#handle = handle;
	}

	/**
	 * @returns {HTMLElement|null}
	 */
	getElement() {
		return this.#element;
	}

	/**
	 * @returns {Object<string, any>}
	 */
	getData() {
		return this.#data;
	}

	/**
	 * @param {string} name
	 * @returns {any}
	 */
	getField(name) {
		return this.#fields[name] || null;
	}

	/**
	 * Get current fields.
	 *
	 * @returns {Object<string, any>}
	 */
	getFields() {
		return {};
	}

	/**
	 * Get current field values.
	 *
	 * @returns {Object<string, any>}
	 */
	getFieldData() {
		return {};
	}

	/**
	 * Returns template element.
	 *
	 * @returns {Element|string|null}
	 */
	getTemplate() {
		return null;
	}

	/**
	 * Get default field values.
	 *
	 * @returns {object}
	 */
	getDefaultData() {
		return {};
	}

	/**
	 * Validates and returns valid data.
	 *
	 * @param {object} data
	 * @returns {object}
	 */
	getValidatedData(data) {
		return {...this.getDefaultData(), ...data};
	}

	onInit() {
		const column_options = this.#column.getColumnOptions();

		this.#fields = this.getFields();
		this.#data = this.getValidatedData(column_options);
	}

	onOpen() {
		const context_links = [];

		this.#element.classList.add(CDataTableOptionsPopup.ZBX_STYLE_OPTIONS_POPUP);
		this.#element.setAttribute('role', 'dialog');
		this.#element.setAttribute('tabindex', '-1');

		const template = this.getTemplate();

		if (template instanceof HTMLElement) {
			this.#template = template;

			this.#element.append(...this.#template.children);
		}
		else if (typeof template === 'string') {
			this.#template = new Template(template).evaluateToElement();

			this.#element.innerHTML = this.#template.innerHTML;
		}

		const column_index = this.#column.getColumnIndex();

		if (this.#column.isDuplicate() || this.#column.isDuplicatable()) {
			const duplicate_link = this.#addContextLink(t('Duplicate column'), e => {
				e.preventDefault();

				this.#datatable.dispatchEvent(CDataTable.EVENT_COLUMN_DUPLICATE, {column_index});
			});

			context_links.push(duplicate_link);
		}

		if (this.#column.isDuplicate() || this.#column.isRenamable()) {
			const form_label = document.createElement('label');
			form_label.classList.add(ZBX_STYLE_FORM_LABEL);
			form_label.setAttribute('for', `column_name_${column_index}`);
			form_label.textContent = t('Column name');

			const form_input = document.createElement('input');
			form_input.classList.add(ZBX_STYLE_FORM_FIELD);
			form_input.setAttribute('type', 'text');
			form_input.setAttribute('maxlength', CDataTableOptionsPopup.COLUMN_NAME_MAXLENGTH.toString());
			form_input.setAttribute('data-field-type', 'text-box');
			form_input.value = this.#column.getName();
			form_input.addEventListener('input', e => {
				const name = e.target.value.substring(0, CDataTableOptionsPopup.COLUMN_NAME_MAXLENGTH);

				this.#datatable.dispatchEvent(CDataTable.EVENT_COLUMN_RENAME, {column_index, name});
			});

			const form_field = document.createElement('div');
			form_field.classList.add(ZBX_STYLE_FORM_FIELD);
			form_field.appendChild(form_input);

			this.#element.prepend(form_label, form_field);

			if (this.#column.isDuplicate()) {
				const delete_link = this.#addContextLink(t('Delete column'), e => {
					e.preventDefault();

					this.#datatable.dispatchEvent(CDataTable.EVENT_COLUMN_DELETE, {column_index});
				});

				context_links.push(delete_link);
			}
		}

		if (context_links.length > 0) {
			const popup_links = document.createElement('div');
			popup_links.classList.add(CDataTableOptionsPopup.ZBX_STYLE_OPTIONS_POPUP_LINKS);

			for (const context_link of context_links) {
				popup_links.appendChild(context_link);
			}

			this.#element.appendChild(popup_links);
		}

		document.addEventListener('mousedown', this.onMouseDown);
		document.addEventListener('click', this.onClickOutside);
		document.addEventListener('keydown', this.onKeyDown);

		this.#element.addEventListener('input', () => {
			const column_options = this.getValidatedData(this.getFieldData());

			this.dispatchEvent(CDataTableOptionsPopup.EVENT_UPDATE, {column_index, column_options});
		});
	}

	onReset() {
		const column_index = this.#column.getColumnIndex();

		this.#datatable.dispatchEvent(CDataTable.EVENT_COLUMN_RENAME, {
			column_index,
			name: this.#column_name
		});

		this.#column.setColumnOptions(this.#data);

		this.dispatchEvent(CDataTableOptionsPopup.EVENT_SAVE, {column_index});
		this.dispatchEvent(CDataTableOptionsPopup.EVENT_CLOSE);
	}

	onClose() {
		document.removeEventListener('mousedown', this.onMouseDown);
		document.removeEventListener('keydown', this.onKeyDown);
		document.removeEventListener('click', this.onClickOutside);

		this.#element.remove();

		this.#mouse_down_inside = false;
	}

	onUpdate() {}

	onSave() {
		const column_index = this.#column.getColumnIndex();
		const name = this.#column.getName();
		const column_options = this.#column.getColumnOptions();

		const save = this.#column_name !== name || !deepCompare(this.#data, column_options);

		this.dispatchEvent(CDataTableOptionsPopup.EVENT_UPDATE, {column_index, column_options, save});
	}

	/**
	 * @param {string}   event
	 * @param {function} callback
	 * @returns {CDataTableOptionsPopup}
	 */
	on(event, callback) {
		this.#element.addEventListener(event, callback.bind(this));

		return this;
	}

	/**
	 * @param {string} type
	 * @param {object} detail
	 * @param {object} options
	 * @returns {boolean}
	 */
	dispatchEvent(type, detail = {}, options = {}) {
		return this.#element.dispatchEvent(new CustomEvent(type, {...options, detail}));
	}

	/**
	 * Positions context popup relative to handle.
	 */
	position() {
		const wrapper = document.querySelector('.wrapper');
		const element_rect = this.getDataTable().getElement().getBoundingClientRect();
		const handle_rect = this.#handle.getBoundingClientRect();
		const popup_rect = this.#element.getBoundingClientRect();
		const offset_top = wrapper.scrollTop + this.#datatable.getElement().getBoundingClientRect().top;

		if (handle_rect.left + popup_rect.width > window.innerWidth) {
			this.#element.style.right = `${element_rect.right - handle_rect.right - 1}px`;
		}
		else {
			this.#element.style.left = `${handle_rect.left - element_rect.left - 1}px`;
		}

		if (this.#datatable.isStickyHeader()) {
			const top = handle_rect.height + (handle_rect.top == 0 ? wrapper.scrollTop - offset_top : 0) + 2;

			this.#element.style.top = `${top}px`;
		}
	}

	/**
	 * Callback for handling a click outside popup.
	 *
	 * @param {PointerEvent} e
	 */
	onClickOutside = e => {
		const elements = [this.#handle, this.#element];

		if (!e.target.parentElement || e.target === this.#element) {
			return;
		}

		if (elements.includes(e.target) || elements.some(element => element.contains(e.target))) {
			return;
		}

		if (this.#mouse_down_inside) {
			return;
		}

		this.dispatchEvent(CDataTableOptionsPopup.EVENT_SAVE);
		this.dispatchEvent(CDataTableOptionsPopup.EVENT_CLOSE);
	}

	onMouseDown = e => {
		this.#mouse_down_inside = this.#element.contains(e.target);
	}

	/**
	 * Callback for handling an "Escape" button.
	 *
	 * @param {KeyboardEvent} e
	 */
	onKeyDown = e => {
		if (e.key === 'Enter') {
			this.dispatchEvent(CDataTableOptionsPopup.EVENT_SAVE);
			this.dispatchEvent(CDataTableOptionsPopup.EVENT_CLOSE);

			this.position();
		}

		if (e.key === 'Escape') {
			this.dispatchEvent(CDataTableOptionsPopup.EVENT_RESET);
		}
	}

	isOpen(handle) {
		return this.#handle === handle;
	}

	#addContextLink(label, callback) {
		const context_link = document.createElement('a');
		context_link.setAttribute('href', 'javascript:void(0);');
		context_link.textContent = label;
		context_link.addEventListener('click', callback);

		const context_link_container = document.createElement('div');
		context_link_container.classList.add(CDataTableOptionsPopup.ZBX_STYLE_OPTIONS_POPUP_LINK);
		context_link_container.appendChild(context_link);

		return context_link_container;
	}

	/**
	 * Binds all events to their corresponding handlers.
	 *
	 * Iterates over the events object and registers each event callback using the `on` method.
	 */
	#bindEvents() {
		Object.entries(this.#events).forEach(([name, callback]) => this.on(name, callback));
	}
}
