/*
** Copyright (C) 2001-2025 Zabbix SIA
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


class CWidgetIterator extends CWidget {

	#unique_id_index = 0;

	#ready_callback = () => {};

	onInitialize() {
		this._css_classes = {
			...this._css_classes,
			actions: 'dashboard-grid-iterator-actions',
			container: 'dashboard-grid-iterator-container',
			contents: 'dashboard-grid-iterator-contents',
			messages: 'dashboard-grid-iterator-messages',
			body: 'dashboard-grid-iterator-body',
			debug: 'dashboard-grid-iterator-debug',
			focus: 'dashboard-grid-iterator-focus',
			header: 'dashboard-grid-iterator-header',
			hidden_header: 'dashboard-grid-iterator-hidden-header',
			mask: 'dashboard-grid-iterator-mask',
			root: 'dashboard-grid-iterator'
		};

		this._widgets = new Map();
		this._widgets_data = new Map();
		this._placeholders = [];

		this._grid_pos = [];

		this._has_contents = false;
		this._has_alt_content = false;

		this._page = 1;
		this._page_count = 1;
	}

	onStart() {
		this._events = {
			...this._events,

			widgetReady: e => {
				const widget = e.detail.target;

				this._widgets_data.get(widget.getWidgetId()).is_ready = true;

				let is_ready = true;

				for (const data of this._widgets_data.values()) {
					if (!data.is_ready) {
						is_ready = false;
						break;
					}
				}

				if (is_ready) {
					this.#ready_callback();
				}
			},

			widgetEnter: e => {
				const widget = e.detail.target;

				if (!widget.isEntered()) {
					widget.enter();

					if (this._view_mode === ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER) {
						this._target.classList.toggle('iterator-double-header', widget.getPos().y === 0);
					}
				}
			},

			widgetLeave: e => {
				const widget = e.detail.target;

				if (widget.isEntered()) {
					widget.leave();
				}
			},

			iteratorEnter: e => {
				if (e.target.closest('.dashboard-grid-iterator-placeholder') !== null) {
					this._target.classList.remove('iterator-double-header');
				}
			},

			previousPageClick: () => {
				if (this._page > 1) {
					this._page--;
					this._startUpdating();
				}
			},

			nextPageClick: () => {
				if (this._page < this._page_count) {
					this._page++;
					this._startUpdating();
				}
			}
		};
	}

	onActivate() {
		this._target.addEventListener('mousemove', this._events.iteratorEnter);
		this._button_previous_page.addEventListener('click', this._events.previousPageClick);
		this._button_next_page.addEventListener('click', this._events.nextPageClick);
	}

	onDeactivate() {
		if (this._has_contents) {
			for (const widget of this._widgets.values()) {
				if (widget._state === WIDGET_STATE_ACTIVE) {
					widget.deactivate();
					this._removeWidgetEventListeners(widget);
				}
			}
		}

		this._target.removeEventListener('mousemove', this._events.iteratorEnter);
		this._button_previous_page.removeEventListener('click', this._events.previousPageClick);
		this._button_next_page.removeEventListener('click', this._events.nextPageClick);
	}

	promiseReady() {
		return new Promise(resolve => {
			const parent_ready_promise = super.promiseReady();

			this.#ready_callback = () => resolve(parent_ready_promise);

			if (this._widgets.size === 0) {
				resolve(parent_ready_promise);
			}
		});
	}

	getNumHeaderLines() {
		if (this._view_mode === ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER
				&& this._target.classList.contains('iterator-double-header')) {
			return 2;
		}

		return 1;
	}

	onResize() {
		if (this._has_contents && !this._isTooSmall()) {
			this._updatePager();
		}

		if (this._has_alt_content || this._isTooSmall() || this.isResizing()) {
			return;
		}

		for (const widget of this._widgets.values()) {
			widget.resize();
		}
	}

	_startUpdating({delay_sec = 0} = {}) {
		if (this._isTooSmall()) {
			return;
		}

		if (this.isResizing()) {
			if (this._has_contents || this._has_alt_content) {
				return;
			}
		}

		super._startUpdating({delay_sec});
	}

	setPos(pos, {is_managed = false} = {}) {
		const original_pos = {...this._pos};

		super.setPos(pos, {is_managed});

		if (this._grid_pos.length > 0
				&& this._pos.width === original_pos.width && this._pos.height === original_pos.height) {
			return;
		}

		const was_too_small = this._isTooSmall();

		this._updateTooSmallState();

		if (this._isTooSmall()) {
			if (this._state === WIDGET_STATE_ACTIVE && !was_too_small) {
				this._stopUpdating();
			}

			return;
		}

		if (this._has_alt_content) {
			return;
		}

		this._updateGridPositions();

		if (!this._has_contents) {
			if (this._state === WIDGET_STATE_ACTIVE && was_too_small) {
				this._startUpdating();
			}

			return;
		}

		const widgets = [...this._widgets.values()];

		for (let index = 0; index < this._grid_pos.length; index++) {
			if (index < this._widgets.size) {
				const widget = widgets[index];
				const widget_pos = widget.getPos();

				this._alignToGrid(widget.getView(), index);

				if (widget_pos.width !== this._grid_pos[index].width
						|| widget_pos.height !== this._grid_pos[index].height) {
					widget.setPos(this._grid_pos[index], {is_managed: true});
					widget.resize();
				}
			}
			else {
				this._alignToGrid(this._placeholders[index - this._widgets.size], index);
			}
		}

		if (this._state === WIDGET_STATE_ACTIVE) {
			this._startUpdating();
		}
	}

	_setContents(response) {
		this._updatePager(response.page, response.page_count);

		let response_widgetids = [];

		for (const data of response.children) {
			response_widgetids.push(data.widgetid);
		}

		for (const widgetid of this._widgets.keys()) {
			if (!response_widgetids.includes(widgetid)) {
				this._deleteWidget(widgetid);
			}
		}

		for (const [index, data] of Object.entries(response.children)) {
			const widget = this._widgets.has(data.widgetid) ? this._widgets.get(data.widgetid) : this._addWidget(data);

			this._alignToGrid(widget.getView(), index);
			widget.setPos(this._grid_pos[index], {is_managed: true});

			if (widget.getState() !== WIDGET_STATE_ACTIVE) {
				widget.activate();
				this._addWidgetEventListeners(widget);
			}
			else {
				this._updateWidget(widget);
			}
		}

		this._appendPlaceholders();

		this._has_contents = true;
	}

	_clearContents() {
		this._deleteWidgets();
		this._deletePlaceholders();
		this._updatePager(1, 1);

		this._has_contents = false;
	}

	_setAltContent({body = null, messages = null} = {}) {
		this._clearAltContent();

		const alt_content = document.createElement('div');

		if (messages !== null) {
			const message_box = makeMessageBox('bad', messages)[0];

			alt_content.appendChild(message_box);
		}

		if (body !== null) {
			alt_content.insertAdjacentHTML('beforeend', body);
		}

		this._body.appendChild(alt_content);
		this._target.classList.add('iterator-alt-content');

		this._has_alt_content = true;
	}

	_clearAltContent() {
		if (this._has_alt_content) {
			this._has_alt_content = false;

			this._target.classList.remove('iterator-alt-content');
			this._body.innerHTML = '';
		}
	}

	getUpdateRequestData() {
		const request_data = super.getUpdateRequestData();

		request_data.page = this._page;

		return request_data;
	}

	processUpdateResponse(response) {
		if ('name' in response) {
			this._setHeaderName(response.name);
		}

		this._updateInfo(response.info);

		if ('body' in response || 'messages' in response) {
			this._clearContents();

			this._setAltContent({
				body: response.body ?? null,
				messages: response.messages ?? null
			});
		}
		else {
			this._clearAltContent();
			this._setContents(response);
		}
	}

	processUpdateErrorResponse(error) {
		this._clearContents();

		this._setAltContent({
			messages: error.messages
		});
	}

	_addWidget(data) {
		const widget = this._createWidget(data);

		widget.on(CWidgetBase.EVENT_READY, this._events.widgetReady);
		widget.start();

		this._body.append(widget.getView());

		this._truncateWidget(widget);

		this._widgets.set(data.widgetid, widget);
		this._widgets_data.set(data.widgetid, {is_ready: false});

		return widget;
	}

	_createWidget(data) {
		return new (eval(data.defaults.js_class))({
			type: data.type,
			name: data.name,
			view_mode: this._view_mode,
			fields: data.fields,
			defaults: data.defaults,
			widgetid: data.widgetid,
			is_new: false,
			rf_rate: 0,
			dashboard: {
				templateid: null,
				dashboardid: null
			},
			dashboard_page: this._dashboard_page,
			cell_width: this._cell_width,
			cell_height: this._cell_height,
			is_editable: false,
			is_edit_mode: false,
			unique_id: this._createUniqueId()
		});
	}

	_truncateWidget(widget) {
		widget._actions.style.display = 'none';
	}

	_deleteWidget(widgetid) {
		const widget = this._widgets.get(widgetid);

		this._body.removeChild(widget.getView());

		this._removeWidgetEventListeners(widget);
		widget.off(CWidgetBase.EVENT_READY, this._events.widgetReady);
		widget.destroy();

		this._widgets.delete(widgetid);
		this._widgets_data.delete(widgetid);
	}

	_updateWidget(widget) {
		widget._startUpdating();
	}

	_deleteWidgets() {
		for (const widgetid of this._widgets.keys()) {
			this._deleteWidget(widgetid);
		}
	}

	_addWidgetEventListeners(widget) {
		widget
			.on(WIDGET_EVENT_ENTER, this._events.widgetEnter)
			.on(WIDGET_EVENT_LEAVE, this._events.widgetLeave);
	}

	_removeWidgetEventListeners(widget) {
		widget
			.off(WIDGET_EVENT_ENTER, this._events.widgetEnter)
			.off(WIDGET_EVENT_LEAVE, this._events.widgetLeave);
	}

	_deletePlaceholders() {
		for (const placeholder of this._placeholders) {
			placeholder.remove();
		}

		this._placeholders = [];
	}

	_appendPlaceholders() {
		this._deletePlaceholders();

		const placeholder = document.createElement('div');

		placeholder.appendChild(document.createElement('div'));
		placeholder.classList.add('dashboard-grid-iterator-placeholder');

		for (let index = this._widgets.size; index < this._grid_pos.length; index++) {
			const placeholder_clone = placeholder.cloneNode(true);

			this._body.appendChild(placeholder_clone);
			this._alignToGrid(placeholder_clone, index);

			this._placeholders.push(placeholder_clone);
		}
	}

	_isTooSmall() {
		return this._target.classList.contains('iterator-too-small');
	}

	_updateTooSmallState() {
		const is_too_small = this._pos.width < this._fields.columns || this._pos.height < this._fields.rows;

		this._target.classList.toggle('iterator-too-small', is_too_small);
	}

	_updateGridPositions() {
		this._grid_pos = [];

		const num_columns = this._fields.columns;
		const num_rows = this._fields.rows;

		for (let index = 0, count = num_columns * num_rows; index < count; index++) {
			const cell_column = index % num_columns;
			const cell_row = Math.floor(index / num_columns);
			const cell_width_min = Math.floor(this._pos.width / num_columns);
			const cell_height_min = Math.floor(this._pos.height / num_rows);

			const num_enlarged_columns = this._pos.width - cell_width_min * num_columns;
			const num_enlarged_rows = this._pos.height - cell_height_min * num_rows;

			this._grid_pos.push({
				x: cell_column * cell_width_min + Math.min(cell_column, num_enlarged_columns),
				y: cell_row * cell_height_min + Math.min(cell_row, num_enlarged_rows),
				width: cell_width_min + (cell_column < num_enlarged_columns ? 1 : 0),
				height: cell_height_min + (cell_row < num_enlarged_rows ? 1 : 0)
			});
		}
	}

	_alignToGrid(element, grid_index) {
		const pos = this._grid_pos[grid_index];

		element.style.left = `${pos.x / this._pos.width * 100}%`;
		element.style.top = `${pos.y * this._cell_height}px`;
		element.style.width = `${pos.width / this._pos.width * 100}%`;
		element.style.height =`${pos.height * this._cell_height}px`;
	}

	_updatePager(page = this._page, page_count = this._page_count) {
		this._page = page;
		this._page_count = page_count;

		if (this._page_count === 1) {
			this._header.classList.remove('pager-visible');

			return;
		}

		this._pager_stats.textContent = `${this._page} / ${this._page_count}`;

		this._header.classList.add('pager-visible');

		const width_available = this._header.clientWidth
			- this._pager.offsetWidth - this._actions.offsetWidth
			- parseFloat(getComputedStyle(this._header).paddingLeft)
			- parseFloat(getComputedStyle(this._header).paddingRight)
			- parseFloat(getComputedStyle(this._pager).marginLeft)
			- parseFloat(getComputedStyle(this._pager).marginRight);

		this._header.classList.toggle('pager-visible', width_available >= 0);
	}

	_createUniqueId() {
		return `${this._unique_id}-${(this.#unique_id_index++).toString(36).toUpperCase().padStart(6, '0')}`;
	}

	_makeView() {
		super._makeView();

		this._target.style.minWidth = null;
		this._target.style.minHeight = null;

		this._pager = document.createElement('div');
		this._pager.classList.add('dashboard-grid-iterator-pager');

		this._button_previous_page = document.createElement('button');
		this._button_previous_page.type = 'button';
		this._button_previous_page.title = t('Previous page');
		this._button_previous_page.classList.add(ZBX_STYLE_BTN_ICON, ZBX_ICON_CHEVRON_LEFT);
		this._pager.appendChild(this._button_previous_page);

		this._pager_stats = document.createElement('span');
		this._pager_stats.classList.add('dashboard-grid-iterator-pager-info');
		this._pager.appendChild(this._pager_stats);

		this._button_next_page = document.createElement('button');
		this._button_next_page.type = 'button';
		this._button_next_page.title = t('Next page');
		this._button_next_page.classList.add(ZBX_STYLE_BTN_ICON, ZBX_ICON_CHEVRON_RIGHT);
		this._pager.appendChild(this._button_next_page);

		this._header.insertBefore(this._pager, this._actions);

		this._too_small = document.createElement('div');
		this._too_small.classList.add('dashboard-grid-iterator-too-small');

		const too_small_content = document.createElement('div');

		too_small_content.textContent = t('Widget is too small for the specified number of columns and rows.');
		this._too_small.appendChild(too_small_content);

		this._container.appendChild(this._too_small);
	}
}
