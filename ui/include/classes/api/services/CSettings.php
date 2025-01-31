<?php declare(strict_types = 0);
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
 * Class containing methods for operations with the main part of administration settings.
 */
class CSettings extends CApiService {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_ZABBIX_USER],
		'update' => ['min_user_type' => USER_TYPE_SUPER_ADMIN]
	];

	private array $output_fields = ['default_theme', 'search_limit', 'max_in_table', 'server_check_interval',
		'work_period', 'show_technical_errors', 'history_period', 'period_default', 'max_period', 'severity_color_0',
		'severity_color_1', 'severity_color_2', 'severity_color_3', 'severity_color_4', 'severity_color_5',
		'severity_name_0', 'severity_name_1', 'severity_name_2', 'severity_name_3', 'severity_name_4',
		'severity_name_5', 'custom_color', 'ok_period', 'blink_period', 'problem_unack_color', 'problem_ack_color',
		'ok_unack_color', 'ok_ack_color', 'problem_unack_style', 'problem_ack_style', 'ok_unack_style', 'ok_ack_style',
		'discovery_groupid', 'default_inventory_mode', 'alert_usrgrpid', 'snmptrap_logging', 'default_lang',
		'default_timezone', 'login_attempts', 'login_block', 'validate_uri_schemes', 'uri_valid_schemes',
		'x_frame_options', 'iframe_sandboxing_enabled', 'iframe_sandboxing_exceptions', 'max_overview_table_size',
		'connect_timeout', 'socket_timeout', 'media_type_test_timeout', 'script_timeout', 'item_test_timeout', 'url',
		'report_test_timeout', 'auditlog_enabled', 'auditlog_mode', 'ha_failover_delay', 'geomaps_tile_provider',
		'geomaps_tile_url', 'geomaps_max_zoom', 'geomaps_attribution', 'vault_provider', 'timeout_zabbix_agent',
		'timeout_simple_check', 'timeout_snmp_agent', 'timeout_external_check', 'timeout_db_monitor',
		'timeout_http_agent', 'timeout_ssh_agent', 'timeout_telnet_agent', 'timeout_script', 'timeout_browser', 'proxy_secrets_provider'
	];

	/**
	 * @param array $options
	 *
	 * @throws APIException
	 *
	 * @return array
	 */
	public function get(array $options): array {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'output' =>	['type' => API_OUTPUT, 'in' => implode(',', $this->output_fields), 'default' => API_OUTPUT_EXTEND]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		if ($options['output'] === API_OUTPUT_EXTEND) {
			$options['output'] = $this->output_fields;
		}

		return CApiSettingsHelper::getParameters($options['output']);
	}

	/**
	 * Get the fields of the Settings API object that are used by parts of the UI where authentication is not required.
	 */
	public static function getPublic(): array {
		return CApiSettingsHelper::getParameters([
			'default_theme', 'server_check_interval', 'show_technical_errors', 'severity_color_0', 'severity_color_1',
			'severity_color_2', 'severity_color_3', 'severity_color_4', 'severity_color_5', 'custom_color',
			'problem_unack_color', 'problem_ack_color', 'ok_unack_color', 'ok_ack_color', 'default_lang',
			'default_timezone', 'login_attempts', 'login_block', 'x_frame_options', 'auditlog_enabled'
		]);
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
			'allow_software_update_check' => false
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

		CApiSettingsHelper::updateParameters($settings, $db_settings);

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_SETTINGS,	[$settings], [$db_settings]);

		return array_keys($settings);
	}

	/**
	 * @param array      $settings
	 * @param array|null $db_settings
	 *
	 * @throws APIException
	 */
	protected function validateUpdate(array &$settings, ?array &$db_settings): void {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'default_theme' =>					['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'in' => implode(',', array_keys(APP::getThemes())), 'length' => CSettingsSchema::getFieldLength('default_theme')],
			'search_limit' =>					['type' => API_INT32, 'in' => '1:999999'],
			'max_in_table' =>					['type' => API_INT32, 'in' => '1:99999'],
			'server_check_interval' =>			['type' => API_INT32, 'in' => '0,'.SERVER_CHECK_INTERVAL],
			'work_period' =>					['type' => API_TIME_PERIOD, 'flags' => API_ALLOW_USER_MACRO, 'length' => CSettingsSchema::getFieldLength('work_period')],
			'show_technical_errors' =>			['type' => API_INT32, 'in' => '0,1'],
			'history_period' =>					['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY, 'in' => implode(':', [SEC_PER_DAY, 7 * SEC_PER_DAY])],
			'period_default' =>					['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_TIME_UNIT_WITH_YEAR, 'in' => implode(':', [SEC_PER_MIN, 10 * SEC_PER_YEAR]), 'length' => CSettingsSchema::getFieldLength('period_default')],
			'max_period' =>						['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_TIME_UNIT_WITH_YEAR, 'in' => implode(':', [SEC_PER_YEAR, 10 * SEC_PER_YEAR]), 'length' => CSettingsSchema::getFieldLength('period_default')],
			'severity_color_0' =>				['type' => API_COLOR, 'flags' => API_NOT_EMPTY, 'length' => CSettingsSchema::getFieldLength('severity_color_0')],
			'severity_color_1' =>				['type' => API_COLOR, 'flags' => API_NOT_EMPTY, 'length' => CSettingsSchema::getFieldLength('severity_color_1')],
			'severity_color_2' =>				['type' => API_COLOR, 'flags' => API_NOT_EMPTY, 'length' => CSettingsSchema::getFieldLength('severity_color_2')],
			'severity_color_3' =>				['type' => API_COLOR, 'flags' => API_NOT_EMPTY, 'length' => CSettingsSchema::getFieldLength('severity_color_3')],
			'severity_color_4' =>				['type' => API_COLOR, 'flags' => API_NOT_EMPTY, 'length' => CSettingsSchema::getFieldLength('severity_color_4')],
			'severity_color_5' =>				['type' => API_COLOR, 'flags' => API_NOT_EMPTY, 'length' => CSettingsSchema::getFieldLength('severity_color_5')],
			'severity_name_0' =>				['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => CSettingsSchema::getFieldLength('severity_name_0')],
			'severity_name_1' =>				['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => CSettingsSchema::getFieldLength('severity_name_1')],
			'severity_name_2' =>				['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => CSettingsSchema::getFieldLength('severity_name_2')],
			'severity_name_3' =>				['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => CSettingsSchema::getFieldLength('severity_name_3')],
			'severity_name_4' =>				['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => CSettingsSchema::getFieldLength('severity_name_4')],
			'severity_name_5' =>				['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => CSettingsSchema::getFieldLength('severity_name_5')],
			'custom_color' =>					['type' => API_INT32, 'in' => EVENT_CUSTOM_COLOR_DISABLED.','.EVENT_CUSTOM_COLOR_ENABLED],
			'ok_period' =>						['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY, 'in' => implode(':', [0, SEC_PER_DAY]), 'length' => CSettingsSchema::getFieldLength('ok_period')],
			'blink_period' =>					['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY, 'in' => implode(':', [0, SEC_PER_DAY]), 'length' => CSettingsSchema::getFieldLength('blink_period')],
			'problem_unack_color' =>			['type' => API_COLOR, 'flags' => API_NOT_EMPTY, 'length' => CSettingsSchema::getFieldLength('problem_unack_color')],
			'problem_ack_color' =>				['type' => API_COLOR, 'flags' => API_NOT_EMPTY, 'length' => CSettingsSchema::getFieldLength('problem_ack_color')],
			'ok_unack_color' =>					['type' => API_COLOR, 'flags' => API_NOT_EMPTY, 'length' => CSettingsSchema::getFieldLength('ok_unack_color')],
			'ok_ack_color' =>					['type' => API_COLOR, 'flags' => API_NOT_EMPTY, 'length' => CSettingsSchema::getFieldLength('ok_ack_color')],
			'problem_unack_style' =>			['type' => API_INT32, 'in' => '0,1'],
			'problem_ack_style' =>				['type' => API_INT32, 'in' => '0,1'],
			'ok_unack_style' =>					['type' => API_INT32, 'in' => '0,1'],
			'ok_ack_style' =>					['type' => API_INT32, 'in' => '0,1'],
			'discovery_groupid' =>				['type' => API_ID],
			'default_inventory_mode' =>			['type' => API_INT32, 'in' => HOST_INVENTORY_DISABLED.','.HOST_INVENTORY_MANUAL.','.HOST_INVENTORY_AUTOMATIC],
			'alert_usrgrpid' =>					['type' => API_ID],
			'snmptrap_logging' =>				['type' => API_INT32, 'in' => '0,1'],
			'default_lang' =>					['type' => API_STRING_UTF8, 'in' => implode(',', array_keys(getLocales())), 'length' => CSettingsSchema::getFieldLength('default_lang')],
			'default_timezone' =>				['type' => API_STRING_UTF8, 'in' => ZBX_DEFAULT_TIMEZONE.','.implode(',', array_keys(CTimezoneHelper::getList())), 'length' => CSettingsSchema::getFieldLength('default_timezone')],
			'login_attempts' =>					['type' => API_INT32, 'in' => '1:32'],
			'login_block' =>					['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY, 'in' => implode(':', [30, SEC_PER_HOUR]), 'length' => CSettingsSchema::getFieldLength('login_block')],
			'validate_uri_schemes' =>			['type' => API_INT32, 'in' => '0,1'],
			'uri_valid_schemes' =>				['type' => API_STRING_UTF8, 'length' => CSettingsSchema::getFieldLength('uri_valid_schemes')],
			'x_frame_options' =>				['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => CSettingsSchema::getFieldLength('x_frame_options')],
			'iframe_sandboxing_enabled' =>		['type' => API_INT32, 'in' => '0,1'],
			'iframe_sandboxing_exceptions' =>	['type' => API_STRING_UTF8, 'length' => CSettingsSchema::getFieldLength('iframe_sandboxing_exceptions')],
			'max_overview_table_size' =>		['type' => API_INT32, 'in' => '5:999999'],
			'connect_timeout' =>				['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY, 'in' => '1:30', 'length' => CSettingsSchema::getFieldLength('connect_timeout')],
			'socket_timeout' =>					['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY, 'in' => '1:300', 'length' => CSettingsSchema::getFieldLength('socket_timeout')],
			'media_type_test_timeout' =>		['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY, 'in' => '1:300', 'length' => CSettingsSchema::getFieldLength('media_type_test_timeout')],
			'script_timeout' =>					['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY, 'in' => '1:300', 'length' => CSettingsSchema::getFieldLength('script_timeout')],
			'item_test_timeout' =>				['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY, 'in' => '1:600', 'length' => CSettingsSchema::getFieldLength('item_test_timeout')],
			'url' =>							['type' => API_STRING_UTF8, 'length' => CSettingsSchema::getFieldLength('url')],
			'report_test_timeout' =>			['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY, 'in' => '1:300', 'length' => CSettingsSchema::getFieldLength('url')],
			'auditlog_enabled' =>				['type' => API_INT32, 'in' => '0,1'],
			'auditlog_mode' =>					['type' => API_INT32, 'in' => '0,1'],
			'geomaps_tile_provider' =>			['type' => API_STRING_UTF8, 'in' => ','.implode(',', array_keys(getTileProviders())), 'length' => CSettingsSchema::getFieldLength('geomaps_tile_provider')],
			'geomaps_tile_url' =>				['type' => API_URL, 'length' => CSettingsSchema::getFieldLength('geomaps_tile_url')],
			'geomaps_max_zoom' =>				['type' => API_INT32, 'in' => '0:'.ZBX_GEOMAP_MAX_ZOOM],
			'geomaps_attribution' =>			['type' => API_STRING_UTF8, 'length' => CSettingsSchema::getFieldLength('geomaps_attribution')],
			'vault_provider' =>					['type' => API_INT32, 'flags' => API_NOT_EMPTY, 'in' => ZBX_VAULT_TYPE_HASHICORP.','.ZBX_VAULT_TYPE_CYBERARK],
			'proxy_secrets_provider' => 			['type' => API_INT32, 'in' => ZBX_PROXY_SECRETS_PROVIDER_SERVER.','.ZBX_PROXY_SECRETS_PROVIDER_PROXY],
			'timeout_zabbix_agent' =>			['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO, 'in' => '1:600', 'length' => CSettingsSchema::getFieldLength('timeout_zabbix_agent')],
			'timeout_simple_check' =>			['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO, 'in' => '1:600', 'length' => CSettingsSchema::getFieldLength('timeout_simple_check')],
			'timeout_snmp_agent' =>				['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO, 'in' => '1:600', 'length' => CSettingsSchema::getFieldLength('timeout_snmp_agent')],
			'timeout_external_check' =>			['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO, 'in' => '1:600', 'length' => CSettingsSchema::getFieldLength('timeout_external_check')],
			'timeout_db_monitor' =>				['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO, 'in' => '1:600', 'length' => CSettingsSchema::getFieldLength('timeout_db_monitor')],
			'timeout_http_agent' =>				['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO, 'in' => '1:600', 'length' => CSettingsSchema::getFieldLength('timeout_http_agent')],
			'timeout_ssh_agent' =>				['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO, 'in' => '1:600', 'length' => CSettingsSchema::getFieldLength('timeout_ssh_agent')],
			'timeout_telnet_agent' =>			['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO, 'in' => '1:600', 'length' => CSettingsSchema::getFieldLength('timeout_telnet_agent')],
			'timeout_script' =>					['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO, 'in' => '1:600', 'length' => CSettingsSchema::getFieldLength('timeout_script')],
			'timeout_browser' =>				['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO, 'in' => '1:600', 'length' => CSettingsSchema::getFieldLength('timeout_browser')]
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

		$db_settings = CApiSettingsHelper::getParameters($this->output_fields, false);

		CApiSettingsHelper::checkUndeclaredParameters($settings, $db_settings);
	}

	public static function updatePrivate(array $settings): bool {
		$json_fields = ['dbversion_status', 'server_status', 'software_update_check_data'];

		foreach ($json_fields as $json_field) {
			if (array_key_exists($json_field, $settings) && is_array($settings[$json_field])) {
				$settings[$json_field] = json_encode($settings[$json_field]);
			}
		}

		CApiSettingsHelper::updateParameters($settings);

		return true;
	}
}
