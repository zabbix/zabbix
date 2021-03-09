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


const ZBX_STYLE_DASHBOARD_SELECTED_TAB = 'selected-tab';

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
		web_layout_mode,
		time_period,
		dynamic_hostid
	}) {
		super(target);

		this._containers = {
			grid: containers.grid,
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
		this._web_layout_mode = web_layout_mode,
		this._time_period = time_period;
		this._dynamic_hostid = dynamic_hostid;

		this._init();
		this._initEvents();
	}

	_init() {
//		const sortable = document.createElement('div');

//		this._containers.navigation_tabs.appendChild(sortable);
//		this._tabs = new CSortable(sortable, {is_vertical: false});
//		this._selected_tab = null;

//		this._tabs_data = new Map();

		this._dashboard_pages = [];

		this._uniqid_index = 0;
	}

	_addTab(title, data) {
		const tab = document.createElement('li');
		const tab_contents = document.createElement('div');

		tab.appendChild(tab_contents);

		if (title !== '') {
			data.index = null;
			tab_contents.innerHTML = title;
		}
		else {
			let max_index = this._tabs_data.size;

			for (const tab_data of this._tabs_data.values()) {
				if (tab_data.index !== null && tab_data.index > max_index) {
					max_index = tab_data.index;
				}
			}

			data.index = max_index + 1;
			tab_contents.innerHTML = sprintf(t('Page %1$d'), data.index);
		}

		this._tabs.insertItemBefore(tab);
		this._tabs_data.set(tab, data);
	}

	_selectTab(tab) {
		if (tab == this._selected_tab) {
			return;
		}

		if (this._selected_tab !== null) {
			this._selected_tab.firstElementChild.classList.remove(ZBX_STYLE_DASHBOARD_SELECTED_TAB);
		}

		this._selected_tab = tab;
		this._selected_tab.firstElementChild.classList.add(ZBX_STYLE_DASHBOARD_SELECTED_TAB);
		this._tabs.scrollItemIntoView(this._selected_tab);

		this._updateNavigationButtons();
	}

	_updateNavigationButtons() {
		const is_scrollable = this._tabs.isScrollable();

		this._buttons.previous_page.style.display = is_scrollable ? 'inline-block' : 'none';
		this._buttons.next_page.style.display = is_scrollable ? 'inline-block' : 'none';

		this._buttons.previous_page.disabled = this._selected_tab === null
			|| this._selected_tab.previousSibling === null;

		this._buttons.next_page.disabled = this._selected_tab === null
			|| this._selected_tab.nextSibling === null;
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
			web_layout_mode: this._web_layout_mode,
			time_period: this._time_period,
			dynamic_hostid: this._dynamic_hostid
		});

		for (const widget_data of widgets) {
			dashboard_page.addWidget({
				...widget_data,
				uniqueid: this._createUniqueId(),
				is_new: false
			});
		}

		this._dashboard_pages.push(dashboard_page);

		// this._addTab(data.name, {page: page});
	}

	setDynamicHost(dynamic_hostid) {
		this._dynamic_hostid = dynamic_hostid;

		for (const dashboard_page of this._dashboard_pages) {
			dashboard_page.setDynamicHost(this._dynamic_hostid);
		}
	}

	_createUniqueId() {
		return 'U' + (this._uniqid_index++).toString(36).toUpperCase().padStart(6, '0');
	}

	activate() {
		// this._selectTab(this._tabs.getList().children[0]);

		this._activatePage(this._dashboard_pages[0]);
	}

	_activatePage(dashboard_page) {
		if (dashboard_page.getState() === DASHBOARD_PAGE_STATE_INITIAL) {
			dashboard_page.start();
		}
		if (dashboard_page.getState() === DASHBOARD_PAGE_STATE_INACTIVE) {
			dashboard_page.activate();
			dashboard_page.on(DASHBOARD_PAGE_EVENT_RESERVE_HEADER_LINES, this._events.reserveHeaderLines);
		}
	}

	_deactivatePage(dashboard_page) {
		if (dashboard_page.getState() === DASHBOARD_PAGE_STATE_ACTIVE) {
			dashboard_page.deactivate();
			dashboard_page.off(DASHBOARD_PAGE_EVENT_RESERVE_HEADER_LINES, this._events.reserveHeaderLines);
		}
	}

	_destroyPage(dashboard_page) {
		this._deactivatePage(dashboard_page);
		dashboard_page.destroy();
	}

	getSelectedPage() {
		return this._tabs_data.get(this._selected_tab).page;
	}


	_initEvents() {
		let resize_timeout_id = null;
		let reserve_header_lines_timeout_id = null;

		this._events = {
			tabsResize: () => {
				this._updateNavigationButtons();
			},

			tabsDragEnd: () => {
				this._updateNavigationButtons();
			},

			tabsClick: (e) => {
				const tab = e.target.closest(`.${ZBX_STYLE_SORTABLE_ITEM}`);

				if (tab !== null) {
					this._selectTab(tab);
				}
			},

			tabsKeyDown: (e) => {
				if (e.key === 'Enter') {
					const tab = e.target.closest(`.${ZBX_STYLE_SORTABLE_ITEM}`);

					if (tab !== null) {
						this._selectTab(tab);
					}
				}
			},

			previousPageClick: () => {
				this._selectTab(this._selected_tab.previousSibling);
			},

			nextPageClick: () => {
				this._selectTab(this._selected_tab.nextSibling);
			},

			timeSelectorRangeUpdate: (e, time_period) => {
				for (const dashboard_page of this._dashboard_pages) {
					dashboard_page.setTimePeriod({
						from: time_period.from,
						from_ts: time_period.from_ts,
						to: time_period.to,
						to_ts: time_period.to_ts
					});
				}
			},

			resize: () => {
				window.addEventListener('resize', () => {
					if (resize_timeout_id != null) {
						clearTimeout(resize_timeout_id);
					}

					resize_timeout_id = setTimeout(() => {
						resize_timeout_id = null;

						for (const dashboard_page of this._dashboard_pages) {
							dashboard_page.resize();
						}
					}, 200);
				});
			},

			reserveHeaderLines: (e) => {
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
			}
		};

//		new ResizeObserver(this._events.tabsResize).observe(this._containers.navigation_tabs);

//		this._tabs.on(SORTABLE_EVENT_DRAG_END, this._events.tabsDragEnd);

//		this._containers.navigation_tabs.addEventListener('click', this._events.tabsClick);
//		this._containers.navigation_tabs.addEventListener('keydown', this._events.tabsKeyDown);

//		this._buttons.previous_page.addEventListener('click', this._events.previousPageClick);
//		this._buttons.next_page.addEventListener('click', this._events.nextPageClick);

		if (this._time_selector !== null) {
			jQuery.subscribe('timeselector.rangeupdate', this._events.timeSelectorRangeUpdate);
		}

		window.addEventListener('resize', this._events.resize);
	}

	// =================================================================================================================
	// =================================================================================================================
	// =================================================================================================================
	// TODO: Temporary solution.

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
		return this.getSelectedPage().isDashboardUpdated();
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
		return this.getSelectedPage().isEditMode();
	}

	addAction(hook_name, function_to_call, uniqueid = null, options = {}) {
		return this.getSelectedPage().addAction(hook_name, function_to_call, uniqueid, options);
	}
}
