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
	static EVENT_BEFORE_RENDER = 'render:before';
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

	static ZBX_STYLE_SCROLLBAR = 'datatable-scrollbar';
	static ZBX_STYLE_SCROLLBAR_INNER = 'datatable-scrollbar-inner';

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
	 * Delay in milliseconds before `#resize_click_count` is reset.
	 * Used to distinguish between single and double-click actions on the column resizer.
	 *
	 * @type {number}
	 */
	static RESIZE_CLICK_COUNT_RESET_DELAY = 250;

	/**
	 * Minimum width of the resized column in pixels.
	 *
	 * @type {number}
	 */
	static RESIZE_MIN_WIDTH = 37;

	/**
	 * @type {number}
	 */
	static COLUMN_INITIAL_MIN_WIDTH = 50;

	/**
	 * @type {number}
	 */
	static COLUMN_TOGGLE_INITIAL_MIN_WIDTH = 150;

	/**
	 * @type {number}
	 */
	static COLUMN_HEADER_PADDING = 8;

	/**
	 * @type {number}
	 */
	static COLUMN_OPTIONS_BUTTON_WIDTH = 31;

	/**
	 * @type {number}
	 */
	static COLUMN_SORTABLE_ARROW_WIDTH = 10;

	/**
	 * @type {number}
	 */
	static TABLE_OPTIONS_BUTTON_WIDTH = 32;

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
	 * The reference to the custom scrollbar track element.
	 *
	 * @type {HTMLElement|null}
	 */
	#scrollbar = null;

	/**
	 * The reference to the inner spacer element inside the scrollbar track.
	 * Its width is kept in sync with the body's scrollWidth to enable native scrolling.
	 *
	 * @type {HTMLElement|null}
	 */
	#scrollbar_inner = null;

	/**
	 * Observer instance that monitors changes in the body dimensions.
	 * Ensures the scrollbar inner width is recalculated when the content layout changes.
	 *
	 * @type {ResizeObserver|null}
	 */
	#body_resize_observer = null;

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

	/**
	 * @type {CDataProvider}
	 */
	#data_provider;

	#form_name = null;

	#checkbox_id = null;

	#selectable = null;

	#storage_idx = null;

	#page = 1;

	#filter = {};

	#tabfilter_item = {_index: 0};

	#pager = null;

	#default_sort_field = null;

	#default_sort_order = null;

	#sort_field = null;

	#sort_order = null;

	/**
	 * @type {string}
	 */
	#row_spacer_width = 'auto';

	/**
	 * @type {CDataTableColumn[]}
	 */
	#columns = [];

	/**
	 * @type {CDataTableColumn[]}
	 */
	#visible_columns = [];

	#option_defaults = {
		checked: false,
		onRender: () => {},
		onChange: () => {},
		isChanged: function () {
			return this.checked;
		},
		onReset: function () {
			this.checked = false;
		}
	};

	/**
	 * @type {Object}
	 */
	#options = {};

	#header_renderers = {};

	#cell_renderers = {};

	#row_renderers = {};

	#options_handlers = {};

	#user_configs = [];

	/**
	 * Elements in this queue are all removed at the same time, once new data is ready to be rendered.
	 *
	 * @type {HTMLElement[]}
	 */
	#element_remove_queue = [];

	/**
	 * @type {HTMLElement|null}
	 */
	#element = null;

	/**
	 * @type {HTMLElement|null}
	 */
	#header = null;

	/**
	 * @type {HTMLElement|null}
	 */
	#body = null;

	/**
	 * @type {HTMLElement|null}
	 */
	#footer = null;

	#sticky_header = false;

	#sticky_footer = false;

	#templates = {};

	#bound_events = [];

	#subscriptions = [];

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
			default: ({column, cell}) => {
				if (column.isSortable()) {
					const sort_field = column.getSortField() || column.getId();

					let sort_order = this.#sort_order;

					const label = document.createElement('span');
					label.classList.add('name');
					label.textContent = column.getName();

					const icon = document.createElement('span');

					const header_link = document.createElement('a');
					header_link.classList.add(CDataTable.ZBX_STYLE_LINK_HEADER);

					if (this.#sort_field === sort_field) {
						sort_order = sort_order === ZBX_SORT_UP ? ZBX_SORT_DOWN : ZBX_SORT_UP;

						icon.classList.add(sort_order === ZBX_SORT_UP ? ZBX_STYLE_ARROW_DOWN : ZBX_STYLE_ARROW_UP);
						header_link.classList.add(CDataTable.ZBX_STYLE_LINK_HEADER_SORTED);
					}

					header_link.setAttribute('href', 'javascript:void(0);');
					header_link.append(label, icon);
					header_link.addEventListener('click', e => {
						e.preventDefault();

						this.dispatchEvent(CDataTable.EVENT_DATA_SORT, {sort_field, sort_order});
					});

					cell.classList.add(CDataTable.ZBX_STYLE_CELL_HEADER_LINK);
					cell.appendChild(header_link);
				}
				else if (column.getId() !== this.#checkbox_id) {
					const cell_inner = this.#templates.cell_inner_span.evaluateToElement();
					cell_inner.textContent = column.getName();

					cell.appendChild(cell_inner);
				}

				if (column.getOptionsPopupHandler()) {
					const icon = document.createElement('span');
					icon.classList.add(column.getOptionsPopupHandleIcon());

					const context_handle = document.createElement('button');
					context_handle.classList.add(CDataTable.ZBX_STYLE_OPTIONS_LINK);
					context_handle.setAttribute('type', 'button');
					context_handle.appendChild(icon);
					context_handle.addEventListener('click', e => {
						e.preventDefault();

						this.dispatchEvent(CDataTable.EVENT_COLUMN_OPTIONS_POPUP, {column, handle: context_handle});
					});
					context_handle.addEventListener('pointerdown', () => {
						cell.classList.add(CDataTable.ZBX_STYLE_CELL_FOCUSED);
					});

					if (this.#options_popup?.getColumnConfig().getColumnIndex() == column.getColumnIndex()) {
						context_handle.classList.add(CDataTable.ZBX_STYLE_OPTIONS_LINK_OPENED);

						this.#options_popup.setHandle(context_handle);
					}

					cell.appendChild(context_handle);
				}
			},
			[CDataTableColumn.CHECKBOX]: ({column, cell}) => {
				const id = column.getId();
				const checkbox_id = `all_${this.#form_name}`;

				const checkbox = document.createElement('input');
				checkbox.classList.add(ZBX_STYLE_CHECKBOX_RADIO);
				checkbox.setAttribute('type', 'checkbox');
				checkbox.setAttribute('id', checkbox_id);
				checkbox.setAttribute('name', checkbox_id);
				checkbox.setAttribute('data-field-type', 'checkbox');
				checkbox.addEventListener('click', () => checkAll(this.#form_name, checkbox_id, id));
				checkbox.value = '1';

				const label = document.createElement('label');
				label.setAttribute('for', checkbox_id);
				label.appendChild(document.createElement('span'));

				cell.classList.add(CDataTable.ZBX_STYLE_CELL_CHECKBOX);
				cell.append(checkbox, label);
			}
		};

		this.setOptionsHandler('tags', CDataTableOptionsPopupTags);
		this.setOptionsHandler('tagvalue', CDataTableOptionsPopupTagValue);

		this.setRowRenderer('default', this.renderDataCells);

		this.setCellRenderer(CDataTableColumn.RENDERER_HTML, ({cell_data, cell_inner}) => {
			cell_inner.innerHTML = cell_data.filter(Boolean).join('');
		});

		this.setCellRenderer(CDataTableColumn.RENDERER_ELEMENT, ({cell_data, cell_inner}) => {
			cell_inner.append(...cell_data.filter(Boolean));
		});

		this.setCellRenderer(CDataTableColumn.RENDERER_TEXT, ({cell_data, cell_inner}) => {
			cell_inner.textContent = cell_data.filter(Boolean).join('');
		});

		this.setCellRenderer(CDataTableColumn.CHECKBOX, ({column, cell_data, cell, cell_inner}) => {
			const [object_id, data_actions] = cell_data;

			if (!object_id) {
				return;
			}

			const input_id = `${column.getId()}_${object_id}`;

			const checkbox = document.createElement('input');
			checkbox.classList.add(ZBX_STYLE_CHECKBOX_RADIO);
			checkbox.setAttribute('type', 'checkbox');
			checkbox.setAttribute('id', input_id);
			checkbox.setAttribute('name', `${column.getId()}[${object_id}]`);
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

		this.setCellRenderer('tagvalue', ({column, cell_data, cell_inner}) => {
			const column_options = column.getColumnOptions();

			let [tags] = cell_data;

			if (!tags) {
				return;
			}

			tags = tags.filter(tag => tag.tag === column_options['tag_name']);
			if (tags.length == 0) {
				return;
			}

			const tags_wrapper = document.createElement('div');
			tags_wrapper.classList.add(ZBX_STYLE_TAGS_WRAPPER);

			for (const tag of tags) {
				const tag_label = document.createElement('span');
				tag_label.classList.add(ZBX_STYLE_TAG);
				tag_label.textContent = tag.value;

				if (tag.type == ZBX_PROPERTY_INHERITED) {
					tag_label.classList.add(ZBX_STYLE_TAG_INHERITED);
				}

				const tag_label_hintbox = document.createElement('div');

				if (tag.type == ZBX_PROPERTY_INHERITED) {
					const inherited_title = document.createElement('div');
					inherited_title.classList.add(ZBX_STYLE_TAG_INHERITED_TITLE);
					inherited_title.textContent = t('Inherited tag');

					tag_label_hintbox.appendChild(inherited_title);
				}

				const hintbox_contents = document.createTextNode(`${tag.tag}: ${tag.value}`);
				tag_label_hintbox.appendChild(hintbox_contents);

				tag_label.setAttribute('data-hintbox-html', tag_label_hintbox.outerHTML);
				tag_label.setAttribute('data-hintbox', '1');
				tag_label.setAttribute('data-hintbox-class', ZBX_STYLE_HINTBOX_WRAP);
				tag_label.setAttribute('data-hintbox-static', '1');
				tag_label.setAttribute('aria-expanded', 'false');

				tags_wrapper.appendChild(tag_label);
			}

			cell_inner.appendChild(tags_wrapper);
		});

		this.setCellRenderer('tags', ({column, cell_data, cell_inner, response}) => {
			let [tags] = cell_data;

			if (!tags) {
				return;
			}

			let tag_display_priorities = new Set();

			const column_options = column.getColumnOptions();
			const tag_display_priority = column_options['tag_display_priority'] || '';
			const number_of_tags = column_options['number_of_tags'] || SHOW_TAGS_3;
			const tag_name_display = column_options['tag_name_display'] || TAG_NAME_FULL;

			const priority_tags = tag_display_priority
				.split(',')
				.map(priority => priority.trim())
				.filter(Boolean);

			for (const priority_tag of priority_tags) {
				tag_display_priorities.add(priority_tag);
			}

			if (tag_display_priorities.size > 0) {
				tag_display_priorities = [...tag_display_priorities];

				const matched = tag_display_priorities.flatMap(priority => tags.filter(tag => tag.tag === priority));
				const unmatched = tags.filter(tag => !tag_display_priorities.some(priority => tag.tag === priority));

				tags = [...matched, ...unmatched];
			}

			const tag_labels = [];
			const {subfilter_tags} = {subfilter_tags: null, ...response};

			let count = number_of_tags;

			const tags_wrapper = document.createElement('div');
			tags_wrapper.classList.add(ZBX_STYLE_TAGS_WRAPPER);

			for (const tag of tags) {
				let tag_label;

				const subfilter_tag = subfilter_tags != null
					? Object.keys(subfilter_tags).includes(tag.tag)
					: false;
				const subfilter_tag_value = subfilter_tag
					? Object.keys(subfilter_tags[tag.tag]).includes(tag.value)
					: false;
				const has_subfilters = subfilter_tags != null && (!subfilter_tag || !subfilter_tag_value);

				if (has_subfilters) {
					tag_label = document.createElement('button');
					tag_label.classList.add(ZBX_STYLE_BTN_TAG, ZBX_STYLE_TAG);
					tag_label.setAttribute('type', 'button');
					tag_label.setAttribute('data-key', tag.tag);
					tag_label.setAttribute('data-value', tag.value);
				}
				else {
					tag_label = document.createElement('span');
					tag_label.classList.add(ZBX_STYLE_TAG);
				}

				tag_label.textContent = `${tag.tag}`;
				if (tag.value) {
					tag_label.textContent += `: ${tag.value}`;
				}

				const tag_label_hintbox = document.createElement('div');

				if (tag.type === ZBX_PROPERTY_INHERITED) {
					tag_label.classList.add(ZBX_STYLE_TAG_INHERITED);

					const inherited_title = document.createElement('div');
					inherited_title.classList.add(ZBX_STYLE_TAG_INHERITED_TITLE);
					inherited_title.textContent = t('Inherited tag');

					tag_label_hintbox.appendChild(inherited_title);
				}

				const column_options = column.getColumnOptions();

				if (tag.type === ZBX_PROPERTY_BOTH) {
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
					inherited_title.textContent = column_options.object_type
						? hint_titles[column_options.object_type]
						: '';

					tag_label_hintbox.appendChild(inherited_title);
				}

				const hintbox_contents = document.createTextNode(tag_label.textContent);
				tag_label_hintbox.appendChild(hintbox_contents);

				tag_label.setAttribute('data-hintbox-html', tag_label_hintbox.outerHTML);
				tag_label.setAttribute('data-hintbox', '1');
				tag_label.setAttribute('data-hintbox-class', ZBX_STYLE_HINTBOX_WRAP);
				tag_label.setAttribute('data-hintbox-static', '1');
				tag_label.setAttribute('aria-expanded', 'false');

				if (count > 0) {
					const tag_label_clone = tag_label.cloneNode(true);

					if (tag_name_display == TAG_NAME_NONE && tag.value) {
						tag_label_clone.textContent = tag.value;
					}
					else if (tag_name_display == TAG_NAME_SHORTENED) {
						tag_label_clone.textContent = tag.tag.substring(0, 3);
					}
					else {
						tag_label_clone.textContent = tag.tag;
					}

					if (tag_name_display != TAG_NAME_NONE && tag.value) {
						tag_label_clone.textContent += `: ${tag.value}`;
					}

					if (has_subfilters) {
						tag_label_clone.addEventListener('click', e => {
							const {key, value} = e.target.dataset;

							view.setSubfilter([`subfilter_tags[${encodeURIComponent(key)}][]`, value]);
						});
					}

					if (tag_name_display != TAG_NAME_NONE || tag.value) {
						tags_wrapper.appendChild(tag_label_clone);
					}
					else {
						count++;
					}
				}

				tag_labels.push(tag_label);
				count--;
			}

			if (tags.length > number_of_tags) {
				const more_tags_hintbox = document.createElement('div');

				for (const tag_label of tag_labels) {
					more_tags_hintbox.appendChild(tag_label.cloneNode(true));
				}

				const more_tags = document.createElement('button');
				more_tags.classList.add(ZBX_STYLE_BTN_ICON, ZBX_ICON_MORE);
				more_tags.setAttribute('data-hintbox-html', more_tags_hintbox.innerHTML);
				more_tags.setAttribute('data-hintbox-class', `${ZBX_STYLE_HINTBOX_WRAP} ${ZBX_STYLE_TAGS_WRAPPER}`);
				more_tags.setAttribute('data-hintbox', '1');
				more_tags.setAttribute('data-hintbox-static', '1');
				more_tags.setAttribute('aria-expanded', 'false');

				tags_wrapper.appendChild(more_tags);
			}

			cell_inner.appendChild(tags_wrapper);
		});

		this.setOptionsHandler(CDataTableColumn.TABLE_OPTIONS, CDataTableOptionsPopupTableOptions);

		this.#templates = {
			footer: new Template(`
				<div class="${CDataTable.ZBX_STYLE_FOOTER} ${ZBX_STYLE_HIDDEN}">
					<div class="${ZBX_STYLE_SELECTED_ITEM_COUNT}"></div>
				</div>
			`),
			cell: new Template(`
				<div class="${CDataTable.ZBX_STYLE_CELL}"></div>
			`),
			cell_inner: new Template(`
				<div class="${CDataTable.ZBX_STYLE_CELL_INNER}"></div>
			`),
			cell_inner_span: new Template(`
				<span class="${CDataTable.ZBX_STYLE_CELL_INNER}"></span>
			`),
			row: new Template(`
				<div class="${CDataTable.ZBX_STYLE_ROW}"></div>
			`),
			row_spacer: new Template(`
				<div class="${CDataTable.ZBX_STYLE_ROW_SPACER}"></div>
			`),
			table_options_button: new Template(`
				<div class="${CDataTable.ZBX_STYLE_OPTIONS_BUTTON} ${ZBX_STYLE_HIDDEN}" tabindex="-1">
					<button class="${CDataTable.ZBX_STYLE_OPTIONS_LINK}" type="button" role="button" title="#{title}">
						<span class="${ZBX_ICON_FILTERS}"></span>
					</button>
				</div>
			`),
			scrollbar: new Template(`
				<div class="${CDataTable.ZBX_STYLE_SCROLLBAR}">
					<div class="${CDataTable.ZBX_STYLE_SCROLLBAR_INNER}"></div>
				</div>
			`)
		}
	}

	destroy() {
		this.#unbindEvents();

		if (this.#scrollbar) {
			this.#unbindScrollbarEvents();

			this.#scrollbar.remove();
			this.#scrollbar = null;

			this.#scrollbar_inner = null;
		}

		this.#save_config_request?.abort?.();
		this.#save_config_request = null;

		this.#initialized = false;
		this.#resizing = false;
		this.#options_popup = null;
	}

	init(user_configs) {
		if (this.#initialized) {
			return this;
		}

		this.#user_configs = user_configs || [{}];

		this.#initColumns();

		this.#visible_columns = this.getVisibleColumns();

		this.#header = document.createElement('div');
		this.#header.classList.add(CDataTable.ZBX_STYLE_HEADER);

		if (this.#sticky_header) {
			this.#header.classList.add(CDataTable.ZBX_STYLE_HEADER_STICKY);
		}

		this.#body = document.createElement('div');
		this.#body.classList.add(CDataTable.ZBX_STYLE_BODY);
		this.#body.addEventListener('scroll', this.onBodyScroll);
		this.#body.appendChild(this.#createNoDataMessage());

		this.#footer = this.#templates.footer.evaluateToElement();

		if (this.#selectable) {
			this.#footer.querySelector(`.${ZBX_STYLE_SELECTED_ITEM_COUNT}`).textContent = `0 ${t('selected')}`;
		}

		if (this.#sticky_footer) {
			this.#footer.classList.add(CDataTable.ZBX_STYLE_FOOTER_STICKY);
		}

		this.#row_spacer_width = '0px';
		this.#applyColumnWidths();
		this.#renderHeaderCells();

		this.#element.classList.add(CDataTable.ZBX_STYLE_DATATABLE, CDataTable.ZBX_STYLE_SCROLLABLE);
		this.#element.innerHTML = '';
		this.#element.append(this.#header, this.#body, this.#footer);

		this.#pager = new CPager(this.#footer);

		this.#bindEvents();

		this.dispatchEvent(CDataTable.EVENT_INIT);

		return this;
	}

	#initCheckBoxRange() {
		const selector = `.${CDataTable.ZBX_STYLE_DATATABLE}`;
		const row_selector = `.${CDataTable.ZBX_STYLE_ROW}`;
		const thead_selector = `.${CDataTable.ZBX_STYLE_HEADER} .${CDataTable.ZBX_STYLE_CELL_CHECKBOX}`;

		chkbxRange.init({selector, row_selector, thead_selector});
	}

	setRowRenderer(name, callback) {
		if (this.#initialized) {
			return this;
		}

		this.#row_renderers[name] = callback.bind(this);

		return this;
	}

	setCellRenderer(name, renderer) {
		if (this.#initialized) {
			return this;
		}

		this.#cell_renderers[name] = renderer;

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
		return [...this.#columns];
	}

	getVisibleColumns() {
		return this.#columns.filter(column => column.isVisible());
	}

	getStickyColumns() {
		return this.#columns.filter(column => column.isSticky());
	}

	getNonDuplicateColumns() {
		return this.#columns.filter(column => !column.isDuplicate());
	}

	getColumnsInRange(lowest_order = null, highest_order = null) {
		return this.#columns.filter(column => {
			if (column.getId() === this.#checkbox_id) {
				return false;
			}

			if (lowest_order !== null && highest_order !== null) {
				return column.getOrder() >= lowest_order && column.getOrder() <= highest_order;
			}

			return true;
		});
	}

	getColumnById(id, duplicate = false) {
		return this.#columns.find(column => column.getId() == id && column.isDuplicate() == duplicate);
	}

	getCheckboxColumn() {
		return this.getColumnById(this.#checkbox_id);
	}

	setColumns(columns) {
		this.#columns = columns;

		return this;
	}

	getOptions() {
		return {...this.#options};
	}

	getOption(id) {
		return this.#options[id];
	}

	setOption(id, name, params = {}) {
		this.#options[id] = Object.assign({}, this.#option_defaults, params, {id, name});

		return this;
	}

	updateOption(id, params) {
		const option = this.#options[id] || {};

		this.#options[id] = {...option, ...params};

		this.updateUserConfig();

		return this;
	}

	setSelectable(form_name, checkbox_id, selectable = []) {
		this.#form_name = form_name;
		this.#checkbox_id = checkbox_id;
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

	isCustomizable() {
		return this.#customizable;
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
		return {...this.#filter};
	}

	setFilter(filter) {
		this.#filter = {...filter};

		return this;
	}

	getPage() {
		return this.#pager?.getPage() ?? this.#page;
	}

	setTabFilterItem(tabfilter_item) {
		this.#tabfilter_item = tabfilter_item;

		return this;
	}

	getDefaultSortField() {
		return this.#default_sort_field;
	}

	setDefaultSortField(default_sort_field) {
		this.#default_sort_field = default_sort_field;

		return this;
	}

	getDefaultSortOrder() {
		return this.#default_sort_order;
	}

	setDefaultSortOrder(default_sort_order) {
		this.#default_sort_order = default_sort_order;

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

	onRender(e) {
		const response = e.detail.response;

		this.#visible_columns = this.getVisibleColumns();

		this.#lockHeight();
		this.#clearBody();
		this.#recalculateColumnSpans();
		this.#renderHeaderCells();
		this.#renderBody(response);
		this.#afterRender(response);
	}

	onColumnToggle(e) {
		if (e.defaultPrevented) {
			return;
		}

		const {column_index, visible} = e.detail;

		const column = this.getColumn(column_index);
		if (!column || !column.isTogglable()) {
			return;
		}

		if (!column.isResized()) {
			column.setWidth(`${CDataTable.COLUMN_TOGGLE_INITIAL_MIN_WIDTH}px`);
		}

		column.setVisible(visible);

		const overrides = column.getOverrides();
		column.setOverrides({...overrides, visible});

		this.#options_popup_updated = true;

		this.updateUserConfig();

		this.dispatchEvent(CDataTable.EVENT_INIT, {reset: true});
		this.dispatchEvent(CDataTable.EVENT_SAVE);

		requestAnimationFrame(() => {
			const header_cell = column.getHeaderCell();
			if (header_cell === null) {
				return;
			}

			this.#scrollBodyToTarget(header_cell.target);
		});
	}

	onColumnsSort(e) {
		const {items, index, index_to} = e.detail;

		const [offset, start, end] = index > index_to
			? [1, index_to + 1, index]
			: [-1, index, index_to - 1];

		const item = items.item(index_to);
		const column_index = parseInt(item.getAttribute('data-col'));
		const lowest_order = this.getColumn(parseInt(items[start].getAttribute('data-col'))).getOrder();
		const highest_order = this.getColumn(parseInt(items[end].getAttribute('data-col'))).getOrder();

		const columns = this.getColumnsInRange(lowest_order, highest_order);

		const column = this.getColumn(column_index);
		column.setOrder(columns.at(offset < 0 ? columns.length - 1 : 0).getOrder());

		for (const column of columns) {
			column.setOrder(column.getOrder() + offset);
		}

		this.#sortColumns();

		this.updateUserConfig()
			.getData()
			.then(response => {
				this.dispatchEvent(CDataTable.EVENT_RENDER, {response});
				this.dispatchEvent(CDataTable.EVENT_SAVE);
			});
	}

	onColumnDuplicate(e) {
		let {column_index, user_column} = {user_column: {}, ...e.detail};

		const column = this.getColumn(column_index);
		if (!column) {
			return null;
		}

		const duplicate_column = this.#duplicateColumnConfig(column, user_column);
		duplicate_column.setColumnIndex(this.#columns.length);

		const start = this.#columns.indexOf(column) + 1;

		this.#columns.splice(start, 0, duplicate_column);

		for (let i = start; i < this.#columns.length; i++) {
			this.#columns[i].setOrder(i + 1);
		}

		this.#options_popup?.dispatchEvent(CDataTableOptionsPopup.EVENT_CLOSE);

		this.getData().then(response => {
			this.dispatchEvent(CDataTable.EVENT_RENDER, {response});
			this.dispatchEvent(CDataTable.EVENT_SAVE);

			requestAnimationFrame(() => {
				const header_cell = duplicate_column.getHeaderCell();
				if (header_cell) {
					this.#scrollBodyToTarget(header_cell.target);

					header_cell.target.focus();
				}
			});
		});
	}

	onColumnDelete(e) {
		const {column_index} = e.detail;

		const column = this.getColumn(column_index);
		if (!column) {
			return;
		}

		const message = sprintf(t('Are you sure you want to delete %1$s? This action cannot be undone.'),
			column.getName());

		if (!confirm(message)) {
			return;
		}

		const header_cell = column.getHeaderCell();
		if (header_cell) {
			header_cell.target.remove();
		}

		const index = this.#columns.indexOf(column);
		this.#columns.splice(index, 1);

		this.#sortColumns();

		for (const column of this.#columns) {
			const index = this.#columns.indexOf(column);
			column.setOrder(index + 1);
		}

		this.getData().then(response => {
			this.#options_popup?.dispatchEvent(CDataTableOptionsPopup.EVENT_CLOSE);

			this.dispatchEvent(CDataTable.EVENT_RENDER, {response});
			this.dispatchEvent(CDataTable.EVENT_SAVE);
		});
	}

	onColumnRename(e) {
		const {column_index, name} = e.detail;

		const column = this.getColumn(column_index);
		if (!column || (!column.isDuplicate() && !column.isRenamable())) {
			return;
		}

		column.setName(name);

		const cell_inner = column.getHeaderCell().target.querySelector(`.${CDataTable.ZBX_STYLE_CELL_INNER}`);
		cell_inner.textContent = name;
	}

	onColumnReset(e) {
		const {column_index} = e.detail;

		const column = this.getColumn(column_index);
		if (!column) {
			return;
		}

		if (column.getDefaults().getWidth() == 'auto') {
			column.resetWidth(`${CDataTable.COLUMN_TOGGLE_INITIAL_MIN_WIDTH}px`);
		}
		else {
			column.resetWidth();

			this.#applyColumnWidths();
			this.#calculateColumnWidth(column);
		}

		this.#applyColumnWidths();
		this.dispatchEvent(CDataTable.EVENT_SAVE);

		this.#resize_click_count = 0;
	}

	onReset() {
		this.#resetColumns();

		this.#options_popup?.dispatchEvent(CDataTableOptionsPopup.EVENT_CLOSE);

		const reset_options = this.#resetOptions();
		const reset_sort = this.#resetSort();
		const force_load = reset_options || reset_sort;

		this.dispatchEvent(CDataTable.EVENT_INIT, {force_load, reset: true});
		this.dispatchEvent(CDataTable.EVENT_SAVE);
	}

	getData(params = {}) {
		params = this.#getDataProviderParams({check_changes: false, force_load: false, ...params});

		return this.#data_provider.getData(params);
	}

	onInit(e) {
		const {loading, check_changes, force_load, reset} = {
			loading: true,
			check_changes: true,
			force_load: false,
			reset: false,
			...e.detail
		};

		if (loading) {
			this.#element.classList.add(ZBX_STYLE_LOADING);
		}

		const {onSuccess, onError, onFinally} = {
			onSuccess: () => {},
			onError: () => {},
			onFinally: () => {},
			...e.detail
		};

		if (!reset) {
			this.#setUserConfig(this.#tabfilter_item._index);
		}

		if (!this.#initialized) {
			this.#recalculateColumnSpans();
		}

		this.getData({check_changes, force_load})
			.then(response => {
				if ('error' in response) {
					const title = response.error.title || t('Unexpected server error.');
					const messages = response.error.messages || [];

					CMessageHelper.error(this.#element, messages, title);
				}

				return response;
			})
			.then(response => {
				window.addEventListener('resize', this.onWindowResize);

				this.dispatchEvent(CDataTable.EVENT_BEFORE_RENDER, {response});
				this.dispatchEvent(CDataTable.EVENT_RENDER, {response});

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

	onResizePointerMove = e => {
		this.dispatchEvent(CDataTable.EVENT_COLUMN_RESIZE, {event: e});
	}

	onResizePointerUp = e => {
		this.dispatchEvent(CDataTable.EVENT_COLUMN_RESIZE_END, {event: e});
	}

	onColumnResize(e) {
		const column = this.getColumn(this.#resize_column_index);

		if (!column || !this.#resizing) {
			return;
		}

		const {clientX} = e.detail.event;
		const delta_x = clientX - this.#resize_start_x;
		const min_width = this.#getColumnMinWidth(column);

		let width = Math.ceil(Math.max(min_width, this.#resize_start_width + delta_x));

		column.setWidth(`${width}px`);

		this.#applyColumnWidths();
		this.#handleScrollbar();
	}

	onColumnResizeStart(e) {
		const {x, column_index, id} = e.detail;

		this.#resize_click_count++;

		if (this.#resize_click_count == 2) {
			return this.dispatchEvent(CDataTable.EVENT_COLUMN_RESET, {column_index, id});
		}

		const column = this.getColumn(column_index);
		if (!column) {
			return;
		}

		column.setResized(false);

		this.#resizing = true;
		this.#resize_column_index = column_index;
		this.#resize_start_x = x;
		this.#resize_start_width = this.#getWidthWithoutUnit(column.getWidth());

		document.body.classList.add(CDataTable.ZBX_STYLE_RESIZING);

		column.getHeaderCell().target.classList.add(CDataTable.ZBX_STYLE_CELL_RESIZING);
	}

	onColumnResizeEnd() {
		if (!this.#resizing) {
			return;
		}

		const column = this.getColumn(this.#resize_column_index);
		if (!column) {
			return;
		}

		column.setResized(true);

		column.getHeaderCell().target.classList.remove(CDataTable.ZBX_STYLE_CELL_RESIZING);

		this.#resizing = false;
		this.#resize_column_index = -1;
		this.#resize_start_x = 0;
		this.#resize_start_width = 0;

		document.body.classList.remove(CDataTable.ZBX_STYLE_RESIZING);

		this.dispatchEvent(CDataTable.EVENT_SAVE);
	}

	onDataSort(e) {
		const {sort_field, sort_order} = e.detail;

		const state = new CState();
		state.setParams({sort: sort_field, sortorder: sort_order});
		state.push();

		this.#sort_field = sort_field;
		this.#sort_order = sort_order;

		this.#element.classList.add(ZBX_STYLE_LOADING);

		this.dispatchEvent(CDataTable.EVENT_SAVE);
		this.dispatchEvent(CDataTable.EVENT_INIT);
	}

	onColumnOptionsPopup(e) {
		const {column, handle} = e.detail;

		if (this.#options_popup?.isOpen(handle)) {
			this.#options_popup.dispatchEvent(CDataTableOptionsPopup.EVENT_SAVE);
			this.#options_popup.dispatchEvent(CDataTableOptionsPopup.EVENT_CLOSE);

			return;
		}

		const column_options_handler = column.getOptionsPopupHandler();

		const handler = this.#options_handlers[column_options_handler];
		if (!handler) {
			return;
		}

		const header_cell = column.getHeaderCell();

		requestAnimationFrame(() => {
			this.#options_popup = this.#createColumnOptionsPopup(handler, column, header_cell, handle);
			this.#options_popup.dispatchEvent(CDataTableOptionsPopup.EVENT_OPEN);
		});
	}

	onColumnOptionsPopupOpen(e) {
		if (!this.#options_popup) {
			return;
		}

		this.dispatchEvent(CDataTable.EVENT_OPTIONS_POPUP_OPEN, e.detail);
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

	onColumnOptionsPopupUpdate(e) {
		if (!this.#options_popup) {
			return;
		}

		const {column_index, column_options} = e.detail;
		const column = this.getColumn(column_index);

		if (!deepCompare(column.getColumnOptions(), column_options)) {
			column.setColumnOptions(column_options);

			this.#renderColumnDataCells(column);

			this.#options_popup_updated = true;

			requestAnimationFrame(() => {
				const header_cell = column.getHeaderCell();
				if (header_cell) {
					this.#scrollBodyToTarget(header_cell.target);
				}
			});
		}

		this.dispatchEvent(CDataTable.EVENT_OPTIONS_POPUP_UPDATE, e.detail);
	}

	onOptionsPopup(e) {
		const {handler, column, header_cell, handle} = e.detail;

		if (column.getId() != CDataTableColumn.TABLE_OPTIONS) {
			this.#scrollBodyToTarget(header_cell);
		}

		if (this.#options_popup?.isOpen(handle)) {
			this.#options_popup.dispatchEvent(CDataTableOptionsPopup.EVENT_SAVE);
			this.#options_popup.dispatchEvent(CDataTableOptionsPopup.EVENT_CLOSE);

			return;
		}

		this.#options_popup_updated = true;

		requestAnimationFrame(() => {
			this.#options_popup = this.#createOptionsPopup(handler, column, header_cell, handle);
			this.#options_popup.dispatchEvent(CDataTableOptionsPopup.EVENT_OPEN);
		});
	}

	onOptionsPopupOpen() {
		if (!this.#options_popup) {
			return;
		}

		const handle = this.#options_popup.getHandle();
		handle.classList.add(CDataTable.ZBX_STYLE_OPTIONS_LINK_OPENED);

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

	onOptionsPopupUpdate(e) {
		if (!this.#options_popup) {
			return;
		}

		const {save = false} = e.detail;

		if (save) {
			this.dispatchEvent(CDataTable.EVENT_SAVE);
		}
	}

	onScroll() {
		this.#header.scrollLeft = this.#body.scrollLeft;

		if (this.#scrollbar) {
			this.#scrollbar.scrollLeft = this.#body.scrollLeft;
		}

		this.#updateTableOptionsButtonPosition();

		if (this.#options_popup_updated) {
			this.#options_popup_updated = false;

			return;
		}

		this.#options_popup?.dispatchEvent(CDataTableOptionsPopup.EVENT_CLOSE);
	}

	onBodyScroll = () => {
		this.dispatchEvent(CDataTable.EVENT_SCROLL);
	}

	onScrollbarScroll = () => {
		this.#header.scrollLeft = this.#scrollbar.scrollLeft;
		this.#body.scrollLeft = this.#scrollbar.scrollLeft;
	}

	onPagerSelect = e => {
		const {page} = e.detail;

		this.#page = page;

		this.dispatchEvent(CDataTable.EVENT_INIT);
		this.dispatchEvent(CPager.EVENT_SELECT, e.detail);
	}

	onPagerStateChange = e => {
		this.dispatchEvent(CPager.EVENT_STATE_CHANGE, e.detail);
	}

	on(event, callback, options = undefined) {
		const bound_callback = callback.bind(this);

		this.#element.addEventListener(event, bound_callback, options);

		this.#bound_events.push({event, callback: bound_callback, options});

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

			const byte_order_mark = new Uint8Array([0xEF, 0xBB, 0xBF]);
			const blob = new Blob([byte_order_mark, response.export], {type: 'text/csv;charset=utf-8'});
			const url = URL.createObjectURL(blob);

			target.setAttribute('href', url.toString());
			target.setAttribute('download', file);
			target.click();

			window.URL.revokeObjectURL(url);
			target.removeAttribute('download');
		}).finally(() => {
			target.setAttribute('href', href);
			target.setAttribute('onclick', onclick);
		});
	}

	getConfig() {
		const options = Object.entries(this.#options).reduce((options, [id, option]) => {
			options[id] = option.checked ? 1 : 0;

			return options;
		}, {});

		return {
			columns: this.getColumnsInRange().map(column => column.diff()),
			options,
			sort_field: this.#sort_field,
			sort_order: this.#sort_order
		};
	}

	/**
	 * @param {Object|undefined} user_config
	 *
	 * @returns {CDataTable}
	 */
	updateUserConfig(user_config = undefined) {
		this.#user_configs[this.#tabfilter_item._index] = user_config || this.getConfig();

		return this;
	}

	/**
	 * Retrieves the configuration object for a specific column.
	 *
	 * @param {number} column_index
	 * @returns {CDataTableColumn|undefined}
	 */
	getColumn(column_index) {
		return this.#columns.find(column => column.getColumnIndex() == column_index);
	}

	/**
	 * @param {CDataTableColumn} column
	 * @returns {{target: HTMLElement}}
	 */
	createDataCell(column) {
		const data_cell = this.#templates.cell.evaluateToElement();
		data_cell.classList.add(CDataTable.ZBX_STYLE_CELL_DATA);

		if (column.getSpan() > 1) {
			data_cell.style.gridColumn = `span ${column.getSpan()}`;
		}

		this.#makeCellSticky(column, data_cell);

		if (column.isOnlyHeader() || !column.isVisible()) {
			data_cell.classList.add(ZBX_STYLE_HIDDEN);
		}

		return {target: data_cell};
	}

	createRowSpacer(target) {
		if (this.findRowSpacer(target)) {
			return;
		}

		target.appendChild(this.#templates.row_spacer.evaluateToElement());
	}

	/**
	 * @param {number|null} column_index
	 * @param {number|null} row_index
	 * @returns {Object[]}
	 */
	findDataCells({column_index = null, row_index = null} = {}) {
		const data_cells = [];

		for (const column of this.#columns) {
			if (column_index !== null && column.getColumnIndex() !== column_index) {
				continue;
			}

			if (row_index !== null) {
				data_cells.push(column.getDataCells().at(row_index));
			}
			else {
				for (const data_cell of column.getDataCells()) {
					data_cells.push(data_cell);
				}
			}
		}

		return data_cells.filter(Boolean);
	}

	renderDataCells({columns, row, row_index, data_fields, row_data, response}) {
		for (const column of columns) {
			const data_cell = this.createDataCell(column);

			const data_cells = column.getDataCells();
			data_cells[row_index] = data_cell;
			column.setDataCells(data_cells);

			row.appendChild(data_cell.target);
		}

		if (this.#customizable) {
			this.createRowSpacer(row);
		}

		// This is separately from the loop above on purpose, since row renderer may have an impact on all cells
		// within a row, which means that cells should be present BEFORE the renderer is executed.
		for (const column of columns) {
			if (column.isOnlyHeader()) {
				continue;
			}

			const cell_data = this.collectColumnData(column, data_fields, row_data);
			const data_cells = column.getDataCells();
			const data_cell = data_cells[row_index];

			this.renderDataCellContents(column, row, row_index, data_cell, data_fields, cell_data, response);
		}
	}

	renderDataCellContents(column, row, row_index, data_cell, data_fields, cell_data, response) {
		const renderer = column.getRenderer();

		if (!this.#cell_renderers[renderer]) {
			return;
		}

		const cell_inner = this.#templates.cell_inner.evaluateToElement();

		data_cell.target.innerHTML = '';
		data_cell.target.appendChild(cell_inner);

		try {
			this.#cell_renderers[renderer].call(this, {
				datatable: this,
				column,
				row,
				row_index,
				cell_data: cell_data || [],
				cell: data_cell.target,
				cell_inner,
				response
			});
		} catch (error) {
			console.error(error);

			data_cell.target.classList.add(CDataTable.ZBX_STYLE_CELL_ERROR);

			cell_inner.textContent = error.message;
		}
	}

	#initColumns() {
		if (this.#checkbox_id) {
			this.#columns.unshift(
				new CDataTableColumn(this.#checkbox_id, '')
					.setFields(this.#selectable)
					.setRenderer(CDataTableColumn.CHECKBOX)
					.setResizable(false)
					.setShowInTableOptions(false)
					.setSticky(true)
					.setWidth('33px')
			);
		}

		const orders = new Set();

		for (const column of this.#columns) {
			const column_index = this.#columns.indexOf(column);
			const order = column.getOrder();

			if (!order || orders.has(order)) {
				let next_order = order + 1;

				while (orders.has(next_order)) {
					next_order++;
				}

				column.setOrder(next_order);
				orders.add(next_order);
			}
			else {
				orders.add(order);
			}

			column.setColumnIndex(column_index)
				.setDefaults(column.clone());
		}

		this.#sortColumns();
	}

	#resetColumns() {
		this.#columns = this.#columns.filter(column => {
			if (column.isDuplicate()) {
				const header_cell = column.getHeaderCell();
				if (header_cell) {
					this.#element_remove_queue.push(header_cell.target);

					column.setHeaderCell(null);
				}

				for (const data_cell of column.getDataCells()) {
					if (data_cell) {
						this.#element_remove_queue.push(data_cell.target);
					}
				}

				column.setDataCells([]);
			}
			else {
				column.merge(column.getDefaults().toObject());
			}

			return !column.isDuplicate();
		});

		this.#sortColumns();
	}

	#resetOptions() {
		const options = Object.entries(this.#options);
		const force_load = options.some(([_, option]) => option.isChanged());

		for (const [, option] of options) {
			option.onReset();
		}

		return force_load;
	}

	#resetSort() {
		if (this.#sort_field != this.#default_sort_field || this.#sort_order != this.#default_sort_order) {
			this.#sort_field = this.#default_sort_field;
			this.#sort_order = this.#default_sort_order;

			return true;
		}

		return false;
	}

	#setUserConfig(tabfilter_idx) {
		if (!this.#customizable && !this.#resizable) {
			return;
		}

		this.#resetColumns();

		const user_config = this.#user_configs[tabfilter_idx];
		if (!user_config) {
			return;
		}

		if (user_config.sort_field) {
			this.#sort_field = user_config.sort_field;
		}

		if (user_config.sort_order) {
			this.#sort_order = user_config.sort_order;
		}

		if (user_config.columns) {
			const user_columns = user_config.columns.filter(user_column => 'id' in user_column);

			// Merge original columns
			for (const user_column of user_columns.filter(user_column => !user_column?.duplicate)) {
				const column = this.getColumnById(user_column.id);
				if (!column) {
					continue;
				}

				column.merge(user_column);
			}

			// Handle duplicated columns
			for (const user_column of user_columns) {
				const column = this.getColumnById(user_column.id);
				if (!column) {
					continue;
				}

				if (!user_column.duplicate) {
					column.merge(user_column);

					continue;
				}

				const duplicate_column = this.#duplicateColumnConfig(column, user_column);
				duplicate_column.setColumnIndex(this.#columns.length);

				this.#columns.splice(this.#columns.indexOf(column) + 1, 0, duplicate_column);
			}

			this.#columns.sort((a, b) => {
				if (a.getOrder() && b.getOrder()) {
					return a.getOrder() - b.getOrder();
				}

				const order_a = a.getOrder() ?? (user_columns.indexOf(a) + 0.5);
				const order_b = b.getOrder() ?? (user_columns.indexOf(b) + 0.5);

				return order_a - order_b;
			});

			for (const column of this.#columns) {
				const column_index = this.#columns.indexOf(column);
				column.setOrder(column_index + 1);
			}
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

	#createHeaderCell(column) {
		let column_options_handler = column.getOptionsPopupHandler();

		if (column.isDuplicate()) {
			const duplicate_column = this.#columns.find(duplicate_column => {
				return duplicate_column.getId() == column.getId() && duplicate_column.isDuplicate();
			});

			if (duplicate_column) {
				column_options_handler = duplicate_column.getOptionsPopupHandler();
			}
		}

		const header_cell = this.#templates.cell.evaluateToElement();
		header_cell.classList.add(CDataTable.ZBX_STYLE_CELL_HEADER);
		header_cell.setAttribute('tabindex', '-1');

		if (column_options_handler) {
			header_cell.classList.add(CDataTable.ZBX_STYLE_CELL_CONTEXT);
		}

		if (column.isVisible()) {
			this.#renderHeaderCellContents(column, header_cell);
		}
		else {
			header_cell.classList.add(ZBX_STYLE_HIDDEN);
		}

		this.#makeCellSticky(column, header_cell);

		return {target: header_cell};
	}

	#makeCellSticky(column, cell) {
		if (!column.isSticky()) {
			return;
		}

		const visible_columns = this.getVisibleColumns()
		if (!visible_columns.length) {
			return;
		}

		const is_first = visible_columns.at(0).getId() == column.getId();
		const is_last = visible_columns[visible_columns.length - 1]?.getId() == column.getId();

		if (is_first || is_last) {
			cell.classList.add(CDataTable.ZBX_STYLE_CELL_STICKY);
			cell.style[is_first ? 'left' : 'right'] = '0px';
		}
	}

	#clearBody() {
		this.#body.innerHTML = '';

		for (const column of this.#columns) {
			column.setDataCells([]);
		}
	}

	#lockHeight() {
		this.#element.style.height = `${this.#element.clientHeight}px`;
	}

	#unlockHeight() {
		this.#element.style.height = null;
	}

	/**
	 * @param {string|undefined} icon
	 * @param {string|undefined} message
	 * @param {string|undefined} description
	 */
	#renderEmptyState({icon = ZBX_ICON_SEARCH_LARGE, message = t('No data found'), description = undefined} = {}) {
		const no_data_message = this.#createNoDataMessage({icon, message, description});

		this.#body.appendChild(no_data_message);
	}

	#renderRows(response) {
		const data_fields = response.data_fields;

		for (let row_index = 0; row_index < response.data.length; row_index++) {
			const [row_config, row_data] = response.data[row_index];

			const row = this.#templates.row.evaluateToElement();

			const renderer = this.#row_renderers[row_config.renderer] || this.#row_renderers.default;
			renderer.call(this, {columns: this.#visible_columns, row, row_index, row_config, data_fields, row_data,
				response});

			this.#body.appendChild(row);
		}
	}

	#updateLayoutAfterRender() {
		for (const element of this.#element_remove_queue) {
			element?.remove();
		}
		this.#element_remove_queue = [];

		this.#header.scrollTo({left: 0});
		this.#body.scrollTo({left: 0});

		for (const column of this.getStickyColumns()) {
			const header_cell = column.getHeaderCell();
			if (!header_cell) {
				continue;
			}

			header_cell.target.classList.add(CDataTable.ZBX_STYLE_CELL_STICKY);
			header_cell.target.style.left = '0';
		}
	}

	#renderBody(response) {
		if ('error' in response) {
			this.#renderEmptyState();

			return;
		}

		if (!('data' in response) || response.data.length === 0) {
			this.#renderEmptyState({
				icon: response.no_data_icon,
				message: response.no_data_message,
				description: response.no_data_description
			});
		}
		else {
			this.#footer.classList.remove(ZBX_STYLE_HIDDEN);
		}

		if (this.#visible_columns.length > 0) {
			this.#renderRows(response);

			for (const [, option] of Object.entries(this.#options)) {
				option.onRender(option);
			}
		}

		this.#updateLayoutAfterRender();
	}

	#afterRender(response) {
		this.#calculateColumnWidths(response);
		this.#handleScrollbar();

		this.#pager.update(response);

		requestAnimationFrame(() => {
			requestAnimationFrame(() => {
				this.#options_popup?.position();
			});

			this.#initCheckBoxRange();
			this.#unlockHeight();

			this.#element.classList.remove(ZBX_STYLE_LOADING);
		});
	}

	#renderHeaderCellContents(column, header_cell) {
		header_cell.innerHTML = '';

		if (this.#resizable && column.isResizable()) {
			const resize_handle = document.createElement('div');
			resize_handle.classList.add(CDataTable.ZBX_STYLE_CELL_HEADER_RESIZER);

			this.#bindColumnResizeEvent(column, resize_handle);

			header_cell.appendChild(resize_handle);
		}

		let header_renderer = 'default';
		if (column.getId() == this.#checkbox_id) {
			header_renderer = column.getRenderer();
		}

		this.#header_renderers[header_renderer].call(this, {
			column,
			datatable: this,
			cell: header_cell
		});
	}

	collectColumnData(column, data_fields, row_data) {
		const column_fields = column.isDuplicate()
			? this.getColumnById(column.getId()).getFields()
			: column.getFields();

		const cell_data = [];

		for (const field of column_fields) {
			const field_index = data_fields.indexOf(field);

			cell_data.push(field_index >= 0 ? row_data[field_index] : null);
		}

		return cell_data;
	}

	#sortColumns() {
		this.#columns = this.#columns.sort((left, right) => left.getOrder() - right.getOrder());
	}

	#duplicateColumnConfig(column, user_column = {}) {
		const id = column.getId();
		const name = this.#columns.find(column => column.getId() === id).getName();

		const duplicate_count = this.#columns
			.filter(column => column.getName().replace(/\s*\(\d+\)$/g, '') === name)
			.length;

		const defaults = column.clone()
			.setDuplicate(false)
			.setName(`${name} (${duplicate_count})`)
			.setSpan(1)
			.setVisible(user_column.visible || true);

		return defaults.clone()
			.setDuplicate(true)
			.setDefaults(defaults)
			.merge(user_column);
	}

	#getColumnMinWidth(column) {
		let min_width = CDataTable.COLUMN_INITIAL_MIN_WIDTH;

		if (column.getOptionsPopupHandler()) {
			min_width += CDataTable.COLUMN_OPTIONS_BUTTON_WIDTH + CDataTable.COLUMN_HEADER_PADDING;
		}

		if (column.isSortable()) {
			min_width += CDataTable.COLUMN_SORTABLE_ARROW_WIDTH;
		}

		if (this.#customizable && this.#visible_columns.at(-1) === column) {
			min_width += CDataTable.TABLE_OPTIONS_BUTTON_WIDTH;
		}

		return min_width;
	}

	#calculateColumnWidths(response) {
		this.#row_spacer_width = '0px';

		this.#applyColumnWidths();

		for (const column of this.#visible_columns.filter(column => column.getWidth() === 'max-content')) {
			this.#calculateColumnWidth(column);
		}

		this.#applyColumnWidths();

		if ('data' in response && response.data.length > 0) {
			for (const column of this.#visible_columns) {
				this.#calculateColumnWidth(column);
			}

			this.#applyColumnWidths();
		}

		this.#row_spacer_width = 'auto';
		this.#applyColumnWidths();
	}

	#calculateColumnWidth(column) {
		if (column.isResized() || !column.isResizable()) {
			return;
		}

		const min_width = this.#getColumnMinWidth(column);
		const header_width = column.getHeaderCell()?.target.getBoundingClientRect().width ?? 0;
		const data_width = column.getDataCells().at(0)?.target.getBoundingClientRect().width ?? 0;

		let width = Math.ceil(Math.max(min_width, header_width, data_width));

		column.setWidth(`${width}px`);
	}

	#renderHeaderCells() {
		for (const column of this.#columns) {
			const header_cell = column.getHeaderCell();

			if (header_cell) {
				header_cell.target.remove();

				column.setHeaderCell(null);
			}
		}

		if (this.#customizable) {
			this.createRowSpacer(this.#header);
			this.#createTableOptionsButton();
		}

		for (const column of this.#visible_columns) {
			const header_cell = this.#createHeaderCell(column);

			column.setHeaderCell(header_cell);

			if (this.#customizable) {
				const row_spacer = this.findRowSpacer(this.#header);

				this.#header.insertBefore(header_cell.target, row_spacer);
			}
			else {
				this.#header.appendChild(header_cell.target);
			}
		}

		if (this.#customizable) {
			this.#updateTableOptionsButtonPosition();
		}
	}

	#applyColumnWidths() {
		const column_widths = this.#visible_columns.map(column => column.getWidth());

		if (this.#customizable) {
			column_widths.push(this.#row_spacer_width);
		}

		this.#header.style.gridTemplateColumns = this.#body.style.gridTemplateColumns = column_widths.join(' ');
	}

	#createNoDataMessage({icon = undefined, message = undefined, description = undefined} = {}) {
		const no_data_message = document.createElement('div');

		no_data_message.classList.add(ZBX_STYLE_NO_DATA_MESSAGE);

		if (icon) {
			no_data_message.classList.add(icon);
		}

		no_data_message.style.gridColumn = '1 / -1';

		if (message) {
			no_data_message.textContent = message;
		}

		if (description) {
			const no_data_description = document.createElement('div');

			no_data_description.classList.add(ZBX_STYLE_NO_DATA_DESCRIPTION);
			no_data_description.textContent = description;

			no_data_message.appendChild(no_data_description);
		}

		return no_data_message;
	}

	#createTableOptionsButton() {
		if (this.#findTableOptionsButton()) {
			return;
		}

		const header_cell = this.#templates.table_options_button.evaluateToElement({title: t('Customize table')});
		const handle = header_cell.querySelector(`.${CDataTable.ZBX_STYLE_OPTIONS_LINK}`);

		handle.addEventListener('click', e => {
			e.preventDefault();

			const handler = this.#options_handlers[CDataTableColumn.TABLE_OPTIONS];
			const column = new CDataTableColumn(CDataTableColumn.TABLE_OPTIONS, '')
				.setOptionsPopupHandler(CDataTableColumn.TABLE_OPTIONS);

			this.dispatchEvent(CDataTable.EVENT_OPTIONS_POPUP, {handler, column, header_cell, handle});
		});

		this.#header.appendChild(header_cell);
	}

	#updateTableOptionsButtonPosition() {
		if (!this.#customizable) {
			return;
		}

		const table_options = this.#findTableOptionsButton();
		if (!table_options) {
			return;
		}

		table_options.style.right = `${-this.#body.scrollLeft}px`;
		table_options.classList.remove(ZBX_STYLE_HIDDEN);
	}

	#createOptionsPopup(handler, column, header_cell, handle) {
		return new handler(this, column, header_cell, handle)
			.on(
				CDataTableOptionsPopup.EVENT_OPEN,
				e => this.dispatchEvent(CDataTable.EVENT_OPTIONS_POPUP_OPEN, e.detail)
			)
			.on(
				CDataTableOptionsPopup.EVENT_CLOSE,
				e => this.dispatchEvent(CDataTable.EVENT_OPTIONS_POPUP_CLOSE, e.detail)
			).on(
				CDataTableOptionsPopup.EVENT_UPDATE,
				e => this.dispatchEvent(CDataTable.EVENT_OPTIONS_POPUP_UPDATE, e.detail)
			);
	}

	#createColumnOptionsPopup(handler, column, header_cell, handle) {
		return new handler(this, column, header_cell, handle)
			.on(
				CDataTableOptionsPopup.EVENT_OPEN,
				e => this.dispatchEvent(CDataTable.EVENT_COLUMN_OPTIONS_POPUP_OPEN, e.detail)
			)
			.on(
				CDataTableOptionsPopup.EVENT_CLOSE,
				e => this.dispatchEvent(CDataTable.EVENT_COLUMN_OPTIONS_POPUP_CLOSE, e.detail)
			).on(
				CDataTableOptionsPopup.EVENT_UPDATE,
				e => this.dispatchEvent(CDataTable.EVENT_COLUMN_OPTIONS_POPUP_UPDATE, e.detail)
			);
	}

	#renderColumnDataCells(column) {
		this.getData().then(response => {
			const data_fields = response.data_fields;

			for (const [row_index, data_cell] of column.getDataCells().entries()) {
				const [row_config, row_data] = response.data[row_index];

				if (row_config.renderer || column.isOnlyHeader()) {
					continue;
				}

				const row = data_cell.target.closest(`.${CDataTable.ZBX_STYLE_ROW}`);
				const cell_data = this.collectColumnData(column, data_fields, row_data);

				this.renderDataCellContents(column, row, row_index, data_cell, data_fields, cell_data, response);
			}
		});
	}

	#getDataProviderParams(params) {
		const column_options = this.getNonDuplicateColumns().reduce((options, column) => {
			return Object.assign(options, column.getColumnOptions());
		}, {});

		const options = Object.fromEntries(
			Object.entries(this.#options).map(([id, option]) => [id, option.checked ? 1 : 0])
		);

		const visible_columns = this.getVisibleColumns();

		return {
			columns: [...visible_columns].sort((a, b) => a.getColumnIndex() - b.getColumnIndex()),
			filter: this.#filter,
			options,
			column_options,
			page: this.getPage(),
			sort_field: this.#sort_field,
			sort_order: this.#sort_order,
			check_changes: true,
			force_load: false,
			...params
		};
	}

	onWindowResize = () => {
		this.getData().then(response => {
			for (const column of this.#visible_columns.filter(column => !column.isResized())) {
				column.resetWidth();
			}

			this.#calculateColumnWidths(response);
			this.#handleScrollbar();
		});
	}

	onWrapperScroll = () => {
		this.#options_popup?.position();
	}

	#bindEvents() {
		for (const [name, callback] of Object.entries(this.#events)) {
			this.on(name, callback);
		}

		if (this.#pager) {
			this.#pager
				.on(CPager.EVENT_SELECT, this.onPagerSelect)
				.on(CPager.EVENT_STATE_CHANGE, this.onPagerStateChange);
		}

		document.querySelector(`.${ZBX_STYLE_LAYOUT_WRAPPER}`)?.addEventListener('scroll', this.onWrapperScroll);

		if (this.#tabfilter_item._parent) {
			this.#tabfilter_item._parent.on(TABFILTER_EVENT_NEWITEM, this.onTabfilterNewItem);
			this.#tabfilter_item.on(TABFILTERITEM_EVENT_DELETE, this.onTabfilterDelete);
		}

		this.#subscriptions.push(ZABBIX.EventHub.subscribe({
			require: {
				context: EVENT_CONTEXT_OVERLAY,
				event: EVENT_UNMOUNT
			},
			callback: () => this.#initCheckBoxRange()
		}));
	}

	#unbindEvents() {
		this.#options_popup?.dispatchEvent(CDataTableOptionsPopup.EVENT_CLOSE);

		this.#body?.removeEventListener('scroll', this.onBodyScroll);

		this.#body_resize_observer?.disconnect();
		this.#body_resize_observer = null;

		if (this.#pager) {
			this.#pager
				.off(CPager.EVENT_SELECT, this.onPagerSelect)
				.off(CPager.EVENT_STATE_CHANGE, this.onPagerStateChange);
		}

		window.removeEventListener('resize', this.onWindowResize);

		document.querySelector(`.${ZBX_STYLE_LAYOUT_WRAPPER}`)?.removeEventListener('scroll', this.onWrapperScroll);

		if (this.#tabfilter_item._parent) {
			this.#tabfilter_item._parent.off(TABFILTER_EVENT_NEWITEM, this.onTabfilterNewItem);
			this.#tabfilter_item.off(TABFILTERITEM_EVENT_DELETE, this.onTabfilterDelete);
		}

		ZABBIX.EventHub.unsubscribeAll(this.#subscriptions);
		this.#subscriptions = [];

		for (const {event, callback, options} of this.#bound_events) {
			this.#element.removeEventListener(event, callback, options);
		}
		this.#bound_events = [];
	}

	onTabfilterNewItem = e => {
		const {item} = e.detail;
		const index = item._index - 1;

		this.#tabfilter_item._index = index;
		this.#user_configs[index] = this.getConfig();

		this.#updateUserProfile(JSON.stringify(this.#user_configs[index]), [index]);
	}

	onTabfilterDelete = e => {
		const {idx2} = e.detail;

		this.#updateUserProfile(JSON.stringify({}), [idx2]);
	}

	#bindScrollbarEvents() {
		this.#body_resize_observer = new ResizeObserver(() => this.#updateScrollbarInnerWidth());
		this.#body_resize_observer.observe(this.#body);

		this.#scrollbar.addEventListener('scroll', this.onScrollbarScroll);
	}

	#unbindScrollbarEvents() {
		this.#scrollbar?.removeEventListener('scroll', this.onScrollbarScroll);
	}

	#bindColumnResizeEvent(column, resizer) {
		resizer.addEventListener('pointerdown', e => {
			resizer.setPointerCapture(e.pointerId);

			this.dispatchEvent(CDataTable.EVENT_COLUMN_RESIZE_START, {
				x: e.clientX,
				column_index: column.getColumnIndex(),
				id: column.getId()
			});

			clearTimeout(this.#resize_click_timeout);
			this.#resize_click_timeout = setTimeout(() => {
				this.#resize_click_count = 0;

				clearTimeout(this.#resize_click_timeout);
			}, CDataTable.RESIZE_CLICK_COUNT_RESET_DELAY);
		});

		resizer.addEventListener('pointermove', e => {
			if (!this.#resizing) {
				return;
			}

			this.onResizePointerMove(e);
		});

		for (const type of ['pointerup', 'pointercancel', 'lostpointercapture']) {
			resizer.addEventListener(type, e => {
				if (resizer.hasPointerCapture(e.pointerId)) {
					resizer.releasePointerCapture(e.pointerId);
				}

				resizer.releasePointerCapture(e.pointerId);

				this.onResizePointerUp(e);
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

	#findClosestHeaderCell(element) {
		return element.closest(`.${CDataTable.ZBX_STYLE_CELL_HEADER}`);
	}

	findRowSpacer(target) {
		return target.querySelector(`.${CDataTable.ZBX_STYLE_ROW_SPACER}`);
	}

	#findTableOptionsButton() {
		return this.#header.querySelector(`.${CDataTable.ZBX_STYLE_OPTIONS_BUTTON}`);
	}

	/**
	 * Recalculates column spans based on visibility and span settings.
	 */
	#recalculateColumnSpans() {
		for (const column of this.#columns) {
			const defaults = column.getDefaults();

			column.merge({span: defaults.getSpan(), only_header: defaults.isOnlyHeader()});
		}

		let remaining_span = 0;

		for (const column of this.getVisibleColumns()) {
			if (remaining_span > 0) {
				column.setOnlyHeader(true);

				remaining_span--;
			}
			else {
				column.setOnlyHeader(false);

				if (column.getSpan() > 1) {
					remaining_span = column.getSpan() - 1;
				}
			}
		}
	}

	#updateScrollbarInnerWidth() {
		if (this.#scrollbar_inner) {
			this.#scrollbar_inner.style.width = `${this.#body.scrollWidth}px`;
		}
	}

	#handleScrollbar() {
		const total_column_width = this.#columns
			.filter(column => column.getHeaderCell())
			.reduce((width, column) => width + column.getHeaderCell().target.offsetWidth, 0);

		if (total_column_width - 2 <= this.#body.clientWidth) {
			this.#body_resize_observer?.disconnect();
			this.#body_resize_observer = null;

			if (this.#scrollbar) {
				this.#unbindScrollbarEvents();

				this.#scrollbar.remove();
				this.#scrollbar = null;

				this.#scrollbar_inner = null;
			}

			this.#applyLastColumnPadding();

			return;
		}

		if (this.#scrollbar) {
			this.#updateScrollbarInnerWidth();
			this.#applyLastColumnPadding();

			return;
		}

		this.#scrollbar = this.#templates.scrollbar.evaluateToElement();
		this.#scrollbar_inner = this.#scrollbar.querySelector(`.${CDataTable.ZBX_STYLE_SCROLLBAR_INNER}`);

		this.#bindScrollbarEvents();
		this.#updateScrollbarInnerWidth();
		this.#applyLastColumnPadding();

		this.#element.insertBefore(this.#scrollbar, this.#footer);
	}

	#applyLastColumnPadding() {
		if (!this.#customizable) {
			return;
		}

		const column = this.#visible_columns.at(-1);
		const header_cell = column.getHeaderCell();
		const table_options_button = this.#findTableOptionsButton();

		if (!column || !header_cell || !table_options_button) {
			return;
		}

		const header_resizer = header_cell.target.querySelector(`.${CDataTable.ZBX_STYLE_CELL_HEADER_RESIZER}`);
		const element_rect = this.#element.getBoundingClientRect();

		const right_edge = header_cell.target.getBoundingClientRect().right - element_rect.left;
		const right_boundary = element_rect.width - table_options_button.clientWidth;
		const right_offset = right_edge > right_boundary || this.#body.scrollWidth > element_rect.width
			? Math.max(0, Math.min(table_options_button.clientWidth, right_edge - right_boundary))
			: 0;

		header_cell.target.style.paddingRight = `${right_offset}px`;

		if (header_resizer) {
			header_resizer.style.marginRight = `${right_offset}px`;
		}
	}

	#scrollBodyToTarget(target) {
		if (!target) {
			return;
		}

		const {right} = target.getBoundingClientRect();
		const width = this.#body.getBoundingClientRect().width;

		if (right > width) {
			const left = this.#body.scrollLeft + right - width;

			this.#header.scrollTo({left});
			this.#body.scrollTo({left});
		}
	}

	#convertPercentToPixels(percent) {
		return Math.ceil((percent / 100) * this.#body.scrollWidth);
	}

	#getWidthWithoutUnit(width) {
		if (!width) {
			return width;
		}

		if (width.endsWith('%')) {
			return this.#convertPercentToPixels(width.substring(0, width.length - 1));
		}

		if (width.endsWith('px')) {
			return parseInt(width.substring(0, width.length - 2));
		}

		return width;
	}
}
