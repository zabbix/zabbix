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

const SIDEBAR_HOVER_DELAY       = 1000;

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
		this._view_mode = SIDEBAR_VIEW_MODE_FULL;

		this.init();
		this.registerEvents();
	}

	init() {
		this._sidebar_toggle = document.getElementById('sidebar-button-toggle');

		if (this._node.classList.contains('is-compact')) {
			this._view_mode = SIDEBAR_VIEW_MODE_COMPACT;
		}
		else if (this._node.classList.contains('is-hidden')) {
			this._view_mode = SIDEBAR_VIEW_MODE_HIDDEN;
			this._node.classList.add('focus-off');
		}

		let max_width = SIDEBAR_MIN_WIDTH;
		for (const menu of this._node.querySelectorAll('nav > ul')) {
			const position = window.getComputedStyle(menu).position;
			menu.style.position = 'absolute';
			max_width = Math.max(max_width, menu.clientWidth);
			menu.style.position = position;
		}
		this._node.style.maxWidth = max_width + 'px';

		this.setViewMode(this._view_mode);
	}

	open() {
		if (!this._is_opened) {
			this._is_opened = true;

			if (this._view_mode === SIDEBAR_VIEW_MODE_COMPACT) {
				this._node.classList.add('is-opened');
				ZABBIX.MenuMain.expandSelected();
			}

			// if (this._view_mode === SIDEBAR_VIEW_MODE_HIDDEN) {
			// 	this._node.classList.remove('focus-off');
			// 	this._timer = setTimeout(() => {
			// 		this._node.classList.add('is-opened');
			// 	}, 0);
			// }

		// 		document.addEventListener('keyup', this._events.escape);
		// 	}

			if (this._view_mode === SIDEBAR_VIEW_MODE_COMPACT || this._view_mode === SIDEBAR_VIEW_MODE_HIDDEN) {
				document.addEventListener('keyup', this._events.escape);
			}

			clearTimeout(this._timer);
			this.trigger('open');
		}

		return this;
	}

	close() {
		if (this._is_opened) {
			this._is_opened = false;
			this._node.classList.remove('is-opened');

			if (this._view_mode === SIDEBAR_VIEW_MODE_COMPACT) {
				ZABBIX.MenuMain.collapseAll();
			}

		// 	if (this._view_mode === SIDEBAR_VIEW_MODE_HIDDEN) {
		// 		this._timer = setTimeout(() => {
		// 			this._node.classList.add('focus-off');
		// 		}, SIDEBAR_OPEN_DELAY);

		// 		document.removeEventListener('keyup', this._events.escape);
		// 	}

			if (this._view_mode === SIDEBAR_VIEW_MODE_COMPACT || this._view_mode === SIDEBAR_VIEW_MODE_HIDDEN) {
				document.removeEventListener('keyup', this._events.escape);
			}

			this.trigger('close');
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
				this.open();
			},

			mouseleave: () => {
				if (!this._is_focused) {
					this._timer = setTimeout(() => {
						this.close();
					}, SIDEBAR_HOVER_DELAY);
				}
			},

			focus: (e) => {
				if (!this._node.contains(e.relatedTarget)) {
					this._is_focused = (e.type === 'focusin');

					if (this._view_mode === SIDEBAR_VIEW_MODE_COMPACT) {
						if (this._is_focused) {
							this.open();
							document.addEventListener('keydown', this._events.escape);
						}
						else {
							this.close();
							document.removeEventListener('keydown', this._events.escape);
						}
					}
				}
			},

			escape: (e) => {
				if (e.key === 'Escape') {
					document.querySelector('[autofocus="autofocus"]').focus();
				}
			},

			toggle: (e) => {
				if (!this._is_opened) {
					this.open();

					// ZABBIX.MenuMain.focusSelected();
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

		if (this._view_mode === SIDEBAR_VIEW_MODE_COMPACT) {
			this.on('mouseenter', this._events.mouseenter);
			this.on('mouseleave', this._events.mouseleave);
		}

		this.on('focusin focusout', this._events.focus);

		for (const el of this._node.querySelectorAll('.js-sidebar-mode')) {
			el.addEventListener('click', this._events.viewmodechange);
		}

		this._sidebar_toggle.addEventListener('click', this._events.toggle);
	}
}
