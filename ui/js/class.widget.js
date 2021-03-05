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

const WIDGET_EVENT_EDIT_CLICK = 'edit-click';
const WIDGET_EVENT_ENTER = 'enter';
const WIDGET_EVENT_LEAVE = 'leave';
const WIDGET_EVENT_BEFORE_UPDATE = 'before-update';
const WIDGET_EVENT_AFTER_UPDATE = 'after-update';

const WIDGET_STATE_INITIAL = 'initial';
const WIDGET_STATE_ACTIVE = 'active';
const WIDGET_STATE_INACTIVE = 'inactive';
const WIDGET_STATE_DESTROYED = 'destroyed';

class CWidget extends CBaseComponent {

	constructor({
		type,
		header,
		view_mode,
		fields,
		configuration,
		defaults,
		uniqueid,
		cell_width,
		cell_height,
		is_editable,
		is_edit_mode,
		dashboard_data,
		rf_rate = 0,
		parent = null,
		index = null,
		widgetid = null,
		is_new = (widgetid === null),
		pos = null
	}) {
		super(document.createElement('div'));

		this._type = type;
		this._header = header;
		this._view_mode = view_mode;

		// Patch JSON-decoded empty array to an empty object.
		this._fields = (typeof fields === 'object') ? fields : {};

		// Patch JSON-decoded empty array to an empty object.
		this._configuration = (typeof configuration === 'object') ? configuration : {};

		this._defaults = defaults;
		this._uniqueid = uniqueid;
		this._cell_width = cell_width;
		this._cell_height = cell_height;
		this._is_editable = is_editable;
		this._dashboard_data = dashboard_data;
		this._rf_rate = rf_rate;
		this._parent = parent;
		this._index = index;
		this._widgetid = widgetid;
		this._is_new = is_new;
		this._pos = pos;

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

		this._update_timeout = null;
		this._update_interval = null;
		this._update_abort_controller = null;
		this._is_updating_paused = false;
		this._update_retry_sec = 3;

		this._is_ready = false;

		this._preloader_timeout = null;
		this._preloader_timeout_sec = 10;

		this._storage = {};
	}

	start() {
		if (this._state !== WIDGET_STATE_INITIAL) {
			throw new Error('Incorrect state change.');
		}
		this._state = WIDGET_STATE_INACTIVE;

		this._makeView();

		if (this._pos !== null) {
			this.setPosition(this._pos);
		}
	}

	activate() {
		if (this._state !== WIDGET_STATE_INACTIVE) {
			throw new Error('Incorrect state change.');
		}
		this._state = WIDGET_STATE_ACTIVE;

		this._registerEvents();
		this.startUpdating();
	}

	deactivate() {
		if (this._state !== WIDGET_STATE_ACTIVE) {
			throw new Error('Incorrect state change.');
		}
		this._state = WIDGET_STATE_INACTIVE;

		this._unregisterEvents();
		this.stopUpdating();
	}

	destroy() {
		if (this._state === WIDGET_STATE_ACTIVE) {
			this.deactivate();
		}
		if (this._state !== WIDGET_STATE_INACTIVE) {
			throw new Error('Incorrect state change.');
		}
		this._state = WIDGET_STATE_DESTROYED;
	}

	getState() {
		return this._state;
	}

	startUpdating(delay_sec = 0) {
		this.stopUpdating(false);

		if (delay_sec > 0) {
			this._update_timeout = setTimeout(() => {
				this._update_timeout = null;
				this._startUpdating();
			}, delay_sec * 1000);
		}
		else {
			if (this._rf_rate > 0) {
				this._update_interval = setInterval(() => {
					this._update_interval = null;
					this._update();
				}, this._rf_rate * 1000);
			}

			this._update();
		}
	}

	stopUpdating(do_abort = true) {
		if (this._update_timeout !== null) {
			clearTimeout(this._update_timeout);
			this._update_timeout = null;
		}

		if (this._update_interval !== null) {
			clearInterval(this._update_interval);
			this._update_interval = null;
		}

		if (do_abort && this._update_abort_controller !== null) {
			this._update_abort_controller.abort();
		}
	}

	_update() {
		if (this._update_abort_controller !== null || this._is_updating_paused
				|| this._$content_body.find('[data-expanded="true"]').length > 0) {
			this._startUpdating(1);

			return;
		}

		this.fire(WIDGET_EVENT_BEFORE_UPDATE);

		// Save the content size upon updating.
		this._content_size = this._getContentSize();

		this._update_abort_controller = new AbortController();
		this._schedulePreloader();

		this
			._promiseUpdate()
			.then(() => this._hidePreloader())
			.catch(() => {
				if (this._update_abort_controller.signal.aborted) {
					this._hidePreloader();
				}
				else {
					this._startUpdating(this._update_retry_sec);
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
				'Content-Type': 'application/json'
			},
			body: JSON.stringify(this._getUpdateRequestData()),
			signal: this._update_abort_controller.signal
		})
			.then((response) => response.json())
			.then((response) => this._processUpdateResponse(response));
	}

	_getUpdateRequestData() {
		return {
			templateid: this._dashboard_data.templateid !== null ? this._dashboard_data.templateid : undefined,
			dashboardid: this._dashboard_data.dashboardid !== null ? this._dashboard_data.dashboardid : undefined,
			dynamic_hostid: this._dashboard_data.dynamic_hostid !== null
				? this._dashboard_data.dynamic_hostid
				: undefined,
			widgetid: (this._widgetid !== null) ? this._widgetid : undefined,
			uniqueid: this._uniqueid,
			name: (this._header !== '') ? this._header : undefined,
			fields: (Object.keys(this._fields).length != 0) ? JSON.stringify(this._fields) : undefined,
			view_mode: this._view_mode,
			edit_mode: this._is_edit_mode ? 1 : 0,
			storage: this._storage,
			...this._content_size
		};
	}

	_processUpdateResponse(response) {



	}

	_getContentSize() {
		return {
			content_width: Math.floor(this._$content_body.width()),
			content_height: Math.floor(this._$content_body.height())
		};
	}

	ready() {
		this._is_ready = true;
	}

	isReady() {
		return this._is_ready;
	}

	resize() {
	}

	setEditMode() {
		this._is_edit_mode = true;
	}

	storeValue(key, value) {
		this._storage[key] = value;
	}

	/**
	 * Focus specified top-level widget.
	 */
	enter() {
		this._$target.addClass(this._css_classes.focus);
	}

	/**
	 * Blur specified top-level widget.
	 */
	leave() {
		if (this._$content_header.has(document.activeElement).length != 0) {
			document.activeElement.blur();
		}

		this._$target.removeClass(this._css_classes.focus);
	}

	setViewMode(view_mode) {
		this._view_mode = view_mode;
		this._$target.toggleClass(this._css_classes.hidden_header, this._view_mode == ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER);
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

	setContents({body, messages, info, debug}) {
		this._$content_body.empty();

		if (messages !== undefined) {
			this._$content_body.append(messages);
		}

		this._$content_body.append(body);

		if (debug !== undefined) {
			this._$content_body.append(debug);
		}

		this.removeInfoButtons();

		if (info !== undefined) {
			this.addInfoButtons(info);
		}
	}

	addInfoButtons(buttons) {
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

	removeInfoButtons() {
		this._$actions.find('.widget-info-button').remove();
	}

	setPosition(pos) {
		this._$target.css({
			left: `${this._cell_width * pos.x}%`,
			top: `${this._cell_height * pos.y}px`,
			width: `${this._cell_width * pos.width}%`,
			height: `${this._cell_height * pos.height}px`
		});
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

	_schedulePreloader(delay_sec = this._preloader_timeout_sec * 1000) {
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
		}, delay_sec);
	}

	getIndex() {
		return this._index;
	}

	setIndex(index) {
		this._index = index;
	}

	getView() {
		return this._$target;
	}

	_makeView() {
		this._$target = $(this._target);

		this._$content_header =
			$('<div>', {'class': this._css_classes.head})
				.append($('<h4>').text((this._header !== '') ? this._header : this._defaults.header));

		if (this._parent === null) {
			// Do not add action buttons for non-top widgets.

			const widget_actions_data = {
				widgetType: this._type,
				currentRate: this._rf_rate,
				widget_uniqueid: this._uniqueid,
				multiplier: '0'
			};

			if (this._fields.graphid !== undefined) {
				widget_actions_data.graphid = this._fields.graphid;
			}

			if (this._fields.itemid !== undefined) {
				widget_actions_data.itemid = this._fields.itemid;
			}

			if (this._dynamic_hostid !== null) {
				widget_actions_data.dynamic_hostid = this._dynamic_hostid;
			}

			this._$actions = $('<ul>', {'class': this._css_classes.actions});

			if (this._is_editable) {
				this._$button_edit = $('<button>', {
					'type': 'button',
					'class': 'btn-widget-edit',
					'title': t('Edit')
				});

				this._$actions.append($('<li>').append(this._$button_edit));
			}

			this._$actions.append(
				$('<li>').append(
					$('<button>', {
						'type': 'button',
						'class': 'btn-widget-action',
						'title': t('Actions'),
						'data-menu-popup': JSON.stringify({
							'type': 'widget_actions',
							'data': widget_actions_data
						}),
						'attr': {
							'aria-expanded': false,
							'aria-haspopup': true
						}
					})
				)
			);

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
		let mousemove_waiting = false;

		this._events = {
			editClick: () => {
				this.fire(WIDGET_EVENT_EDIT_CLICK);
			},

			focusin: () => {
				// Skip mouse events caused by animations which were caused by focus change.
				mousemove_waiting = true;

				this.fire(WIDGET_EVENT_ENTER);
			},

			focusout: (e) => {
				// Skip mouse events caused by animations which were caused by focus change.
				mousemove_waiting = true;

				if (!this._$content_header.has(e.relatedTarget).length) {
					this.fire(WIDGET_EVENT_LEAVE);
				}
			},

			enter: () => {
				mousemove_waiting = false;

				this.fire(WIDGET_EVENT_ENTER);
			},

			leave: () => {
				if (!mousemove_waiting) {
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
				this._$button_edit.on('click', this._events.editClick);
			}
		}

		this._$content_header
			.on('focusin', this._events.focusin)
			.on('focusout', this._events.focusout);

		this._$target
			.on('mouseenter mousemove', this._events.enter)
			.on('mouseleave', this._events.leave)
			.on('load.image', this._events.loadImage);
	}

	_unregisterEvents() {
		if (this._parent === null) {
			if (this._is_editable) {
				this._$button_edit.off('click', this._events.editClick);
			}
		}

		this._$content_header
			.off('focusin', this._events.focusin)
			.off('focusout', this._events.focusout);

		this._$target
			.off('mouseenter mousemove', this._events.enter)
			.off('mouseleave', this._events.leave)
			.off('load.image', this._events.loadImage);

		delete this._events;
	}
}
