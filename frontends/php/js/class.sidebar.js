/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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


const SIDEBAR_VIEW_MODE_FULL       = 0;
const SIDEBAR_VIEW_MODE_COMPACT    = 1;
const SIDEBAR_VIEW_MODE_HIDDEN     = 2;

const SIDEBAR_MIN_WIDTH            = 200;
const SIDEBAR_HOVER_DELAY          = 1000;

const SIDEBAR_EVENT_BLUR           = 'blur';
const SIDEBAR_EVENT_CLOSE          = 'close';
const SIDEBAR_EVENT_FOCUS          = 'focus';
const SIDEBAR_EVENT_OPEN           = 'open';
const SIDEBAR_EVENT_VIEWMODECHANGE = 'viewmodechange';

class CSidebar extends CBaseComponent {

	constructor(node) {
		super(node);

		this.init();
		this.registerEvents();
	}

	init() {
		this._sidebar_toggle = document.getElementById('sidebar-button-toggle');

		this._is_focused = false;
		this._is_opened = false;

		let max_width = 0;
		for (const menu of this._node.querySelectorAll('nav > ul')) {
			const position = window.getComputedStyle(menu).position;
			menu.style.position = 'absolute';
			max_width = Math.max(max_width, menu.clientWidth);
			menu.style.position = position;
		}
		this._node.style.maxWidth = Math.max(max_width, SIDEBAR_MIN_WIDTH) + 'px';
		this._node.querySelector('.sidebar-header').style.maxWidth = max_width + 'px';
		this._node.querySelector('.sidebar-header .server-name').style.width = 'auto';

		this._view_mode = SIDEBAR_VIEW_MODE_FULL;
		if (this.hasClass('is-compact')) {
			this._view_mode = SIDEBAR_VIEW_MODE_COMPACT;
		}
		else if (this.hasClass('is-hidden')) {
			this._view_mode = SIDEBAR_VIEW_MODE_HIDDEN;
			this.addClass('focus-off');
		}
		this.setViewMode(this._view_mode);
	}

	open() {
		if (!this._is_opened) {
			this._is_opened = true;

			if (this._view_mode === SIDEBAR_VIEW_MODE_COMPACT) {
				ZABBIX.MenuMain.expandSelected();
			}

			if (this._view_mode === SIDEBAR_VIEW_MODE_HIDDEN) {
				this.removeClass('focus-off');
				ZABBIX.MenuMain.focusSelected();
			}

			if ([SIDEBAR_VIEW_MODE_COMPACT, SIDEBAR_VIEW_MODE_HIDDEN].includes(this._view_mode)) {
				document.addEventListener('keyup', this._events.escape);
			}

			setTimeout(() => this.addClass('is-opened'), 0);
			this.trigger(SIDEBAR_EVENT_OPEN);
		}

		return this;
	}

	close() {
		if (this._is_opened) {
			this._is_opened = false;

			if (this._view_mode === SIDEBAR_VIEW_MODE_COMPACT) {
				ZABBIX.MenuMain.collapseAll();
			}

			if (this._view_mode === SIDEBAR_VIEW_MODE_HIDDEN) {
				this._opened_timer = setTimeout(() => this.addClass('focus-off'), UI_TRANSITION_DURATION);
			}

			if ([SIDEBAR_VIEW_MODE_COMPACT, SIDEBAR_VIEW_MODE_HIDDEN].includes(this._view_mode)) {
				const active_element = document.activeElement;
				if (active_element.parentElement.classList.contains('has-submenu')) {
					active_element.blur();
				}

				document.removeEventListener('keyup', this._events.escape);
			}

			this.removeClass('is-opened');
			this.trigger(SIDEBAR_EVENT_CLOSE);
		}

		return this;
	}

	/**
	 * Set view mode {0: full, 1: compact, 2: hidden}.
	 *
	 * @param {number} view_mode
	 *
	 * @returns {CSidebar}
	 */
	setViewMode(view_mode) {
		this.toggleClass('is-compact', view_mode === SIDEBAR_VIEW_MODE_COMPACT);
		this.toggleClass('is-hidden', view_mode === SIDEBAR_VIEW_MODE_HIDDEN);

		if (this._view_mode !== view_mode) {
			this._view_mode = view_mode;
			this.trigger(SIDEBAR_EVENT_VIEWMODECHANGE, {view_mode: this._view_mode});
		}

		return this;
	}

	/**
	 * Register all DOM events.
	 */
	registerEvents() {
		this._events = {

			mouseenter: () => {
				clearTimeout(this._opened_timer);
				this.open();
			},

			mouseleave: () => {
				if (!this._is_focused || document.activeElement.parentElement.classList.contains('has-submenu')) {
					this._opened_timer = setTimeout(() => this.close(), SIDEBAR_HOVER_DELAY);
				}
			},

			focus: (e) => {
				if (!this._node.contains(e.relatedTarget)) {
					this._is_focused = (e.type === 'focusin');

					if ([SIDEBAR_VIEW_MODE_COMPACT, SIDEBAR_VIEW_MODE_HIDDEN].includes(this._view_mode)) {
						if (this._is_focused) {
							this.open();
						}
						else {
							this.close();
						}

						this.trigger(this._is_focused ? SIDEBAR_EVENT_FOCUS : SIDEBAR_EVENT_BLUR);
					}
				}
			},

			escape: (e) => {
				if (e.key === 'Escape') {
					this.close();
				}
			},

			toggle: (e) => {
				if (!this._is_opened) {
					this.open();
				}
				else {
					this.close();
				}

				e.preventDefault();
			},

			expandSelected: () => {
				this._expand_timer = setTimeout(() => {
					ZABBIX.MenuMain
						.expandSelected()
						.focusSelected();
				}, MENU_EXPAND_SELECTED_DELAY);
			},

			cancelExpandSelected: () => {
				clearTimeout(this._expand_timer);
			},

			expand: () => {
				this.on('mouseleave', this._events.expandSelected);
				this.on('mouseenter', this._events.cancelExpandSelected);
			},

			viewmodechange: (e) => {
				if (e.target.classList.contains('button-compact')) {
					ZABBIX.MenuMain.collapseAll();
					clearTimeout(this._expand_timer);
					this.setViewMode(SIDEBAR_VIEW_MODE_COMPACT);
				}
				else if (e.target.classList.contains('button-hide')) {
					this.setViewMode(SIDEBAR_VIEW_MODE_HIDDEN);
				}
				else {
					this.setViewMode(SIDEBAR_VIEW_MODE_FULL);
				}

				this._events._update(this._view_mode);

				e.preventDefault();
			},

			/**
			 * Update event listeners based on view mode.
			 *
			 * @param view_mode
			 *
			 * @private
			 */
			_update: (view_mode) => {
				if (view_mode === SIDEBAR_VIEW_MODE_COMPACT) {
					this.on('mouseenter', this._events.mouseenter);
					this.on('mouseleave', this._events.mouseleave);
				}
				else {
					this.off('mouseenter', this._events.mouseenter);
					this.off('mouseleave', this._events.mouseleave);
				}

				if (view_mode === SIDEBAR_VIEW_MODE_HIDDEN) {
					this._sidebar_toggle.addEventListener('click', this._events.toggle);
				}
				else {
					this._sidebar_toggle.removeEventListener('click', this._events.toggle);
				}

				if ([SIDEBAR_VIEW_MODE_FULL, SIDEBAR_VIEW_MODE_HIDDEN].includes(this._view_mode)) {
					ZABBIX.MenuMain.on('expand', this._events.expand);
				}
				else {
					ZABBIX.MenuMain.off('expand', this._events.expand);
					this.off('mouseleave', this._events.expandSelected);
					this.off('mouseenter', this._events.cancelExpandSelected);
				}
			}
		};

		this.on('focusin focusout', this._events.focus);

		for (const el of this._node.querySelectorAll('.js-sidebar-mode')) {
			el.addEventListener('click', this._events.viewmodechange);
		}

		this._events._update(this._view_mode);
	}
}
