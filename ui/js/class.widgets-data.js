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


/**
 * Singleton class, containing information about standard widget data types.
 */
class CWidgetsData {

	static DATA_TYPE_EVENT_ID =				'_eventid';
	static DATA_TYPE_HOST_GROUP_ID =		'_hostgroupid';
	static DATA_TYPE_HOST_GROUP_IDS =		'_hostgroupids';
	static DATA_TYPE_HOST_ID =				'_hostid';
	static DATA_TYPE_HOST_IDS =				'_hostids';
	static DATA_TYPE_ITEM_ID =				'_itemid';
	static DATA_TYPE_ITEM_IDS =				'_itemids';
	static DATA_TYPE_ITEM_PROTOTYPE_ID =	'_itemprototypeid';
	static DATA_TYPE_GRAPH_ID =				'_graphid';
	static DATA_TYPE_GRAPH_PROTOTYPE_ID =	'_graphprototypeid';
	static DATA_TYPE_MAP_ID =				'_mapid';
	static DATA_TYPE_SERVICE_ID =			'_serviceid';
	static DATA_TYPE_SLA_ID =				'_slaid';
	static DATA_TYPE_TIME_PERIOD =			'_timeperiod';

	/**
	 * @type {CWidgetsData|null}
	 */
	static #singleton = null;

	#defaults = new Map();

	static #getSingleton() {
		if (CWidgetsData.#singleton === null) {
			CWidgetsData.#singleton = new CWidgetsData();
		}

		return CWidgetsData.#singleton;
	}

	static getDefault(data_type) {
		const singleton = CWidgetsData.#getSingleton();

		if (singleton.#defaults.has(data_type)) {
			return singleton.#defaults.get(data_type).value;
		}

		return null;
	}

	static setDefault(data_type, value, {is_comparable} = {}) {
		const singleton = CWidgetsData.#getSingleton();

		singleton.#defaults.set(data_type, {value, is_comparable});
	}

	static isDefault(data_type, value) {
		const singleton = CWidgetsData.#getSingleton();

		if (singleton.#defaults.has(data_type)) {
			const _default = singleton.#defaults.get(data_type);

			if (_default.is_comparable && JSON.stringify(value) === JSON.stringify(_default.value)) {
				return true;
			}
		}

		return false;
	}

	constructor() {
		this.#defaults.set(CWidgetsData.DATA_TYPE_EVENT_ID, {value: [], is_comparable: true});
		this.#defaults.set(CWidgetsData.DATA_TYPE_HOST_GROUP_ID, {value: [], is_comparable: true});
		this.#defaults.set(CWidgetsData.DATA_TYPE_HOST_GROUP_IDS, {value: [], is_comparable: true});
		this.#defaults.set(CWidgetsData.DATA_TYPE_HOST_ID, {value: [], is_comparable: true});
		this.#defaults.set(CWidgetsData.DATA_TYPE_HOST_IDS, {value: [], is_comparable: true});
		this.#defaults.set(CWidgetsData.DATA_TYPE_ITEM_ID, {value: [], is_comparable: true});
		this.#defaults.set(CWidgetsData.DATA_TYPE_ITEM_IDS, {value: [], is_comparable: true});
		this.#defaults.set(CWidgetsData.DATA_TYPE_ITEM_PROTOTYPE_ID, {value: [], is_comparable: true});
		this.#defaults.set(CWidgetsData.DATA_TYPE_GRAPH_ID, {value: [], is_comparable: true});
		this.#defaults.set(CWidgetsData.DATA_TYPE_GRAPH_PROTOTYPE_ID, {value: [], is_comparable: true});
		this.#defaults.set(CWidgetsData.DATA_TYPE_MAP_ID, {value: [], is_comparable: true});
		this.#defaults.set(CWidgetsData.DATA_TYPE_SERVICE_ID, {value: [], is_comparable: true});
		this.#defaults.set(CWidgetsData.DATA_TYPE_SLA_ID, {value: [], is_comparable: true});
		this.#defaults.set(CWidgetsData.DATA_TYPE_TIME_PERIOD, {
			value: {
				from: null,
				from_ts: null,
				to: null,
				to_ts: null
			},
			is_comparable: true
		});
	}
}
