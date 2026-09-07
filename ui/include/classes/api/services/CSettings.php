<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
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
 * Class containing methods for operations with the main part of administration settings.
 */
class CSettings extends CApiService {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_ZABBIX_USER],
		'update' => ['min_user_type' => USER_TYPE_SUPER_ADMIN]
	];

	public const OUTPUT_FIELDS = [
		// GUI.
		'default_lang', 'default_timezone', 'default_theme', 'search_limit', 'max_overview_table_size', 'max_in_table',
		'server_check_interval', 'work_period', 'show_technical_errors', 'history_period', 'period_default',
		'max_period',

		// Timeouts.
		'timeout_zabbix_agent', 'timeout_simple_check', 'timeout_snmp_agent', 'timeout_external_check',
		'timeout_db_monitor', 'timeout_http_agent', 'timeout_ssh_agent', 'timeout_telnet_agent', 'timeout_script',
		'timeout_browser', 'timeout_telemetry_query', 'socket_timeout', 'connect_timeout', 'media_type_test_timeout',
		'script_timeout', 'item_test_timeout', 'report_test_timeout', 'device_link_timeout',

		// Trigger displaying options.
		'custom_color', 'problem_unack_color', 'problem_unack_style', 'problem_ack_color', 'problem_ack_style',
		'ok_unack_color', 'ok_unack_style', 'ok_ack_color', 'ok_ack_style', 'ok_period', 'blink_period',
		'severity_name_0', 'severity_color_0', 'severity_name_1', 'severity_color_1', 'severity_name_2',
		'severity_color_2', 'severity_name_3', 'severity_color_3', 'severity_name_4', 'severity_color_4',
		'severity_name_5', 'severity_color_5',

		// Geographical maps.
		'geomaps_tile_provider', 'geomaps_tile_url', 'geomaps_attribution', 'geomaps_max_zoom',

		// Other configuration parameters.
		'url', 'discovery_groupid', 'default_inventory_mode', 'alert_usrgrpid', 'snmptrap_logging', 'login_attempts',
		'login_block', 'vault_provider', 'proxy_secrets_provider', 'validate_uri_schemes', 'uri_valid_schemes',
		'x_frame_options', 'iframe_sandboxing_enabled', 'iframe_sandboxing_exceptions',

		// Audit log.
		'auditlog_enabled', 'auditlog_mode',

		// Read-only parameters.
		'ha_failover_delay', 'serverid',

		// APM global configuration.
		'apm_global_db'
	];

	private const APM_GLOBAL_DB_SCHEMA = [
		'status' => [
			'type' => API_INT32,
			'default' => APM_GLOBAL_DB_STATUS_NOT_CONFIGURED
		],
		'url' => [
			'type' => API_URL,
			'length' => 2048,
			'default' => ''
		],
		'authentication_type' => [
			'type' => API_INT32,
			'default' => APM_GLOBAL_DB_AUTHTYPE_PASSWORD
		],
		'username' => [
			'type' => API_STRING_UTF8,
			'length' => 255,
			'default' => ''
		],
		'password' => [
			'type' => API_STRING_UTF8,
			'length' => 255,
			'default' => ''
		],
		'db' => [
			'type' => API_STRING_UTF8,
			'length' => 255,
			'default' => ''
		],
		'ssl_verify_peer' => [
			'type' => API_INT32,
			'default' => APM_GLOBAL_DB_VERIFY_PEER_DISABLED
		],
		'ssl_verify_host' => [
			'type' => API_INT32,
			'default' => APM_GLOBAL_DB_VERIFY_HOST_DISABLED
		]
	];

	/**
	 * @param array $options
	 *
	 * @throws APIException
	 *
	 * @return array
	 */
	public function get(array $options): array {
		$output_fields = self::$userData['type'] != USER_TYPE_SUPER_ADMIN
			? array_diff(self::OUTPUT_FIELDS, ['apm_global_db'])
			: self::OUTPUT_FIELDS;

		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'output' =>	['type' => API_OUTPUT, 'flags' => API_NORMALIZE, 'in' => implode(',', $output_fields), 'default' => API_OUTPUT_EXTEND]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_settings = CApiSettingsHelper::getParameters($options['output']);

		self::prepareApmGlobalDbForApi($db_settings);

		if (array_key_exists('apm_global_db', $db_settings)) {
			unset($db_settings['apm_global_db']['password']);
		}

		return $db_settings;
	}

	private static function prepareApmGlobalDbForApi(array &$settings): void {
		if (!array_key_exists('apm_global_db', $settings)) {
			return;
		}

		$settings['apm_global_db'] = $settings['apm_global_db'] === CSettingsSchema::getDefault('apm_global_db')
			? array_map(static fn(array $field) => $field['default'], self::APM_GLOBAL_DB_SCHEMA)
			: json_decode($settings['apm_global_db'], true);
	}

	/**
	 * Get the fields of the Settings API object that are used by parts of the UI where authentication is not required.
	 */
	public static function getPublic(): array {
		$parameters = CApiSettingsHelper::getParameters([
			// GUI.
			'default_lang', 'default_timezone', 'default_theme', 'server_check_interval', 'show_technical_errors',

			// Trigger displaying options.
			'custom_color', 'problem_unack_color', 'problem_ack_color', 'ok_unack_color', 'ok_ack_color',
			'severity_color_0', 'severity_color_1', 'severity_color_2', 'severity_color_3', 'severity_color_4',
			'severity_color_5',

			// Other configuration parameters.
			'login_attempts', 'login_block', 'x_frame_options',

			// Audit log.
			'auditlog_enabled',

			// Read-only parameters.
			'serverid',

			// APM global configuration.
			'apm_global_db'
		]);

		self::prepareApmGlobalDbForApi($parameters);

		return $parameters;
	}

	/**
	 * Get the private settings used in UI.
	 */
	public static function getPrivate(): array {
		$parameters = CApiSettingsHelper::getParameters([
			'session_key', 'dbversion_status', 'server_status', 'software_update_checkid', 'software_update_check_data'
		]);

		$parameters['dbversion_status'] = json_decode($parameters['dbversion_status'], true) ?: [];
		$parameters['server_status'] = json_decode($parameters['server_status'], true) ?: [];
		$parameters['server_status'] += ['configuration' => [
			'enable_global_scripts' => true,
			'allow_software_update_check' => false,
			'enable_mobile_devices' => false,
			'bridge_adapter_configured' => false
		]];
		$parameters['software_update_check_data'] =
			json_decode($parameters['software_update_check_data'], true) ?: [];

		if ($parameters['software_update_checkid'] !== '') {
			$parameters['software_update_checkid'] = hash('sha256', $parameters['software_update_checkid']);
		}

		return $parameters;
	}

	/**
	 * @param array $settings
	 *
	 * @throws APIException
	 *
	 * @return array
	 */
	public function update(array $settings): array {
		$this->validateUpdate($settings, $db_settings);

		CApiSettingsHelper::updateParameters(array_diff_key($settings, array_flip(['apm_global_db'])), $db_settings);

		self::updateApmGlobalDb($settings, $db_settings);

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_SETTINGS,	[$settings], [$db_settings]);

		return array_keys($settings);
	}

	/**
	 * @param array      $settings
	 * @param array|null $db_settings
	 *
	 * @throws APIException
	 */
	protected function validateUpdate(array &$settings, ?array &$db_settings = null): void {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			// GUI.
			'default_lang' =>					['type' => API_STRING_UTF8, 'in' => implode(',', array_keys(getLocales()))],
			'default_timezone' =>				['type' => API_STRING_UTF8, 'in' => ZBX_DEFAULT_TIMEZONE.','.implode(',', array_keys(CTimezoneHelper::getList()))],
			'default_theme' =>					['type' => API_STRING_UTF8, 'in' => implode(',', array_keys(APP::getThemes()))],
			'search_limit' =>					['type' => API_INT32, 'in' => '1:999999'],
			'max_overview_table_size' =>		['type' => API_INT32, 'in' => '5:999999'],
			'max_in_table' =>					['type' => API_INT32, 'in' => '1:99999'],
			'server_check_interval' =>			['type' => API_INT32, 'in' => '0,'.SERVER_CHECK_INTERVAL],
			'work_period' =>					['type' => API_TIME_PERIOD, 'flags' => API_ALLOW_USER_MACRO, 'length' => CSettingsSchema::getFieldLength('work_period')],
			'show_technical_errors' =>			['type' => API_INT32, 'in' => '0,1'],
			'history_period' =>					['type' => API_TIME_UNIT, 'in' => implode(':', [SEC_PER_DAY, 7 * SEC_PER_DAY])],
			'period_default' =>					['type' => API_TIME_UNIT, 'flags' => API_TIME_UNIT_WITH_YEAR, 'in' => implode(':', [SEC_PER_MIN, 10 * SEC_PER_YEAR])],
			'max_period' =>						['type' => API_TIME_UNIT, 'flags' => API_TIME_UNIT_WITH_YEAR, 'in' => implode(':', [SEC_PER_YEAR, 10 * SEC_PER_YEAR])],

			// Timeouts.
			'timeout_zabbix_agent' =>			['type' => API_TIME_UNIT, 'flags' => API_ALLOW_USER_MACRO, 'in' => '1:600'],
			'timeout_simple_check' =>			['type' => API_TIME_UNIT, 'flags' => API_ALLOW_USER_MACRO, 'in' => '1:600'],
			'timeout_snmp_agent' =>				['type' => API_TIME_UNIT, 'flags' => API_ALLOW_USER_MACRO, 'in' => '1:600'],
			'timeout_external_check' =>			['type' => API_TIME_UNIT, 'flags' => API_ALLOW_USER_MACRO, 'in' => '1:600'],
			'timeout_db_monitor' =>				['type' => API_TIME_UNIT, 'flags' => API_ALLOW_USER_MACRO, 'in' => '1:600'],
			'timeout_http_agent' =>				['type' => API_TIME_UNIT, 'flags' => API_ALLOW_USER_MACRO, 'in' => '1:600'],
			'timeout_ssh_agent' =>				['type' => API_TIME_UNIT, 'flags' => API_ALLOW_USER_MACRO, 'in' => '1:600'],
			'timeout_telnet_agent' =>			['type' => API_TIME_UNIT, 'flags' => API_ALLOW_USER_MACRO, 'in' => '1:600'],
			'timeout_script' =>					['type' => API_TIME_UNIT, 'flags' => API_ALLOW_USER_MACRO, 'in' => '1:600'],
			'timeout_browser' =>				['type' => API_TIME_UNIT, 'flags' => API_ALLOW_USER_MACRO, 'in' => '1:600'],
			'timeout_telemetry_query' =>		['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO, 'in' => '1:600'],
			'socket_timeout' =>					['type' => API_TIME_UNIT, 'in' => '1:300'],
			'connect_timeout' =>				['type' => API_TIME_UNIT, 'in' => '1:30'],
			'media_type_test_timeout' =>		['type' => API_TIME_UNIT, 'in' => '1:300'],
			'script_timeout' =>					['type' => API_TIME_UNIT, 'in' => '1:300'],
			'item_test_timeout' =>				['type' => API_TIME_UNIT, 'in' => '1:600'],
			'report_test_timeout' =>			['type' => API_TIME_UNIT, 'in' => '1:300'],
			'device_link_timeout' =>			['type' => API_TIME_UNIT, 'in' => '1:300'],

			// Trigger displaying options.
			'custom_color' =>					['type' => API_INT32, 'in' => EVENT_CUSTOM_COLOR_DISABLED.','.EVENT_CUSTOM_COLOR_ENABLED],
			'problem_unack_color' =>			['type' => API_COLOR, 'flags' => API_NOT_EMPTY],
			'problem_unack_style' =>			['type' => API_INT32, 'in' => '0,1'],
			'problem_ack_color' =>				['type' => API_COLOR, 'flags' => API_NOT_EMPTY],
			'problem_ack_style' =>				['type' => API_INT32, 'in' => '0,1'],
			'ok_unack_color' =>					['type' => API_COLOR, 'flags' => API_NOT_EMPTY],
			'ok_unack_style' =>					['type' => API_INT32, 'in' => '0,1'],
			'ok_ack_color' =>					['type' => API_COLOR, 'flags' => API_NOT_EMPTY],
			'ok_ack_style' =>					['type' => API_INT32, 'in' => '0,1'],
			'ok_period' =>						['type' => API_TIME_UNIT, 'in' => implode(':', [0, SEC_PER_DAY])],
			'blink_period' =>					['type' => API_TIME_UNIT, 'in' => implode(':', [0, SEC_PER_DAY])],
			'severity_name_0' =>				['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => CSettingsSchema::getFieldLength('severity_name_0')],
			'severity_color_0' =>				['type' => API_COLOR, 'flags' => API_NOT_EMPTY],
			'severity_name_1' =>				['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => CSettingsSchema::getFieldLength('severity_name_1')],
			'severity_color_1' =>				['type' => API_COLOR, 'flags' => API_NOT_EMPTY],
			'severity_name_2' =>				['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => CSettingsSchema::getFieldLength('severity_name_2')],
			'severity_color_2' =>				['type' => API_COLOR, 'flags' => API_NOT_EMPTY],
			'severity_name_3' =>				['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => CSettingsSchema::getFieldLength('severity_name_3')],
			'severity_color_3' =>				['type' => API_COLOR, 'flags' => API_NOT_EMPTY],
			'severity_name_4' =>				['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => CSettingsSchema::getFieldLength('severity_name_4')],
			'severity_color_4' =>				['type' => API_COLOR, 'flags' => API_NOT_EMPTY],
			'severity_name_5' =>				['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => CSettingsSchema::getFieldLength('severity_name_5')],
			'severity_color_5' =>				['type' => API_COLOR, 'flags' => API_NOT_EMPTY],

			// Geographical maps.
			'geomaps_tile_provider' =>			['type' => API_STRING_UTF8, 'in' => ','.implode(',', array_keys(getTileProviders()))],
			'geomaps_tile_url' =>				['type' => API_URL, 'length' => CSettingsSchema::getFieldLength('geomaps_tile_url')],
			'geomaps_attribution' =>			['type' => API_STRING_UTF8, 'length' => CSettingsSchema::getFieldLength('geomaps_attribution')],
			'geomaps_max_zoom' =>				['type' => API_INT32, 'in' => '0:'.ZBX_GEOMAP_MAX_ZOOM],

			// Other configuration parameters.
			'url' =>							['type' => API_STRING_UTF8, 'length' => CSettingsSchema::getFieldLength('url')],
			'discovery_groupid' =>				['type' => API_ID],
			'default_inventory_mode' =>			['type' => API_INT32, 'in' => HOST_INVENTORY_DISABLED.','.HOST_INVENTORY_MANUAL.','.HOST_INVENTORY_AUTOMATIC],
			'alert_usrgrpid' =>					['type' => API_ID],
			'snmptrap_logging' =>				['type' => API_INT32, 'in' => '0,1'],
			'login_attempts' =>					['type' => API_INT32, 'in' => '1:32'],
			'login_block' =>					['type' => API_TIME_UNIT, 'in' => implode(':', [30, SEC_PER_HOUR])],
			'vault_provider' =>					['type' => API_INT32, 'in' => ZBX_VAULT_TYPE_HASHICORP.','.ZBX_VAULT_TYPE_CYBERARK],
			'proxy_secrets_provider' =>			['type' => API_INT32, 'in' => ZBX_PROXY_SECRETS_PROVIDER_SERVER.','.ZBX_PROXY_SECRETS_PROVIDER_PROXY],
			'validate_uri_schemes' =>			['type' => API_INT32, 'in' => '0,1'],
			'uri_valid_schemes' =>				['type' => API_STRING_UTF8, 'length' => CSettingsSchema::getFieldLength('uri_valid_schemes')],
			'x_frame_options' =>				['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => CSettingsSchema::getFieldLength('x_frame_options')],
			'iframe_sandboxing_enabled' =>		['type' => API_INT32, 'in' => '0,1'],
			'iframe_sandboxing_exceptions' =>	['type' => API_STRING_UTF8, 'length' => CSettingsSchema::getFieldLength('iframe_sandboxing_exceptions')],

			// Audit log.
			'auditlog_enabled' =>				['type' => API_INT32, 'in' => '0,1'],
			'auditlog_mode' =>					['type' => API_INT32, 'in' => '0,1'],

			// APM global configuration.
			'apm_global_db' =>					['type' => API_ANY]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $settings, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		if (array_key_exists('discovery_groupid', $settings)) {
			$db_hstgrp_exists = API::HostGroup()->get([
				'countOutput' => true,
				'groupids' => $settings['discovery_groupid'],
				'filter' => ['flags' => ZBX_FLAG_DISCOVERY_NORMAL],
				'editable' => true
			]);

			if (!$db_hstgrp_exists) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Host group with ID "%1$s" is not available.', $settings['discovery_groupid'])
				);
			}
		}

		if (array_key_exists('alert_usrgrpid', $settings) && $settings['alert_usrgrpid'] != 0) {
			$db_usrgrp_exists = API::UserGroup()->get([
				'countOutput' => true,
				'usrgrpids' => $settings['alert_usrgrpid']
			]);

			if (!$db_usrgrp_exists) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('User group with ID "%1$s" is not available.', $settings['alert_usrgrpid'])
				);
			}
		}

		if (array_key_exists('geomaps_tile_provider', $settings) && $settings['geomaps_tile_provider'] !== '') {
			$settings['geomaps_tile_url'] = CSettingsSchema::getDefault('geomaps_tile_url');
			$settings['geomaps_max_zoom'] = CSettingsSchema::getDefault('geomaps_max_zoom');
			$settings['geomaps_attribution'] = CSettingsSchema::getDefault('geomaps_attribution');
		}

		$period_default_updated = array_key_exists('period_default', $settings);
		$max_period_updated = array_key_exists('max_period', $settings);

		if ($period_default_updated || $max_period_updated) {
			$period_default = $period_default_updated
				? timeUnitToSeconds($settings['period_default'], true)
				: timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::PERIOD_DEFAULT), true);

			$max_period = $max_period_updated
				? timeUnitToSeconds($settings['max_period'], true)
				: timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::MAX_PERIOD), true);

			if ($period_default > $max_period) {
				$field = 'period_default';
				$message = _('time filter default period exceeds the max period');

				if (!$period_default_updated) {
					$field = 'max_period';
					$message = _('max period is less than time filter default period');
				}

				$error = _s('Incorrect value for field "%1$s": %2$s.', $field, $message);

				self::exception(ZBX_API_ERROR_PARAMETERS, $error);
			}
		}

		$db_settings = CApiSettingsHelper::getParameters(self::OUTPUT_FIELDS, false);

		CApiSettingsHelper::checkUndeclaredParameters($settings, $db_settings);

		self::prepareApmGlobalDbForApi($db_settings);

		self::validateApmGlobalDb($settings, $db_settings);
	}

	private static function validateApmGlobalDb(array &$settings, array $db_settings): void {
		if (!array_key_exists('apm_global_db', $settings)) {
			return;
		}

		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'status' =>					['type' => API_INT32, 'in' => implode(',', [APM_GLOBAL_DB_STATUS_NOT_CONFIGURED, APM_GLOBAL_DB_STATUS_CONFIGURED])],
			'url' =>					['type' => API_ANY],
			'authentication_type' =>	['type' => API_ANY],
			'username' =>				['type' => API_ANY],
			'password' =>				['type' => API_ANY],
			'db' =>						['type' => API_ANY],
			'ssl_verify_peer' =>		['type' => API_ANY],
			'ssl_verify_host' =>		['type' => API_ANY]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $settings['apm_global_db'], '/apm_global_db', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$apm_global_db = &$settings['apm_global_db'];
		$db_apm_global_db = $db_settings['apm_global_db'];

		$apm_global_db += ['status' => $db_apm_global_db['status']];

		if ($apm_global_db['status'] == APM_GLOBAL_DB_STATUS_NOT_CONFIGURED) {
			self::validateNotConfiguredApmGlobalDb($apm_global_db);

			return;
		}

		$api_input_rules = ['type' => API_OBJECT, 'flags' => API_ALLOW_UNEXPECTED, 'fields' => [
			'url' =>					['type' => API_URL, 'flags' => API_NOT_EMPTY, 'schemes' => [CSettingsHelper::APM_GLOBAL_DB_URL_SCHEMA_HTTP, CSettingsHelper::APM_GLOBAL_DB_URL_SCHEMA_HTTPS], 'length' => self::APM_GLOBAL_DB_SCHEMA['url']['length']],
			'authentication_type' =>	['type' => API_INT32, 'in' => implode(',', [APM_GLOBAL_DB_AUTHTYPE_PASSWORD, APM_GLOBAL_DB_AUTHTYPE_NONE])],
			'db' =>						['type' => API_STRING_UTF8, 'length' => self::APM_GLOBAL_DB_SCHEMA['db']['length']],
		]];

		if (!CApiInputValidator::validate($api_input_rules, $apm_global_db, '/apm_global_db', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$apm_global_db += array_intersect_key($db_apm_global_db, array_flip(['url', 'authentication_type']));

		$username_api_required = 0;
		$password_api_required = 0;

		if ($apm_global_db['url'] != $db_apm_global_db['url']) {
			$password_api_required = API_REQUIRED;
		}

		if ($apm_global_db['status'] != $db_apm_global_db['status']
				|| ($apm_global_db['authentication_type'] != $db_apm_global_db['authentication_type']
					&& $apm_global_db['authentication_type'] == APM_GLOBAL_DB_AUTHTYPE_PASSWORD)) {
			$username_api_required = API_REQUIRED;
			$password_api_required = API_REQUIRED;
		}

		$url_scheme = parse_url($apm_global_db['url'], PHP_URL_SCHEME);

		$api_input_rules = ['type' => API_OBJECT, 'flags' => API_ALLOW_UNEXPECTED, 'fields' => [
			'username' =>			$apm_global_db['authentication_type'] == APM_GLOBAL_DB_AUTHTYPE_PASSWORD
										? ['type' => API_STRING_UTF8, 'flags' => $username_api_required | API_NOT_EMPTY, 'length' => self::APM_GLOBAL_DB_SCHEMA['username']['length']]
										: ['type' => API_STRING_UTF8, 'in' => self::APM_GLOBAL_DB_SCHEMA['username']['default']],
			'password' =>			$apm_global_db['authentication_type'] == APM_GLOBAL_DB_AUTHTYPE_PASSWORD
										? ['type' => API_STRING_UTF8, 'flags' => $password_api_required, 'length' => self::APM_GLOBAL_DB_SCHEMA['password']['length']]
										: ['type' => API_STRING_UTF8, 'in' => self::APM_GLOBAL_DB_SCHEMA['password']['default']],
			'ssl_verify_peer' =>	$url_scheme === CSettingsHelper::APM_GLOBAL_DB_URL_SCHEMA_HTTPS
										? ['type' => API_INT32, 'in' => implode(',', [APM_GLOBAL_DB_VERIFY_PEER_DISABLED, APM_GLOBAL_DB_VERIFY_PEER_ENABLED])]
										: ['type' => API_INT32, 'in' => self::APM_GLOBAL_DB_SCHEMA['ssl_verify_peer']['default']]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $apm_global_db, '/apm_global_db', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$apm_global_db_default = array_map(static fn(array $field) => $field['default'], self::APM_GLOBAL_DB_SCHEMA);

		$apm_global_db += $url_scheme === CSettingsHelper::APM_GLOBAL_DB_URL_SCHEMA_HTTPS
			? ['ssl_verify_peer' => $db_apm_global_db['ssl_verify_peer']]
			: ['ssl_verify_peer' => $apm_global_db_default['ssl_verify_peer']];

		$api_input_rules = ['type' => API_OBJECT, 'flags' => API_ALLOW_UNEXPECTED, 'fields' => [
			'ssl_verify_host' =>	$apm_global_db['ssl_verify_peer'] == APM_GLOBAL_DB_VERIFY_PEER_ENABLED
										? ['type' => API_INT32, 'in' => implode(',', [APM_GLOBAL_DB_VERIFY_HOST_DISABLED, APM_GLOBAL_DB_VERIFY_HOST_ENABLED])]
										: ['type' => API_INT32, 'in' => self::APM_GLOBAL_DB_SCHEMA['ssl_verify_host']['default']]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $apm_global_db, '/apm_global_db', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}
	}

	private static function validateNotConfiguredApmGlobalDb(array &$apm_global_db):void {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'status' =>	['type' => API_ANY],
			'url' => ['type' => API_STRING_UTF8, 'in' => self::APM_GLOBAL_DB_SCHEMA['url']['default']],
			'authentication_type' => ['type' => API_INT32, 'in' => self::APM_GLOBAL_DB_SCHEMA['authentication_type']['default']],
			'username' => ['type' => API_STRING_UTF8, 'in' => self::APM_GLOBAL_DB_SCHEMA['username']['default']],
			'password' => ['type' => API_STRING_UTF8, 'in' => self::APM_GLOBAL_DB_SCHEMA['password']['default']],
			'db' => ['type' => API_STRING_UTF8, 'in' => self::APM_GLOBAL_DB_SCHEMA['db']['default']],
			'ssl_verify_peer' => ['type' => API_INT32, 'in' => self::APM_GLOBAL_DB_SCHEMA['ssl_verify_peer']['default']],
			'ssl_verify_host' => ['type' => API_INT32, 'in' => self::APM_GLOBAL_DB_SCHEMA['ssl_verify_host']['default']]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $apm_global_db, '/apm_global_db', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}
	}

	private static function updateApmGlobalDb(array &$settings, array $db_settings): void {
		if (!array_key_exists('apm_global_db', $settings)) {
			return;
		}

		$apm_global_db_defaults = array_map(static fn(array $field) => $field['default'], self::APM_GLOBAL_DB_SCHEMA);

		if ($settings['apm_global_db']['status'] == APM_GLOBAL_DB_STATUS_NOT_CONFIGURED) {
			if ($settings['apm_global_db']['status'] !== $db_settings['apm_global_db']['status']) {
				CApiSettingsHelper::updateParameters(
					['apm_global_db' =>  CSettingsSchema::getDefault('apm_global_db')],
					['apm_global_db' => json_encode($db_settings['apm_global_db'], JSON_UNESCAPED_UNICODE)]
				);

				$settings['apm_global_db'] += $apm_global_db_defaults;
			}

			return;
		}

		$url_scheme = parse_url($settings['apm_global_db']['url'], PHP_URL_SCHEME);
		$db_url_scheme = parse_url($db_settings['apm_global_db']['url'], PHP_URL_SCHEME);

		if ($url_scheme !== $db_url_scheme && $db_url_scheme === CSettingsHelper::APM_GLOBAL_DB_URL_SCHEMA_HTTPS) {
			$settings['apm_global_db'] +=
				array_intersect_key($apm_global_db_defaults, array_flip(['ssl_verify_peer', 'ssl_verify_host']));
		}

		if ($settings['apm_global_db']['authentication_type'] != $db_settings['apm_global_db']['authentication_type']
				&& $db_settings['apm_global_db']['authentication_type'] == APM_GLOBAL_DB_AUTHTYPE_PASSWORD) {
			$settings['apm_global_db'] +=
				array_intersect_key($apm_global_db_defaults, array_flip(['username', 'password']));
		}

		if ($settings['apm_global_db']['ssl_verify_peer'] != $db_settings['apm_global_db']['ssl_verify_peer']
				&& $db_settings['apm_global_db']['ssl_verify_peer'] == APM_GLOBAL_DB_VERIFY_PEER_ENABLED) {
			$settings['apm_global_db'] += ['ssl_verify_host' => $apm_global_db_defaults['ssl_verify_host']];
		}

		$apm_global_db =
			json_encode(array_merge($db_settings['apm_global_db'], $settings['apm_global_db']), JSON_UNESCAPED_UNICODE);

		$db_apm_global_db = $db_settings['apm_global_db']['status'] == APM_GLOBAL_DB_STATUS_NOT_CONFIGURED
			? CSettingsSchema::getDefault('apm_global_db')
			: json_encode($db_settings['apm_global_db'], JSON_UNESCAPED_UNICODE);

		CApiSettingsHelper::updateParameters(['apm_global_db' => $apm_global_db],
			['apm_global_db' => $db_apm_global_db]
		);
	}

	public static function updatePrivate(array $settings): array {
		$settings['software_update_check_data'] = json_encode($settings['software_update_check_data']);

		CApiSettingsHelper::updateParameters($settings);

		return array_keys($settings);
	}
}
