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
		rf_rate = 0,
		parent = null,
		index = null,
		widgetid = '',
		is_new = (widgetid.length == 0),
		dynamic_hostid = null,
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
		this._rf_rate = rf_rate;
		this._parent = parent;
		this._index = index;
		this._widgetid = widgetid;
		this._is_new = is_new;
		this._dynamic_hostid = dynamic_hostid;
		this._pos = pos;

		this._create();
	}

	_create() {
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

		this._preloader_timeout = null;
		this._preloader_timeout_ms = 10000;
	}

	start() {
		this._makeView();

		if (this._pos !== null) {
			this.setPosition(this._pos);
		}

		return this;
	}

	resume() {
		this._registerEvents();

		return this;
	}

	pause() {
		this._unregisterEvents();

		return this;
	}

	delete() {
		return this;
	}

	refresh() {
		return this;
	}

	resize() {
		return this;
	}

	/**
	 * Focus specified top-level widget.
	 */
	enter() {
		this._$view.addClass(this._css_classes.focus);

		return this;
	}

	/**
	 * Blur specified top-level widget.
	 */
	leave() {
		if (this._$content_header.has(document.activeElement).length != 0) {
			document.activeElement.blur();
		}

		this._$view.removeClass(this._css_classes.focus);

		return this;
	}

	setViewMode(view_mode) {
		this._view_mode = view_mode;
		this._$view.toggleClass(this._css_classes.hidden_header, this._view_mode == ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER);

		return this;
	}

	/**
	 * Enable user functional interaction with widget.
	 */
	enableControls() {
		this._$content_header
			.find('button')
			.prop('disabled', false);

		return this;
	}

	/**
	 * Disable user functional interaction with widget.
	 */
	disableControls() {
		this._$content_header
			.find('button')
			.prop('disabled', true);

		return this;
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

		return this;
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

		return this;
	}

	removeInfoButtons() {
		this._$actions.find('.widget-info-button').remove();

		return this;
	}

	setPosition(pos) {
		this._$view.css({
			left: `${this._cell_width * pos.x}%`,
			top: `${this._cell_height * pos.y}px`,
			width: `${this._cell_width * pos.width}%`,
			height: `${this._cell_height * pos.height}px`
		});

		return this;
	}

	showPreloader() {
		if (this._preloader_timeout !== null) {
			clearTimeout(this._preloader_timeout);
			this._preloader_timeout = null;
		}

		this._$view
			.find(`.${this._css_classes.content}`)
			.addClass('is-loading');

		return this;
	}

	hidePreloader() {
		if (this._preloader_timeout !== null) {
			clearTimeout(this._preloader_timeout);
			this._preloader_timeout = null;
		}

		this._$view
			.find(`.${this._css_classes.content}`)
			.removeClass('is-loading');

		return this;
	}

	schedulePreloader(timeout = this._preloader_timeout_ms) {
		if (this._preloader_timeout !== null) {
			return;
		}

		const is_showing_preloader =
			this._$view
				.find(`.${this._css_classes.content}`)
				.hasClass('is-loading');

		if (is_showing_preloader) {
			return;
		}

		this._preloader_timeout = setTimeout(() => {
			this._preloader_timeout = null;
			this.showPreloader();
		}, timeout);

		return this;
	}

	getIndex() {
		return this._index;
	}

	setIndex(index) {
		this._index = index;

		return this;
	}

	getView() {
		return this._$view;
	}

	_makeView() {
		this._$view = $(this._target);

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

		this._$view
			.append(this._$container, this._$mask)
			.addClass(this._css_classes.root)
			.toggleClass(this._css_classes.hidden_header, this._view_mode == ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER)
			.toggleClass('new-widget', this._is_new);

		if (this._parent === null) {
			this._$view.css({
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
				const $menu_popup = this._$view.find('[data-expanded="true"][data-menu-popup]');

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

		this._$view
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

		this._$view
			.off('mouseenter mousemove', this._events.enter)
			.off('mouseleave', this._events.leave)
			.off('load.image', this._events.loadImage);

		delete this._events;
	}
}
