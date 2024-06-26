/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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


class CWidgetUrl extends CWidget {

	_promiseReady() {
		const readiness = [super._promiseReady()];

		const iframe = this._target.querySelector('iframe');

		if (iframe !== null) {
			readiness.push(
				new Promise(resolve => {
					iframe.addEventListener('load', () => setTimeout(resolve, 200));
				})
			);
		}

		return Promise.all(readiness);
	}

	_registerEvents() {
		super._registerEvents();

		this._events = {
			...this._events,

			mousedown: () => {
				if (this._is_edit_mode) {
					const iframe = this._content_body.querySelector('iframe');

					if (iframe !== null) {
						iframe.style.pointerEvents = 'none';

						addEventListener('mouseup', this._events.mouseup, {once: true});
					}
				}
			},

			mouseup: () => {
				const iframe = this._content_body.querySelector('iframe');

				if (iframe !== null) {
					iframe.style.pointerEvents = '';
				}
			}
		}
	}

	_activateEvents() {
		super._activateEvents();

		this._target.addEventListener('mousedown', this._events.mousedown);
	}

	_deactivateEvents() {
		super._deactivateEvents();

		this._target.removeEventListener('mousedown', this._events.mousedown);
	}
}
