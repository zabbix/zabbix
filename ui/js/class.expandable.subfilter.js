/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


const EXPANDABLE_SUBFILTER_EVENT_EXPAND = 'expand';

const ZBX_STYLE_ICON_WIZARD_ACTION = 'icon-wizard-action';
const ZBX_STYLE_EXPANDED = 'expanded';
const ZBX_STYLE_HIDDEN = 'hidden';

// Empty space in pixels reserved for expand button.
const ZBX_EXPAND_BUTTON_SIZE = 40;

class CExpandableSubfilter extends CBaseComponent {

	constructor(target) {
		super(target);

		this.init();
	}

	init() {
		if (this._target.classList.contains(ZBX_STYLE_EXPANDED) || !this.isOverflowing()) {
			return;
		}

		this._target.append(this.makeExpandButton());

		const resize_observer = new ResizeObserver(() => this.hideOverflown());
		resize_observer.observe(this._target);
	}

	hideOverflown() {
		const isOverflown = (element) => {
			return element.offsetTop + element.offsetHeight > this._target.offsetHeight
				|| ZBX_EXPAND_BUTTON_SIZE > this._target.offsetWidth - element.offsetLeft - element.offsetWidth;
		};

		this._target.querySelectorAll(`.subfilter.${ZBX_STYLE_HIDDEN}`).forEach((element) => {
			element.classList.remove(ZBX_STYLE_HIDDEN);
		});

		let last = this._target.querySelector('div').lastChild;
		while (last !== null && isOverflown(last)) {
			last.classList.add(ZBX_STYLE_HIDDEN);
			last = last.previousSibling;
		}
	}

	makeExpandButton() {
		this.btn_expand = document.createElement('button');
		this.btn_expand.classList.add(ZBX_STYLE_ICON_WIZARD_ACTION);
		this.btn_expand.addEventListener('click', () => {
			this._target.classList.add(ZBX_STYLE_EXPANDED);
			this.btn_expand.remove();

			this.fire(EXPANDABLE_SUBFILTER_EVENT_EXPAND, {name: this._target.dataset.name});
		});

		return this.btn_expand;
	}

	isOverflowing() {
		return this._target.clientHeight < this._target.scrollHeight;
	}
}
