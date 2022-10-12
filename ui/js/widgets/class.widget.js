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


const ZBX_WIDGET_VIEW_MODE_NORMAL = 0;
const ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER = 1;

const WIDGET_STATE_INITIAL = 'initial';
const WIDGET_STATE_ACTIVE = 'active';
const WIDGET_STATE_INACTIVE = 'inactive';
const WIDGET_STATE_DESTROYED = 'destroyed';

const WIDGET_EVENT_EDIT = 'widget-edit';
const WIDGET_EVENT_ACTIONS = 'widget-actions';
const WIDGET_EVENT_ENTER = 'widget-enter';
const WIDGET_EVENT_LEAVE = 'widget-leave';
const WIDGET_EVENT_BEFORE_UPDATE = 'widget-before-update';
const WIDGET_EVENT_AFTER_UPDATE = 'widget-after-update';
const WIDGET_EVENT_COPY = 'widget-copy';
const WIDGET_EVENT_PASTE = 'widget-paste';
const WIDGET_EVENT_DELETE = 'widget-delete';

class CWidget extends CBaseComponent {

	constructor({
		type,
		name,
		view_mode,
		fields,
		configuration,
		defaults,
		widgetid = null,
		pos = null,
		is_new,
		rf_rate,
		dashboard,
		dashboard_page,
		cell_width,
		cell_height,
		min_rows,
		is_editable,
		is_edit_mode,
		can_edit_dashboards,
		time_period,
		dynamic_hostid,
		scope_id,
		unique_id
	}) {
		super(document.createElement('div'));

		this._type = type;
		this._name = name;
		this._view_mode = view_mode;
		this._fields = fields;
		this._configuration = configuration;
		this._defaults = defaults;
		this._widgetid = widgetid;
		this._pos = pos;
		this._is_new = is_new;
		this._rf_rate = rf_rate;

		this._dashboard = {
			templateid: dashboard.templateid,
			dashboardid: dashboard.dashboardid
		};

		this._dashboard_page = {
			unique_id: dashboard_page.unique_id
		};

		this._cell_width = cell_width;
		this._cell_height = cell_height;
		this._min_rows = min_rows;
		this._is_editable = is_editable;
		this._is_edit_mode = is_edit_mode;
		this._can_edit_dashboards = can_edit_dashboards;
		this._time_period = time_period;
		this._dynamic_hostid = dynamic_hostid;
		this._scope_id = scope_id;
		this._unique_id = unique_id;

		this._init();
		this._registerEvents();
	}

	_init() {
		this._css_classes = {
			actions: 'dashboard-grid-widget-actions',
			container: 'dashboard-grid-widget-container',
			content: 'dashboard-grid-widget-content',
			focus: 'dashboard-grid-widget-focus',
			head: 'dashboard-grid-widget-head',
			hidden_header: 'dashboard-grid-widget-hidden-header',
			mask: 'dashboard-grid-widget-mask',
			root: 'dashboard-grid-widget',
			resize_handle: 'ui-resizable-handle'
		};

		this._state = WIDGET_STATE_INITIAL;

		this._content_size = {};
		this._update_timeout_id = null;
		this._update_interval_id = null;
		this._update_abort_controller = null;
		this._is_updating_paused = false;
		this._update_retry_sec = 3;
		this._show_preloader_asap = true;
		this._resizable_handles = [];

		this._hide_preloader_animation_frame = null;
	}

	// Logical state control methods.

	getState() {
		return this._state;
	}

	start() {
		if (this._state !== WIDGET_STATE_INITIAL) {
			throw new Error('Unsupported state change.');
		}

		this._state = WIDGET_STATE_INACTIVE;

		this._doStart();
	}

	_doStart() {
		this._makeView();

		if (this._pos !== null) {
			this.setPos(this._pos);
		}
	}

	activate() {
		if (this._state !== WIDGET_STATE_INACTIVE) {
			throw new Error('Unsupported state change.');
		}

		this._state = WIDGET_STATE_ACTIVE;

		this._doActivate();
	}

	_doActivate() {
		this._activateEvents();
		this._startUpdating();
	}

	deactivate() {
		if (this._state !== WIDGET_STATE_ACTIVE) {
			throw new Error('Unsupported state change.');
		}

		this._state = WIDGET_STATE_INACTIVE;

		this._doDeactivate();
	}

	_doDeactivate() {
		if (this._is_new) {
			this._is_new = false;
			this._target.classList.remove('new-widget');
		}

		this._deactivateEvents();
		this._stopUpdating();
	}

	destroy() {
		if (this._state === WIDGET_STATE_ACTIVE) {
			this.deactivate();
		}

		if (this._state !== WIDGET_STATE_INACTIVE) {
			throw new Error('Unsupported state change.');
		}

		this._state = WIDGET_STATE_DESTROYED;

		this._doDestroy();
	}

	_doDestroy() {
	}

	// External events management methods.

	isEditMode() {
		return this._is_edit_mode;
	}

	setEditMode() {
		this._is_edit_mode = true;

		if (this._state === WIDGET_STATE_ACTIVE) {
			this._stopUpdating({do_abort: false});
		}

		this._target.classList.add('ui-draggable', 'ui-resizable');
	}

	supportsDynamicHosts() {
		return this._fields.dynamic == 1;
	}

	getDynamicHost() {
		return this._dynamic_hostid;
	}

	setDynamicHost(dynamic_hostid) {
		this._dynamic_hostid = dynamic_hostid;

		if (this._state === WIDGET_STATE_ACTIVE) {
			this._startUpdating();
		}
	}

	setTimePeriod(time_period) {
		this._time_period = time_period;
	}

	isEntered() {
		return this._target.classList.contains(this._css_classes.focus);
	}

	enter() {
		if (this._is_edit_mode) {
			this._addResizeHandles();
		}

		this._target.classList.add(this._css_classes.focus);
	}

	leave() {
		if (this._is_edit_mode) {
			this._removeResizeHandles();
		}

		if (this._content_header.contains(document.activeElement)) {
			document.activeElement.blur();
		}

		this._target.classList.remove(this._css_classes.focus);
	}

	getNumHeaderLines() {
		return this._view_mode == ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER ? 1 : 0;
	}

	_isResizing() {
		return this._target.classList.contains('ui-resizable-resizing');
	}

	setResizing(is_resizing) {
		this._target.classList.toggle('ui-resizable-resizing', is_resizing);
	}

	_isDragging() {
		return this._target.classList.contains('ui-draggable-dragging');
	}

	setDragging(is_dragging) {
		this._target.classList.toggle('ui-draggable-dragging', is_dragging);
	}

	isUserInteracting() {
		return this._target.querySelectorAll('[data-expanded="true"], [aria-expanded="true"]').length > 0;
	}

	announceWidgets(widgets) {
	}

	resize() {
	}

	// Data interface methods.

	getUniqueId() {
		return this._unique_id;
	}

	getType() {
		return this._type;
	}

	getName() {
		return this._name;
	}

	_setName(name) {
		this._name = name;
		this._setHeaderName(this._name !== '' ? this._name : this._defaults.name);
	}

	getHeaderName() {
		return this._name !== '' ? this._name : this._defaults.name;
	}

	_setHeaderName(name) {
		if (this._state !== WIDGET_STATE_INITIAL) {
			this._content_header.querySelector('h4').textContent = name;
		}
	}

	getViewMode() {
		return this._view_mode;
	}

	_setViewMode(view_mode) {
		if (this._view_mode !== view_mode) {
			this._view_mode = view_mode;
			this._target.classList.toggle(this._css_classes.hidden_header,
				this._view_mode == ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER
			);
		}
	}

	getFields() {
		return this._fields;
	}

	_setFields(fields) {
		this._fields = fields;
	}

	_setConfiguration(configuration) {
		this._configuration = configuration;

		if (this._state !== WIDGET_STATE_INITIAL) {
			this._content_body.classList.toggle('no-padding', !this._configuration.padding);
		}
	}

	updateProperties({name, view_mode, fields, configuration}) {
		if (name !== undefined) {
			this._setName(name);
		}

		if (view_mode !== undefined) {
			this._setViewMode(view_mode);
		}

		if (fields !== undefined) {
			this._setFields(fields);
		}

		if (configuration !== undefined) {
			this._setConfiguration(configuration);
		}

		this._show_preloader_asap = true;

		if (this._state === WIDGET_STATE_ACTIVE) {
			this._startUpdating();
		}
	}

	getRfRate() {
		return this._rf_rate;
	}

	_setRfRate(rf_rate) {
		this._rf_rate = rf_rate;

		if (this._widgetid !== null) {
			const curl = new Curl('zabbix.php');

			curl.setArgument('action', 'dashboard.widget.rfrate');

			fetch(curl.getUrl(), {
				method: 'POST',
				headers: {'Content-Type': 'application/json'},
				body: JSON.stringify({widgetid: this._widgetid, rf_rate})
			});
		}
	}

	getDataCopy({is_single_copy}) {
		const data = {
			type: this._type,
			name: this._name,
			view_mode: this._view_mode,
			fields: this._fields,
			configuration: this._configuration,
			pos: is_single_copy
				? {
					width: this._pos.width,
					height: this._pos.height
				}
				: this._pos,
			rf_rate: this._rf_rate
		};

		if (is_single_copy) {
			data.dashboard = {
				templateid: this._dashboard.templateid
			};
		}

		return data;
	}

	save() {
		return {
			widgetid: this._widgetid ?? undefined,
			pos: this._pos,
			type: this._type,
			name: this._name,
			view_mode: this._view_mode,
			fields: Object.keys(this._fields).length > 0 ? JSON.stringify(this._fields) : undefined
		};
	}

	getActionsContextMenu({can_paste_widget}) {
		let menu = [];
		let menu_actions = [];

		if (this._can_edit_dashboards && (this._dashboard.templateid === null || this._dynamic_hostid === null)) {
			menu_actions.push({
				label: t('Copy'),
				clickCallback: () => this.fire(WIDGET_EVENT_COPY)
			});
		}

		if (this._is_edit_mode) {
			menu_actions.push({
				label: t('Paste'),
				disabled: can_paste_widget === false,
				clickCallback: () => this.fire(WIDGET_EVENT_PASTE)
			});

			menu_actions.push({
				label: t('Delete'),
				clickCallback: () => this.fire(WIDGET_EVENT_DELETE)
			});
		}

		if (menu_actions.length) {
			menu.push({
				label: t('Actions'),
				items: menu_actions
			});
		}

		if (!this._is_edit_mode) {
			const refresh_interval_section = {
				label: t('Refresh interval'),
				items: []
			};

			const rf_rates = {
				0: t('No refresh'),
				10: t('10 seconds'),
				30: t('30 seconds'),
				60: t('1 minute'),
				120: t('2 minutes'),
				600: t('10 minutes'),
				900: t('15 minutes')
			};

			for (const [rf_rate, label] of Object.entries(rf_rates)) {
				refresh_interval_section.items.push({
					label: label,
					selected: rf_rate == this._rf_rate,
					clickCallback: () => {
						this._setRfRate(rf_rate);

						if (this._state === WIDGET_STATE_ACTIVE) {
							if (this._rf_rate > 0) {
								this._startUpdating();
							}
							else {
								this._stopUpdating();
							}
						}
					}
				});
			}

			menu.push(refresh_interval_section);
		}

		return menu;
	}

	// Content updating methods.

	_startUpdating(delay_sec = 0, {do_update_once = null} = {}) {
		if (do_update_once === null) {
			do_update_once = this._is_edit_mode;
		}

		this._stopUpdating({do_abort: false});

		if (delay_sec > 0) {
			this._update_timeout_id = setTimeout(() => {
				this._update_timeout_id = null;
				this._startUpdating(0, {do_update_once});
			}, delay_sec * 1000);
		}
		else {
			if (!do_update_once && this._rf_rate > 0) {
				this._update_interval_id = setInterval(() => {
					this._update(do_update_once);
				}, this._rf_rate * 1000);
			}

			this._update(do_update_once);
		}
	}

	_stopUpdating({do_abort = true} = {}) {
		if (this._update_timeout_id !== null) {
			clearTimeout(this._update_timeout_id);
			this._update_timeout_id = null;
		}

		if (this._update_interval_id !== null) {
			clearInterval(this._update_interval_id);
			this._update_interval_id = null;
		}

		if (do_abort && this._update_abort_controller !== null) {
			this._update_abort_controller.abort();
		}
	}

	_pauseUpdating() {
		this._is_updating_paused = true;
	}

	_resumeUpdating() {
		this._is_updating_paused = false;
	}

	_update(do_update_once) {
		if (this._update_abort_controller !== null || this._is_updating_paused || this.isUserInteracting()) {
			this._startUpdating(1, {do_update_once});

			return;
		}

		this.fire(WIDGET_EVENT_BEFORE_UPDATE);

		this._content_size = this._getContentSize();

		this._update_abort_controller = new AbortController();

		if (this._show_preloader_asap) {
			this._show_preloader_asap = false;
			this._showPreloader();
		}
		else {
			this._schedulePreloader();
		}

		new Promise((resolve) => resolve(this._promiseUpdate()))
			.then(() => this._hidePreloader())
			.catch((error) => {
				console.log('Could not update widget:', error);

				if (this._update_abort_controller.signal.aborted) {
					this._hidePreloader();
				}
				else {
					this._startUpdating(this._update_retry_sec, {do_update_once});
				}
			})
			.finally(() => {
				this._update_abort_controller = null;

				this.fire(WIDGET_EVENT_AFTER_UPDATE);
			});
	}

	_promiseUpdate() {
		const curl = new Curl('zabbix.php');

		curl.setArgument('action', `widget.${this._type}.view`);

		return fetch(curl.getUrl(), {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify(this._getUpdateRequestData()),
			signal: this._update_abort_controller.signal
		})
			.then((response) => response.json())
			.then((response) => this._processUpdateResponse(response));
	}

	_getUpdateRequestData() {
		return {
			templateid: this._dashboard.templateid ?? undefined,
			dashboardid: this._dashboard.dashboardid ?? undefined,
			widgetid: this._widgetid ?? undefined,
			name: this._name !== '' ? this._name : undefined,
			fields: Object.keys(this._fields).length > 0 ? JSON.stringify(this._fields) : undefined,
			view_mode: this._view_mode,
			edit_mode: this._is_edit_mode ? 1 : 0,
			dynamic_hostid: this._dashboard.templateid !== null || this.supportsDynamicHosts()
				? (this._dynamic_hostid ?? undefined)
				: undefined,
			...this._content_size
		};
	}

	_processUpdateResponse(response) {
		this._setContents({
			name: response.name,
			body: response.body,
			messages: response.messages,
			info: response.info,
			debug: response.debug
		});
	}

	// Widget view methods.

	getView() {
		return this._target;
	}

	getCssClass(name) {
		return this._css_classes[name];
	}

	getPos() {
		return this._pos;
	}

	setPos(pos, {is_managed = false} = {}) {
		this._pos = pos;

		if (!is_managed) {
			this._target.style.left = `${this._cell_width * this._pos.x}%`;
			this._target.style.top = `${this._cell_height * this._pos.y}px`;
			this._target.style.width = `${this._cell_width * this._pos.width}%`;
			this._target.style.height = `${this._cell_height * this._pos.height}px`;
		}
	}

	getResizeHandleSides(resize_handle) {
		return {
			top: resize_handle.classList.contains('ui-resizable-nw')
				|| resize_handle.classList.contains('ui-resizable-n')
				|| resize_handle.classList.contains('ui-resizable-ne'),
			right: resize_handle.classList.contains('ui-resizable-ne')
				|| resize_handle.classList.contains('ui-resizable-e')
				|| resize_handle.classList.contains('ui-resizable-se'),
			bottom: resize_handle.classList.contains('ui-resizable-se')
				|| resize_handle.classList.contains('ui-resizable-s')
				|| resize_handle.classList.contains('ui-resizable-sw'),
			left: resize_handle.classList.contains('ui-resizable-sw')
				|| resize_handle.classList.contains('ui-resizable-w')
				|| resize_handle.classList.contains('ui-resizable-nw')
		};
	}

	_addResizeHandles() {
		this._resizable_handles = {};

		for (const direction of ['n', 'e', 's', 'w', 'ne', 'se', 'sw', 'nw']) {
			const resizable_handle = document.createElement('div');

			resizable_handle.classList.add('ui-resizable-handle', `ui-resizable-${direction}`);

			if (['n', 'e', 's', 'w'].includes(direction)) {
				const ui_resize_dot = document.createElement('div');

				ui_resize_dot.classList.add('ui-resize-dot');
				resizable_handle.appendChild(ui_resize_dot);

				const ui_resizable_border = document.createElement('div');

				ui_resizable_border.classList.add(`ui-resizable-border-${direction}`);
				resizable_handle.appendChild(ui_resizable_border);
			}

			this._target.append(resizable_handle);
			this._resizable_handles[direction] = resizable_handle;
		}
	}

	_removeResizeHandles() {
		for (const resizable_handle of Object.values(this._resizable_handles)) {
			resizable_handle.remove();
		}

		this._resizable_handles = {};
	}

	_getContentSize() {
		const computed_style = getComputedStyle(this._content_body);

		const content_width = parseInt(
			parseFloat(computed_style.width)
				- parseFloat(computed_style.paddingLeft) - parseFloat(computed_style.paddingRight)
				- parseFloat(computed_style.borderLeftWidth) - parseFloat(computed_style.borderRightWidth)
		);

		const content_height = parseInt(
			parseFloat(computed_style.height)
				- parseFloat(computed_style.paddingTop) - parseFloat(computed_style.paddingBottom)
				- parseFloat(computed_style.borderTopWidth) - parseFloat(computed_style.borderBottomWidth)
		);

		return {content_width, content_height};
	}

	_setContents({name, body, messages, info, debug}) {
		this._setHeaderName(name);

		this._content_body.innerHTML = '';

		if (messages !== undefined) {
			this._content_body.insertAdjacentHTML('beforeend', messages);
		}

		if (body !== undefined) {
			this._content_body.insertAdjacentHTML('beforeend', body);
		}

		if (debug !== undefined) {
			this._content_body.insertAdjacentHTML('beforeend', debug);
		}

		this._removeInfoButtons();

		if (info !== undefined) {
			this._addInfoButtons(info);
		}
	}

	_addInfoButtons(buttons) {
		buttons.reverse();

		for (const button of buttons) {
			const li = document.createElement('li');

			li.classList.add('widget-info-button');

			const li_button = document.createElement('button');

			li_button.type = 'button';
			li_button.setAttribute('data-hintbox', '1');
			li_button.setAttribute('data-hintbox-static', '1');
			li_button.classList.add(button.icon);
			li.appendChild(li_button);

			const li_div = document.createElement('div');

			li_div.innerHTML = button.hint;
			li_div.classList.add('hint-box');
			li_div.style.display = 'none';
			li.appendChild(li_div);

			this._actions.prepend(li);
		}
	}

	_removeInfoButtons() {
		for (const li of this._actions.querySelectorAll('.widget-info-button')) {
			li.remove();
		}
	}

	_showPreloader() {
		// Fixed Safari 16 bug: removing preloader classes on animation frame to ensure removal of icons.

		if (this._hide_preloader_animation_frame !== null) {
			cancelAnimationFrame(this._hide_preloader_animation_frame);
			this._hide_preloader_animation_frame = null;
		}

		this._content_body.classList.add('is-loading');
		this._content_body.classList.remove('is-loading-fadein', 'delayed-15s');
	}

	_hidePreloader() {
		// Fixed Safari 16 bug: removing preloader classes on animation frame to ensure removal of icons.

		if (this._hide_preloader_animation_frame !== null) {
			return;
		}

		this._hide_preloader_animation_frame = requestAnimationFrame(() => {
			this._content_body.classList.remove('is-loading', 'is-loading-fadein', 'delayed-15s');
			this._hide_preloader_animation_frame = null;
		});
	}

	_schedulePreloader() {
		// Fixed Safari 16 bug: removing preloader classes on animation frame to ensure removal of icons.

		if (this._hide_preloader_animation_frame !== null) {
			cancelAnimationFrame(this._hide_preloader_animation_frame);
			this._hide_preloader_animation_frame = null;
		}

		this._content_body.classList.add('is-loading', 'is-loading-fadein', 'delayed-15s');
	}

	_makeView() {
		this._container = document.createElement('div');
		this._container.classList.add(this._css_classes.container);

		this._content_header = document.createElement('div');
		this._content_header.classList.add(this._css_classes.head);

		const content_header_h4 = document.createElement('h4');

		content_header_h4.textContent = this._name !== '' ? this._name : this._defaults.name;
		this._content_header.appendChild(content_header_h4);

		this._actions = document.createElement('ul');
		this._actions.classList.add(this._css_classes.actions);

		if (this._is_editable) {
			this._button_edit = document.createElement('button');
			this._button_edit.type = 'button';
			this._button_edit.title = t('Edit')
			this._button_edit.classList.add('btn-widget-edit');

			const li = document.createElement('li');

			li.appendChild(this._button_edit);
			this._actions.appendChild(li);
		}

		this._button_actions = document.createElement('button');
		this._button_actions.type = 'button';
		this._button_actions.title = t('Actions');
		this._button_actions.setAttribute('aria-expanded', 'false');
		this._button_actions.setAttribute('aria-haspopup', 'true');
		this._button_actions.classList.add('btn-widget-action');

		const li = document.createElement('li');

		li.appendChild(this._button_actions);
		this._actions.appendChild(li);

		this._content_header.append(this._actions);

		this._container.appendChild(this._content_header);

		this._content_body = document.createElement('div');
		this._content_body.classList.add(this._css_classes.content);
		this._content_body.classList.toggle('no-padding', !this._configuration.padding);

		this._container.appendChild(this._content_body);

		this._target.appendChild(this._container);
		this._target.classList.add(this._css_classes.root);
		this._target.classList.toggle('ui-draggable', this._is_edit_mode);
		this._target.classList.toggle('ui-resizable', this._is_edit_mode);
		this._target.classList.toggle(this._css_classes.hidden_header,
			this._view_mode == ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER
		);
		this._target.classList.toggle('new-widget', this._is_new);

		this._target.style.minWidth = `${this._cell_width}%`;
		this._target.style.minHeight = `${this._cell_height}px`;
	}

	// Internal events management methods.

	_registerEvents() {
		this._events = {
			actions: (e) => {
				this.fire(WIDGET_EVENT_ACTIONS, {mouse_event: e});
			},

			edit: () => {
				this.fire(WIDGET_EVENT_EDIT);
			},

			focusin: () => {
				this.fire(WIDGET_EVENT_ENTER);
			},

			focusout: (e) => {
				if (!this._content_header.contains(e.relatedTarget)) {
					this.fire(WIDGET_EVENT_LEAVE);
				}
			},

			enter: () => {
				this.fire(WIDGET_EVENT_ENTER);
			},

			leave: () => {
				this.fire(WIDGET_EVENT_LEAVE);
			}
		};
	}

	_activateEvents() {
		this._button_actions.addEventListener('click', this._events.actions);

		if (this._is_editable) {
			this._button_edit.addEventListener('click', this._events.edit);
		}

		this._target.addEventListener('mousemove', this._events.enter);
		this._target.addEventListener('mouseleave', this._events.leave);
		this._content_header.addEventListener('focusin', this._events.focusin);
		this._content_header.addEventListener('focusout', this._events.focusout);
	}

	_deactivateEvents() {
		this._button_actions.removeEventListener('click', this._events.actions);

		if (this._is_editable) {
			this._button_edit.removeEventListener('click', this._events.edit);
		}

		this._target.removeEventListener('mousemove', this._events.enter);
		this._target.removeEventListener('mouseleave', this._events.leave);
		this._content_header.removeEventListener('focusin', this._events.focusin);
		this._content_header.removeEventListener('focusout', this._events.focusout);
	}
}
