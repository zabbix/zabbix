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
 * Class containing methods for operations with housekeeping parameters.
 */
class CHousekeeping extends CApiService {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_ZABBIX_USER],
		'update' => ['min_user_type' => USER_TYPE_SUPER_ADMIN]
	];

	protected $tableName = 'config';
	protected $tableAlias = 'c';

	/**
	 * @var array
	 */
	private $output_fields = ['hk_events_mode', 'hk_events_trigger', 'hk_events_service', 'hk_events_internal',
		'hk_events_discovery', 'hk_events_autoreg', 'hk_services_mode', 'hk_services', 'hk_audit_mode', 'hk_audit',
		'hk_sessions_mode', 'hk_sessions', 'hk_history_mode', 'hk_history_global', 'hk_history', 'hk_trends_mode',
		'hk_trends_global', 'hk_trends', 'db_extension', 'compression_status', 'compress_older'
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

		$db_hk = [];

		$result = DBselect($this->createSelectQuery($this->tableName(), $options));
		while ($row = DBfetch($result)) {
			$db_hk[] = $row;
		}
		$db_hk = $this->unsetExtraFields($db_hk, ['configid'], []);

		return $db_hk[0];
	}

	/**
	 * @param array $hk
	 *
	 * @throws APIException if the input is invalid.
	 *
	 * @return array
	 */
	public function update(array $hk): array {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS,
				_s('No permissions to call "%1$s.%2$s".', 'housekeeping', __FUNCTION__)
			);
		}

		$db_hk = $this->validateUpdate($hk);

		$upd_config = DB::getUpdatedValues('config', $hk, $db_hk);

		if ($upd_config) {
			DB::update('config', [
				'values' => $upd_config,
				'where' => ['configid' => $db_hk['configid']]
			]);
		}

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_HOUSEKEEPING,
			[['configid' => $db_hk['configid']] + $hk], [$db_hk['configid'] => $db_hk]
		);

		return array_keys($hk);
	}

	/**
	 * @param array $hk
	 *
	 * @throws APIException if the input is invalid.
	 *
	 * @return array
	 */
	protected function validateUpdate(array $hk): array {
		$api_input_rules = ['type' => API_OBJECT, 'flags' => API_NOT_EMPTY, 'fields' => [
			'hk_events_mode' =>			['type' => API_INT32, 'in' => '0,1'],
			'hk_events_trigger' =>		['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY, 'in' => implode(':', [SEC_PER_DAY, 25 * SEC_PER_YEAR])],
			'hk_events_service' =>		['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY, 'in' => implode(':', [SEC_PER_DAY, 25 * SEC_PER_YEAR])],
			'hk_events_internal' =>		['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY, 'in' => implode(':', [SEC_PER_DAY, 25 * SEC_PER_YEAR])],
			'hk_events_discovery' =>	['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY, 'in' => implode(':', [SEC_PER_DAY, 25 * SEC_PER_YEAR])],
			'hk_events_autoreg' =>		['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY, 'in' => implode(':', [SEC_PER_DAY, 25 * SEC_PER_YEAR])],
			'hk_services_mode' =>		['type' => API_INT32, 'in' => '0,1'],
			'hk_services' =>			['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY, 'in' => implode(':', [SEC_PER_DAY, 25 * SEC_PER_YEAR])],
			'hk_audit_mode' =>			['type' => API_INT32, 'in' => '0,1'],
			'hk_audit' =>				['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY, 'in' => implode(':', [SEC_PER_DAY, 25 * SEC_PER_YEAR])],
			'hk_sessions_mode' =>		['type' => API_INT32, 'in' => '0,1'],
			'hk_sessions' =>			['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY, 'in' => implode(':', [SEC_PER_DAY, 25 * SEC_PER_YEAR])],
			'hk_history_mode' =>		['type' => API_INT32, 'in' => '0,1'],
			'hk_history_global' =>		['type' => API_INT32, 'in' => '0,1'],
			'hk_history' =>				['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY, 'in' => '0,'.implode(':', [SEC_PER_HOUR, 25 * SEC_PER_YEAR])],
			'hk_trends_mode' =>			['type' => API_INT32, 'in' => '0,1'],
			'hk_trends_global' =>		['type' => API_INT32, 'in' => '0,1'],
			'hk_trends' =>				['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY, 'in' => '0,'.implode(':', [SEC_PER_DAY, 25 * SEC_PER_YEAR])],
			'compression_status' =>		['type' => API_INT32, 'in' => '0,1'],
			'compress_older' =>			['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY, 'in' => implode(':', [7 * SEC_PER_DAY, 25 * SEC_PER_YEAR])]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $hk, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$output_fields = array_diff($this->output_fields, ['db_extension']);
		$output_fields[] = 'configid';

		return DB::select('config', ['output' => $output_fields])[0];
	}
}
