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
