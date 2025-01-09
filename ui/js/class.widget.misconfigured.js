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


class CWidgetMisconfigured extends CWidget {

	static MESSAGE_TYPE_EMPTY_REFERENCES = 'empty-references';

	#messages = [];

	#message_type = null;

	onStart() {
		if (this.#messages.length > 0) {
			this._updateMessages(this.#messages);
		}
		else if (this.#message_type !== null) {
			this.setCoverMessage(CWidgetMisconfigured.#getMessageTypeInfo(this.#message_type));
		}
	}

	setMessages(messages) {
		this.#messages = messages;

		if (this.getState() !== WIDGET_STATE_INITIAL) {
			this._updateMessages(this.#messages);
		}
	}

	setMessageType(message_type) {
		this.#message_type = message_type;

		if (this.getState() !== WIDGET_STATE_INITIAL) {
			this.setCoverMessage(CWidgetMisconfigured.#getMessageTypeInfo(message_type));
		}
	}

	static #getMessageTypeInfo(message_type) {
		switch (message_type) {
			case CWidgetMisconfigured.MESSAGE_TYPE_EMPTY_REFERENCES:
				return {
					message: t('Referred widget is unavailable'),
					description: t('Please update configuration'),
					icon: ZBX_ICON_WIDGET_EMPTY_REFERENCES_LARGE
				};
		}
	}

	_startUpdating({delay_sec = 0} = {}) {
		this.broadcast(this.getBroadcastDefaults());

		requestAnimationFrame(() => this.fire(CWidgetBase.EVENT_READY));
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
