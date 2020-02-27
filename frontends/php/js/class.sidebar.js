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


const SIDEBAR_VIEW_MODE_FULL = 0;
const SIDEBAR_VIEW_MODE_COMPACT = 1;
const SIDEBAR_VIEW_MODE_HIDDEN = 2;

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
		this.handleEvents();
	}

	init() {
		this.setViewMode((this._node.classList.contains('is-compact'))
			? SIDEBAR_VIEW_MODE_COMPACT
			: (this._node.classList.contains('is-hidden'))
				? SIDEBAR_VIEW_MODE_HIDDEN
				: SIDEBAR_VIEW_MODE_FULL
		);

		let max_width = 200;  // Minimum sidebar width for the full view mode

		for (const menu of this._node.querySelectorAll('nav > ul')) {
			const position = window.getComputedStyle(menu).position;
			menu.style.position = 'absolute';
			max_width = Math.max(max_width, menu.clientWidth);
			menu.style.position = position;
		}
		this._node.style.maxWidth = max_width + 'px';
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
			this.trigger('viewmodechange', {view_mode: this._view_mode });
		}

		return this;
	}

	open() {
		if (!this._is_opened) {
			clearTimeout(this._timer);

			this._is_opened = true;
			this._node.classList.add('is-opened');

			if (this._view_mode === SIDEBAR_VIEW_MODE_COMPACT) {
				ZABBIX.MenuMain.expandSelected();
			}

			if (this._view_mode === SIDEBAR_VIEW_MODE_HIDDEN) {
				this._node.classList.add('hidden');
			}

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

				if (this._view_mode === SIDEBAR_VIEW_MODE_HIDDEN) {
					this._timer = setTimeout(() => {
						this._node.classList.remove('hidden');
					}, 500);
				}

				this.trigger('close');
			}
		}

		return this;
	}

	/**
	 * Register all DOM events.
	 */
	handleEvents() {
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
						document.addEventListener('keyup', this._events.escape);
						this.open();
					}
					else {
						this.close();
					}
				}
			},

			escape: (e) => {
				if (e.key === 'Escape') {
					document.removeEventListener('keyup', this._events.escape);
					this.close();
				}
			},

			toggle: (e) => {
				if (!this._is_opened) {
					e.target.blur();
					document.addEventListener('keyup', this._events.escape);
					this.open();
				}
				else {
					document.removeEventListener('keyup', this._events.escape);
					this.close();
				}

				e.preventDefault();
			},

			viewmodechange: (e) => {
				if (e.target.classList.contains('button-sidebar-compact')) {
					this.setViewMode(SIDEBAR_VIEW_MODE_COMPACT);
				} else if (e.target.classList.contains('button-sidebar-hide')) {
					this.setViewMode(SIDEBAR_VIEW_MODE_HIDDEN);
				} else {
					this.setViewMode(SIDEBAR_VIEW_MODE_FULL);
				}

				e.preventDefault();
			}
		};

		document.addEventListener('focusin', this._events.focus);
		document.addEventListener('focusout', this._events.focus);

		this._node.addEventListener('mouseenter', this._events.mouseenter);
		this._node.addEventListener('mouseleave', this._events.mouseleave);

		for (const el of this._node.querySelectorAll('.js-sidebar-mode')) {
			el.addEventListener('click', this._events.viewmodechange);
		}

		document.getElementById('sidebar-toggle').addEventListener('click', this._events._toggle);
	}
}
