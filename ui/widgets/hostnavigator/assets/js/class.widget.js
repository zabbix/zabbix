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


class CWidgetHostNavigator extends CWidget {

	/**
	 * @type {CHostNavigator|null}
	 */
	#host_navigator = null;

	/**
	 * Events of host navigator widget.
	 *
	 * @type {Object}
	 */
	#events = {};

	getUpdateRequestData() {
		return {
			...super.getUpdateRequestData(),
			with_config: this.#host_navigator === null ? 1 : undefined
		};
	}

	updateProperties({name, view_mode, fields}) {
		if (this.#host_navigator !== null) {
			this.#host_navigator.destroy();
			this.#host_navigator = null;
		}

		super.updateProperties({name, view_mode, fields});
	}

	setContents(response) {
		if (this.#host_navigator === null) {
			this.#host_navigator = new CHostNavigator(response.config);

			this._body.appendChild(this.#host_navigator.getContainer());

			this.#registerEvents();
			this.#activateEvents();
		}

		this.#host_navigator.setValue({
			hosts: response.hosts,
			maintenances: response.maintenances,
			is_limit_exceeded: response.is_limit_exceeded
		});
	}

	#registerEvents() {
		this.#events = {
			hostSelect: e => {
				this.broadcast({_hostid: e.detail._hostid});
			},
			groupToggle: e => {
				updateUserProfile(`web.dashboard.widget.hostnavigator.group-${e.detail.group_id}.toggle`,
					e.detail.is_closed, [this.getWidgetId()]
				);
			}
		};
	}

	#activateEvents() {
		this.#host_navigator.getContainer().addEventListener('host.select', this.#events.hostSelect);
		this.#host_navigator.getContainer().addEventListener('group.toggle', this.#events.groupToggle);
	}

	hasPadding() {
		return false;
	}
}
