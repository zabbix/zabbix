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


class CWidgetInaccessible extends CWidget {

	onStart() {
		this._updateButtons();

		this._body.innerHTML = `<div>${t('No permissions to referred object or it does not exist!')}</div>`;
	}

	_updateButtons() {
		for (const button of this._header.querySelectorAll('button')) {
			button.style.display = !button.classList.contains('js-widget-action') || !this.isEditMode() ? 'none' : '';
		}
	}

	onEdit() {
		const state = this.getState();

		if (state === WIDGET_STATE_ACTIVE || state === WIDGET_STATE_INACTIVE) {
			this._updateButtons();
		}
	}

	promiseUpdate() {
		return Promise.resolve();
	}

	getActionsContextMenu({can_copy_widget, can_paste_widget}) {
		const menu = super.getActionsContextMenu({can_copy_widget: false, can_paste_widget});

		for (const section of menu) {
			switch (section.label) {
				case t('Refresh interval'):
					for (const item of section.items) {
						item.disabled = true;
					}
					break;
			}
		}

		return menu;
	}

	hasPadding() {
		return true;
	}
}
