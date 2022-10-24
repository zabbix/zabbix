<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
class CSettingsHelper extends CConfigGeneralHelper {

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

	/**
	 * Settings API object parameters array.
	 *
	 * @static
	 *
	 * @var array
	 */
	protected static $params = [];

	/**
	 * @inheritdoc
	 */
	protected static function loadParams(?string $param = null, bool $is_global = false): void {
		if (!self::$params) {
			self::$params = $is_global
				? API::getApiService('settings')->getGlobal(['output' => 'extend'], false)
				: API::Settings()->get(['output' => 'extend']);
		}
		else if (!$is_global && $param && !array_key_exists($param, self::$params)) {
			$settings = API::Settings()->get(['output' => 'extend']);
			self::$params = is_array($settings) ? ($settings + self::$params) : false;
		}

		if (self::$params === false) {
			throw new Exception(_('Unable to load settings API parameters.'));
		}
	}

	/**
	 * Get value by global parameter name of API object (load parameters if need).
	 *
	 * @static
	 *
	 * @param string  $name  API object global parameter name.
	 *
	 * @return string|null Parameter value. If parameter not exists, return null.
	 */
	public static function getGlobal(string $name): ?string {
		self::loadParams($name, true);

		return array_key_exists($name, self::$params) ? self::$params[$name] : null;
	}

	/**
	 * @return array
	 */
	public static function getDbVersionStatus(): array {
		$dbversion_status = json_decode((string) self::getGlobal(self::DBVERSION_STATUS), true);

		if (!is_array($dbversion_status)) {
			return [];
		}

		return $dbversion_status;
	}
}
