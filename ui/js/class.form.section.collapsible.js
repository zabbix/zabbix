/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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


const ZBX_STYLE_COLLAPSED = 'section-collapsed';
const ZBX_STYLE_TOGGLE = 'section-toggle';

class CFormSectionCollapsible {

	constructor(target) {
		this._target = target;
		this._toggle = this._target.querySelector(`.${ZBX_STYLE_TOGGLE}`);

		this._init();
	}

	_init() {
		this._toggle.addEventListener('click', () => {
			this._target.classList.toggle(ZBX_STYLE_COLLAPSED);
		});

		for (const element of this._target.querySelectorAll('.form-field')) {
			new ResizeObserver(() => this._update()).observe(element);
		}
	}

	_update() {
		const fields = this._target.querySelectorAll('.form-field');
		let height = 0;

		if (fields !== null) {
			for (const element of Array.from(fields).reverse()) {
				const rect = element.getBoundingClientRect();

				if (rect.height > 0) {
					height = rect.bottom - this._toggle.getBoundingClientRect().bottom + 4;
					break;
				}
			}
		}

		this._target.style.setProperty('--form-section-height', height + 'px');
	}
}
