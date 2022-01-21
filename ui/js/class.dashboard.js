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


const ZBX_STYLE_DASHBOARD_IS_MULTIPAGE = 'dashboard-is-multipage';
const ZBX_STYLE_DASHBOARD_IS_EDIT_MODE = 'dashboard-is-edit-mode';
const ZBX_STYLE_DASHBOARD_NAVIGATION_IS_SCROLLABLE = 'is-scrollable';
const ZBX_STYLE_DASHBOARD_SELECTED_TAB = 'selected-tab';

const DASHBOARD_STATE_INITIAL = 'initial';
const DASHBOARD_STATE_ACTIVE = 'active';

const DASHBOARD_CLIPBOARD_TYPE_WIDGET = 'widget';
const DASHBOARD_CLIPBOARD_TYPE_DASHBOARD_PAGE = 'dashboard-page';

const DASHBOARD_EVENT_BUSY = 'dashboard-busy';
const DASHBOARD_EVENT_IDLE = 'dashboard-idle';
const DASHBOARD_EVENT_EDIT = 'dashboard-edit';
const DASHBOARD_EVENT_APPLY_PROPERTIES = 'dashboard-apply-properties';

class CDashboard extends CBaseComponent {

	constructor(target, {
		containers,
		buttons,
		data,
		max_dashboard_pages,
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
		is_kiosk_mode,
		time_period,
		dynamic_hostid
	}) {
		super(target);

		this._containers = {
			grid: containers.grid,
			navigation: containers.navigation,
			navigation_tabs: containers.navigation_tabs
		}

		this._buttons = {
			previous_page: buttons.previous_page,
			next_page: buttons.next_page,
			slideshow: buttons.slideshow
		};

		this._data = {
			dashboardid: data.dashboardid,
			name: data.name,
			userid: data.userid,
			templateid: data.templateid,
			display_period: data.display_period,
			auto_start: data.auto_start
		};

		this._max_dashboard_pages = max_dashboard_pages;
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
		this._is_kiosk_mode = is_kiosk_mode;
		this._time_period = time_period;
		this._dynamic_hostid = dynamic_hostid;

		this._init();
		this._registerEvents();
	}

	_init() {
		this._state = DASHBOARD_STATE_INITIAL;

		this._dashboard_pages = new Map();
		this._selected_dashboard_page = null;

		this._busy_conditions = new Set();

		this._original_properties = {
			name: this._data.name,
			userid: this._data.userid,
			display_period: this._data.display_period,
			auto_start: this._data.auto_start
		};

		this._async_timeout_ms = 50;

		this._unique_id_index = 0;

		this._new_widget_dashboard_page = null;
		this._new_widget_pos = null;
		this._new_widget_pos_reserved = null;

		this._warning_message_box = null;

		this._reserve_header_lines = 0;
		this._reserve_header_lines_timeout_id = null;
		this._is_edit_widget_properties_cancel_subscribed = false;

		this._header_lines_steady_period = 2000;

		this._slideshow_steady_period = 5000;
		this._slideshow_switch_time = null;
		this._slideshow_timeout_id = null;

		this._is_unsaved = false;

		if (!this._is_kiosk_mode) {
			const sortable = document.createElement('div');

			this._containers.navigation_tabs.appendChild(sortable);

			this._tabs = new CSortable(sortable, {
				is_vertical: false,
				is_sorting_enabled: this._is_edit_mode
			});

			this._tabs_dashboard_pages = new Map();
		}
	}

	// Logical state control methods.

	activate() {
		if (this._dashboard_pages.size == 0) {
			throw new Error('Cannot activate dashboard without dashboard pages.');
		}

		this._state = DASHBOARD_STATE_ACTIVE;

		this._activateEvents();

		this._announceWidgets();

		let dashboard_page = this._getRestorableDashboardPage();

		if (dashboard_page === null) {
			dashboard_page = this._dashboard_pages.keys().next().value;
		}

		this._selectDashboardPage(dashboard_page);

		if (this._is_edit_mode) {
			this._target.classList.add(ZBX_STYLE_DASHBOARD_IS_EDIT_MODE);
		}

		if (!this._is_edit_mode && this._data.auto_start == 1 && this._dashboard_pages.size > 1) {
			this._startSlideshow();
		}
	}

	// External events management methods.

	isEditMode() {
		return this._is_edit_mode;
	}

	setEditMode({is_internal_call = false} = {}) {
		this._is_edit_mode = true;

		for (const dashboard_page of this._dashboard_pages.keys()) {
			if (!dashboard_page.isEditMode()) {
				dashboard_page.setEditMode();
			}
		}

		if (!this._is_kiosk_mode) {
			this._tabs.enableSorting();
		}

		this._stopSlideshow();

		this._target.classList.add(ZBX_STYLE_DASHBOARD_IS_EDIT_MODE);

		if (is_internal_call) {
			this.fire(DASHBOARD_EVENT_EDIT);
		}
	}

	setDynamicHost(dynamic_hostid) {
		this._dynamic_hostid = dynamic_hostid;

		for (const dashboard_page of this._dashboard_pages.keys()) {
			dashboard_page.setDynamicHost(this._dynamic_hostid);
		}
	}

	_startSlideshow() {
		if (this._slideshow_timeout_id !== null) {
			clearTimeout(this._slideshow_timeout_id);
		}

		if (this._buttons.slideshow !== null) {
			this._buttons.slideshow.classList.remove('slideshow-state-stopped');
			this._buttons.slideshow.classList.add('slideshow-state-started');

			if (this._buttons.slideshow.title !== '') {
				this._buttons.slideshow.title = t('Stop slideshow');
			}
		}

		let timeout_ms = this._selected_dashboard_page.getDisplayPeriod() * 1000;

		if (timeout_ms == 0) {
			timeout_ms = this._data.display_period * 1000;
		}

		this._slideshow_switch_time = Date.now() + timeout_ms;
		this._slideshow_timeout_id = setTimeout(() => this._switchSlideshow(), timeout_ms);
	}

	_stopSlideshow() {
		if (this._slideshow_timeout_id === null) {
			return;
		}

		if (this._buttons.slideshow !== null) {
			this._buttons.slideshow.classList.remove('slideshow-state-started');
			this._buttons.slideshow.classList.add('slideshow-state-stopped');

			if (this._buttons.slideshow.title !== '') {
				this._buttons.slideshow.title = t('Start slideshow');
			}
		}

		clearTimeout(this._slideshow_timeout_id);

		this._slideshow_switch_time = null;
		this._slideshow_timeout_id = null;
	}

	_switchSlideshow() {
		this._slideshow_timeout_id = null;

		if (this._isUserInteracting()) {
			this._slideshow_switch_time = Date.now() + this._slideshow_steady_period;
			this._slideshow_timeout_id = setTimeout(() => this._switchSlideshow(), this._slideshow_steady_period);

			return;
		}

		const dashboard_pages = [...this._dashboard_pages.keys()];
		const dashboard_page_index = dashboard_pages.indexOf(this._selected_dashboard_page);

		this._selectDashboardPage(
			dashboard_pages[dashboard_page_index < dashboard_pages.length - 1 ? dashboard_page_index + 1 : 0]
		)

		let timeout_ms = this._selected_dashboard_page.getDisplayPeriod() * 1000;

		if (timeout_ms == 0) {
			timeout_ms = this._data.display_period * 1000;
		}

		this._slideshow_switch_time = Math.max(Date.now() + this._slideshow_steady_period,
			timeout_ms + this._slideshow_switch_time
		);

		this._slideshow_timeout_id = setTimeout(() => this._switchSlideshow(),
			this._slideshow_switch_time - Date.now()
		);
	}

	_isSlideshowRunning() {
		return this._slideshow_timeout_id !== null;
	}

	_keepSteadySlideshow() {
		if (this._slideshow_timeout_id === null) {
			return;
		}

		if (this._slideshow_switch_time - Date.now() < this._slideshow_steady_period) {
			clearTimeout(this._slideshow_timeout_id);

			this._slideshow_switch_time = Date.now() + this._slideshow_steady_period;

			this._slideshow_timeout_id = setTimeout(() => this._switchSlideshow(),
				this._slideshow_switch_time - Date.now()
			);
		}
	}

	_announceWidgets() {
		const dashboard_pages = Array.from(this._dashboard_pages.keys());

		for (const dashboard_page of dashboard_pages) {
			dashboard_page.announceWidgets(dashboard_pages);
		}
	}

	_createBusyCondition() {
		if (this._busy_conditions.size == 0) {
			this.fire(DASHBOARD_EVENT_BUSY);
		}

		const busy_condition = {};

		this._busy_conditions.add(busy_condition);

		return busy_condition;
	}

	_deleteBusyCondition(busy_condition) {
		this._busy_conditions.delete(busy_condition);

		if (this._busy_conditions.size == 0) {
			this.fire(DASHBOARD_EVENT_IDLE);
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

		if (!this._is_kiosk_mode) {
			const dashboard_pages_data = Array.from(this._dashboard_pages.values());
			const tabs = [...this._tabs.getList().children];

			if (tabs.length != dashboard_pages_data.length) {
				return true;
			}

			for (let i = 0; i < dashboard_pages_data.length; i++) {
				if (dashboard_pages_data[i].tab !== tabs[i]) {
					return true;
				}
			}
		}

		for (const dashboard_page of this._dashboard_pages.keys()) {
			if (dashboard_page.isUnsaved()) {
				return true;
			}
		}

		return false;
	}

	// Data interface methods.

	getData() {
		return this._data;
	}

	addNewDashboardPage() {
		if (this._dashboard_pages.size >= this._max_dashboard_pages) {
			this._warnDashboardExhausted();

			return;
		}

		this.editDashboardPageProperties();
	}

	addNewWidget() {
		this.editWidgetProperties();
	}

	addDashboardPage({dashboard_pageid, name, display_period, widgets}) {
		const dashboard_page = new CDashboardPage(this._containers.grid, {
			data: {
				dashboard_pageid,
				name,
				display_period
			},
			dashboard: {
				templateid: this._data.templateid,
				dashboardid: this._data.dashboardid
			},
			cell_width: this._cell_width,
			cell_height: this._cell_height,
			max_columns: this._max_columns,
			max_rows: this._max_rows,
			widget_min_rows: this._widget_min_rows,
			widget_max_rows: this._widget_max_rows,
			widget_defaults: this._widget_defaults,
			is_editable: this._is_editable,
			is_edit_mode: this._is_edit_mode,
			can_edit_dashboards: this._can_edit_dashboards,
			time_period: this._time_period,
			dynamic_hostid: this._dynamic_hostid,
			unique_id: this._createUniqueId()
		});

		this._dashboard_pages.set(dashboard_page, {});

		for (const widget_data of widgets) {
			dashboard_page.addWidget({
				...widget_data,
				is_new: false,
				unique_id: this._createUniqueId()
			});
		}

		if (this._state === DASHBOARD_STATE_ACTIVE) {
			this._announceWidgets();
		}

		if (!this._is_kiosk_mode) {
			this._addTab(dashboard_page);
		}

		this._target.classList.toggle(ZBX_STYLE_DASHBOARD_IS_MULTIPAGE, this._dashboard_pages.size > 1);

		if (dashboard_pageid === null) {
			this._is_unsaved = true;
		}

		return dashboard_page;
	}

	deleteDashboardPage(dashboard_page) {
		if (this._dashboard_pages.size == 1) {
			throw new Error('Cannot delete the last dashboard page.');
		}

		if (dashboard_page === this._selected_dashboard_page) {
			if (this._is_kiosk_mode) {
				for (const select_dashboard_page of this._dashboard_pages.keys()) {
					if (select_dashboard_page !== dashboard_page) {
						this._selectDashboardPage(select_dashboard_page);
						break;
					}
				}
			}
			else {
				const tabs = [...this._tabs.getList().children];
				const tab_index = tabs.indexOf(this._dashboard_pages.get(dashboard_page).tab);

				this._selectDashboardPage(
					this._tabs_dashboard_pages.get(tabs[tab_index > 0 ? tab_index - 1 : tab_index + 1])
				);
			}
		}

		if (dashboard_page.getState() !== DASHBOARD_PAGE_STATE_INITIAL) {
			dashboard_page.destroy();
		}

		if (!this._is_kiosk_mode) {
			this._deleteTab(dashboard_page);
		}

		this._dashboard_pages.delete(dashboard_page);

		this._announceWidgets();

		this._target.classList.toggle(ZBX_STYLE_DASHBOARD_IS_MULTIPAGE, this._dashboard_pages.size > 1);

		this._is_unsaved = true;
	}

	getDashboardPage(unique_id) {
		for (const dashboard_page of this._dashboard_pages.keys()) {
			if (dashboard_page.getUniqueId() === unique_id) {
				return dashboard_page;
			}
		}

		return null;
	}

	getDashboardPages() {
		return [...this._dashboard_pages.keys()];
	}

	pasteDashboardPage(new_dashboard_page_data) {
		if (this._dashboard_pages.size >= this._max_dashboard_pages) {
			this._warnDashboardExhausted();

			return;
		}

		const busy_condition = this._createBusyCondition();

		return Promise.resolve()
			.then(() => this._promiseDashboardWidgetsSanitize(new_dashboard_page_data.widgets))
			.then((response) => {
				if (this._dashboard_pages.size >= this._max_dashboard_pages) {
					this._warnDashboardExhausted();

					return;
				}

				const widgets = new_dashboard_page_data.widgets;

				for (let i = 0; i < response.widgets.length; i++) {
					widgets[i].fields = response.widgets[i].fields;
				}

				const used_references = this._getUsedReferences();
				const reference_substitution = new Map();

				for (const widget of widgets) {
					const reference_field = this._widget_defaults[widget.type].reference_field;

					if (reference_field !== null) {
						const old_reference = widget.fields[reference_field];
						const new_reference = this._createReference({used_references});

						widget.fields[reference_field] = new_reference;

						used_references.add(new_reference);
						reference_substitution.set(old_reference, new_reference);
					}
				}

				for (const widget of widgets) {
					for (const reference_field of this._widget_defaults[widget.type].foreign_reference_fields) {
						const old_reference = widget.fields[reference_field];

						if (reference_substitution.has(old_reference)) {
							widget.fields[reference_field] = reference_substitution.get(old_reference);
						}
					}
				}

				const dashboard_page = this.addDashboardPage({
					dashboard_pageid: null,
					name: new_dashboard_page_data.name,
					display_period: new_dashboard_page_data.display_period,
					widgets
				});

				this._selectDashboardPage(dashboard_page, {is_async: true});
			})
			.catch((error) => {
				clearMessages();

				addMessage((typeof error === 'object' && 'html_string' in error)
					? error.html_string
					: makeMessageBox('bad', [], t('Failed to paste dashboard page.'), true, false)
				);
			})
			.finally(() => this._deleteBusyCondition(busy_condition))
	}

	pasteWidget(new_widget_data, {widget = null, new_widget_pos = null} = {}) {
		const dashboard_page = this._selected_dashboard_page;

		if (widget !== null) {
			new_widget_pos = widget.getPos();
		}
		else if (new_widget_pos !== null) {
			new_widget_pos = {
				...new_widget_data.pos,
				...new_widget_pos
			};

			new_widget_pos.width = Math.min(new_widget_pos.width, this._max_columns - new_widget_pos.x);
			new_widget_pos.height = Math.min(new_widget_pos.height, this._max_rows - new_widget_pos.y);
			new_widget_pos = dashboard_page.accommodatePos(new_widget_pos);
		}
		else {
			new_widget_pos = dashboard_page.findFreePos(new_widget_data.pos);
		}

		if (new_widget_pos === null) {
			this._warnDashboardPageExhausted();

			return;
		}

		if (widget !== null) {
			dashboard_page.deleteWidget(widget, {is_batch_mode: true});
		}

		const reference_field = this._widget_defaults[new_widget_data.type].reference_field;

		if (reference_field !== null) {
			new_widget_data.fields[reference_field] = this._createReference();
		}

		let references = [];

		for (const widget of dashboard_page.getWidgets()) {
			const reference_field = this._widget_defaults[widget.getType()].reference_field;

			if (reference_field !== null) {
				references.push(widget.getFields()[reference_field]);
			}
		}

		for (const reference_field of this._widget_defaults[new_widget_data.type].foreign_reference_fields) {
			if (reference_field in new_widget_data.fields
					&& !references.includes(new_widget_data.fields[reference_field])) {
				new_widget_data.fields[reference_field] = '';
			}
		}

		const paste_placeholder_widget = dashboard_page.addPastePlaceholderWidget({
			type: new_widget_data.type,
			name: new_widget_data.name,
			view_mode: new_widget_data.view_mode,
			pos: new_widget_pos,
			unique_id: this._createUniqueId()
		});

		dashboard_page.resetWidgetPlaceholder();

		const busy_condition = this._createBusyCondition();

		dashboard_page.promiseScrollIntoView(new_widget_pos)
			.then(() => this._promiseDashboardWidgetsSanitize([new_widget_data]))
			.then((response) => {
				if (dashboard_page.getState() === DASHBOARD_PAGE_STATE_DESTROYED) {
					return;
				}

				dashboard_page.replaceWidget(paste_placeholder_widget, {
					...new_widget_data,
					fields: response.widgets[0].fields,
					widgetid: null,
					pos: new_widget_pos,
					is_new: true,
					unique_id: this._createUniqueId()
				});
			})
			.catch((error) => {
				clearMessages();

				addMessage((typeof error === 'object' && 'html_string' in error)
					? error.html_string
					: makeMessageBox('bad', [], t('Failed to paste widget.'), true, false)
				);
			})
			.finally(() => this._deleteBusyCondition(busy_condition));
	}

	_promiseDashboardWidgetsSanitize(widgets_data) {
		let request_widgets_data = [];

		for (const widget_data of widgets_data) {
			request_widgets_data.push({
				type: widget_data.type,
				fields: JSON.stringify(widget_data.fields)
			});
		}

		const curl = new Curl('zabbix.php');

		curl.setArgument('action', 'dashboard.widgets.sanitize');

		return fetch(curl.getUrl(), {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify({widgets: request_widgets_data})
		})
			.then((response) => response.json())
			.then((response) => {
				if ('errors' in response) {
					throw {html_string: response.errors};
				}

				return response;
			});
	}

	_storeDashboardObject(data) {
		localStorage.setItem('dashboard.clipboard', JSON.stringify({
			zbx_session_name: window.ZBX_SESSION_NAME,
			data
		}));
	}

	_getStoredDashboardObject() {
		let clipboard = localStorage.getItem('dashboard.clipboard');

		if (clipboard === null) {
			return null;
		}

		clipboard = JSON.parse(clipboard);

		if (clipboard.zbx_session_name !== window.ZBX_SESSION_NAME) {
			return null;
		}

		return clipboard.data;
	}

	_storeWidgetDataCopy(widget_data) {
		this._storeDashboardObject({
			type: DASHBOARD_CLIPBOARD_TYPE_WIDGET,
			data: widget_data
		});
	}

	getStoredWidgetDataCopy() {
		const data = this._getStoredDashboardObject();

		if (data === null || data.type !== DASHBOARD_CLIPBOARD_TYPE_WIDGET) {
			return null;
		}

		const widget_data = data.data;

		if (widget_data.dashboard.templateid !== this._data.templateid) {
			return null;
		}

		return widget_data;
	}

	_storeDashboardPageDataCopy(dashboard_page_data) {
		this._storeDashboardObject({
			type: DASHBOARD_CLIPBOARD_TYPE_DASHBOARD_PAGE,
			data: dashboard_page_data
		});
	}

	getStoredDashboardPageDataCopy() {
		const data = this._getStoredDashboardObject();

		if (data === null || data.type !== DASHBOARD_CLIPBOARD_TYPE_DASHBOARD_PAGE) {
			return null;
		}

		const dashboard_page_data = data.data;

		if (dashboard_page_data.dashboard.templateid !== this._data.templateid) {
			return null;
		}

		return dashboard_page_data;
	}

	_selectDashboardPage(dashboard_page, {is_async = false} = {}) {
		if (!this._is_edit_mode) {
			this._storeSelectedDashboardPage(dashboard_page);

			if (this._isSlideshowRunning()) {
				this._keepSteadySlideshow();
			}
		}

		this._promiseSelectDashboardPage(dashboard_page, {is_async})
			.then(() => {
				if (this._isSlideshowRunning()) {
					this._startSlideshow();
				}
			});
	}

	_promiseSelectDashboardPage(dashboard_page, {is_async = false} = {}) {
		return new Promise((resolve) => {
			if (this._is_kiosk_mode) {
				this._doSelectDashboardPage(dashboard_page);

				resolve();
			}
			else {
				this._selectTab(dashboard_page);

				if (is_async) {
					setTimeout(() => {
						this._doSelectDashboardPage(dashboard_page);

						resolve();
					}, this._async_timeout_ms);
				}
				else {
					this._doSelectDashboardPage(dashboard_page);

					resolve();
				}
			}
		});
	}

	_doSelectDashboardPage(dashboard_page) {
		if (this._selected_dashboard_page !== null) {
			this._deactivatePage(this._selected_dashboard_page);
		}

		this._selected_dashboard_page = dashboard_page;

		if (this._selected_dashboard_page.getState() === DASHBOARD_PAGE_STATE_INITIAL) {
			this._selected_dashboard_page.start();
		}

		this._activatePage(this._selected_dashboard_page);

		if (this._is_kiosk_mode) {
			this._resetHeaderLines();
		}
	}

	_activatePage(dashboard_page) {
		dashboard_page.activate();
		dashboard_page
			.on(DASHBOARD_PAGE_EVENT_EDIT, this._events.dashboardPageEdit)
			.on(DASHBOARD_PAGE_EVENT_WIDGET_ADD, this._events.dashboardPageWidgetAdd)
			.on(DASHBOARD_PAGE_EVENT_WIDGET_ADD_NEW, this._events.dashboardPageWidgetAddNew)
			.on(DASHBOARD_PAGE_EVENT_WIDGET_DELETE, this._events.dashboardPageWidgetDelete)
			.on(DASHBOARD_PAGE_EVENT_WIDGET_POSITION, this._events.dashboardPageWidgetPosition)
			.on(DASHBOARD_PAGE_EVENT_WIDGET_ACTIONS, this._events.dashboardPageWidgetActions)
			.on(DASHBOARD_PAGE_EVENT_WIDGET_EDIT, this._events.dashboardPageWidgetEdit)
			.on(DASHBOARD_PAGE_EVENT_WIDGET_COPY, this._events.dashboardPageWidgetCopy)
			.on(DASHBOARD_PAGE_EVENT_WIDGET_PASTE, this._events.dashboardPageWidgetPaste)
			.on(DASHBOARD_PAGE_EVENT_ANNOUNCE_WIDGETS, this._events.dashboardPageAnnounceWidgets);

		if (this._is_kiosk_mode) {
			dashboard_page.on(DASHBOARD_PAGE_EVENT_RESERVE_HEADER_LINES, this._events.dashboardPageReserveHeaderLines);
		}
	}

	_deactivatePage(dashboard_page) {
		dashboard_page.deactivate();
		dashboard_page
			.off(DASHBOARD_PAGE_EVENT_EDIT, this._events.dashboardPageEdit)
			.off(DASHBOARD_PAGE_EVENT_WIDGET_ADD, this._events.dashboardPageWidgetAdd)
			.off(DASHBOARD_PAGE_EVENT_WIDGET_ADD_NEW, this._events.dashboardPageWidgetAddNew)
			.off(DASHBOARD_PAGE_EVENT_WIDGET_DELETE, this._events.dashboardPageWidgetDelete)
			.off(DASHBOARD_PAGE_EVENT_WIDGET_POSITION, this._events.dashboardPageWidgetPosition)
			.off(DASHBOARD_PAGE_EVENT_WIDGET_ACTIONS, this._events.dashboardPageWidgetActions)
			.off(DASHBOARD_PAGE_EVENT_WIDGET_EDIT, this._events.dashboardPageWidgetEdit)
			.off(DASHBOARD_PAGE_EVENT_WIDGET_COPY, this._events.dashboardPageWidgetCopy)
			.off(DASHBOARD_PAGE_EVENT_WIDGET_PASTE, this._events.dashboardPageWidgetPaste)
			.off(DASHBOARD_PAGE_EVENT_ANNOUNCE_WIDGETS, this._events.dashboardPageAnnounceWidgets);

		if (this._is_kiosk_mode) {
			dashboard_page.off(DASHBOARD_PAGE_EVENT_RESERVE_HEADER_LINES, this._events.dashboardPageReserveHeaderLines);
		}
	}

	_storeSelectedDashboardPage(dashboard_page) {
		sessionStorage.setItem('dashboard.selected_dashboard_page', JSON.stringify({
			dashboardid: this._data.dashboardid,
			dashboard_pageid: dashboard_page.getDashboardPageId(),
			is_kiosk_mode: this._is_kiosk_mode
		}));
	}

	_getRestorableDashboardPage() {
		let stored_data = sessionStorage.getItem('dashboard.selected_dashboard_page');

		if (stored_data === null) {
			return null;
		}

		stored_data = JSON.parse(stored_data);

		if (stored_data.dashboardid !== this._data.dashboardid || stored_data.is_kiosk_mode === this._is_kiosk_mode) {
			return null;
		}

		for (const dashboard_page of this._dashboard_pages.keys()) {
			if (stored_data.dashboard_pageid === dashboard_page.getDashboardPageId()) {
				return dashboard_page;
			}
		}
		return null;
	}

	getSelectedDashboardPage() {
		return this._selected_dashboard_page;
	}

	save() {
		const data = {
			dashboardid: this._data.dashboardid ?? undefined,
			name: this._data.name,
			userid: this._data.userid ?? undefined,
			templateid: this._data.templateid ?? undefined,
			display_period: this._data.display_period,
			auto_start: this._data.auto_start,
			pages: []
		};

		let dashboard_pages = [];

		if (this._is_kiosk_mode) {
			for (const dashboard_page of this._dashboard_pages.keys()) {
				dashboard_pages.push(dashboard_page);
			}
		}
		else {
			for (const tab of this._tabs.getList().children) {
				dashboard_pages.push(this._tabs_dashboard_pages.get(tab));
			}
		}

		for (const dashboard_page of dashboard_pages) {
			data.pages.push(dashboard_page.save());
		}

		return data;
	}

	editProperties() {
		const properties = {
			template: this._data.templateid !== null ? 1 : undefined,
			userid: this._data.templateid === null ? this._data.userid : undefined,
			name: this._data.name,
			display_period: this._data.display_period,
			auto_start: this._data.auto_start
		};

		PopUp('dashboard.properties.edit', properties, {
			dialogueid: 'dashboard_properties',
			dialogue_class: 'modal-popup-generic'
		});
	}

	applyProperties() {
		const overlay = overlays_stack.getById('dashboard_properties');
		const form = overlay.$dialogue.$body[0].querySelector('form');
		const properties = getFormFields(form);

		overlay.setLoading();

		const busy_condition = this._createBusyCondition();

		return new Promise((resolve) => resolve(this._promiseApplyProperties(properties)))
			.then(() => {
				overlayDialogueDestroy(overlay.dialogueid);

				this.fire(DASHBOARD_EVENT_APPLY_PROPERTIES);
			})
			.catch((error) => {
				for (const el of form.parentNode.children) {
					if (el.matches('.msg-good, .msg-bad, .msg-warning')) {
						el.parentNode.removeChild(el);
					}
				}

				const message_box = (typeof error === 'object' && 'html_string' in error)
					? new DOMParser().parseFromString(error.html_string, 'text/html').body.firstElementChild
					: makeMessageBox('bad', [], t('Failed to update dashboard properties.'), true, false)[0];

				form.parentNode.insertBefore(message_box, form);
			})
			.finally(() => {
				overlay.unsetLoading();
				this._deleteBusyCondition(busy_condition);
			});
	}

	_promiseApplyProperties(properties) {
		properties.template = this._data.templateid !== null ? 1 : undefined;
		properties.name = properties.name.trim();

		const curl = new Curl('zabbix.php');

		curl.setArgument('action', 'dashboard.properties.check');

		return fetch(curl.getUrl(), {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify(properties)
		})
			.then((response) => response.json())
			.then((response) => {
				if ('errors' in response) {
					throw {html_string: response.errors};
				}

				this._data.name = properties.name;
				this._data.userid = this._data.templateid === null ? properties.userid : null;
				this._data.display_period = properties.display_period;
				this._data.auto_start = properties.auto_start === '1' ? '1' : '0';
			});
	}

	editDashboardPageProperties(properties = {}) {
		properties.dashboard_display_period = this._data.display_period;

		PopUp('dashboard.page.properties.edit', properties, {
			dialogueid: 'dashboard_page_properties',
			dialogue_class: 'modal-popup-generic'
		});
	}

	applyDashboardPageProperties() {
		const overlay = overlays_stack.getById('dashboard_page_properties');
		const form = overlay.$dialogue.$body[0].querySelector('form');
		const properties = getFormFields(form);

		overlay.setLoading();

		const busy_condition = this._createBusyCondition();

		return Promise.resolve()
			.then(() => this._promiseApplyDashboardPageProperties(properties, overlay.data))
			.then(() => {
				overlayDialogueDestroy(overlay.dialogueid);
			})
			.catch((error) => {
				for (const el of form.parentNode.children) {
					if (el.matches('.msg-good, .msg-bad, .msg-warning')) {
						el.parentNode.removeChild(el);
					}
				}

				const message_box = (typeof error === 'object' && 'html_string' in error)
					? new DOMParser().parseFromString(error.html_string, 'text/html').body.firstElementChild
					: makeMessageBox('bad', [], t('Failed to update dashboard page properties.'), true, false)[0];

				form.parentNode.insertBefore(message_box, form);
			})
			.finally(() => {
				overlay.unsetLoading();
				this._deleteBusyCondition(busy_condition);
			});
	}

	_promiseApplyDashboardPageProperties(properties, data) {
		properties.name = properties.name.trim();

		const curl = new Curl('zabbix.php');

		curl.setArgument('action', 'dashboard.page.properties.check');

		return fetch(curl.getUrl(), {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify(properties)
		})
			.then((response) => response.json())
			.then((response) => {
				if ('errors' in response) {
					throw {html_string: response.errors};
				}

				if (data.unique_id !== null) {
					const dashboard_page = this.getDashboardPage(data.unique_id);

					if (dashboard_page === null) {
						return;
					}

					const dashboard_page_name = dashboard_page.getName();

					dashboard_page.setName(properties.name);
					dashboard_page.setDisplayPeriod(properties.display_period);

					if (properties.name !== dashboard_page_name && !this._is_kiosk_mode) {
						this._updateTab(dashboard_page);
					}
				}
				else {
					const dashboard_page = this.addDashboardPage({
						dashboard_pageid: null,
						name: properties.name,
						display_period: properties.display_period,
						widgets: []
					});

					this._selectDashboardPage(dashboard_page);
				}
			});
	}

	editWidgetProperties(properties = {}, {new_widget_pos = null} = {}) {
		const overlay = PopUp('dashboard.widget.edit', {
			templateid: this._data.templateid ?? undefined,
			...properties
		}, {
			dialogueid: 'widget_properties',
			dialogue_class: 'modal-popup-generic'
		});

		overlay.xhr.then(() => {
			const form = overlay.$dialogue.$body[0].querySelector('form');
			const original_properties = overlay.data.original_properties;

			if (original_properties.unique_id === null) {
				this._new_widget_dashboard_page = this._selected_dashboard_page;
				this._new_widget_pos = new_widget_pos;

				const default_widget_size = this._widget_defaults[original_properties.type].size;

				if (this._new_widget_pos === null) {
					this._new_widget_pos_reserved = this._new_widget_dashboard_page.findFreePos(default_widget_size);
				}
				else {
					this._new_widget_pos_reserved = {
						...default_widget_size,
						...this._new_widget_pos
					};

					this._new_widget_pos_reserved.width = Math.min(this._new_widget_pos_reserved.width,
						this._max_columns - this._new_widget_pos_reserved.x
					);

					this._new_widget_pos_reserved.height = Math.min(this._new_widget_pos_reserved.height,
						this._max_rows - this._new_widget_pos_reserved.y
					);

					this._new_widget_pos_reserved = this._new_widget_dashboard_page.accommodatePos(
						this._new_widget_pos_reserved
					);
				}

				if (this._new_widget_pos_reserved === null) {
					for (const el of form.parentNode.children) {
						if (el.matches('.msg-warning')) {
							el.parentNode.removeChild(el);
						}
					}

					const message_box = makeMessageBox('warning',
						t('Cannot add widget: not enough free space on the dashboard.'), null, false
					)[0];

					form.parentNode.insertBefore(message_box, form);

					overlay.$btn_submit[0].disabled = true;
				}
			}

			try {
				new TabIndicators();
			}
			catch (error) {
			}
		});

		if (!this._is_edit_widget_properties_cancel_subscribed) {
			this._is_edit_widget_properties_cancel_subscribed = true;

			overlay.$dialogue[0].addEventListener('overlay.close', this._events.editWidgetPropertiesCancel,
				{once: true}
			);
		}
	}

	reloadWidgetProperties() {
		const overlay = overlays_stack.getById('widget_properties');
		const form = overlay.$dialogue.$body[0].querySelector('form');
		const fields = getFormFields(form);

		const properties = {
			type: fields.type,
			prev_type: overlay.data.original_properties.type,
			unique_id: overlay.data.original_properties.unique_id ?? undefined,
			dashboard_page_unique_id: overlay.data.original_properties.dashboard_page_unique_id ?? undefined
		};

		if (properties.type === properties.prev_type) {
			properties.name = fields.name;
			properties.view_mode = fields.show_header == 1
				? ZBX_WIDGET_VIEW_MODE_NORMAL
				: ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER;

			delete fields.type;
			delete fields.name;
			delete fields.show_header;

			properties.fields = JSON.stringify(fields);
		}

		this.editWidgetProperties(properties, {new_widget_pos: this._new_widget_pos});
	}

	applyWidgetProperties() {
		const overlay = overlays_stack.getById('widget_properties');
		const form = overlay.$dialogue.$body[0].querySelector('form');
		const fields = getFormFields(form);

		const templateid = this._data.templateid ?? undefined;
		const type = fields.type;
		const name = fields.name;
		const view_mode = fields.show_header == 1
			? ZBX_WIDGET_VIEW_MODE_NORMAL
			: ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER;

		delete fields.type;
		delete fields.name;
		delete fields.show_header;

		const dashboard_page = overlay.data.original_properties.dashboard_page_unique_id !== null
			? this.getDashboardPage(overlay.data.original_properties.dashboard_page_unique_id)
			: null;

		const widget = dashboard_page !== null
			? dashboard_page.getWidget(overlay.data.original_properties.unique_id)
			: null;

		const busy_condition = this._createBusyCondition();

		return Promise.resolve()
			.then(() => this._promiseDashboardWidgetCheck({templateid, type, name, view_mode, fields}))
			.then(() => this._promiseDashboardWidgetConfigure({templateid, type, view_mode, fields}))
			.then((configuration) => {
				overlayDialogueDestroy(overlay.dialogueid);

				if (widget !== null && widget.getType() === type) {
					widget.updateProperties({name, view_mode, fields, configuration});

					return;
				}

				if (this._widget_defaults[type].reference_field !== null) {
					fields[this._widget_defaults[type].reference_field] = this._createReference();
				}

				const widget_data = {
					type,
					name,
					view_mode,
					fields,
					configuration,
					widgetid: null,
					pos: widget === null ? this._new_widget_pos_reserved : widget.getPos(),
					is_new: widget === null,
					rf_rate: 0,
					unique_id: this._createUniqueId()
				};

				if (widget === null) {
					this._new_widget_dashboard_page.promiseScrollIntoView(widget_data.pos)
						.then(() => {
							this._new_widget_dashboard_page.addWidget(widget_data);
							this._new_widget_dashboard_page.resetWidgetPlaceholder();
							this._new_widget_dashboard_page = null;
							this._new_widget_pos = null;
							this._new_widget_pos_reserved = null;
						});
				}
				else {
					if (dashboard_page.getState() === DASHBOARD_PAGE_STATE_DESTROYED) {
						return;
					}

					dashboard_page.promiseScrollIntoView(widget_data.pos)
						.then(() => {
							dashboard_page.replaceWidget(widget, widget_data);
							dashboard_page.resetWidgetPlaceholder();
						});
				}
			})
			.catch((error) => {
				for (const el of form.parentNode.children) {
					if (el.matches('.msg-good, .msg-bad, .msg-warning')) {
						el.parentNode.removeChild(el);
					}
				}

				const message_box = (typeof error === 'object' && 'html_string' in error)
					? new DOMParser().parseFromString(error.html_string, 'text/html').body.firstElementChild
					: makeMessageBox('bad', [], t('Failed to update widget properties.'), true, false)[0];

				form.parentNode.insertBefore(message_box, form);
			})
			.finally(() => {
				overlay.unsetLoading();
				this._deleteBusyCondition(busy_condition);
			});
	}

	_promiseDashboardWidgetCheck({templateid, type, name, view_mode, fields}) {
		const fields_str = Object.keys(fields).length > 0 ? JSON.stringify(fields) : undefined;

		const curl = new Curl('zabbix.php');

		curl.setArgument('action', 'dashboard.widget.check');

		return fetch(curl.getUrl(), {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify({templateid, type, name, view_mode, fields: fields_str})
		})
			.then((response) => response.json())
			.then((response) => {
				if ('errors' in response) {
					throw {html_string: response.errors};
				}
			});
	}

	_promiseDashboardWidgetConfigure({templateid, type, view_mode, fields}) {
		const fields_str = Object.keys(fields).length > 0 ? JSON.stringify(fields) : undefined;

		const curl = new Curl('zabbix.php');

		curl.setArgument('action', 'dashboard.widget.configure');

		return fetch(curl.getUrl(), {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify({templateid, type, view_mode, fields: fields_str})
		})
			.then((response) => response.json())
			.then((response) => {
				return typeof response.configuration === 'object' ? response.configuration : {};
			});
	}

	_cancelEditingWidgetProperties() {
		this._selected_dashboard_page.resetWidgetPlaceholder();
	}

	_getDashboardPageActionsContextMenu(dashboard_page) {
		let menu = [];
		let menu_actions = [];

		if (this._can_edit_dashboards) {
			menu_actions.push({
				label: t('Copy'),
				clickCallback: () => this._storeDashboardPageDataCopy(dashboard_page.getDataCopy())
			});
		}

		if (this._is_edit_mode) {
			menu_actions.push({
				label: t('Delete'),
				disabled: this._dashboard_pages.size == 1,
				clickCallback: () => this.deleteDashboardPage(dashboard_page)
			});
		}

		if (menu_actions.length > 0) {
			menu.push({
				label: t('Actions'),
				items: menu_actions
			});
		}

		if (this._is_editable) {
			menu.push({
				items: [
					{
						label: t('Properties'),
						clickCallback: () => {
							if (!this._is_edit_mode) {
								this.setEditMode({is_internal_call: true});
							}

							this.editDashboardPageProperties({
								name: dashboard_page.getName(),
								display_period: dashboard_page.getDisplayPeriod(),
								unique_id: dashboard_page.getUniqueId()
							});
						}
					}
				]
			});
		}

		return menu;
	}

	// Dashboard view methods.

	_warnDashboardExhausted() {
		this._clearWarnings();

		this._warning_message_box = makeMessageBox('warning', [], sprintf(
			t('Cannot add dashboard page: maximum number of %1$d dashboard pages has been added.'),
			this._max_dashboard_pages
		), true, false);

		addMessage(this._warning_message_box);
	}

	_warnDashboardPageExhausted() {
		this._clearWarnings();

		this._warning_message_box = makeMessageBox(
			'warning', [], t('Cannot add widget: not enough free space on the dashboard.'), true, false
		);

		addMessage(this._warning_message_box);
	}

	_clearWarnings() {
		if (this._warning_message_box !== null) {
			this._warning_message_box.remove();
			this._warning_message_box = null;
		}
	}

	_isUserInteracting() {
		if (this._selected_dashboard_page.isUserInteracting()) {
			return true;
		}

		if (!this._is_kiosk_mode) {
			const has_aria_expanded = this._tabs
				.getList()
				.querySelector('.btn-dashboard-page-properties[aria-expanded="true"]') !== null;

			if (has_aria_expanded) {
				return true;
			}
		}

		return false;
	}

	_addTab(dashboard_page) {
		const tab = document.createElement('li');
		const tab_contents = document.createElement('div');
		const tab_contents_name = document.createElement('span');

		tab.appendChild(tab_contents);
		tab_contents.appendChild(tab_contents_name);

		const data = this._dashboard_pages.get(dashboard_page);
		const name = dashboard_page.getName();

		data.tab = tab;

		if (name !== '') {
			data.index = null;
			tab_contents_name.textContent = name;
			tab_contents_name.title = name;
		}
		else {
			let max_index = this._dashboard_pages.size - 1;

			for (const dashboard_page_data of this._dashboard_pages.values()) {
				if (dashboard_page_data.index !== null && dashboard_page_data.index > max_index) {
					max_index = dashboard_page_data.index;
				}
			}

			data.index = max_index + 1;

			const name = sprintf(t('Page %1$d'), data.index);

			tab_contents_name.textContent = name;
			tab_contents_name.title = name;
		}

		if (this._getDashboardPageActionsContextMenu(dashboard_page).length > 0) {
			const properties_button = document.createElement('button');

			properties_button.type = 'button';
			properties_button.title = t('Actions');
			properties_button.setAttribute('aria-expanded', 'false');
			properties_button.setAttribute('aria-haspopup', 'true');
			properties_button.classList.add('btn-dashboard-page-properties');

			tab_contents.append(properties_button);
		}

		this._tabs.insertItemBefore(tab);
		this._tabs_dashboard_pages.set(tab, dashboard_page);
	}

	_updateTab(dashboard_page) {
		const name = dashboard_page.getName();
		const data = this._dashboard_pages.get(dashboard_page);
		const tab_contents_name = data.tab.firstElementChild.firstElementChild;

		data.index = null;

		if (name !== '') {
			tab_contents_name.textContent = name;
			tab_contents_name.title = name;
		}
		else {
			const tab_index = [...this._tabs.getList().children].indexOf(data.tab) + 1;

			let max_index = this._dashboard_pages.size - 1;
			let is_tab_index_available = true;

			for (const dashboard_page_data of this._dashboard_pages.values()) {
				if (dashboard_page_data.index !== null) {
					if (dashboard_page_data.index === tab_index) {
						is_tab_index_available = false;
					}

					if (dashboard_page_data.index > max_index) {
						max_index = dashboard_page_data.index;
					}
				}
			}

			data.index = is_tab_index_available ? tab_index : max_index + 1;

			const name = sprintf(t('Page %1$d'), data.index);

			tab_contents_name.textContent = name;
			tab_contents_name.title = name;
		}
	}

	_deleteTab(dashboard_page) {
		const data = this._dashboard_pages.get(dashboard_page);

		this._tabs.removeItem(data.tab);
		this._tabs_dashboard_pages.delete(data.tab);
	}

	_selectTab(dashboard_page) {
		this._tabs.getList().querySelectorAll(`.${ZBX_STYLE_DASHBOARD_SELECTED_TAB}`).forEach((el) => {
			el.classList.remove(ZBX_STYLE_DASHBOARD_SELECTED_TAB);
		})

		const data = this._dashboard_pages.get(dashboard_page);

		data.tab.firstElementChild.classList.add(ZBX_STYLE_DASHBOARD_SELECTED_TAB);
		this._tabs.scrollItemIntoView(data.tab);
		this._updateNavigationButtons(dashboard_page);
	}

	_updateNavigationButtons(dashboard_page = null) {
		this._containers.navigation.classList.toggle(ZBX_STYLE_DASHBOARD_NAVIGATION_IS_SCROLLABLE,
			this._tabs.isScrollable()
		);

		if (dashboard_page !== null) {
			const tab = this._dashboard_pages.get(dashboard_page).tab;

			this._buttons.previous_page.disabled = tab.previousElementSibling === null;
			this._buttons.next_page.disabled = tab.nextElementSibling === null;
		}
	}

	_reserveHeaderLines(num_lines) {
		this._reserve_header_lines = num_lines;

		if (this._reserve_header_lines_timeout_id !== null) {
			clearTimeout(this._reserve_header_lines_timeout_id);
			this._reserve_header_lines_timeout_id = null;
		}

		let old_num_lines = 0;

		for (let i = 2; i > 0; i--) {
			if (this._containers.grid.classList.contains(`reserve-header-lines-${i}`)) {
				old_num_lines = i;
				break;
			}
		}

		if (num_lines > old_num_lines) {
			if (old_num_lines > 0) {
				this._containers.grid.classList.remove(`reserve-header-lines-${old_num_lines}`);
			}
			this._containers.grid.classList.add(`reserve-header-lines-${num_lines}`);
		}
		else if (num_lines < old_num_lines) {
			this._reserve_header_lines_timeout_id = setTimeout(() => {
				this._reserve_header_lines_timeout_id = null;

				this._containers.grid.classList.remove(`reserve-header-lines-${old_num_lines}`);

				if (num_lines > 0) {
					this._containers.grid.classList.add(`reserve-header-lines-${num_lines}`);
				}
			}, this._header_lines_steady_period);
		}
	}

	_keepSteadyHeaderLines() {
		if (this._reserve_header_lines_timeout_id !== null) {
			this._reserveHeaderLines(this._reserve_header_lines);
		}
	}

	_resetHeaderLines() {
		if (this._reserve_header_lines_timeout_id !== null) {
			clearTimeout(this._reserve_header_lines_timeout_id);
			this._reserve_header_lines_timeout_id = null;
		}

		this._containers.grid.classList.remove('reserve-header-lines-1', 'reserve-header-lines-2');
	}

	_createUniqueId() {
		return 'U' + (this._unique_id_index++).toString(36).toUpperCase().padStart(6, '0');
	}

	_createReference({used_references = null} = {}) {
		if (used_references === null) {
			used_references = this._getUsedReferences();
		}

		let reference;

		do {
			reference = '';

			for (let i = 0; i < 5; i++) {
				reference += String.fromCharCode(65 + Math.floor(Math.random() * 26));
			}
		}
		while (used_references.has(reference));

		return reference;
	}

	_getUsedReferences() {
		const used_references = new Set();

		for (const dashboard_page of this._dashboard_pages.keys()) {
			for (const widget of dashboard_page.getWidgets()) {
				const type = widget.getType();
				const fields = widget.getFields();

				if (this._widget_defaults[type].reference_field !== null) {
					used_references.add(fields[this._widget_defaults[type].reference_field]);
				}

				for (const reference_field of this._widget_defaults[type].foreign_reference_fields) {
					used_references.add(fields[reference_field]);
				}
			}
		}

		return used_references;
	}

	// Internal events management methods.

	_registerEvents() {
		let wrapper_scrollbar_width = 0;
		let user_interaction_animation_frame = null;

		this._events = {
			dashboardPageEdit: () => {
				this.setEditMode({is_internal_call: true});
			},

			dashboardPageWidgetAdd: (e) => {
				const new_widget_data = this.getStoredWidgetDataCopy();
				const new_widget_pos = e.detail.new_widget_pos;

				if (new_widget_data !== null) {
					const dashboard_page = this._selected_dashboard_page;

					let menu_was_cancelled = true;

					const menu = [
						{
							label: t('Actions'),
							items: [
								{
									label: t('Add widget'),
									clickCallback: () => {
										this.editWidgetProperties({}, {new_widget_pos});
										menu_was_cancelled = false;
									}
								},
								{
									label: t('Paste widget'),
									clickCallback: () => {
										this.pasteWidget(new_widget_data, {new_widget_pos});
										menu_was_cancelled = false;
									}
								}
							]
						}
					];

					const placeholder = e.detail.placeholder;
					const placeholder_event = new jQuery.Event(e.detail.mouse_event);

					placeholder_event.target = placeholder;

					jQuery(placeholder).menuPopup(menu, placeholder_event, {
						closeCallback: () => {
							if (menu_was_cancelled) {
								dashboard_page.resetWidgetPlaceholder();
							}
						}
					});
				}
				else {
					this.editWidgetProperties({}, {new_widget_pos});
				}
			},

			dashboardPageWidgetAddNew: () => {
				this.editWidgetProperties();
			},

			dashboardPageWidgetDelete: () => {
				this._clearWarnings();
			},

			dashboardPageWidgetPosition: () => {
				this._clearWarnings();
			},

			dashboardPageWidgetActions: (e) => {
				const menu = e.detail.widget.getActionsContextMenu({
					can_paste_widget: this.getStoredWidgetDataCopy() !== null
				});

				jQuery(e.detail.mouse_event.target).menuPopup(menu, new jQuery.Event(e.detail.mouse_event));
			},

			dashboardPageWidgetEdit: (e) => {
				const dashboard_page = e.detail.target;
				const widget = e.detail.widget;

				this.editWidgetProperties({
					type: widget.getType(),
					name: widget.getName(),
					view_mode: widget.getViewMode(),
					fields: JSON.stringify(widget.getFields()),
					unique_id: widget.getUniqueId(),
					dashboard_page_unique_id: dashboard_page.getUniqueId()
				});
			},

			dashboardPageWidgetCopy: (e) => {
				const widget = e.detail.widget;

				this._storeWidgetDataCopy(widget.getDataCopy({is_single_copy: true}));
			},

			dashboardPageWidgetPaste: (e) => {
				const widget = e.detail.widget;

				this.pasteWidget(this.getStoredWidgetDataCopy(), {widget});
			},

			dashboardPageAnnounceWidgets: () => {
				this._announceWidgets();
			},

			dashboardPageReserveHeaderLines: (e) => {
				this._reserveHeaderLines(e.detail.num_lines);
			},

			gridResize: () => {
				const wrapper = document.querySelector('.wrapper');

				if (wrapper.offsetWidth > wrapper.clientWidth) {
					wrapper_scrollbar_width = wrapper.offsetWidth - wrapper.clientWidth;

					this._buttons.next_page.style.marginRight = '0';
				}
				else {
					this._buttons.next_page.style.marginRight = `${wrapper_scrollbar_width}px`;
				}
			},

			tabsResize: () => {
				this._updateNavigationButtons();
			},

			tabsDragStart: () => {
				this._selected_dashboard_page.blockInteraction();
			},

			tabsDragEnd: () => {
				this._selected_dashboard_page.unblockInteraction();

				this._updateNavigationButtons();
			},

			tabsClick: (e) => {
				const tab = e.target.closest(`.${ZBX_STYLE_SORTABLE_ITEM}`);

				if (tab !== null && tab.parentNode.classList.contains(ZBX_STYLE_SORTABLE_LIST)) {
					const dashboard_page = this._tabs_dashboard_pages.get(tab);

					if (dashboard_page !== this._selected_dashboard_page) {
						this._selectDashboardPage(dashboard_page, {is_async: true});
					}
					else if (e.target.classList.contains('btn-dashboard-page-properties')) {
						jQuery(e.target).menuPopup(this._getDashboardPageActionsContextMenu(dashboard_page),
							new jQuery.Event(e)
						);
					}
				}
			},

			tabsKeyDown: (e) => {
				if (e.key === 'Enter') {
					const tab = e.target.closest(`.${ZBX_STYLE_SORTABLE_ITEM}`);

					if (tab !== null) {
						const dashboard_page = this._tabs_dashboard_pages.get(tab);

						if (dashboard_page !== this._selected_dashboard_page) {
							this._selectDashboardPage(dashboard_page, {is_async: true});
						}
						else if (e.target.classList.contains('btn-dashboard-page-properties')) {
							jQuery(e.target).menuPopup(this._getDashboardPageActionsContextMenu(dashboard_page),
								new jQuery.Event(e)
							);
						}
					}
				}
			},

			tabsPreviousPageClick: () => {
				const tab = this._dashboard_pages.get(this._selected_dashboard_page).tab;

				this._selectDashboardPage(this._tabs_dashboard_pages.get(tab.previousElementSibling), {is_async: true});
			},

			tabsNextPageClick: () => {
				const tab = this._dashboard_pages.get(this._selected_dashboard_page).tab;

				this._selectDashboardPage(this._tabs_dashboard_pages.get(tab.nextElementSibling), {is_async: true});
			},

			slideshowToggle: () => {
				if (this._is_edit_mode) {
					return;
				}

				if (this._isSlideshowRunning()) {
					this._stopSlideshow();
				}
				else {
					this._startSlideshow();
				}
			},

			userInteract: () => {
				if (user_interaction_animation_frame !== null) {
					cancelAnimationFrame(user_interaction_animation_frame);
				}

				user_interaction_animation_frame = requestAnimationFrame(() => {
					user_interaction_animation_frame = null;

					if (this._is_kiosk_mode) {
						this._keepSteadyHeaderLines();
					}

					if (!this._is_edit_mode) {
						if (this._isSlideshowRunning()) {
							this._keepSteadySlideshow();
						}
					}
				});
			},

			kioskModePreviousPageClick: () => {
				const dashboard_pages = [...this._dashboard_pages.keys()];
				const dashboard_page_index = dashboard_pages.indexOf(this._selected_dashboard_page);

				this._selectDashboardPage(
					dashboard_pages[dashboard_page_index > 0 ? dashboard_page_index - 1 : dashboard_pages.length - 1]
				);
			},

			kioskModeNextPageClick: () => {
				const dashboard_pages = [...this._dashboard_pages.keys()];
				const dashboard_page_index = dashboard_pages.indexOf(this._selected_dashboard_page);

				this._selectDashboardPage(
					dashboard_pages[dashboard_page_index < dashboard_pages.length - 1 ? dashboard_page_index + 1 : 0]
				);
			},

			timeSelectorRangeUpdate: (e, time_period) => {
				this._time_period = {
					from: time_period.from,
					from_ts: time_period.from_ts,
					to: time_period.to,
					to_ts: time_period.to_ts
				};

				for (const dashboard_page of this._dashboard_pages.keys()) {
					dashboard_page.setTimePeriod(this._time_period);
				}
			},

			editWidgetPropertiesCancel: () => {
				this._cancelEditingWidgetProperties();

				this._is_edit_widget_properties_cancel_subscribed = false;
			}
		};
	}

	_activateEvents() {
		if (!this._is_kiosk_mode) {
			new ResizeObserver(this._events.gridResize).observe(this._containers.grid);
			new ResizeObserver(this._events.tabsResize).observe(this._containers.navigation_tabs);

			this._tabs.on(SORTABLE_EVENT_DRAG_START, this._events.tabsDragStart);
			this._tabs.on(SORTABLE_EVENT_DRAG_END, this._events.tabsDragEnd);

			this._containers.navigation_tabs.addEventListener('click', this._events.tabsClick);
			this._containers.navigation_tabs.addEventListener('keydown', this._events.tabsKeyDown);

			this._buttons.previous_page.addEventListener('click', this._events.tabsPreviousPageClick);
			this._buttons.next_page.addEventListener('click', this._events.tabsNextPageClick);
		}

		if (this._buttons.slideshow !== null && !this._is_edit_mode && this._dashboard_pages.size > 1) {
			this._buttons.slideshow.addEventListener('click', this._events.slideshowToggle);
		}

		for (const event_name of ['mousemove', 'mousedown', 'keydown', 'wheel']) {
			window.addEventListener(event_name, this._events.userInteract);
		}

		if (this._is_kiosk_mode) {
			if (this._buttons.previous_page !== null) {
				this._buttons.previous_page.addEventListener('click', this._events.kioskModePreviousPageClick);
			}

			if (this._buttons.next_page !== null) {
				this._buttons.next_page.addEventListener('click', this._events.kioskModeNextPageClick);
			}
		}

		if (this._time_period !== null) {
			jQuery.subscribe('timeselector.rangeupdate', this._events.timeSelectorRangeUpdate);
		}
	}
}
