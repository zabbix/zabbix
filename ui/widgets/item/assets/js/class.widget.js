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


class CWidgetItem extends CWidget {

	static AGGREGATE_NONE = 0;

	onStart() {
		this._events.resize = () => {
			const margin = 5;
			const padding = 10;
			const header_height = this._view_mode === ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER ? 0 : 33;

			this._target.style.setProperty(
				'--content-height',
				`${this._cell_height * this._pos.height - margin * 2 - padding * 2 - header_height}px`
			);
		}
	}

	onActivate() {
		this._resize_observer = new ResizeObserver(this._events.resize);
		this._resize_observer.observe(this._target);
	}

	onDeactivate() {
		this._resize_observer.disconnect();
	}

	getUpdateRequestData() {
		const update_request_data = super.getUpdateRequestData();

		if (this.getFieldsData().aggregate_function !== CWidgetItem.AGGREGATE_NONE
				&& !this.getFieldsReferredData().has('time_period')) {
			update_request_data.has_custom_time_period = 1;
		}

		return update_request_data;
	}

	hasPadding() {
		return false;
	}
}
