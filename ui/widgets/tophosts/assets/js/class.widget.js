/*
** Copyright (C) 2001-2024 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
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
	 * @type {string}
	 */
	#selected_host_id = '';

	setContents(response) {
		super.setContents(response);

		this.#table_body = this._contents.querySelector(`.${ZBX_STYLE_LIST_TABLE} tbody`);

		if (this.#table_body !== null) {
			if (this.#selected_host_id !== '') {
				const row = this.#table_body.querySelector(`tr[data-hostid="${this.#selected_host_id}"]`);

				if (row !== null) {
					this.#selectHost();
				}
				else {
					this.#selected_host_id = '';
				}
			}

			this.#table_body.addEventListener('click', e => this.#onTableBodyClick(e));
		}
	}

	#selectHost() {
		const rows = this.#table_body.querySelectorAll('tr[data-hostid]');

		for (const row of rows) {
			row.classList.toggle(ZBX_STYLE_ROW_SELECTED, row.dataset.hostid === this.#selected_host_id);
		}
	}

	#onTableBodyClick(e) {
		if (e.target.closest('a') !== null || e.target.closest('[data-hintbox="1"]') !== null) {
			return;
		}

		const row = e.target.closest('tr');

		if (row !== null) {
			const hostid = row.dataset.hostid;

			if (hostid !== undefined) {
				this.#selected_host_id = hostid;

				this.#selectHost();

				this.broadcast({
					[CWidgetsData.DATA_TYPE_HOST_ID]: [hostid],
					[CWidgetsData.DATA_TYPE_HOST_IDS]: [hostid]
				});
			}
		}
	}
}
