/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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


class CWidgetUrl extends CWidget {

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
