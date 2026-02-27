/*
** Copyright (C) 2001-2026 Zabbix SIA
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


class CWidgetTopHosts extends CWidget {

	/**
	 * Table body of top hosts.
	 *
	 * @type {HTMLElement|null}
	 */
	#table_body = null;

	/**
	 * ID of selected host.
	 *
	 * @type {string|null}
	 */
	#selected_hostid = null;

	setContents(response) {
		super.setContents(response);

		this.#table_body = this._contents.querySelector(`.${ZBX_STYLE_LIST_TABLE} tbody`);

		if (this.#table_body === null) {
			return;
		}

		this.#table_body.addEventListener('click', e => this.#onTableBodyClick(e));

		if (this.isReferred() && (this.isFieldsReferredDataUpdated() || !this.hasEverUpdated())) {
			if (this.#selected_hostid === null || !this.#hasSelectable()) {
				this.#selected_hostid = this.#getDefaultSelectable();
			}

			if (this.#selected_hostid !== null) {
				this.#selectHost();
				this.#broadcastSelected();
			}
		}
		else if (this.#selected_hostid !== null) {
			this.#selectHost();
		}
	}

	onReferredUpdate() {
		if (this.#table_body === null || this.#selected_hostid !== null) {
			return;
		}

		this.#selected_hostid = this.#getDefaultSelectable();

		if (this.#selected_hostid !== null) {
			this.#selectHost();
			this.#broadcastSelected();
		}
	}

	#getDefaultSelectable() {
		const row = this.#table_body.querySelector('[data-hostid]');

		return row !== null ? row.dataset.hostid : null;
	}

	#hasSelectable() {
		return this.#table_body.querySelector(`[data-hostid="${this.#selected_hostid}"]`) !== null;
	}

	#selectHost() {
		const rows = this.#table_body.querySelectorAll('[data-hostid]');

		for (const row of rows) {
			row.classList.toggle(ZBX_STYLE_ROW_SELECTED, row.dataset.hostid === this.#selected_hostid);
		}
	}

	#broadcastSelected() {
		this.broadcast({
			[CWidgetsData.DATA_TYPE_HOST_ID]: [this.#selected_hostid],
			[CWidgetsData.DATA_TYPE_HOST_IDS]: [this.#selected_hostid]
		});
	}

	#onTableBodyClick(e) {
		if (e.target.closest('a') !== null || e.target.closest('[data-hintbox="1"]') !== null) {
			return;
		}

		const row = e.target.closest('[data-hostid]');

		if (row !== null) {
			this.#selected_hostid = row.dataset.hostid

			this.#selectHost();
			this.#broadcastSelected();
		}
	}
}
