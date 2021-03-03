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
		dashboard,
		options
	}) {
		super(target);

		this._containers = containers;
		this._buttons = buttons;
		this._dashboard = {
			templateid: null,
			dashboardid: null,
			dynamic_hostid: null,
			...dashboard
		};
		this._options = options;

		this._init();
		this._registerEvents();
	}

	_init() {
		const sortable = document.createElement('div');

		this._containers.navigation_tabs.appendChild(sortable);
		this._tabs = new CSortable(sortable, {is_vertical: false});
		this._selected_tab = null;

		this._tabs_data = new Map();

		this._widget_defaults = {};
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

	addPage(data) {
		const page = new CDashboardPage($(this._containers.grid), {
			dashboard: {
				templateid: this._dashboard.templateid !== undefined ? this._dashboard.templateid : null,
				dashboardid: this._dashboard.dashboardid !== undefined ? this._dashboard.dashboardid : null,
				dynamic_hostid: this._dashboard.dynamic_hostid !== undefined ? this._dashboard.dynamic_hostid : null
			},
			options: this._options
		});

		page.setWidgetDefaults(this._widget_defaults);
		page.addWidgets(data.widgets);

		this._addTab(data.name, {page: page});
	}

	activate() {
		this._selectTab(this._tabs.getList().children[0]);

		return this.getSelectedPage().activate();
	}

	getSelectedPage() {
		return this._tabs_data.get(this._selected_tab).page;
	}


	_registerEvents() {

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

			timeSelectorRangeUpdate: (e, data) => {
				this._dashboard.time_selector = {
					...this._dashboard.time_selector,
					from: data.from,
					to: data.to,
					from_ts: data.from_ts,
					to_ts: data.to_ts
				};
			},

			windowResize: () => {
				this.getSelectedPage().fire(DASHBOARD_PAGE_EVENT_RESIZE);
			}
		};

		new ResizeObserver(this._events.tabsResize).observe(this._containers.navigation_tabs);

		this._tabs.on(SORTABLE_EVENT_DRAG_END, this._events.tabsDragEnd);

		this._containers.navigation_tabs.addEventListener('click', this._events.tabsClick);
		this._containers.navigation_tabs.addEventListener('keydown', this._events.tabsKeyDown);

		this._buttons.previous_page.addEventListener('click', this._events.previousPageClick);
		this._buttons.next_page.addEventListener('click', this._events.nextPageClick);

		if (this._dashboard.time_selector !== null) {
			jQuery.subscribe('timeselector.rangeupdate', this._events.timeSelectorRangeUpdate);
		}

		window.addEventListener('resize', this._events.windowResize);
	}







	getTimeSelector() {
		return this._dashboard.time_selector;
	}

	// =================================================================================================================
	// =================================================================================================================
	// =================================================================================================================
	// TODO: Temporary solution.

	addPages(pages) {
		for (const page of pages) {
			this.addPage(page);

			break;
		}

		return this;
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
