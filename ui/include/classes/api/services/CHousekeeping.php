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
 * Class containing methods for operations with housekeeping parameters.
 */
class CHousekeeping extends CApiService {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_ZABBIX_USER],
		'update' => ['min_user_type' => USER_TYPE_SUPER_ADMIN]
	];

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
	 * @param array $housekeeping
	 *
	 * @throws APIException
	 *
	 * @return array
	 */
	public function update(array $housekeeping): array {
		$this->validateUpdate($housekeeping, $db_housekeeping);

		CApiSettingsHelper::updateParameters($housekeeping, $db_housekeeping);

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_HOUSEKEEPING, [$housekeeping], [$db_housekeeping]);

		return array_keys($housekeeping);
	}

	/**
	 * @param array      $housekeeping
	 * @param array|null $db_housekeeping
	 *
	 * @throws APIException
	 */
	private function validateUpdate(array $housekeeping, ?array &$db_housekeeping): void {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'hk_events_mode' =>			['type' => API_INT32, 'in' => '0,1'],
			'hk_events_trigger' =>		['type' => API_TIME_UNIT, 'in' => implode(':', [SEC_PER_DAY, 25 * SEC_PER_YEAR])],
			'hk_events_service' =>		['type' => API_TIME_UNIT, 'in' => implode(':', [SEC_PER_DAY, 25 * SEC_PER_YEAR])],
			'hk_events_internal' =>		['type' => API_TIME_UNIT, 'in' => implode(':', [SEC_PER_DAY, 25 * SEC_PER_YEAR])],
			'hk_events_discovery' =>	['type' => API_TIME_UNIT, 'in' => implode(':', [SEC_PER_DAY, 25 * SEC_PER_YEAR])],
			'hk_events_autoreg' =>		['type' => API_TIME_UNIT, 'in' => implode(':', [SEC_PER_DAY, 25 * SEC_PER_YEAR])],
			'hk_services_mode' =>		['type' => API_INT32, 'in' => '0,1'],
			'hk_services' =>			['type' => API_TIME_UNIT, 'in' => implode(':', [SEC_PER_DAY, 25 * SEC_PER_YEAR])],
			'hk_audit_mode' =>			['type' => API_INT32, 'in' => '0,1'],
			'hk_audit' =>				['type' => API_TIME_UNIT, 'in' => implode(':', [SEC_PER_DAY, 25 * SEC_PER_YEAR])],
			'hk_sessions_mode' =>		['type' => API_INT32, 'in' => '0,1'],
			'hk_sessions' =>			['type' => API_TIME_UNIT, 'in' => implode(':', [SEC_PER_DAY, 25 * SEC_PER_YEAR])],
			'hk_history_mode' =>		['type' => API_INT32, 'in' => '0,1'],
			'hk_history_global' =>		['type' => API_INT32, 'in' => '0,1'],
			'hk_history' =>				['type' => API_TIME_UNIT, 'in' => '0,'.implode(':', [SEC_PER_HOUR, 25 * SEC_PER_YEAR])],
			'hk_trends_mode' =>			['type' => API_INT32, 'in' => '0,1'],
			'hk_trends_global' =>		['type' => API_INT32, 'in' => '0,1'],
			'hk_trends' =>				['type' => API_TIME_UNIT, 'in' => '0,'.implode(':', [SEC_PER_DAY, 25 * SEC_PER_YEAR])],
			'compression_status' =>		['type' => API_INT32, 'in' => '0,1'],
			'compress_older' =>			['type' => API_TIME_UNIT, 'in' => implode(':', [7 * SEC_PER_DAY, 25 * SEC_PER_YEAR])]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $housekeeping, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_housekeeping = CApiSettingsHelper::getParameters(array_diff($this->output_fields, ['db_extension']), false);

		CApiSettingsHelper::checkUndeclaredParameters($housekeeping, $db_housekeeping);
	}
}
