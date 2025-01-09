/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


const EXPANDABLE_SUBFILTER_EVENT_EXPAND = 'expand';

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
		this.btn_expand.classList.add(ZBX_ICON_MORE);
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
