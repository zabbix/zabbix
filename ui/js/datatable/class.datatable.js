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


class CDataTable {

	static EVENT_INIT = 'init';
	static EVENT_RENDER = 'render';
	static EVENT_RESET = 'reset';
	static EVENT_SAVE = 'save';
	static EVENT_SCROLL = 'scroll';
	static EVENT_DATA_SORT = 'data:sort';
	static EVENT_COLUMNS_SORT = 'columns:sort';
	static EVENT_COLUMN_RESIZE = 'column:resize';
	static EVENT_COLUMN_RESIZE_START = 'column:resize:start';
	static EVENT_COLUMN_RESIZE_END = 'column:resize:end';
	static EVENT_COLUMN_RESET = 'column:reset';
	static EVENT_COLUMN_TOGGLE = 'column:toggle';
	static EVENT_COLUMN_DUPLICATE = 'column:duplicate';
	static EVENT_COLUMN_RENAME = 'column:rename';
	static EVENT_COLUMN_DELETE = 'column:delete';
	static EVENT_COLUMN_OPTIONS_POPUP = 'column-options';
	static EVENT_COLUMN_OPTIONS_POPUP_OPEN = 'column-options:open';
	static EVENT_COLUMN_OPTIONS_POPUP_CLOSE = 'column-options:close';
	static EVENT_COLUMN_OPTIONS_POPUP_UPDATE = 'column-options:update';
	static EVENT_OPTIONS_POPUP = 'options';
	static EVENT_OPTIONS_POPUP_OPEN = 'options:open';
	static EVENT_OPTIONS_POPUP_CLOSE = 'options:close';
	static EVENT_OPTIONS_POPUP_UPDATE = 'options:update';

	static ZBX_STYLE_DATATABLE = 'datatable';
	static ZBX_STYLE_RESIZING = 'datatable-resizing';
	static ZBX_STYLE_SCROLLABLE = 'datatable-scrollable';
	static ZBX_STYLE_BODY = 'datatable-body';
	static ZBX_STYLE_HEADER = 'datatable-header';
	static ZBX_STYLE_HEADER_STICKY = 'datatable-header-sticky';
	static ZBX_STYLE_ROW = 'row';
	static ZBX_STYLE_ROW_DISABLED = 'row-disabled';
	static ZBX_STYLE_ROW_SPACER = 'row-spacer';
	static ZBX_STYLE_FOOTER = 'datatable-footer';
	static ZBX_STYLE_FOOTER_STICKY = 'datatable-footer-sticky';
	static ZBX_STYLE_OPTIONS_LINK = 'datatable-options-link';
	static ZBX_STYLE_OPTIONS_LINK_OPENED = 'datatable-options-link-opened';
	static ZBX_STYLE_OPTIONS_BUTTON = 'datatable-options-button';

	static ZBX_STYLE_CELL = 'cell';
	static ZBX_STYLE_CELL_BG = 'cell-bg';
	static ZBX_STYLE_CELL_BG_HOVER = 'cell-bg-hover';
	static ZBX_STYLE_CELL_INNER = 'cell-inner';
	static ZBX_STYLE_CELL_HEADER = 'cell-header';
	static ZBX_STYLE_CELL_HEADER_LINK = 'cell-header-link';
	static ZBX_STYLE_CELL_HEADER_RESIZER = 'cell-header-resizer';
	static ZBX_STYLE_CELL_DATA = 'cell-data';
	static ZBX_STYLE_CELL_ERROR = 'cell-error';
	static ZBX_STYLE_CELL_CONTEXT = 'cell-context';
	static ZBX_STYLE_CELL_STICKY = 'cell-sticky';
	static ZBX_STYLE_CELL_FOCUSED = 'cell-focused';
	static ZBX_STYLE_CELL_RESIZING = 'cell-resizing';
	static ZBX_STYLE_CELL_CHECKBOX = 'cell-checkbox';

	static ZBX_STYLE_LINK_HEADER = 'header-link';
	static ZBX_STYLE_LINK_HEADER_SORTED = 'header-link-sorted';

	/**
	 * Minimum width of the resized column in pixels.
	 *
	 * @type {number}
	 */
	static RESIZE_MIN_WIDTH = 37;

	/**
	 * Delay in milliseconds before `#resize_click_count` is reset.
	 * Used to distinguish between single and double-click actions on the column resizer.
	 *
	 * @type {number}
	 */
	static RESIZE_CLICK_COUNT_RESET_DELAY = 250;

	/**
	 * @type {number}
	 */
	static COLUMN_TOGGLE_INITIAL_MIN_WIDTH = 150;

	/**
	 * Flag to determine when a component is initialized, to disallow any further modifications.
	 *
	 * @type {boolean}
	 */
	#initialized = false;

	/**
	 * Determines whether a column is being resized or not.
	 *
	 * @type {boolean}
	 */
	#resizing = false;

	/**
	 * Determines whether columns in a table are resizable or not.
	 *
	 * @type {boolean}
	 */
	#resizable = true;

	/**
	 * Determines whether a table can be customized or not.
	 *
	 * @type {boolean}
	 */
	#customizable = true;

	/**
	 * Index of the column that is being resized.
	 *
	 * @type {number}
	 */
	#resize_column_index = -1;

	/**
	 * The starting position of the mouse cursor on X axis (in pixels).
	 *
	 * @type {number}
	 */
	#resize_start_x = 0;

	/**
	 * The starting width of the column.
	 * Used to calculate the delta value (in pixels).
	 *
	 * @type {number}
	 */
	#resize_start_width = 0;

	/**
	 * Timeout ID used to reset `#resize_click_count` after a delay.
	 * This prevents conflicts between single and double-click events on the column resizer.
	 *
	 * @type {number}
	 */
	#resize_click_timeout = -1;

	/**
	 * Number of consecutive resize clicks detected within a `#resize_click_timeout` timeout.
	 * Used to differentiate between single and double-clicks on the column resizer.
	 *
	 * @type {number}
	 */
	#resize_click_count = 0;

	/**
	 * Holds an opened instance of the context popup.
	 *
	 * @type {CDataTableOptionsPopup|CDataTableOptionsPopupTableOptions|null}
	 */
	#options_popup = null;

	/**
	 * Used to track whether the context popup was updated, to be able to differentiate between scroll with mouse wheel
	 * and manually triggered scroll due to updated data cell contents.
	 *
	 * @type {boolean}
	 */
	#options_popup_updated = false;

	/**
	 * This holds a request when saving the current table's configuration.
	 *
	 * @type {Object|null}
	 */
	#save_config_request = null;

	#data_provider;

	#form_name = null;

	#checkbox_id = null;

	#selectable = null;

	#storage_idx = null;

	#page = 1;

	#filter = {};

	#tabfilter_item = { _index: 1 };

	#pager = null;

	#sort_field = null;

	#sort_order = null;

	#columns = [];

	#options = [];

	#header_renderers = {};

	#renderers = {};

	#row_renderers = {};

	#options_handlers = {};

	#user_configs = [];

	#element = null;

	#header = null;

	#sticky_header = false;

	#body = null;

	#footer = null;

	#sticky_footer = false;

	#templates = {};

	#events = {
		[CDataTable.EVENT_INIT]: this.onInit,
		[CDataTable.EVENT_RENDER]: this.onRender,
		[CDataTable.EVENT_RESET]: this.onReset,
		[CDataTable.EVENT_SAVE]: this.onSave,
		[CDataTable.EVENT_SCROLL]: this.onScroll,
		[CDataTable.EVENT_DATA_SORT]: this.onDataSort,
		[CDataTable.EVENT_COLUMNS_SORT]: this.onColumnsSort,
		[CDataTable.EVENT_COLUMN_RESIZE]: this.onColumnResize,
		[CDataTable.EVENT_COLUMN_RESIZE_START]: this.onColumnResizeStart,
		[CDataTable.EVENT_COLUMN_RESIZE_END]: this.onColumnResizeEnd,
		[CDataTable.EVENT_COLUMN_TOGGLE]: this.onColumnToggle,
		[CDataTable.EVENT_COLUMN_DELETE]: this.onColumnDelete,
		[CDataTable.EVENT_COLUMN_DUPLICATE]: this.onColumnDuplicate,
		[CDataTable.EVENT_COLUMN_RENAME]: this.onColumnRename,
		[CDataTable.EVENT_COLUMN_RESET]: this.onColumnReset,
		[CDataTable.EVENT_COLUMN_OPTIONS_POPUP]: this.onColumnOptionsPopup,
		[CDataTable.EVENT_COLUMN_OPTIONS_POPUP_OPEN]: this.onColumnOptionsPopupOpen,
		[CDataTable.EVENT_COLUMN_OPTIONS_POPUP_CLOSE]: this.onColumnOptionsPopupClose,
		[CDataTable.EVENT_COLUMN_OPTIONS_POPUP_UPDATE]: this.onColumnOptionsPopupUpdate,
		[CDataTable.EVENT_OPTIONS_POPUP]: this.onOptionsPopup,
		[CDataTable.EVENT_OPTIONS_POPUP_OPEN]: this.onOptionsPopupOpen,
		[CDataTable.EVENT_OPTIONS_POPUP_CLOSE]: this.onOptionsPopupClose,
		[CDataTable.EVENT_OPTIONS_POPUP_UPDATE]: this.onOptionsPopupUpdate
	};

	constructor(element, data_provider) {
		this.#element = element;
		this.#data_provider = data_provider;

		this.#header_renderers = {
			default: ({column_config, cell}) => {
				if (column_config.isSortable()) {
					const sort_field = column_config.getSortField() || column_config.getId();

					let sort_order = this.#sort_order;
					if (this.#sort_field == sort_field) {
						sort_order = sort_order == 'ASC' ? 'DESC' : 'ASC';
					}

					const label = document.createElement('span');
					label.classList.add('name');
					label.innerText = column_config.getName();

					const icon = document.createElement('span');

					if (this.#sort_field == sort_field) {
						icon.classList.add(sort_order == 'ASC' ? ZBX_STYLE_ARROW_DOWN : ZBX_STYLE_ARROW_UP);
					}

					const header_link = document.createElement('a');
					header_link.classList.add(CDataTable.ZBX_STYLE_LINK_HEADER);

					if (this.#sort_field == sort_field) {
						header_link.classList.add(CDataTable.ZBX_STYLE_LINK_HEADER_SORTED);
					}

					header_link.setAttribute('href', 'javascript:void(0);');
					header_link.append(label, icon);
					header_link.addEventListener('click', event => {
						event.preventDefault();

						this.dispatchEvent(CDataTable.EVENT_DATA_SORT, {sort_field, sort_order});
					});

					cell.classList.add(CDataTable.ZBX_STYLE_CELL_HEADER_LINK);
					cell.appendChild(header_link);
				}
				else if (column_config.getId() != this.#checkbox_id) {
					const cell_inner = document.createElement('span');
					cell_inner.classList.add(CDataTable.ZBX_STYLE_CELL_INNER);
					cell_inner.innerText = column_config.getName();

					cell.appendChild(cell_inner);
				}

				if (column_config.getOptionsPopupHandler()) {
					const icon = document.createElement('span');
					icon.classList.add(column_config.getOptionsPopupHandleIcon());

					const context_handle = document.createElement('button');
					context_handle.classList.add(CDataTable.ZBX_STYLE_OPTIONS_LINK);
					context_handle.setAttribute('type', 'button');
					context_handle.setAttribute('role', 'button');
					context_handle.appendChild(icon);
					context_handle.addEventListener('click', event => {
						event.preventDefault();

						this.dispatchEvent(CDataTable.EVENT_COLUMN_OPTIONS_POPUP, {handle: context_handle});
					});
					context_handle.addEventListener('mousedown', () => {
						cell.classList.add(CDataTable.ZBX_STYLE_CELL_FOCUSED);
					});

					if (this.#options_popup?.getColumnConfig().getColumnIndex() == column_config.getColumnIndex()) {
						context_handle.classList.add(CDataTable.ZBX_STYLE_OPTIONS_LINK_OPENED);

						this.#options_popup.setHandle(context_handle);
					}

					cell.appendChild(context_handle);
				}
			},
			[CDataTableColumn.CHECKBOX]: ({column_config, cell}) => {
				const id = column_config.getId();
				const checkbox_id = `all_${this.#form_name}`;

				const checkbox = document.createElement('input');
				checkbox.classList.add(ZBX_STYLE_CHECKBOX_RADIO);
				checkbox.setAttribute('type', 'checkbox');
				checkbox.setAttribute('id', checkbox_id);
				checkbox.setAttribute('name', checkbox_id);
				checkbox.setAttribute('data-field-type', 'checkbox');
				checkbox.setAttribute('onclick', `checkAll('${this.#form_name}', '${checkbox_id}', '${id}');`);
				checkbox.value = '1';

				const label = document.createElement('label');
				label.setAttribute('for', checkbox_id);
				label.appendChild(document.createElement('span'));

				cell.classList.add(CDataTable.ZBX_STYLE_CELL_CHECKBOX);
				cell.append(checkbox, label);
			}
		};

		this.setOptionsHandler('tags', 'CDataTableOptionsPopupTags');
		this.setOptionsHandler('tagvalue', 'CDataTableOptionsPopupTagValue');

		this.setRowRenderer('default', this.renderDataCells);

		this.setRenderer(CDataTableColumn.RENDERER_HTML, ({column_data, cell_inner}) => {
			cell_inner.innerHTML = column_data.toString();
		});

		this.setRenderer(CDataTableColumn.RENDERER_TEXT, ({column_data, cell_inner}) => {
			cell_inner.innerText = column_data.toString();
		});

		this.setRenderer(CDataTableColumn.CHECKBOX, ({column_config, column_data, cell, cell_inner}) => {
			const [object_id, data_actions] = column_data;

			if (!object_id) {
				return;
			}

			const input_id = `${column_config.getId()}_${object_id}`;

			const checkbox = document.createElement('input');
			checkbox.classList.add(ZBX_STYLE_CHECKBOX_RADIO);
			checkbox.setAttribute('type', 'checkbox');
			checkbox.setAttribute('id', input_id);
			checkbox.setAttribute('name', `${column_config.getId()}[${object_id}]`);
			checkbox.setAttribute('data-field-type', 'checkbox');
			checkbox.value = object_id.toString();

			if (data_actions) {
				checkbox.setAttribute('data-actions', Object.keys(data_actions).join(' '));
			}

			const label = document.createElement('label');
			label.setAttribute('for', input_id);
			label.appendChild(document.createElement('span'));

			cell.classList.add(CDataTable.ZBX_STYLE_CELL_CHECKBOX);

			cell_inner.append(checkbox, label);
		});

		this.setRenderer('tagvalue', ({column_config, column_data, cell_inner}) => {
			const column_options = column_config.getColumnOptions();

			let [tags] = column_data;

			if (!tags) {
				return;
			}

			tags = tags.filter(tag => tag.tag == column_options['tag_name']);
			if (tags.length == 0) {
				return;
			}

			const tags_wrapper = document.createElement('div');
			tags_wrapper.classList.add(ZBX_STYLE_TAGS_WRAPPER);

			const tag_label = document.createElement('span');
			tag_label.classList.add(ZBX_STYLE_TAG);
			tag_label.innerText = tags[0].value;

			if (tags[0].type == ZBX_PROPERTY_INHERITED) {
				tag_label.classList.add(ZBX_STYLE_TAG_INHERITED);
			}

			const tag_label_hintbox = document.createElement('div');

			if (tags[0].type == ZBX_PROPERTY_INHERITED) {
				const inherited_title = document.createElement('div');
				inherited_title.classList.add(ZBX_STYLE_TAG_INHERITED_TITLE);
				inherited_title.innerText = t('Inherited tag');

				tag_label_hintbox.appendChild(inherited_title);
			}

			const hintbox_contents = document.createTextNode(`${tags[0].tag}: ${tags[0].value}`);
			tag_label_hintbox.appendChild(hintbox_contents);

			tag_label.setAttribute('data-hintbox-contents', tag_label_hintbox.outerHTML);
			tag_label.setAttribute('data-hintbox', '1');
			tag_label.setAttribute('data-hintbox-static', '1');

			tags_wrapper.appendChild(tag_label);

			cell_inner.appendChild(tags_wrapper);
		});

		this.setRenderer('tags', ({column_config, column_data, cell_inner}) => {
			let [tags] = column_data;

			if (!tags) {
				return;
			}

			let tag_display_priorities = new Set();

			const column_options = column_config.getColumnOptions();
			const tag_display_priority = column_options['tag_display_priority'] || '';
			const number_of_tags = column_options['number_of_tags'] || SHOW_TAGS_3;
			const tag_name_display = column_options['tag_name_display'] || TAG_NAME_FULL;

			tag_display_priority
				.split(',')
				.map((priority) => priority.trim())
				.filter(Boolean)
				.forEach((priority) => tag_display_priorities.add(priority));

			if (tag_display_priorities.size > 0) {
				tag_display_priorities = [...tag_display_priorities];

				const matched = tag_display_priorities.flatMap((priority) =>
					tags.filter((tag) => tag.tag.startsWith(priority))
				);

				const unmatched = tags.filter(
					(tag) => !tag_display_priorities.some((priority) => tag.tag.startsWith(priority))
				);

				tags = [...matched, ...unmatched];
			}

			const tag_labels = [];

			let count = number_of_tags;

			const tags_wrapper = document.createElement('div');
			tags_wrapper.classList.add(ZBX_STYLE_TAGS_WRAPPER);

			this.getData().then(response => {
				const {subfilter_tags} = {subfilter_tags: null, ...response};

				tags.forEach((tag) => {
					let tag_label;

					const subfilter_tag = subfilter_tags != null
						? Object.keys(subfilter_tags).includes(tag.tag)
						: false;
					const subfilter_tag_value = subfilter_tag
						? Object.keys(subfilter_tags[tag.tag]).includes(tag.value)
						: false;

					if (subfilter_tags != null && (!subfilter_tag || !subfilter_tag_value)) {
						tag_label = document.createElement('button');
						tag_label.classList.add(ZBX_STYLE_BTN_TAG, ZBX_STYLE_TAG);
						tag_label.setAttribute('type', 'button');
						tag_label.setAttribute('role', 'button');
						tag_label.setAttribute('data-key', tag.tag);
						tag_label.setAttribute('data-value', tag.value);
						tag_label.setAttribute('onclick',
							'view.setSubfilter([`subfilter_tags[${encodeURIComponent(this.dataset.key)}][]`,'+
							'this.dataset.value'+
							']);');
					}
					else {
						tag_label = document.createElement('span');
						tag_label.classList.add(ZBX_STYLE_TAG);
					}

					tag_label.innerText = `${tag.tag}: ${tag.value}`;

					if (tag.type == ZBX_PROPERTY_INHERITED) {
						tag_label.classList.add(ZBX_STYLE_TAG_INHERITED);
					}

					const tag_label_hintbox = document.createElement('div');

					if (tag.type == ZBX_PROPERTY_INHERITED) {
						const inherited_title = document.createElement('div');
						inherited_title.classList.add(ZBX_STYLE_TAG_INHERITED_TITLE);
						inherited_title.innerText = t('Inherited tag');

						tag_label_hintbox.appendChild(inherited_title);
					}

					if (tag.type == ZBX_PROPERTY_BOTH) {
						tag_label.classList.add(ZBX_STYLE_TAG_INHERITED_DUPLICATE);

						const hint_titles = {
							[ZBX_TAG_OBJECT_TEMPLATE]: t('Inherited and template tag'),
							[ZBX_TAG_OBJECT_HOST]: t('Inherited and host tag'),
							[ZBX_TAG_OBJECT_HOST_PROTOTYPE]: t('Inherited and host prototype tag'),
							[ZBX_TAG_OBJECT_ITEM]: t('Inherited and item tag'),
							[ZBX_TAG_OBJECT_ITEM_PROTOTYPE]: t('Inherited and item prototype tag'),
							[ZBX_TAG_OBJECT_TRIGGER]: t('Inherited and trigger tag'),
							[ZBX_TAG_OBJECT_TRIGGER_PROTOTYPE]: t('Inherited and trigger prototype tag'),
							[ZBX_TAG_OBJECT_HTTPTEST]: t('Inherited and web scenario tag')
						};

						const inherited_title = document.createElement('div');
						inherited_title.classList.add(ZBX_STYLE_TAG_INHERITED_TITLE);
						inherited_title.innerText = CDataTableColumnTags.object_type
							? hint_titles[CDataTableColumnTags.object_type]
							: '';

						tag_label_hintbox.appendChild(inherited_title);
					}

					const hintbox_contents = document.createTextNode(tag_label.innerText);
					tag_label_hintbox.appendChild(hintbox_contents);

					tag_label.setAttribute('data-hintbox-contents', tag_label_hintbox.outerHTML);
					tag_label.setAttribute('data-hintbox', '1');
					tag_label.setAttribute('data-hintbox-static', '1');

					if (count > 0) {
						let name = `${tag.tag}: ${tag.value}`;

						if (tag_name_display != TAG_NAME_FULL) {
							name = tag_name_display == TAG_NAME_SHORTENED
								? `${tag.tag.substring(0, 3)}: ${tag.value}`
								: tag.value;
						}

						const tag_label_clone = tag_label.cloneNode(true);
						tag_label_clone.innerText = name;

						tags_wrapper.appendChild(tag_label_clone);
					}

					tag_labels.push(tag_label);
					count--;
				});

				if (tags.length > number_of_tags) {
					const more_tags_hintbox = document.createElement('div');

					for (const tag_label of tag_labels) {
						more_tags_hintbox.appendChild(tag_label.cloneNode(true));
					}

					const more_tags = document.createElement('button');
					more_tags.classList.add(ZBX_STYLE_BTN_ICON, ZBX_ICON_MORE);
					more_tags.setAttribute('data-hintbox', '1');
					more_tags.setAttribute('data-hintbox-contents', more_tags_hintbox.innerHTML);
					more_tags.setAttribute('data-hintbox-class', `${ZBX_STYLE_HINTBOX_WRAP} ${ZBX_STYLE_TAGS_WRAPPER}`);
					more_tags.setAttribute('data-hintbox-static', '1');

					tags_wrapper.appendChild(more_tags);
				}
			});

			cell_inner.appendChild(tags_wrapper);
		});

		this.setOptionsHandler(CDataTableColumn.TABLE_OPTIONS, 'CDataTableOptionsPopupTableOptions');

		this.#templates = {
			footer: new Template(`
				<div class="${CDataTable.ZBX_STYLE_FOOTER} ${ZBX_STYLE_HIDDEN}">
					<div id="selected_count">0 ${t('selected')}</div>
				</div>
			`),
			row_spacer: new Template(`
				<div class="${CDataTable.ZBX_STYLE_ROW_SPACER}"></div>
			`),
			table_options_button: new Template(`
				<div class="${CDataTable.ZBX_STYLE_OPTIONS_BUTTON} ${ZBX_STYLE_HIDDEN}" tabindex="-1">
					<button class="${CDataTable.ZBX_STYLE_OPTIONS_LINK}" type="button" role="button">
						<span class="${ZBX_ICON_FILTERS}"></span>
					</button>
				</div>
			`)
		}
	}

	init(user_configs) {
		this.#user_configs = user_configs || [{}];

		this.#initColumns();

		this.#header = document.createElement('div');
		this.#header.classList.add(CDataTable.ZBX_STYLE_HEADER);

		if (this.#sticky_header) {
			this.#header.classList.add(CDataTable.ZBX_STYLE_HEADER_STICKY);
		}

		this.#body = document.createElement('div');
		this.#body.classList.add(CDataTable.ZBX_STYLE_BODY);
		this.#body.addEventListener('scroll', () => this.dispatchEvent(CDataTable.EVENT_SCROLL));
		this.#body.appendChild(this.#createNoDataMessage());

		this.#footer = this.#templates.footer.evaluateToElement();

		if (this.#sticky_footer) {
			this.#footer.classList.add(CDataTable.ZBX_STYLE_FOOTER_STICKY);
		}

		this.#applyColumnWidths();
		this.#renderHeaderCells();

		this.#element.classList.add(CDataTable.ZBX_STYLE_DATATABLE, CDataTable.ZBX_STYLE_SCROLLABLE);
		this.#element.innerHTML = '';
		this.#element.append(this.#header, this.#body, this.#footer);

		this.#pager = new CPager(this.#footer);
		this.#pager
			.on(CPager.EVENT_SELECT, event => {
				const {page} = event.detail;

				this.#page = page;

				this.dispatchEvent(CDataTable.EVENT_INIT);
				this.dispatchEvent(CPager.EVENT_SELECT, event.detail);
			})
			.on(CPager.EVENT_STATE_CHANGE, event => {
				this.dispatchEvent(CPager.EVENT_STATE_CHANGE, event.detail);
			});

		this.#bindEvents();

		this.dispatchEvent(CDataTable.EVENT_INIT);

		return this;
	}

	setRowRenderer(name, callback) {
		if (this.#initialized) {
			return this;
		}

		this.#row_renderers[name] = callback.bind(this);

		return this;
	}

	setRenderer(name, callback) {
		if (this.#initialized) {
			return this;
		}

		this.#renderers[name] = callback.bind(this);

		return this;
	}

	setOptionsHandler(name, handler) {
		if (this.#initialized) {
			return this;
		}

		this.#options_handlers[name] = handler;

		return this;
	}

	getElement() {
		return this.#element;
	}

	getHeaders() {
		return this.#header;
	}

	getDataProvider() {
		return this.#data_provider;
	}

	getColumns() {
		return this.#columns;
	}

	getColumnConfigById(id, duplicate = false) {
		return this.#columns
			.find(column_config => column_config.getId() == id && column_config.isDuplicate() == duplicate);
	}

	getCheckboxColumnConfig() {
		return this.getColumnConfigById(this.#checkbox_id);
	}

	setColumns(columns) {
		this.#columns = columns;

		return this;
	}

	getOptions() {
		return this.#options;
	}

	getOption(id) {
		return this.#options[id];
	}

	setOption(id, name, params = {}) {
		this.#options[id] = Object.assign({
			checked: false,
			onRender: () => {},
			onChange: () => {},
			isChanged: function () {
				return this.checked;
			},
			onReset: function () {
				this.checked = false;
			}
		}, params, {id, name});

		return this;
	}

	updateOption(id, params) {
		const option = this.#options[id] || {};

		this.#options[id] = {...option, ...params};

		this.updateUserConfig();
	}

	setSelectable(form_name, checkboxid, selectable = []) {
		this.#form_name = form_name;
		this.#checkbox_id = checkboxid;
		this.#selectable = selectable;

		return this;
	}

	setStorageIdx(storage_idx) {
		this.#storage_idx = storage_idx;

		return this;
	}

	setResizable(resizable) {
		this.#resizable = resizable;

		return this;
	}

	setCustomizable(customizable) {
		this.#customizable = customizable;

		return this;
	}

	setPage(page) {
		this.#page = page;

		return this;
	}

	getFilter() {
		return this.#filter;
	}

	setFilter(filter) {
		this.#filter = filter;

		return this;
	}

	getPage() {
		return this.#pager.getPage();
	}

	setTabFilterItem(tabfilter_item) {
		this.#tabfilter_item = tabfilter_item;

		return this;
	}

	getSortField() {
		return this.#sort_field;
	}

	setSortField(sort_field) {
		this.#sort_field = sort_field;

		return this;
	}

	getSortOrder() {
		return this.#sort_order;
	}

	setSortOrder(sort_order) {
		this.#sort_order = sort_order;

		return this;
	}

	isStickyHeader() {
		return this.#sticky_header;
	}

	setStickyHeader(sticky_header) {
		this.#sticky_header = sticky_header;

		return this;
	}

	isStickyFooter() {
		return this.#sticky_footer;
	}

	setStickyFooter(sticky_footer) {
		this.#sticky_footer = sticky_footer;

		return this;
	}

	onSave() {
		if (!this.#storage_idx) {
			return;
		}

		if (this.#save_config_request != null) {
			this.#save_config_request.abort();

			this.#save_config_request = null;
		}

		const config = this.getConfig();

		this.updateUserConfig(config);

		const idx2 = this.#tabfilter_item ? [this.#tabfilter_item._index] : [];

		this.#save_config_request = this.#updateUserProfile(JSON.stringify(config), idx2);
	}

	onRender() {
		this.#element.classList.remove(ZBX_STYLE_LOADING);

		this.getData().then(response => {
			this.#body.innerHTML = '';

			this.#recalculateColumnSpans();
			this.#renderHeaderCells();

			if ('error' in response) {
				this.#applyColumnWidths();

				return;
			}

			if (!('data' in response) || response.data.length == 0) {
				const no_data_message = this.#createNoDataMessage({
					icon: response.no_data_icon || ZBX_ICON_SEARCH_LARGE,
					message: response.no_data_message || t('No data found'),
					description: response.no_data_description
				});

				this.#body.appendChild(no_data_message);
			}
			else {
				this.#footer.classList.remove(ZBX_STYLE_HIDDEN);
			}

			const columns = this.#columns.filter(column_config => column_config.isVisible());

			if (columns.length > 0) {
				const data_fields = response.data_fields;

				for (let row_index = 0; row_index < response.data.length; row_index++) {
					const [row_config, row_data] = response.data[row_index];

					const row = this.#createRow(row_index);

					const renderer = this.#row_renderers[row_config.renderer] || this.#row_renderers.default;
					renderer.call(this, {columns, row, row_index, row_config, data_fields, row_data});

					this.#body.appendChild(row);
				}

				for (const [_, option] of Object.entries(this.#options)) {
					option.onRender(option);
				}
			}

			this.#header.scrollTo({left: 0});
			this.#body.scrollTo({left: 0});

			this.#columns
				.filter(column_config => column_config.isSticky())
				.forEach(column_config => {
					const header_cell = this.#findHeaderCell(column_config.getColumnIndex());
					header_cell.classList.add(CDataTable.ZBX_STYLE_CELL_STICKY);
					header_cell.style.left = '0';
				});

			this.#applyColumnWidths();

			columns.forEach(column_config => this.#calculateColumnWidth(column_config));

			this.#applyColumnWidths();
			this.#applyLastColumnPadding();
			this.#updateTableOptionsButtonPosition();

			this.#pager.update(response);

			setTimeout(() => {
				this.#options_popup?.position();

				const selector = `.${CDataTable.ZBX_STYLE_BODY}`;
				const row_selector = `.${CDataTable.ZBX_STYLE_ROW}`;
				const thead_selector = `.${CDataTable.ZBX_STYLE_HEADER} .${CDataTable.ZBX_STYLE_CELL_CHECKBOX}`;

				chkbxRange.init({selector, row_selector, thead_selector});
			});
		});
	}

	#applyLastColumnPadding() {
		if (!this.#customizable) {
			return;
		}

		const column_config = this.#columns.filter(column_config => column_config.isVisible()).at(-1);
		const header_cell = this.#findHeaderCell(column_config.getColumnIndex());
		const header_resizer = header_cell.querySelector(`.${CDataTable.ZBX_STYLE_CELL_HEADER_RESIZER}`);

		const element_rect = this.#element.getBoundingClientRect();
		const table_options_button_rect = this.#findTableOptionsButton().getBoundingClientRect();

		const right_edge = header_cell.getBoundingClientRect().right - element_rect.left;
		const right_boundary = element_rect.width - table_options_button_rect.width;
		const right_offset = right_edge > right_boundary || this.#element.scrollWidth > element_rect.width
			? Math.min(table_options_button_rect.width, right_edge - right_boundary)
			: 0;

		header_cell.style.paddingRight = `${right_offset}px`;
		header_resizer.style.right = `${right_offset}px`;
	}

	onColumnToggle(event) {
		const {column_index, visible} = event.detail;

		const column_config = this.getColumnConfig(column_index);
		if (!column_config || !column_config.isTogglable()) {
			return;
		}

		if (column_config.getWidth() == 'auto') {
			column_config.setWidth(`${CDataTable.COLUMN_TOGGLE_INITIAL_MIN_WIDTH}px`);
		}

		column_config.setVisible(visible);

		this.updateUserConfig();

		this.#options_popup_updated = true;

		this.dispatchEvent(CDataTable.EVENT_INIT, {
			onSuccess: () => requestAnimationFrame(() => {
				const header_cell = this.#findHeaderCell(column_index);

				this.#scrollBodyToTarget(header_cell);
			})
		});

		this.dispatchEvent(CDataTable.EVENT_SAVE);
	}

	onColumnsSort(event) {
		const {items, index, index_to} = event.detail;

		const [offset, start, end] = index > index_to
			? [1, index_to + 1, index]
			: [-1, index, index_to - 1];

		const item = items.item(index_to);
		const column_index = parseInt(item.getAttribute('data-col'));
		const lowest_order = this.getColumnConfig(parseInt(items[start].getAttribute('data-col'))).getOrder();
		const highest_order = this.getColumnConfig(parseInt(items[end].getAttribute('data-col'))).getOrder();

		const columns = this.#columns.filter(
			column_config => column_config.getId() != this.#checkbox_id
				&& column_config.getOrder() >= lowest_order
				&& column_config.getOrder() <= highest_order
		);

		const column_config = this.getColumnConfig(column_index);
		column_config.setOrder(columns.at(offset < 0 ? columns.length - 1 : 0).getOrder());

		for (const column_config of columns) {
			column_config.setOrder(column_config.getOrder() + offset);
		}

		this.#sortColumns();

		this.updateUserConfig();

		this.dispatchEvent(CDataTable.EVENT_RENDER);
		this.dispatchEvent(CDataTable.EVENT_SAVE);
	}

	onColumnDuplicate(event) {
		let {column_index, user_column_config} = {user_column_config: {}, ...event.detail};

		const column_config = this.getColumnConfig(column_index);
		if (!column_config) {
			return null;
		}

		const duplicate_column_config = this.#duplicateColumnConfig(column_config, user_column_config);
		duplicate_column_config.setColumnIndex(this.#columns.length);

		const start = this.#columns.indexOf(column_config) + 1;

		this.#columns.splice(start, 0, duplicate_column_config);

		for (let i = start; i < this.#columns.length; i++) {
			this.#columns[i].setOrder(i + 1);
		}

		this.#options_popup?.dispatchEvent(CDataTableOptionsPopup.EVENT_CLOSE);

		this.dispatchEvent(CDataTable.EVENT_RENDER);
		this.dispatchEvent(CDataTable.EVENT_SAVE);

		requestAnimationFrame(() => {
			const column_index = duplicate_column_config.getColumnIndex();
			const header_cell = this.#findHeaderCell(column_index);

			this.#scrollBodyToTarget(header_cell);

			header_cell.focus();
		});
	}

	onColumnDelete(event) {
		const {column_index} = event.detail;

		const column_config = this.getColumnConfig(column_index);
		if (!column_config) {
			return;
		}

		const message = sprintf(t('Are you sure you want to delete %1$s? This action cannot be undone.'),
			column_config.getName());

		if (!confirm(message)) {
			return;
		}

		const order = column_config.getOrder();

		this.#columns
			.filter(column_config => column_config.getOrder() > order)
			.forEach(column_config => column_config.setOrder(order - 1));

		const index = this.#columns.indexOf(column_config);
		this.#columns.splice(index, 1);

		if (this.#options_popup.dispatchEvent(CDataTableOptionsPopup.EVENT_CLOSE)
				&& this.dispatchEvent(CDataTable.EVENT_RENDER)) {

			this.dispatchEvent(CDataTable.EVENT_SAVE);
		}
	}

	onColumnRename(event) {
		const {column_index, name} = event.detail;

		const column_config = this.getColumnConfig(column_index);
		if (!column_config || (!column_config.isDuplicate() && !column_config.isRenamable())) {
			return;
		}

		column_config.setName(name);

		const cell_inner = this.#findHeaderCell(column_index).querySelector(`.${CDataTable.ZBX_STYLE_CELL_INNER}`);
		cell_inner.innerText = name;
	}

	onColumnReset(event) {
		const {column_index} = event.detail;

		const column_config = this.getColumnConfig(column_index);
		if (!column_config) {
			return;
		}

		const defaults = column_config.getDefaults();

		column_config.setResized(false);

		if (defaults.getWidth() == 'auto') {
			column_config.setWidth(`${CDataTable.COLUMN_TOGGLE_INITIAL_MIN_WIDTH}px`);
		}
		else {
			column_config.setWidth(defaults.getWidth());

			this.#applyColumnWidths();
			this.#calculateColumnWidth(column_config);
		}

		this.#applyColumnWidths();
		this.dispatchEvent(CDataTable.EVENT_SAVE);

		this.#resize_click_count = 0;
	}

	onReset() {
		this.#resetColumns();

		this.#options_popup?.dispatchEvent(CDataTableOptionsPopup.EVENT_CLOSE);

		const force_load = this.#resetOptions();

		this.dispatchEvent(CDataTable.EVENT_INIT, {force_load, reset: true});
		this.dispatchEvent(CDataTable.EVENT_SAVE);
	}

	getData(params = {}) {
		params = this.#getDataProviderParams({check_changes: false, force_load: false, ...params});

		return this.#data_provider.getData(params);
	}

	onInit(event) {
		const {loading, check_changes, force_load, reset} = {
			loading: true,
			check_changes: true,
			force_load: false,
			reset: false,
			...event.detail
		};

		if (loading) {
			this.#element.classList.add(ZBX_STYLE_LOADING);
		}

		const {onSuccess, onError, onFinally} = {
			onSuccess: () => {},
			onError: () => {},
			onFinally: () => {},
			...event.detail
		};

		if (!reset) {
			this.#setUserConfig(this.#tabfilter_item._index);
		}

		if (!this.#initialized) {
			this.#recalculateColumnSpans();
			this.#applyColumnWidths();
			this.#renderHeaderCells();
		}

		this.getData({check_changes, force_load})
			.then(response => {
				if ('error' in response && response.error) {
					CMessageHelper.error(this.#element, [response.error], t('Unexpected server error.'));
				}

				return response;
			})
			.then(response => {
				this.dispatchEvent(CDataTable.EVENT_RENDER);

				onSuccess(response);
			})
			.catch(error => {
				if (error.name != 'AbortError') {
					CMessageHelper.error(this.#element, [error.message], error.name);
				}

				onError(error);
			})
			.finally(() => onFinally());

		this.#initialized = true;
	}

	onResizeMouseMove = event => {
		this.dispatchEvent(CDataTable.EVENT_COLUMN_RESIZE, {event});
	}

	onResizeMouseUp = event => {
		this.dispatchEvent(CDataTable.EVENT_COLUMN_RESIZE_END, {event});
	}

	onColumnResize(event) {
		if (!this.#resizing) {
			return;
		}

		const column_config = this.getColumnConfig(this.#resize_column_index);
		if (!column_config) {
			return;
		}

		const {clientX} = event.detail.event;
		const delta_x = clientX - this.#resize_start_x;
		const total_width = this.#element.getBoundingClientRect().width;
		const delta_percent = (delta_x / total_width) * 100;

		let min_width = CDataTable.RESIZE_MIN_WIDTH;
		if (column_config.getOptionsPopupHandler()) {
			min_width *= 2;
		}
		if (column_config.isSortable()) {
			min_width += 10;
		}

		const min_width_percent = (min_width / total_width) * 100;

		let width = parseFloat(Math.max(min_width_percent, this.#resize_start_width + delta_percent).toFixed(4));

		column_config.setWidth(`${width}%`);

		const visible_columns = this.#columns.filter(column_config => column_config.isVisible());
		visible_columns.forEach(column_config => this.#calculateColumnWidth(column_config));

		this.#applyColumnWidths();
		this.#applyLastColumnPadding();
	}

	onColumnResizeStart(event) {
		const {x, column_index, id} = event.detail;

		this.#resize_click_count++;

		if (this.#resize_click_count == 2) {
			return this.dispatchEvent(CDataTable.EVENT_COLUMN_RESET, {column_index, id});
		}

		const column_config = this.getColumnConfig(column_index);
		column_config.setResized(false);

		this.#calculateColumnWidth(column_config);

		this.#resizing = true;
		this.#resize_column_index = column_index;
		this.#resize_start_x = x;
		this.#resize_start_width = parseFloat(this.#getWidthWithoutUnit(column_config.getWidth()));

		this.#element.classList.add(CDataTable.ZBX_STYLE_RESIZING);

		document.querySelector('.sidebar').style.pointerEvents = 'none';

		this.#findHeaderCell(column_index).classList.add(CDataTable.ZBX_STYLE_CELL_RESIZING);

		window.addEventListener('mousemove', this.onResizeMouseMove);
		window.addEventListener('mouseup', this.onResizeMouseUp);
	}

	onColumnResizeEnd() {
		if (!this.#resizing) {
			return;
		}

		const column_config = this.getColumnConfig(this.#resize_column_index);
		if (!column_config) {
			return;
		}

		column_config.setResized(true);

		const column_index = column_config.getColumnIndex();

		this.#findHeaderCell(column_index).classList.remove(CDataTable.ZBX_STYLE_CELL_RESIZING);

		this.#resizing = false;
		this.#resize_column_index = -1;
		this.#resize_start_x = 0;
		this.#resize_start_width = 0;

		this.#element.classList.remove(CDataTable.ZBX_STYLE_RESIZING);

		document.querySelector('.sidebar').style.pointerEvents = null;

		this.dispatchEvent(CDataTable.EVENT_SAVE);

		window.removeEventListener('mousemove', this.onResizeMouseMove);
		window.removeEventListener('mouseup', this.onResizeMouseUp);
	}

	onDataSort(event) {
		const {sort_field, sort_order} = event.detail;

		const state = new CState();
		state.setParams({ sort: sort_field, sortorder: sort_order });
		state.push();

		this.#sort_field = sort_field;
		this.#sort_order = sort_order;

		this.#element.classList.add(ZBX_STYLE_LOADING);

		this.dispatchEvent(CDataTable.EVENT_INIT);
	}

	onColumnOptionsPopup(event) {
		const {handle} = event.detail;

		if (this.#options_popup?.isOpen(handle)) {
			this.dispatchEvent(CDataTable.EVENT_COLUMN_OPTIONS_POPUP_CLOSE, {handle});

			return;
		}

		const header_cell = this.#findClosestHeaderCell(handle);
		const column_index = parseInt(header_cell.getAttribute('data-col'));
		const column_config = this.getColumnConfig(column_index);
		const column_options_handler = column_config.getOptionsPopupHandler();

		const handler = this.#options_handlers[column_options_handler];
		if (!handler) {
			return;
		}

		this.#options_popup = this.#createColumnOptionsPopup(handler, column_config, header_cell, handle);
		this.#options_popup.dispatchEvent(CDataTableOptionsPopup.EVENT_OPEN);
	}

	onColumnOptionsPopupOpen(event) {
		if (!this.#options_popup) {
			return;
		}

		this.dispatchEvent(CDataTable.EVENT_OPTIONS_POPUP_OPEN, event.detail);
	}

	onColumnOptionsPopupClose() {
		if (!this.#options_popup) {
			return;
		}

		const handle = this.#options_popup.getHandle();

		const header_cell = this.#findClosestHeaderCell(handle);
		header_cell?.classList.remove(CDataTable.ZBX_STYLE_CELL_FOCUSED);

		this.dispatchEvent(CDataTable.EVENT_OPTIONS_POPUP_CLOSE);
	}

	onColumnOptionsPopupUpdate(event) {
		if (!this.#options_popup) {
			return;
		}

		const {column_index, column_options} = event.detail;
		const column_config = this.getColumnConfig(column_index);

		if (!deepCompare(column_config.getColumnOptions(), column_options)) {
			column_config.setColumnOptions(column_options);

			this.#renderColumnDataCells(column_config);

			this.#options_popup_updated = true;

			requestAnimationFrame(() => {
				const header_cell = this.#findHeaderCell(column_index);

				this.#scrollBodyToTarget(header_cell);
			});
		}

		this.dispatchEvent(CDataTable.EVENT_OPTIONS_POPUP_UPDATE, event.detail);
	}

	onOptionsPopup(event) {
		const {handler, column_config, header_cell, handle} = event.detail;

		if (column_config.getId() != CDataTableColumn.TABLE_OPTIONS) {
			this.#scrollBodyToTarget(header_cell);
		}

		if (this.#options_popup?.isOpen(handle)) {
			this.#options_popup.dispatchEvent(CDataTableOptionsPopup.EVENT_CLOSE);

			return;
		}

		this.#options_popup_updated = true;

		this.#options_popup = this.#createOptionsPopup(handler, column_config, header_cell, handle);
		this.#options_popup.dispatchEvent(CDataTableOptionsPopup.EVENT_OPEN);
	}

	onOptionsPopupOpen() {
		if (!this.#options_popup) {
			return;
		}

		this.#options_popup.getHandle().classList.add(CDataTable.ZBX_STYLE_OPTIONS_LINK_OPENED);

		this.#element.appendChild(this.#options_popup.getElement());

		this.#options_popup.position();
		this.#options_popup.getElement().focus();
	}

	onOptionsPopupClose() {
		if (!this.#options_popup) {
			return;
		}

		const handle = this.#options_popup.getHandle();
		handle.classList.remove(CDataTable.ZBX_STYLE_OPTIONS_LINK_OPENED);

		this.#options_popup = null;
		this.#options_popup_updated = false;
	}

	onOptionsPopupUpdate(event) {
		if (!this.#options_popup) {
			return;
		}

		const {save = false} = event.detail;

		if (save) {
			this.dispatchEvent(CDataTable.EVENT_SAVE);
		}
	}

	onScroll() {
		this.#updateTableOptionsButtonPosition();

		if (this.#options_popup_updated) {
			this.#options_popup_updated = false;

			return;
		}

		this.#options_popup?.dispatchEvent(CDataTableOptionsPopup.EVENT_CLOSE);

		this.#header.scrollLeft = this.#body.scrollLeft;
	}

	on(event, callback, options = undefined) {
		this.#element.addEventListener(event, callback.bind(this), options);

		return this;
	}

	dispatchEvent(type, detail = {}, options = {}) {
		return this.#element.dispatchEvent(new CustomEvent(type, {...options, detail}));
	}

	export(target, file) {
		const href = target.getAttribute('href');
		const onclick = target.getAttribute('onclick');

		target.removeAttribute('onclick');

		this.getData({export_file: 'csv', force_load: true}).then(response => {
			if ('error' in response && response.error) {
				CMessageHelper.error(this.#element, [response.error], t('Unexpected server error.'));

				return;
			}

			const blob = new Blob([response.export], { type: 'text/csv;charset=utf-8;' });
			const url = URL.createObjectURL(blob);

			target.setAttribute('href', url.toString());
			target.setAttribute('download', file);
			target.click();

			window.URL.revokeObjectURL(url);
			target.removeAttribute('download');

			target.setAttribute('href', href);
			target.setAttribute('onclick', onclick);
		});
	}

	getConfig() {
		const columns = this.#columns.filter(column_config => column_config.getId() != this.#checkbox_id);

		const options = Object.entries(this.#options).reduce((options, [id, option]) => {
			options[id] = option.checked ? '1' : '0';

			return options;
		}, {});

		return {
			columns: columns.map(column_config => column_config.diff()),
			options,
		};
	}

	/**
	 * @param {Object|undefined} user_config
	 */
	updateUserConfig(user_config = undefined) {
		this.#user_configs[this.#tabfilter_item._index] = user_config || this.getConfig();
	}

	/**
	 * Retrieves the configuration object for a specific column.
	 *
	 * @param {number} column_index
	 * @returns {CDataTableColumn|undefined}
	 */
	getColumnConfig(column_index) {
		return this.#columns.find(column_config => column_config.getColumnIndex() == column_index);
	}

	createDataCell(column_config, row_index) {
		const data_cell = this.#createCell(column_config, row_index);
		data_cell.classList.add(CDataTable.ZBX_STYLE_CELL_DATA);

		if (column_config.getSpan() > 1) {
			data_cell.style.gridColumn = `span ${column_config.getSpan()}`;
		}

		this.#makeCellSticky(column_config, data_cell);

		if (column_config.isOnlyHeader() || !column_config.isVisible()) {
			data_cell.classList.add(ZBX_STYLE_HIDDEN);
		}

		return data_cell;
	}

	findDataCells(column_index = null, row_index = null) {
		return this.#findCells(column_index, row_index, `.${CDataTable.ZBX_STYLE_CELL_DATA}`);
	}

	renderDataCells({columns, row, row_index, data_fields, row_data}) {
		const data_cells = {};

		for (const column_config of columns) {
			const column_index = column_config.getColumnIndex();

			data_cells[column_index] = this.createDataCell(column_config, row_index);

			row.appendChild(data_cells[column_index]);
		}

		if (this.#customizable) {
			this.#createRowSpacer(row);
		}

		// This is separately from the loop above on purpose, since row renderer may have an impact on all cells
		// within the row, which means that the cells should be present BEFORE renderer is executed.
		for (const column_config of columns) {
			if (column_config.isOnlyHeader()) {
				continue;
			}

			const column_index = column_config.getColumnIndex();

			this.renderDataCellContents(column_config, row, data_cells[column_index], data_fields, row_data);
		}
	}

	renderDataCellContents(column_config, row, data_cell, data_fields, row_data) {
		const renderer = column_config.getRenderer();

		if (!this.#renderers[renderer]) {
			return;
		}

		data_cell.innerHTML = '';

		const column_index = column_config.getColumnIndex();

		const cell_inner = document.createElement('div');
		cell_inner.classList.add(CDataTable.ZBX_STYLE_CELL_INNER);

		data_cell.appendChild(cell_inner);

		const column_fields = column_config.isDuplicate()
			? this.getColumnConfigById(column_config.getId()).getFields()
			: column_config.getFields();
		const column_data = [];

		for (const field of column_fields) {
			const field_index = data_fields.indexOf(field);

			column_data.push(field_index >= 0 ? row_data[field_index] : null);
		}

		try {
			this.#renderers[renderer].call(this, {
				column_config,
				column_index,
				datatable: this,
				row,
				row_index: data_cell.getAttribute('data-row'),
				column_data: column_data || [],
				cell: data_cell,
				cell_inner
			});
		} catch (error) {
			console.error(error);

			data_cell.classList.add(CDataTable.ZBX_STYLE_CELL_ERROR);

			cell_inner.innerHTML = error.message;
		}
	}

	#initColumns() {
		if (this.#checkbox_id) {
			this.#columns.unshift(
				new CDataTableColumn(this.#checkbox_id, '')
					.setFields(this.#selectable)
					.setRenderer(CDataTableColumn.CHECKBOX)
					.setResizable(false)
					.setShowInCustomizeTable(false)
					.setSticky(true)
					.setWidth('37px')
			);
		}

		const orders = new Set();

		this.#columns.forEach((column_config, column_index) => {
			const order = column_config.getOrder();

			if (!order || orders.has(order)) {
				let next_order = order + 1;

				while (orders.has(next_order)) {
					next_order++;
				}

				column_config.setOrder(next_order);
				orders.add(next_order);
			}
			else {
				orders.add(order);
			}

			column_config.setColumnIndex(column_index)
				.setDefaults(column_config.clone());
		});

		this.#sortColumns();
	}

	#resetColumns() {
		this.#columns = this.#columns.filter(column_config => {
			if (!column_config.isDuplicate()) {
				column_config.merge(column_config.getDefaults().toObject());
			}

			return !column_config.isDuplicate();
		});

		this.#sortColumns();
	}

	#resetOptions() {
		const options = Object.entries(this.#options);
		const force_load = options.some(([_, option]) => option.isChanged());

		options.forEach(([_, option]) => option.onReset());

		return force_load;
	}

	#setUserConfig(tabfilter_idx) {
		if (!this.#customizable && !this.#resizable) {
			return;
		}

		this.#resetColumns();

		const user_config = this.#user_configs[tabfilter_idx];
		if (!user_config) {
			return this;
		}

		if (user_config.columns) {
			const user_columns = user_config.columns.filter(user_column_config => 'id' in user_column_config);

			// Merge original columns
			user_columns.filter(user_column_config => !user_column_config?.duplicate)
				.forEach(user_column_config => {
					const column_config = this.getColumnConfigById(user_column_config.id);
					if (!column_config) {
						return;
					}

					column_config.merge(user_column_config);
				});

			// Handle duplicated columns
			user_columns.forEach(user_column_config => {
				const column_config = this.getColumnConfigById(user_column_config.id);
				if (!column_config) {
					return;
				}

				if (!user_column_config.duplicate) {
					column_config.merge(user_column_config);

					return;
				}

				const duplicate_column_config = this.#duplicateColumnConfig(column_config, user_column_config);
				duplicate_column_config.setColumnIndex(this.#columns.length);

				this.#columns.splice(this.#columns.indexOf(column_config) + 1, 0, duplicate_column_config);
			});

			this.#columns.sort((a, b) => {
				if (a.getOrder() && b.getOrder()) {
					return a.getOrder() - b.getOrder();
				}

				const order_a = a.getOrder() ?? (user_columns.indexOf(a) + 0.5);
				const order_b = b.getOrder() ?? (user_columns.indexOf(b) + 0.5);

				return order_a - order_b;
			});

			this.#columns.forEach((column_config, column_index) => column_config.setOrder(column_index + 1));
		}

		if (user_config.options) {
			for (const [id, value] of Object.entries(user_config.options)) {
				if (!this.#options[id]) {
					continue;
				}

				this.#options[id].checked = value == 1;
			}
		}
	}

	/**
	 * Recalculates column spans based on visibility and span settings.
	 */
	#recalculateColumnSpans() {
		this.#columns.forEach(column_config => {
			const defaults = column_config.getDefaults();

			column_config.merge({span: defaults.getSpan(), only_header: defaults.isOnlyHeader()});
		});

		let remaining_span = 0;

		this.#columns
			.filter(column_config => column_config.isVisible())
			.forEach(column_config => {
				if (remaining_span > 0) {
					column_config.setOnlyHeader(true);

					remaining_span--;
				}
				else {
					column_config.setOnlyHeader(false);

					if (column_config.getSpan() > 1) {
						remaining_span = column_config.getSpan() - 1;
					}
				}
			});
	}

	#createRow(row_index) {
		const row = document.createElement('div');
		row.classList.add(CDataTable.ZBX_STYLE_ROW);
		row.setAttribute('data-row', row_index.toString());

		return row;
	}

	#createCell(column_config, row_index) {
		const cell = document.createElement('div');
		cell.classList.add(CDataTable.ZBX_STYLE_CELL);
		cell.setAttribute('data-row', row_index.toString());
		cell.setAttribute('data-col', column_config.getColumnIndex().toString());

		return cell;
	}

	#createHeaderCell(data) {
		const {column_config, row_index} = data;

		let column_options_handler = column_config.getOptionsPopupHandler();

		if (column_config.isDuplicate()) {
			const duplicate_column_config = this.#columns.find(duplicate_column_config => {
				return duplicate_column_config.getId() == column_config.getId() && duplicate_column_config.isDuplicate();
			});

			if (duplicate_column_config) {
				column_options_handler = duplicate_column_config.getOptionsPopupHandler();
			}
		}

		const header_cell = this.#createCell(column_config, row_index);
		header_cell.classList.add(CDataTable.ZBX_STYLE_CELL_HEADER);
		header_cell.setAttribute('tabindex', '-1');

		if (column_options_handler) {
			header_cell.classList.add(CDataTable.ZBX_STYLE_CELL_CONTEXT);
		}

		if (column_config.isVisible()) {
			this.#renderHeaderCellContents(column_config, row_index, header_cell);
		}
		else {
			header_cell.classList.add(ZBX_STYLE_HIDDEN);
		}

		this.#makeCellSticky(column_config, header_cell);

		return header_cell;
	}

	#makeCellSticky(column_config, cell) {
		if (!column_config.isSticky()) {
			return;
		}

		const visible_columns = this.#columns.filter(column_config => column_config.isVisible());
		if (!visible_columns.length) {
			return;
		}

		const is_first = visible_columns.at(0).getId() == column_config.getId();
		const is_last = visible_columns[visible_columns.length - 1]?.getId() == column_config.getId();

		if (is_first || is_last) {
			cell.classList.add(CDataTable.ZBX_STYLE_CELL_STICKY);
			cell.style[is_first ? 'left' : 'right'] = '0px';
		}
	}

	#renderHeaderCellContents(column_config, row_index, header_cell) {
		header_cell.innerHTML = '';

		if (this.#resizable && column_config.isResizable()) {
			const resize_handle = document.createElement('div');
			resize_handle.classList.add(CDataTable.ZBX_STYLE_CELL_HEADER_RESIZER);

			this.#bindColumnResizeEvent(column_config, resize_handle);

			header_cell.appendChild(resize_handle);
		}

		let header_renderer = 'default';
		if (column_config.getId() == this.#checkbox_id) {
			header_renderer = column_config.getRenderer();
		}

		this.#header_renderers[header_renderer].call(this, {
			column_config,
			datatable: this,
			row_index: header_cell.getAttribute('data-row'),
			cell: header_cell
		});
	}

	#sortColumns() {
		this.#columns = this.#columns.sort((left, right) => left.getOrder() - right.getOrder());
	}

	#duplicateColumnConfig(column_config, user_column_config = {}) {
		const id = column_config.getId();
		const name = this.#columns.find(column_config => column_config.getId() === id).getName();

		const duplicate_count = this.#columns
			.filter(column_config => column_config.getName().replace(/\s*\(\d+\)$/g, '') === name)
			.length;

		const defaults = column_config.clone()
			.setDuplicate(false)
			.setName(`${name} (${duplicate_count})`)
			.setSpan(1);

		return defaults.clone()
			.setDuplicate(true)
			.setDefaults(defaults)
			.merge(user_column_config);
	}

	#scrollBodyToTarget(target) {
		const {right} = target.getBoundingClientRect();
		const width = this.#body.getBoundingClientRect().width;

		if (right > width) {
			const left = this.#body.scrollLeft + right - width;

			this.#header.scrollTo({left});
			this.#body.scrollTo({left});
		}
	}

	#calculateColumnWidth(column_config) {
		if (column_config.isResized() || !column_config.isResizable()) {
			return;
		}

		let header_width = 0;

		const header_cell = this.#findHeaderCell(column_config.getColumnIndex());
		if (header_cell) {
			header_width = header_cell.getBoundingClientRect().width;
		}

		let data_width = 0;

		const data_cell = this.#findDataCell(column_config.getColumnIndex());
		if (data_cell) {
			data_width = data_cell.getBoundingClientRect().width;

			if (column_config.getWidth() == 'auto') {
				data_width = Math.min(data_width, CDataTable.COLUMN_TOGGLE_INITIAL_MIN_WIDTH);
			}
		}

		let offset = 0;
		// Width 'max-content' may cause text overflow, hence we need to add 1px
		if (column_config.getWidth() == 'max-content') {
			offset++;
		}

		const width = parseFloat(this.#convertPixelsToPercent(Math.max(header_width, data_width) + offset));

		column_config.setResized(true).setWidth(`${width}%`);
	}

	#applyColumnWidths(column_widths = []) {
		if (column_widths.length == 0) {
			const visible_columns = this.getColumns().filter(column_config => column_config.isVisible());

			column_widths = visible_columns.map(column_config => column_config.getWidth());
		}

		if (this.#customizable) {
			column_widths.push('auto');
		}

		this.#header.style.gridTemplateColumns = this.#body.style.gridTemplateColumns = column_widths.join(' ');
	}

	#findRowSpacer(target) {
		return target.querySelector(`.${CDataTable.ZBX_STYLE_ROW_SPACER}`);
	}

	#findTableOptionsButton() {
		return this.#header.querySelector(`.${CDataTable.ZBX_STYLE_OPTIONS_BUTTON}`);
	}

	#renderHeaderCells() {
		this.#findHeaderCells().forEach(header_cell => header_cell.remove());

		if (this.#customizable) {
			this.#createRowSpacer(this.#header);
			this.#createTableOptionsButton();
		}

		const columns = this.#columns.filter(column_config => column_config.isVisible());

		for (const column_config of columns) {
			const header_cell = this.#createHeaderCell({column_config, row_index: 0});

			if (this.#customizable) {
				const row_spacer = this.#findRowSpacer(this.#header);

				this.#header.insertBefore(header_cell, row_spacer);
			}
			else {
				this.#header.appendChild(header_cell);
			}
		}

		this.#updateTableOptionsButtonPosition();
	}

	#createNoDataMessage({icon = undefined, message = undefined, description = undefined} = {}) {
		const no_data_message = document.createElement('div');
		no_data_message.classList.add(ZBX_STYLE_NO_DATA_MESSAGE);

		if (icon) {
			no_data_message.classList.add(icon);
		}

		no_data_message.style.gridColumn = '1 / -1';

		if (message) {
			no_data_message.innerText = message;
		}

		if (description) {
			const no_data_description = document.createElement('div');
			no_data_description.classList.add(ZBX_STYLE_NO_DATA_DESCRIPTION);
			no_data_description.innerText = description;

			no_data_message.appendChild(no_data_description);
		}

		return no_data_message;
	}

	#createRowSpacer(target) {
		if (this.#findRowSpacer(target)) {
			return;
		}

		target.appendChild(this.#templates.row_spacer.evaluateToElement());
	}

	#createTableOptionsButton() {
		if (this.#findTableOptionsButton()) {
			return;
		}

		const header_cell = this.#templates.table_options_button.evaluateToElement();

		const handle = header_cell.querySelector(`.${CDataTable.ZBX_STYLE_OPTIONS_LINK}`);
		handle.addEventListener('click', event => {
			event.preventDefault();

			const handler = this.#options_handlers[CDataTableColumn.TABLE_OPTIONS];
			const column_config = new CDataTableColumn(CDataTableColumn.TABLE_OPTIONS, '')
				.setOptionsPopupHandler(CDataTableColumn.TABLE_OPTIONS);

			this.dispatchEvent(CDataTable.EVENT_OPTIONS_POPUP, {handler, column_config, header_cell, handle});
		});

		this.#header.appendChild(header_cell);
	}

	#updateTableOptionsButtonPosition() {
		if (!this.#customizable) {
			return;
		}

		const table_options = this.#findTableOptionsButton();
		table_options.style.right = `${-this.#body.scrollLeft}px`;
		table_options.classList.remove(ZBX_STYLE_HIDDEN);
	}

	#createOptionsPopup(handler, column_config, header_cell, handle) {
		return new (eval(handler))(this, column_config, header_cell, handle)
			.on(
				CDataTableOptionsPopup.EVENT_OPEN,
				event => this.dispatchEvent(CDataTable.EVENT_OPTIONS_POPUP_OPEN, event.detail)
			)
			.on(
				CDataTableOptionsPopup.EVENT_CLOSE,
				event => this.dispatchEvent(CDataTable.EVENT_OPTIONS_POPUP_CLOSE, event.detail)
			).on(
				CDataTableOptionsPopup.EVENT_UPDATE,
				event => this.dispatchEvent(CDataTable.EVENT_OPTIONS_POPUP_UPDATE, event.detail)
			);
	}

	#createColumnOptionsPopup(handler, column_config, header_cell, handle) {
		return new (eval(handler))(this, column_config, header_cell, handle)
			.on(
				CDataTableOptionsPopup.EVENT_OPEN,
				event => this.dispatchEvent(CDataTable.EVENT_COLUMN_OPTIONS_POPUP_OPEN, event.detail)
			)
			.on(
				CDataTableOptionsPopup.EVENT_CLOSE,
				event => this.dispatchEvent(CDataTable.EVENT_COLUMN_OPTIONS_POPUP_CLOSE, event.detail)
			).on(
				CDataTableOptionsPopup.EVENT_UPDATE,
				event => this.dispatchEvent(CDataTable.EVENT_COLUMN_OPTIONS_POPUP_UPDATE, event.detail)
			);
	}

	#renderColumnDataCells(column_config) {
		const column_index = column_config.getColumnIndex();

		this.getData().then(response => {
			for (let row_index = 0; row_index < response.data.length; row_index++) {
				const [row_config, row_data] = response.data[row_index];

				if (row_config.renderer) {
					continue;
				}

				const row = this.#body.querySelector(`.${CDataTable.ZBX_STYLE_ROW}[data-row="${row_index}"]`);
				const data_cell = this.createDataCell(column_config, row_index);

				const cell = this.#body
					.querySelector(`.${CDataTable.ZBX_STYLE_CELL}[data-row="${row_index}"][data-col="${column_index}"]`)
				if (cell) {
					const attributes = cell.attributes;
					cell.replaceWith(data_cell);

					Array.from(attributes).forEach(attr => data_cell.setAttribute(attr.nodeName, attr.nodeValue));
				}
				else {
					row?.appendChild(data_cell);
				}

				if (column_config.isOnlyHeader()) {
					continue;
				}

				const data_fields = response.data_fields;

				this.renderDataCellContents(column_config, row, data_cell, data_fields, row_data);
			}
		});
	}

	#getDataProviderParams(params) {
		const columns = this.#columns.filter(column_config => !column_config.isDuplicate());

		let column_options = {};
		for (const column_config of columns) {
			column_options = Object.assign(column_options, column_config.getColumnOptions());
		}

		const options = Object.fromEntries(
			Object.entries(this.#options).map(([id, option]) => [id, option.checked ? 1 : 0])
		);

		return {
			columns: columns.sort((a, b) => a.getColumnIndex() - b.getColumnIndex()),
			filter: this.#filter,
			options,
			column_options,
			page: this.#pager.getPage(),
			sort_field: this.#sort_field,
			sort_order: this.#sort_order,
			check_changes: true,
			force_load: false,
			...params
		};
	}

	#bindEvents() {
		Object.entries(this.#events).forEach(([name, callback]) => this.on(name, callback));

		document.querySelector('.wrapper').addEventListener('scroll', () => this.#options_popup?.position());

		if (this.#tabfilter_item._parent) {
			this.#tabfilter_item._parent.on(TABFILTER_EVENT_NEWITEM, event => {
				const {item} = event.detail;

				this.#user_configs[item._index] = this.getConfig();

				this.#updateUserProfile(JSON.stringify({}), [item._index]);
			});

			this.#tabfilter_item.on(TABFILTERITEM_EVENT_DELETE, event => {
				const {idx2} = event.detail;

				this.#updateUserProfile(JSON.stringify({}), [idx2]);
			});
		}
	}

	#updateUserProfile(value, idx2) {
		if (!this.#storage_idx) {
			return;
		}

		/* global updateUserProfile */
		return updateUserProfile(this.#storage_idx, value, idx2, PROFILE_TYPE_STR);
	}

	#bindColumnResizeEvent(column_config, resizer) {
		resizer.addEventListener('mousedown', event => {
			this.dispatchEvent(CDataTable.EVENT_COLUMN_RESIZE_START, {
				x: event.clientX,
				column_index: column_config.getColumnIndex(),
				id: column_config.getId()
			});

			clearTimeout(this.#resize_click_timeout);
			this.#resize_click_timeout = setTimeout(() => {
				this.#resize_click_count = 0;

				clearTimeout(this.#resize_click_timeout);
			}, CDataTable.RESIZE_CLICK_COUNT_RESET_DELAY);
		});
	}

	#findHeaderCell(column_index) {
		return this.#findHeaderCells(column_index).item(0);
	}

	#findDataCell(column_index) {
		return this.#findDataCells(column_index).item(0);
	}

	#findClosestHeaderCell(element) {
		return element.closest(`.${CDataTable.ZBX_STYLE_CELL_HEADER}`);
	}

	#findCells(column_index = null, row_index = null, selector = `.${CDataTable.ZBX_STYLE_CELL}`) {
		if (column_index) {
			selector += `[data-col="${column_index.toString()}"]`;
		}

		if (row_index) {
			selector += `[data-row="${row_index.toString()}"]`;
		}

		return this.#body.querySelectorAll(selector);
	}

	#findHeaderCells(column_index = null) {
		let selector = `.${CDataTable.ZBX_STYLE_CELL_HEADER}`;

		if (column_index) {
			selector += `[data-col="${column_index.toString()}"]`;
		}

		return this.#header.querySelectorAll(selector);
	}

	#findDataCells(column_index = null, row_index = null) {
		let selector = `.${CDataTable.ZBX_STYLE_CELL_DATA}`;

		if (column_index) {
			selector += `[data-col="${column_index.toString()}"]`;
		}

		if (row_index) {
			selector += `[data-row="${row_index.toString()}"]`;
		}

		return this.#body.querySelectorAll(selector);
	}

	#convertPixelsToPercent(pixels) {
		return (pixels / (this.#element.getBoundingClientRect().width / 100)).toFixed(4);
	}

	#getWidthWithoutUnit(width) {
		if (!width) {
			return width;
		}

		if (width.endsWith('%')) {
			return parseFloat(width.substring(0, width.length - 1)).toFixed(4);
		}

		if (width.endsWith('px')) {
			return this.#convertPixelsToPercent(width.substring(0, width.length - 2));
		}

		return width;
	}
}
