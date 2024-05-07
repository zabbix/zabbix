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


class CWidgetWeb extends CWidget {

	/**
	 * Table body of web monitoring.
	 *
	 * @type {HTMLElement|null}
	 */
	#table_body = null;

	/**
	 * Listeners of web monitoring widget.
	 *
	 * @type {Object}
	 */
	#listeners = {};

	/**
	 * ID of selected host group.
	 *
	 * @type {string}
	 */
	#selected_host_group_id = '';

	setContents(response) {
		super.setContents(response);

		this.#table_body = this._contents.querySelector(`.${ZBX_STYLE_LIST_TABLE} tbody`);

		if (this.#table_body !== null) {
			if (this.#selected_host_group_id !== '') {
				const row = this.#table_body.querySelector(`tr[data-hostgroupid="${this.#selected_host_group_id}"]`);

				if (row !== null) {
					this.#selectHostGroup();
				}
				else {
					this.#selected_host_group_id = '';
				}
			}

			this.#registerListeners();
			this.#activateListeners();
		}
	}

	#registerListeners() {
		this.#listeners = {
			tableBodyClick: e => {
				if (e.target.closest('a') !== null || e.target.closest('[data-hintbox="1"]') !== null) {
					return;
				}

				const row = e.target.closest('tr');

				if (row !== null) {
					const hostgroupid = row.dataset.hostgroupid;

					if (hostgroupid !== undefined) {
						this.#selected_host_group_id = hostgroupid;

						this.#selectHostGroup();

						this.broadcast({
							[CWidgetsData.DATA_TYPE_HOST_GROUP_ID]: [hostgroupid],
							[CWidgetsData.DATA_TYPE_HOST_GROUP_IDS]: [hostgroupid]
						});
					}
				}
			},
		};
	}

	#activateListeners() {
		this.#table_body.addEventListener('click', this.#listeners.tableBodyClick);
	}

	/**
	 * Select host group row.
	 */
	#selectHostGroup() {
		const rows = this.#table_body.querySelectorAll('tr[data-hostgroupid]');

		for (const row of rows) {
			row.classList.toggle(ZBX_STYLE_ROW_SELECTED, row.dataset.hostgroupid === this.#selected_host_group_id);
		}
	}
}
