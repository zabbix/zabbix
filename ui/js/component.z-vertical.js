/*
** Zabbix
** Copyright (C) 2001-2025 Zabbix SIA
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

class ZVertical extends HTMLElement {

	constructor() {
		super();

		this.attachShadow({mode: 'open'});

		const container_styles = window.getComputedStyle(this);

		const div = document.createElement('div');
		div.classList.add('inner-container');

		Object.assign(div.style, {
			display: 'inline-block',
			position: 'absolute',
			bottom: 0,
			left: 0,
			transform: `rotate(270deg)`,
			transformOrigin: `11px 11px`,
		});

		const props_to_inherit = {
			maxHeight: 'maxWidth',
			maxWidth: 'maxHeight',
			width: 'height',
			height: 'width',
			minWidth: 'minHeight',
			minHeight: 'minWidth',
			textOverflow: 'textOverflow',
			overflow: 'overflow',
		}

		for (const prop in props_to_inherit) {
			if (container_styles[prop] !== 'auto' && container_styles[prop] !== 'none'
					&& container_styles[prop] !== '0px') {
				div.style[props_to_inherit[prop]] = container_styles[prop];
			}
		}

		const slot = document.createElement('slot');
		div.append(slot);

		this.shadowRoot.append(div);
	}

	connectedCallback() {
		this.registerEvents();
		this._refresh();
	}

	disconnectedCallback() {
		this.unregisterEvents();
	}

	_refresh() {
		const inner_container = this.shadowRoot.querySelector('.inner-container');

		if (inner_container !== null) {
			this.style.width = `${inner_container.scrollHeight}px`;
			this.style.height = `${inner_container.scrollWidth}px`;
		}
	}

	registerEvents() {
		this._events = {
			resize: () => {
				this._refresh();
			},

			update: () => {
				this._refresh();
			}
		}

		this._events_data = {
			resize_observer: new ResizeObserver(this._events.resize),
			mutation_observer: new MutationObserver(this._events.update)
		}

		this._events_data.resize_observer.observe(this);
		this._events_data.mutation_observer.observe(this, {childList: true});
	}

	unregisterEvents() {
		this._events_data.resize_observer.disconnect();
		this._events_data.mutation_observer.disconnect();
	}
}

customElements.define('z-vertical', ZVertical);
