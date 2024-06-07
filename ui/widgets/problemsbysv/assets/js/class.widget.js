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
**/


class CWidgetProblemsBySv extends CWidget {

	static SHOW_GROUPS = 0;
	static SHOW_TOTALS = 1;

	static LAYOUT_HORIZONTAL = 0;
	static LAYOUT_VERTICAL = 1;

	/**
	 * Table body of problems.
	 *
	 * @type {HTMLElement|null}
	 */
	#table_body = null;

	/**
	 * ID of selected host group.
	 *
	 * @type {string}
	 */
	#selected_host_group_id = '';

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

	onResize() {
		this.#adjustSize();
	}

	setContents(response) {
		super.setContents(response);

		this.#adjustSize();

		if (this.getFields().show_type !== CWidgetProblemsBySv.SHOW_TOTALS) {
			this.#table_body = this._contents.querySelector(`.${ZBX_STYLE_LIST_TABLE} tbody`);

			if (this.#table_body !== null) {
				if (this.#selected_host_group_id !== '') {
					const row = this.#table_body
						.querySelector(`tr[data-hostgroupid="${this.#selected_host_group_id}"]`);

					if (row !== null) {
						this.#selectHostGroup();
					}
					else {
						this.#selected_host_group_id = '';
					}
				}

				this.#table_body.addEventListener('click', e => this.#onTableBodyClick(e));
			}
		}
	}

	#adjustSize() {
		if (this.getFields().show_type === CWidgetProblemsBySv.SHOW_TOTALS
				&& this.getFields().layout === CWidgetProblemsBySv.LAYOUT_VERTICAL) {
			const number_elements = this._contents.querySelectorAll(
				`.${ZBX_STYLE_TOTALS_LIST} .${ZBX_STYLE_TOTALS_LIST_COUNT} .${ZBX_STYLE_TOTALS_LIST_NUMBER},
					.${ZBX_STYLE_TOTALS_LIST} .${ZBX_STYLE_TOTALS_LIST_COUNT} .${ZBX_STYLE_TOTALS_LIST_OF}`
			);
			const number_widths = [];

			for (const number_element of number_elements) {
				number_widths.push(number_element.getBoundingClientRect().width);
			}

			const cell_element = this._contents.querySelector(`.${ZBX_STYLE_TOTALS_LIST} > div`);
			const cell_width = parseFloat(getComputedStyle(cell_element).width);
			const min_name_width = 30;
			const max_count_width = cell_width - min_name_width;
			const count_elements = this._contents.querySelectorAll(
				`.${ZBX_STYLE_TOTALS_LIST} .${ZBX_STYLE_TOTALS_LIST_COUNT}`
			);
			const count_width = Math.max(0, Math.min(Math.max(...number_widths), max_count_width));

			for (const count_element of count_elements) {
				count_element.style.width = `${count_width}px`;
			}
		}
	}

	#selectHostGroup() {
		const rows = this.#table_body.querySelectorAll('tr[data-hostgroupid]');

		for (const row of rows) {
			row.classList.toggle(ZBX_STYLE_ROW_SELECTED, row.dataset.hostgroupid === this.#selected_host_group_id);
		}
	}

	#onTableBodyClick(e) {
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
	}

	hasPadding() {
		return this.getViewMode() === ZBX_WIDGET_VIEW_MODE_NORMAL
			&& this.getFields().show_type !== CWidgetProblemsBySv.SHOW_TOTALS;
	}
}
