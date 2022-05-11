/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


const DASHBOARD_PAGE_STATE_INITIAL = 'initial';
const DASHBOARD_PAGE_STATE_ACTIVE = 'active';
const DASHBOARD_PAGE_STATE_INACTIVE = 'inactive';
const DASHBOARD_PAGE_STATE_DESTROYED = 'destroyed';

const DASHBOARD_PAGE_EVENT_EDIT = 'dashboard-page-edit';
const DASHBOARD_PAGE_EVENT_WIDGET_ADD = 'dashboard-page-widget-add';
const DASHBOARD_PAGE_EVENT_WIDGET_ADD_NEW = 'dashboard-page-widget-add-new';
const DASHBOARD_PAGE_EVENT_WIDGET_DELETE = 'dashboard-page-widget-delete';
const DASHBOARD_PAGE_EVENT_WIDGET_POSITION = 'dashboard-page-widget-position';
const DASHBOARD_PAGE_EVENT_WIDGET_EDIT = 'dashboard-page-widget-edit';
const DASHBOARD_PAGE_EVENT_WIDGET_ACTIONS = 'dashboard-page-widget-actions';
const DASHBOARD_PAGE_EVENT_WIDGET_COPY = 'dashboard-page-widget-copy';
const DASHBOARD_PAGE_EVENT_WIDGET_PASTE = 'dashboard-page-widget-paste';
const DASHBOARD_PAGE_EVENT_ANNOUNCE_WIDGETS = 'dashboard-page-announce-widgets';
const DASHBOARD_PAGE_EVENT_RESERVE_HEADER_LINES = 'dashboard-page-reserve-header-lines';

class CDashboardPage extends CBaseComponent {

	constructor(target, {
		data,
		dashboard,
		cell_width,
		cell_height,
		max_columns,
		max_rows,
		widget_min_rows,
		widget_max_rows,
		widget_defaults,
		is_editable,
		is_edit_mode,
		can_edit_dashboards,
		time_period,
		dynamic_hostid,
		unique_id
	}) {
		super(document.createElement('div'));

		this._dashboard_grid = target;

		this._data = {
			dashboard_pageid: data.dashboard_pageid,
			name: data.name,
			display_period: data.display_period
		};

		this._dashboard = {
			templateid: dashboard.templateid,
			dashboardid: dashboard.dashboardid
		};

		this._cell_width = cell_width;
		this._cell_height = cell_height;
		this._max_columns = max_columns;
		this._max_rows = max_rows;
		this._widget_min_rows = widget_min_rows;
		this._widget_max_rows = widget_max_rows;
		this._widget_defaults = widget_defaults;
		this._is_editable = is_editable;
		this._is_edit_mode = is_edit_mode;
		this._can_edit_dashboards = can_edit_dashboards;
		this._time_period = time_period;
		this._dynamic_hostid = dynamic_hostid;
		this._unique_id = unique_id;

		this._init();
		this._registerEvents();
	}

	_init() {
		this._state = DASHBOARD_PAGE_STATE_INITIAL;

		this._widgets = new Map();

		this._original_properties = {
			name: this._data.name,
			display_period: this._data.display_period
		};

		this._grid_min_rows = 0;
		this._grid_pad_rows = 2;

		this._is_unsaved = false;

		if (this._is_edit_mode) {
			this._initWidgetDragging();
			this._initWidgetResizing();
		}

		this._initWidgetPlaceholder();
	}

	// Logical state control methods.

	getState() {
		return this._state;
	}

	start() {
		this._state = DASHBOARD_PAGE_STATE_INACTIVE;

		for (const widget of this._widgets.keys()) {
			widget.start();
		}
	}

	activate() {
		this._state = DASHBOARD_PAGE_STATE_ACTIVE;

		this._resizeGrid();
		this._activateEvents();

		for (const widget of this._widgets.keys()) {
			this._dashboard_grid.appendChild(widget.getView());
			this._activateWidget(widget);
		}

		if (this._is_edit_mode) {
			this._activateWidgetDragging();
			this._activateWidgetResizing();
		}

		this.resetWidgetPlaceholder();
	}

	_activateWidget(widget) {
		widget.activate();
		widget
			.on(WIDGET_EVENT_ACTIONS, this._events.widgetActions)
			.on(WIDGET_EVENT_EDIT, this._events.widgetEdit)
			.on(WIDGET_EVENT_ENTER, this._events.widgetEnter)
			.on(WIDGET_EVENT_LEAVE, this._events.widgetLeave)
			.on(WIDGET_EVENT_COPY, this._events.widgetCopy)
			.on(WIDGET_EVENT_PASTE, this._events.widgetPaste)
			.on(WIDGET_EVENT_DELETE, this._events.widgetDelete);
	}

	deactivate() {
		this._state = DASHBOARD_PAGE_STATE_INACTIVE;

		for (const widget of this._widgets.keys()) {
			this._dashboard_grid.removeChild(widget.getView());
			this._deactivateWidget(widget);
		}

		this._deactivateEvents();
		this._deactivateWidgetPlaceholder();

		if (this._is_edit_mode) {
			this._deactivateWidgetDragging();
			this._deactivateWidgetResizing();
		}
	}

	_deactivateWidget(widget) {
		widget.deactivate();
		widget
			.off(WIDGET_EVENT_ACTIONS, this._events.widgetActions)
			.off(WIDGET_EVENT_EDIT, this._events.widgetEdit)
			.off(WIDGET_EVENT_ENTER, this._events.widgetEnter)
			.off(WIDGET_EVENT_LEAVE, this._events.widgetLeave)
			.off(WIDGET_EVENT_COPY, this._events.widgetCopy)
			.off(WIDGET_EVENT_PASTE, this._events.widgetPaste)
			.off(WIDGET_EVENT_DELETE, this._events.widgetDelete);
	}

	destroy() {
		if (this._state === DASHBOARD_PAGE_STATE_ACTIVE) {
			this.deactivate();
		}

		if (this._state !== DASHBOARD_PAGE_STATE_INACTIVE) {
			throw new Error('Unsupported state change.');
		}

		this._state = DASHBOARD_PAGE_STATE_DESTROYED;

		for (const widget of this._widgets.keys()) {
			widget.destroy();
		}

		this._widgets.clear();
	}

	// External events management methods.

	isEditMode() {
		return this._is_edit_mode;
	}

	setEditMode() {
		this._is_edit_mode = true;

		for (const widget of this._widgets.keys()) {
			widget.setEditMode();
		}

		this._initWidgetDragging();
		this._initWidgetResizing();

		if (this._state === DASHBOARD_PAGE_STATE_ACTIVE) {
			this._resizeGrid();

			this._activateWidgetDragging();
			this._activateWidgetResizing();
			this.resetWidgetPlaceholder();
		}
	}

	setDynamicHost(dynamic_hostid) {
		if (this._dynamic_hostid != dynamic_hostid) {
			this._dynamic_hostid = dynamic_hostid;

			for (const widget of this._widgets.keys()) {
				if (widget.supportsDynamicHosts() && this._dynamic_hostid != widget.getDynamicHost()) {
					widget.setDynamicHost(this._dynamic_hostid);
				}
			}
		}
	}

	setTimePeriod(time_period) {
		this._time_period = time_period;

		for (const widget of this._widgets.keys()) {
			widget.setTimePeriod(this._time_period);
		}
	}

	isUserInteracting() {
		for (const widget of this._widgets.keys()) {
			if (widget.isUserInteracting()) {
				return true;
			}
		}

		return false;
	}

	announceWidgets(dashboard_pages) {
		let widgets = [];

		for (const dashboard_page of dashboard_pages) {
			widgets = widgets.concat(Array.from(dashboard_page._widgets.keys()));
		}

		for (const widget of widgets) {
			widget.announceWidgets(widgets);
		}
	}

	isUnsaved() {
		if (this._is_unsaved) {
			return true;
		}

		for (const [name, value] of Object.entries(this._original_properties)) {
			if (value != this._data[name]) {
				return true;
			}
		}

		return false;
	}

	// Data interface methods.

	getUniqueId() {
		return this._unique_id;
	}

	getDashboardPageId() {
		return this._data.dashboard_pageid;
	}

	getName() {
		return this._data.name;
	}

	setName(name) {
		this._data.name = name;
	}

	getDisplayPeriod() {
		return this._data.display_period;
	}

	setDisplayPeriod(display_period) {
		this._data.display_period = display_period;
	}

	getWidgets() {
		return [...this._widgets.keys()];
	}

	getWidget(unique_id) {
		for (const widget of this._widgets.keys()) {
			if (widget.getUniqueId() === unique_id) {
				return widget;
			}
		}

		return null;
	}

	addWidget({type, name, view_mode, fields, configuration, widgetid, pos, is_new, rf_rate, unique_id}) {
		const widget = this._createWidget(this._widget_defaults[type].js_class, {
			type,
			name,
			view_mode,
			fields,
			configuration,
			widgetid,
			pos,
			is_new,
			rf_rate,
			unique_id
		});

		this._doAddWidget(widget);

		if (widgetid === null) {
			this._is_unsaved = true;
		}

		return widget;
	}

	addPastePlaceholderWidget({type, name, view_mode, pos, unique_id}) {
		const paste_placeholder_widget = this._createPastePlaceholderWidget({type, name, view_mode, pos, unique_id});

		this._doAddWidget(paste_placeholder_widget);

		return paste_placeholder_widget;
	}

	_doAddWidget(widget) {
		this._widgets.set(widget, {});

		if (!this._isHelperWidget(widget)) {
			this.fire(DASHBOARD_PAGE_EVENT_ANNOUNCE_WIDGETS);
		}

		if (this._state !== DASHBOARD_PAGE_STATE_INITIAL) {
			widget.start();
		}

		if (this._state === DASHBOARD_PAGE_STATE_ACTIVE) {
			const pos = widget.getPos();

			this._resizeGrid(pos.y + pos.height + this._grid_pad_rows);

			this._dashboard_grid.appendChild(widget.getView());
			this._activateWidget(widget);
		}
	}

	deleteWidget(widget, {is_batch_mode = false} = {}) {
		if (widget.getState() === WIDGET_STATE_ACTIVE) {
			this._dashboard_grid.removeChild(widget.getView());
			this._deactivateWidget(widget);
		}

		if (widget.getState() !== WIDGET_STATE_INITIAL) {
			widget.destroy();
		}

		this._widgets.delete(widget);

		if (!is_batch_mode) {
			if (!this._isHelperWidget(widget)) {
				this.fire(DASHBOARD_PAGE_EVENT_ANNOUNCE_WIDGETS);
			}

			this._resizeGrid();
		}

		this._is_unsaved = true;
	}

	replaceWidget(widget, widget_data) {
		this._is_unsaved = true;

		this.deleteWidget(widget, {is_batch_mode: true});

		return this.addWidget(widget_data);
	}

	_createWidget(js_class, {type, name, view_mode, fields, configuration, widgetid, pos, is_new, rf_rate, unique_id}) {
		return new (eval(js_class))({
			type,
			name,
			view_mode,
			fields,
			configuration,
			defaults: this._widget_defaults[type],
			widgetid,
			pos,
			is_new,
			rf_rate,
			dashboard: {
				templateid: this._dashboard.templateid,
				dashboardid: this._dashboard.dashboardid
			},
			dashboard_page: {
				unique_id: this._unique_id
			},
			cell_width: this._cell_width,
			cell_height: this._cell_height,
			min_rows: this._widget_min_rows,
			is_editable: this._is_editable,
			is_edit_mode: this._is_edit_mode,
			can_edit_dashboards: this._can_edit_dashboards,
			time_period: this._time_period,
			dynamic_hostid: this._dynamic_hostid,
			scope_id: this._unique_id,
			unique_id
		});
	}

	_createPastePlaceholderWidget({type, name, view_mode, pos, unique_id}) {
		return this._createWidget('CWidgetPastePlaceholder', {
			type,
			name,
			view_mode,
			fields: {},
			configuration: {},
			widgetid: null,
			pos,
			is_new: false,
			rf_rate: 0,
			unique_id
		});
	}

	_isHelperWidget(widget) {
		return widget instanceof CWidgetPastePlaceholder;
	}

	getDataCopy() {
		const data = {
			name: this._data.name,
			display_period: this._data.display_period,
			widgets: [],
			dashboard: {
				templateid: this._dashboard.templateid
			}
		};

		for (const widget of this._widgets.keys()) {
			if (!this._isHelperWidget(widget)) {
				data.widgets.push(widget.getDataCopy({is_single_copy: false}));
			}
		}

		return data;
	}

	save() {
		const data = {
			dashboard_pageid: this._data.dashboard_pageid ?? undefined,
			name: this._data.name,
			display_period: this._data.display_period,
			widgets: []
		};

		for (const widget of this._widgets.keys()) {
			if (!this._isHelperWidget(widget)) {
				data.widgets.push(widget.save());
			}
		}

		return data;
	}

	// Dashboard page view methods.

	findFreePos({width, height}) {
		const pos = {x: 0, y: 0, width: width, height: height};

		const max_column = this._max_columns - pos.width;
		const max_row = this._max_rows - pos.height;

		let found = false;
		let x, y;

		for (y = 0; !found; y++) {
			if (y > max_row) {
				return null;
			}
			for (x = 0; x <= max_column && !found; x++) {
				pos.x = x;
				pos.y = y;
				found = this._isPosFree(pos);
			}
		}

		return pos;
	}

	accommodatePos(pos, {reverse_x = false, reverse_y = false} = {}) {
		let pos_variants = [];

		let pos_x = this._accommodatePosX({
			...pos,
			y: reverse_y ? pos.y + pos.height - this._widget_min_rows : pos.y,
			height: this._widget_min_rows
		}, {reverse: reverse_x});

		pos_x = {...pos_x, y: pos.y, height: pos.height};

		if (reverse_x) {
			for (let x = pos_x.x, width = pos_x.width; width >= 1; x++, width--) {
				pos_variants.push(this._accommodatePosY({...pos_x, x, width}, {reverse: reverse_y}));
			}
		}
		else {
			for (let width = pos_x.width; width >= 1; width--) {
				pos_variants.push(this._accommodatePosY({...pos_x, width}, {reverse: reverse_y}));
			}
		}

		let pos_best = null;
		let pos_best_value = null;

		for (const pos_variant of pos_variants) {
			const delta_x = Math.abs(reverse_x ? pos_variant.x - pos.x : pos_variant.width - pos.width);
			const delta_y = Math.abs(reverse_y ? pos_variant.y - pos.y : pos_variant.height - pos.height);
			const value = Math.sqrt(Math.pow(delta_x, 2) + Math.pow(delta_y, 2));

			if (pos_best === null
					|| (pos_best.width == 1 && pos_variant.width > 1)
					|| ((pos_best.width > 1 === pos_variant.width > 1) && value < pos_best_value)) {
				pos_best = {...pos_variant};
				pos_best_value = value;
			}
		}

		return pos_best;
	}

	_accommodatePosX(pos, {reverse = false} = {}) {
		const max_pos = {...pos};

		if (reverse) {
			for (let x = pos.x + pos.width - 1, width = 1; x >= pos.x; x--, width++) {
				if (!this._isPosFree({...max_pos, x, width})) {
					break;
				}

				max_pos.x = x;
				max_pos.width = width;
			}
		}
		else {
			for (let width = 1; width <= pos.width; width++) {
				if (!this._isPosFree({...max_pos, width})) {
					break;
				}

				max_pos.width = width;
			}
		}

		return max_pos;
	}

	_accommodatePosY(pos, {reverse = false} = {}) {
		const max_pos = {...pos};

		if (reverse) {
			for (let y = pos.y + pos.height - 1, height = 1; y >= pos.y; y--, height++) {
				if (!this._isPosFree({...max_pos, y, height})) {
					break;
				}

				max_pos.y = y;
				max_pos.height = height;
			}
		}
		else {
			for (let height = this._widget_min_rows; height <= pos.height; height++) {
				if (!this._isPosFree({...max_pos, height})) {
					break;
				}

				max_pos.height = height;
			}
		}

		return max_pos;
	}

	promiseScrollIntoView(pos) {
		const wrapper = document.querySelector('.wrapper');
		const wrapper_offset_top = wrapper.scrollTop + this._dashboard_grid.getBoundingClientRect().y;

		const margin = 5;

		const pos_top = wrapper_offset_top + pos.y * this._cell_height - margin;
		const pos_height = pos.height * this._cell_height + margin * 2;

		const wrapper_scroll_top_min = Math.max(0, pos_top + Math.min(0, pos_height - wrapper.clientHeight));
		const wrapper_scroll_top_max = pos_top;

		this._resizeGrid(pos.y + pos.height + this._grid_pad_rows);

		return new Promise((resolve) => {
			let scroll_to = null;

			if (wrapper.scrollTop < wrapper_scroll_top_min) {
				scroll_to = wrapper_scroll_top_min;
			}
			else if (wrapper.scrollTop > wrapper_scroll_top_max) {
				scroll_to = wrapper_scroll_top_max;
			}
			else {
				return resolve();
			}

			const start_scroll = wrapper.scrollTop;
			const start_time = Date.now();
			const end_time = start_time + 300;

			const animate = () => {
				const time = Date.now();

				if (time <= end_time) {
					const progress = (time - start_time) / (end_time - start_time);
					const smooth_progress = 0.5 + Math.sin(Math.PI * (progress - 0.5)) / 2;

					wrapper.scrollTop = parseFloat(start_scroll + (scroll_to - start_scroll) * smooth_progress);

					requestAnimationFrame(animate);
				}
				else {
					wrapper.scrollTop = scroll_to;

					resolve();
				}
			};

			requestAnimationFrame(animate);
		});
	}

	_isPosEqual(pos_1, pos_2) {
		return (pos_1.x == pos_2.x && pos_1.y == pos_2.y && pos_1.width == pos_2.width && pos_1.height == pos_2.height);
	}

	_isPosOverlapping(pos_1, pos_2) {
		return (
			pos_1.x < (pos_2.x + pos_2.width) && (pos_1.x + pos_1.width) > pos_2.x
				&& pos_1.y < (pos_2.y + pos_2.height) && (pos_1.y + pos_1.height) > pos_2.y
		);
	}

	_isPosFree(pos) {
		for (const widget of this._widgets.keys()) {
			if (this._isPosOverlapping(pos, widget.getPos())) {
				return false;
			}
		}

		return true;
	}

	_isDataPosFree(pos, {except_widgets = null} = {}) {
		for (const [widget, data] of this._widgets) {
			if (except_widgets !== null && except_widgets.has(widget)) {
				continue;
			}

			if (this._isPosOverlapping(data.pos, pos)) {
				return false;
			}
		}

		return true;
	}

	_resizeGrid(min_rows = null) {
		if (min_rows == 0) {
			this._grid_min_rows = 0;
		}
		else if (min_rows !== null) {
			this._grid_min_rows = Math.max(this._grid_min_rows, Math.min(this._max_rows, min_rows));
		}

		let num_rows = Math.max(this._grid_min_rows, this._getNumOccupiedRows());

		let height = this._cell_height * num_rows;

		if (this._is_edit_mode) {
			let min_height = window.innerHeight - document.querySelector('.wrapper > footer').clientHeight
				- this._dashboard_grid.getBoundingClientRect().y;

			let element = this._dashboard_grid;

			do {
				min_height -= parseFloat(getComputedStyle(element).paddingBottom);
				element = element.parentElement;
			}
			while (!element.classList.contains('wrapper'));

			height = Math.max(height, min_height);
		}

		this._dashboard_grid.style.height = `${height}px`;
	}

	_getNumOccupiedRows() {
		let num_rows = 0;

		for (const widget of this._widgets.keys()) {
			const pos = widget.getPos();

			num_rows = Math.max(num_rows, pos.y + pos.height);
		}

		return num_rows;
	}

	_leaveWidgets({except_widget = null} = {}) {
		for (const widget of this._widgets.keys()) {
			if (widget !== except_widget) {
				widget.leave();
			}
		}
	}

	blockInteraction() {
		if (this._dashboard_grid.querySelector('.dashboard-grid-widget-blocker') !== null) {
			return;
		}

		const widget_blocker = document.createElement('div');

		widget_blocker.classList.add('dashboard-grid-widget-blocker');
		this._dashboard_grid.prepend(widget_blocker);
	}

	unblockInteraction() {
		const widget_blocker = this._dashboard_grid.querySelector('.dashboard-grid-widget-blocker');

		if (widget_blocker !== null) {
			widget_blocker.remove();
		}
	}

	// Widget placeholder methods.

	_initWidgetPlaceholder() {
		this._widget_placeholder = new CDashboardWidgetPlaceholder(this._cell_width, this._cell_height);
		this._widget_placeholder_pos = null;
		this._widget_placeholder_clicked_pos = null;
		this._widget_placeholder_is_active = false;
		this._widget_placeholder_is_edit_mode = null;
		this._widget_placeholder_move_animation_frame = null;

		this._dashboard_grid.appendChild(this._widget_placeholder.getNode());

		const getGridEventPos = (e, {width, height}) => {
			const rect = this._dashboard_grid.getBoundingClientRect();
			const x = Math.round((e.pageX - rect.x) / rect.width * this._max_columns - width / 2);
			const y = Math.round((e.pageY - rect.y) / this._cell_height - height / 2);

			return {
				x: Math.max(0, Math.min(this._max_columns - width, x)),
				y: Math.max(0, Math.min(this._max_rows - height, y)),
				width: width,
				height: height
			}
		};

		const move = (e) => {
			if (this._widget_placeholder_clicked_pos !== null) {
				let event_pos = getGridEventPos(e, {width: 1, height: 1});
				let reverse_x = false;
				let reverse_y = false;

				const delta_x = event_pos.x - this._widget_placeholder_clicked_pos.x;

				this._widget_placeholder_pos = {};

				if (this._widget_placeholder_clicked_pos.width == 2) {
					if (delta_x <= 0) {
						this._widget_placeholder_clicked_pos.width = 1;
					}
					else if (delta_x >= 1) {
						this._widget_placeholder_clicked_pos.x++;
						this._widget_placeholder_clicked_pos.width = 1;
					}
				}

				if (delta_x > 0) {
					this._widget_placeholder_pos.x = this._widget_placeholder_clicked_pos.x;
					this._widget_placeholder_pos.width = Math.max(
						this._widget_placeholder_clicked_pos.width,
						event_pos.x - this._widget_placeholder_clicked_pos.x + 1
					);
				}
				else {
					this._widget_placeholder_pos.x = event_pos.x;
					this._widget_placeholder_pos.width = this._widget_placeholder_clicked_pos.x
						- event_pos.x + this._widget_placeholder_clicked_pos.width;
					reverse_x = true;
				}

				if (event_pos.y >= this._widget_placeholder_clicked_pos.y) {
					this._widget_placeholder_pos.y = this._widget_placeholder_clicked_pos.y;
					this._widget_placeholder_pos.height = Math.max(
						this._widget_placeholder_clicked_pos.height,
						event_pos.y - this._widget_placeholder_clicked_pos.y + 1
					);
					this._widget_placeholder_pos.height = Math.min(this._widget_max_rows,
						this._widget_placeholder_pos.height
					);
				}
				else {
					this._widget_placeholder_pos.y = event_pos.y;
					this._widget_placeholder_pos.height = this._widget_placeholder_clicked_pos.y
						- event_pos.y + this._widget_placeholder_clicked_pos.height;
					reverse_y = true;

					const delta_y = this._widget_placeholder_pos.height - this._widget_max_rows;

					if (delta_y > 0) {
						this._widget_placeholder_pos.y += delta_y;
						this._widget_placeholder_pos.height -= delta_y;
					}
				}

				this._widget_placeholder_pos = this.accommodatePos(
					this._widget_placeholder_pos, {reverse_x, reverse_y}
				);

				this._resizeGrid(this._widget_placeholder_pos.y + this._widget_placeholder_pos.height
					+ this._grid_pad_rows
				);

				this._widget_placeholder
					.setState(WIDGET_PLACEHOLDER_STATE_RESIZING)
					.showAtPosition(this._widget_placeholder_pos);
			}
			else {
				if (this._widget_placeholder_pos !== null && this._widget_placeholder.getNode().contains(e.target)) {
					return;
				}

				if (e.target !== this._dashboard_grid) {
					this._widget_placeholder.hide();

					return;
				}

				this._widget_placeholder_pos = null;

				const event_pos_1x1 = getGridEventPos(e, {width: 1, height: 1});

				if (this._isPosFree(event_pos_1x1)) {
					let event_pos = getGridEventPos(e, {width: 2, height: this._widget_min_rows});

					for (const width of [2, 1]) {
						for (const offset_y of [0, -1, 1]) {
							for (const offset_x of [0, -1, 1]) {
								const pos = {
									x: event_pos.x + offset_x,
									y: event_pos.y + offset_y,
									width: width,
									height: this._widget_min_rows
								};

								if (pos.x < 0 || pos.x + pos.width > this._max_columns
										|| pos.y < 0 || pos.y + pos.height > this._max_rows) {
									continue;
								}

								if (this._isPosOverlapping(pos, event_pos_1x1) && this._isPosFree(pos)) {
									this._widget_placeholder_pos = pos;
									break;
								}
							}

							if (this._widget_placeholder_pos !== null) {
								break;
							}
						}

						if (this._widget_placeholder_pos !== null) {
							break;
						}
					}
				}

				if (this._widget_placeholder_pos !== null) {
					this._resizeGrid(this._widget_placeholder_pos.y + this._widget_placeholder_pos.height
						+ this._grid_pad_rows
					);

					this._widget_placeholder
						.setState(WIDGET_PLACEHOLDER_STATE_POSITIONING)
						.showAtPosition(this._widget_placeholder_pos);

					this._leaveWidgets();
				}
				else {
					this._widget_placeholder.hide();
				}
			}
		};

		this._widget_placeholder_events = {
			addNewWidget: () => {
				if (!this._is_edit_mode) {
					this.setEditMode();
					this.fire(DASHBOARD_PAGE_EVENT_EDIT);
				}

				this.fire(DASHBOARD_PAGE_EVENT_WIDGET_ADD_NEW);
			},

			mouseDown: (e) => {
				if (e.button != 0) {
					return;
				}

				if (this._widget_placeholder_pos === null) {
					return;
				}

				e.preventDefault();

				this.blockInteraction();

				this._widget_placeholder_clicked_pos = this._widget_placeholder_pos;

				this._widget_placeholder
					.setState(WIDGET_PLACEHOLDER_STATE_RESIZING)
					.showAtPosition(this._widget_placeholder_clicked_pos);

				document.addEventListener('mouseup', this._widget_placeholder_events.mouseUp);
				document.addEventListener('mousemove', this._widget_placeholder_events.mouseMove);
				this._dashboard_grid.removeEventListener('mousemove', this._widget_placeholder_events.mouseMove);
			},

			mouseUp: (e) => {
				this._deactivateWidgetPlaceholder({do_hide: false});

				this.unblockInteraction();

				const new_widget_pos = {...this._widget_placeholder_pos};

				if (new_widget_pos.width == 2 && new_widget_pos.height == this._widget_min_rows) {
					delete new_widget_pos.width;
					delete new_widget_pos.height;
				}

				this.fire(DASHBOARD_PAGE_EVENT_WIDGET_ADD, {
					placeholder: this._widget_placeholder.getNode(),
					new_widget_pos,
					mouse_event: e
				});
			},

			mouseMove: (e) => {
				if (this._widget_placeholder_move_animation_frame !== null) {
					cancelAnimationFrame(this._widget_placeholder_move_animation_frame);
				}

				this._widget_placeholder_move_animation_frame = requestAnimationFrame(() => {
					this._widget_placeholder_move_animation_frame = null;
					move(e);
				});
			},

			mouseLeave: () => {
				if (this._widget_placeholder_move_animation_frame !== null) {
					cancelAnimationFrame(this._widget_placeholder_move_animation_frame);
					this._widget_placeholder_move_animation_frame = null;
				}

				if (this._widget_placeholder_clicked_pos === null) {
					this.resetWidgetPlaceholder();
				}
			},

			scroll: (e) => {
				if (e.target.scrollTop == 0) {
					this._resizeGrid(0);
				}
			}
		}
	}

	resetWidgetPlaceholder() {
		if (this._widget_placeholder_is_active && this._widget_placeholder_is_edit_mode != this._is_edit_mode) {
			this._deactivateWidgetPlaceholder();
		}

		if (!this._widget_placeholder_is_active) {
			this._activateWidgetPlaceholder();
		}

		this._widget_placeholder_pos = null;
		this._widget_placeholder_clicked_pos = null;

		if (this._is_editable && this._widgets.size == 0) {
			this._widget_placeholder
				.setState(WIDGET_PLACEHOLDER_STATE_ADD_NEW)
				.showAtDefaultPosition();
		}
		else {
			this._widget_placeholder.hide();
		}
	}

	_activateWidgetPlaceholder() {
		this._widget_placeholder.on(WIDGET_PLACEHOLDER_EVENT_ADD_NEW_WIDGET,
			this._widget_placeholder_events.addNewWidget
		);

		if (this._is_edit_mode) {
			this._widget_placeholder.on('mousedown', this._widget_placeholder_events.mouseDown);

			this._dashboard_grid.addEventListener('mousemove', this._widget_placeholder_events.mouseMove);
			this._dashboard_grid.addEventListener('mouseleave', this._widget_placeholder_events.mouseLeave);

			document.querySelector('.wrapper').addEventListener('scroll', this._widget_placeholder_events.scroll);
		}

		this._widget_placeholder_is_active = true;
		this._widget_placeholder_is_edit_mode = this._is_edit_mode;
	}

	_deactivateWidgetPlaceholder({do_hide = true} = {}) {
		this._widget_placeholder.off(WIDGET_PLACEHOLDER_EVENT_ADD_NEW_WIDGET,
			this._widget_placeholder_events.addNewWidget
		);

		if (this._widget_placeholder_is_edit_mode) {
			if (this._widget_placeholder_move_animation_frame !== null) {
				cancelAnimationFrame(this._widget_placeholder_move_animation_frame);
				this._widget_placeholder_move_animation_frame = null;
			}

			this._widget_placeholder.off('mousedown', this._widget_placeholder_events.mouseDown);

			this._dashboard_grid.removeEventListener('mousemove', this._widget_placeholder_events.mouseMove);
			this._dashboard_grid.removeEventListener('mouseleave', this._widget_placeholder_events.mouseLeave);

			document.querySelector('.wrapper').removeEventListener('scroll', this._widget_placeholder_events.scroll);

			document.removeEventListener('mousemove', this._widget_placeholder_events.mouseMove);
			document.removeEventListener('mouseup', this._widget_placeholder_events.mouseUp);
		}

		if (do_hide) {
			this._widget_placeholder.hide();
		}

		this._widget_placeholder_is_active = false;
		this._widget_placeholder_is_edit_mode = null;
	}

	// Widget dragging methods.

	_initWidgetDragging() {
		const widget_helper = document.createElement('div');

		widget_helper.classList.add('dashboard-grid-widget-placeholder');
		widget_helper.append(document.createElement('div'));

		let move_animation_frame = null;
		let drag_widget = null;
		let drag_pos = null;
		let drag_rel_x = null;
		let drag_rel_y = null;

		const getGridPos = ({x, y, width, height}) => {
			const rect = this._dashboard_grid.getBoundingClientRect();

			const pos_x = parseInt(x / rect.width * this._max_columns + 0.5);
			const pos_y = parseInt(y / this._cell_height + 0.5);

			return {
				x: Math.max(0, Math.min(this._max_columns - width, pos_x)),
				y: Math.max(0, Math.min(this._max_rows - height, pos_y)),
				width,
				height
			};
		};

		const showWidgetHelper = (pos) => {
			if (widget_helper.parentNode === null) {
				this._dashboard_grid.prepend(widget_helper);
			}

			widget_helper.style.left = `${this._cell_width * pos.x}%`;
			widget_helper.style.top = `${this._cell_height * pos.y}px`;
			widget_helper.style.width = `${this._cell_width * pos.width}%`;
			widget_helper.style.height = `${this._cell_height * pos.height}px`;
		};

		const hideWidgetHelper = () => {
			widget_helper.remove();
		};

		const pullWidgetsUp = (widgets, max_delta) => {
			do {
				let widgets_below = [];

				for (const widget of widgets) {
					const data = this._widgets.get(widget);

					for (let y = Math.max(0, data.pos.y - max_delta); y < data.pos.y; y++) {
						const test_pos = {...data.pos, y};

						if (this._isDataPosFree(test_pos, {except_widgets: new Set([drag_widget, widget])})) {
							for (const [w, w_data] of this._widgets) {
								if (w === widget || w === drag_widget) {
									continue;
								}

								if (this._isPosOverlapping(w_data.pos, {...data.pos, height: data.pos.height + 1})) {
									widgets_below.push(w);
								}
							}

							data.pos = test_pos;
							break;
						}
					}
				}

				widgets = widgets_below;
			}
			while (widgets.length > 0);
		};

		const relocateWidget = (widget, pos) => {
			if (pos.y + pos.height > this._max_rows) {
				return false;
			}

			for (const [w, data] of this._widgets) {
				if (w === widget || w === drag_widget) {
					continue;
				}

				if (this._isPosOverlapping(data.pos, pos)) {
					const test_pos = {...data.pos, y: pos.y + pos.height};

					if (!relocateWidget(w, test_pos)) {
						return false;
					}

					data.pos = test_pos;
				}
			}

			return true;
		};

		const allocatePos = (widget, pos) => {
			for (const [w, w_data] of this._widgets) {
				w_data.pos = w_data.original_pos;
			}

			const data = this._widgets.get(widget);
			const original_pos = data.original_pos;

			let widgets_below = [];

			for (const [w, w_data] of this._widgets) {
				if (w === widget || w === drag_widget) {
					continue;
				}

				if (this._isPosOverlapping(w_data.pos, {...original_pos, height: original_pos.height + 1})) {
					widgets_below.push(w);
				}
			}

			pullWidgetsUp(widgets_below, original_pos.height);

			const result = relocateWidget(widget, pos);

			for (const [w, w_data] of this._widgets) {
				if (result && w !== widget) {
					w.setPos(w_data.pos);
				}

				delete w_data.pos;
			}

			return result;
		};

		const move = (x, y) => {
			const grid_rect = this._dashboard_grid.getBoundingClientRect();

			const widget_pos = drag_widget.getPos();
			const widget_view = drag_widget.getView();
			const widget_view_rect = widget_view.getBoundingClientRect();

			const pos_left = Math.max(0, Math.min(grid_rect.width - widget_view_rect.width, x + drag_rel_x));
			const pos_top = Math.max(0, Math.min(grid_rect.height - widget_view_rect.height,
				y + drag_rel_y + document.querySelector('.wrapper').scrollTop
			));

			widget_view.style.left = `${pos_left}px`;
			widget_view.style.top = `${pos_top}px`;

			const pos = getGridPos({
				x: pos_left,
				y: pos_top,
				width: widget_pos.width,
				height: widget_pos.height
			});

			if (!this._isPosEqual(pos, drag_pos)) {
				if (allocatePos(drag_widget, pos)) {
					drag_pos = pos;

					drag_widget.setPos(drag_pos, {is_managed: true});
					showWidgetHelper(drag_pos);

					this._resizeGrid(drag_pos.y + drag_pos.height + this._grid_pad_rows);
				}
			}
		};

		this._widget_dragging_events = {
			mouseDown: (e) => {
				if (e.button != 0) {
					return;
				}

				drag_widget = null;

				for (const widget of this._widgets.keys()) {
					const widget_view = widget.getView();

					if (widget_view.querySelector(`.${widget.getCssClass('head')}`).contains(e.target)
							&& !widget_view.querySelector(`.${widget.getCssClass('actions')}`).contains(e.target)) {
						drag_widget = widget;
						break;
					}
				}

				if (drag_widget === null) {
					return;
				}

				e.preventDefault();

				this.blockInteraction();
				this._deactivateWidgetPlaceholder();

				for (const [widget, data] of this._widgets) {
					data.original_pos = widget.getPos();
				}

				drag_widget.setDragging(true);
				drag_pos = drag_widget.getPos();

				const widget_view = drag_widget.getView();
				const widget_view_computed_style = getComputedStyle(widget_view);

				drag_rel_x = parseFloat(widget_view_computed_style.left) - e.pageX;
				drag_rel_y = parseFloat(widget_view_computed_style.top) - e.pageY
					- document.querySelector('.wrapper').scrollTop;

				document.addEventListener('mouseup', this._widget_dragging_events.mouseUp, {passive: false});
				document.addEventListener('mousemove', this._widget_dragging_events.mouseMove);

				this._is_unsaved = true;

				this.fire(DASHBOARD_PAGE_EVENT_WIDGET_POSITION);
			},

			mouseUp: (e) => {
				if (move_animation_frame !== null) {
					cancelAnimationFrame(move_animation_frame);
				}

				drag_widget.setDragging(false);
				drag_widget.setPos(drag_pos);
				hideWidgetHelper();

				move_animation_frame = null;
				drag_widget = null;
				drag_pos = null;
				drag_rel_x = null;
				drag_rel_y = null;

				for (const data of this._widgets.values()) {
					delete data.original_position;
				}

				document.removeEventListener('mouseup', this._widget_dragging_events.mouseUp);
				document.removeEventListener('mousemove', this._widget_dragging_events.mouseMove);

				this.unblockInteraction();
				this.resetWidgetPlaceholder();
			},

			mouseMove: (e) => {
				if (move_animation_frame !== null) {
					cancelAnimationFrame(move_animation_frame);
				}

				move_animation_frame = requestAnimationFrame(() => {
					move_animation_frame = null;
					move(e.pageX, e.pageY);
				});
			}
		};
	}

	_activateWidgetDragging() {
		this._dashboard_grid.addEventListener('mousedown', this._widget_dragging_events.mouseDown, {passive: false});
	}

	_deactivateWidgetDragging() {
		this._dashboard_grid.removeEventListener('mousedown', this._widget_dragging_events.mouseDown);

		document.removeEventListener('mouseup', this._widget_dragging_events.mouseUp);
		document.removeEventListener('mousemove', this._widget_dragging_events.mouseMove);
	}

	// Widget resizing methods.

	_initWidgetResizing() {
		let move_animation_frame = null;
		let grid_rect = null;
		let grid_cell_width = null;
		let resize_widget = null;
		let resize_sides = null;
		let resize_pos = null;
		let resize_pos_tested = null;
		let resize_dim = null;
		let resize_rel_x = null;
		let resize_rel_y = null;

		const axes_dim = {
			x: 'width',
			y: 'height'
		};
		const axes_dim_min = {
			x: 1,
			y: this._widget_min_rows
		};
		const axes_max = {
			x: this._max_columns,
			y: this._max_rows
		};

		const updateWidgetContainerPosition = () => {
			const widget_view = resize_widget.getView();
			const widget_view_container = widget_view.querySelector(`.${resize_widget.getCssClass('container')}`);

			if (resize_sides.right) {
				widget_view_container.style.right = 'auto';
				widget_view_container.style.width = `${resize_pos.width * grid_cell_width}px`;
			}
			else if (resize_sides.left) {
				widget_view_container.style.left = 'auto';
				widget_view_container.style.width = `${resize_pos.width * grid_cell_width}px`;
			}

			if (resize_sides.bottom) {
				widget_view_container.style.bottom = 'auto';
				widget_view_container.style.height = `${resize_pos.height * this._cell_height}px`;
			}
			else if (resize_sides.top) {
				widget_view_container.style.top = 'auto';
				widget_view_container.style.height = `${resize_pos.height * this._cell_height}px`;
			}
		};

		const getResizeSteps = (source_pos, target_pos) => {
			let resize_steps = [];

			for (const axis of ['x', 'y']) {
				if (source_pos[axis] != target_pos[axis]) {
					const distance = target_pos[axis] - source_pos[axis];

					resize_steps.push({
						operations: [
							{property: axis, direction: Math.sign(distance)},
							{property: axes_dim[axis], direction: -Math.sign(distance)},
						],
						count: Math.abs(distance)
					});
				}
				else if (source_pos[axes_dim[axis]] != target_pos[axes_dim[axis]]) {
					const distance = target_pos[axes_dim[axis]] - source_pos[axes_dim[axis]];

					resize_steps.push({
						operations: [
							{property: axes_dim[axis], direction: Math.sign(distance)}
						],
						count: Math.abs(distance)
					});
				}
			}

			return resize_steps;
		};

		const getRunAwaySpec = (source_pos, target_pos) => {
			if (source_pos.x + source_pos.width <= target_pos.x) {
				return {axis: 'x', direction: 1};
			}
			else if (source_pos.x >= target_pos.x + target_pos.width) {
				return {axis: 'x', direction: -1};
			}
			else if (source_pos.y + source_pos.height <= target_pos.y) {
				return {axis: 'y', direction: 1};
			}
			else if (source_pos.y >= target_pos.y + target_pos.height) {
				return {axis: 'y', direction: -1};
			}

			throw new Error('Source position must not overlap with the target position.');
		};

		const runAway = (widget, axis, direction, {do_squash = true} = {}) => {
			const data = this._widgets.get(widget);

			data.pos[axis] += direction;

			if (data.pos[axis] < 0 || data.pos[axis] + data.pos[axes_dim[axis]] > axes_max[axis]) {
				data.pos[axis] -= direction;

				if (data.pos[axes_dim[axis]] > axes_dim_min[axis] && do_squash) {
					data.pos[axes_dim[axis]]--;

					if (direction == 1) {
						data.pos[axis]++;
					}

					return true;
				}

				return false;
			}

			const original_positions = new Map();

			let overlapping_widgets = [];

			for (const [w, w_data] of this._widgets) {
				if (w === resize_widget || w === widget) {
					continue;
				}

				original_positions.set(w, {...w_data.pos});

				if (this._isPosOverlapping(data.pos, w_data.pos)) {
					overlapping_widgets.push(w);
				}
			}

			if (overlapping_widgets.length == 0) {
				return true;
			}

			let has_ran_away = true;

			for (const w of overlapping_widgets) {
				if (!runAway(w, axis, direction, {do_squash: false})) {
					has_ran_away = false;
					break;
				}
			}

			if (has_ran_away) {
				return true;
			}

			for (const [w, w_data] of this._widgets) {
				if (w === resize_widget || w === widget) {
					continue;
				}

				w_data.pos = {...original_positions.get(w)};
			}

			if (!do_squash) {
				return false;
			}

			if (data.pos[axes_dim[axis]] > axes_dim_min[axis]) {
				data.pos[axes_dim[axis]]--;

				if (direction == -1) {
					data.pos[axis]++;
				}

				return true;
			}

			has_ran_away = true;

			for (const w of overlapping_widgets) {
				if (!runAway(w, axis, direction, {do_squash: true})) {
					has_ran_away = false;
					break;
				}
			}

			if (has_ran_away) {
				return true;
			}

			for (const [w, w_data] of this._widgets) {
				if (w === resize_widget || w === widget) {
					continue;
				}

				w_data.pos = {...original_positions.get(w)};
			}

			return false;
		};

		const runBack = () => {
			const relocated_widgets = new Map();

			for (const [w, w_data] of this._widgets) {
				if (w === resize_widget) {
					continue;
				}

				if (!this._isPosEqual(w_data.pos, w_data.original_pos)) {
					relocated_widgets.set(w, w_data);
				}
			}

			let has_ran_back;

			do {
				has_ran_back = false;

				for (const [w, w_data] of relocated_widgets) {
					const pos = {...w_data.pos};

					pos.x += Math.sign(w_data.original_pos.x - pos.x);

					if (pos.width < w_data.original_pos.width) {
						pos.width++;
					}

					pos.y += Math.sign(w_data.original_pos.y - pos.y);

					if (pos.height < w_data.original_pos.height) {
						pos.height++;
					}

					if (!this._isPosEqual(pos, w_data.pos)) {
						if (this._isDataPosFree(pos, {except_widgets: new Set([w])})) {
							w_data.pos = pos;

							has_ran_back = true;
						}
					}
				}
			}
			while (has_ran_back);
		};

		const resize = (target_resize_pos) => {
			if (this._isPosEqual(target_resize_pos, resize_pos)
					|| this._isPosEqual(target_resize_pos, resize_pos_tested)) {
				return false;
			}

			resize_pos_tested = target_resize_pos;

			let best_resize_pos = resize_pos;

			for (const [w, w_data] of this._widgets) {
				if (w !== resize_widget) {
					w_data.best_pos = {...w.getPos()};
					w_data.pos = {...w_data.best_pos};
				}
			}

			for (const resize_step of getResizeSteps(resize_pos, target_resize_pos)) {
				for (let i = 0; i < resize_step.count; i++) {
					const step_resize_pos = {...best_resize_pos};

					for (const operation of resize_step.operations) {
						step_resize_pos[operation.property] += operation.direction;
					}

					let can_relocate = true;

					for (const [w, w_data] of this._widgets) {
						if (w === resize_widget) {
							continue;
						}

						if (this._isPosOverlapping(step_resize_pos, w_data.pos)) {
							const run_away_spec = getRunAwaySpec(best_resize_pos, w_data.pos);

							can_relocate = runAway(w, run_away_spec.axis, run_away_spec.direction);

							if (!can_relocate) {
								break;
							}
						}
					}

					if (!can_relocate) {
						for (const [w, w_data] of this._widgets) {
							if (w !== resize_widget) {
								w_data.pos = {...w_data.best_pos};
							}
						}

						break;
					}

					best_resize_pos = step_resize_pos;

					for (const [w, w_data] of this._widgets) {
						if (w !== resize_widget) {
							w_data.best_pos = {...w_data.pos};
						}
					}
				}
			}

			const can_relocate = !this._isPosEqual(best_resize_pos, resize_pos);

			if (can_relocate) {
				resize_pos = best_resize_pos;
				resize_pos_tested = best_resize_pos;

				this._widgets.get(resize_widget).pos = {...best_resize_pos};

				for (const [w, w_data] of this._widgets) {
					if (w !== resize_widget) {
						w_data.pos = w_data.best_pos;
					}
				}

				runBack();
			}

			delete this._widgets.get(resize_widget).pos;

			for (const [w, w_data] of this._widgets) {
				if (w === resize_widget) {
					continue;
				}

				if (can_relocate) {
					const pos = w.getPos();

					w.setPos(w_data.pos);

					if (w_data.pos.width != pos.width || w_data.pos.height != pos.height) {
						w.resize();
					}
				}

				delete w_data.best_pos;
				delete w_data.pos;
			}

			return can_relocate;
		};

		const move = (x, y) => {
			const rel_x = x - resize_rel_x;
			const rel_y = y - resize_rel_y + document.querySelector('.wrapper').scrollTop;

			const dim = {...resize_dim};

			if (resize_sides.right) {
				dim.x = resize_dim.x;
				dim.width = Math.max(
					grid_cell_width,
					Math.min(grid_rect.width - dim.x, resize_dim.width + rel_x)
				);
			}
			else if (resize_sides.left) {
				dim.x = Math.max(
					0,
					Math.min(
						resize_dim.x + resize_dim.width - grid_cell_width,
						resize_dim.x + rel_x
					)
				);
				dim.width = resize_dim.width + resize_dim.x - dim.x;
			}

			if (resize_sides.bottom) {
				dim.y = resize_dim.y;
				dim.height = Math.max(
					this._cell_height * this._widget_min_rows,
					Math.min(
						this._cell_height * this._max_rows - dim.y,
						this._cell_height * this._widget_max_rows,
						resize_dim.height + rel_y
					)
				);
			}
			else if (resize_sides.top) {
				dim.y = Math.max(
					0,
					Math.min(
						resize_dim.y + resize_dim.height - this._cell_height * this._widget_min_rows,
						resize_dim.y + rel_y
					),
					resize_dim.y + resize_dim.height - this._cell_height * this._widget_max_rows
				);
				dim.height = resize_dim.height + resize_dim.y - dim.y;
			}

			const resize_pos_width = Math.floor((dim.width + 0.49) / grid_cell_width);
			const resize_pos_height = Math.floor((dim.height + 0.49) / this._cell_height);

			const target_resize_pos = {
				x: resize_sides.left ? resize_pos.x + resize_pos.width - resize_pos_width : resize_pos.x,
				y: resize_sides.top ? resize_pos.y + resize_pos.height - resize_pos_height : resize_pos.y,
				width: resize_pos_width,
				height: resize_pos_height
			};

			if (resize(target_resize_pos)) {
				updateWidgetContainerPosition();

				resize_widget.setPos(resize_pos, {is_managed: true});
				resize_widget.resize();

				this._resizeGrid(resize_pos.y + resize_pos.height + this._grid_pad_rows);
			}

			const widget_view = resize_widget.getView();

			widget_view.style.left = `${dim.x}px`;
			widget_view.style.top = `${dim.y}px`;
			widget_view.style.width = `${dim.width}px`;
			widget_view.style.height = `${dim.height}px`;
		};

		this._widget_resizing_events = {
			mouseDown: (e) => {
				if (e.button != 0) {
					return;
				}

				resize_widget = null;

				for (const widget of this._widgets.keys()) {
					const widget_view = widget.getView();

					if (widget_view.contains(e.target)) {
						const resize_handle = e.target.closest(`.${widget.getCssClass('resize_handle')}`);

						if (resize_handle === null) {
							return false;
						}

						resize_widget = widget;
						resize_sides = resize_widget.getResizeHandleSides(resize_handle);

						break;
					}
				}

				if (resize_widget === null) {
					return;
				}

				e.preventDefault();

				this.blockInteraction();
				this._deactivateWidgetPlaceholder();

				for (const [widget, data] of this._widgets) {
					data.original_pos = widget.getPos();
				}

				grid_rect = this._dashboard_grid.getBoundingClientRect();
				grid_cell_width = grid_rect.width * this._cell_width / 100;

				resize_widget.setResizing(true);
				resize_pos = resize_widget.getPos();
				resize_pos_tested = resize_pos;

				updateWidgetContainerPosition();

				const widget_view = resize_widget.getView();
				const widget_view_computed_style = getComputedStyle(widget_view);

				resize_rel_x = e.pageX;
				resize_rel_y = e.pageY + document.querySelector('.wrapper').scrollTop;

				resize_dim = {
					x: parseFloat(widget_view_computed_style.left),
					y: parseFloat(widget_view_computed_style.top),
					width: parseFloat(widget_view_computed_style.width),
					height: parseFloat(widget_view_computed_style.height)
				};

				document.addEventListener('mouseup', this._widget_resizing_events.mouseUp, {passive: false});
				document.addEventListener('mousemove', this._widget_resizing_events.mouseMove);

				this._is_unsaved = true;

				this.fire(DASHBOARD_PAGE_EVENT_WIDGET_POSITION);
			},

			mouseUp: (e) => {
				if (move_animation_frame !== null) {
					cancelAnimationFrame(move_animation_frame);
				}

				resize_widget.setResizing(false);
				resize_widget.setPos(resize_pos);

				const widget_view = resize_widget.getView();
				const widget_view_container = widget_view.querySelector(`.${resize_widget.getCssClass('container')}`);

				widget_view_container.style.top = null;
				widget_view_container.style.left = null;
				widget_view_container.style.right = null;
				widget_view_container.style.bottom = null;
				widget_view_container.style.width = null;
				widget_view_container.style.height = null;

				move_animation_frame = null;
				grid_rect = null;
				grid_cell_width = null;
				resize_widget = null;
				resize_sides = null;
				resize_pos = null;
				resize_pos_tested = null;
				resize_dim = null;
				resize_rel_x = null;
				resize_rel_y = null;

				for (const data of this._widgets.values()) {
					delete data.original_position;
				}

				document.removeEventListener('mouseup', this._widget_resizing_events.mouseUp);
				document.removeEventListener('mousemove', this._widget_resizing_events.mouseMove);

				this.unblockInteraction();
				this.resetWidgetPlaceholder();
			},

			mouseMove: (e) => {
				if (move_animation_frame !== null) {
					cancelAnimationFrame(move_animation_frame);
				}

				move_animation_frame = requestAnimationFrame(() => {
					move_animation_frame = null;
					move(e.pageX, e.pageY);
				});
			}
		};
	}

	_activateWidgetResizing() {
		this._dashboard_grid.addEventListener('mousedown', this._widget_resizing_events.mouseDown, {passive: false});
	}

	_deactivateWidgetResizing() {
		this._dashboard_grid.removeEventListener('mousedown', this._widget_resizing_events.mouseDown);

		document.removeEventListener('mouseup', this._widget_resizing_events.mouseUp);
		document.removeEventListener('mousemove', this._widget_resizing_events.mouseMove);
	}

	// Internal events management methods.

	_registerEvents() {
		this._events = {
			widgetActions: (e) => {
				const widget = e.detail.target;

				this.fire(DASHBOARD_PAGE_EVENT_WIDGET_ACTIONS, {
					widget,
					mouse_event: e.detail.mouse_event
				});
			},

			widgetEdit: (e) => {
				const widget = e.detail.target;

				if (!this._is_edit_mode) {
					this.setEditMode();
					this.fire(DASHBOARD_PAGE_EVENT_EDIT);
				}

				this.fire(DASHBOARD_PAGE_EVENT_WIDGET_EDIT, {widget});
			},

			widgetEnter: (e) => {
				const widget = e.detail.target;

				if (this._is_edit_mode) {
					const pos = widget.getPos();

					this._resizeGrid(pos.y + pos.height + this._grid_pad_rows);

					this.resetWidgetPlaceholder();
				}

				if (!widget.isEntered()) {
					widget.enter();
					this._leaveWidgets({except_widget: widget});
				}

				if (widget.getPos().y == 0) {
					const num_lines = widget.getNumHeaderLines();

					if (num_lines != this._events_data.last_num_reserved_header_lines) {
						this._events_data.last_num_reserved_header_lines = num_lines;

						this.fire(DASHBOARD_PAGE_EVENT_RESERVE_HEADER_LINES, {num_lines});
					}
				}
			},

			widgetLeave: (e) => {
				const widget = e.detail.target;

				if (widget.isEntered()) {
					widget.leave();
				}

				if (this._events_data.last_num_reserved_header_lines > 0) {
					this._events_data.last_num_reserved_header_lines = 0;

					this.fire(DASHBOARD_PAGE_EVENT_RESERVE_HEADER_LINES, {num_lines: 0});
				}
			},

			widgetCopy: (e) => {
				const widget = e.detail.target;

				this.fire(DASHBOARD_PAGE_EVENT_WIDGET_COPY, {widget});
			},

			widgetPaste: (e) => {
				const widget = e.detail.target;

				this.fire(DASHBOARD_PAGE_EVENT_WIDGET_PASTE, {widget});
			},

			widgetDelete: (e) => {
				const widget = e.detail.target;

				this.deleteWidget(widget);

				this.fire(DASHBOARD_PAGE_EVENT_WIDGET_DELETE);
			},

			dashboardGridResize: () => {
				if (this._events_data.dashboard_grid_resize_first_time) {
					this._events_data.dashboard_grid_resize_first_time = false;
					this._events_data.dashboard_grid_resize_width = this._dashboard_grid.clientWidth;

					return;
				}

				if (this._dashboard_grid.clientWidth == this._events_data.dashboard_grid_resize_width) {
					return;
				}

				this._events_data.dashboard_grid_resize_width = this._dashboard_grid.clientWidth;

				if (this._events_data.dashboard_grid_resize_timeout_id != null) {
					clearTimeout(this._events_data.dashboard_grid_resize_timeout_id);
				}

				this._events_data.dashboard_grid_resize_timeout_id = setTimeout(() => {
					this._events_data.dashboard_grid_resize_timeout_id = null;

					this._resizeGrid();

					if (this._is_edit_mode) {
						this._widget_placeholder.resize();
					}

					for (const widget of this._widgets.keys()) {
						widget.resize();
					}
				}, 200);
			}
		};

		this._events_data = {
			last_num_reserved_header_lines: 0,

			dashboard_grid_resize_timeout_id: null,
			dashboard_grid_resize_first_time: true,
			dashboard_grid_resize_width: null
		};
	}

	_activateEvents() {
		this._events_data.dashboard_grid_resize_observer = new ResizeObserver(this._events.dashboardGridResize);
		this._events_data.dashboard_grid_resize_observer.observe(this._dashboard_grid);
	}

	_deactivateEvents() {
		this._events_data.dashboard_grid_resize_observer.disconnect();

		if (this._events_data.dashboard_grid_resize_timeout_id != null) {
			clearTimeout(this._events_data.dashboard_grid_resize_timeout_id);
		}
	}
}
