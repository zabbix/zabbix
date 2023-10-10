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


class CFormFieldsetCollapsible {

	static ZBX_STYLE_TOGGLE = 'toggle';

	constructor(target) {
		this._target = target;
		this._toggle = this._target.querySelector(`.${CFormFieldsetCollapsible.ZBX_STYLE_TOGGLE}`);
		this._observed_fields = this._target.querySelectorAll(':scope > .form-field, :scope > .fields-group');

		this._init();
	}

	_init() {
		this._toggle.addEventListener('click', () => {
			const is_collapsed = this._target.classList.contains(ZBX_STYLE_COLLAPSED);

			this._target.classList.toggle(ZBX_STYLE_COLLAPSED, !is_collapsed);

			this._toggle.classList.toggle(ZBX_ICON_CHEVRON_DOWN, !is_collapsed);
			this._toggle.classList.toggle(ZBX_ICON_CHEVRON_UP, is_collapsed);
			this._toggle.setAttribute('title', is_collapsed ? t('S_COLLAPSE') : t('S_EXPAND'));
		});

		for (const element of this._observed_fields) {
			new ResizeObserver(() => this._update()).observe(element);
		}
	}

	_update() {
		let height = 0;

		for (const element of [...this._observed_fields].reverse()) {
			const rect = element.getBoundingClientRect();

			if (rect.height > 0) {
				height = rect.bottom - this._toggle.getBoundingClientRect().bottom + 4;
				break;
			}
		}

		this._target.style.setProperty('--fieldset-height', height + 'px');
	}
}
