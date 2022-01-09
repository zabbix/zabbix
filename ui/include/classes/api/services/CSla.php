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
 * SLA API implementation.
 */
class CSla extends CApiService {

	public const ACCESS_RULES = [
		'get' =>	['min_user_type' => USER_TYPE_ZABBIX_USER],
		'getsli' =>	['min_user_type' => USER_TYPE_ZABBIX_USER],
		'create' =>	['min_user_type' => USER_TYPE_ZABBIX_ADMIN, 'action' => CRoleHelper::ACTIONS_MANAGE_SLA],
		'update' =>	['min_user_type' => USER_TYPE_ZABBIX_ADMIN, 'action' => CRoleHelper::ACTIONS_MANAGE_SLA],
		'delete' =>	['min_user_type' => USER_TYPE_ZABBIX_ADMIN, 'action' => CRoleHelper::ACTIONS_MANAGE_SLA]
	];

	protected $tableName = 'sla';
	protected $tableAlias = 'sla';
	protected $sortColumns = ['slaid', 'name', 'period', 'slo', 'effective_date', 'timezone', 'status', 'description'];

	/**
	 * @param array $options
	 *
	 * @throws APIException
	 *
	 * @return array|string
	 */
	public function get(array $options = []) {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			// filter
			'slaids' =>						['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'evaltype' =>					['type' => API_INT32, 'in' => implode(',', [TAG_EVAL_TYPE_AND_OR, TAG_EVAL_TYPE_OR]), 'default' => TAG_EVAL_TYPE_AND_OR],
			'service_tags' =>				['type' => API_OBJECTS, 'default' => [], 'fields' => [
				'tag' =>						['type' => API_STRING_UTF8, 'flags' => API_REQUIRED],
				'value' =>						['type' => API_STRING_UTF8],
				'operator' =>					['type' => API_INT32, 'in' => implode(',', [TAG_OPERATOR_LIKE, TAG_OPERATOR_EQUAL, TAG_OPERATOR_NOT_LIKE, TAG_OPERATOR_NOT_EQUAL, TAG_OPERATOR_EXISTS, TAG_OPERATOR_NOT_EXISTS])]
			]],
			'serviceids' =>					['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'filter' =>						['type' => API_OBJECT, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => [
				'slaid' =>						['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'name' =>						['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'period' =>						['type' => API_INTS32, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'in' => implode(',', [ZBX_SLA_PERIOD_DAILY, ZBX_SLA_PERIOD_WEEKLY, ZBX_SLA_PERIOD_MONTHLY, ZBX_SLA_PERIOD_QUARTERLY, ZBX_SLA_PERIOD_ANNUALLY])],
				'slo' =>						['type' => API_FLOATS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'in' => '0:100'],
				'effective_date' =>				['type' => API_INTS32, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'in' => '0:'.ZBX_MAX_DATE],
				'timezone' =>					['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'in' => implode(',', array_merge([ZBX_DEFAULT_TIMEZONE], array_keys(CTimezoneHelper::getList())))],
				'status' =>						['type' => API_INTS32, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'in' => implode(',', [ZBX_SLA_STATUS_DISABLED, ZBX_SLA_STATUS_ENABLED])]
			]],
			'search' =>						['type' => API_OBJECT, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => [
				'name' =>						['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'timezone' =>					['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'description' =>				['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE]
			]],
			'searchByAny' =>				['type' => API_BOOLEAN, 'default' => false],
			'startSearch' =>				['type' => API_FLAG, 'default' => false],
			'excludeSearch' =>				['type' => API_FLAG, 'default' => false],
			'searchWildcardsEnabled' =>		['type' => API_BOOLEAN, 'default' => false],
			// output
			'output' =>						['type' => API_OUTPUT, 'in' => implode(',', ['slaid', 'name', 'period', 'slo', 'effective_date', 'timezone', 'status', 'description']), 'default' => API_OUTPUT_EXTEND],
			'countOutput' =>				['type' => API_FLAG, 'default' => false],
			'selectServiceTags' =>			['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL | API_ALLOW_COUNT, 'in' => implode(',', ['tag', 'operator', 'value']), 'default' => null],
			'selectSchedule' =>				['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL | API_ALLOW_COUNT, 'in' => implode(',', ['period_from', 'period_to']), 'default' => null],
			'selectExcludedDowntimes' =>	['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL | API_ALLOW_COUNT, 'in' => implode(',', ['name', 'period_from', 'period_to']), 'default' => null],
			// sort and limit
			'sortfield' =>					['type' => API_STRINGS_UTF8, 'flags' => API_NORMALIZE, 'in' => implode(',', ['slaid', 'name', 'period', 'slo', 'effective_date', 'timezone', 'status', 'description']), 'uniq' => true, 'default' => []],
			'sortorder' =>					['type' => API_SORTORDER, 'default' => []],
			'limit' =>						['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => '1:'.ZBX_MAX_INT32, 'default' => null],
			// flags
			'editable' =>					['type' => API_BOOLEAN, 'default' => false],
			'preservekeys' =>				['type' => API_BOOLEAN, 'default' => false]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$accessible_slaids = self::getAccessibleSlaids($options['slaids'], $options['serviceids']);

		$count_output = $options['countOutput'];

		if ($count_output) {
			$options['output'] = ['slaid'];
			$options['countOutput'] = false;
		}

		$resource = DBselect($this->createSelectQuery('sla', $options));

		$db_slas = [];

		while (($options['limit'] === null || count($db_slas) < $options['limit']) && $row = DBfetch($resource)) {
			if ($accessible_slaids !== null && !array_key_exists($row['slaid'], $accessible_slaids)) {
				continue;
			}

			$db_slas[$row['slaid']] = $row;
		}

		if ($count_output) {
			return (string) count($db_slas);
		}

		if ($db_slas) {
			$db_slas = $this->addRelatedObjects($options, $db_slas);
			$db_slas = $this->unsetExtraFields($db_slas, ['slaid'], $options['output']);

			if (!$options['preservekeys']) {
				$db_slas = array_values($db_slas);
			}
		}

		return $db_slas;
	}

	/**
	 * @param array $slas
	 *
	 * @throws APIException
	 *
	 * @return array
	 */
	public function create(array $slas): array {
		self::validateCreate($slas);

		$ins_slas = [];

		foreach ($slas as $sla) {
			unset($sla['service_tags'], $sla['schedule'], $sla['excluded_downtimes']);
			$ins_slas[] = $sla;
		}

		$slaids = DB::insert('sla', $ins_slas);

		foreach ($slas as $index => &$sla) {
			$sla['slaid'] = $slaids[$index];
		}
		unset($sla);

		self::updateServiceTags($slas);
		self::updateSchedule($slas);
		self::updateExcludedDowntimes($slas);

		self::addAuditLog(CAudit::ACTION_ADD, CAudit::RESOURCE_SLA, $slas);

		return ['slaids' => $slaids];
	}

	/**
	 * @param array $slas
	 *
	 * @throws APIException
	 */
	private static function validateCreate(array &$slas): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['name']], 'fields' => [
			'name' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('sla', 'name')],
			'period' =>				['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [ZBX_SLA_PERIOD_DAILY, ZBX_SLA_PERIOD_WEEKLY, ZBX_SLA_PERIOD_MONTHLY, ZBX_SLA_PERIOD_QUARTERLY, ZBX_SLA_PERIOD_ANNUALLY])],
			'slo' =>				['type' => API_FLOAT, 'flags' => API_REQUIRED, 'in' => '0:100'],
			'effective_date' =>		['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '0:'.ZBX_MAX_DATE],
			'timezone' =>			['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'in' => implode(',', array_merge([ZBX_DEFAULT_TIMEZONE], array_keys(CTimezoneHelper::getList()))), 'length' => DB::getFieldLength('sla', 'timezone')],
			'status' =>				['type' => API_INT32, 'in' => implode(',', [ZBX_SLA_STATUS_DISABLED, ZBX_SLA_STATUS_ENABLED])],
			'description' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('sla', 'description')],
			'service_tags' =>		['type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'uniq' => [['tag', 'value']], 'fields' => [
				'tag' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('sla_service_tag', 'tag')],
				'operator' =>			['type' => API_INT32, 'in' => implode(',', [ZBX_SLA_SERVICE_TAG_OPERATOR_EQUAL, ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE]), 'default' => DB::getDefault('sla_service_tag', 'operator')],
				'value' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('sla_service_tag', 'value'), 'default' => DB::getDefault('sla_service_tag', 'value')]
			]],
			'schedule' =>			['type' => API_OBJECTS, 'uniq' => [['period_from', 'period_to']], 'fields' => [
				'period_from' =>		['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '0:'.SEC_PER_WEEK],
				'period_to' =>			['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '0:'.SEC_PER_WEEK]
			]],
			'excluded_downtimes' =>	['type' => API_OBJECTS, 'uniq' => [['period_from', 'period_to']], 'fields' => [
				'name' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('sla_excluded_downtime', 'name')],
				'period_from' =>		['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '0:'.ZBX_MAX_DATE],
				'period_to' =>			['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '0:'.ZBX_MAX_DATE]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $slas, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		self::checkDuplicates($slas);
		self::checkSlo($slas);
		self::checkSchedule($slas);
		self::checkExcludedDowntimes($slas);
	}

	/**
	 * @param array $slas
	 *
	 * @throws APIException
	 *
	 * @return array
	 */
	public function update(array $slas): array {
		self::validateUpdate($slas, $db_slas);

		$upd_slas = [];

		foreach ($slas as $sla) {
			$upd_sla = DB::getUpdatedValues('sla', $sla, $db_slas[$sla['slaid']]);

			if ($upd_sla) {
				$upd_slas[] = [
					'values' => $upd_sla,
					'where' => ['slaid' => $sla['slaid']]
				];
			}
		}

		if ($upd_slas) {
			DB::update('sla', $upd_slas);
		}

		self::updateServiceTags($slas, $db_slas);
		self::updateSchedule($slas, $db_slas);
		self::updateExcludedDowntimes($slas, $db_slas);

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_SLA, $slas, $db_slas);

		return ['slaids' => array_column($slas, 'slaid')];
	}

	/**
	 * @param array      $slas
	 * @param array|null $db_slas
	 *
	 * @throws APIException
	 */
	private static function validateUpdate(array &$slas, ?array &$db_slas): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['slaid'], ['name']], 'fields' => [
			'slaid' =>				['type' => API_ID, 'flags' => API_REQUIRED],
			'name' =>				['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('sla', 'name')],
			'period' =>				['type' => API_INT32, 'in' => implode(',', [ZBX_SLA_PERIOD_DAILY, ZBX_SLA_PERIOD_WEEKLY, ZBX_SLA_PERIOD_MONTHLY, ZBX_SLA_PERIOD_QUARTERLY, ZBX_SLA_PERIOD_ANNUALLY])],
			'slo' =>				['type' => API_FLOAT, 'in' => '0:100'],
			'effective_date' =>		['type' => API_INT32, 'in' => '0:'.ZBX_MAX_DATE],
			'timezone' =>			['type' => API_STRING_UTF8, 'in' => implode(',', array_merge([ZBX_DEFAULT_TIMEZONE], array_keys(CTimezoneHelper::getList()))), 'length' => DB::getFieldLength('sla', 'timezone')],
			'status' =>				['type' => API_INT32, 'in' => implode(',', [ZBX_SLA_STATUS_DISABLED, ZBX_SLA_STATUS_ENABLED])],
			'description' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('sla', 'description')],
			'service_tags' =>		['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY, 'uniq' => [['tag', 'value']], 'fields' => [
				'tag' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('sla_service_tag', 'tag')],
				'operator' =>			['type' => API_INT32, 'in' => implode(',', [ZBX_SLA_SERVICE_TAG_OPERATOR_EQUAL, ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE]), 'default' => DB::getDefault('sla_service_tag', 'operator')],
				'value' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('sla_service_tag', 'value'), 'default' => DB::getDefault('sla_service_tag', 'value')]
			]],
			'schedule' =>			['type' => API_OBJECTS, 'uniq' => [['period_from', 'period_to']], 'fields' => [
				'period_from' =>		['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '0:'.SEC_PER_WEEK],
				'period_to' =>			['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '0:'.SEC_PER_WEEK]
			]],
			'excluded_downtimes' =>	['type' => API_OBJECTS, 'uniq' => [['period_from', 'period_to']], 'fields' => [
				'name' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('sla_excluded_downtime', 'name')],
				'period_from' =>		['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '0:'.ZBX_MAX_DATE],
				'period_to' =>			['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '0:'.ZBX_MAX_DATE]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $slas, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_slas = DB::select('sla', [
			'output' => ['slaid', 'name', 'period', 'slo', 'timezone', 'status', 'description'],
			'slaids' => array_column($slas, 'slaid'),
			'preservekeys' => true
		]);

		if (count($db_slas) != count($slas)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		self::addAffectedObjects($slas, $db_slas);

		self::checkDuplicates($slas, $db_slas);
		self::checkSlo($slas, $db_slas);
		self::checkSchedule($slas, $db_slas);
		self::checkExcludedDowntimes($slas, $db_slas);
	}

	/**
	 * @param array $slaids
	 *
	 * @throws APIException
	 *
	 * @return array
	 */
	public function delete(array $slaids): array {
		$this->validateDelete($slaids, $db_slas);

		DB::delete('sla', ['slaid' => $slaids]);

		self::addAuditLog(CAudit::ACTION_DELETE, CAudit::RESOURCE_SLA, $db_slas);

		return ['slaids' => $slaids];
	}

	/**
	 * @param array      $slaids
	 * @param array|null $db_slas
	 *
	 * @throws APIException
	 */
	private function validateDelete(array $slaids, ?array &$db_slas): void {
		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];

		if (!CApiInputValidator::validate($api_input_rules, $slaids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_slas = $this->get([
			'output' => ['slaid', 'name'],
			'slaids' => $slaids,
			'editable' => true,
			'preservekeys' => true
		]);

		if (count($db_slas) != count($slaids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}
	}

	/**
	 * @param string $table_name
	 * @param string $table_alias
	 * @param array  $options
	 * @param array  $sql_parts
	 *
	 * @return array
	 */
	protected function applyQueryFilterOptions($table_name, $table_alias, array $options, array $sql_parts): array {
		$sql_parts = parent::applyQueryFilterOptions($table_name, $table_alias, $options, $sql_parts);

		if ($options['service_tags']) {
			$sql_parts['where'][] = CApiTagHelper::addWhereCondition($options['service_tags'], $options['evaltype'],
				'sla', 'sla_service_tag', 'slaid'
			);
		}

		return $sql_parts;
	}

	/**
	 * @param array $options
	 * @param array $result
	 *
	 * @return array
	 */
	protected function addRelatedObjects(array $options, array $result): array {
		$result = parent::addRelatedObjects($options, $result);

		self::addRelatedServiceTags($options, $result);
		self::addRelatedSchedule($options, $result);
		self::addRelatedExcludedDowntimes($options, $result);

		return $result;
	}

	/**
	 * @param array $options
	 * @param array $result
	 */
	private static function addRelatedServiceTags(array $options, array &$result): void {
		if ($options['selectServiceTags'] === null) {
			return;
		}

		foreach ($result as &$row) {
			$row['service_tags'] = [];
		}
		unset($row);

		if ($options['selectServiceTags'] === API_OUTPUT_COUNT) {
			$output = ['sla_service_tagid', 'slaid'];
		}
		elseif ($options['selectServiceTags'] === API_OUTPUT_EXTEND) {
			$output = ['sla_service_tagid', 'slaid', 'tag', 'operator', 'value'];
		}
		else {
			$output = array_unique(array_merge(['sla_service_tagid', 'slaid'], $options['selectServiceTags']));
		}

		$sql_options = [
			'output' => $output,
			'filter' => ['slaid' => array_keys($result)]
		];
		$db_service_tags = DBselect(DB::makeSql('sla_service_tag', $sql_options));

		while ($db_service_tag = DBfetch($db_service_tags)) {
			$slaid = $db_service_tag['slaid'];

			unset($db_service_tag['sla_service_tagid'], $db_service_tag['slaid']);

			$result[$slaid]['service_tags'][] = $db_service_tag;
		}

		if ($options['selectServiceTags'] === API_OUTPUT_COUNT) {
			foreach ($result as &$row) {
				$row['service_tags'] = (string) count($row['service_tags']);
			}
			unset($row);
		}
	}

	/**
	 * @param array $options
	 * @param array $result
	 */
	private static function addRelatedSchedule(array $options, array &$result): void {
		if ($options['selectSchedule'] === null) {
			return;
		}

		foreach ($result as &$row) {
			$row['schedule'] = [];
		}
		unset($row);

		$sql_options = [
			'output' => ['sla_scheduleid', 'slaid', 'period_from', 'period_to'],
			'filter' => ['slaid' => array_keys($result)]
		];
		$db_schedule = DBselect(DB::makeSql('sla_schedule', $sql_options));

		while ($db_schedule_row = DBfetch($db_schedule)) {
			$slaid = $db_schedule_row['slaid'];

			unset($db_schedule_row['sla_scheduleid'], $db_schedule_row['slaid']);

			$result[$slaid]['schedule'][] = $db_schedule_row;
		}

		foreach ($result as &$row) {
			$row['schedule'] = self::normalizeSchedule($row['schedule']);

			if ($options['selectSchedule'] === API_OUTPUT_COUNT) {
				$row['schedule'] = (string) count($row['schedule']);
			}
			elseif ($options['selectSchedule'] !== API_OUTPUT_EXTEND) {
				$select_schedule = array_flip($options['selectSchedule']);

				foreach ($row['schedule'] as &$schedule_row) {
					$schedule_row = array_intersect_key($schedule_row, $select_schedule);
				}
				unset($schedule_row);
			}
		}
		unset($row);
	}

	/**
	 * @param array $options
	 * @param array $result
	 */
	private static function addRelatedExcludedDowntimes(array $options, array &$result): void {
		if ($options['selectExcludedDowntimes'] === null) {
			return;
		}

		foreach ($result as &$row) {
			$row['excluded_downtimes'] = [];
		}
		unset($row);

		if ($options['selectExcludedDowntimes'] === API_OUTPUT_COUNT) {
			$output = ['sla_excluded_downtimeid', 'slaid'];
		}
		elseif ($options['selectExcludedDowntimes'] === API_OUTPUT_EXTEND) {
			$output = ['sla_excluded_downtimeid', 'slaid', 'name', 'period_from', 'period_to'];
		}
		else {
			$output = array_unique(array_merge(['sla_excluded_downtimeid', 'slaid'],
				$options['selectExcludedDowntimes']
			));
		}

		$sql_options = [
			'output' => $output,
			'filter' => ['slaid' => array_keys($result)],
			'sortfield' => ['period_from'],
			'sortorder' => [ZBX_SORT_UP]
		];
		$db_sla_excluded_downtimes = DBselect(DB::makeSql('sla_excluded_downtime', $sql_options));

		while ($db_sla_excluded_downtime = DBfetch($db_sla_excluded_downtimes)) {
			$slaid = $db_sla_excluded_downtime['slaid'];

			unset($db_sla_excluded_downtime['sla_excluded_downtimeid'], $db_sla_excluded_downtime['slaid']);

			$result[$slaid]['excluded_downtimes'][] = $db_sla_excluded_downtime;
		}

		if ($options['selectExcludedDowntimes'] === API_OUTPUT_COUNT) {
			foreach ($result as &$row) {
				$row['excluded_downtimes'] = (string) count($row['excluded_downtimes']);
			}
			unset($row);
		}
	}

	/**
	 * @param array      $slas
	 * @param array|null $db_slas
	 *
	 * @throws APIException
	 */
	private static function checkDuplicates(array $slas, array $db_slas = null): void {
		$names = [];

		foreach ($slas as $sla) {
			if ($db_slas === null
					|| (array_key_exists('name', $sla) && $sla['name'] !== $db_slas[$sla['slaid']]['name'])) {
				$names[] = $sla['name'];
			}
		}

		if (!$names) {
			return;
		}

		$duplicate = DBfetch(DBselect(
			'SELECT sla.name FROM sla WHERE '.dbConditionString('sla.name', $names), 1
		));

		if ($duplicate) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('SLA "%1$s" already exists.', $duplicate['name']));
		}
	}

	/**
	 * @param array      $slas
	 * @param array|null $db_slas
	 *
	 * @throws APIException
	 */
	private static function checkSlo(array $slas, array $db_slas = null): void {
		foreach ($slas as $sla) {
			$name = $db_slas !== null ? $db_slas[$sla['slaid']]['name'] : $sla['name'];

			if (array_key_exists('slo', $sla) && round($sla['slo'], 4) != $sla['slo']) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('SLA "%1$s" SLO must have no more than 4 fractional digits.', $name)
				);
			}
		}
	}

	/**
	 * @param array      $slas
	 * @param array|null $db_slas
	 *
	 * @throws APIException
	 */
	private static function checkSchedule(array &$slas, array $db_slas = null): void {
		foreach ($slas as &$sla) {
			if (!array_key_exists('schedule', $sla)) {
				continue;
			}

			$name = $db_slas !== null ? $db_slas[$sla['slaid']]['name'] : $sla['name'];

			foreach ($sla['schedule'] as $schedule_row) {
				if ($schedule_row['period_from'] >= $schedule_row['period_to']) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Start time must be less than end time for SLA "%1$s".', $name)
					);
				}
			}

			$sla['schedule'] = self::normalizeSchedule($sla['schedule']);
		}
		unset($sla);
	}

	/**
	 * @param array      $slas
	 * @param array|null $db_slas
	 *
	 * @throws APIException
	 */
	private static function checkExcludedDowntimes(array $slas, array $db_slas = null): void {
		foreach ($slas as $sla) {
			if (!array_key_exists('excluded_downtimes', $sla)) {
				continue;
			}

			$name = $db_slas !== null ? $db_slas[$sla['slaid']]['name'] : $sla['name'];

			foreach ($sla['excluded_downtimes'] as $excluded_downtime) {
				if ($excluded_downtime['period_from'] >= $excluded_downtime['period_to']) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s(
						'Start time must be less than end time for excluded downtime "%2$s" of SLA "%1$s".',
						$name, $excluded_downtime['name']
					));
				}
			}
		}
	}

	/**
	 * @param array $slas
	 * @param array $db_slas
	 */
	private static function addAffectedObjects(array $slas, array &$db_slas): void {
		self::addAffectedServiceTags($slas, $db_slas);
		self::addAffectedSchedule($slas, $db_slas);
		self::addAffectedExcludedDowntimes($slas, $db_slas);
	}

	/**
	 * @param array $slas
	 * @param array $db_slas
	 */
	private static function addAffectedServiceTags(array $slas, array &$db_slas): void {
		$affected_slaids = [];

		foreach ($slas as $sla) {
			if (array_key_exists('service_tags', $sla)) {
				$affected_slaids[$sla['slaid']] = true;
				$db_slas[$sla['slaid']]['service_tags'] = [];
			}
		}

		$sql_options = [
			'output' => ['sla_service_tagid', 'slaid', 'tag', 'operator', 'value'],
			'filter' => ['slaid' => array_keys($affected_slaids)]
		];
		$db_service_tags = DBselect(DB::makeSql('sla_service_tag', $sql_options));

		while ($db_service_tag = DBfetch($db_service_tags)) {
			$slaid = $db_service_tag['slaid'];

			unset($db_service_tag['slaid']);

			$db_slas[$slaid]['service_tags'][$db_service_tag['sla_service_tagid']] = $db_service_tag;
		}
	}

	/**
	 * @param array $slas
	 * @param array $db_slas
	 */
	private static function addAffectedSchedule(array $slas, array &$db_slas): void {
		$affected_slaids = [];

		foreach ($slas as $sla) {
			if (array_key_exists('schedule', $sla)) {
				$affected_slaids[$sla['slaid']] = true;
				$db_slas[$sla['slaid']]['schedule'] = [];
			}
		}

		$sql_options = [
			'output' => ['sla_scheduleid', 'slaid', 'period_from', 'period_to'],
			'filter' => ['slaid' => array_keys($affected_slaids)]
		];
		$db_schedule = DBselect(DB::makeSql('sla_schedule', $sql_options));

		while ($db_schedule_row = DBfetch($db_schedule)) {
			$slaid = $db_schedule_row['slaid'];

			unset($db_schedule_row['slaid']);

			$db_slas[$slaid]['schedule'][$db_schedule_row['sla_scheduleid']] = $db_schedule_row;
		}
	}

	/**
	 * @param array $slas
	 * @param array $db_slas
	 */
	private static function addAffectedExcludedDowntimes(array $slas, array &$db_slas): void {
		$affected_slaids = [];

		foreach ($slas as $sla) {
			if (array_key_exists('excluded_downtimes', $sla)) {
				$affected_slaids[$sla['slaid']] = true;
				$db_slas[$sla['slaid']]['excluded_downtimes'] = [];
			}
		}

		$sql_options = [
			'output' => ['sla_excluded_downtimeid', 'slaid', 'name', 'period_from', 'period_to'],
			'filter' => ['slaid' => array_keys($affected_slaids)]
		];
		$db_excluded_downtimes = DBselect(DB::makeSql('sla_excluded_downtime', $sql_options));

		while ($db_excluded_downtime = DBfetch($db_excluded_downtimes)) {
			$slaid = $db_excluded_downtime['slaid'];

			unset($db_excluded_downtime['slaid']);

			$db_slas[$slaid]['excluded_downtimes'][$db_excluded_downtime['sla_excluded_downtimeid']] =
				$db_excluded_downtime;
		}
	}

	/**
	 * @param array      $slas
	 * @param array|null $db_slas
	 */
	private static function updateServiceTags(array &$slas, array $db_slas = null): void {
		$ins_service_tags = [];
		$del_service_tags = [];

		foreach ($slas as &$sla) {
			if (!array_key_exists('service_tags', $sla)) {
				continue;
			}

			$db_service_tags = [];

			if ($db_slas !== null) {
				foreach ($db_slas[$sla['slaid']]['service_tags'] as $db_service_tag) {
					$db_service_tags[$db_service_tag['tag']][$db_service_tag['operator']][$db_service_tag['value']] =
						$db_service_tag['sla_service_tagid'];

					$del_service_tags[$db_service_tag['sla_service_tagid']] = true;
				}
			}

			foreach ($sla['service_tags'] as &$service_tag) {
				if (array_key_exists($service_tag['tag'], $db_service_tags)
						&& array_key_exists($service_tag['operator'], $db_service_tags[$service_tag['tag']])
						&& array_key_exists($service_tag['value'],
							$db_service_tags[$service_tag['tag']][$service_tag['operator']]
						)) {
					$service_tag['sla_service_tagid'] =
						$db_service_tags[$service_tag['tag']][$service_tag['operator']][$service_tag['value']];

					unset($del_service_tags[$service_tag['sla_service_tagid']]);
				}
				else {
					$ins_service_tags[] = ['slaid' => $sla['slaid']] + $service_tag;
				}
			}
			unset($service_tag);
		}
		unset($sla);

		if ($del_service_tags) {
			DB::delete('sla_service_tag', ['sla_service_tagid' => array_keys($del_service_tags)]);
		}

		if ($ins_service_tags) {
			$sla_service_tagids = DB::insert('sla_service_tag', $ins_service_tags);
			$sla_service_tagids_index = 0;

			foreach ($slas as &$sla) {
				if (!array_key_exists('service_tags', $sla)) {
					continue;
				}

				foreach ($sla['service_tags'] as &$service_tag) {
					if (array_key_exists('sla_service_tagid', $service_tag)) {
						continue;
					}

					$service_tag['sla_service_tagid'] = $sla_service_tagids[$sla_service_tagids_index];
					$sla_service_tagids_index++;
				}
				unset($service_tag);
			}
			unset($sla);
		}
	}

	/**
	 * @param array      $slas
	 * @param array|null $db_slas
	 */
	private static function updateSchedule(array &$slas, array $db_slas = null): void {
		$ins_schedule = [];
		$del_schedule = [];

		foreach ($slas as &$sla) {
			if (!array_key_exists('schedule', $sla)) {
				continue;
			}

			$db_schedule = [];

			if ($db_slas !== null) {
				foreach ($db_slas[$sla['slaid']]['schedule'] as $db_schedule_row) {
					$db_schedule[$db_schedule_row['period_from']][$db_schedule_row['period_to']] =
						$db_schedule_row['sla_scheduleid'];

					$del_schedule[$db_schedule_row['sla_scheduleid']] = true;
				}
			}

			foreach ($sla['schedule'] as &$schedule_row) {
				if (array_key_exists($schedule_row['period_from'], $db_schedule)
						&& array_key_exists($schedule_row['period_to'], $db_schedule[$schedule_row['period_from']])) {
					$schedule_row['sla_scheduleid'] =
						$db_schedule[$schedule_row['period_from']][$schedule_row['period_to']];

					unset($del_schedule[$schedule_row['sla_scheduleid']]);
				}
				else {
					$ins_schedule[] = ['slaid' => $sla['slaid']] + $schedule_row;
				}
			}
			unset($schedule_row);
		}
		unset($sla);

		if ($del_schedule) {
			DB::delete('sla_schedule', ['sla_scheduleid' => array_keys($del_schedule)]);
		}

		if ($ins_schedule) {
			$sla_scheduleids = DB::insert('sla_schedule', $ins_schedule);
			$sla_scheduleids_index = 0;

			foreach ($slas as &$sla) {
				if (!array_key_exists('schedule', $sla)) {
					continue;
				}

				foreach ($sla['schedule'] as &$schedule_row) {
					if (array_key_exists('sla_scheduleid', $schedule_row)) {
						continue;
					}

					$schedule_row['sla_scheduleid'] = $sla_scheduleids[$sla_scheduleids_index];
					$sla_scheduleids_index++;
				}
				unset($schedule_row);
			}
			unset($sla);
		}
	}

	/**
	 * @param array      $slas
	 * @param array|null $db_slas
	 */
	private static function updateExcludedDowntimes(array &$slas, array $db_slas = null): void {
		$ins_excluded_downtimes = [];
		$upd_excluded_downtimes = [];
		$del_excluded_downtimes = [];

		foreach ($slas as &$sla) {
			if (!array_key_exists('excluded_downtimes', $sla)) {
				continue;
			}

			$db_excluded_downtimes = [];

			if ($db_slas !== null) {
				foreach ($db_slas[$sla['slaid']]['excluded_downtimes'] as $db_excluded_downtime) {
					$db_excluded_downtimes[$db_excluded_downtime['period_from']][$db_excluded_downtime['period_to']] =
						$db_excluded_downtime;

					$del_excluded_downtimes[$db_excluded_downtime['sla_excluded_downtimeid']] = true;
				}
			}

			foreach ($sla['excluded_downtimes'] as &$excluded_downtime) {
				if (array_key_exists($excluded_downtime['period_from'], $db_excluded_downtimes)
						&& array_key_exists($excluded_downtime['period_to'],
							$db_excluded_downtimes[$excluded_downtime['period_from']]
						)) {
					$excluded_downtime['sla_excluded_downtimeid'] = $db_excluded_downtimes
						[$excluded_downtime['period_from']][$excluded_downtime['period_to']]['sla_excluded_downtimeid'];

					unset($del_excluded_downtimes[$excluded_downtime['sla_excluded_downtimeid']]);

					$upd_excluded_downtime = DB::getUpdatedValues('sla_excluded_downtime', $excluded_downtime,
						$db_excluded_downtimes[$excluded_downtime['period_from']][$excluded_downtime['period_to']]
					);

					if ($upd_excluded_downtime) {
						$upd_excluded_downtimes[] = [
							'values' => $upd_excluded_downtime,
							'where' => ['sla_excluded_downtimeid' => $excluded_downtime['sla_excluded_downtimeid']]
						];
					}
				}
				else {
					$ins_excluded_downtimes[] = ['slaid' => $sla['slaid']] + $excluded_downtime;
				}
			}
			unset($excluded_downtime);
		}
		unset($sla);

		if ($del_excluded_downtimes) {
			DB::delete('sla_excluded_downtime', ['sla_excluded_downtimeid' => array_keys($del_excluded_downtimes)]);
		}

		if ($ins_excluded_downtimes) {
			$sla_excluded_downtimeids = DB::insert('sla_excluded_downtime', $ins_excluded_downtimes);
			$sla_excluded_downtimeids_index = 0;

			foreach ($slas as &$sla) {
				if (!array_key_exists('excluded_downtimes', $sla)) {
					continue;
				}

				foreach ($sla['excluded_downtimes'] as &$excluded_downtime) {
					if (array_key_exists('sla_excluded_downtimeid', $excluded_downtime)) {
						continue;
					}

					$excluded_downtime['sla_excluded_downtimeid'] =
						$sla_excluded_downtimeids[$sla_excluded_downtimeids_index];

					$sla_excluded_downtimeids_index++;
				}
				unset($excluded_downtime);
			}
			unset($sla);
		}

		if ($upd_excluded_downtimes) {
			DB::update('sla_excluded_downtime', $upd_excluded_downtimes);
		}
	}

	/**
	 * Concatenate overlapping periods and sort by starting time of periods.
	 * Full weekly period is returned as empty array.
	 *
	 * @param array $schedule
	 *
	 * @return array
	 */
	private static function normalizeSchedule(array $schedule): array {
		$converted_schedule = [];

		foreach ($schedule as $schedule_row) {
			$period_from = $schedule_row['period_from'];
			$period_to = $schedule_row['period_to'];

			foreach ($converted_schedule as $converted_schedule_row) {
				$is_overlapping = $schedule_row['period_from'] <= $converted_schedule_row['period_to']
					&& $schedule_row['period_to'] >= $converted_schedule_row['period_from'];

				if ($is_overlapping) {
					$period_from = min($period_from, $converted_schedule_row['period_from']);
					$period_to = max($period_to, $converted_schedule_row['period_to']);
				}
			}

			foreach ($converted_schedule as $index => $converted_schedule_row) {
				if ($converted_schedule_row['period_from'] >= $period_from
						&& $converted_schedule_row['period_to'] <= $period_to) {
					unset($converted_schedule[$index]);
				}
			}

			$converted_schedule[] = ['period_from' => $period_from, 'period_to' => $period_to];
		}

		usort($converted_schedule,
			static function (array $schedule_row_a, array $schedule_row_b): int {
				return $schedule_row_a['period_from'] <=> $schedule_row_b['period_from'];
			}
		);

		if (count($converted_schedule) == 1 && $converted_schedule[0]['period_from'] == 0
				&& $converted_schedule[0]['period_to'] == SEC_PER_WEEK) {
			return [];
		}

		return $converted_schedule;
	}

	/**
	 * @param array|null $limit_slaids
	 * @param array|null $limit_serviceids
	 *
	 * @throws APIException
	 *
	 * @return array|null
	 */
	private static function getAccessibleSlaids(?array $limit_slaids, ?array $limit_serviceids): ?array {
		$role = API::Role()->get([
			'output' => [],
			'selectRules' => ['services.read.mode', 'services.write.mode', 'actions'],
			'roleids' => self::$userData['roleid']
		]);

		if (!$role) {
			return [];
		}

		if ($limit_serviceids === null) {
			$rules = $role[0]['rules'];

			$manage_sla_status = 0;
			foreach ($rules['actions'] as $action) {
				if ($action['name'] === 'manage_sla') {
					$manage_sla_status = $action['status'];
					break;
				}
			}

			if ($rules['services.read.mode'] == ZBX_ROLE_RULE_SERVICES_ACCESS_ALL
					|| $rules['services.write.mode'] == ZBX_ROLE_RULE_SERVICES_ACCESS_ALL
					|| $manage_sla_status == 1) {
				return null;
			}
		}

		$accessible_services = API::Service()->get([
			'output' => [],
			'serviceids' => $limit_serviceids,
			'preservekeys' => true
		]);

		if (!$accessible_services) {
			return [];
		}

		$services_slas_resource_sql = 'SELECT DISTINCT st.serviceid, sst.slaid'.
			' FROM service_tag st, sla_service_tag sst'.
			' WHERE sst.tag=st.tag'.
				' AND ('.
					'(sst.operator='.ZBX_SLA_SERVICE_TAG_OPERATOR_EQUAL.' AND st.value=sst.value)'.
					' OR (sst.operator='.ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE." AND UPPER(st.value) LIKE CONCAT('%', ".
						"CONCAT(REPLACE(REPLACE(REPLACE(UPPER(sst.value), '%', '!%'), '_', '!_'), '!', '!!'), '%')".
					") ESCAPE '!')".
				')'.
				($limit_slaids !== null ? ' AND '.dbConditionId('sst.slaid', $limit_slaids) : '').
				($limit_serviceids !== null ? ' AND '.dbConditionId('st.serviceid', $limit_serviceids) : '');

		$services_slas_resource = DBSelect($services_slas_resource_sql);

		$accessible_slaids = [];

		while ($db_service_sla = DBfetch($services_slas_resource)) {
			if (array_key_exists($db_service_sla['serviceid'], $accessible_services)) {
				$accessible_slaids[$db_service_sla['slaid']] = true;
			}
		}

		return $accessible_slaids;
	}

	/**
	 * @param array $options
	 *
	 * @throws Exception
	 * @throws APIException
	 *
	 * @return array
	 */
	public function getSli(array $options = []): array {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'slaid' =>			['type' => API_ID, 'flags' => API_REQUIRED],
			'period_from' =>	['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => '0:'.ZBX_MAX_DATE, 'default' => null],
			'period_to' =>		['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => '0:'.ZBX_MAX_DATE, 'default' => null],
			'periods' =>		['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => '1:'.ZBX_SLA_MAX_REPORTING_PERIODS, 'default' => null],
			'serviceids' =>		['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_sla = $this->get([
			'output' => ['period', 'slo', 'timezone', 'effective_date'],
			'selectSchedule' => ['period_from', 'period_to'],
			'selectExcludedDowntimes' => ['name', 'period_from', 'period_to'],
			'slaids' => $options['slaid']
		]);

		if (!$db_sla) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		$db_sla = $db_sla[0];

		$reporting_periods = self::getReportingPeriods($db_sla, $options);

		if ($reporting_periods) {
			$db_services = API::Service()->get([
				'output' => ['created_at'],
				'selectStatusTimeline' => $reporting_periods,
				'slaids' => $options['slaid'],
				'serviceids' => $options['serviceids'],
				'preservekeys' => true
			]);
		}
		else {
			$db_services = [];
		}

		return [
			'periods' => $reporting_periods,
			'serviceids' => array_keys($db_services),
			'sli' => self::calculateSli($db_sla, $reporting_periods, array_values($db_services))
		];
	}

	/**
	 * @param array $sla
	 * @param array $options
	 *
	 * @throws Exception
	 *
	 * @return array
	 */
	private static function getReportingPeriods(array $sla, array $options): array {
		$interval = new DateInterval([
			ZBX_SLA_PERIOD_DAILY => 'P1D',
			ZBX_SLA_PERIOD_WEEKLY => 'P1W',
			ZBX_SLA_PERIOD_MONTHLY => 'P1M',
			ZBX_SLA_PERIOD_QUARTERLY => 'P3M',
			ZBX_SLA_PERIOD_ANNUALLY => 'P1Y'
		][$sla['period']]);

		$timezone = new DateTimeZone($sla['timezone'] !== ZBX_DEFAULT_TIMEZONE
			? $sla['timezone']
			: CTimezoneHelper::getSystemTimezone()
		);

		$effective_min = (new DateTime('@'.$sla['effective_date']))->setTimezone($timezone);
		self::alignDateToPeriodStart($effective_min, (int) $sla['period']);

		$effective_max = (new DateTime('now'))->setTimezone($timezone);
		self::alignDateToPeriodStart($effective_max, (int) $sla['period']);
		$effective_max->add($interval);

		if ($options['period_from'] !== null) {
			$period_from = (new DateTime('@'.$options['period_from']))->setTimezone($timezone);
			self::alignDateToPeriodStart($period_from, (int) $sla['period']);
		}
		else {
			$period_from = null;
		}

		if ($options['period_to'] !== null) {
			$period_to = (new DateTime('@'.$options['period_to']))->setTimezone($timezone);
			self::alignDateToPeriodStart($period_to, (int) $sla['period']);
			$period_to->add($interval);
		}
		elseif ($period_from === null) {
			$period_to = $effective_max;
		}
		else {
			$period_to = null;
		}

		$reporting_periods = [];

		$do_descend = $period_to !== null;
		$date = $do_descend ? clone $period_to : clone $period_from;

		while (count($reporting_periods) < ZBX_SLA_MAX_REPORTING_PERIODS) {
			if ($options['periods'] !== null) {
				if (count($reporting_periods) == $options['periods']) {
					break;
				}
			}
			else {
				if (($period_from === null || $period_to === null)
						&& count($reporting_periods) == ZBX_SLA_DEFAULT_REPORTING_PERIODS) {
					break;
				}

				if ($do_descend) {
					if ($period_from === null && $date <= $effective_min) {
						break;
					}
				}
				elseif ($date >= $effective_max) {
					break;
				}
			}

			if ($do_descend && $period_from !== null && $date <= $period_from) {
				break;
			}

			if ($do_descend) {
				$to = $date->getTimestamp();
				$date->sub($interval);
				$from = $date->getTimestamp();

				if ($from < 0) {
					break;
				}

				array_unshift($reporting_periods, ['period_from' => $from, 'period_to' => $to]);
			}
			else {
				$from = $date->getTimestamp();
				$date->add($interval);
				$to = $date->getTimestamp();

				if ($to > ZBX_MAX_DATE) {
					break;
				}

				$reporting_periods[] = ['period_from' => $from, 'period_to' => $to];
			}
		}

		return $reporting_periods;
	}

	/**
	 * @param DateTime $date
	 *
	 * @param int $sla_period
	 */
	private static function alignDateToPeriodStart(DateTime $date, int $sla_period): void {
		$year = (int) $date->format('Y');
		$month = (int) $date->format('n');

		switch ($sla_period) {
			case ZBX_SLA_PERIOD_WEEKLY:
				$date
					->modify('1 day')
					->modify('last Sunday');
				break;

			case ZBX_SLA_PERIOD_MONTHLY:
				$date->setDate($year, $month, 1);
				break;

			case ZBX_SLA_PERIOD_QUARTERLY:
				$date->setDate($year, intdiv($month - 1, 3) * 3 + 1, 1);
				break;

			case ZBX_SLA_PERIOD_ANNUALLY:
				$date->setDate($year, 1, 1);
				break;
		}

		$date->setTime(0, 0);
	}

	/**
	 * @param array $db_sla
	 * @param array $reporting_periods
	 * @param array $db_services
	 *
	 * @throws Exception
	 *
	 * @return array
	 */
	private static function calculateSli(array $db_sla, array $reporting_periods, array $db_services): array {
		if (!$reporting_periods || !$db_services) {
			return [];
		}

		$sli = [];

		$combined_excluded_downtimes = self::combineExcludedDowntimes($db_sla['excluded_downtimes']);

		foreach ($reporting_periods as $reporting_period_index => $reporting_period) {
			$scheduled_uptime_periods = self::getScheduledUptimePeriods($db_sla, $reporting_period);

			foreach ($db_services as $service_index => $db_service) {
				$cell = [
					'uptime' => 0,
					'downtime' => 0,
					'sli' => -1.0,
					'error_budget' => 0,
					'excluded_downtimes' => []
				];

				$max_uptime = 0;

				foreach ($scheduled_uptime_periods as $scheduled_uptime_period) {
					$uptime_period_from = max($db_service['created_at'], $scheduled_uptime_period['period_from']);
					$uptime_period_to = $scheduled_uptime_period['period_to'];
					$uptime = $uptime_period_to - $uptime_period_from;

					if ($uptime <= 0) {
						continue;
					}

					$max_uptime += $uptime;

					foreach ($combined_excluded_downtimes as $combined_excluded_downtime) {
						$downtime = min($combined_excluded_downtime['period_to'], $uptime_period_to)
							- max($combined_excluded_downtime['period_from'], $uptime_period_from);

						if ($downtime > 0) {
							$max_uptime -= $downtime;
						}
					}
				}

				$last_excluded_downtimes = [];

				$prev_clock = $reporting_period['period_from'];
				$prev_value = $db_service['status_timeline'][$reporting_period_index]['start_value'];

				$alarms = $db_service['status_timeline'][$reporting_period_index]['alarms'];

				if (!$alarms || $alarms[count($alarms) - 1]['clock'] <= time()) {
					$alarms[] = ['clock' => time() + 1, 'value' => null];
				}

				foreach ($alarms as $alarm) {
					foreach ($scheduled_uptime_periods as $scheduled_uptime_period) {
						$uptime_period_from = max($db_service['created_at'], $scheduled_uptime_period['period_from'],
							$prev_clock
						);
						$uptime_period_to = min($scheduled_uptime_period['period_to'], $alarm['clock']);
						$uptime = $uptime_period_to - $uptime_period_from;

						if ($uptime <= 0) {
							continue;
						}

						foreach ($combined_excluded_downtimes as $combined_excluded_downtime) {
							$downtime = min($combined_excluded_downtime['period_to'], $uptime_period_to)
								- max($combined_excluded_downtime['period_from'], $uptime_period_from);

							if ($downtime > 0) {
								$uptime -= $downtime;
							}
						}

						if ($prev_value == ZBX_SEVERITY_OK) {
							$cell['uptime'] += $uptime;
						}
						else {
							$cell['downtime'] += $uptime;
						}

						foreach ($db_sla['excluded_downtimes'] as $index => $excluded_downtime) {
							$downtime_period_from = max($excluded_downtime['period_from'], $uptime_period_from);
							$downtime_period_to = min($excluded_downtime['period_to'], $uptime_period_to);

							if ($downtime_period_to > $downtime_period_from) {
								if (array_key_exists($index, $last_excluded_downtimes)) {
									$cell['excluded_downtimes'][$last_excluded_downtimes[$index]['cell']]['period_to'] =
										(int) $downtime_period_to;
								}
								else {
									$cell['excluded_downtimes'][] = [
										'name' => $excluded_downtime['name'],
										'period_from' => (int) $downtime_period_from,
										'period_to' => (int) $downtime_period_to
									];
								}

								$last_excluded_downtimes[$index] = [
									'cell' => count($cell['excluded_downtimes']) - 1,
									'period_to' => $downtime_period_to
								];
							}
							else {
								unset($last_excluded_downtimes[$index]);
							}
						}
					}

					$prev_clock = $alarm['clock'];
					$prev_value = $alarm['value'];
				}

				if ($cell['uptime'] + $cell['downtime'] != 0) {
					$cell['sli'] = $cell['uptime'] / ($cell['uptime'] + $cell['downtime']) * 100;
				}

				if ($cell['sli'] != -1) {
					$available_uptime = $max_uptime - $cell['uptime'] - $cell['downtime'];

					$cell['error_budget'] = $db_sla['slo'] > 0
						? min($available_uptime,
							(int) ($cell['uptime'] / $db_sla['slo'] * 100) - $cell['uptime'] - $cell['downtime']
						)
						: $available_uptime;
				}

				$sli[$reporting_period_index][$service_index] = $cell;
			}
		}

		return $sli;
	}

	/**
	 * @param array $excluded_downtimes
	 *
	 * @return array
	 */
	private static function combineExcludedDowntimes(array $excluded_downtimes): array {
		$combined_excluded_downtimes = [];

		foreach ($excluded_downtimes as $excluded_downtime) {
			$period_from = $excluded_downtime['period_from'];
			$period_to = $excluded_downtime['period_to'];

			foreach ($combined_excluded_downtimes as $combined_excluded_downtime) {
				$is_overlapping = $excluded_downtime['period_from'] <= $combined_excluded_downtime['period_to']
					&& $excluded_downtime['period_to'] >= $combined_excluded_downtime['period_from'];

				if ($is_overlapping) {
					$period_from = min($period_from, $combined_excluded_downtime['period_from']);
					$period_to = max($period_to, $combined_excluded_downtime['period_to']);
				}
			}

			foreach ($combined_excluded_downtimes as $index => $combined_excluded_downtime) {
				if ($combined_excluded_downtime['period_from'] >= $period_from
						&& $combined_excluded_downtime['period_to'] <= $period_to) {
					unset($combined_excluded_downtimes[$index]);
				}
			}

			$combined_excluded_downtimes[] = ['period_from' => $period_from, 'period_to' => $period_to];
		}

		return $combined_excluded_downtimes;
	}

	/**
	 * @param array $db_sla
	 * @param array $reporting_period
	 *
	 * @throws Exception
	 *
	 * @return array
	 */
	private static function getScheduledUptimePeriods(array $db_sla, array $reporting_period): array {
		if (!$db_sla['schedule']) {
			return [$reporting_period];
		}

		$uptime_periods = [];

		$week_offset = $reporting_period['period_from'] -
			(new DateTime('@'.$reporting_period['period_from']))
				->setTimezone(new DateTimeZone($db_sla['timezone'] !== ZBX_DEFAULT_TIMEZONE
					? $db_sla['timezone']
					: CTimezoneHelper::getSystemTimezone()
				))
				->modify('1 day')
				->modify('last Sunday')
				->getTimestamp();

		for ($week = 0;; $week++) {
			$week_period_from = $reporting_period['period_from'] - $week_offset + SEC_PER_WEEK * $week;

			foreach ($db_sla['schedule'] as $schedule_row) {
				$period_from = $week_period_from + $schedule_row['period_from'];
				$period_to = $week_period_from + $schedule_row['period_to'];

				if ($period_from < $reporting_period['period_to'] && $period_to > $reporting_period['period_from']) {
					$new_period_from = max($reporting_period['period_from'], $period_from);
					$new_period_to = min($reporting_period['period_to'], $period_to);

					if ($uptime_periods
							&& $uptime_periods[count($uptime_periods) - 1]['period_to'] == $new_period_from) {
						$uptime_periods[count($uptime_periods) - 1]['period_to'] = $new_period_to;
					}
					else {
						$uptime_periods[] = ['period_from' => $new_period_from, 'period_to' => $new_period_to];
					}
				}

				if ($period_to >= $reporting_period['period_to']) {
					break 2;
				}
			}
		}

		return $uptime_periods;
	}
}
