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


class CWidgetTrigerOver extends CWidget {

	_registerEvents() {
		super._registerEvents();

		this._events = {
			...this._events,

			acknowledgeCreated: (e, response) => {
				for (let i = overlays_stack.length - 1; i >= 0; i--) {
					const overlay = overlays_stack.getById(overlays_stack.stack[i]);

					if (overlay.type === 'hintbox') {
						const element = overlay.element instanceof jQuery ? overlay.element[0] : overlay.element;

						if (this._content_body.contains(element)) {
							hintBox.deleteHint(overlay.element);
						}
					}
				}

				clearMessages();

				addMessage(makeMessageBox('good', [], response.message, true, false));

				if (this._state === WIDGET_STATE_ACTIVE) {
					this._startUpdating();
				}
			}
		}
	}

	_activateEvents() {
		super._activateEvents();

		$.subscribe('acknowledge.create', this._events.acknowledgeCreated);
	}

	_deactivateEvents() {
		super._deactivateEvents();

		$.unsubscribe('acknowledge.create', this._events.acknowledgeCreated);
	}
}
