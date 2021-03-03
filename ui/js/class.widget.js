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

const WIDGET_EVENT_EDIT_CLICK = 'widget-edit-click';
const WIDGET_EVENT_ENTER      = 'widget-enter';
const WIDGET_EVENT_LEAVE      = 'widget-leave';

const WIDGET_EVENT_ACTIVATE   = 'widget-activate';
const WIDGET_EVENT_DEACTIVATE = 'widget-deactivate';
const WIDGET_EVENT_REFRESH    = 'widget-refresh';
const WIDGET_EVENT_DELETE     = 'widget-delete';
const WIDGET_EVENT_RESIZE     = 'widget-resize';

class CWidget extends CBaseComponent {

	constructor({
		type,
		header,
		view_mode,
		pos,
		fields,
		configuration,
		defaults,
		uniqueid,
		index,
		cell_width,
		cell_height,
		is_editable,
		dynamic_hostid,

		widgetid = '',
		rf_rate = 0,

		storage = {},
		preloader_timeout = 10000,
		parent = null,
		is_new = !widgetid.length,
		css_classes = {
			actions: 'dashbrd-grid-widget-actions',
			container: 'dashbrd-grid-widget-container',
			content: 'dashbrd-grid-widget-content',
			focus: 'dashbrd-grid-widget-focus',
			head: 'dashbrd-grid-widget-head',
			hidden_header: 'dashbrd-grid-widget-hidden-header',
			mask: 'dashbrd-grid-widget-mask',
			root: 'dashbrd-grid-widget'
		}
	} = {}) {
		super(document.createElement('div'));

		this.type = type;
		this.header = header;
		this.view_mode = view_mode;
		this.pos = pos;

		// Patch JSON-decoded empty array to an empty object.
		this.fields = (typeof fields === 'object') ? fields : {};

		// Patch JSON-decoded empty array to an empty object.
		this.configuration = (typeof configuration === 'object') ? configuration : {};

		this.defaults = defaults;
		this.uniqueid = uniqueid;
		this.index = index;

		// Private properties.

		this._cell_width = cell_width;
		this._cell_height = cell_height;
		this._is_editable = is_editable;

		this.dynamic_hostid = (!this.fields.dynamic || this.fields.dynamic != 1) ? null : dynamic_hostid;

		this.widgetid = widgetid;

		// Replace empty arrays (or anything non-object) with empty objects.

		this.storage = storage;


		// TODO Remove dynamic_hostid from widget class

		this.rf_rate = rf_rate;
		this._preloader_timeout = preloader_timeout;


		this.parent = parent;
		this.update_paused = false;
		this.initial_load = true;

		this._is_active = false;
		this._is_new = is_new;
		this.is_ready = false;

		this._css_classes = css_classes;
	}

	activate() {
		if (!this._is_active) {
			this._is_active = true;

			if (!this._target.hasChildNodes()) {
				this._makeView();
				this.setDivPosition(this.pos);

//				this.showPreloader();
			}

			this._registerEvents();
//			this.fire(WIDGET_EVENT_ACTIVATE);
		}

		return this;
	}

	deactivate() {
		if (this._is_active) {
			this._is_active = false;

			this._unregisterEvents();
			this.clearUpdateContentTimer();
//			this.fire(WIDGET_EVENT_DEACTIVATE);
		}

		return this;
	}

	/**
	 * Focus specified top-level widget.
	 */
	enter() {
		this.div.addClass(this._css_classes.focus);
	}

	/**
	 * Blur specified top-level widget.
	 */
	leave() {
		if (this.content_header.has(document.activeElement).length != 0) {
			document.activeElement.blur();
		}

		this.div.removeClass(this._css_classes.focus);
	}

	update({body, messages, info, debug}) {
		this.content_body.empty();

		if (messages !== undefined) {
			this.content_body.append(messages);
		}
		this.content_body.append(body);

		if (debug !== undefined) {
			$(debug).appendTo(this.content_body);
		}

		this.removeInfoButtons();

		if (info !== undefined) {
			this.addInfoButtons(info);
		}
	}

	isActive() {
		return this._is_active;
	}

	isEditable() {
		return this._is_editable;
	}

	getView() {
		return this.div;
	}

	/**
	 * Update ready state of the widget.
	 *
	 * @returns {boolean}  True, if status was updated.
	 */
	updateReady() {
		let is_ready_updated = false;

		if (this.parent !== null) {
			this.is_ready = true;

			const children_not_ready = this.parent.children.filter((w) => {
				return !w.is_ready;
			});

			if (children_not_ready.length == 0) {
				// Set parent iterator to ready state.

				is_ready_updated = !this.parent.is_ready;
				this.parent.is_ready = true;
			}
		}
		else {
			is_ready_updated = !this.is_ready;
			this.is_ready = true;
		}

		return is_ready_updated;
	}

	getCssClass(key) {
		return this._css_classes[key] || '';
	}

	getIndex() {
		return this._index;
	}

	setIndex(index) {
		this._index = index;

		return this;
	}

	setViewMode(view_mode) {
		if (this.view_mode !== view_mode) {
			this.view_mode = view_mode;
			this.div.toggleClass(this._css_classes.hidden_header, view_mode == ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER);
		}

		return this;
	}

	showPreloader() {
		this.div.find(`.${this._css_classes.content}`).addClass('is-loading');

		return this;
	}

	hidePreloader() {
		this.div.find(`.${this._css_classes.content}`).removeClass('is-loading');

		return this;
	}

	startPreloader(timeout = this._preloader_timeout) {
		if (typeof this._preloader_timeoutid !== 'undefined' || this.div.find('.is-loading').length) {
			return;
		}

		this._preloader_timeoutid = setTimeout(() => {
			delete this._preloader_timeoutid;

			this.showPreloader();
		}, timeout);
	}

	stopPreloader() {
		if (this._preloader_timeoutid !== undefined) {
			clearTimeout(this._preloader_timeoutid);
			delete this._preloader_timeoutid;
		}

		this.hidePreloader();
	}

	getContentSize() {
		return {
			'content_width': Math.floor(this.content_body.width()),
			'content_height': Math.floor(this.content_body.height())
		};
	}

	clearUpdateContentTimer() {
		if (this.rf_timeoutid !== undefined) {
			clearTimeout(this.rf_timeoutid);
			delete this.rf_timeoutid;
		}
	}

	/**
	 * Enable user functional interaction with widget.
	 */
	enableControls() {
		this.content_header.find('button').prop('disabled', false);
	}

	/**
	 * Disable user functional interaction with widget.
	 */
	disableControls() {
		this.content_header.find('button').prop('disabled', true);
	}

	addInfoButtons(buttons) {
		// Note: this function is used only for widgets and not iterators.

		const $widget_actions = $(`.${this._css_classes.actions}`, this.content_header);

		for (const button of buttons) {
			$widget_actions.prepend(
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
	}

	removeInfoButtons() {
		// Note: this function is used only for widgets and not iterators.

		$('.dashbrd-grid-widget-actions', this.content_header).find('.widget-info-button').remove();
	}

	_makeView() {
		this.content_header = $('<div>', {'class': this._css_classes.head})
			.append($('<h4>').text((this.header !== '') ? this.header : this.defaults.header));

		if (this.parent === null) {
			// Do not add action buttons for child widgets of iterators.

			const widget_actions_data = {
				'widgetType': this.type,
				'currentRate': this.rf_rate,
				'widget_uniqueid': this.uniqueid,
				'multiplier': '0'
			};

			// TODO Remove graphid, itemid, dynamic_hostid from widget class
			// Fields must be accessed through a request to a widget method.

			if ('graphid' in this.fields) {
				widget_actions_data.graphid = this.fields['graphid'];
			}

			if ('itemid' in this.fields) {
				widget_actions_data.itemid = this.fields['itemid'];
			}

			if (this.dynamic_hostid !== null) {
				widget_actions_data.dynamic_hostid = this.dynamic_hostid;
			}

			const actions = $('<ul>', {'class': this._css_classes.actions});

			if (this._is_editable) {
				actions.append(
					$('<li>').append(
						$('<button>', {
							'type': 'button',
							'class': 'btn-widget-edit',
							'title': t('Edit')
						})
					)
				);
			}

			actions.append(
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

			this.content_header.append(actions);
		}

		this.content_body = $('<div>', {'class': this._css_classes.content})
			.toggleClass('no-padding', !this.configuration['padding']);

		this.container = $('<div>', {'class': this._css_classes.container})
			.append(this.content_header)
			.append(this.content_body);

		this.div = $(this._target)
			.addClass(this._css_classes.root)
			.toggleClass(this._css_classes.hidden_header, this.view_mode == ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER)
			.toggleClass('new-widget', this._is_new);

		if (this.parent === null) {
			this.div.css({
				'min-height': `${this._cell_height}px`,
				'min-width': `${this._cell_width}%`
			});
		}

		// Used for disabling widget interactivity in edit mode while resizing.
		this.mask = $('<div>', {'class': this._css_classes.mask});

		this.div.append(this.container, this.mask);
	}

	setDivPosition(pos) {
		console.log(this, pos);

		this.div.css({
			left: `${this._cell_width * pos.x}%`,
			top: `${this._cell_height * pos.y}px`,
			width: `${this._cell_width * pos.width}%`,
			height: `${this._cell_height * pos.height}px`
		});
	}

	fire(event_type) {
		console.log('WIDGET', event_type);

		return !super.fire(event_type, {}, {cancelable: true});
	}

	_registerEvents() {
		this._events = {
			edit: () => {
				this.fire(WIDGET_EVENT_EDIT_CLICK);
			},

			focusin: () => {
				// Skip mouse events caused by animations which were caused by focus change.
				this._mousemove_waiting = true;

				this.fire(WIDGET_EVENT_ENTER);
			},

			focusout: (e) => {
				// Skip mouse events caused by animations which were caused by focus change.
				this._mousemove_waiting = true;

				if (!this.content_header.has(e.relatedTarget).length) {
					this.fire(WIDGET_EVENT_LEAVE);
				}
			},

			enter: () => {
				delete this._mousemove_waiting;

				this.fire(WIDGET_EVENT_ENTER);
			},

			leave: () => {
				if (!this._mousemove_waiting) {
					this.fire(WIDGET_EVENT_LEAVE);
				}
			},

			loadImage: () => {
				// Call refreshCallback handler for expanded popup menu items.
				const $menu_popup = this.div.find('[data-expanded="true"][data-menu-popup]');

				if ($menu_popup.length) {
					$menu_popup.menuPopup('refresh', this);
				}
			}
		};

		if (this.parent === null) {
			if (this._is_editable) {
//				this.$button_edit.on('click', this._events.edit);
			}
		}

		this.content_header
			.on('focusin', this._events.focusin)
			.on('focusout', this._events.focusout);

		this.div
			.on('mouseenter mousemove', this._events.enter)
			.on('mouseleave', this._events.leave)
			.on('load.image', this._events.loadImage);
	}

	_unregisterEvents() {
		if (this.parent === null) {
			if (this._is_editable) {
//				this.$button_edit.off('click', this._events.edit);
			}
		}

		this.content_header
			.off('focusin', this._events.focusin)
			.off('focusout', this._events.focusout);

		this.div
			.off('mouseenter mousemove', this._events.enter)
			.off('mouseleave', this._events.leave)
			.off('load.image', this._events.loadImage);
	}
}
