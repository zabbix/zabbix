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


const SIDEBAR_VIEW_MODE_FULL    = 0;
const SIDEBAR_VIEW_MODE_COMPACT = 1;
const SIDEBAR_VIEW_MODE_HIDDEN  = 2;

const SIDEBAR_MIN_WIDTH         = 200;

/**
 * Supported events:
 *   close
 *   open
 *   viewmodechange
 */
class CSidebar extends CBaseComponent {

	constructor(node) {
		super(node);

		this._is_focused = false;
		this._is_opened = false;

		this.init();
		this.registerEvents();
	}

	init() {
		this._control_show = document.getElementById('sidebar-button-toggle');

		this._view_mode = this._node.classList.contains('is-compact')
			? SIDEBAR_VIEW_MODE_COMPACT
			: (this._node.classList.contains('is-hidden'))
				? SIDEBAR_VIEW_MODE_HIDDEN
				: SIDEBAR_VIEW_MODE_FULL;

		let max_width = SIDEBAR_MIN_WIDTH;

		for (const menu of this._node.querySelectorAll('nav > ul')) {
			const position = window.getComputedStyle(menu).position;
			menu.style.position = 'absolute';
			max_width = Math.max(max_width, menu.clientWidth);
			menu.style.position = position;
		}
		this._node.style.maxWidth = max_width + 'px';
		this._node.classList.add('focus-off');

		this.setViewMode(this._view_mode);
	}

	open() {
		if (!this._is_opened) {
			clearTimeout(this._timer);
			this._is_opened = true;

			if (this._view_mode === SIDEBAR_VIEW_MODE_COMPACT) {
				ZABBIX.MenuMain.expandSelected();
				this._node.classList.add('is-opened');
			}

			if (this._view_mode === SIDEBAR_VIEW_MODE_HIDDEN) {
				this._node.classList.remove('focus-off');
				this._timer = setTimeout(() => {
					this._node.classList.add('is-opened');
				}, 0);

				document.addEventListener('keyup', this._events.escape);
			}

			if ([SIDEBAR_VIEW_MODE_COMPACT, SIDEBAR_VIEW_MODE_HIDDEN].includes(this._view_mode)) {
				this.trigger('open');
			}
		}

		return this;
	}

	close() {
		if (this._is_opened) {
			this._is_opened = false;
			this._node.classList.remove('is-opened');

			if (this._view_mode === SIDEBAR_VIEW_MODE_COMPACT) {
				console.log('here');
				ZABBIX.MenuMain.collapseAll();
			}

			if (this._view_mode === SIDEBAR_VIEW_MODE_HIDDEN) {
				this._timer = setTimeout(() => {
					this._node.classList.add('focus-off');
				}, 500);

				document.removeEventListener('keyup', this._events.escape);
			}

			if ([SIDEBAR_VIEW_MODE_COMPACT, SIDEBAR_VIEW_MODE_HIDDEN].includes(this._view_mode)) {
				this.trigger('close');
			}
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
		this._node.classList.toggle('is-compact', view_mode === SIDEBAR_VIEW_MODE_COMPACT);
		this._node.classList.toggle('is-hidden', view_mode === SIDEBAR_VIEW_MODE_HIDDEN);

		if (this._view_mode !== view_mode) {
			console.info('setViewMode: Event triggered');
			this._view_mode = view_mode;
			this.trigger('viewmodechange', {view_mode: this._view_mode});
		}

		return this;
	}

	/**
	 * Register all DOM events.
	 */
	registerEvents() {
		this._events = {

			mouseenter: () => {
				if ([SIDEBAR_VIEW_MODE_COMPACT, SIDEBAR_VIEW_MODE_HIDDEN].includes(this._view_mode)) {
					this.open();
				}
			},

			mouseleave: () => {
				if (!this._is_focused) {
					this._timer = setTimeout(() => {
						this.close();
					}, 500);
				}
			},

			focus: () => {
				if ([SIDEBAR_VIEW_MODE_COMPACT, SIDEBAR_VIEW_MODE_HIDDEN].includes(this._view_mode)) {
					this._is_focused = this._node.contains(document.activeElement);

					if (this._is_focused) {
						this.open();
					}
					else {
						this.close();
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

					ZABBIX.MenuMain.focusSelected();
				}
				else {
					this.close();
				}

				e.preventDefault();
			},

			viewmodechange: (e) => {
				if (e.target.classList.contains('button-compact')) {
					this.setViewMode(SIDEBAR_VIEW_MODE_COMPACT);
				} else if (e.target.classList.contains('button-hide')) {
					this.setViewMode(SIDEBAR_VIEW_MODE_HIDDEN);
				} else {
					this.setViewMode(SIDEBAR_VIEW_MODE_FULL);
				}

				e.preventDefault();
			}
		};

		document.addEventListener('focusin', this._events.focus);
		document.addEventListener('focusout', this._events.focus);

		this.on('mouseenter', this._events.mouseenter);
		this.on('mouseleave', this._events.mouseleave);

		for (const el of this._node.querySelectorAll('.js-sidebar-mode')) {
			el.addEventListener('click', this._events.viewmodechange);
		}

		this._control_show.addEventListener('click', this._events.toggle);
	}

	/**
	 * Unregister all DOM events.
	 */
	destroy() {
		document.removeEventListener('focusin', this._events.focus);
		document.removeEventListener('focusout', this._events.focus);

		this.off('mouseenter', this._events.mouseenter);
		this.off('mouseleave', this._events.mouseleave);

		for (const el of this._node.querySelectorAll('.js-sidebar-mode')) {
			el.removeEventListener('click', this._events.viewmodechange);
		}

		this._control_show.removeEventListener('click', this._events.toggle);
	}
}
