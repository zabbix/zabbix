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


const ZBX_STYLE_BTN_DASHBOARD_PAGE_PROPERTIES = 'btn-dashboard-page-properties';
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
const DASHBOARD_EVENT_CONFIGURATION_OUTDATED = 'dashboard-configuration-outdated';

class CDashboard {

	static ZBX_STYLE_IS_READY = 'is-ready';

	static REFERENCE_DASHBOARD = 'DASHBOARD';

	static EVENT_FEEDBACK = 'dashboard-feedback';

	#broadcast_options;

	#broadcast_cache = new Map();

	#edit_widget_cache = null;

	constructor(target, {
		containers,
		buttons,
		data,
		max_dashboard_pages,
		cell_width,
		cell_height,
		max_columns,
		max_rows,
		widget_defaults,
		widget_last_type = null,
		configuration_hash = null,
		is_editable,
		is_edit_mode,
		can_edit_dashboards,
		is_kiosk_mode,
		broadcast_options = {},
		csrf_token = null
	}) {
		this._target = target;

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
		this._widget_defaults = {...widget_defaults};
		this._widget_last_type = widget_last_type;
		this._configuration_hash = configuration_hash;
		this._is_editable = is_editable;
		this._is_edit_mode = is_edit_mode;
		this._can_edit_dashboards = can_edit_dashboards;
		this._is_kiosk_mode = is_kiosk_mode;
		this.#broadcast_options = broadcast_options;
		this._csrf_token = csrf_token;

		this.#initialize();
		this.#registerEvents();
	}

	#initialize() {
		this._state = DASHBOARD_STATE_INITIAL;

		this._dashboard_pages = new Map();
		this._selected_dashboard_page = null;

		this._busy_conditions = new Set();

		this._async_timeout_ms = 50;

		this._unique_id_index = 0;

		this._warning_message_box = null;

		this._reserve_header_lines = 0;
		this._reserve_header_lines_timeout_id = null;

		this._header_lines_steady_period = 2000;

		this._slideshow_steady_period = 5000;
		this._slideshow_switch_time = null;
		this._slideshow_timeout_id = null;

		this._configuration_check_period = 60000;
		this._configuration_check_steady_period = 2000;
		this._configuration_check_time = null;
		this._configuration_check_timeout_id = null;

		this._is_unsaved = false;

		if (!this._is_kiosk_mode) {
			const sortable = document.createElement('ul');

			this._containers.navigation_tabs.appendChild(sortable);

			this._tabs = new CSortable(sortable, {
				is_horizontal: true,
				enable_sorting: this._is_edit_mode
			});

			this._tabs_dashboard_pages = new Map();
		}
	}

	// Logical state control methods.

	activate() {
		if (this._dashboard_pages.size === 0) {
			throw new Error('Cannot activate dashboard without dashboard pages.');
		}

		this._state = DASHBOARD_STATE_ACTIVE;

		this.#activateEvents();

		const dashboard_page = this._getInitialDashboardPage();

		this._selectDashboardPage(dashboard_page);

		if (this._is_edit_mode) {
			this._target.classList.add(ZBX_STYLE_DASHBOARD_IS_EDIT_MODE);
		}

		if (!this._is_edit_mode) {
			this._startConfigurationChecker();

			if (this._data.auto_start === '1' && this._dashboard_pages.size > 1) {
				this._startSlideshow();
			}
		}
	}

	// External events management methods.

	broadcast(data) {
		for (const [type, value] of Object.entries(data)) {
			ZABBIX.EventHub.publish({
				data: value,
				descriptor: {
					context: 'dashboard',
					sender_unique_id: 'dashboard',
					sender_type: 'dashboard',
					event_type: 'broadcast',
					event_origin: 'dashboard',
					reference: CDashboard.REFERENCE_DASHBOARD,
					type
				}
			});

			this.#broadcast_cache.set(type, value);
		}
	}

	isEditMode() {
		return this._is_edit_mode;
	}

	setEditMode({is_internal_call = false} = {}) {
		this._is_edit_mode = true;

		this._target.classList.add(ZBX_STYLE_DASHBOARD_IS_EDIT_MODE);

		for (const dashboard_page of this._dashboard_pages.keys()) {
			if (!dashboard_page.isEditMode()) {
				dashboard_page.setEditMode();
			}
		}

		if (!this._is_kiosk_mode) {
			this._tabs.enableSorting();
		}

		this._stopConfigurationChecker();
		this._stopSlideshow();

		if (is_internal_call) {
			this.fire(DASHBOARD_EVENT_EDIT);
		}
	}

	_startSlideshow() {
		if (this._slideshow_timeout_id !== null) {
			clearTimeout(this._slideshow_timeout_id);
		}

		if (this._buttons.slideshow !== null) {
			if (this._is_kiosk_mode) {
				this._buttons.slideshow.classList.remove(ZBX_ICON_PLAY);
				this._buttons.slideshow.classList.add(ZBX_ICON_PAUSE);
			}

			this._buttons.slideshow.classList.remove('slideshow-state-stopped');
			this._buttons.slideshow.classList.add('slideshow-state-started');

			if (this._buttons.slideshow.title !== '') {
				this._buttons.slideshow.title = t('Stop slideshow');
			}
		}

		let timeout_ms = this._selected_dashboard_page.getDisplayPeriod() * 1000;

		if (timeout_ms === 0) {
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
			if (this._is_kiosk_mode) {
				this._buttons.slideshow.classList.remove(ZBX_ICON_PAUSE);
				this._buttons.slideshow.classList.add(ZBX_ICON_PLAY);
			}

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

		if (timeout_ms === 0) {
			timeout_ms = this._data.display_period * 1000;
		}

		this._slideshow_switch_time = Math.max(Date.now() + this._slideshow_steady_period,
			this._slideshow_switch_time + timeout_ms
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

	_startConfigurationChecker() {
		if (this._configuration_check_timeout_id !== null) {
			clearTimeout(this._configuration_check_timeout_id);
		}

		this._configuration_check_time = Date.now() + this._configuration_check_period;
		this._configuration_check_timeout_id = setTimeout(() => this._checkConfiguration(),
			this._configuration_check_period
		);
	}

	_stopConfigurationChecker() {
		if (this._configuration_check_timeout_id === null) {
			return;
		}

		clearTimeout(this._configuration_check_timeout_id);

		this._configuration_check_time = null;
		this._configuration_check_timeout_id = null;
	}

	_checkConfiguration() {
		this._configuration_check_timeout_id = null;

		if (this._isUserInteracting()) {
			this._configuration_check_time = Date.now() + this._configuration_check_steady_period;
			this._configuration_check_timeout_id = setTimeout(() => this._checkConfiguration(),
				this._configuration_check_steady_period
			);

			return;
		}

		const busy_condition = this._createBusyCondition();

		Promise.resolve()
			.then(() => this._promiseCheckConfiguration())
			.catch(exception => {
				console.log('Could not check the dashboard configuration', exception);
			})
			.finally(() => {
				this._configuration_check_time = Math.max(Date.now() + this._configuration_check_steady_period,
					this._configuration_check_time + this._configuration_check_period
				);

				this._configuration_check_timeout_id = setTimeout(() => this._checkConfiguration(),
					this._configuration_check_time - Date.now()
				);

				this._deleteBusyCondition(busy_condition);
			});
	}

	_promiseCheckConfiguration() {
		const curl = new Curl('zabbix.php');

		curl.setArgument('action', 'dashboard.config.hash');

		return fetch(curl.getUrl(), {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify({
				templateid: this._data.templateid ?? undefined,
				dashboardid: this._data.dashboardid
			})
		})
			.then(response => response.json())
			.then(response => {
				if ('error' in response) {
					throw {error: response.error};
				}

				if (response.configuration_hash !== null && this._configuration_hash !== response.configuration_hash) {
					this.fire(DASHBOARD_EVENT_CONFIGURATION_OUTDATED);
				}
			});
	}

	_keepSteadyConfigurationChecker() {
		if (this._configuration_check_timeout_id === null) {
			return;
		}

		if (this._configuration_check_time - Date.now() < this._configuration_check_steady_period) {
			clearTimeout(this._configuration_check_timeout_id);

			this._configuration_check_time = Date.now() + this._configuration_check_steady_period;

			this._configuration_check_timeout_id = setTimeout(() => this._checkConfiguration(),
				this._configuration_check_time - Date.now()
			);
		}
	}

	_createBusyCondition() {
		if (this._busy_conditions.size === 0) {
			this.fire(DASHBOARD_EVENT_BUSY);
		}

		const busy_condition = {};

		this._busy_conditions.add(busy_condition);

		return busy_condition;
	}

	_deleteBusyCondition(busy_condition) {
		this._busy_conditions.delete(busy_condition);

		if (this._busy_conditions.size === 0) {
			this.fire(DASHBOARD_EVENT_IDLE);
		}
	}

	isUnsaved() {
		if (this._is_unsaved) {
			return true;
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

	addPastePlaceholderWidget(dashboard_page, {type, name, view_mode, pos}) {
		const widget = new CWidgetPastePlaceholder({
			type: 'paste-placeholder',
			name,
			view_mode,
			fields: {},
			defaults: this._widget_defaults[type],
			widgetid: null,
			pos,
			is_new: false,
			rf_rate: 0,
			dashboard: {
				templateid: this._data.templateid,
				dashboardid: this._data.dashboardid
			},
			dashboard_page: {
				unique_id: dashboard_page.getUniqueId()
			},
			cell_width: this._cell_width,
			cell_height: this._cell_height,
			is_editable: this._is_editable,
			is_edit_mode: this._is_edit_mode,
			csrf_token: this._csrf_token,
			unique_id: this._createUniqueId()
		});

		dashboard_page.addWidget(widget, {is_helper: true});

		return widget;
	}

	addWidgetFromData(dashboard_page, {type, name, view_mode, fields, widgetid, pos, is_new, rf_rate, messages}) {
		const widget_data = {
			type,
			name,
			view_mode,
			fields,
			defaults: this._widget_defaults[type],
			widgetid,
			pos,
			is_new,
			rf_rate,
			dashboard: {
				templateid: this._data.templateid,
				dashboardid: this._data.dashboardid
			},
			dashboard_page: {
				unique_id: dashboard_page.getUniqueId()
			},
			cell_width: this._cell_width,
			cell_height: this._cell_height,
			is_editable: this._is_editable,
			is_edit_mode: this._is_edit_mode,
			csrf_token: this._csrf_token,
			unique_id: this._createUniqueId()
		};

		let widget;

		if (type in this._widget_defaults) {
			if (messages.length > 0) {
				widget = new CWidgetMisconfigured(widget_data);
				widget.setMessages(messages);
			}
			else {
				let has_empty_references = false;

				for (const accessor of CWidgetBase.getFieldsReferencesAccessors(widget_data.fields).values()) {
					if (accessor.getTypedReference() === '') {
						has_empty_references = true;

						break;
					}
				}

				if (has_empty_references) {
					widget = new CWidgetMisconfigured(widget_data);
					widget.setMessageType(CWidgetMisconfigured.MESSAGE_TYPE_EMPTY_REFERENCES);
				}
				else {
					widget = new (eval(this._widget_defaults[type].js_class))(widget_data);
				}
			}
		}
		else {
			widget = new CWidgetInaccessible({
				...widget_data,
				type: 'inaccessible',
				name: '',
				view_mode: ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER,
				fields: {},
				defaults: {
					name: t('Inaccessible widget')
				},
				is_new: false,
				rf_rate: 0
			});
		}

		dashboard_page.addWidget(widget);

		return widget;
	}

	replaceWidgetFromData(dashboard_page, old_widget, new_widget_data) {
		dashboard_page.deleteWidget(old_widget, {is_batch_mode: true});

		return this.addWidgetFromData(dashboard_page, new_widget_data);
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
		const references = new Set();

		for (const widget of widgets) {
			if ('reference' in widget.fields) {
				references.add(widget.fields.reference);
			}
		}

		for (const widget of widgets) {
			for (const accessor of CWidgetBase.getFieldsReferencesAccessors(widget.fields).values()) {
				const {reference} = CWidgetBase.parseTypedReference(accessor.getTypedReference());

				if (reference === CDashboard.REFERENCE_DASHBOARD) {
					continue;
				}

				if (!references.has(reference)) {
					accessor.setTypedReference(CWidgetBase.createTypedReference({reference: ''}));
				}
			}
		}

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
			widget_defaults: this._widget_defaults,
			is_editable: this._is_editable,
			is_edit_mode: this._is_edit_mode,
			csrf_token: this._csrf_token,
			unique_id: this._createUniqueId()
		});

		this._dashboard_pages.set(dashboard_page, {is_ready: false});

		for (const widget_data of widgets) {
			this.addWidgetFromData(dashboard_page, {
				...widget_data,
				is_new: false
			});
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
		if (this._dashboard_pages.size === 1) {
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
				const tabs = [...this._tabs.getTarget().children];
				const tab_index = tabs.indexOf(this._dashboard_pages.get(dashboard_page).tab);

				this._selectDashboardPage(
					this._tabs_dashboard_pages.get(tabs[tab_index > 0 ? tab_index - 1 : tab_index + 1])
				);
			}
		}

		if (dashboard_page.getState() !== DASHBOARD_PAGE_STATE_INITIAL) {
			this.#destroyDashboardPage(dashboard_page);
		}

		if (!this._is_kiosk_mode) {
			this._deleteTab(dashboard_page);
		}

		this._dashboard_pages.delete(dashboard_page);

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
		this._clearWarnings();

		if (this._dashboard_pages.size >= this._max_dashboard_pages) {
			this._warnDashboardExhausted();

			return;
		}

		const widgets = [];

		for (const widget of new_dashboard_page_data.widgets) {
			if (widget.type in this._widget_defaults) {
				widgets.push(widget);
			}
		}

		const references = this._getReferences();
		const references_substitution = new Map();

		for (const widget of widgets) {
			if ('reference' in widget.fields) {
				const old_reference = widget.fields.reference;
				const new_reference = this._createReference({references});

				widget.fields.reference = new_reference;

				references.add(new_reference);
				references_substitution.set(old_reference, new_reference);
			}
		}

		for (const widget of widgets) {
			for (const accessor of CWidgetBase.getFieldsReferencesAccessors(widget.fields).values()) {
				const {reference, type} = CWidgetBase.parseTypedReference(accessor.getTypedReference());

				if (reference === CDashboard.REFERENCE_DASHBOARD) {
					continue;
				}

				accessor.setTypedReference(
					CWidgetBase.createTypedReference(references_substitution.has(reference)
						? {
							reference: references_substitution.get(reference),
							type
						}
						: {
							reference: ''
						}
					)
				);
			}
		}

		const busy_condition = this._createBusyCondition();

		Promise.resolve()
			.then(() => this._promiseDashboardWidgetsValidate(widgets))
			.then(response => {
				if (this._dashboard_pages.size >= this._max_dashboard_pages) {
					this._warnDashboardExhausted();

					return;
				}

				if (response.widgets.length < new_dashboard_page_data.widgets.length) {
					this._warn(t('Inaccessible widgets were not pasted.'));
				}

				const sane_widgets = [];

				for (let i = 0; i < response.widgets.length; i++) {
					if (response.widgets[i] !== null) {
						sane_widgets.push({
							...widgets[i],
							fields: response.widgets[i].fields,
							messages: response.widgets[i].messages
						});
					}
				}

				const dashboard_page = this.addDashboardPage({
					dashboard_pageid: null,
					name: new_dashboard_page_data.name,
					display_period: new_dashboard_page_data.display_period,
					widgets: sane_widgets
				});

				this._selectDashboardPage(dashboard_page, {is_async: true});
			})
			.catch(exception => {
				clearMessages();

				let title;
				let messages = [];

				if (typeof exception === 'object' && 'error' in exception) {
					title = exception.error.title;
					messages = exception.error.messages;
				}
				else {
					title = t('Failed to paste dashboard page.');
				}

				const message_box = makeMessageBox('bad', messages, title);

				addMessage(message_box);
			})
			.finally(() => this._deleteBusyCondition(busy_condition))
	}

	pasteWidget(new_widget_data, {widget = null, new_widget_pos = null} = {}) {
		this._clearWarnings();

		if (!(new_widget_data.type in this._widget_defaults)) {
			this._warn(t('Cannot paste inaccessible widget.'));

			return;
		}

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
			dashboard_page.deleteWidget(widget, {do_destroy: false, is_batch_mode: true});
		}

		if ('reference' in new_widget_data.fields) {
			new_widget_data.fields.reference = this._createReference();
		}

		const references = this._getReferences({dashboard_page});

		for (const accessor of CWidgetBase.getFieldsReferencesAccessors(new_widget_data.fields).values()) {
			if (accessor.getTypedReference() === '') {
				continue;
			}

			const {reference} = CWidgetBase.parseTypedReference(accessor.getTypedReference());

			if (reference === CDashboard.REFERENCE_DASHBOARD) {
				continue;
			}

			if (!references.has(reference)) {
				accessor.setTypedReference(CWidgetBase.createTypedReference({reference: ''}));
			}
		}

		const paste_placeholder_widget = this.addPastePlaceholderWidget(dashboard_page, {
			type: new_widget_data.type,
			name: new_widget_data.name,
			view_mode: new_widget_data.view_mode,
			pos: new_widget_pos
		});

		dashboard_page.resetWidgetPlaceholder();

		const busy_condition = this._createBusyCondition();

		dashboard_page.promiseScrollIntoView(new_widget_pos)
			.then(() => this._promiseDashboardWidgetsValidate([new_widget_data]))
			.then(response => {
				if (dashboard_page.getState() === DASHBOARD_PAGE_STATE_DESTROYED) {
					return;
				}

				if (response.widgets[0] === null) {
					if (widget !== null) {
						dashboard_page.replaceWidget(paste_placeholder_widget, widget);
					}
					else {
						dashboard_page.deleteWidget(paste_placeholder_widget);
					}

					this._warn(t('Cannot paste inaccessible widget.'));

					return;
				}

				const widget_replace = this.replaceWidgetFromData(dashboard_page, paste_placeholder_widget, {
					...new_widget_data,
					fields: response.widgets[0].fields,
					widgetid: null,
					pos: new_widget_pos,
					is_new: true,
					messages: response.widgets[0].messages
				});

				if (widget !== null) {
					this.#validateFieldsReferences({dashboard_page, widget_delete: widget, widget_replace});
				}
			})
			.catch(exception => {
				dashboard_page.deleteWidget(paste_placeholder_widget);

				clearMessages();

				let title;
				let messages = [];

				if (typeof exception === 'object' && 'error' in exception) {
					title = exception.error.title;
					messages = exception.error.messages;
				}
				else {
					title = t('Failed to paste widget.');
				}

				const message_box = makeMessageBox('bad', messages, title);

				addMessage(message_box);
			})
			.finally(() => this._deleteBusyCondition(busy_condition));
	}

	_promiseDashboardWidgetsValidate(widgets_data) {
		let request_widgets_data = [];

		for (const widget_data of widgets_data) {
			request_widgets_data.push({
				type: widget_data.type,
				fields: widget_data.fields
			});
		}

		const curl = new Curl('zabbix.php');

		curl.setArgument('action', 'dashboard.widgets.validate');

		return fetch(curl.getUrl(), {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify({
				templateid: this._data.templateid ?? undefined,
				widgets: request_widgets_data
			})
		})
			.then(response => response.json())
			.then(response => {
				if ('error' in response) {
					throw {error: response.error};
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
		if (this._data.templateid === null || !this._is_edit_mode) {
			this._setInitialDashboardPage(dashboard_page);
		}

		if (!this._is_edit_mode) {
			this._keepSteadyConfigurationChecker();

			if (this._isSlideshowRunning()) {
				this._keepSteadySlideshow();
			}
		}

		this._promiseSelectDashboardPage(dashboard_page, {is_async})
			.then(() => {
				this._updateReadyState();

				if (!this._is_edit_mode) {
					this._keepSteadyConfigurationChecker();

					if (this._isSlideshowRunning()) {
						this._startSlideshow();
					}
				}
			});
	}

	_promiseSelectDashboardPage(dashboard_page, {is_async = false} = {}) {
		return new Promise(resolve => {
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
			this._deactivateDashboardPage(this._selected_dashboard_page);
		}

		this._selected_dashboard_page = dashboard_page;

		if (this._selected_dashboard_page.getState() === DASHBOARD_PAGE_STATE_INITIAL) {
			this._startDashboardPage(this._selected_dashboard_page);
		}

		this._activateDashboardPage(this._selected_dashboard_page);

		if (this._is_kiosk_mode) {
			this._resetHeaderLines();
		}
	}

	_startDashboardPage(dashboard_page) {
		dashboard_page.on(CDashboardPage.EVENT_READY, this._events.dashboardPageReady);
		dashboard_page.on(CDashboardPage.EVENT_REQUIRE_DATA_SOURCE, this._events.dashboardPageRequireDataSource);

		dashboard_page.start();
	}

	_activateDashboardPage(dashboard_page) {
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
			.on(DASHBOARD_PAGE_EVENT_WIDGET_PASTE, this._events.dashboardPageWidgetPaste);

		if (this._is_kiosk_mode) {
			dashboard_page.on(DASHBOARD_PAGE_EVENT_RESERVE_HEADER_LINES, this._events.dashboardPageReserveHeaderLines);
		}
	}

	_deactivateDashboardPage(dashboard_page) {
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
			.off(DASHBOARD_PAGE_EVENT_WIDGET_PASTE, this._events.dashboardPageWidgetPaste);

		if (this._is_kiosk_mode) {
			dashboard_page.off(DASHBOARD_PAGE_EVENT_RESERVE_HEADER_LINES, this._events.dashboardPageReserveHeaderLines);
		}
	}

	#destroyDashboardPage(dashboard_page) {
		dashboard_page.off(CDashboardPage.EVENT_READY, this._events.dashboardPageReady);
		dashboard_page.off(CDashboardPage.EVENT_REQUIRE_DATA_SOURCE, this._events.dashboardPageRequireDataSource);

		dashboard_page.destroy();
	}

	_setInitialDashboardPage(dashboard_page) {
		const dashboard_page_index = this.getDashboardPageIndex(dashboard_page);

		const url = new URL(location.href);

		if (dashboard_page_index > 0) {
			url.searchParams.set('page', `${dashboard_page_index + 1}`);
		}
		else {
			url.searchParams.delete('page');
		}

		history.replaceState(null, null, url);
	}

	_getInitialDashboardPage() {
		const url = new URL(location.href);

		if (url.searchParams.has('page')) {
			const dashboard_pages = [...this._dashboard_pages.keys()];
			const dashboard_page_index = parseInt(url.searchParams.get('page')) - 1;

			if (dashboard_page_index in dashboard_pages) {
				return dashboard_pages[dashboard_page_index];
			}
		}

		return this._dashboard_pages.keys().next().value;
	}

	getSelectedDashboardPage() {
		return this._selected_dashboard_page;
	}

	getDashboardPageIndex(dashboard_page) {
		if (this._is_kiosk_mode) {
			const dashboard_pages = [...this._dashboard_pages.keys()];

			return dashboard_pages.indexOf(dashboard_page);
		}

		const tabs = [...this._tabs.getTarget().children];
		const data = this._dashboard_pages.get(dashboard_page);

		return tabs.indexOf(data.tab);
	}

	/**
	 * Update readiness state of the dashboard.
	 *
	 * Readiness state is updated on switching dashboard pages and as soon as the selected page gets fully loaded.
	 */
	_updateReadyState() {
		const data = this._dashboard_pages.get(this._selected_dashboard_page);

		this._target.classList.toggle(CDashboard.ZBX_STYLE_IS_READY, data.is_ready);
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
			for (const tab of this._tabs.getTarget().children) {
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

		Promise.resolve()
			.then(() => this._promiseApplyProperties(properties))
			.then(() => {
				this._is_unsaved = true;

				overlayDialogueDestroy(overlay.dialogueid);

				this.fire(DASHBOARD_EVENT_APPLY_PROPERTIES);
			})
			.catch(exception => {
				for (const element of form.parentNode.children) {
					if (element.matches('.msg-good, .msg-bad, .msg-warning')) {
						element.parentNode.removeChild(element);
					}
				}

				let title;
				let messages = [];

				if (typeof exception === 'object' && 'error' in exception) {
					title = exception.error.title;
					messages = exception.error.messages;
				}
				else {
					title = t('Failed to update dashboard properties.');
				}

				const message_box = makeMessageBox('bad', messages, title)[0];

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
			.then(response => response.json())
			.then(response => {
				if ('error' in response) {
					throw {error: response.error};
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

		Promise.resolve()
			.then(() => this._promiseApplyDashboardPageProperties(properties, overlay.data))
			.then(() => {
				this._is_unsaved = true;

				overlayDialogueDestroy(overlay.dialogueid);
			})
			.catch(exception => {
				for (const element of form.parentNode.children) {
					if (element.matches('.msg-good, .msg-bad, .msg-warning')) {
						element.parentNode.removeChild(element);
					}
				}

				let title;
				let messages = [];

				if (typeof exception === 'object' && 'error' in exception) {
					title = exception.error.title;
					messages = exception.error.messages;
				}
				else {
					title = t('Failed to update dashboard page properties.');
				}

				const message_box = makeMessageBox('bad', messages, title)[0];

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
			.then(response => response.json())
			.then(response => {
				if ('error' in response) {
					throw {error: response.error};
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
		this._clearWarnings();

		if (!('type' in properties)) {
			properties.type = this._widget_last_type;

			if (properties.type === null) {
				this._warn(t('Cannot add widget: no widgets available.'));

				return;
			}
		}

		this.#edit_widget_cache = {
			type: properties.type
		};

		if ('unique_id' in properties && 'dashboard_page_unique_id' in properties) {
			this.#edit_widget_cache.unique_id = properties.unique_id;
			this.#edit_widget_cache.dashboard_page_unique_id = properties.dashboard_page_unique_id;
		}
		else {
			this.#edit_widget_cache.new_widget_dashboard_page = this._selected_dashboard_page;
		}

		const overlay = PopUp(`widget.${properties.type}.edit`, {
			templateid: this._data.templateid ?? undefined,
			...properties
		}, {
			dialogueid: 'widget_properties',
			dialogue_class: 'modal-widget-configuration',
			prevent_navigation: true
		});

		overlay.xhr.then(() => {
			const form = overlay.$dialogue.$body[0].querySelector('form');

			if (!('unique_id' in properties) || !('dashboard_page_unique_id' in properties)) {
				this.#edit_widget_cache.new_widget_pos = new_widget_pos;

				const default_widget_size = this._widget_defaults[properties.type].size;

				if (new_widget_pos === null) {
					this.#edit_widget_cache.new_widget_pos_reserved =
						this.#edit_widget_cache.new_widget_dashboard_page.findFreePos(default_widget_size);
				}
				else {
					this.#edit_widget_cache.new_widget_pos_reserved = {
						...default_widget_size,
						...new_widget_pos
					};

					this.#edit_widget_cache.new_widget_pos_reserved.width = Math.min(
						this.#edit_widget_cache.new_widget_pos_reserved.width,
						this._max_columns - this.#edit_widget_cache.new_widget_pos_reserved.x
					);

					this.#edit_widget_cache.new_widget_pos_reserved.height = Math.min(
						this.#edit_widget_cache.new_widget_pos_reserved.height,
						this._max_rows - this.#edit_widget_cache.new_widget_pos_reserved.y
					);

					this.#edit_widget_cache.new_widget_pos_reserved =
						this.#edit_widget_cache.new_widget_dashboard_page.accommodatePos(
							this.#edit_widget_cache.new_widget_pos_reserved
						);
				}

				if (this.#edit_widget_cache.new_widget_pos_reserved === null) {
					for (const el of form.parentNode.children) {
						if (el.matches('.msg-warning')) {
							el.parentNode.removeChild(el);
						}
					}

					const message_box = makeMessageBox('warning', [],
						t('Cannot add widget: not enough free space on the dashboard.')
					)[0];

					form.parentNode.insertBefore(message_box, form);

					overlay.$btn_submit[0].disabled = true;
				}
			}
		});

		overlay.$dialogue[0].removeEventListener('dialogue.close', this._events.editWidgetPropertiesClose);
		overlay.$dialogue[0].addEventListener('dialogue.close', this._events.editWidgetPropertiesClose, {once: true});
	}

	reloadWidgetProperties() {
		const overlay = overlays_stack.getById('widget_properties');
		const form = overlay.$dialogue.$body[0].querySelector('form');
		const fields = getFormFields(form);

		const properties = {
			type: fields.type
		};

		if (properties.type === this.#edit_widget_cache.type) {
			properties.name = fields.name;
			properties.view_mode = fields.show_header === '1'
				? ZBX_WIDGET_VIEW_MODE_NORMAL
				: ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER;

			delete fields.type;
			delete fields.name;
			delete fields.show_header;

			properties.fields = fields;
		}

		let new_widget_pos = null;

		if ('unique_id' in this.#edit_widget_cache && 'dashboard_page_unique_id' in this.#edit_widget_cache) {
			properties.unique_id = this.#edit_widget_cache.unique_id;
			properties.dashboard_page_unique_id = this.#edit_widget_cache.dashboard_page_unique_id;
		}
		else {
			new_widget_pos = this.#edit_widget_cache.new_widget_pos;
		}

		this.editWidgetProperties(properties, {new_widget_pos});
	}

	applyWidgetProperties() {
		const overlay = overlays_stack.getById('widget_properties');
		const form = overlay.$dialogue.$body[0].querySelector('form');
		const fields = getFormFields(form);

		const templateid = this._data.templateid ?? undefined;
		const type = fields.type;
		const name = fields.name;
		const view_mode = fields.show_header === '1'
			? ZBX_WIDGET_VIEW_MODE_NORMAL
			: ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER;

		delete fields.type;
		delete fields.name;
		delete fields.show_header;

		let dashboard_page = null;
		let widget = null;

		if ('unique_id' in this.#edit_widget_cache && 'dashboard_page_unique_id' in this.#edit_widget_cache) {
			dashboard_page = this.getDashboardPage(this.#edit_widget_cache.dashboard_page_unique_id);
			widget = dashboard_page.getWidget(this.#edit_widget_cache.unique_id);
		}

		const busy_condition = this._createBusyCondition();

		Promise.resolve()
			.then(() => this._promiseDashboardWidgetCheck({templateid, type, name, view_mode, fields}))
			.then(({fields}) => {
				this._is_unsaved = true;

				overlayDialogueDestroy(overlay.dialogueid);

				if (type !== this._widget_last_type && widget?.getType() !== type) {
					this._widget_last_type = type;
					updateUserProfile('web.dashboard.last_widget_type', type, [], PROFILE_TYPE_STR);
				}

				if ('reference' in fields && fields.reference === '') {
					fields.reference = this._createReference();
				}

				const widget_data = {
					type,
					name,
					view_mode,
					fields,
					widgetid: widget === null || widget.getType() !== type ? null : widget.getWidgetId(),
					pos: widget === null ? this.#edit_widget_cache.new_widget_pos_reserved : widget.getPos(),
					is_new: widget === null,
					rf_rate: 0,
					messages: []
				};

				if (widget === null) {
					if (this.#edit_widget_cache.new_widget_dashboard_page.getState()
							=== DASHBOARD_PAGE_STATE_DESTROYED) {
						return;
					}

					this.#edit_widget_cache.new_widget_dashboard_page.promiseScrollIntoView(widget_data.pos)
						.then(() => {
							this.addWidgetFromData(this.#edit_widget_cache.new_widget_dashboard_page, widget_data);
							this.#edit_widget_cache.new_widget_dashboard_page.resetWidgetPlaceholder();
						});
				}
				else {
					if (dashboard_page.getState() === DASHBOARD_PAGE_STATE_DESTROYED) {
						return;
					}

					dashboard_page.promiseScrollIntoView(widget_data.pos)
						.then(() => {
							const widget_replace = this.replaceWidgetFromData(dashboard_page, widget, widget_data);

							dashboard_page.resetWidgetPlaceholder();

							this.#validateFieldsReferences({dashboard_page, widget_delete: widget, widget_replace});
						});
				}
			})
			.catch(exception => {
				let title;
				let messages = [];

				if (typeof exception === 'object' && 'error' in exception) {
					title = exception.error.title;
					messages = exception.error.messages;
				}
				else {
					title = t('Failed to update widget properties.');
				}

				const message_box = makeMessageBox('bad', messages, title);

				if (form.isConnected) {
					for (const element of form.parentNode.children) {
						if (element.matches('.msg-good, .msg-bad, .msg-warning')) {
							element.parentNode.removeChild(element);
						}
					}

					form.parentNode.insertBefore(message_box[0], form);
				}
				else {
					clearMessages();
					addMessage(message_box);
				}
			})
			.finally(() => {
				overlay.unsetLoading();
				this._deleteBusyCondition(busy_condition);
			});
	}

	initWidgetPropertiesForm() {
		const overlay = overlays_stack.getById('widget_properties');
		const form = overlay.$dialogue.$body[0].querySelector('form');

		form.fields = {};

		form.querySelector('[name="type"]').addEventListener('change', () => this.reloadWidgetProperties());

		form.addEventListener('change', e => {
			const do_trim = e.target.matches(
				'input[type="text"]:not([data-no-trim="1"]), textarea:not([data-no-trim="1"])'
			);

			if (do_trim) {
				e.target.value = e.target.value.trim();
			}
		}, {capture: true});

		for (const fieldset of
				form.querySelectorAll(`fieldset.${ZBX_STYLE_COLLAPSIBLE}`)) {
			new CFormFieldsetCollapsible(fieldset);
		}

		try {
			new TabIndicators();
		}
		catch (error) {
		}
	}

	getEditingWidgetContext() {
		if ('unique_id' in this.#edit_widget_cache && 'dashboard_page_unique_id' in this.#edit_widget_cache) {
			return {
				unique_id: this.#edit_widget_cache.unique_id,
				dashboard_page_unique_id: this.#edit_widget_cache.dashboard_page_unique_id
			};
		}

		return {
			unique_id: null,
			dashboard_page_unique_id: this.#edit_widget_cache.new_widget_dashboard_page.getUniqueId()
		};
	}

	_isEditingWidgetProperties() {
		return overlays_stack.getById('widget_properties') !== undefined;
	}

	_promiseDashboardWidgetCheck({templateid, type, name, view_mode, fields}) {
		const curl = new Curl('zabbix.php');

		curl.setArgument('action', 'dashboard.widget.check');

		return fetch(curl.getUrl(), {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify({templateid, type, name, view_mode, fields})
		})
			.then(response => response.json())
			.then(response => {
				if ('error' in response) {
					throw {error: response.error};
				}

				return response;
			});
	}

	_getDashboardPageActionsContextMenu(dashboard_page) {
		let menu = [];
		let menu_actions = [];

		if (this._can_edit_dashboards) {
			menu_actions.push({
				label: t('Copy'),
				clickCallback: () => {
					this._clearWarnings();

					const data_copy = dashboard_page.getDataCopy();
					const data_copy_widgets = data_copy.widgets;

					data_copy.widgets = [];

					for (const widget of data_copy_widgets) {
						if (widget.type in this._widget_defaults) {
							data_copy.widgets.push(widget);
						}
					}

					this._storeDashboardPageDataCopy(data_copy);

					if (data_copy.widgets.length < data_copy_widgets.length) {
						this._warn(t('Inaccessible widgets were not copied.'));
					}
				}
			});
		}

		if (this._is_edit_mode) {
			menu_actions.push({
				label: t('Delete'),
				disabled: this._dashboard_pages.size === 1,
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

	_warn(warning) {
		this._clearWarnings();

		this._warning_message_box = makeMessageBox('warning', [], warning);

		addMessage(this._warning_message_box);
	}

	_warnDashboardExhausted() {
		this._warn(
			t('Cannot add dashboard page: maximum number of %1$d dashboard pages has been added.')
				.replace('%1$d', this._max_dashboard_pages)
		);
	}

	_warnDashboardPageExhausted() {
		this._warn(t('Cannot add widget: not enough free space on the dashboard.'));
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
			const has_aria_expanded = this._tabs.getTarget()
				.querySelector(`.${ZBX_STYLE_BTN_DASHBOARD_PAGE_PROPERTIES}[aria-expanded="true"]`) !== null;

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

		tab.tabIndex = 0;

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

			const name = t('Page %1$d').replace('%1$d', data.index);

			tab_contents_name.textContent = name;
			tab_contents_name.title = name;
		}

		if (this._getDashboardPageActionsContextMenu(dashboard_page).length > 0) {
			const properties_button = document.createElement('button');

			properties_button.type = 'button';
			properties_button.title = t('Actions');
			properties_button.setAttribute('aria-expanded', 'false');
			properties_button.setAttribute('aria-haspopup', 'true');
			properties_button.classList.add(ZBX_STYLE_BTN_ICON, ZBX_ICON_MORE, ZBX_STYLE_BTN_DASHBOARD_PAGE_PROPERTIES);

			tab_contents.append(properties_button);
		}

		this._tabs.getTarget().insertBefore(tab, null);
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
			const tab_index = [...this._tabs.getTarget().children].indexOf(data.tab) + 1;

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

			const name = t('Page %1$d').replace('%1$d', data.index);

			tab_contents_name.textContent = name;
			tab_contents_name.title = name;
		}
	}

	_deleteTab(dashboard_page) {
		const data = this._dashboard_pages.get(dashboard_page);

		this._tabs.getTarget().removeChild(data.tab);
		this._tabs_dashboard_pages.delete(data.tab);
	}

	_selectTab(dashboard_page) {
		this._tabs.getTarget().querySelectorAll(`.${ZBX_STYLE_DASHBOARD_SELECTED_TAB}`).forEach(element => {
			element.classList.remove(ZBX_STYLE_DASHBOARD_SELECTED_TAB);
		})

		const data = this._dashboard_pages.get(dashboard_page);

		data.tab.firstElementChild.classList.add(ZBX_STYLE_DASHBOARD_SELECTED_TAB);
		this._updateNavigationButtons(dashboard_page);
		this._tabs.scrollIntoView(data.tab);
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
		return `U${(this._unique_id_index++).toString(36).toUpperCase().padStart(6, '0')}`;
	}

	/**
	 * @param {Set|null} references
	 *
	 * @returns {string}
	 */
	_createReference({references = null} = {}) {
		if (references === null) {
			references = this._getReferences();
		}

		let reference;

		do {
			reference = '';

			for (let i = 0; i < 5; i++) {
				reference += String.fromCharCode(65 + Math.floor(Math.random() * 26));
			}
		}
		while (references.has(reference));

		return reference;
	}

	_getReferences({dashboard_page = null} = {}) {
		const references = new Set();

		const dashboard_pages = dashboard_page !== null ? [dashboard_page] : this._dashboard_pages.keys();

		for (const dashboard_page of dashboard_pages) {
			for (const widget of dashboard_page.getWidgets()) {
				const fields = widget.getFields();

				if ('reference' in fields) {
					references.add(fields.reference);
				}
			}
		}

		return references;
	}

	#validateFieldsReferences({dashboard_page, widget_delete = null, widget_replace = null}) {
		const references = this._getReferences({dashboard_page});

		for (const widget of dashboard_page.getWidgets()) {
			const fields = {...widget.getFields()};

			let is_altered = false;

			for (const accessor of CWidgetBase.getFieldsReferencesAccessors(fields).values()) {
				if (accessor.getTypedReference() === '') {
					continue;
				}

				const {reference, type} = CWidgetBase.parseTypedReference(accessor.getTypedReference());

				if (reference === CDashboard.REFERENCE_DASHBOARD || references.has(reference)) {
					continue;
				}

				is_altered = true;

				if (widget_delete !== null && widget_replace !== null
						&& reference === widget_delete.getFields().reference) {
					const referable_widgets = this.getReferableWidgets({
						type,
						widget_context: {
							dashboard_page_unique_id: dashboard_page.getUniqueId(),
							unique_id: widget.getUniqueId()
						}
					});

					if (referable_widgets.includes(widget_replace)) {
						accessor.setTypedReference(CWidgetBase.createTypedReference({
							reference: widget_replace.getFields().reference,
							type
						}));

						continue;
					}
				}

				accessor.setTypedReference(CWidgetBase.createTypedReference({reference: ''}));
			}

			if (is_altered) {
				this.replaceWidgetFromData(dashboard_page, widget, {
					...widget.getDataCopy({is_single_copy: false}),
					fields,
					widgetid: widget.getWidgetId(),
					is_new: false,
					messages: []
				});
			}
		}
	}

	getReferableWidgets({type, widget_context: {dashboard_page_unique_id, unique_id = null}}) {
		const dashboard_page = this.getDashboardPage(dashboard_page_unique_id);

		const widgets_with_reference = new Map();

		for (const widget of dashboard_page.getWidgets()) {
			if ('reference' in widget.getFields()) {
				widgets_with_reference.set(widget.getUniqueId(), widget);
			}
		}

		const referable_widgets = new Set();

		for (const widget of widgets_with_reference.values()) {
			if (widget.getBroadcastTypes().includes(type)) {
				referable_widgets.add(widget);
			}
		}

		if (unique_id !== null && widgets_with_reference.has(unique_id)) {
			referable_widgets.delete(widgets_with_reference.get(unique_id));

			let circular_references = new Set([widgets_with_reference.get(unique_id).getFields().reference]);

			while (circular_references.size > 0) {
				let circular_references_next = new Set();

				for (const widget of widgets_with_reference.values()) {
					const fields = widget.getFields();

					for (const accessor of CWidgetBase.getFieldsReferencesAccessors(fields).values()) {
						const {reference} = CWidgetBase.parseTypedReference(accessor.getTypedReference());

						if (reference !== '' && circular_references.has(reference)) {
							circular_references_next.add(fields.reference);

							referable_widgets.delete(widget);

							break;
						}
					}
				}

				circular_references = circular_references_next;
			}
		}

		return [...referable_widgets];
	}

	// Internal events management methods.

	#registerEvents() {
		let wrapper_scrollbar_width = 0;
		let user_interaction_animation_frame = null;

		this._events = {
			dashboardPageReady: e => {
				const data = this._dashboard_pages.get(e.detail.target);

				data.is_ready = true;

				this._updateReadyState();
			},

			dashboardPageRequireDataSource: e => {
				if (e.detail.reference === CDashboard.REFERENCE_DASHBOARD && this.#broadcast_cache.has(e.detail.type)) {
					return;
				}

				ZABBIX.EventHub.publish({
					data: CWidgetsData.getDefault(e.detail.type),
					descriptor: {
						context: 'dashboard',
						sender_unique_id: 'dashboard',
						sender_type: 'dashboard',
						event_type: 'broadcast',
						event_origin: 'dashboard',
						reference: e.detail.reference,
						type: e.detail.type
					}
				});

				console.log('Could not require referred data source', `${e.detail.reference}.${e.detail.type}`);
			},

			dashboardPageEdit: () => {
				this.setEditMode({is_internal_call: true});
			},

			dashboardPageWidgetAdd: e => {
				const dashboard_page = this._selected_dashboard_page;

				const new_widget_data = this.getStoredWidgetDataCopy();
				const new_widget_pos = e.detail.new_widget_pos;

				if (new_widget_data !== null) {
					const menu = [
						{
							label: t('Actions'),
							items: [
								{
									label: t('Add widget'),
									clickCallback: () => this.editWidgetProperties({}, {new_widget_pos})
								},
								{
									label: t('Paste widget'),
									clickCallback: () => this.pasteWidget(new_widget_data, {new_widget_pos})
								}
							]
						}
					];

					const placeholder = e.detail.placeholder;
					const placeholder_event = new jQuery.Event(e.detail.mouse_event);

					placeholder_event.target = placeholder;

					jQuery(placeholder).menuPopup(menu, placeholder_event, {
						closeCallback: () => {
							if (!this._isEditingWidgetProperties()) {
								dashboard_page.resetWidgetPlaceholder();
							}
						}
					});
				}
				else {
					this.editWidgetProperties({}, {new_widget_pos});

					if (!this._isEditingWidgetProperties()) {
						dashboard_page.resetWidgetPlaceholder();
					}
				}
			},

			dashboardPageWidgetAddNew: () => {
				this.editWidgetProperties();
			},

			dashboardPageWidgetDelete: () => {
				this._clearWarnings();

				this.#validateFieldsReferences({dashboard_page: this._selected_dashboard_page});
			},

			dashboardPageWidgetPosition: () => {
				this._clearWarnings();
			},

			dashboardPageWidgetActions: e => {
				const menu = e.detail.widget.getActionsContextMenu({
					can_copy_widget: this._can_edit_dashboards
						&& (this._data.templateid === null
							|| this.#broadcast_cache.get(CWidgetsData.DATA_TYPE_HOST_ID)
								=== CWidgetsData.getDefault(CWidgetsData.DATA_TYPE_HOST_ID)
						),
					can_paste_widget: this._can_edit_dashboards && this.getStoredWidgetDataCopy() !== null
				});

				jQuery(e.detail.mouse_event.target).menuPopup(menu, new jQuery.Event(e.detail.mouse_event));
			},

			dashboardPageWidgetEdit: e => {
				const dashboard_page = e.detail.target;
				const widget = e.detail.widget;

				this.editWidgetProperties({
					type: widget.getType(),
					name: widget.getName(),
					view_mode: widget.getViewMode(),
					fields: widget.getFields(),
					unique_id: widget.getUniqueId(),
					dashboard_page_unique_id: dashboard_page.getUniqueId()
				});
			},

			dashboardPageWidgetCopy: e => {
				const widget = e.detail.widget;

				this._storeWidgetDataCopy(widget.getDataCopy({is_single_copy: true}));
			},

			dashboardPageWidgetPaste: e => {
				const widget = e.detail.widget;

				this.pasteWidget(this.getStoredWidgetDataCopy(), {widget});
			},

			dashboardPageReserveHeaderLines: e => {
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

			tabsDragEnd: () => {
				this._updateNavigationButtons();
			},

			tabsSort: () => {
				this._setInitialDashboardPage(this._selected_dashboard_page);

				this._is_unsaved = true;
			},

			tabsMouseDown: e => {
				const tab = e.target.closest('li');

				if (tab !== null && tab.parentElement === this._tabs.getTarget()) {
					tab.focus();
				}
			},

			tabsClick: e => {
				const tab = e.target.closest('li');

				if (tab !== null && tab.parentElement === this._tabs.getTarget()) {
					const dashboard_page = this._tabs_dashboard_pages.get(tab);

					if (dashboard_page !== this._selected_dashboard_page) {
						this._selectDashboardPage(dashboard_page, {is_async: true});
					}
					else if (e.target.classList.contains(ZBX_STYLE_BTN_DASHBOARD_PAGE_PROPERTIES)) {
						jQuery(e.target).menuPopup(this._getDashboardPageActionsContextMenu(dashboard_page),
							new jQuery.Event(e)
						);
					}
				}
			},

			tabsKeyDown: e => {
				if (e.key === 'Enter') {
					const tab = e.target.closest('li');

					if (tab !== null) {
						const dashboard_page = this._tabs_dashboard_pages.get(tab);

						if (dashboard_page !== this._selected_dashboard_page) {
							this._selectDashboardPage(dashboard_page, {is_async: true});
						}
						else if (e.target.classList.contains(ZBX_STYLE_BTN_DASHBOARD_PAGE_PROPERTIES)) {
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
						this._keepSteadyConfigurationChecker();

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

			editWidgetPropertiesClose: () => {
				this._selected_dashboard_page.resetWidgetPlaceholder();
			},

			feedback: ({data, descriptor}) => {
				if (!('type' in descriptor) || !this.#broadcast_cache.has(descriptor.type)) {
					return;
				}

				if (JSON.stringify(this.#broadcast_cache.get(descriptor.type)) !== JSON.stringify(data)) {
					this.#broadcast_cache.set(descriptor.type, data);

					this.fire(CDashboard.EVENT_FEEDBACK, {
						type: descriptor.type,
						value: data
					});

					if (this.#broadcast_options[descriptor.type].rebroadcast) {
						ZABBIX.EventHub.publish({
							data,
							descriptor: {
								context: 'dashboard',
								sender_unique_id: 'dashboard',
								sender_type: 'dashboard',
								event_type: 'broadcast',
								event_origin: descriptor.event_origin,
								reference: CDashboard.REFERENCE_DASHBOARD,
								type: descriptor.type
							}
						});
					}
				}
			}
		};
	}

	#activateEvents() {
		if (!this._is_kiosk_mode) {
			new ResizeObserver(this._events.gridResize).observe(this._containers.grid);
			new ResizeObserver(this._events.tabsResize).observe(this._containers.navigation_tabs);

			this._tabs.on(CSortable.EVENT_DRAG_END, this._events.tabsDragEnd);
			this._tabs.on(CSortable.EVENT_SORT, this._events.tabsSort);

			this._containers.navigation_tabs.addEventListener('mousedown', this._events.tabsMouseDown);
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

		ZABBIX.EventHub.subscribe({
			require: {
				context: 'dashboard',
				event_type: 'feedback',
				reference: CDashboard.REFERENCE_DASHBOARD
			},
			callback: this._events.feedback
		});
	}

	/**
	 * Attach event listener to dashboard events.
	 *
	 * @param {string}			type
	 * @param {function}		listener
	 * @param {Object|false}	options
	 *
	 * @returns {CDashboard}
	 */
	on(type, listener, options = false) {
		this._target.addEventListener(type, listener, options);

		return this;
	}

	/**
	 * Detach event listener from dashboard events.
	 *
	 * @param {string}			type
	 * @param {function}		listener
	 * @param {Object|false}	options
	 *
	 * @returns {CDashboard}
	 */
	off(type, listener, options = false) {
		this._target.removeEventListener(type, listener, options);

		return this;
	}

	/**
	 * Dispatch dashboard event.
	 *
	 * @param {string}	type
	 * @param {Object}	detail
	 * @param {Object}	options
	 *
	 * @returns {boolean}
	 */
	fire(type, detail = {}, options = {}) {
		return this._target.dispatchEvent(new CustomEvent(type, {...options, detail: {target: this, ...detail}}));
	}
}
