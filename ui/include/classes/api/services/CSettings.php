<?php declare(strict_types = 1);
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
 * Class containing methods for operations with the main part of administration settings.
 */
class CSettings extends CApiService {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_ZABBIX_USER],
		'update' => ['min_user_type' => USER_TYPE_SUPER_ADMIN]
	];

	protected $tableName = 'config';
	protected $tableAlias = 'c';

	/**
	 * @var array
	 */
	private $output_fields = ['default_theme', 'search_limit', 'max_in_table', 'server_check_interval', 'work_period',
		'show_technical_errors', 'history_period', 'period_default', 'max_period', 'severity_color_0',
		'severity_color_1', 'severity_color_2', 'severity_color_3', 'severity_color_4', 'severity_color_5',
		'severity_name_0', 'severity_name_1', 'severity_name_2', 'severity_name_3', 'severity_name_4',
		'severity_name_5', 'custom_color', 'ok_period', 'blink_period', 'problem_unack_color', 'problem_ack_color',
		'ok_unack_color', 'ok_ack_color', 'problem_unack_style', 'problem_ack_style', 'ok_unack_style',
		'ok_ack_style', 'discovery_groupid', 'default_inventory_mode', 'alert_usrgrpid',
		'snmptrap_logging', 'default_lang', 'default_timezone', 'login_attempts', 'login_block', 'validate_uri_schemes',
		'uri_valid_schemes', 'x_frame_options', 'iframe_sandboxing_enabled', 'iframe_sandboxing_exceptions',
		'max_overview_table_size', 'connect_timeout', 'socket_timeout', 'media_type_test_timeout', 'script_timeout',
		'item_test_timeout', 'url', 'report_test_timeout', 'auditlog_enabled', 'ha_failover_delay',
		'geomaps_tile_provider', 'geomaps_tile_url', 'geomaps_max_zoom', 'geomaps_attribution'
	];

	/**
	 * @param array $options
	 *
	 * @throws APIException if the input is invalid.
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

		$db_settings = [];

		$result = DBselect($this->createSelectQuery($this->tableName(), $options));
		while ($row = DBfetch($result)) {
			$db_settings[] = $row;
		}
		$db_settings = $this->unsetExtraFields($db_settings, ['configid'], []);

		return $db_settings[0];
	}

	/**
	 * @param array $options
	 * @param bool  $api_call  Flag indicating whether this method called via an API call or from a local PHP file.
	 *
	 * @throws APIException if the input is invalid.
	 *
	 * @return array
	 */
	public function getGlobal(array $options, bool $api_call = true): array {
		if ($api_call) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Incorrect method "%1$s.%2$s".', 'settings', 'getglobal')
			);
		}

		$output_fields = ['default_theme', 'show_technical_errors', 'severity_color_0', 'severity_color_1',
			'severity_color_2', 'severity_color_3', 'severity_color_4', 'severity_color_5', 'custom_color',
			'problem_unack_color', 'problem_ack_color', 'ok_unack_color', 'ok_ack_color', 'default_lang',
			'x_frame_options', 'default_timezone', 'session_key', 'dbversion_status'
		];
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'output' =>	['type' => API_OUTPUT, 'in' => implode(',', $output_fields), 'default' => API_OUTPUT_EXTEND]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		if ($options['output'] === API_OUTPUT_EXTEND) {
			$options['output'] = $output_fields;
		}

		$db_settings = [];

		$result = DBselect($this->createSelectQuery($this->tableName(), $options));
		while ($row = DBfetch($result)) {
			$db_settings[] = $row;
		}
		$db_settings = $this->unsetExtraFields($db_settings, ['configid'], []);

		return $db_settings[0];
	}

	/**
	 * @param array $settings
	 *
	 * @throws APIException if the input is invalid.
	 *
	 * @return array
	 */
	public function update(array $settings): array {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS,
				_s('No permissions to call "%1$s.%2$s".', 'settings', __FUNCTION__)
			);
		}

		$db_settings = $this->validateUpdate($settings);

		$upd_config = DB::getUpdatedValues('config', $settings, $db_settings);

		if ($upd_config) {
			DB::update('config', [
				'values' => $upd_config,
				'where' => ['configid' => $db_settings['configid']]
			]);

			if (array_key_exists('discovery_groupid', $upd_config)
					&& bccomp($upd_config['discovery_groupid'], $db_settings['discovery_groupid']) != 0) {
				$this->setHostGroupInternal($db_settings['discovery_groupid'], ZBX_NOT_INTERNAL_GROUP);
				$this->setHostGroupInternal($upd_config['discovery_groupid'], ZBX_INTERNAL_GROUP);
			}
		}

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_SETTINGS,
			[['configid' => $db_settings['configid']] + $settings], [$db_settings['configid'] => $db_settings]
		);

		return array_keys($settings);
	}

	/**
	 * @param array $settings
	 *
	 * @throws APIException if the input is invalid.
	 *
	 * @return array
	 */
	protected function validateUpdate(array &$settings): array {
		$api_input_rules = ['type' => API_OBJECT, 'flags' => API_NOT_EMPTY, 'fields' => [
			'default_theme' =>					['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'in' => implode(',', array_keys(APP::getThemes()))],
			'search_limit' =>					['type' => API_INT32, 'in' => '1:999999'],
			'max_in_table' =>					['type' => API_INT32, 'in' => '1:99999'],
			'server_check_interval' =>			['type' => API_INT32, 'in' => '0,'.SERVER_CHECK_INTERVAL],
			'work_period' =>					['type' => API_TIME_PERIOD, 'flags' => API_ALLOW_USER_MACRO],
			'show_technical_errors' =>			['type' => API_INT32, 'in' => '0,1'],
			'history_period' =>					['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY, 'in' => implode(':', [SEC_PER_DAY, 7 * SEC_PER_DAY])],
			'period_default' =>					['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_TIME_UNIT_WITH_YEAR, 'in' => implode(':', [SEC_PER_MIN, 10 * SEC_PER_YEAR])],
			'max_period' =>						['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_TIME_UNIT_WITH_YEAR, 'in' => implode(':', [SEC_PER_YEAR, 10 * SEC_PER_YEAR])],
			'severity_color_0' =>				['type' => API_COLOR, 'flags' => API_NOT_EMPTY],
			'severity_color_1' =>				['type' => API_COLOR, 'flags' => API_NOT_EMPTY],
			'severity_color_2' =>				['type' => API_COLOR, 'flags' => API_NOT_EMPTY],
			'severity_color_3' =>				['type' => API_COLOR, 'flags' => API_NOT_EMPTY],
			'severity_color_4' =>				['type' => API_COLOR, 'flags' => API_NOT_EMPTY],
			'severity_color_5' =>				['type' => API_COLOR, 'flags' => API_NOT_EMPTY],
			'severity_name_0' =>				['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('config', 'severity_name_0')],
			'severity_name_1' =>				['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('config', 'severity_name_1')],
			'severity_name_2' =>				['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('config', 'severity_name_2')],
			'severity_name_3' =>				['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('config', 'severity_name_3')],
			'severity_name_4' =>				['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('config', 'severity_name_4')],
			'severity_name_5' =>				['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('config', 'severity_name_5')],
			'custom_color' =>					['type' => API_INT32, 'in' => EVENT_CUSTOM_COLOR_DISABLED.','.EVENT_CUSTOM_COLOR_ENABLED],
			'ok_period' =>						['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY, 'in' => implode(':', [0, SEC_PER_DAY])],
			'blink_period' =>					['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY, 'in' => implode(':', [0, SEC_PER_DAY])],
			'problem_unack_color' =>			['type' => API_COLOR, 'flags' => API_NOT_EMPTY],
			'problem_ack_color' =>				['type' => API_COLOR, 'flags' => API_NOT_EMPTY],
			'ok_unack_color' =>					['type' => API_COLOR, 'flags' => API_NOT_EMPTY],
			'ok_ack_color' =>					['type' => API_COLOR, 'flags' => API_NOT_EMPTY],
			'problem_unack_style' =>			['type' => API_INT32, 'in' => '0,1'],
			'problem_ack_style' =>				['type' => API_INT32, 'in' => '0,1'],
			'ok_unack_style' =>					['type' => API_INT32, 'in' => '0,1'],
			'ok_ack_style' =>					['type' => API_INT32, 'in' => '0,1'],
			'discovery_groupid' =>				['type' => API_ID],
			'default_inventory_mode' =>			['type' => API_INT32, 'in' => HOST_INVENTORY_DISABLED.','.HOST_INVENTORY_MANUAL.','.HOST_INVENTORY_AUTOMATIC],
			'alert_usrgrpid' =>					['type' => API_ID, 'flags' => API_ALLOW_NULL],
			'snmptrap_logging' =>				['type' => API_INT32, 'in' => '0,1'],
			'default_lang' =>					['type' => API_STRING_UTF8, 'in' => implode(',', array_keys(getLocales()))],
			'default_timezone' =>				['type' => API_STRING_UTF8, 'in' => ZBX_DEFAULT_TIMEZONE.','.implode(',', array_keys(CTimezoneHelper::getList()))],
			'login_attempts' =>					['type' => API_INT32, 'in' => '1:32'],
			'login_block' =>					['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY, 'in' => implode(':', [30, SEC_PER_HOUR])],
			'validate_uri_schemes' =>			['type' => API_INT32, 'in' => '0,1'],
			'uri_valid_schemes' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('config', 'uri_valid_schemes')],
			'x_frame_options' =>				['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('config', 'x_frame_options')],
			'iframe_sandboxing_enabled' =>		['type' => API_INT32, 'in' => '0,1'],
			'iframe_sandboxing_exceptions' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('config', 'iframe_sandboxing_exceptions')],
			'max_overview_table_size' =>		['type' => API_INT32, 'in' => '5:999999'],
			'connect_timeout' =>				['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY, 'in' => '1:30'],
			'socket_timeout' =>					['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY, 'in' => '1:300'],
			'media_type_test_timeout' =>		['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY, 'in' => '1:300'],
			'script_timeout' =>					['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY, 'in' => '1:300'],
			'item_test_timeout' =>				['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY, 'in' => '1:300'],
			'url' =>							['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('config', 'url')],
			'report_test_timeout' =>			['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY, 'in' => '1:300'],
			'auditlog_enabled' =>				['type' => API_INT32, 'in' => '0,1'],
			'geomaps_tile_provider' =>			['type' => API_STRING_UTF8, 'in' => ','.implode(',', array_keys(getTileProviders()))],
			'geomaps_tile_url' =>				['type' => API_URL, 'length' => DB::getFieldLength('config', 'geomaps_tile_url')],
			'geomaps_max_zoom' =>				['type' => API_INT32, 'in' => '0:'.ZBX_GEOMAP_MAX_ZOOM],
			'geomaps_attribution' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('config', 'geomaps_attribution')]
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

		if (array_key_exists('alert_usrgrpid', $settings) && $settings['alert_usrgrpid'] !== null) {
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
			$settings['geomaps_tile_url'] = DB::getDefault('config', 'geomaps_tile_url');
			$settings['geomaps_max_zoom'] = DB::getDefault('config', 'geomaps_max_zoom');
			$settings['geomaps_attribution'] = DB::getDefault('config', 'geomaps_attribution');
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

		$output_fields = $this->output_fields;
		$output_fields[] = 'configid';

		return DB::select('config', ['output' => $output_fields])[0];
	}

	/**
	 * Set or unset the host group as internal
	 *
	 * @param string $groupid   Host group ID
	 * @param int    $internal  Value of internal option
	 */
	private function setHostGroupInternal(string $groupid, int $internal): void {
		DB::update('hstgrp', [
			'values' => ['internal' =>  $internal],
			'where' => ['groupid' => $groupid]
		]);
	}
}
