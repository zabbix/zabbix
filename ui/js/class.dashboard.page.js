/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

const DASHBOARD_PAGE_EVENT_EDIT = 'edit';
const DASHBOARD_PAGE_EVENT_WIDGET_EDIT = 'widget-edit';
const DASHBOARD_PAGE_EVENT_WIDGET_COPY = 'widget-copy';
const DASHBOARD_PAGE_EVENT_WIDGET_DELETE = 'widget-delete';
const DASHBOARD_PAGE_EVENT_ANNOUNCE_WIDGETS = 'announce-widgets';
const DASHBOARD_PAGE_EVENT_RESERVE_HEADER_LINES = 'reserve-header-lines';

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

		this._dashboard_target = target;

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
	}

	_registerEvents() {
		this._events = {
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

				if (widget.isEntered() || this._isInteracting()) {
					return;
				}

				widget.enter();
				this.leaveWidgetsExcept(widget);
				this._reserveHeaderLines();
			},

			widgetLeave: (e) => {
				const widget = e.detail.target;

				if (!widget.isEntered() || this._isInteracting()) {
					return;
				}

				widget.leave();
				this._reserveHeaderLines();
			},

			widgetCopy: (e) => {
				const widget = e.detail.target;

				this.fire(DASHBOARD_PAGE_EVENT_WIDGET_COPY, {data: widget.getDataCopy()});
			},

			widgetPaste: (e) => {
				const widget = e.detail.target;

//				if (!widget.isEntered() || this._isInteracting()) {
//					return;
//				}

//				widget.leave();
//				this._reserveHeaderLines();
			},

			widgetDelete: (e) => {
				const widget = e.detail.target;

				this.deleteWidget(widget);
			}
		};
	}

	/**
	 * Find free position of the given width and height.
	 *
	 * @param {int} width
	 * @param {int} height
	 *
	 * @returns {object|null}
	 */
	findFreePos({width, height}) {
		const pos = {x: 0, y: 0, width: width, height: height};

		// Go y by row and try to position widget in each space.
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
				found = this.isPosFree(pos);
			}
		}

		return pos;
	}

	isPosFree(pos) {
		for (const widget of this._widgets.keys()) {
			if (this._posOverlap(pos, widget.getPosition())) {
				return false;
			}
		}

		return true;
	}

	accommodatePos(pos, {reverse_x = false, reverse_y = false} = {}) {
		const pos_1 = this._accommodatePosX({
			...this._accommodatePosY(
				{
					...pos,
					x: reverse_x ? pos.x + pos.width - 1 : pos.x,
					width: 1
				},
				{reverse: reverse_y}
			),
			x: pos.x,
			width: pos.width
		}, {reverse: reverse_x});

		const pos_2 = this._accommodatePosY({
			...this._accommodatePosX(
				{
					...pos,
					y: reverse_y ? pos.y + pos.height - 2 : pos.y,
					height: 2
				},
				{reverse: reverse_x}
			),
			y: pos.y,
			height: pos.height
		}, {reverse: reverse_y});

		return (pos_1.width * pos_1.height > pos_2.width * pos_2.height) ? pos_1 : pos_2;
	}

	_accommodatePosX(pos, {reverse = false} = {}) {
		const max_pos = {...pos};

		if (reverse) {
			for (let x = pos.x + pos.width - 1, width = 1; x >= pos.x; x--, width++) {
				if (!this.isPosFree({...max_pos, x, width})) {
					break;
				}

				max_pos.x = x;
				max_pos.width = width;
			}
		}
		else {
			for (let width = 1; width <= pos.width; width++) {
				if (!this.isPosFree({...max_pos, width})) {
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
				if (!this.isPosFree({...max_pos, y, height})) {
					break;
				}

				max_pos.y = y;
				max_pos.height = height;
			}
		}
		else {
			for (let height = 2; height <= pos.height; height++) {
				if (!this.isPosFree({...max_pos, height})) {
					break;
				}

				max_pos.height = height;
			}
		}

		return max_pos;
	}

	/**
	 * Check if positions overlap.
	 *
	 * @param {object} pos_1
	 * @param {object} pos_2
	 *
	 * @returns {boolean}
	 */
	_posOverlap(pos_1, pos_2) {
		return (
			pos_1.x < (pos_2.x + pos_2.width)
				&& (pos_1.x + pos_1.width) > pos_2.x
				&& pos_1.y < (pos_2.y + pos_2.height)
				&& (pos_1.y + pos_1.height) > pos_2.y
		);
	}

	getData() {
		return this._data;
	}

	getNumWidgets() {
		return this._widgets.size;
	}

	getWidget(unique_id) {
		for (const widget of this._widgets.keys()) {
			if (widget.getUniqueId() === unique_id) {
				return widget;
			}
		}

		return null;
	}

	getUniqueId() {
		return this._unique_id;
	}

	isUpdated() {

	}

	editProperties() {

	}

	applyProperties() {

	}

	_reserveHeaderLines() {
		let num_header_lines = 0;

		for (const widget of this._widgets.keys()) {
			if (!widget.isEntered()) {
				continue;
			}

			if (widget.getPosition().y != 0) {
				break;
			}

			num_header_lines = widget.getNumHeaderLines();
		}

		this.fire(DASHBOARD_PAGE_EVENT_RESERVE_HEADER_LINES, {num_header_lines: num_header_lines});
	}

	leaveWidgetsExcept(except_widget = null) {
		for (const widget of this._widgets.keys()) {
			if (widget !== except_widget) {
				widget.leave();
			}
		}
	}

	_isInteracting() {
		for (const widget of this._widgets.keys()) {
			const widget_view = widget.getView();

			if (widget.isInteracting()
					|| widget_view.classList.contains('ui-draggable-dragging')
					|| widget_view.classList.contains('ui-resizable-resizing')) {
				return true;
			}
		}

		return false;
	}

	start() {
		this._state = DASHBOARD_PAGE_STATE_INACTIVE;

		for (const widget of this._widgets.keys()) {
			widget.start();
		}
	}

	activate() {
		this._state = DASHBOARD_PAGE_STATE_ACTIVE;

		for (const widget of this._widgets.keys()) {
			this._dashboard_target.appendChild(widget.getView());
			this._activateWidget(widget);
		}
	}

	_activateWidget(widget) {
		widget.activate();
		widget
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
			this._dashboard_target.removeChild(widget.getView());
			this._deactivateWidget(widget);
		}
	}

	_deactivateWidget(widget) {
		widget.deactivate();
		widget
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

	resize() {
		for (const widget of this._widgets.keys()) {
			widget.resize();
		}
	}

	getState() {
		return this._state;
	}

	isEditMode() {
		return this._is_edit_mode;
	}

	setEditMode() {
		this._is_edit_mode = true;

		for (const widget of this._widgets.keys()) {
			widget.setEditMode();
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

	getNumRows() {
		let num_rows = 0;

		for (const widget of this._widgets.keys()) {
			const pos = widget.getPosition();

			num_rows = Math.max(num_rows, pos.y + pos.height);
		}

		return num_rows;
	}

	addWidget({type, name, view_mode, fields, configuration, widgetid, pos, is_new, rf_rate, unique_id}) {
		const widget = new (eval(this._widget_defaults[type].js_class))({
			type,
			name,
			view_mode,
			fields,
			configuration,
			defaults: this._widget_defaults[type],
			parent: null,
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
			is_editable: this._is_editable,
			is_edit_mode: this._is_edit_mode,
			can_edit_dashboards: this._can_edit_dashboards,
			time_period: this._time_period,
			dynamic_hostid: this._dynamic_hostid,
			unique_id
		});

		this._widgets.set(widget, {});

		this.fire(DASHBOARD_PAGE_EVENT_ANNOUNCE_WIDGETS);

		if (this._state !== DASHBOARD_PAGE_STATE_INITIAL) {
			widget.start();
		}

		if (this._state === DASHBOARD_PAGE_STATE_ACTIVE) {
			this._dashboard_target.appendChild(widget.getView());
			this._activateWidget(widget);
		}

		return widget;
	}

	deleteWidget(widget, {is_batch_mode = false} = {}) {
		if (widget.getState() === WIDGET_STATE_ACTIVE) {
			this._dashboard_target.removeChild(widget.getView());
			this._deactivateWidget(widget);
		}

		if (widget.getState() !== WIDGET_STATE_INITIAL) {
			widget.destroy();
		}

		this._widgets.delete(widget);

		if (!is_batch_mode) {
			this.fire(DASHBOARD_PAGE_EVENT_WIDGET_DELETE);
			this.fire(DASHBOARD_PAGE_EVENT_ANNOUNCE_WIDGETS);
		}
	}

	replaceWidget(widget, widget_data) {
		this.deleteWidget(widget, {is_batch_mode: true});
		this.addWidget(widget_data);
	}

	announceWidgets(dashboard_pages) {
		let widgets = [];

		for (const dashboard_page of dashboard_pages) {
			widgets = widgets.concat(dashboard_page._widgets);
		}

		for (const widget of widgets) {
			widget.announceWidgets(widgets);
		}
	}
}
