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
const TABFILTERITEM_EVENT_AFTER_RENDER = 'afterrender';

class CTabFilterItem extends CBaseComponent {

	constructor(target, options) {
		super(target);

		this._content_container = options.container;
		this._can_toggle = options.can_toggle;
		this._data = options.data||{};
		this._template = options.template;

		this.init();
		this.registerEvents();
	}

	init() {
		this._expanded = this.hasClass('active');

		if (this._expanded) {
			this.renderContentTemplate();
			this.fire(TABFILTERITEM_EVENT_AFTER_RENDER);
		}
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

				if (!this._content_container.children.length) {
					this.renderContentTemplate();
					this.fire(TABFILTERITEM_EVENT_AFTER_RENDER);
				}

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

	renderContentTemplate() {
		this._content_container.innerHTML = (new Template(this._template.innerHTML)).evaluate(this._data);
	}

	select() {
		this._events.click();
	}
}
