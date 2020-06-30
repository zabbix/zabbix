<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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


/**
 * A class for accessing once loaded parameters of Housekeeping API object.
 */
class CHousekeepingHelper {

	const COMPRESS_OLDER = 'compress_older';
	const COMPRESSION_AVAILABILITY = 'compression_availability';
	const COMPRESSION_STATUS = 'compression_status';
	const DB_EXTENSION = 'db_extension';
	const HK_AUDIT = 'hk_audit';
	const HK_AUDIT_MODE = 'hk_audit_mode';
	const HK_EVENTS_AUTOREG = 'hk_events_autoreg';
	const HK_EVENTS_DISCOVERY = 'hk_events_discovery';
	const HK_EVENTS_INTERNAL = 'hk_events_internal';
	const HK_EVENTS_MODE = 'hk_events_mode';
	const HK_EVENTS_TRIGGER = 'hk_events_trigger';
	const HK_HISTORY = 'hk_history';
	const HK_HISTORY_GLOBAL = 'hk_history_global';
	const HK_HISTORY_MODE = 'hk_history_mode';
	const HK_SERVICES = 'hk_services';
	const HK_SERVICES_MODE = 'hk_services_mode';
	const HK_SESSIONS = 'hk_sessions';
	const HK_SESSIONS_MODE = 'hk_sessions_mode';
	const HK_TRENDS = 'hk_trends';
	const HK_TRENDS_GLOBAL = 'hk_trends_global';
	const HK_TRENDS_MODE = 'hk_trends_mode';

	/**
	 * Housekeeping parameters array.
	 *
	 * @var array
	 */
	private static $params = [];

	/**
	 * Load once all parameters of Housekeeping API object.
	 */
	private static function loadParams() {
		if (!self::$params) {
			self::$params = API::Housekeeping()->get(['output' => 'extend']);
		}
	}

	/**
	 * Get value by parameter name of Housekeeping (load parameters if need).
	 *
	 * @param string  $name  Housekeeping parameter name.
	 *
	 * @return string Parameter value. If parameter not exists, return null.
	 */
	public static function get(string $name): ?string {
		self::loadParams();

		return (array_key_exists($name, self::$params) ? self::$params[$name] : null);
	}

	/**
	 * Get values of all parameters of Housekeeping (load parameters if need).
	 *
	 * @return array String array with all values of Housekeeping parameters in format <parameter name> => <value>.
	 */
	public static function getAll(): array {
		self::loadParams();

		return self::$params;
	}

	/**
	 * Set value by parameter name of Housekeeping into $params (load parameters if need).
	 *
	 * @param string $name   Housekeeping parameter name.
	 * @param string $value  Housekeeping parameter value.
	 */
	public static function set(string $key, string $value): void {
		self::loadParams();

		if (array_key_exists($key, self::$params)) {
			self::$params[$key] = $value;
		}
	}
}
