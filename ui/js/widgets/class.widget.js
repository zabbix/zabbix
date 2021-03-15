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

const ZBX_WIDGET_VIEW_MODE_NORMAL = 0;
const ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER = 1;

const WIDGET_EVENT_EDIT = 'edit';
const WIDGET_EVENT_ENTER = 'enter';
const WIDGET_EVENT_LEAVE = 'leave';
const WIDGET_EVENT_BEFORE_UPDATE = 'before-update';
const WIDGET_EVENT_AFTER_UPDATE = 'after-update';
const WIDGET_EVENT_COPY = 'copy';
const WIDGET_EVENT_PASTE = 'paste';
const WIDGET_EVENT_DELETE = 'delete';

const WIDGET_STATE_INITIAL = 'initial';
const WIDGET_STATE_ACTIVE = 'active';
const WIDGET_STATE_INACTIVE = 'inactive';
const WIDGET_STATE_DESTROYED = 'destroyed';

class CWidget extends CBaseComponent {

	constructor({
		type,
		name,
		view_mode,
		fields,
		configuration,
		defaults,
		parent = null,
		widgetid = null,
		pos = null,
		is_new,
		rf_rate,
		dashboard,
		dashboard_page,
		cell_width,
		cell_height,
		is_editable,
		is_edit_mode,
		can_edit_dashboards,
		time_period,
		dynamic_hostid,
		unique_id
	}) {
		super(document.createElement('div'));

		this._$target = $(this._target);

		this._type = type;
		this._name = name;
		this._view_mode = view_mode;
		this._fields = fields;
		this._configuration = configuration;
		this._defaults = defaults;
		this._parent = parent;
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
		this._is_editable = is_editable;
		this._is_edit_mode = is_edit_mode;
		this._can_edit_dashboards = can_edit_dashboards;
		this._time_period = time_period;
		this._dynamic_hostid = dynamic_hostid;
		this._unique_id = unique_id;

		this._init();
	}

	_init() {
		this._css_classes = {
			actions: 'dashbrd-grid-widget-actions',
			container: 'dashbrd-grid-widget-container',
			content: 'dashbrd-grid-widget-content',
			focus: 'dashbrd-grid-widget-focus',
			head: 'dashbrd-grid-widget-head',
			hidden_header: 'dashbrd-grid-widget-hidden-header',
			mask: 'dashbrd-grid-widget-mask',
			root: 'dashbrd-grid-widget'
		};

		this._state = WIDGET_STATE_INITIAL;

		this._content_size = {};
		this._update_timeout_id = null;
		this._update_interval_id = null;
		this._update_abort_controller = null;
		this._is_updating_paused = false;
		this._update_retry_sec = 3;
		this._preloader_timeout = null;
		this._preloader_timeout_sec = 10;
		this._show_preloader_asap = true;
		this._storage = {};

		this._resizable_handles = [];
	}

	getCssClass(name) {
		return this._css_classes[name];
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
			this.setPosition(this._pos);
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
		this._registerEvents();
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
		this._unregisterEvents();
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

	isEntered() {
		return this._$target.hasClass(this._css_classes.focus);
	}

	/**
	 * Focus specified top-level widget.
	 */
	enter() {
		if (this._is_edit_mode) {
			this._addResizeHandles();
		}

		this._$target.addClass(this._css_classes.focus);
	}

	/**
	 * Blur specified top-level widget.
	 */
	leave() {
		if (this._is_edit_mode) {
			this._removeResizeHandles();
		}

		if (this._$content_header.has(document.activeElement).length != 0) {
			document.activeElement.blur();
		}

		this._$target.removeClass(this._css_classes.focus);
	}

	getNumHeaderLines() {
		return (this._view_mode == ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER) ? 1 : 0;
	}

	resize() {
	}

	_setName(name) {
		this._name = name;

		if (this._state !== WIDGET_STATE_INITIAL) {
			this._$content_header.find('h4').text(name);
		}
	}

	_setFields(fields) {
		this._fields = fields;
	}

	_setConfiguration(configuration) {
		this._configuration = configuration;

		if (this._state !== WIDGET_STATE_INITIAL) {
			this._$content_body.toggleClass('no-padding', !this._configuration.padding);
		}
	}

	getState() {
		return this._state;
	}

	getType() {
		return this._type;
	}

	getName() {
		return this._name;
	}

	getView() {
		return this._target;
	}

	announceWidgets(widgets) {
	}

	isInteracting() {
		return (this._$target.find('[data-expanded="true"], [aria-expanded="true"]').length > 0);
	}

	_setViewMode(view_mode) {
		if (this._view_mode !== view_mode) {
			this._view_mode = view_mode;
			this._$target.toggleClass(this._css_classes.hidden_header,
				this._view_mode == ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER
			);
		}
	}

	getViewMode() {
		return this._view_mode;
	}

	getFields() {
		return this._fields;
	}

	isEditMode() {
		return this._is_edit_mode;
	}

	setEditMode() {
		this._is_edit_mode = true;

		if (this._state === WIDGET_STATE_ACTIVE) {
			this._stopUpdating();
		}

		this._target.classList.add('ui-draggable', 'ui-resizable');
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

	getUniqueId() {
		return this._unique_id;
	}

	supportsDynamicHosts() {
		return (this._fields.dynamic == 1);
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

	getTimePeriod() {
		return this._time_period;
	}

	setTimePeriod(time_period) {
		this._time_period = time_period;
	}

	storeValue(key, value) {
		this._storage[key] = value;
	}

	getPosition() {
		return this._pos;
	}

	setPosition(pos) {
		this._pos = pos;

		this._$target.css({
			left: `${this._cell_width * this._pos.x}%`,
			top: `${this._cell_height * this._pos.y}px`,
			width: `${this._cell_width * this._pos.width}%`,
			height: `${this._cell_height * this._pos.height}px`
		});
	}

	/**
	 * Enable user functional interaction with widget.
	 */
	enableControls() {
		this._$content_header
			.find('button')
			.prop('disabled', false);
	}

	/**
	 * Disable user functional interaction with widget.
	 */
	disableControls() {
		this._$content_header
			.find('button')
			.prop('disabled', true);
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
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
				},
				body: urlEncodeData({widgetid: this._widgetid, rf_rate})
			});
		}
	}

	getDataCopy() {
		return {
			type: this._type,
			name: this._name,
			view_mode: this._view_mode,
			fields: this._fields,
			configuration: this._configuration,
			pos: {
				width: this._pos.width,
				height: this._pos.height
			},
			rf_rate: this._rf_rate,
			dashboard: {
				templateid: this._dashboard.templateid
			}
		};
	}

	getActionsMenu({can_paste_widget}) {
		let menu = [];
		let menu_actions = [];

		if (this._can_edit_dashboards && (this._dashboard.templateid === null || this._dynamic_hostid === null)) {
			menu_actions.push({
				label: t('S_COPY'),
				clickCallback: () => this.fire(WIDGET_EVENT_COPY)
			});
		}

		if (this._is_edit_mode) {
			menu_actions.push({
				label: t('S_PASTE'),
				disabled: (can_paste_widget === false),
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
					selected: (rf_rate == this._rf_rate),
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

	_startUpdating(delay_sec = 0, {do_update_once = null} = {}) {
		if (do_update_once === null) {
			do_update_once = this._is_edit_mode;
		}

		this._stopUpdating({do_abort: false});

		if (delay_sec > 0) {
			this._update_timeout_id = setTimeout(() => {
				this._update_timeout_id = null;
				this._startUpdating(0, {do_update_once: do_update_once});
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
		if (this._update_abort_controller !== null || this._is_updating_paused || this.isInteracting()) {
			this._startUpdating(1, {do_update_once: do_update_once});

			return;
		}

		this.fire(WIDGET_EVENT_BEFORE_UPDATE);

		// Save the content size upon updating.
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
			.catch(() => {
				if (this._update_abort_controller.signal.aborted) {
					this._hidePreloader();
				}
				else {
					this._startUpdating(this._update_retry_sec, {do_update_once: do_update_once});
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
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
			},
			body: urlEncodeData(this._getUpdateRequestData()),
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
			dynamic_hostid: this.supportsDynamicHosts() ? (this._dynamic_hostid ?? undefined) : undefined,
			storage: this._storage,
			...this._content_size
		};
	}

	_processUpdateResponse(response) {
		this._setContents({
			name: response.header,
			body: response.body,
			messages: response.messages,
			info: response.info,
			debug: response.debug
		});
	}

	_getContentSize() {
		return {
			content_width: Math.floor(this._$content_body.width()),
			content_height: Math.floor(this._$content_body.height())
		};
	}

	_setContents({name, body, messages, info, debug}) {
		this._setName(name);

		this._$content_body.empty();

		if (messages !== undefined) {
			this._$content_body.append(messages);
		}

		this._$content_body.append(body);

		if (debug !== undefined) {
			this._$content_body.append(debug);
		}

		this._removeInfoButtons();

		if (info !== undefined) {
			this._addInfoButtons(info);
		}
	}

	_addInfoButtons(buttons) {
		let html_buttons = [];

		for (const button of buttons) {
			html_buttons.push(
				$('<li>', {'class': 'widget-info-button'})
					.append(
						$('<button>', {
							'type': 'button',
							'class': button.icon,
							'data-hintbox': 1,
							'data-hintbox-static': 1
						})
					)
					.append(
						$('<div>', {
							'class': 'hint-box',
							'html': button.hint
						}).hide()
					)
			);
		}

		this._$actions.prepend(html_buttons);
	}

	_removeInfoButtons() {
		this._$actions.find('.widget-info-button').remove();
	}

	_showPreloader() {
		if (this._preloader_timeout !== null) {
			clearTimeout(this._preloader_timeout);
			this._preloader_timeout = null;
		}

		this._$target
			.find(`.${this._css_classes.content}`)
			.addClass('is-loading');
	}

	_hidePreloader() {
		if (this._preloader_timeout !== null) {
			clearTimeout(this._preloader_timeout);
			this._preloader_timeout = null;
		}

		this._$target
			.find(`.${this._css_classes.content}`)
			.removeClass('is-loading');
	}

	_schedulePreloader(delay_sec = this._preloader_timeout_sec) {
		if (this._preloader_timeout !== null) {
			return;
		}

		const is_showing_preloader =
			this._$target
				.find(`.${this._css_classes.content}`)
				.hasClass('is-loading');

		if (is_showing_preloader) {
			return;
		}

		this._preloader_timeout = setTimeout(() => {
			this._preloader_timeout = null;
			this._showPreloader();
		}, delay_sec * 1000);
	}

	_makeView() {
		this._$content_header =
			$('<div>', {'class': this._css_classes.head})
				.append($('<h4>').text((this._name !== '') ? this._name : this._defaults.name));

		if (this._parent === null) {
			this._$actions = $('<ul>', {'class': this._css_classes.actions});

			if (this._is_editable) {
				this._$button_edit = $('<button>', {
					'type': 'button',
					'class': 'btn-widget-edit',
					'title': t('Edit')
				});

				this._$actions.append($('<li>').append(this._$button_edit));
			}

			this._$button_actions = $('<button>', {
				'type': 'button',
				'class': 'btn-widget-action',
				'title': t('Actions'),
				'data-menu-popup': JSON.stringify({
					type: 'widget_actions',
					data: {
						unique_id: this._unique_id,
						dashboard_page_unique_id: this._dashboard_page.unique_id
					}
				}),
				'attr': {
					'aria-expanded': false,
					'aria-haspopup': true
				}
			});

			this._$actions.append($('<li>').append(this._$button_actions));

			this._$content_header.append(this._$actions);
		}

		this._$content_body =
			$('<div>', {'class': this._css_classes.content})
				.toggleClass('no-padding', !this._configuration.padding);

		this._$container =
			$('<div>', {'class': this._css_classes.container})
				.append(this._$content_header)
				.append(this._$content_body);

		// Used for disabling widget interactivity in edit mode while resizing.
		this._$mask = $('<div>', {'class': this._css_classes.mask});

		this._$target
			.append(this._$container, this._$mask)
			.addClass(this._css_classes.root)
			.toggleClass('ui-draggable', this._is_edit_mode)
			.toggleClass('ui-resizable', this._is_edit_mode)
			.toggleClass(this._css_classes.hidden_header, this._view_mode == ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER)
			.toggleClass('new-widget', this._is_new);

		if (this._parent === null) {
			this._$target.css({
				minWidth: `${this._cell_width}%`,
				minHeight: `${this._cell_height}px`
			});
		}
	}

	_registerEvents() {
		let is_mousemove_required = false;

		this._events = {
			edit: () => {
				this.fire(WIDGET_EVENT_EDIT);
			},

			focusin: () => {
				// Skip mouse events caused by animations which were caused by focus change.
				is_mousemove_required = true;

				this.fire(WIDGET_EVENT_ENTER);
			},

			focusout: (e) => {
				// Skip mouse events caused by animations which were caused by focus change.
				is_mousemove_required = true;

				if (!this._$content_header.has(e.relatedTarget).length) {
					this.fire(WIDGET_EVENT_LEAVE);
				}
			},

			enter: (e) => {
				is_mousemove_required = false;

				this.fire(WIDGET_EVENT_ENTER);
			},

			leave: () => {
				if (!is_mousemove_required) {
					this.fire(WIDGET_EVENT_LEAVE);
				}
			},

			loadImage: () => {
				// Call refreshCallback handler for expanded popup menu items.
				const $menu_popup = this._$target.find('[data-expanded="true"][data-menu-popup]');

				if ($menu_popup.length) {
					$menu_popup.menuPopup('refresh', this);
				}
			}
		};

		if (this._parent === null) {
			if (this._is_editable) {
				this._$button_edit.on('click', this._events.edit);
			}
		}

		this._$content_header
			.on('focusin', this._events.focusin)
			.on('focusout', this._events.focusout)
			.on('mouseenter mousemove', this._events.enter);

		this._$content_body
			.on('mouseenter mousemove', this._events.enter);

		this._$target
			.on('mouseleave', this._events.leave)
			.on('load.image', this._events.loadImage);
	}

	_unregisterEvents() {
		if (this._parent === null) {
			if (this._is_editable) {
				this._$button_edit.off('click', this._events.edit);
			}
		}

		this._$content_header
			.off('focusin', this._events.focusin)
			.off('focusout', this._events.focusout)
			.off('mouseenter mousemove', this._events.enter);

		this._$content_body
			.off('mouseenter mousemove', this._events.enter);

		this._$target
			.off('mouseleave', this._events.leave)
			.off('load.image', this._events.loadImage);

		delete this._events;
	}
}
