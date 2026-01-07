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


class CDataTableContextPopup {

	static COLUMN_NAME_MAXLENGTH = 32;

	static EVENT_INIT = 'init';
	static EVENT_OPEN = 'open';
	static EVENT_CLOSE = 'close';
	static EVENT_RESET = 'reset';
	static EVENT_UPDATE = 'update';
	static EVENT_SAVE = 'save';

	static ZBX_STYLE_CONTEXT = 'datatable-context';
	static ZBX_STYLE_CONTEXT_LINKS = 'datatable-context-links';
	static ZBX_STYLE_CONTEXT_LINK = 'datatable-context-link';

	/**
	 * @type {CDataTable}
	 */
	#datatable;

	/**
	 * @type {CDataTableColumn}
	 */
	#column_config;

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
	#popup;

	/**
	 * @type {HTMLElement|null}
	 */
	#template = null;

	/**
	 * @type {Object<string, any>}
	 */
	#fields = {};

	/**
	 * @type {Object<string, any>}
	 */
	#data = {};

	/**
	 * @type {Object<string, function>}
	 */
	#events = {
		[CDataTableContextPopup.EVENT_INIT]: this.onInit,
		[CDataTableContextPopup.EVENT_OPEN]: this.onOpen,
		[CDataTableContextPopup.EVENT_CLOSE]: this.onClose,
		[CDataTableContextPopup.EVENT_RESET]: this.onReset,
		[CDataTableContextPopup.EVENT_UPDATE]: this.onUpdate,
		[CDataTableContextPopup.EVENT_SAVE]: this.onSave,
	}

	/**
	 * @param {CDataTable}       datatable
	 * @param {CDataTableColumn} column_config
	 * @param {HTMLElement}      header_cell
	 * @param {HTMLElement}      handle
	 */
	constructor(datatable, column_config, header_cell, handle) {
		this.#datatable = datatable;
		this.#column_config = column_config;
		this.#column_name = column_config.getName();
		this.#header_cell = header_cell;
		this.#handle = handle;
		this.#popup = document.createElement('div');

		this.#bindEvents();

		requestAnimationFrame(() => this.dispatchEvent(CDataTableContextPopup.EVENT_INIT));
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
		return this.#column_config;
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
	getPopup() {
		return this.#popup;
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
	 * @returns {Element|null}
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
		const context_popup_data = this.#column_config.getContextPopupData();

		this.#fields = this.getFields();
		this.#data = this.getValidatedData(context_popup_data);
	}

	onOpen() {
		const context_links = [];

		this.#popup.classList.add(CDataTableContextPopup.ZBX_STYLE_CONTEXT);
		this.#popup.setAttribute('role', 'dialog');
		this.#popup.setAttribute('tabindex', '-1');

		this.#template = this.getTemplate();

		if (this.#template instanceof HTMLTemplateElement) {
			this.#popup.innerHTML = this.#template.innerHTML;
		}
		else if (this.#template instanceof HTMLElement) {
			Array.from(this.#template.children).forEach(child => this.#popup.appendChild(child));
		}

		const column_index = this.#column_config.getColumnIndex();

		if (this.#column_config.isDuplicate() || this.#column_config.isDuplicatable()) {
			const duplicate_link = this.#addContextLink(t('Duplicate column'), event => {
				event.preventDefault();

				this.#datatable.dispatchEvent(CDataTable.EVENT_COLUMN_DUPLICATE, {column_index});
			});

			context_links.push(duplicate_link);
		}

		if (this.#column_config.isDuplicate() || this.#column_config.isRenamable()) {
			const form_label = document.createElement('label');
			form_label.classList.add(ZBX_STYLE_FORM_LABEL);
			form_label.setAttribute('for', `column_name_${column_index}`);
			form_label.textContent = t('Column name');

			const form_input = document.createElement('input');
			form_input.classList.add(ZBX_STYLE_FORM_FIELD);
			form_input.setAttribute('type', 'text');
			form_input.setAttribute('maxlength', CDataTableContextPopup.COLUMN_NAME_MAXLENGTH.toString());
			form_input.setAttribute('data-field-type', 'text-box');
			form_input.value = this.#column_config.getName();
			form_input.addEventListener('input', event => {
				const name = event.target.value.substring(0, CDataTableContextPopup.COLUMN_NAME_MAXLENGTH);

				this.#datatable.dispatchEvent(CDataTable.EVENT_COLUMN_RENAME, {column_index, name});
			});

			const form_field = document.createElement('div');
			form_field.classList.add(ZBX_STYLE_FORM_FIELD);
			form_field.appendChild(form_input);

			this.#popup.prepend(form_label, form_field);

			if (this.#column_config.isDuplicate()) {
				const delete_link = this.#addContextLink(t('Delete column'), event => {
					event.preventDefault();

					this.#datatable.dispatchEvent(CDataTable.EVENT_COLUMN_DELETE, {column_index});
				});

				context_links.push(delete_link);
			}
		}

		if (context_links.length > 0) {
			const popup_links = document.createElement('div');
			popup_links.classList.add(CDataTableContextPopup.ZBX_STYLE_CONTEXT_LINKS);

			for (const context_link of context_links) {
				popup_links.appendChild(context_link);
			}

			this.#popup.appendChild(popup_links);
		}

		document.addEventListener('click', this.onClickOutside);
		document.addEventListener('keydown', this.onKeyDown);

		this.#popup.addEventListener('input', () => {
			const context_popup_data = this.getValidatedData(this.getFieldData());

			this.dispatchEvent(CDataTableContextPopup.EVENT_UPDATE, {column_index, context_popup_data});
		});
	}

	onReset() {
		const column_index = this.#column_config.getColumnIndex();

		this.#datatable.dispatchEvent(CDataTable.EVENT_COLUMN_RENAME, {
			column_index,
			name: this.#column_name
		});

		this.#column_config.setContextPopupData(this.#data);

		this.dispatchEvent(CDataTableContextPopup.EVENT_SAVE,  {column_index});
		this.dispatchEvent(CDataTableContextPopup.EVENT_CLOSE);
	}

	onClose() {
		document.removeEventListener('keydown', this.onKeyDown);
		document.removeEventListener('click', this.onClickOutside);

		this.#popup.remove();
	}

	onUpdate() {}

	onSave() {
		const column_index = this.#column_config.getColumnIndex();
		const name = this.#column_config.getName();
		const context_popup_data = this.#column_config.getContextPopupData();

		const save = this.#column_name != name || !deepCompare(this.#data, context_popup_data);

		this.dispatchEvent(CDataTableContextPopup.EVENT_UPDATE, {column_index, context_popup_data, save});
	}

	/**
	 * @param {string}   event
	 * @param {function} callback
	 */
	on(event, callback) {
		this.#popup.addEventListener(event, callback.bind(this));
	}

	/**
	 * @param {string} type
	 * @param {object} detail
	 * @param {object} options
	 * @returns {boolean}
	 */
	dispatchEvent(type, detail = {}, options = {}) {
		return this.#popup.dispatchEvent(new CustomEvent(type, {...options, detail}));
	}

	/**
	 * Positions context popup relative to handle.
	 */
	position() {
		const wrapper = document.querySelector('.wrapper');
		const element_rect = this.getDataTable().getElement().getBoundingClientRect();
		const handle_rect = this.#handle.getBoundingClientRect();
		const popup_rect = this.#popup.getBoundingClientRect();

		this.#popup.style.maxHeight = `${wrapper.scrollHeight - popup_rect.top}px`; // FIXME: -141?

		if (handle_rect.left + popup_rect.width > window.innerWidth) {
			this.#popup.style.right = `${element_rect.right - handle_rect.right - 1}px`;
		}
		else {
			this.#popup.style.left = `${handle_rect.left - element_rect.left - 1}px`;
		}
	}

	/**
	 * Callback for handling a click outside popup.
	 *
	 * @param {PointerEvent} event
	 */
	onClickOutside = event => {
		const elements = [this.#handle, this.#popup];

		if (event.target == this.#popup) {
			return;
		}

		if (elements.includes(event.target) || elements.some(element => element.contains(event.target))) {
			return;
		}

		this.dispatchEvent(CDataTableContextPopup.EVENT_SAVE);
		this.dispatchEvent(CDataTableContextPopup.EVENT_CLOSE);
	}

	/**
	 * Callback for handling an "Escape" button.
	 *
	 * @param {KeyboardEvent} event
	 */
	onKeyDown = event => {
		if (event.key == 'Enter') {
			this.dispatchEvent(CDataTableContextPopup.EVENT_SAVE);
			this.dispatchEvent(CDataTableContextPopup.EVENT_CLOSE);

			this.position();
		}

		if (event.key == 'Escape') {
			this.dispatchEvent(CDataTableContextPopup.EVENT_RESET);
		}
	}

	#addContextLink(label, callback) {
		const context_link = document.createElement('a');
		context_link.setAttribute('href', 'javascript:void(0);');
		context_link.innerText = label;
		context_link.addEventListener('click', callback);

		const context_link_container = document.createElement('div');
		context_link_container.classList.add(CDataTableContextPopup.ZBX_STYLE_CONTEXT_LINK);
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
