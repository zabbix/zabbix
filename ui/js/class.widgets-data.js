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


class CWidgetsData {

	static DATA_TYPE_HOST_GROUP_ID =		'_hostgroupid';
	static DATA_TYPE_HOST_GROUP_IDS =		'_hostgroupids';
	static DATA_TYPE_HOST_ID =				'_hostid';
	static DATA_TYPE_HOST_IDS =				'_hostids';
	static DATA_TYPE_ITEM_ID =				'_itemid';
	static DATA_TYPE_ITEM_PROTOTYPE_ID =	'_itemprototypeid';
	static DATA_TYPE_GRAPH_ID =				'_graphid';
	static DATA_TYPE_GRAPH_PROTOTYPE_ID =	'_graphprototypeid';
	static DATA_TYPE_MAP_ID =				'_mapid';
	static DATA_TYPE_SERVICE_ID =			'_serviceid';
	static DATA_TYPE_SLA_ID =				'_slaid';
	static DATA_TYPE_TIME_PERIOD =			'_timeperiod';

	static getDefault(data_type) {
		switch (data_type) {
			case CWidgetsData.DATA_TYPE_HOST_GROUP_ID:
			case CWidgetsData.DATA_TYPE_HOST_GROUP_IDS:
			case CWidgetsData.DATA_TYPE_HOST_ID:
			case CWidgetsData.DATA_TYPE_HOST_IDS:
			case CWidgetsData.DATA_TYPE_ITEM_ID:
			case CWidgetsData.DATA_TYPE_ITEM_PROTOTYPE_ID:
			case CWidgetsData.DATA_TYPE_GRAPH_ID:
			case CWidgetsData.DATA_TYPE_GRAPH_PROTOTYPE_ID:
			case CWidgetsData.DATA_TYPE_MAP_ID:
			case CWidgetsData.DATA_TYPE_SERVICE_ID:
			case CWidgetsData.DATA_TYPE_SLA_ID:
				return [];

			case CWidgetsData.DATA_TYPE_TIME_PERIOD:
				return {
					from: null,
					from_ts: null,
					to: null,
					to_ts: null
				};

			default:
				return null;
		}
	}
}
