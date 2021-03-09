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

const DASHBOARD_PAGE_EVENT_READY = 'ready';
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
		web_layout_mode,
		time_period,
		dynamic_hostid
	}) {
		super(document.createElement('div'));

		this._dashboard_target = target;

		this._data = {
			dashboard_pageid: data.dashboard_pageid,
			name: data.name,
			display_period: data.display_period
		};
		this._dashboard = {
			templateid: data.templateid,
			dashboardid: data.dashboardid
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
		this._web_layout_mode = web_layout_mode;
		this._time_period = time_period;
		this._dynamic_hostid = dynamic_hostid;

		this._init();
		this._initEvents();
	}

	_init() {
		this._state = DASHBOARD_PAGE_STATE_INITIAL;

		this._is_ready = false;

		this._widgets = [];
	}

	_initEvents() {
		this._events = {
			widgetEnter: (e) => {
				const widget = e.detail.target;

				if (widget.isEntered() || this._isInteracting()) {
					return;
				}

				widget.enter();
				this._leaveWidgetsExcept(widget);
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

			widgetUpdate: (e) => {
				const widget = e.detail.target;

				if (widget.isReady()) {
					return;
				}

				widget.ready();

				if (this._is_ready) {
					return;
				}

				let is_ready = true;

				for (const widget of this._widgets) {
					if (!widget.isReady()) {
						is_ready = false;
						break;
					}
				}

				if (is_ready) {
					this._is_ready = true;

					for (const widget of this._widgets) {
						widget.dashboardPageReady(this._widgets);
					}

					this.fire(DASHBOARD_PAGE_EVENT_READY);
				}
			}
		};

		this._registerEvents = () => {};
		this._unregisterEvents = () => {};
	}

	isReady() {
		return this._is_ready;
	}

	dashboardReady(dashboard_pages) {
		let widgets = [];

		for (const dashboard_page of dashboard_pages) {
			widgets = widgets.concat(dashboard_page._widgets);
		}

		for (const widget of widgets) {
			widget.dashboardReady(widgets);
		}
	}

	_reserveHeaderLines() {
		let num_header_lines = 0;

		for (const widget of this._widgets) {
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

	_leaveWidgetsExcept(except_widget = null) {
		for (const widget of this._widgets) {
			if (widget !== except_widget) {
				widget.leave();
			}
		}
	}

	_isInteracting() {
		for (const widget of this._widgets) {
			const widget_view = widget.getView();

			if (widget.isInteracting()
					|| widget_view.classList.contains('ui-draggable-dragging')
					|| widget_view.classList.contains('ui-resizable-resizing')) {
				return true;
			}
		}

		return false;
	}

//	getWidgets() {
//		return this._widgets;
//	}

	start() {
		this._state = DASHBOARD_PAGE_STATE_INACTIVE;

		for (const widget of this._widgets) {
			this._startWidget(widget);
		}
	}

	_startWidget(widget) {
		widget.start();
		this._dashboard_target.appendChild(widget.getView());
	}

	activate() {
		this._state = DASHBOARD_PAGE_STATE_ACTIVE;

		for (const widget of this._widgets) {
			this._activateWidget(widget);
		}
	}

	_activateWidget(widget) {
		widget.activate();
		widget
			.on(WIDGET_EVENT_ENTER, this._events.widgetEnter)
			.on(WIDGET_EVENT_LEAVE, this._events.widgetLeave)
			.on(WIDGET_EVENT_UPDATE, this._events.widgetUpdate);
	}

	deactivate() {
		this._state = DASHBOARD_PAGE_STATE_INACTIVE;

		for (const widget of this._widgets) {
			this._deactivateWidget(widget);
		}
	}

	_deactivateWidget(widget) {
		widget.deactivate();
		widget
			.off(WIDGET_EVENT_ENTER, this._events.widgetEnter)
			.off(WIDGET_EVENT_LEAVE, this._events.widgetLeave)
			.off(WIDGET_EVENT_UPDATE, this._events.widgetUpdate);
	}

	destroy() {
		if (this._state === DASHBOARD_PAGE_STATE_ACTIVE) {
			this.deactivate();
		}
		if (this._state !== DASHBOARD_PAGE_STATE_INACTIVE) {
			throw new Error('Unsupported state change.');
		}
		this._state = DASHBOARD_PAGE_STATE_DESTROYED;

		for (const widget of this._widgets) {
			widget.destroy();
		}

		delete this._widgets;
	}

	resize() {
		for (const widget of this._widgets) {
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

		for (const widget of this._widgets) {
			widget.setEditMode();
		}
	}

	setDynamicHost(dynamic_hostid) {
		if (this._dynamic_hostid != dynamic_hostid) {
			this._dynamic_hostid = dynamic_hostid;

			for (const widget of this._widgets) {
				if (widget.supportsDynamicHosts() && this._dynamic_hostid != widget.getDynamicHost()) {
					widget.setDynamicHost(this._dynamic_hostid);
				}
			}
		}
	}

	setTimePeriod(time_period) {
		this._time_period = time_period;

		for (const widget of this._widgets) {
			widget.setTimePeriod(this._time_period);
		}
	}

	addWidget({
		type,
		header,
		view_mode,
		fields,
		configuration,
		widgetid = null,
		uniqueid,
		pos,
		is_new,
		rf_rate
	}) {
		const widget = new (eval(this._widget_defaults[type].js_class))({
			type: type,
			header: header,
			view_mode: view_mode,
			fields: fields,
			configuration: configuration,
			defaults: this._widget_defaults[type],
			parent: null,
			widgetid: widgetid,
			uniqueid: uniqueid,
			pos: pos,
			is_new: is_new,
			rf_rate: rf_rate,
			dashboard: {
				templateid: this._dashboard.templateid,
				dashboardid: this._dashboard.dashboardid
			},
			cell_width: this._cell_width,
			cell_height: this._cell_height,
			is_editable: this._is_editable,
			is_edit_mode: this._is_edit_mode,
			web_layout_mode: this._web_layout_mode,
			time_period: this._time_period,
			dynamic_hostid: this._dynamic_hostid
		});

		this._widgets.push(widget);

		if (this._state !== DASHBOARD_PAGE_STATE_INITIAL) {
			this._startWidget(widget);
		}

		if (this._state === DASHBOARD_PAGE_STATE_ACTIVE) {
			this._activateWidget(widget);
		}

		this._is_ready = false;
	}

	deleteWidget(widget) {
		// TODO
	}
}
