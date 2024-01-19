<?php declare(strict_types = 0);
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


/**
 * A class for accessing once loaded parameters of Settings API object.
 */
class CSettingsHelper {

	public const ALERT_USRGRPID = 'alert_usrgrpid';
	public const BLINK_PERIOD = 'blink_period';
	public const CONNECT_TIMEOUT = 'connect_timeout';
	public const CUSTOM_COLOR = 'custom_color';
	public const DEFAULT_INVENTORY_MODE = 'default_inventory_mode';
	public const DEFAULT_LANG = 'default_lang';
	public const DEFAULT_THEME = 'default_theme';
	public const DEFAULT_TIMEZONE = 'default_timezone';
	public const DISCOVERY_GROUPID = 'discovery_groupid';
	public const HISTORY_PERIOD = 'history_period';
	public const IFRAME_SANDBOXING_ENABLED = 'iframe_sandboxing_enabled';
	public const IFRAME_SANDBOXING_EXCEPTIONS = 'iframe_sandboxing_exceptions';
	public const ITEM_TEST_TIMEOUT = 'item_test_timeout';
	public const LOGIN_ATTEMPTS = 'login_attempts';
	public const LOGIN_BLOCK = 'login_block';
	public const MAX_IN_TABLE = 'max_in_table';
	public const MAX_PERIOD = 'max_period';
	public const MAX_OVERVIEW_TABLE_SIZE = 'max_overview_table_size';
	public const MEDIA_TYPE_TEST_TIMEOUT = 'media_type_test_timeout';
	public const OK_ACK_COLOR = 'ok_ack_color';
	public const OK_ACK_STYLE = 'ok_ack_style';
	public const OK_PERIOD = 'ok_period';
	public const OK_UNACK_COLOR = 'ok_unack_color';
	public const OK_UNACK_STYLE = 'ok_unack_style';
	public const PERIOD_DEFAULT = 'period_default';
	public const PROBLEM_ACK_COLOR = 'problem_ack_color';
	public const PROBLEM_ACK_STYLE = 'problem_ack_style';
	public const PROBLEM_UNACK_COLOR = 'problem_unack_color';
	public const PROBLEM_UNACK_STYLE = 'problem_unack_style';
	public const SCRIPT_TIMEOUT = 'script_timeout';
	public const SEARCH_LIMIT = 'search_limit';
	public const SERVER_CHECK_INTERVAL = 'server_check_interval';
	public const SEVERITY_COLOR_0 = 'severity_color_0';
	public const SEVERITY_COLOR_1 = 'severity_color_1';
	public const SEVERITY_COLOR_2 = 'severity_color_2';
	public const SEVERITY_COLOR_3 = 'severity_color_3';
	public const SEVERITY_COLOR_4 = 'severity_color_4';
	public const SEVERITY_COLOR_5 = 'severity_color_5';
	public const SEVERITY_NAME_0 = 'severity_name_0';
	public const SEVERITY_NAME_1 = 'severity_name_1';
	public const SEVERITY_NAME_2 = 'severity_name_2';
	public const SEVERITY_NAME_3 = 'severity_name_3';
	public const SEVERITY_NAME_4 = 'severity_name_4';
	public const SEVERITY_NAME_5 = 'severity_name_5';
	public const SHOW_TECHNICAL_ERRORS = 'show_technical_errors';
	public const SNMPTRAP_LOGGING = 'snmptrap_logging';
	public const SOCKET_TIMEOUT = 'socket_timeout';
	public const URI_VALID_SCHEMES = 'uri_valid_schemes';
	public const VALIDATE_URI_SCHEMES = 'validate_uri_schemes';
	public const WORK_PERIOD = 'work_period';
	public const X_FRAME_OPTIONS = 'x_frame_options';
	public const SESSION_KEY = 'session_key';
	public const URL = 'url';
	public const SCHEDULED_REPORT_TEST_TIMEOUT = 'report_test_timeout';
	public const DBVERSION_STATUS = 'dbversion_status';
	public const AUDITLOG_ENABLED = 'auditlog_enabled';
	public const GEOMAPS_TILE_PROVIDER = 'geomaps_tile_provider';
	public const GEOMAPS_TILE_URL = 'geomaps_tile_url';
	public const GEOMAPS_MAX_ZOOM = 'geomaps_max_zoom';
	public const GEOMAPS_ATTRIBUTION = 'geomaps_attribution';
	public const HA_FAILOVER_DELAY = 'ha_failover_delay';

	private static $params = [];
	private static $params_public = [];
	private static $params_private = [];

	/**
	 * Get the value of the given Settings API object's field.
	 *
	 * @param string $field
	 *
	 * @throws Exception
	 *
	 * @return string|null
	 */
	public static function get(string $field): ?string {
		if (!self::$params) {
			self::$params = API::Settings()->get([
				'output' => [
					'default_theme', 'search_limit', 'max_in_table', 'server_check_interval', 'work_period',
					'show_technical_errors', 'history_period', 'period_default', 'max_period', 'severity_color_0',
					'severity_color_1', 'severity_color_2', 'severity_color_3', 'severity_color_4', 'severity_color_5',
					'severity_name_0', 'severity_name_1', 'severity_name_2', 'severity_name_3', 'severity_name_4',
					'severity_name_5', 'custom_color', 'ok_period', 'blink_period', 'problem_unack_color',
					'problem_ack_color', 'ok_unack_color', 'ok_ack_color', 'problem_unack_style', 'problem_ack_style',
					'ok_unack_style', 'ok_ack_style', 'discovery_groupid', 'default_inventory_mode', 'alert_usrgrpid',
					'snmptrap_logging', 'default_lang', 'default_timezone', 'login_attempts', 'login_block',
					'validate_uri_schemes', 'uri_valid_schemes', 'x_frame_options', 'iframe_sandboxing_enabled',
					'iframe_sandboxing_exceptions', 'max_overview_table_size', 'connect_timeout', 'socket_timeout',
					'media_type_test_timeout', 'script_timeout', 'item_test_timeout', 'url', 'report_test_timeout',
					'auditlog_enabled', 'ha_failover_delay', 'geomaps_tile_provider', 'geomaps_tile_url',
					'geomaps_max_zoom', 'geomaps_attribution'
				]
			]);

			if (self::$params === false) {
				throw new Exception(_('Unable to load settings API parameters.'));
			}
		}

		return array_key_exists($field, self::$params) ? self::$params[$field] : null;
	}

	/**
	 * Get the value of the given Settings API object's field available to parts of the UI without authentication.
	 *
	 * @param string $field
	 *
	 * @return string|null
	 */
	public static function getPublic(string $field): ?string {
		if (!self::$params_public) {
			self::$params_public = CSettings::getPublic();
		}

		return array_key_exists($field, self::$params_public) ? self::$params_public[$field] : null;
	}

	/**
	 * Get the value of the given private settings field used in UI.
	 *
	 * @param string $field
	 *
	 * @return string|null
	 */
	public static function getPrivate(string $field): ?string {
		if (!self::$params_private) {
			self::$params_private = CSettings::getPrivate();
		}

		$supported_params = array_diff_key(self::$params_private, array_flip([self::SESSION_KEY]));

		return array_key_exists($field, $supported_params) ? $supported_params[$field] : null;
	}

	public static function getDbVersionStatus(): array {
		if (!self::$params_private) {
			self::$params_private = CSettings::getPrivate();
		}

		return array_key_exists(self::DBVERSION_STATUS, self::$params_private)
			? self::$params_private[self::DBVERSION_STATUS]
			: [];
	}
}
