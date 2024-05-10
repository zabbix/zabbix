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


class CDashboardPrint extends CDashboard {

	activate() {
		if (this._dashboard_pages.size === 0) {
			throw new Error('Cannot activate dashboard without dashboard pages.');
		}

		this._state = DASHBOARD_STATE_ACTIVE;

		for (const dashboard_page of this.getDashboardPages()) {
			this._startDashboardPage(dashboard_page);

			dashboard_page.activate();
		}
	}

	addDashboardPage({dashboard_pageid, name, display_period, widgets}, container) {
		const dashboard_page = new CDashboardPage(container, {
			data: {
				dashboard_pageid,
				name,
				display_period
			},
			dashboard: {
				templateid: this._data.templateid,
				dashboardid: this._data.dashboardid
			},
			cell_width: this._cell_width,
			cell_height: this._cell_height,
			max_columns: this._max_columns,
			max_rows: this._max_rows,
			widget_defaults: this._widget_defaults,
			is_editable: this._is_editable,
			is_edit_mode: this._is_edit_mode,
			csrf_token: this._csrf_token,
			unique_id: this._createUniqueId()
		});

		this._dashboard_pages.set(dashboard_page, {is_ready: false});

		for (const widget_data of widgets) {
			dashboard_page.addWidgetFromData({
				...widget_data,
				is_new: false,
				unique_id: this._createUniqueId()
			});
		}

		return dashboard_page;
	}

	_updateReadyState() {
		let is_ready = true;

		for (const data of this._dashboard_pages.values()) {
			if (!data.is_ready) {
				is_ready = false;

				break;
			}
		}

		this._target.classList.toggle(CDashboard.ZBX_STYLE_IS_READY, is_ready);
	}
}
