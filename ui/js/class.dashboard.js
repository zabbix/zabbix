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
		this._dashboard = dashboard;
		this._options = options;

		this._init();
		this._registerEvents();
	}

	_init() {
		const div = document.createElement('div');
		this._containers.navigation_tabs.appendChild(div);
		this._tabs = new CSortable(div, {is_vertical: false});
		this._tabs_data = new Map();
		this._selected_tab = null;

		// TODO: Temporary solution.
		this._selected_page = new CDashboardPage($(this._containers.grid), {
			dashboard: this._dashboard,
			options: this._options
		});
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

		this._buttons.previous_page.disabled = (this._selected_tab === null
				|| this._selected_tab.previousSibling === null);

		this._buttons.next_page.disabled = (this._selected_tab === null
				|| this._selected_tab.nextSibling === null);
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
			}
		};

		new ResizeObserver(this._events.tabsResize).observe(this._containers.navigation_tabs);

		this._tabs.on(SORTABLE_EVENT_DRAG_END, this._events.tabsDragEnd);

		this._containers.navigation_tabs.addEventListener('click', this._events.tabsClick);
		this._containers.navigation_tabs.addEventListener('keydown', this._events.tabsKeyDown);

		this._buttons.previous_page.addEventListener('click', this._events.previousPageClick);
		this._buttons.next_page.addEventListener('click', this._events.nextPageClick);
	}

	addPages(pages) {
		for (const page of pages) {
			this.addPage(page);
		}

		return this;
	}

	addPage(page) {
		this._addTab(page.name, {dashboard_pageid: page.dashboard_pageid});

		// TODO: Temporary solution.
		if (this._tabs_data.size == 1) {
			this._selected_page.addWidgets(page.widgets);

			this._selectTab(this._tabs.getList().children[0]);
		}
	}

	// TODO: Temporary solution.

	activate() {
		return this._selected_page.activate();
	}

	getDashboardData() {
		return this._selected_page.getDashboardData();
	}

	getWidgets() {
		return this._selected_page.getWidgets();
	}

	getOptions() {
		return this._options;
	}

	getCopiedWidget() {
		return this._selected_page.getCopiedWidget();
	}

	updateDynamicHost(hostid) {
		return this._selected_page.updateDynamicHost(hostid);
	}

	setWidgetDefaults(defaults) {
		return this._selected_page.setWidgetDefaults(defaults);
	}

	addWidgets(widgets) {
		return this._selected_page.addWidgets(widgets);
	}

	addNewWidget(trigger_element, pos) {
		return this._selected_page.addNewWidget(trigger_element, pos);
	}

	setWidgetRefreshRate(widgetid, rf_rate) {
		return this._selected_page.setWidgetRefreshRate(widgetid, rf_rate);
	}

	refreshWidget(widgetid) {
		return this._selected_page.refreshWidget(widgetid);
	}

	pauseWidgetRefresh(widgetid) {
		return this._selected_page.pauseWidgetRefresh(widgetid);
	}

	unpauseWidgetRefresh(widgetid) {
		return this._selected_page.unpauseWidgetRefresh(widgetid);
	}

	setWidgetStorageValue(uniqueid, field, value) {
		return this._selected_page.setWidgetStorageValue(uniqueid, field, value);
	}

	editDashboard() {
		return this._selected_page.editDashboard();
	}

	isDashboardUpdated() {
		return this._selected_page.isDashboardUpdated();
	}

	saveDashboard(callback) {
		return this._selected_page.saveDashboard(callback);
	}

	copyWidget(widget) {
		return this._selected_page.copyWidget(widget);
	}

	pasteWidget(widget, pos) {
		return this._selected_page.pasteWidget(widget, pos);
	}

	deleteWidget(widget) {
		return this._selected_page.deleteWidget(widget);
	}

	updateWidgetConfigDialogue() {
		return this._selected_page.updateWidgetConfigDialogue();
	}

	getWidgetsBy(key, value) {
		return this._selected_page.getWidgetsBy(key, value);
	}

	registerDataExchange(obj) {
		return this._selected_page.registerDataExchange(obj);
	}

	widgetDataShare(widget, data_name) {
		return this._selected_page.widgetDataShare(widget, data_name);
	}

	callWidgetDataShare() {
		return this._selected_page.callWidgetDataShare();
	}

	makeReference() {
		return this._selected_page.makeReference();
	}

	isEditMode() {
		return this._selected_page.isEditMode();
	}

	addAction(hook_name, function_to_call, uniqueid = null, options = {}) {
		return this._selected_page.addAction(hook_name, function_to_call, uniqueid, options);
	}
}
