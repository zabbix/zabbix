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


class CWidgetProblemsBySv extends CWidget {

	static SHOW_GROUPS = 0;
	static SHOW_TOTALS = 1;

	/**
	 * Table body of problems.
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

	onStart() {
		this._events = {
			...this._events,

			acknowledgeCreated: (e, response) => {
				clearMessages();
				addMessage(makeMessageBox('good', [], response.success.title));

				if (this._state === WIDGET_STATE_ACTIVE) {
					this._startUpdating();
				}
			}
		}
	}

	onActivate() {
		$.subscribe('acknowledge.create', this._events.acknowledgeCreated);
	}

	onDeactivate() {
		$.unsubscribe('acknowledge.create', this._events.acknowledgeCreated);
	}

	setContents(response) {
		super.setContents(response);

		if (this.getFields().show_type !== CWidgetProblemsBySv.SHOW_GROUPS) {
			return;
		}

		this.#table_body = this._body.querySelector(`.${ZBX_STYLE_LIST_TABLE} tbody`);

		if (this.#table_body === null) {
			return;
		}

		this.#table_body.addEventListener('click', e => this.#onTableBodyClick(e));

		if (!this.hasEverUpdated() && this.isReferred()) {
			this.#selected_hostgroupid = this.#getDefaultSelectable();

			if (this.#selected_hostgroupid !== null) {
				this.#select();
				this.#broadcast();
			}
		}
		else if (this.#selected_hostgroupid !== null) {
			this.#select();
		}
	}

	#getDefaultSelectable() {
		const row = this.#table_body.querySelector('[data-hostgroupid]');

		return row !== null ? row.dataset.hostgroupid : null;
	}

	#select() {
		for (const row of this.#table_body.querySelectorAll('[data-hostgroupid]')) {
			row.classList.toggle(ZBX_STYLE_ROW_SELECTED, row.dataset.hostgroupid === this.#selected_hostgroupid);
		}
	}

	#broadcast() {
		this.broadcast({
			[CWidgetsData.DATA_TYPE_HOST_GROUP_ID]: [this.#selected_hostgroupid],
			[CWidgetsData.DATA_TYPE_HOST_GROUP_IDS]: [this.#selected_hostgroupid]
		});
	}

	onReferredUpdate() {
		if (this.#table_body === null || this.#selected_hostgroupid !== null) {
			return;
		}

		this.#selected_hostgroupid = this.#getDefaultSelectable();

		if (this.#selected_hostgroupid !== null) {
			this.#select();
			this.#broadcast();
		}
	}

	#onTableBodyClick(e) {
		if (e.target.closest('a') !== null || e.target.closest('[data-hintbox="1"]') !== null) {
			return;
		}

		const row = e.target.closest('[data-hostgroupid]');

		if (row !== null) {
			this.#selected_hostgroupid = row.dataset.hostgroupid;

			this.#select();
			this.#broadcast();
		}
	}

	hasPadding() {
		return this.getViewMode() === ZBX_WIDGET_VIEW_MODE_NORMAL
			&& this.getFields().show_type !== CWidgetProblemsBySv.SHOW_TOTALS;
	}
}
