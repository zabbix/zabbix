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
const ZBX_STYLE_DASHBRD_POSITIONING = 'dashbrd-positioning';

const DASHBOARD_STATE_INITIAL = 'initial';
const DASHBOARD_STATE_ACTIVE = 'active';

const DASHBOARD_CLIPBOARD_TYPE_WIDGET = 'widget';
const DASHBOARD_CLIPBOARD_TYPE_DASHBOARD_PAGE = 'dashboard-page';

const DASHBOARD_EVENT_EDIT = 'edit';
const DASHBOARD_EVENT_APPLY_PROPERTIES = 'apply-properties';

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
		this._is_kiosk_mode = is_kiosk_mode,
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

		this._new_widget_dashboard_page = null;
		this._new_widget_pos = null;

		this._grid_min_rows = 0;

		this._reserve_header_lines_timeout_id = null;
		this._is_edit_widget_properties_cancel_subscribed = false;

		if (!this._is_kiosk_mode) {
			this._initTabs();
		}

		if (this._is_edit_mode) {
			this._initWidgetPlaceholder();
		}
	}

	activate() {
		if (this._dashboard_pages.size == 0) {
			throw new Error('Cannot activate dashboard without dashboard pages.');
		}

		this._state = DASHBOARD_STATE_ACTIVE;

		this._selectDashboardPage(this._dashboard_pages.keys().next().value);
		this._announceWidgets();

		if (this._is_edit_mode) {
			this._activateWidgetPlaceholder();

			this._target.classList.add(ZBX_STYLE_DASHBRD_IS_EDIT_MODE);
		}
	}

	getData() {
		return this._data;
	}

	addNewWidget() {
		this.editWidgetProperties();
	}

	addNewDashboardPage() {
		alert(1);
		// TODO
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

		this._initWidgetPlaceholder();
		this._activateWidgetPlaceholder();

		for (const dashboard_page of this._dashboard_pages.keys()) {
			if (!dashboard_page.isEditMode()) {
				dashboard_page.setEditMode();
			}
		}

		this._resetHeaderLines();
		this._target.classList.add(ZBX_STYLE_DASHBRD_IS_EDIT_MODE);
	}

	editProperties() {
		const properties = {
			template: this._data.templateid !== null ? 1 : undefined,
			userid: this._data.templateid === null ? this._data.userid : undefined,
			name: this._data.name,
			display_period: this._data.display_period,
			auto_start: this._data.auto_start
		};

		PopUp('dashboard.properties.edit', properties, 'dashboard_properties', document.activeElement);
	}

	applyProperties() {
		const overlay = overlays_stack.getById('dashboard_properties');
		const form = overlay.$dialogue.$body[0].querySelector('form');
		const properties = getFormFields(form);

		overlay.setLoading();

		return new Promise((resolve) => resolve())
			.then(() => ZABBIX.Dashboard.promiseApplyProperties(properties))
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
					: makeMessageBox('bad', [], t('Cannot update dashboard properties.'), true, false)[0];

				form.parentNode.insertBefore(message_box, form);
			})
			.finally(() => overlay.unsetLoading());
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
				this._data.display_period = data.display_period;
				this._data.auto_start = (data.auto_start === '1') ? '1' : '0';
			});
	}

	editWidgetProperties(properties = {}, {new_widget_pos = null} = {}) {
		const overlay = PopUp('dashboard.widget.edit', {
			templateid: this._data.templateid ?? undefined,
			...properties
		}, 'widget_properties', document.activeElement);

		overlay.xhr.then(() => {
			const original_properties = overlay.data.original_properties;
			const dialogue_stick_to_top = this._widget_defaults[original_properties.type].dialogue_stick_to_top;

			if (dialogue_stick_to_top !== overlay.$dialogue[0].classList.contains('sticked-to-top')) {
				overlay.$dialogue[0].classList.toggle('sticked-to-top', dialogue_stick_to_top);
				overlay.centerDialog();
			}

			if (original_properties.unique_id === null) {
				this._new_widget_dashboard_page = this._selected_dashboard_page;

				const default_widget_size = this._widget_defaults[original_properties.type].size;

				if (new_widget_pos === null) {
					this._new_widget_pos = this._new_widget_dashboard_page.findFreePos(default_widget_size);
				}
				else {
					this._new_widget_pos = this._new_widget_dashboard_page.accommodatePos({
						...default_widget_size,
						...new_widget_pos
					});
				}

				if (this._new_widget_pos === null) {
					overlay.$dialogue.$body.find('.msg-warning').remove();
					overlay.$dialogue.$body.prepend(makeMessageBox(
						'warning', t('Cannot add widget: not enough free space on the dashboard.'), null, false
					));

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
			$.subscribe('overlay.close', this._events.editWidgetPropertiesCancel);
		}
	}

	reloadWidgetProperties() {
		const overlay = overlays_stack.getById('widget_properties');
		const form = overlay.$dialogue.$body[0].querySelector('form');
		const fields = getFormFields(form);

		const properties = {
			type: fields.type,
			prev_type: overlay.data.original_properties.type,
			unique_id: overlay.data.original_properties.unique_id,
			dashboard_page_unique_id: overlay.data.original_properties.dashboard_page_unique_id
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

		this.editWidgetProperties(properties);
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

		return new Promise((resolve) => resolve())
			.then(() => this._promiseDashboardWidgetCheck({templateid, type, name, view_mode, fields}))
			.then(() => this._promiseDashboardWidgetConfigure({templateid, type, view_mode, fields}))
			.then((configuration) => {
				overlayDialogueDestroy(overlay.dialogueid);

				this._resetHeaderLines();

				if (widget !== null && widget.getType() === type) {
					widget.updateProperties({name, view_mode, fields, configuration});

					return Promise.resolve();
				}

				const widget_data = {
					type,
					name,
					view_mode,
					fields,
					configuration,
					widgetid: null,
					pos: widget === null ? this._new_widget_pos : widget.getPosition(),
					is_new: widget === null,
					rf_rate: 0,
					unique_id: this._createUniqueId()
				};

				return this
					._promiseScrollIntoView(widget_data.pos)
					.then(() => {
						if (widget === null) {
							this._new_widget_dashboard_page.addWidget(widget_data);
							this._new_widget_dashboard_page = null;
							this._new_widget_pos = null;
						}
						else {
							dashboard_page.replaceWidget(widget, widget_data);
						}
					});
			})
			.then(() => {
				this._resetWidgetPlaceholder();
			})
			.catch((error) => {
				for (const el of form.parentNode.children) {
					if (el.matches('.msg-good, .msg-bad, .msg-warning')) {
						el.parentNode.removeChild(el);
					}
				}

				const message_box = (typeof error === 'object' && 'html_string' in error)
					? new DOMParser().parseFromString(error.html_string, 'text/html').body.firstElementChild
					: makeMessageBox('bad', [], t('Cannot update widget properties.'), true, false)[0];

				form.parentNode.insertBefore(message_box, form);
			})
			.finally(() => overlay.unsetLoading());
	}

	_cancelEditingWidgetProperties() {
		this._resetWidgetPlaceholder();
	}

	_promiseDashboardWidgetCheck({templateid, type, name, view_mode, fields}) {
		const fields_str = Object.keys(fields).length > 0 ? JSON.stringify(fields) : undefined;

		const curl = new Curl('zabbix.php');

		curl.setArgument('action', 'dashboard.widget.check');

		return fetch(curl.getUrl(), {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
			},
			body: urlEncodeData({templateid, type, name, view_mode, fields: fields_str})
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
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
			},
			body: urlEncodeData({templateid, type, view_mode, fields: fields_str})
		})
			.then((response) => response.json())
			.then((response) => {
				return typeof response.configuration === 'object' ? response.configuration : {};
			});
	}

	/**
	 * Smoothly scroll object of given position and dimension into view and return a promise.
	 *
	 * @param {object} pos  Object with position and dimension.
	 *
	 * @returns {object}
	 */
	_promiseScrollIntoView(pos) {
		const $wrapper = $('.wrapper');
		const offset_top = $wrapper.scrollTop() + $(this._containers.grid).offset().top;
		const margin = 5;
		const widget_top = offset_top + pos.y * this._cell_height - margin;
		const widget_height = pos.height * this._cell_height + margin * 2;
		const wrapper_height = $wrapper.height();
		const wrapper_scroll_top = $wrapper.scrollTop();
		const wrapper_scroll_top_min = Math.max(0, widget_top + Math.min(0, widget_height - wrapper_height));
		const wrapper_scroll_top_max = widget_top;

		this._resizeGrid(pos.y + pos.height);

		return new Promise((resolve) => {
			if (wrapper_scroll_top < wrapper_scroll_top_min) {
				$wrapper
					.animate({scrollTop: wrapper_scroll_top_min})
					.promise()
					.then(() => resolve());
			}
			else if (wrapper_scroll_top > wrapper_scroll_top_max) {
				$wrapper
					.animate({scrollTop: wrapper_scroll_top_max})
					.promise()
					.then(() => resolve());
			}
			else {
				resolve();
			}
		});
	}

	/**
	 * @param {int|null}  min_rows  Min number of rows or null not to update the current one.
	 */
	_resizeGrid(min_rows = null) {
		if (min_rows !== null) {
			this._grid_min_rows = min_rows;
		}

		let num_rows = this._selected_dashboard_page.getNumRows();

		if (this._grid_min_rows !== null) {
			num_rows = Math.max(num_rows, this._grid_min_rows);
		}

		let height = this._cell_height * num_rows;

		if (this._is_edit_mode) {
			let min_height = window.innerHeight - document.querySelector('.wrapper > footer').clientHeight
				- this._containers.grid.offsetTop;

			let element = this._containers.grid;

			do {
				min_height -= parseInt(getComputedStyle(element).getPropertyValue('padding-bottom'));
				element = element.parentElement;
			}
			while (!element.classList.contains('wrapper'));

			height = Math.max(height, min_height);
		}

		this._containers.grid.style.height = `${height}px`;
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

		this._target.classList.toggle(ZBX_STYLE_DASHBRD_IS_MULTIPAGE, this._dashboard_pages.size > 1);
	}

	deleteDashboardPage(dashboard_page) {
		if (this._state === DASHBOARD_STATE_ACTIVE) {
			if (dashboard_page === this._selected_dashboard_page) {
				const dashboard_pages = this._dashboard_pages.keys();

				for (let i = 0; i < dashboard_pages.size; i++) {
					if (dashboard_pages[i] === dashboard_page) {
						this._selectDashboardPage(dashboard_pages[i > 0 ? i - 1 : i + 1]);

						break;
					}
				}

				if (dashboard_page === this._selected_dashboard_page) {
					throw new Error('Cannot delete the last dashboard page.');
				}
			}
		}

		if (dashboard_page.getState() !== DASHBOARD_PAGE_STATE_INITIAL) {
			dashboard_page.destroy();
		}

		this._dashboard_pages.delete(dashboard_page);

		if (this._state === DASHBOARD_STATE_ACTIVE) {
			this._announceWidgets();
		}

		this._target.classList.toggle(ZBX_STYLE_DASHBRD_IS_MULTIPAGE, this._dashboard_pages.size > 1);
	}

	_activatePage(dashboard_page) {
		dashboard_page.activate();
		dashboard_page
			.on(DASHBOARD_PAGE_EVENT_EDIT, this._events.dashboardPageEdit)
			.on(DASHBOARD_PAGE_EVENT_WIDGET_EDIT, this._events.dashboardPageWidgetEdit)
			.on(DASHBOARD_PAGE_EVENT_WIDGET_COPY, this._events.dashboardPageWidgetCopy)
			.on(DASHBOARD_PAGE_EVENT_WIDGET_DELETE, this._events.dashboardPageWidgetDelete)
			.on(DASHBOARD_PAGE_EVENT_WIDGET_DRAG_START, this._events.dashboardPageWidgetDragStart)
			.on(DASHBOARD_PAGE_EVENT_WIDGET_DRAG, this._events.dashboardPageWidgetDrag)
			.on(DASHBOARD_PAGE_EVENT_WIDGET_DRAG_END, this._events.dashboardPageWidgetDragEnd)
			.on(DASHBOARD_PAGE_EVENT_ANNOUNCE_WIDGETS, this._events.dashboardPageAnnounceWidgets)
			.on(DASHBOARD_PAGE_EVENT_RESERVE_HEADER_LINES, this._events.dashboardPageReserveHeaderLines);
	}

	_deactivatePage(dashboard_page) {
		dashboard_page.deactivate();
		dashboard_page
			.off(DASHBOARD_PAGE_EVENT_EDIT, this._events.dashboardPageEdit)
			.off(DASHBOARD_PAGE_EVENT_WIDGET_EDIT, this._events.dashboardPageWidgetEdit)
			.off(DASHBOARD_PAGE_EVENT_WIDGET_COPY, this._events.dashboardPageWidgetCopy)
			.off(DASHBOARD_PAGE_EVENT_WIDGET_DELETE, this._events.dashboardPageWidgetDelete)
			.off(DASHBOARD_PAGE_EVENT_WIDGET_DRAG_START, this._events.dashboardPageWidgetDragStart)
			.off(DASHBOARD_PAGE_EVENT_WIDGET_DRAG, this._events.dashboardPageWidgetDrag)
			.off(DASHBOARD_PAGE_EVENT_WIDGET_DRAG_END, this._events.dashboardPageWidgetDragEnd)
			.off(DASHBOARD_PAGE_EVENT_ANNOUNCE_WIDGETS, this._events.dashboardPageAnnounceWidgets)
			.off(DASHBOARD_PAGE_EVENT_RESERVE_HEADER_LINES, this._events.dashboardPageReserveHeaderLines);
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

		if (!this._is_kiosk_mode) {
			this._selectTab(this._selected_dashboard_page);
		}

		this._resizeGrid();
	}

	_announceWidgets() {
		const dashboard_pages = Array.from(this._dashboard_pages.keys());

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

	_deleteTab(dashboard_page) {
		const data = this._dashboard_pages.get(dashboard_page);

		this._tabs.removeItem(data.tab);
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
			type: DASHBOARD_CLIPBOARD_TYPE_WIDGET,
			data: data
		}));
	}

	getStoredWidgetCopy() {
		let clipboard = localStorage.getItem('dashboard.clipboard');

		if (clipboard === null) {
			return null;
		}

		clipboard = JSON.parse(clipboard);

		if (clipboard.type !== DASHBOARD_CLIPBOARD_TYPE_WIDGET) {
			return null;
		}

		return (clipboard.data.dashboard.templateid === this._data.templateid) ? clipboard.data : null;
	}

	_resize() {
		this._resizeGrid();

		if (this._is_edit_mode) {
			this._widget_placeholder.resize();
		}

		for (const dashboard_page of this._dashboard_pages.keys()) {
			dashboard_page.resize();
		}
	}

	_reserveHeaderLines(num_lines) {
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
			}, 2000);
		}
	}

	_resetHeaderLines() {
		if (this._reserve_header_lines_timeout_id !== null) {
			clearTimeout(this._reserve_header_lines_timeout_id);
			this._reserve_header_lines_timeout_id = null;
		}

		this._containers.grid.classList.remove('reserve-header-lines-1', 'reserve-header-lines-2');
	}

	_registerEvents() {
		let window_resize_timeout_id = null;

		this._events = {
			editWidgetPropertiesCancel: () => {
				this._cancelEditingWidgetProperties();
				this._is_edit_widget_properties_cancel_subscribed = false;
				$.unsubscribe('overlay.close', this._events.editWidgetPropertiesCancel);
			},

			dashboardPageEdit: (e) => {
				this.setEditMode();
				this.fire(DASHBOARD_EVENT_EDIT);
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
				this.storeWidgetCopy(e.detail.data);
			},

			dashboardPageWidgetDelete: () => {
				this._resizeGrid();
			},

			dashboardPageWidgetDragStart: () => {
				this._deactivateWidgetPlaceholder();
			},

			dashboardPageWidgetDrag: (e) => {
				const grid_min_rows = Math.min(this._max_rows, e.detail.pos.y + e.detail.pos.height + 2);

				this._resizeGrid(grid_min_rows);
			},

			dashboardPageWidgetDragEnd: () => {
				this._activateWidgetPlaceholder();
			},

			dashboardPageAnnounceWidgets: () => {
				this._announceWidgets();
			},

			dashboardPageReserveHeaderLines: (e) => {
				this._reserveHeaderLines(e.detail.num_header_lines);
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
				if (window_resize_timeout_id != null) {
					clearTimeout(window_resize_timeout_id);
				}

				window_resize_timeout_id = setTimeout(() => {
					window_resize_timeout_id = null;
					this._resize();
				}, 200);
			}
		};

		if (this._time_period !== null) {
			jQuery.subscribe('timeselector.rangeupdate', this._events.timeSelectorRangeUpdate);
		}

		window.addEventListener('resize', this._events.windowResize);
	}

	_initTabs() {
		const sortable = document.createElement('div');

		this._containers.navigation_tabs.appendChild(sortable);

		this._tabs = new CSortable(sortable, {is_vertical: false});
		this._tabs_dashboard_pages = new Map();

		this._registerTabsEvents();
	}

	_registerTabsEvents() {
		const events = {
			resize: () => {
				this._updateNavigationButtons();
			},

			dragEnd: () => {
				this._updateNavigationButtons();
			},

			click: (e) => {
				const tab = e.target.closest(`.${ZBX_STYLE_SORTABLE_ITEM}`);

				if (tab !== null) {
					const dashboard_page = this._tabs_dashboard_pages.get(tab);

					if (dashboard_page !== this._selected_dashboard_page) {
						this._selectDashboardPage(dashboard_page);
					}
				}
			},

			keyDown: (e) => {
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
		}

		new ResizeObserver(events.resize).observe(this._containers.navigation_tabs);

		this._tabs.on(SORTABLE_EVENT_DRAG_END, events.dragEnd);

		this._containers.navigation_tabs.addEventListener('click', events.click);
		this._containers.navigation_tabs.addEventListener('keydown', events.keyDown);

		this._buttons.previous_page.addEventListener('click', events.previousPageClick);
		this._buttons.next_page.addEventListener('click', events.nextPageClick);
	}

	_initWidgetPlaceholder() {
		this._widget_placeholder_events = {
			mouseDown: (e) => {
				if (this._widget_placeholder_pos === null) {
					return;
				}

				this._widget_placeholder_clicked_pos = this._widget_placeholder_pos;

				this._widget_placeholder
					.setState(WIDGET_PLACEHOLDER_STATE_RESIZING)
					.showAtPosition(this._widget_placeholder_clicked_pos);

				e.preventDefault();

				document.addEventListener('mouseup', this._widget_placeholder_events.mouseUp);
				document.addEventListener('mousemove', this._widget_placeholder_events.mouseMove);
				this._containers.grid.removeEventListener('mousemove', this._widget_placeholder_events.mouseMove);
			},

			mouseUp: (e) => {
				const new_widget_pos = {...this._widget_placeholder_pos};

				if (new_widget_pos.width == 2 && new_widget_pos.height == this._widget_min_rows) {
					delete new_widget_pos.width;
					delete new_widget_pos.height;
				}

				this.editWidgetProperties({}, {new_widget_pos});

				document.removeEventListener('mouseup', this._widget_placeholder_events.mouseUp);
				document.removeEventListener('mousemove', this._widget_placeholder_events.mouseMove);
				this._containers.grid.addEventListener('mousemove', this._widget_placeholder_events.mouseMove);
			},

			mouseMove: (e) => {
				if (this._widget_placeholder_clicked_pos !== null) {
					let event_pos = this._getGridEventPos(e, {width: 1, height: 1});
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

					this._widget_placeholder_pos = this._selected_dashboard_page.accommodatePos(
						this._widget_placeholder_pos, {reverse_x, reverse_y}
					);

					const grid_min_rows = Math.min(this._max_rows,
						this._widget_placeholder_pos.y + this._widget_placeholder_pos.height + 2
					);

					if (grid_min_rows > this._grid_min_rows) {
						this._resizeGrid(grid_min_rows);
					}

					this._widget_placeholder
						.setState(WIDGET_PLACEHOLDER_STATE_RESIZING)
						.showAtPosition(this._widget_placeholder_pos);
				}
				else {
					if (this._widget_placeholder_pos !== null
							&& this._widget_placeholder.getNode().contains(e.target)) {
						return;
					}

					this._widget_placeholder_pos = null;

					const event_pos_1x1 = this._getGridEventPos(e, {width: 1, height: 1});

					if (this._selected_dashboard_page.isPosFree(event_pos_1x1)) {
						let event_pos = this._getGridEventPos(e, {width: 2, height: this._widget_min_rows});

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

									if (this._selected_dashboard_page._isOverlappingPos(pos, event_pos_1x1)) {
										if (this._selected_dashboard_page.isPosFree(pos)) {
											this._widget_placeholder_pos = pos;
											break;
										}
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
						const grid_min_rows = Math.min(this._max_rows,
							this._widget_placeholder_pos.y + this._widget_placeholder_pos.height + 2
						);

						if (grid_min_rows > this._grid_min_rows) {
							this._resizeGrid(grid_min_rows);
						}

						this._widget_placeholder
							.setState(WIDGET_PLACEHOLDER_STATE_POSITIONING)
							.showAtPosition(this._widget_placeholder_pos);
					}
					else {
						this._widget_placeholder.hide();
					}
				}
			},

			mouseLeave: (e) => {
				if (this._widget_placeholder_clicked_pos === null) {
					this._resetWidgetPlaceholder();
				}
			},

			scroll: (e) => {
				if (e.target.scrollTop == 0) {
					this._resizeGrid(0);
				}
			}
		}

		this._widget_placeholder = new CDashboardWidgetPlaceholder(this._cell_width, this._cell_height);
		this._widget_placeholder_pos = null;
		this._widget_placeholder_clicked_pos = null;

		this._containers.grid.appendChild(this._widget_placeholder.getNode());
	}

	_activateWidgetPlaceholder() {
		this._widget_placeholder.on('mousedown', this._widget_placeholder_events.mouseDown);

		this._containers.grid.addEventListener('mousemove', this._widget_placeholder_events.mouseMove);
		this._containers.grid.addEventListener('mouseleave', this._widget_placeholder_events.mouseLeave);

		document.querySelector('.wrapper').addEventListener('scroll', this._widget_placeholder_events.scroll);

		this._resetWidgetPlaceholder();
	}

	_deactivateWidgetPlaceholder() {
		this._widget_placeholder.off('mousedown', this._widget_placeholder_events.mouseDown);

		this._containers.grid.removeEventListener('mousemove', this._widget_placeholder_events.mouseMove);
		this._containers.grid.removeEventListener('mouseleave', this._widget_placeholder_events.mouseLeave);

		document.querySelector('.wrapper').removeEventListener('scroll', this._widget_placeholder_events.scroll);

		document.removeEventListener('mousemove', this._widget_placeholder_events.mouseMove);
		document.removeEventListener('mouseup', this._widget_placeholder_events.mouseUp);

		this._widget_placeholder.hide();
	}

	_resetWidgetPlaceholder() {
		this._widget_placeholder_pos = null;
		this._widget_placeholder_clicked_pos = null;

		if (this._is_editable) {
			if (this._is_kiosk_mode) {
				this._widget_placeholder.setState(WIDGET_PLACEHOLDER_STATE_KIOSK_MODE);
			}
			else {
				this._widget_placeholder.setState(WIDGET_PLACEHOLDER_STATE_ADD_NEW);
			}
		}
		else {
			this._widget_placeholder.setState(WIDGET_PLACEHOLDER_STATE_READONLY);
		}

		if (this._selected_dashboard_page.getWidgets().length == 0) {
			this._widget_placeholder.showAtDefaultPosition();
		}
	}

	_getGridEventPos(e, {width, height}) {
		const rect = this._containers.grid.getBoundingClientRect();
		const x = Math.round((e.pageX - rect.x) / rect.width * this._max_columns - width / 2);
		const y = Math.round((e.pageY - rect.y) / this._cell_height - height / 2);

		return {
			x: Math.max(0, Math.min(this._max_columns - width, x)),
			y: Math.max(0, Math.min(this._max_rows - height, y)),
			width: width,
			height: height
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
