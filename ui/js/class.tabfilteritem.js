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


const TABFILTERITEM_EVENT_CLICK = 'click';
const TABFILTERITEM_EVENT_COLLAPSE = 'collapse';
const TABFILTERITEM_EVENT_EXPAND   = 'expand';
const TABFILTERITEM_EVENT_EXPAND_BEFORE = 'expandbefore';

class CTabFilterItem extends CBaseComponent {

	/**
	 * Node of tab content.
	 */
	_content_container;

	_expanded;
	_can_toggle = true;

	constructor(title, container) {
		super(title);
		this._content_container = container;

		this.init();
		this.registerEvents();
	}

	init() {
		this._expanded = this.hasClass('active');
	}

	registerEvents() {
		this._events = {
			click: () => {
				if (!this._expanded) {
					this.fire(TABFILTERITEM_EVENT_EXPAND_BEFORE);
					this.fire(TABFILTERITEM_EVENT_EXPAND);
				}
				else if (this._can_toggle) {
					this.fire(TABFILTERITEM_EVENT_COLLAPSE);
				}
			},

			expand: () => {
				this._expanded = true;
				this.addClass('active');
				this._content_container.classList.remove('display-none');
			},

			collapse: () => {
				this._expanded = false;
				this.removeClass('active');
				this._content_container.classList.add('display-none');
			}
		}

		this
			.on(TABFILTERITEM_EVENT_EXPAND, this._events.expand)
			.on(TABFILTERITEM_EVENT_COLLAPSE, this._events.collapse)
			.on(TABFILTERITEM_EVENT_CLICK, this._events.click);
	}
}
