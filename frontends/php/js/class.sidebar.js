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

class CSidebar {

	constructor(node) {
		this._node = node;

		this._is_focused = false;

		this._view_mode = (this._node.classList.contains('is-compact'))
			? SIDEBAR_VIEW_MODE_COMPACT
			: (this._node.classList.contains('is-hidden'))
				? SIDEBAR_VIEW_MODE_HIDDEN
				: SIDEBAR_VIEW_MODE_FULL;

		this._node.style.maxWidth = this._node.scrollWidth + 'px';

		this.setViewMode(this._view_mode);
		this.handleEvents();
	}

	/**
	 * Set view mode {0: full, 1: compact, 2: hidden}
	 *
	 * @param {number} view_mode
	 *
	 * @returns {CSidebar}
	 */
	setViewMode(view_mode) {
		this.view_mode = view_mode;

		this._node.classList.toggle('is-compact', this.view_mode === SIDEBAR_VIEW_MODE_COMPACT);
		this._node.classList.toggle('is-hidden', this.view_mode === SIDEBAR_VIEW_MODE_HIDDEN);

		return this;
	}

	open() {
		this._node.classList.add('is-opened');

		return this;
	}

	close() {
		this._node.classList.remove('is-opened');

		return this;
	}

	handleEvents() {
		this._events = {

			open: () => {
				this.open();
			},

			close: () => {
				if (!this._is_focused) {
					this.close();
				}
			}
		};

		this._node.addEventListener('mouseenter', this._events.open);
		this._node.addEventListener('mouseleave', this._events.close);
	}

	destroy() {
		this._node.removeEventListener('mouseenter', this._events.open);
		this._node.removeEventListener('mouseleave', this._events.close);
	}
}
