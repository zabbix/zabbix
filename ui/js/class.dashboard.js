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


const ZBX_STYLE_DASHBRD_IS_MULTIPAGE = 'dashbrd-is-multipage';
const ZBX_STYLE_DASHBRD_IS_EDIT_MODE = 'dashbrd-is-edit-mode';
const ZBX_STYLE_DASHBRD_NAVIGATION_IS_SCROLLABLE = 'is-scrollable';
const ZBX_STYLE_DASHBRD_SELECTED_TAB = 'selected-tab';

const DASHBIOARD_CLIPBOARD_TYPE_WIDGET = 'widget';
const DASHBIOARD_CLIPBOARD_TYPE_DASHBOARD_PAGE = 'dashboard-page';

const DASHBOARD_EVENT_EDIT_MODE = 'edit-mode';

class CDashboard extends CBaseComponent {

	constructor(target, {
		containers,
		buttons,
		data,
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
		web_layout_mode,
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
		this._web_layout_mode = web_layout_mode,
		this._time_period = time_period;
		this._dynamic_hostid = dynamic_hostid;

		this._init();
		this._registerEvents();
	}

	_init() {
		this._dashboard_pages = new Map();
		this._selected_dashboard_page = null;

		this._original_properties = {
			name: this._data.name,
			userid: this._data.userid,
			display_period: this._data.display_period,
			auto_start: this._data.auto_start
		};

		this._uniqid_index = 0;

		if (this._web_layout_mode != ZBX_LAYOUT_KIOSKMODE) {
			const sortable = document.createElement('div');

			this._containers.navigation_tabs.appendChild(sortable);

			this._tabs = new CSortable(sortable, {is_vertical: false});
			this._tabs_dashboard_pages = new Map();
		}
	}

	activate() {
		this._selectDashboardPage(this._dashboard_pages.keys().next().value);
		this._announceWidgets();
	}

	getData() {
		return this._data;
	}

	getDashboardPage(unique_id) {
		for (const dashboard_page of this._dashboard_pages.keys()) {
			if (dashboard_page.getUniqueId() === unique_id) {
				return dashboard_page;
			}
		}

		return null;
	}

	isUpdated() {
		for (const [name, value] of Object.entries(this._original_properties)) {
			if (value != this._data[name]) {
				return true;
			}
		}

		for (const dashboard_page of this._dashboard_pages.keys()) {
			if (dashboard_page.isUpdated()) {
				return true;
			}
		}

		return false;
	}

	setDynamicHost(dynamic_hostid) {
		this._dynamic_hostid = dynamic_hostid;

		for (const dashboard_page of this._dashboard_pages.keys()) {
			dashboard_page.setDynamicHost(this._dynamic_hostid);
		}
	}

	setEditMode() {
		this._is_edit_mode = true;

		for (const dashboard_page of this._dashboard_pages.keys()) {
			dashboard_page.setEditMode();
		}

		this._target.classList.add(ZBX_STYLE_DASHBRD_IS_EDIT_MODE);
	}

	editProperties() {
		const options = {
			template: this._data.templateid !== null ? 1 : undefined,
			userid: this._data.templateid === null ? this._data.userid : undefined,
			name: this._data.name,
			display_period: this._data.display_period,
			auto_start: this._data.auto_start
		};

		PopUp('dashboard.properties.edit', options, 'dashboard_properties', document.activeElement);
	}

	promiseApplyProperties(data) {
		data.name = data.name.trim();

		const curl = new Curl('zabbix.php', false);

		curl.setArgument('action', 'dashboard.properties.check');

		return fetch(curl.getUrl(), {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
			},
			body: urlEncodeData(data)
		})
			.then((response) => response.json())
			.then((response) => {
				if ('errors' in response) {
					throw {html_string: response.errors};
				}

				this._data.name = data.name;
				this._data.userid = this._data.templateid === null ? data.userid : null;
				this._data.display_period =data.display_period;
				this._data.auto_start = (data.auto_start === '1') ? '1' : '0';
			});
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
			web_layout_mode: this._web_layout_mode,
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

		if (this._web_layout_mode != ZBX_LAYOUT_KIOSKMODE) {
			this._addTab(dashboard_page);
		}

		this._target.classList.toggle(ZBX_STYLE_DASHBRD_IS_MULTIPAGE, this._dashboard_pages.size > 1);
	}

	deleteDashboardPage(dashboard_page) {
		if (dashboard_page.getState() === DASHBOARD_PAGE_STATE_INITIAL) {
			//? DASHBOARD_PAGE_STATE_ACTIVE
		}
		// TODO ...

		this._target.classList.toggle(ZBX_STYLE_DASHBRD_IS_MULTIPAGE, this._dashboard_pages.size > 1);
	}

	_activatePage(dashboard_page) {
		dashboard_page.activate();
		dashboard_page
			.on(DASHBOARD_PAGE_EVENT_ANNOUNCE_WIDGETS, this._events.dashboardPageAnnounceWidgets)
			.on(DASHBOARD_PAGE_EVENT_RESERVE_HEADER_LINES, this._events.dashboardPageReserveHeaderLines)
			.on(DASHBOARD_PAGE_EVENT_WIDGET_COPY, this._events.dashboardPageWidgetCopy);
	}

	_deactivatePage(dashboard_page) {
		dashboard_page.deactivate();
		dashboard_page
			.off(DASHBOARD_PAGE_EVENT_ANNOUNCE_WIDGETS, this._events.dashboardPageAnnounceWidgets)
			.off(DASHBOARD_PAGE_EVENT_RESERVE_HEADER_LINES, this._events.dashboardPageReserveHeaderLines)
			.off(DASHBOARD_PAGE_EVENT_WIDGET_COPY, this._events.dashboardPageWidgetCopy);
	}

	_selectDashboardPage(dashboard_page) {
		if (this._selected_dashboard_page !== null) {
			this._deactivatePage(this._selected_dashboard_page);
		}

		this._selected_dashboard_page = dashboard_page;

		if (this._selected_dashboard_page.getState() === DASHBOARD_PAGE_STATE_INITIAL) {
			this._selected_dashboard_page.start();
		}

		this._activatePage(this._selected_dashboard_page);

		if (this._web_layout_mode != ZBX_LAYOUT_KIOSKMODE) {
			this._selectTab(this._selected_dashboard_page);
		}
	}

	_announceWidgets() {
		const dashboard_pages = this._dashboard_pages.keys();

		for (const dashboard_page of dashboard_pages) {
			dashboard_page.announceWidgets(dashboard_pages);
		}
	}

	_addTab(dashboard_page) {
		const tab = document.createElement('li');
		const tab_contents = document.createElement('div');

		tab.appendChild(tab_contents);

		const data = this._dashboard_pages.get(dashboard_page);
		const name = dashboard_page.getData().name;

		data.tab = tab;

		if (name !== '') {
			data.index = null;
			tab_contents.innerHTML = name;
		}
		else {
			let max_index = this._dashboard_pages.size - 1;

			for (const dashboard_page_data of this._dashboard_pages) {
				if (dashboard_page_data.index !== null && dashboard_page_data.index > max_index) {
					max_index = dashboard_page_data.index;
				}
			}

			data.index = max_index + 1;
			tab_contents.innerHTML = sprintf(t('Page %1$d'), data.index);
		}

		this._tabs.insertItemBefore(tab);
		this._tabs_dashboard_pages.set(tab, dashboard_page);
	}

	_selectTab(dashboard_page) {
		this._tabs.getList().querySelectorAll(`.${ZBX_STYLE_DASHBRD_SELECTED_TAB}`).forEach((el) => {
			el.classList.remove(ZBX_STYLE_DASHBRD_SELECTED_TAB);
		})

		const data = this._dashboard_pages.get(dashboard_page);

		data.tab.firstElementChild.classList.add(ZBX_STYLE_DASHBRD_SELECTED_TAB);
		this._tabs.scrollItemIntoView(data.tab);
		this._updateNavigationButtons();
	}

	_updateNavigationButtons() {
		this._containers.navigation.classList.toggle(ZBX_STYLE_DASHBRD_NAVIGATION_IS_SCROLLABLE,
			this._tabs.isScrollable()
		);

		const tab = this._dashboard_pages.get(this._selected_dashboard_page).tab;

		this._buttons.previous_page.disabled = this._selected_dashboard_page === null
			|| tab.previousElementSibling === null;

		this._buttons.next_page.disabled = this._selected_dashboard_page === null
			|| tab.nextElementSibling === null;
	}

	_createUniqueId() {
		return 'U' + (this._uniqid_index++).toString(36).toUpperCase().padStart(6, '0');
	}

	storeWidgetCopy(data) {
		localStorage.setItem('dashboard.clipboard', JSON.stringify({
			type: DASHBIOARD_CLIPBOARD_TYPE_WIDGET,
			data: data
		}));
	}

	getStoredWidgetCopy() {
		let clipboard = localStorage.getItem('dashboard.clipboard');

		if (clipboard === null) {
			return null;
		}

		clipboard = JSON.parse(clipboard);

		if (clipboard.type !== DASHBIOARD_CLIPBOARD_TYPE_WIDGET) {
			return null;
		}

		return (clipboard.data.dashboard.templateid === this._data.templateid) ? clipboard.data : null;
	}

	_registerEvents() {
		let resize_timeout_id = null;
		let reserve_header_lines_timeout_id = null;

		this._events = {
			dashboardPageAnnounceWidgets: () => {
				this._announceWidgets();
			},

			dashboardPageReserveHeaderLines: (e) => {
				if (reserve_header_lines_timeout_id !== null) {
					clearTimeout(reserve_header_lines_timeout_id);
					reserve_header_lines_timeout_id = null;
				}

				const new_num_header_lines = e.detail.num_header_lines;
				let old_num_header_lines = 0;

				for (let i = 2; i > 0; i--) {
					if (this._containers.grid.classList.contains(`reserve-header-lines-${i}`)) {
						old_num_header_lines = i;
						break;
					}
				}

				if (new_num_header_lines > old_num_header_lines) {
					if (old_num_header_lines > 0) {
						this._containers.grid.classList.remove(`reserve-header-lines-${old_num_header_lines}`);
					}
					this._containers.grid.classList.add(`reserve-header-lines-${new_num_header_lines}`);
				}
				else if (new_num_header_lines < old_num_header_lines) {
					reserve_header_lines_timeout_id = setTimeout(() => {
						reserve_header_lines_timeout_id = null;

						this._containers.grid.classList.remove(`reserve-header-lines-${old_num_header_lines}`);

						if (new_num_header_lines > 0) {
							this._containers.grid.classList.add(`reserve-header-lines-${new_num_header_lines}`);
						}
					}, 2000);
				}
			},

			dashboardPageWidgetCopy: (e) => {
				this.storeWidgetCopy(e.detail.data);
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

			windowResize: () => {
				window.addEventListener('resize', () => {
					if (resize_timeout_id != null) {
						clearTimeout(resize_timeout_id);
					}

					resize_timeout_id = setTimeout(() => {
						resize_timeout_id = null;

						for (const dashboard_page of this._dashboard_pages.keys()) {
							dashboard_page.resize();
						}
					}, 200);
				});
			},

			tabsResize: () => {
				this._updateNavigationButtons();
			},

			tabsDragEnd: () => {
				this._updateNavigationButtons();
			},

			tabsClick: (e) => {
				const tab = e.target.closest(`.${ZBX_STYLE_SORTABLE_ITEM}`);

				if (tab !== null) {
					const dashboard_page = this._tabs_dashboard_pages.get(tab);

					if (dashboard_page !== this._selected_dashboard_page) {
						this._selectDashboardPage(dashboard_page);
					}
				}
			},

			tabsKeyDown: (e) => {
				if (e.key === 'Enter') {
					const tab = e.target.closest(`.${ZBX_STYLE_SORTABLE_ITEM}`);

					if (tab !== null) {
						const dashboard_page = this._tabs_dashboard_pages.get(tab);

						if (dashboard_page !== this._selected_dashboard_page) {
							this._selectDashboardPage(dashboard_page);
						}
					}
				}
			},

			previousPageClick: () => {
				const tab = this._dashboard_pages.get(this._selected_dashboard_page).tab;

				this._selectDashboardPage(this._tabs_dashboard_pages.get(tab.previousElementSibling));
			},

			nextPageClick: () => {
				const tab = this._dashboard_pages.get(this._selected_dashboard_page).tab;

				this._selectDashboardPage(this._tabs_dashboard_pages.get(tab.nextElementSibling));
			}
		};

		if (this._time_period !== null) {
			jQuery.subscribe('timeselector.rangeupdate', this._events.timeSelectorRangeUpdate);
		}

		window.addEventListener('resize', this._events.windowResize);

		if (this._web_layout_mode != ZBX_LAYOUT_KIOSKMODE) {
			new ResizeObserver(this._events.tabsResize).observe(this._containers.navigation_tabs);

			this._tabs.on(SORTABLE_EVENT_DRAG_END, this._events.tabsDragEnd);

			this._containers.navigation_tabs.addEventListener('click', this._events.tabsClick);
			this._containers.navigation_tabs.addEventListener('keydown', this._events.tabsKeyDown);

			this._buttons.previous_page.addEventListener('click', this._events.previousPageClick);
			this._buttons.next_page.addEventListener('click', this._events.nextPageClick);
		}
	}


	// =================================================================================================================
	// =================================================================================================================
	// =================================================================================================================
	// TODO: Temporary solution.

	getSelectedPage() {
//		return this._tabs_data.get(this._selected_tab).page;
	}


	getDashboardData() {
		return this.getSelectedPage().getDashboardData();
	}

	getWidgets() {
		return this.getSelectedPage().getWidgets();
	}

	getOptions() {
		return this._options;
	}

	deactivate() {
		return this.getSelectedPage().activate();
	}

	getCopiedWidget() {
		return this.getSelectedPage().getCopiedWidget();
	}

	updateDynamicHost(hostid) {
		return this.getSelectedPage().updateDynamicHost(hostid);
	}

	setWidgetDefaults(defaults) {
		this._widget_defaults = defaults;
	}

	addWidgets(widgets) {
		return this.getSelectedPage().addWidgets(widgets);
	}

	addNewWidget(trigger_element, pos) {
		return this.getSelectedPage().addNewWidget(trigger_element, pos);
	}

	setWidgetRefreshRate(widgetid, rf_rate) {
		return this.getSelectedPage().setWidgetRefreshRate(widgetid, rf_rate);
	}

	refreshWidget(widgetid) {
		return this.getSelectedPage().refreshWidget(widgetid);
	}

	pauseWidgetRefresh(widgetid) {
		return this.getSelectedPage().pauseWidgetRefresh(widgetid);
	}

	unpauseWidgetRefresh(widgetid) {
		return this.getSelectedPage().unpauseWidgetRefresh(widgetid);
	}

	setWidgetStorageValue(uniqueid, field, value) {
		return this.getSelectedPage().setWidgetStorageValue(uniqueid, field, value);
	}

	editDashboard() {
		return this.getSelectedPage().editDashboard();
	}

	isDashboardUpdated() {
		// => isUpdated()
	}

	saveDashboard() {
		return this.getSelectedPage().saveDashboard();
	}

	copyWidget(widget) {
		return this.getSelectedPage().copyWidget(widget);
	}

	pasteWidget(widget, pos) {
		return this.getSelectedPage().pasteWidget(widget, pos);
	}

	deleteWidget(widget) {
		return this.getSelectedPage().deleteWidget(widget);
	}

	updateWidgetConfigDialogue() {
		return this.getSelectedPage().updateWidgetConfigDialogue();
	}

	getWidgetsBy(key, value) {
		return this.getSelectedPage().getWidgetsBy(key, value);
	}

	registerDataExchange(obj) {
		return this.getSelectedPage().registerDataExchange(obj);
	}

	widgetDataShare(widget, data_name, data) {
		return this._selected_page.widgetDataShare(widget, data_name, data);
	}

	callWidgetDataShare() {
		return this.getSelectedPage().callWidgetDataShare();
	}

	makeReference() {
		return this.getSelectedPage().makeReference();
	}

	isEditMode() {
		return this._is_edit_mode;
	}

	addAction(hook_name, function_to_call, uniqueid = null, options = {}) {
		return this.getSelectedPage().addAction(hook_name, function_to_call, uniqueid, options);
	}
}
