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
 * A class for accessing once loaded parameters of Settings API object.
 */
class CSettingsHelper {

	const ALERT_USRGRPID = 'alert_usrgrpid';
	const BLINK_PERIOD = 'blink_period';
	const CONNECT_TIMEOUT = 'connect_timeout';
	const CUSTOM_COLOR = 'custom_color';
	const DEFAULT_INVENTORY_MODE = 'default_inventory_mode';
	const DEFAULT_THEME = 'default_theme';
	const DISCOVERY_GROUPID = 'discovery_groupid';
	const HISTORY_PERIOD = 'history_period';
	const ITEM_TEST_TIMEOUT = 'item_test_timeout';
	const LOGIN_ATTEMPTS = 'login_attempts';
	const LOGIN_BLOCK = 'login_block';
	const MAX_IN_TABLE = 'max_in_table';
	const MAX_PERIOD = 'max_period';
	const MEDIA_TYPE_TEST_TIMEOUT = 'media_type_test_timeout';
	const OK_ACK_COLOR = 'ok_ack_color';
	const OK_ACK_STYLE = 'ok_ack_style';
	const OK_PERIOD = 'ok_period';
	const OK_UNACK_COLOR = 'ok_unack_color';
	const OK_UNACK_STYLE = 'ok_unack_style';
	const PERIOD_DEFAULT = 'period_default';
	const PROBLEM_ACK_COLOR = 'problem_ack_color';
	const PROBLEM_ACK_STYLE = 'problem_ack_style';
	const PROBLEM_UNACK_COLOR = 'problem_unack_color';
	const PROBLEM_UNACK_STYLE = 'problem_unack_style';
	const REFRESH_UNSUPPORTED = 'refresh_unsupported';
	const SCRIPT_TIMEOUT = 'script_timeout';
	const SEARCH_LIMIT = 'search_limit';
	const SERVER_CHECK_INTERVAL = 'server_check_interval';
	const SESSION_NAME = 'session_name';
	const SEVERITY_COLOR_0 = 'severity_color_0';
	const SEVERITY_COLOR_1 = 'severity_color_1';
	const SEVERITY_COLOR_2 = 'severity_color_2';
	const SEVERITY_COLOR_3 = 'severity_color_3';
	const SEVERITY_COLOR_4 = 'severity_color_4';
	const SEVERITY_COLOR_5 = 'severity_color_5';
	const SEVERITY_NAME_0 = 'severity_name_0';
	const SEVERITY_NAME_1 = 'severity_name_1';
	const SEVERITY_NAME_2 = 'severity_name_2';
	const SEVERITY_NAME_3 = 'severity_name_3';
	const SEVERITY_NAME_4 = 'severity_name_4';
	const SEVERITY_NAME_5 = 'severity_name_5';
	const SHOW_TECHNICAL_ERRORS = 'show_technical_errors';
	const SNMPTRAP_LOGGING = 'snmptrap_logging';
	const SOCKET_TIMEOUT = 'socket_timeout';
	const URI_VALID_SCHEMES = 'uri_valid_schemes';
	const VALIDATE_URI_SCHEMES = 'validate_uri_schemes';
	const WORK_PERIOD = 'work_period';
	const X_FRAME_OPTIONS = 'x_frame_options';

	/**
	 * Settings parameters array.
	 *
	 * @var array
	 */
	private static $params = [];

	/**
	 * Load once all parameters of Settings API object.
	 */
	private static function loadParams() {
		if (!self::$params) {
			self::$params = API::Settings()->get(['output' => 'extend']);
		}
	}

	/**
	 * Get value by parameter name of Settings (load parameters if need).
	 *
	 * @param string  $name  Settings parameter name.
	 *
	 * @return string Parameter value. If parameter not exists, return null.
	 */
	public static function get(string $name): ?string {
		self::loadParams();

		return (array_key_exists($name, self::$params) ? self::$params[$name] : null);
	}

	/**
	 * Get values of all parameters of Settings (load parameters if need).
	 *
	 * @return array String array with all values of Settings parameters in format <parameter name> => <value>.
	 */
	public static function getAll(): array {
		self::loadParams();

		return self::$params;
	}

	/**
	 * Set value by parameter name of Settings into $params (load parameters if need).
	 *
	 * @param string $name   Settings parameter name.
	 * @param string $value  Settings parameter value.
	 */
	public static function set(string $key, string $value): void {
		self::loadParams();

		if (array_key_exists($key, self::$params)) {
			self::$params[$key] = $value;
		}
	}
}
