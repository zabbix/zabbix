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

	_doStart() {
		super._doStart();

		this._updateButtons();

		this._content_body.innerHTML = `<div>${t('No permissions to referred object or it does not exist!')}</div>`;
	}

	_updateButtons() {
		for (const button of this._content_header.querySelectorAll('button')) {
			button.hidden = !button.classList.contains('js-widget-action') || !this.isEditMode();
		}
	}

	setEditMode() {
		super.setEditMode();

		const state = this.getState();

		if (state === WIDGET_STATE_ACTIVE || state === WIDGET_STATE_INACTIVE) {
			this._updateButtons();
		}
	}

	_promiseUpdate() {
		return Promise.resolve();
	}

	getActionsContextMenu({can_paste_widget}) {
		const menu = super.getActionsContextMenu({can_paste_widget});

		for (const section of menu) {
			switch (section.label) {
				case t('Actions'):
					for (const item of section.items) {
						if (item.label === t('Copy')) {
							item.disabled = true;
						}
					}
					break;

				case t('Refresh interval'):
					for (const item of section.items) {
						item.disabled = true;
					}
					break;
			}
		}

		return menu;
	}

	_hasPadding() {
		return true;
	}
}
