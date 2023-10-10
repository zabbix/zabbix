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


class CWidgetMisconfigured extends CWidget {

	#messages = [];

	onStart() {
		this._updateMessages(this.#messages);
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

	setMessages(messages) {
		this.#messages = messages;

		if (this.getState() !== WIDGET_STATE_INITIAL) {
			this._updateMessages(this.#messages);
		}
	}

	hasPadding() {
		return true;
	}
}
