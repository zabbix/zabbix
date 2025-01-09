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


class CWidgetUrl extends CWidget {

	onStart() {
		this._events = {
			...this._events,

			mousedown: () => {
				if (this._is_edit_mode) {
					const iframe = this._contents.querySelector('iframe');

					if (iframe !== null) {
						iframe.style.pointerEvents = 'none';

						addEventListener('mouseup', this._events.mouseup, {once: true});
					}
				}
			},

			mouseup: () => {
				const iframe = this._contents.querySelector('iframe');

				if (iframe !== null) {
					iframe.style.pointerEvents = '';
				}
			}
		}
	}

	onActivate() {
		this._target.addEventListener('mousedown', this._events.mousedown);
	}

	onDeactivate() {
		this._target.removeEventListener('mousedown', this._events.mousedown);
	}

	promiseReady() {
		const readiness = [super.promiseReady()];

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

	getUpdateRequestData() {
		const use_dashboard_host = this._dashboard.templateid !== null
			|| CWidgetBase.FOREIGN_REFERENCE_KEY in this.getFields().override_hostid;

		return {
			...super.getUpdateRequestData(),
			use_dashboard_host: use_dashboard_host ? '1' : undefined
		};
	}

	hasPadding() {
		return false;
	}
}
