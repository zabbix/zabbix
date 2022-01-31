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


const ZBX_STYLE_ICON_WZRD_ACTION = 'icon-wzrd-action';
const ZBX_STYLE_EXPANDED = 'expanded';
const ZBX_STYLE_HIDDEN = 'hidden';

class CExpandableSubfilter extends CBaseComponent {

	constructor(target) {
		super(target);

		this.init();
	}

	init() {
		if (this._target.classList.contains(ZBX_STYLE_EXPANDED) || !this.isOverflowing()) {
			return;
		}

		this.hideOverflown();
		this._target.append(this.makeExpandButton());

		window.addEventListener('resize', () => {
			this._target.querySelectorAll('div > .' + ZBX_STYLE_HIDDEN).forEach(el => {
				el.classList.remove(ZBX_STYLE_HIDDEN);
			});
			this.hideOverflown();
		});
	}

	hideOverflown() {
		const isOverflown = el => {
			return (el.offsetTop + el.offsetHeight > this._target.offsetHeight
				|| 40 > this._target.offsetWidth - el.offsetLeft - el.offsetWidth
			);
		};

		let last = this._target.querySelector('div').lastChild;
		while (isOverflown(last)) {
			last.classList.add(ZBX_STYLE_HIDDEN);
			last = last.previousSibling;
		}
	}

	makeExpandButton() {
		this.btn_expand = document.createElement('button');
		this.btn_expand.classList.add(ZBX_STYLE_ICON_WZRD_ACTION);
		this.btn_expand.addEventListener('click', e => {
			view.subfilters_expanded.push(this._target.dataset.name);
			this._target.classList.add(ZBX_STYLE_EXPANDED);
			this.btn_expand.remove();
		});

		return this.btn_expand;
	}

	isOverflowing() {
		return (this._target.clientHeight < this._target.scrollHeight);
	}
}
