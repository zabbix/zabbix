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


class CWidgetWeb extends CWidget {

	/**
	 * Table body of web monitoring.
	 *
	 * @type {HTMLElement|null}
	 */
	#table_body = null;

	/**
	 * ID of selected host group.
	 *
	 * @type {string|null}
	 */
	#selected_hostgroupid = null;

	setContents(response) {
		super.setContents(response);

		this.#table_body = this._contents.querySelector(`.${ZBX_STYLE_LIST_TABLE} tbody`);

		if (this.#table_body == null) {
			return;
		}

		this.#table_body.addEventListener('click', e => this.#onTableBodyClick(e));

		if (!this.hasEverUpdated() && this.isReferred()) {
			this.#selected_hostgroupid = this.#getDefaultSelectable();

			if (this.#selected_hostgroupid !== null) {
				this.#selectHostGroup();
				this.#broadcast();
			}
		}
		else if (this.#selected_hostgroupid !== null) {
			this.#selectHostGroup();
		}
	}

	onReferredUpdate() {
		if (this.#table_body === null || this.#selected_hostgroupid !== null) {
			return;
		}

		this.#selected_hostgroupid = this.#getDefaultSelectable();

		if (this.#selected_hostgroupid !== null) {
			this.#selectHostGroup();
			this.#broadcast();
		}
	}

	#getDefaultSelectable() {
		const row = this.#table_body.querySelector('[data-hostgroupid]');

		return row !== null ? row.dataset.hostgroupid : null;
	}

	#selectHostGroup() {
		const rows = this.#table_body.querySelectorAll('[data-hostgroupid]');

		for (const row of rows) {
			row.classList.toggle(ZBX_STYLE_ROW_SELECTED, row.dataset.hostgroupid === this.#selected_hostgroupid);
		}
	}

	#broadcast() {
		this.broadcast({
			[CWidgetsData.DATA_TYPE_HOST_GROUP_ID]: [this.#selected_hostgroupid],
			[CWidgetsData.DATA_TYPE_HOST_GROUP_IDS]: [this.#selected_hostgroupid]
		});
	}

	#onTableBodyClick(e) {
		if (e.target.closest('a') !== null || e.target.closest('[data-hintbox="1"]') !== null) {
			return;
		}

		const row = e.target.closest('[data-hostgroupid]');

		if (row !== null) {
			this.#selected_hostgroupid = row.dataset.hostgroupid;

			this.#selectHostGroup();
			this.#broadcast();
		}
	}
}
