<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
 * Services API implementation.
 */
class CService extends CApiService {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_ZABBIX_USER],
		'getsla' => ['min_user_type' => USER_TYPE_ZABBIX_USER],
		'create' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN],
		'update' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN],
		'delete' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN]
	];

	private const AUDIT_RESOURCE = AUDIT_RESOURCE_IT_SERVICE;

	protected $tableName = 'services';
	protected $tableAlias = 's';
	protected $sortColumns = ['sortorder', 'name'];

	/**
	 * @param array $options
	 *
	 * @return array|int
	 *
	 * @throws APIException
	 */
	public function get(array $options = []) {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			// filter
			'serviceids' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'parentids' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'childids' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'evaltype' =>				['type' => API_INT32, 'in' => implode(',', [TAG_EVAL_TYPE_AND_OR, TAG_EVAL_TYPE_OR]), 'default' => TAG_EVAL_TYPE_AND_OR],
			'tags' =>					['type' => API_OBJECTS, 'default' => [], 'fields' => [
				'tag' =>					['type' => API_STRING_UTF8, 'flags' => API_REQUIRED],
				'value' =>					['type' => API_STRING_UTF8],
				'operator' =>				['type' => API_STRING_UTF8, 'in' => implode(',', [TAG_OPERATOR_LIKE, TAG_OPERATOR_EQUAL, TAG_OPERATOR_NOT_LIKE, TAG_OPERATOR_NOT_EQUAL, TAG_OPERATOR_EXISTS, TAG_OPERATOR_NOT_EXISTS])]
			]],
			'problem_tags' =>			['type' => API_OBJECTS, 'default' => [], 'fields' => [
				'tag' =>					['type' => API_STRING_UTF8, 'flags' => API_REQUIRED],
				'value' =>					['type' => API_STRING_UTF8],
				'operator' =>				['type' => API_STRING_UTF8, 'in' => implode(',', [TAG_OPERATOR_LIKE, TAG_OPERATOR_EQUAL, TAG_OPERATOR_NOT_LIKE, TAG_OPERATOR_NOT_EQUAL, TAG_OPERATOR_EXISTS, TAG_OPERATOR_NOT_EXISTS])]
			]],
			'without_problem_tags' =>	['type' => API_FLAG, 'default' => false],
			'filter' =>					['type' => API_OBJECT, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => [
				'serviceid' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'name' =>					['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'status' =>					['type' => API_INTS32, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'in' => implode(',', range(TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_COUNT - 1))],
				'algorithm' =>				['type' => API_INTS32, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'in' => implode(',', [SERVICE_ALGORITHM_MAX, SERVICE_ALGORITHM_MIN, SERVICE_ALGORITHM_NONE])],
				'showsla' =>				['type' => API_INTS32, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'in' => implode(',', [SERVICE_SHOW_SLA_OFF, SERVICE_SHOW_SLA_ON])]
			]],
			'search' =>					['type' => API_OBJECT, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => [
				'name' =>					['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE]
			]],
			'searchByAny' =>			['type' => API_BOOLEAN, 'default' => false],
			'startSearch' =>			['type' => API_FLAG, 'default' => false],
			'excludeSearch' =>			['type' => API_FLAG, 'default' => false],
			'searchWildcardsEnabled' =>	['type' => API_BOOLEAN, 'default' => false],
			// output
			'output' =>					['type' => API_OUTPUT, 'in' => implode(',', ['serviceid', 'name', 'status', 'algorithm', 'showsla', 'goodsla', 'sortorder']), 'default' => API_OUTPUT_EXTEND],
			'countOutput' =>			['type' => API_FLAG, 'default' => false],
			'selectParents' =>			['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL | API_ALLOW_COUNT, 'in' => implode(',', ['serviceid', 'name', 'status', 'algorithm', 'showsla', 'goodsla', 'sortorder']), 'default' => null],
			'selectChildren' =>			['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL | API_ALLOW_COUNT, 'in' => implode(',', ['serviceid', 'name', 'status', 'algorithm', 'showsla', 'goodsla', 'sortorder']), 'default' => null],
			'selectTags' =>				['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL | API_ALLOW_COUNT, 'in' => implode(',', ['tag', 'value']), 'default' => null],
			'selectProblemTags' =>		['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL | API_ALLOW_COUNT, 'in' => implode(',', ['tag', 'operator', 'value']), 'default' => null],
			'selectTimes' =>			['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', ['type', 'ts_from', 'ts_to', 'note']), 'default' => null],
			'selectAlarms' =>			['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', ['clock', 'value']), 'default' => null],
			// sort and limit
			'sortfield' =>				['type' => API_STRINGS_UTF8, 'flags' => API_NORMALIZE, 'in' => implode(',', ['sortorder', 'name']), 'uniq' => true, 'default' => []],
			'sortorder' =>				['type' => API_SORTORDER, 'default' => []],
			'limit' =>					['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => '1:'.ZBX_MAX_INT32, 'default' => null],
			// flags
			'editable' =>				['type' => API_BOOLEAN, 'default' => false],
			'preservekeys' =>			['type' => API_BOOLEAN, 'default' => false]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_services = [];

		$sql = $this->createSelectQuery('services', $options);
		$resource = DBselect($sql, $options['limit']);

		while ($row = DBfetch($resource)) {
			if ($options['countOutput']) {
				return $row['rowscount'];
			}

			$db_services[$row['serviceid']] = $row;
		}

		if ($db_services) {
			$db_services = $this->addRelatedObjects($options, $db_services);
			$db_services = $this->unsetExtraFields($db_services, ['serviceid', 'sortorder', 'name'],
				$options['output']
			);

			if (!$options['preservekeys']) {
				$db_services = array_values($db_services);
			}
		}

		return $db_services;
	}

	/**
	 * @param array $services
	 *
	 * @return array
	 *
	 * @throws APIException
	 */
	public function create(array $services): array {
		$this->validateCreate($services);

		$ins_services = [];

		foreach ($services as $service) {
			unset($service['tags'], $service['parents'], $service['children'], $service['times']);
			$ins_services[] = $service;
		}

		$serviceids = DB::insert('services', $ins_services);
		$services = array_combine($serviceids, $services);

		$this->updateTags($services, __FUNCTION__);
		$this->updateProblemTags($services, __FUNCTION__);
		$this->updateParents($services, __FUNCTION__);
		$this->updateChildren($services, __FUNCTION__);
		$this->updateTimes($services,  __FUNCTION__);

		foreach ($services as $serviceid => &$service) {
			$service['serviceid'] = $serviceid;
		}
		unset($service);

		$this->addAuditBulk(AUDIT_ACTION_ADD, self::AUDIT_RESOURCE, $services);

		return ['serviceids' => $serviceids];
	}

	/**
	 * @param array $services
	 *
	 * @throws APIException
	 */
	private function validateCreate(array &$services): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'fields' => [
			'name' =>			['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('services', 'name')],
			'algorithm' =>		['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [SERVICE_ALGORITHM_MAX, SERVICE_ALGORITHM_MIN, SERVICE_ALGORITHM_NONE])],
			'showsla' =>		['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [SERVICE_SHOW_SLA_OFF, SERVICE_SHOW_SLA_ON])],
			'goodsla' =>		['type' => API_FLOAT, 'in' => '0:100'],
			'sortorder' =>		['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '0:999'],
			'tags' =>			['type' => API_OBJECTS, 'uniq' => [['tag', 'value']], 'fields' => [
				'tag' =>			['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('service_tag', 'tag')],
				'value' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('service_tag', 'value'), 'default' => DB::getDefault('service_tag', 'value')]
			]],
			'problem_tags' =>	['type' => API_OBJECTS, 'uniq' => [['tag']], 'fields' => [
				'tag' =>			['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('service_problem_tag', 'tag')],
				'operator' =>		['type' => API_INT32, 'in' => implode(',', [TAG_OPERATOR_LIKE, TAG_OPERATOR_EQUAL]), 'default' => DB::getDefault('service_problem_tag', 'operator')],
				'value' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('service_problem_tag', 'value'), 'default' => DB::getDefault('service_problem_tag', 'value')]
			]],
			'parents' =>		['type' => API_OBJECTS, 'uniq' => [['serviceid']], 'fields' => [
				'serviceid' =>		['type' => API_ID]
			]],
			'children' =>		['type' => API_OBJECTS, 'uniq' => [['serviceid']], 'fields' => [
				'serviceid' =>		['type' => API_ID]
			]],
			'times' =>			['type' => API_OBJECTS, 'uniq' => [['type', 'ts_from', 'ts_to']], 'fields' => [
				'type' =>			['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [SERVICE_TIME_TYPE_UPTIME, SERVICE_TIME_TYPE_DOWNTIME, SERVICE_TIME_TYPE_ONETIME_DOWNTIME])],
				'ts_from' =>		['type' => API_MULTIPLE, 'flags' => API_REQUIRED, 'rules' => [
					['if' =>			['field' => 'type', 'in' => implode(',', [SERVICE_TIME_TYPE_DOWNTIME, SERVICE_TIME_TYPE_UPTIME])], 'type' => API_INT32, 'in' => '0:'.SEC_PER_WEEK],
					['if' =>			['field' => 'type', 'in' => implode(',', [SERVICE_TIME_TYPE_ONETIME_DOWNTIME])], 'type' => API_INT32, 'in' => '0:'.ZBX_MAX_DATE]
				]],
				'ts_to' =>			['type' => API_MULTIPLE, 'flags' => API_REQUIRED, 'rules' => [
					['if' =>			['field' => 'type', 'in' => implode(',', [SERVICE_TIME_TYPE_DOWNTIME, SERVICE_TIME_TYPE_UPTIME])], 'type' => API_INT32, 'in' => '0:'.SEC_PER_WEEK],
					['if' =>			['field' => 'type', 'in' => implode(',', [SERVICE_TIME_TYPE_ONETIME_DOWNTIME])], 'type' => API_INT32, 'in' => '0:'.ZBX_MAX_DATE]
				]],
				'note' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('services_times', 'note'), 'default' => DB::getDefault('services_times', 'note')]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $services, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$this->checkGoodSla($services);
		$this->checkAlgorithmDependencies($services);
		$this->checkParents($services);
		$this->checkChildren($services);
		$this->checkCircularReferences($services);
		$this->checkTimes($services);
	}

	/**
	 * @param array $services
	 *
	 * @return array
	 *
	 * @throws APIException
	 */
	public function update(array $services): array {
		$this->validateUpdate($services, $db_services);

<<<<<<< HEAD
		$upd_services = [];

		foreach ($services as $service) {
			$upd_service = DB::getUpdatedValues('services', $service, $db_services[$service['serviceid']]);

			if ($upd_service) {
				$upd_services[] = [
					'values' => $upd_service,
					'where' => ['serviceid' => $service['serviceid']]
				];
			}
=======
		// save the services
		$serviceids = DB::insert($this->tableName(), $services);

		$dependencies = [];
		$serviceTimes = [];
		foreach ($services as $key => &$service) {
			$service['serviceid'] = $serviceids[$key];

			// save dependencies
			if (!empty($service['dependencies'])) {
				foreach ($service['dependencies'] as $dependency) {
					$dependency['serviceid'] = $service['serviceid'];
					$dependencies[] = $dependency;
				}
			}

			// save parent service
			if (!empty($service['parentid'])) {
				$dependencies[] = [
					'serviceid' => $service['parentid'],
					'dependsOnServiceid' => $service['serviceid'],
					'soft' => 0
				];
			}

			// save service times
			if (isset($service['times'])) {
				foreach ($service['times'] as $serviceTime) {
					$serviceTime['serviceid'] = $service['serviceid'];
					$serviceTimes[] = $serviceTime;
				}
			}
>>>>>>> f7ff6b10458221dd1bff0e88f90ac7c148f76ed2
		}
		unset($service);

		if ($upd_services) {
			DB::update('services', $upd_services);
		}

		$services = array_column($services, null, 'serviceid');

		$this->updateTags($services, __FUNCTION__);
		$this->updateProblemTags($services, __FUNCTION__);
		$this->updateParents($services, __FUNCTION__);
		$this->updateChildren($services, __FUNCTION__);
		$this->updateTimes($services, __FUNCTION__);

<<<<<<< HEAD
		$this->addAuditBulk(AUDIT_ACTION_UPDATE, self::AUDIT_RESOURCE, $services, $db_services);
=======
		$this->addAuditBulk(AUDIT_ACTION_ADD, AUDIT_RESOURCE_IT_SERVICE, $services);
>>>>>>> f7ff6b10458221dd1bff0e88f90ac7c148f76ed2

		return ['serviceids' => array_column($services, 'serviceid')];
	}

	/**
<<<<<<< HEAD
	 * @param array      $services
	 * @param array|null $db_services
	 *
	 * @throws APIException
	 */
	private function validateUpdate(array &$services, array &$db_services = null): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['serviceid']], 'fields' => [
			'serviceid' =>		['type' => API_ID, 'flags' => API_REQUIRED],
			'name' =>			['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('services', 'name')],
			'algorithm' =>		['type' => API_INT32, 'in' => implode(',', [SERVICE_ALGORITHM_MAX, SERVICE_ALGORITHM_MIN, SERVICE_ALGORITHM_NONE])],
			'showsla' =>		['type' => API_INT32, 'in' => implode(',', [SERVICE_SHOW_SLA_OFF, SERVICE_SHOW_SLA_ON])],
			'goodsla' =>		['type' => API_FLOAT, 'in' => '0:100'],
			'sortorder' =>		['type' => API_INT32, 'in' => '0:999'],
			'tags' =>			['type' => API_OBJECTS, 'uniq' => [['tag', 'value']], 'fields' => [
				'tag' =>			['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('service_tag', 'tag')],
				'value' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('service_tag', 'value'), 'default' => DB::getDefault('service_tag', 'value')]
			]],
			'problem_tags' =>	['type' => API_OBJECTS, 'uniq' => [['tag']], 'fields' => [
				'tag' =>			['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('service_problem_tag', 'tag')],
				'operator' =>		['type' => API_INT32, 'in' => implode(',', [TAG_OPERATOR_LIKE, TAG_OPERATOR_EQUAL]), 'default' => DB::getDefault('service_problem_tag', 'operator')],
				'value' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('service_problem_tag', 'value'), 'default' => DB::getDefault('service_problem_tag', 'value')]
			]],
			'parents' =>		['type' => API_OBJECTS, 'uniq' => [['serviceid']], 'fields' => [
				'serviceid' =>		['type' => API_ID]
			]],
			'children' =>		['type' => API_OBJECTS, 'uniq' => [['serviceid']], 'fields' => [
				'serviceid' =>		['type' => API_ID]
			]],
			'times' =>			['type' => API_OBJECTS, 'uniq' => [['type', 'ts_from', 'ts_to']], 'fields' => [
				'type' =>		['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [SERVICE_TIME_TYPE_UPTIME, SERVICE_TIME_TYPE_DOWNTIME, SERVICE_TIME_TYPE_ONETIME_DOWNTIME])],
				'ts_from' =>		['type' => API_MULTIPLE, 'flags' => API_REQUIRED, 'rules' => [
					['if' =>			['field' => 'type', 'in' => implode(',', [SERVICE_TIME_TYPE_DOWNTIME, SERVICE_TIME_TYPE_UPTIME])], 'type' => API_INT32, 'in' => '0:'.SEC_PER_WEEK],
					['if' =>			['field' => 'type', 'in' => implode(',', [SERVICE_TIME_TYPE_ONETIME_DOWNTIME])], 'type' => API_INT32, 'in' => '0:'.ZBX_MAX_DATE]
				]],
				'ts_to' =>			['type' => API_MULTIPLE, 'flags' => API_REQUIRED, 'rules' => [
					['if' =>			['field' => 'type', 'in' => implode(',', [SERVICE_TIME_TYPE_DOWNTIME, SERVICE_TIME_TYPE_UPTIME])], 'type' => API_INT32, 'in' => '0:'.SEC_PER_WEEK],
					['if' =>			['field' => 'type', 'in' => implode(',', [SERVICE_TIME_TYPE_ONETIME_DOWNTIME])], 'type' => API_INT32, 'in' => '0:'.ZBX_MAX_DATE]
				]],
				'note' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('services_times', 'note'), 'default' => DB::getDefault('services_times', 'note')]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $services, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_services = $this->get([
			'output' => ['serviceid', 'name', 'status', 'algorithm', 'showsla', 'goodsla', 'sortorder'],
			'selectParents' => ['serviceid'],
			'selectChildren' => ['serviceid'],
			'selectProblemTags' => API_OUTPUT_COUNT,
			'serviceids' => array_column($services, 'serviceid'),
			'editable' => true,
			'preservekeys' => true
		]);
=======
	 * Validates the input parameters for the update() method.
	 *
	 * @param array $services
	 * @param array $db_services
	 *
	 * @throws APIException if the input is invalid
	 */
	public function validateUpdate(array $services, array $db_services) {
		foreach ($services as $service) {
			if (empty($service['serviceid'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Invalid method parameters.'));
			}
		}

		$this->checkServicePermissions(array_column($services, 'serviceid'));

		$services = $this->extendFromObjects(zbx_toHash($services, 'serviceid'), $db_services, ['name']);

		foreach ($services as $service) {
			$this->checkName($service);

			if (array_key_exists('algorithm', $service)) {
				$this->checkAlgorithm($service);
			}
			if (array_key_exists('showsla', $service)) {
				$this->checkShowSla($service);
			}
			if (array_key_exists('goodsla', $service)) {
				$this->checkGoodSla($service);
			}
			if (array_key_exists('sortorder', $service)) {
				$this->checkSortOrder($service);
			}
			if (array_key_exists('triggerid', $service)) {
				$this->checkTriggerId($service);
			}
			if (array_key_exists('status', $service)) {
				$this->checkStatus($service);
			}
			if (array_key_exists('parentid', $service)) {
				$this->checkParentId($service);
			}
>>>>>>> f7ff6b10458221dd1bff0e88f90ac7c148f76ed2

		if (count($db_services) != count($services)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		$this->checkGoodSla($services, $db_services);
		$this->checkAlgorithmDependencies($services, $db_services);
		$this->checkParents($services);
		$this->checkChildren($services);
		$this->checkCircularReferences($services, $db_services);
		$this->checkTimes($services);
	}

	/**
	 * @param array $serviceids
	 *
	 * @return array
	 *
	 * @throws APIException
	 */
<<<<<<< HEAD
	public function delete(array $serviceids): array {
		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];
=======
	public function update(array $services) {
		$services = zbx_toArray($services);

		$db_services = $this->get([
			'output' => ['serviceid', 'name', 'triggerid', 'algorithm', 'parentid', 'showsla', 'goodsla', 'sortorder'],
			'serviceids' => array_column($services, 'serviceid'),
			'preservekeys' => true
		]);

		$this->validateUpdate($services, $db_services);
>>>>>>> f7ff6b10458221dd1bff0e88f90ac7c148f76ed2

		if (!CApiInputValidator::validate($api_input_rules, $serviceids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_services = $this->get([
			'output' => ['serviceid', 'name'],
			'serviceids' => $serviceids,
			'editable' => true
		]);

		if (count($db_services) != count($serviceids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		DB::delete('services', ['serviceid' => $serviceids]);

		$this->addAuditBulk(AUDIT_ACTION_DELETE, self::AUDIT_RESOURCE, $db_services);

		return ['serviceids' => $serviceids];
	}

	protected function applyQueryFilterOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		$sqlParts = parent::applyQueryFilterOptions($tableName, $tableAlias, $options, $sqlParts);

		if ($options['parentids'] !== null) {
			$sqlParts['left_table'] = ['table' => 'services', 'alias' => 's'];
			$sqlParts['left_join'][] = [
				'table' => 'services_links',
				'alias' => 'slp',
				'using' => 'servicedownid',
			];
			$sqlParts['where'][] = dbConditionId('slp.serviceupid', $options['parentids']);
		}

		if ($options['childids'] !== null) {
			$sqlParts['left_table'] = ['table' => 'services', 'alias' => 's'];
			$sqlParts['left_join'][] = [
				'table' => 'services_links',
				'alias' => 'slc',
				'using' => 'serviceupid',
			];
			$sqlParts['where'][] = dbConditionId('slc.servicedownid', $options['childids']);
		}

		if ($options['tags']) {
			$sqlParts['where'][] = CApiTagHelper::addWhereCondition($options['tags'], $options['evaltype'], 's',
				'service_tag', 'serviceid'
			);
		}

		if ($options['problem_tags']) {
			$sqlParts['where'][] = CApiTagHelper::addWhereCondition($options['problem_tags'], $options['evaltype'], 's',
				'service_problem_tag', 'serviceid'
			);
		}
		elseif ($options['without_problem_tags']) {
			$sqlParts['left_table'] = ['table' => 'services', 'alias' => 's'];
			$sqlParts['left_join'][] = [
				'table' => 'service_problem_tag',
				'alias' => 'spt',
				'using' => 'serviceid',
			];
			$sqlParts['where'][] = dbConditionId('spt.service_problem_tagid', [0]);
		}

		return $sqlParts;
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$serviceids = array_keys($result);

		if ($options['selectParents'] !== null) {
			$relation_map = $this->createRelationMap($result, 'servicedownid', 'serviceupid', 'services_links');
			$parents = $this->get([
				'output' => ($options['selectParents'] === API_OUTPUT_COUNT) ? [] : $options['selectParents'],
				'serviceids' => $relation_map->getRelatedIds(),
				'sortfield' => $options['sortfield'],
				'sortorder' => $options['sortorder'],
				'preservekeys' => true
			]);
			$result = $relation_map->mapMany($result, $parents, 'parents');

			if ($options['selectParents'] === API_OUTPUT_COUNT) {
				foreach ($result as &$row) {
					$row['parents'] = (string) count($row['parents']);
				}
				unset($row);
			}
		}

		if ($options['selectChildren'] !== null) {
			$relation_map = $this->createRelationMap($result, 'serviceupid', 'servicedownid', 'services_links');
			$children = $this->get([
				'output' => ($options['selectChildren'] === API_OUTPUT_COUNT) ? [] : $options['selectChildren'],
				'serviceids' => $relation_map->getRelatedIds(),
				'sortfield' => $options['sortfield'],
				'sortorder' => $options['sortorder'],
				'preservekeys' => true
			]);
			$result = $relation_map->mapMany($result, $children, 'children');

			if ($options['selectChildren'] === API_OUTPUT_COUNT) {
				foreach ($result as &$row) {
					$row['children'] = (string) count($row['children']);
				}
				unset($row);
			}
		}

		if ($options['selectTags'] !== null) {
			if ($options['selectTags'] === API_OUTPUT_COUNT) {
				$output = ['servicetagid', 'serviceid'];
			}
			elseif ($options['selectTags'] === API_OUTPUT_EXTEND) {
				$output = ['servicetagid', 'serviceid', 'tag', 'value'];
			}
			else {
				$output = array_unique(array_merge(['servicetagid', 'serviceid'], $options['selectTags']));
			}

			$tags = DB::select('service_tag', [
				'output' => $output,
				'filter' => ['serviceid' => $serviceids],
				'preservekeys' => true
			]);
			$relation_map = $this->createRelationMap($tags, 'serviceid', 'servicetagid');
			$tags = $this->unsetExtraFields($tags, ['servicetagid', 'serviceid'], $options['selectTags']);
			$result = $relation_map->mapMany($result, $tags, 'tags');

			if ($options['selectTags'] === API_OUTPUT_COUNT) {
				foreach ($result as &$row) {
					$row['tags'] = (string) count($row['tags']);
				}
				unset($row);
			}
		}

		if ($options['selectProblemTags'] !== null) {
			if ($options['selectProblemTags'] === API_OUTPUT_COUNT) {
				$output = ['service_problem_tagid', 'serviceid'];
			}
			elseif ($options['selectProblemTags'] === API_OUTPUT_EXTEND) {
				$output = ['service_problem_tagid', 'serviceid', 'tag', 'operator', 'value'];
			}
			else {
				$output = array_unique(array_merge(['service_problem_tagid', 'serviceid'],
					$options['selectProblemTags']
				));
			}

			$problem_tags = DB::select('service_problem_tag', [
				'output' => $output,
				'filter' => ['serviceid' => $serviceids],
				'preservekeys' => true
			]);
			$relation_map = $this->createRelationMap($problem_tags, 'serviceid', 'service_problem_tagid');
			$problem_tags = $this->unsetExtraFields($problem_tags, ['service_problem_tagid', 'serviceid'],
				$options['selectProblemTags']
			);
			$result = $relation_map->mapMany($result, $problem_tags, 'problem_tags');

			if ($options['selectProblemTags'] === API_OUTPUT_COUNT) {
				foreach ($result as &$row) {
					$row['problem_tags'] = (string) count($row['problem_tags']);
				}
				unset($row);
			}
		}

		if ($options['selectTimes'] !== null) {
			if ($options['selectTimes'] === API_OUTPUT_COUNT) {
				$output = ['timeid', 'serviceid'];
			}
			elseif ($options['selectTimes'] === API_OUTPUT_EXTEND) {
				$output = ['timeid', 'serviceid', 'type', 'ts_from', 'ts_to', 'note'];
			}
			else {
				$output = array_unique(array_merge(['timeid', 'serviceid'], $options['selectTimes']));
			}

			$times = DB::select('services_times', [
				'output' => $output,
				'filter' => ['serviceid' => $serviceids],
				'preservekeys' => true
			]);
			$relation_map = $this->createRelationMap($times, 'serviceid', 'timeid');
			$times = $this->unsetExtraFields($times, ['timeid', 'serviceid'], $options['selectTimes']);
			$result = $relation_map->mapMany($result, $times, 'times');
		}

		if ($options['selectAlarms'] !== null) {
			if ($options['selectAlarms'] === API_OUTPUT_COUNT) {
				$output = ['servicealarmid', 'serviceid'];
			}
			elseif ($options['selectAlarms'] === API_OUTPUT_EXTEND) {
				$output = ['servicealarmid', 'serviceid', 'clock', 'value'];
			}
			else {
				$output = array_unique(array_merge(['servicealarmid', 'serviceid'], $options['selectAlarms']));
			}

			$alarms = DB::select('service_alarms', [
				'output' => $output,
				'filter' => ['serviceid' => $serviceids],
				'preservekeys' => true
			]);
			$relation_map = $this->createRelationMap($alarms, 'serviceid', 'servicealarmid');
			$alarms = $this->unsetExtraFields($alarms, ['servicealarmid', 'serviceid'], ['selectAlarms']);
			$result = $relation_map->mapMany($result, $alarms, 'alarms');
		}

<<<<<<< HEAD
		return $result;
	}

	/**
	 * @param array      $services
	 * @param array|null $db_services
	 *
	 * @throws APIException
	 */
	private function checkGoodSla(array $services, array $db_services = null): void {
		foreach ($services as $service) {
			$name = array_key_exists('name', $service)
				? $service['name']
				: $db_services[$service['serviceid']]['name'];

			if (array_key_exists('goodsla', $service)
					&& round($service['goodsla'], 4) != $service['goodsla']) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Service "%1$s" must have no more than 4 digits after the decimal point.', $name)
				);
			}
		}
	}

	/**
	 * @param array      $services
	 * @param array|null $db_services
=======
		$this->addAuditBulk(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_IT_SERVICE, $services, $db_services);

		return ['serviceids' => zbx_objectValues($services, 'serviceid')];
	}

	/**
	 * Validates the input parameters for the delete() method.
	 *
	 * @param array $serviceids
	 * @param array $db_services
	 *
	 * @throws APIException if the input is invalid
	 */
	public function validateDelete(array $serviceids, array &$db_services = null) {
		if (!$serviceids) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		$this->checkServicePermissions($serviceids, $db_services);
		$this->checkThatServicesDontHaveChildren($serviceids);
	}

	/**
	 * Delete services.
	 *
	 * @param array $serviceids
>>>>>>> f7ff6b10458221dd1bff0e88f90ac7c148f76ed2
	 *
	 * @throws APIException
	 */
<<<<<<< HEAD
	private function checkAlgorithmDependencies(array $services, array $db_services = null): void {
		foreach ($services as $service) {
			$name = array_key_exists('name', $service)
				? $service['name']
				: $db_services[$service['serviceid']]['name'];

			$algorithm = array_key_exists('algorithm', $service)
				? $service['algorithm']
				: $db_services[$service['serviceid']]['algorithm'];

			if (array_key_exists('problem_tags', $service)) {
				$has_problem_tags = count($service['problem_tags']) > 0;
			}
			elseif ($db_services !== null) {
				$has_problem_tags = $db_services[$service['serviceid']]['problem_tags'] > 0;
			}
			else {
				$has_problem_tags = false;
			}

			if ($algorithm == SERVICE_ALGORITHM_NONE) {
				$showsla = array_key_exists('showsla', $service)
					? $service['showsla']
					: $db_services[$service['serviceid']]['showsla'];

				if ($showsla == SERVICE_SHOW_SLA_ON) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Service "%1$s" cannot show SLA for the selected status calculation algorithm.', $name)
					);
				}

				if ($has_problem_tags) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Service "%1$s" cannot have problem tags with the selected status calculation algorithm.',
							$name
						)
					);
				}
			}
=======
	public function delete(array $serviceids) {
		$this->validateDelete($serviceids, $db_services);

		DB::delete($this->tableName(), ['serviceid' => $serviceids]);
>>>>>>> f7ff6b10458221dd1bff0e88f90ac7c148f76ed2

			if (array_key_exists('children', $service)) {
				$has_children = count($service['children']) > 0;
			}
			elseif ($db_services !== null) {
				$has_children = count($db_services[$service['serviceid']]['children']) > 0;
			}
			else {
				$has_children = false;
			}

<<<<<<< HEAD
			if ($has_problem_tags && $has_children) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Service "%1$s" cannot have problem tags and children at the same time.', $name)
				);
			}
		}
=======
		$this->addAuditBulk(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_IT_SERVICE, $db_services);

		return ['serviceids' => $serviceids];
>>>>>>> f7ff6b10458221dd1bff0e88f90ac7c148f76ed2
	}

	/**
	 * @param array $services
	 *
	 * @throws APIException
	 */
	private function checkParents(array $services): void {
		$parent_serviceids = [];

		foreach ($services as $service) {
			if (array_key_exists('parents', $service)) {
				$parent_serviceids += array_column($service['parents'], 'serviceid', 'serviceid');
			}
		}

		if (!$parent_serviceids) {
			return;
		}

		$db_parent_services = $this->get([
			'output' => ['name'],
			'selectProblemTags' => API_OUTPUT_COUNT,
			'serviceids' => $parent_serviceids,
			'editable' => true,
			'preservekeys' => true
		]);

		if (count($db_parent_services) != count($parent_serviceids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		foreach ($db_parent_services as $db_parent_service) {
			if ($db_parent_service['problem_tags'] > 0) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Service "%1$s" cannot have problem tags and children at the same time.',
						$db_parent_service['name']
					)
				);
			}
		}
	}

	/**
	 * @param array $services
	 *
	 * @throws APIException
	 */
	private function checkChildren(array $services): void {
		$child_serviceids = [];

		foreach ($services as $service) {
			if (array_key_exists('children', $service)) {
				$child_serviceids += array_column($service['children'], 'serviceid', 'serviceid');
			}
		}

<<<<<<< HEAD
		if (!$child_serviceids) {
			return;
		}

		$count = $this->get([
			'countOutput' => true,
			'serviceids' => $child_serviceids,
			'editable' => true
		]);

		if ($count != count($child_serviceids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}
=======
	/**
	 * Validates the input for the deleteDependencies() method.
	 *
	 * @throws APIException if the given input is invalid
	 *
	 * @param array $serviceids
	 */
	protected function validateDeleteDependencies(array $serviceids) {
		if (!$serviceids) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		$this->checkServicePermissions($serviceids);
>>>>>>> f7ff6b10458221dd1bff0e88f90ac7c148f76ed2
	}

	/**
	 * @param array      $services
	 * @param array|null $db_services
	 *
<<<<<<< HEAD
	 * @throws APIException
	 */
	private function checkCircularReferences(array $services, array $db_services = null): void {
		$add_references = [];
		$del_references = [];

		foreach ($services as $service) {
			if ($db_services !== null) {
				$db_service = $db_services[$service['serviceid']];

				if (array_key_exists('parents', $service)) {
					foreach ($db_service['parents'] as $parent) {
						$del_references[$parent['serviceid']][$service['serviceid']] = true;
					}
					foreach ($service['parents'] as $parent) {
						$add_references[$parent['serviceid']][$service['serviceid']] = true;
					}
				}

				if (array_key_exists('children', $service)) {
					foreach ($db_service['children'] as $child) {
						$del_references[$service['serviceid']][$child['serviceid']] = true;
					}
					foreach ($service['children'] as $child) {
						$add_references[$service['serviceid']][$child['serviceid']] = true;
					}
				}
			}
			else if (array_key_exists('parents', $service) && array_key_exists('children', $service)) {
				foreach ($service['children'] as $child) {
					foreach ($service['parents'] as $parent) {
						$add_references[$parent['serviceid']][$child['serviceid']] = true;
					}
				}
			}
		}

		if ($this->hasCircularReferences($add_references, $del_references)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Services form a circular dependency.'));
		}
	}

	/**
	 * @param array $add_references
	 * @param array $del_references
	 *
	 * @return bool
	 */
	private function hasCircularReferences(array $add_references, array $del_references): bool {
		$reverse_references = [];

		foreach ($add_references as $child_serviceid => $parents) {
			foreach (array_keys($parents) as $parent_serviceid) {
				$reverse_references[$parent_serviceid][$child_serviceid] = true;
			}
		}

		while ($add_references) {
			$db_links = DB::select('services_links', [
				'output' => ['serviceupid', 'servicedownid'],
				'filter' => ['servicedownid' => array_keys($add_references)]
			]);

			$db_parents = [];

			foreach ($db_links as $db_link) {
				if (!array_key_exists($db_link['servicedownid'], $del_references)
						|| !array_key_exists($db_link['serviceupid'], $del_references[$db_link['servicedownid']])) {
					$db_parents[$db_link['servicedownid']][$db_link['serviceupid']] = true;
				}
			}

			$next_references = [];

			foreach ($add_references as $child_serviceid => $parents) {
				foreach (array_keys($parents) as $parent_serviceid) {
					if ((string) $child_serviceid === (string) $parent_serviceid) {
						return true;
					}

					if (array_key_exists($child_serviceid, $reverse_references)) {
						foreach (array_keys($reverse_references[$child_serviceid]) as $serviceid) {
							$next_references[$serviceid][$parent_serviceid] = true;
						}
					}
=======
	 * @param array $serviceids
	 *
	 * @return boolean
	 */
	public function deleteDependencies($serviceids) {
		$serviceids = zbx_toArray($serviceids);
		$this->validateDeleteDependencies($serviceids);

		DB::delete('services_links', [
			'serviceupid' =>  $serviceids
		]);

		return ['serviceids' => $serviceids];
	}

	/**
	 * Validates the input for the addTimes() method.
	 *
	 * @param array $service_times
	 *
	 * @throws APIException if the given input is invalid
	 */
	public function validateAddTimes(array $service_times) {
		foreach ($service_times as $service_time) {
			$this->checkTime($service_time);

			$this->checkUnsupportedFields('services_times', $service_time,
				_s('Wrong fields for time for service "%1$s".', $service_time['serviceid'])
			);
		}

		$this->checkServicePermissions(array_column($service_times, 'serviceid'));
	}

	/**
	 * Adds the given service times.
	 *
	 * @param array $service_times an array of service times
	 *
	 * @return array
	 */
	public function addTimes(array $service_times) {
		$service_times = zbx_toArray($service_times);
		$this->validateAddTimes($service_times);

		DB::insert('services_times', $service_times);

		return ['serviceids' => array_column($service_times, 'serviceid')];
	}

	/**
	 * Validates the input for the deleteTimes() method.
	 *
	 * @throws APIException if the given input is invalid
	 *
	 * @param array $serviceids
	 */
	protected function validateDeleteTimes(array $serviceids) {
		if (!$serviceids) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		$this->checkServicePermissions($serviceids);
	}
>>>>>>> f7ff6b10458221dd1bff0e88f90ac7c148f76ed2

					if (array_key_exists($child_serviceid, $db_parents)) {
						foreach (array_keys($db_parents[$child_serviceid]) as $serviceid) {
							$next_references[$serviceid][$parent_serviceid] = true;
						}
					}
				}
			}

			$add_references = $next_references;
		}

		return false;
	}

	/**
	 * @param array $services
	 *
	 * @throws APIException
	 */
	private function checkTimes(array $services): void {
		foreach ($services as $service) {
			if (!array_key_exists('times', $service)) {
				continue;
			}

			foreach ($service['times'] as $time) {
				if ($time['ts_from'] >= $time['ts_to']) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Service start time must be less than end time.'));
				}
			}
		}
	}

	/**
	 * @param array  $services
	 * @param string $method
	 */
	private function updateTags(array $services, string $method): void {
		$serviceids = [];

		foreach ($services as $serviceid => $service) {
			if (array_key_exists('tags', $service)) {
				$serviceids[$serviceid] = true;
			}
		}

		if (!$serviceids) {
			return;
		}

		$del_tags = [];
		$ins_tags = [];

		if ($method === 'update') {
			$db_tags = DB::select('service_tag', [
				'output' => ['servicetagid', 'serviceid', 'tag', 'value'],
				'filter' => ['serviceid' => array_keys($serviceids)]
			]);

			foreach ($db_tags as $db_tag) {
				$del_tags[$db_tag['serviceid']][$db_tag['tag']][$db_tag['value']] = $db_tag['servicetagid'];
			}
		}

		foreach (array_keys($serviceids) as $serviceid) {
			foreach ($services[$serviceid]['tags'] as $tag) {
				if (array_key_exists($serviceid, $del_tags)
						&& array_key_exists($tag['tag'], $del_tags[$serviceid])
						&& array_key_exists($tag['value'], $del_tags[$serviceid][$tag['tag']])) {
					unset($del_tags[$serviceid][$tag['tag']][$tag['value']]);
				}
				else {
					$ins_tags[] = ['serviceid' => $serviceid] + $tag;
				}
			}
		}

		if ($del_tags) {
			$del_servicetagids = [];

			foreach ($del_tags as $del_tags) {
				foreach ($del_tags as $del_tags) {
					foreach ($del_tags as $servicetagid) {
						$del_servicetagids[$servicetagid] = true;
					}
				}
			}

			DB::delete('service_tag', ['servicetagid' => array_keys($del_servicetagids)]);
		}

		if ($ins_tags) {
			DB::insertBatch('service_tag', $ins_tags);
		}
	}

	/**
	 * @param array  $services
	 * @param string $method
	 */
	private function updateProblemTags(array $services, string $method): void {
		$serviceids = [];

		foreach ($services as $serviceid => $service) {
			if (array_key_exists('problem_tags', $service)) {
				$serviceids[$serviceid] = true;
			}
		}

		if (!$serviceids) {
			return;
		}

		$del_problem_tags = [];
		$ins_problem_tags = [];

		if ($method === 'update') {
			$db_problem_tags = DB::select('service_problem_tag', [
				'output' => ['service_problem_tagid', 'serviceid', 'tag', 'operator', 'value'],
				'filter' => ['serviceid' => array_keys($serviceids)]
			]);

			foreach ($db_problem_tags as $db_problem_tag) {
				$del_problem_tags[$db_problem_tag['serviceid']][$db_problem_tag['tag']][$db_problem_tag['operator']]
					[$db_problem_tag['value']] = $db_problem_tag['service_problem_tagid'];
			}
		}

		foreach (array_keys($serviceids) as $serviceid) {
			foreach ($services[$serviceid]['problem_tags'] as $problem_tag) {
				if (array_key_exists($serviceid, $del_problem_tags)
						&& array_key_exists($problem_tag['tag'], $del_problem_tags[$serviceid])
						&& array_key_exists($problem_tag['operator'],
							$del_problem_tags[$serviceid][$problem_tag['tag']]
						)
						&& array_key_exists($problem_tag['value'],
							$del_problem_tags[$serviceid][$problem_tag['tag']][$problem_tag['operator']]
						)) {
					unset($del_problem_tags[$serviceid][$problem_tag['tag']][$problem_tag['operator']]
						[$problem_tag['value']]
					);
				}
				else {
					$ins_problem_tags[] = ['serviceid' => $serviceid] + $problem_tag;
				}
			}
		}

		if ($del_problem_tags) {
			$del_service_problem_tagids = [];

			foreach ($del_problem_tags as $del_problem_tags) {
				foreach ($del_problem_tags as $del_problem_tags) {
					foreach ($del_problem_tags as $del_problem_tags) {
						foreach ($del_problem_tags as $service_problem_tagid) {
							$del_service_problem_tagids[$service_problem_tagid] = true;
						}
					}
				}
			}

			DB::delete('service_problem_tag', ['service_problem_tagid' => array_keys($del_service_problem_tagids)]);
		}

		if ($ins_problem_tags) {
			DB::insertBatch('service_problem_tag', $ins_problem_tags);
		}
	}

	/**
	 * @param array  $services
	 * @param string $method
	 */
	private function updateParents(array $services, string $method): void {
		$serviceids = [];

		foreach ($services as $serviceid => $service) {
			if (array_key_exists('parents', $service)) {
				$serviceids[$serviceid] = true;
			}
		}

		if (!$serviceids) {
			return;
		}

		$del_parents = [];
		$ins_parents = [];

		if ($method === 'update') {
			$db_parents = DB::select('services_links', [
				'output' => ['linkid', 'serviceupid', 'servicedownid'],
				'filter' => ['servicedownid' => array_keys($serviceids)]
			]);

			foreach ($db_parents as $db_parent) {
				$del_parents[$db_parent['servicedownid']][$db_parent['serviceupid']] = $db_parent['linkid'];
			}
		}

		foreach (array_keys($serviceids) as $serviceid) {
			foreach ($services[$serviceid]['parents'] as $parent) {
				if (array_key_exists($serviceid, $del_parents)
						&& array_key_exists($parent['serviceid'], $del_parents[$serviceid])) {
					unset($del_parents[$serviceid][$parent['serviceid']]);
				}
				else {
					$ins_parents[] = ['serviceupid' => $parent['serviceid'], 'servicedownid' => $serviceid];
				}
			}
		}

		if ($del_parents) {
			$del_linkids = [];

			foreach ($del_parents as $del_parents) {
				foreach ($del_parents as $linkid) {
					$del_linkids[$linkid] = true;
				}
			}

			DB::delete('services_links', ['linkid' => array_keys($del_linkids)]);
		}

		if ($ins_parents) {
			DB::insertBatch('services_links', $ins_parents);
		}
	}

	/**
	 * @param array  $services
	 * @param string $method
	 */
	private function updateChildren(array $services, string $method): void {
		$serviceids = [];

		foreach ($services as $serviceid => $service) {
			if (array_key_exists('children', $service)) {
				$serviceids[$serviceid] = true;
			}
		}

		if (!$serviceids) {
			return;
		}

		$del_children = [];
		$ins_children = [];

		if ($method === 'update') {
			$db_children = DB::select('services_links', [
				'output' => ['linkid', 'serviceupid', 'servicedownid'],
				'filter' => ['serviceupid' => array_keys($serviceids)]
			]);

			foreach ($db_children as $db_child) {
				$del_children[$db_child['serviceupid']][$db_child['servicedownid']] = $db_child['linkid'];
			}
		}

		foreach (array_keys($serviceids) as $serviceid) {
			foreach ($services[$serviceid]['children'] as $child) {
				if (array_key_exists($serviceid, $del_children)
						&& array_key_exists($child['serviceid'], $del_children[$serviceid])) {
					unset($del_children[$serviceid][$child['serviceid']]);
				}
				else {
					$ins_children[] = ['serviceupid' => $serviceid, 'servicedownid' => $child['serviceid']];
				}
			}
		}

		if ($del_children) {
			$del_linkids = [];

			foreach ($del_children as $del_children) {
				foreach ($del_children as $linkid) {
					$del_linkids[$linkid] = true;
				}
			}

			DB::delete('services_links', ['linkid' => array_keys($del_linkids)]);
		}

		if ($ins_children) {
			DB::insertBatch('services_links', $ins_children);
		}
	}

	/**
	 * @param array  $services
	 * @param string $method
	 */
	private function updateTimes(array $services, string $method): void {
		$serviceids = [];

		foreach ($services as $serviceid => $service) {
			if (array_key_exists('times', $service)) {
				$serviceids[$serviceid] = true;
			}
		}

		if (!$serviceids) {
			return;
		}

		$del_times = [];
		$ins_times = [];
		$upd_times = [];

		if ($method === 'update') {
			$db_times = DB::select('services_times', [
				'output' => ['timeid', 'serviceid', 'type', 'ts_from', 'ts_to', 'note'],
				'filter' => ['serviceid' => array_keys($serviceids)]
			]);

			foreach ($db_times as $db_time) {
				$del_times[$db_time['serviceid']][$db_time['type']][$db_time['ts_from']][$db_time['ts_to']] = [
					'timeid' => $db_time['timeid'],
					'fields' => [
						'note' => $db_time['note']
					]
				];
			}
		}

		foreach (array_keys($serviceids) as $serviceid) {
			foreach ($services[$serviceid]['times'] as $time) {
				if (array_key_exists($serviceid, $del_times)
						&& array_key_exists($time['type'], $del_times[$serviceid])
						&& array_key_exists($time['ts_from'], $del_times[$serviceid][$time['type']])
						&& array_key_exists($time['ts_to'], $del_times[$serviceid][$time['type']][$time['ts_from']])) {
					$db_time = $del_times[$serviceid][$time['type']][$time['ts_from']][$time['ts_to']];

					$upd_time = DB::getUpdatedValues('services_times', $time, $db_time['fields']);

					if ($upd_time) {
						$upd_times[] = [
							'values' => $upd_time,
							'where' => ['timeid' => $db_time['timeid']]
						];
					}

					unset($del_times[$serviceid][$time['type']][$time['ts_from']][$time['ts_to']]);
				}
				else {
					$ins_times[] = ['serviceid' => $serviceid] + $time;
				}
			}
		}

		if ($del_times) {
			$del_timeids = [];

			foreach ($del_times as $del_times) {
				foreach ($del_times as $del_times) {
					foreach ($del_times as $del_times) {
						foreach ($del_times as $del_times) {
							$del_timeids[$del_times['timeid']] = true;
						}
					}
				}
			}

			DB::delete('services_times', ['timeid' => array_keys($del_timeids)]);
		}

		if ($ins_times) {
			DB::insertBatch('services_times', $ins_times);
		}

		if ($upd_times) {
			DB::update('services_times', $upd_times);
		}
	}

	// Methods related to an SLA calculation - to be reworked.

	/**
	 * Returns availability-related information about the given services during the given time intervals.
	 *
	 * Available options:
	 *  - serviceids    - a single service ID or an array of service IDs;
	 *  - intervals     - a single time interval or an array of time intervals, each containing:
	 *      - from          - the beginning of the interval, timestamp;
	 *      - to            - the end of the interval, timestamp.
	 *
	 * Returns the following availability information for each service:
	 *  - status            - the current status of the service;
	 *  - problems          - an array of triggers that are currently in problem state and belong to the given service
	 *                        or it's descendants;
	 *  - sla               - an array of requested intervals with SLA information:
	 *      - from              - the beginning of the interval;
	 *      - to                - the end of the interval;
	 *      - okTime            - the time the service was in OK state, in seconds;
	 *      - problemTime       - the time the service was in problem state, in seconds;
	 *      - downtimeTime      - the time the service was down, in seconds.
	 *
	 * If the service calculation algorithm is set to SERVICE_ALGORITHM_NONE, the method will return an empty 'problems'
	 * array and null for all of the calculated values.
	 *
	 * @param array $options
	 *
	 * @return array    as array(serviceId2 => data1, serviceId2 => data2, ...)
	 */
	public function getSla(array $options) {
		$serviceIds = (isset($options['serviceids'])) ? zbx_toArray($options['serviceids']) : null;
		$intervals = (isset($options['intervals'])) ? zbx_toArray($options['intervals']) : [];

		// fetch services
		$services = $this->get([
			'output' => ['serviceid', 'name', 'status', 'algorithm'],
			'selectTimes' => API_OUTPUT_EXTEND,
			'selectParents' => ['serviceid'],
			'serviceids' => $serviceIds,
			'preservekeys' => true
		]);

		$rs = [];
		if ($services) {
			$usedSeviceIds = [];

			$problemServiceIds = [];
			foreach ($services as &$service) {
				// don't calculate SLA for services with disabled status calculation
				if ($this->isStatusEnabled($service)) {
					$usedSeviceIds[$service['serviceid']] = $service['serviceid'];
					$service['alarms'] = [];

					if ($service['status'] > 0) {
						$problemServiceIds[] = $service['serviceid'];
					}
				}
			}
			unset($service);

			// initial data
			foreach ($services as $service) {
				$rs[$service['serviceid']] = [
					'status' => ($this->isStatusEnabled($service)) ? $service['status'] : null,
					'problems' => [],
					'sla' => []
				];
			}

			if ($usedSeviceIds) {
				// add service alarms
				if ($intervals) {
					$intervalConditions = [];
					foreach ($intervals as $interval) {
						$intervalConditions[] = 'sa.clock BETWEEN '.zbx_dbstr($interval['from']).' AND '.zbx_dbstr($interval['to']);
					}
					$query = DBselect(
						'SELECT *'.
						' FROM service_alarms sa'.
						' WHERE '.dbConditionInt('sa.serviceid', $usedSeviceIds).
						' AND ('.implode(' OR ', $intervalConditions).')'.
						' ORDER BY sa.servicealarmid'
					);
					while ($data = DBfetch($query)) {
						$services[$data['serviceid']]['alarms'][] = $data;
					}
				}

				// add problem triggers
				if ($problemServiceIds) {
					$problemTriggers = $this->fetchProblemTriggers($problemServiceIds);
					$rs = $this->escalateProblems($services, $problemTriggers, $rs);
				}

				$slaCalculator = new CServicesSlaCalculator();

				// calculate SLAs
				foreach ($intervals as $interval) {
					$latestValues = $this->fetchLatestValues($usedSeviceIds, $interval['from']);

					foreach ($services as $service) {
						$serviceId = $service['serviceid'];

						// only calculate the sla for services which require it
						if (isset($usedSeviceIds[$serviceId])) {
							$latestValue = (isset($latestValues[$serviceId])) ? $latestValues[$serviceId] : 0;
							$intervalSla = $slaCalculator->calculateSla($service['alarms'], $service['times'],
								$interval['from'], $interval['to'], $latestValue
							);
						}
						else {
							$intervalSla = [
								'ok' => null,
								'okTime' => null,
								'problemTime' => null,
								'downtimeTime' => null
							];
						}

						$rs[$service['serviceid']]['sla'][] = [
							'from' => $interval['from'],
							'to' => $interval['to'],
							'sla' => $intervalSla['ok'],
							'okTime' => $intervalSla['okTime'],
							'problemTime' => $intervalSla['problemTime'],
							'downtimeTime' => $intervalSla['downtimeTime']
						];
					}
				}
			}
		}

		return $rs;
	}

	/**
	 * Returns true if status calculation is enabled for the given service.
	 *
	 * @param array $service
	 *
	 * @return bool
	 */
	protected function isStatusEnabled(array $service) {
		return ($service['algorithm'] != SERVICE_ALGORITHM_NONE);
	}

	/**
	 * Returns an array of triggers which are in a problem state and are linked to the given services.
	 *
	 * @param array $serviceIds
	 *
	 * @return array    in the form of array(serviceId1 => array(triggerId => trigger), ...)
	 */
	protected function fetchProblemTriggers(array $serviceIds) {
		$sql = 'SELECT s.serviceid,t.triggerid'.
			' FROM services s,triggers t'.
			' WHERE s.status>0'.
			' AND t.triggerid=s.triggerid'.
			' AND '.dbConditionInt('s.serviceid', $serviceIds).
			' ORDER BY s.status DESC,t.description';

		// get service reason
		$triggers = DBfetchArray(DBSelect($sql));

		$rs = [];
		foreach ($triggers as $trigger) {
			$serviceId = $trigger['serviceid'];
			unset($trigger['serviceid']);

			$rs[$serviceId] = [$trigger['triggerid'] => $trigger];
		}

		return $rs;
	}

	/**
	 * Escalates the problem triggers from the child services to their parents and adds them to $slaData.
	 * The escalation will stop if a service has status calculation disabled or is in OK state.
	 *
	 * @param array $services
	 * @param array $serviceProblems    an array of service triggers defines as
	 *                                  array(serviceId1 => array(triggerId => trigger), ...)
	 * @param array $slaData
	 *
	 * @return array
	 */
	protected function escalateProblems(array $services, array $serviceProblems, array $slaData) {
		$parentProblems = [];
		foreach ($serviceProblems as $serviceId => $problemTriggers) {
			$service = $services[$serviceId];

			// add the problem trigger of the current service to the data
			$slaData[$serviceId]['problems'] = zbx_array_merge($slaData[$serviceId]['problems'], $problemTriggers);

			// add the same trigger to the parent services
			foreach ($service['parents'] as $parent) {
				$parentServiceId = $parent['serviceid'];

				if (isset($services[$parentServiceId])) {
					$parentService = $services[$parentServiceId];

					// escalate only if status calculation is enabled for the parent service and it's in problem state
					if ($this->isStatusEnabled($parentService) && $parentService['status']) {
						if (!isset($parentProblems[$parentServiceId])) {
							$parentProblems[$parentServiceId] = [];
						}
						$parentProblems[$parentServiceId] = zbx_array_merge($parentProblems[$parentServiceId], $problemTriggers);
					}
				}
			}
		}

		// propagate the problems to the parents
		if ($parentProblems) {
			$slaData = $this->escalateProblems($services, $parentProblems, $slaData);
		}

		return $slaData;
	}

	/**
	 * Returns the value of the latest service alarm before the given time.
	 *
	 * @param array $serviceIds
	 * @param int $beforeTime
	 *
	 * @return array
	 */
	protected function fetchLatestValues(array $serviceIds, $beforeTime) {
		// The query will return the alarms with the latest servicealarmid for each service, before $beforeTime.
		$query = DBSelect(
			'SELECT sa.serviceid,sa.value'.
			' FROM (SELECT sa2.serviceid,MAX(sa2.servicealarmid) AS servicealarmid'.
			' FROM service_alarms sa2'.
			' WHERE sa2.clock<'.zbx_dbstr($beforeTime).
			' AND '.dbConditionInt('sa2.serviceid', $serviceIds).
			' GROUP BY sa2.serviceid) ss2'.
			' JOIN service_alarms sa ON sa.servicealarmid = ss2.servicealarmid'
		);
		$rs = [];
		while ($alarm = DBfetch($query)) {
			$rs[$alarm['serviceid']] = $alarm['value'];
		}

		return $rs;
	}
<<<<<<< HEAD
=======

	/**
	 * Returns an array of dependencies that are children of the given services. Performs permission checks.
	 *
	 * @param array $parentServiceIds
	 * @param $output
	 *
	 * @return array    an array of service links sorted by "sortorder" in ascending order
	 */
	protected function fetchChildDependencies(array $parentServiceIds, $output) {
		$sqlParts = API::getApiService()->createSelectQueryParts('services_links', 'sl', [
			'output' => $output,
			'filter' => ['serviceupid' => $parentServiceIds]
		]);

		// sort by sortorder
		$sqlParts['from'][] = $this->tableName().' '.$this->tableAlias();
		$sqlParts['where'][] = 'sl.servicedownid='.$this->fieldId('serviceid');
		$sqlParts = $this->addQueryOrder($this->fieldId('sortorder'), $sqlParts);
		$sqlParts = $this->addQueryOrder($this->fieldId('serviceid'), $sqlParts);

		// add permission filter
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			$sqlParts = $this->addPermissionFilter($sqlParts);
		}

		$sql = self::createSelectQueryFromParts($sqlParts);

		return DBfetchArray(DBselect($sql));
	}

	/**
	 * Returns an array of dependencies from the parent services to the given services.
	 * Performs permission checks.
	 *
	 * @param array $childServiceIds
	 * @param $output
	 * @param boolean $soft             if set to true, will return only soft-linked dependencies
	 *
	 * @return array    an array of service links sorted by "sortorder" in ascending order
	 */
	protected function fetchParentDependencies(array $childServiceIds, $output, $soft = null) {
		$sqlParts = API::getApiService()->createSelectQueryParts('services_links', 'sl', [
			'output' => $output,
			'filter' => ['servicedownid' => $childServiceIds]
		]);

		$sqlParts['from'][] = $this->tableName().' '.$this->tableAlias();
		$sqlParts['where'][] = 'sl.serviceupid='.$this->fieldId('serviceid');
		if ($soft !== null) {
			$sqlParts['where'][] = 'sl.soft='.($soft ? 1 : 0);
		}
		$sqlParts = $this->addQueryOrder($this->fieldId('sortorder'), $sqlParts);
		$sqlParts = $this->addQueryOrder($this->fieldId('serviceid'), $sqlParts);

		// add permission filter
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			$sqlParts = $this->addPermissionFilter($sqlParts);
		}

		$sql = self::createSelectQueryFromParts($sqlParts);

		return DBfetchArray(DBselect($sql));
	}

	/**
	 * Returns true if status calculation is enabled for the given service.
	 *
	 * @param array $service
	 *
	 * @return bool
	 */
	protected function isStatusEnabled(array $service) {
		return ($service['algorithm'] != SERVICE_ALGORITHM_NONE);
	}

	/**
	 * Validates the "name" field.
	 *
	 * @throws APIException if the name is missing
	 *
	 * @param array $service
	 */
	protected function checkName(array $service) {
		if (!isset($service['name']) || zbx_empty($service['name'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty name.'));
		}
	}

	/**
	 * Validates the "algorithm" field. Assumes the "name" field is valid.
	 *
	 * @throws APIException if the name is missing or invalid
	 *
	 * @param array $service
	 */
	protected function checkAlgorithm(array $service) {
		if (!isset($service['algorithm']) || !serviceAlgorithm($service['algorithm'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect algorithm for service "%1$s".', $service['name']));
		}
	}

	/**
	 * Validates the "showsla" field. Assumes the "name" field is valid.
	 *
	 * @throws APIException if the name is missing or is not a boolean value
	 *
	 * @param array $service
	 */
	protected function checkShowSla(array $service) {
		$showSlaValues = [
			SERVICE_SHOW_SLA_OFF => true,
			SERVICE_SHOW_SLA_ON => true
		];
		if (!isset($service['showsla']) || !isset($showSlaValues[$service['showsla']])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect calculate SLA value for service "%1$s".', $service['name']));
		}
	}

	/**
	 * Validates the "showsla" field. Assumes the "name" field is valid.
	 *
	 * @throws APIException if the value is missing, or is out of bounds
	 *
	 * @param array $service
	 */
	protected function checkGoodSla(array $service) {
		if ((!empty($service['showsla']) && empty($service['goodsla']))
				|| (isset($service['goodsla'])
					&& (!is_numeric($service['goodsla']) || $service['goodsla'] < 0 || $service['goodsla'] > 100))) {

			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect acceptable SLA for service "%1$s".', $service['name']));
		}
	}

	/**
	 * Validates the "sortorder" field. Assumes the "name" field is valid.
	 *
	 * @throws APIException if the value is missing, or is out of bounds
	 *
	 * @param array $service
	 */
	protected function checkSortOrder(array $service) {
		if (!isset($service['sortorder']) || !zbx_is_int($service['sortorder'])
			|| $service['sortorder'] < 0 || $service['sortorder'] > 999) {

			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect sort order for service "%1$s".', $service['name']));
		}
	}

	/**
	 * Validates the "triggerid" field. Assumes the "name" field is valid.
	 *
	 * @throws APIException if the value is incorrect
	 *
	 * @param array $service
	 */
	protected function checkTriggerId(array $service) {
		if (!empty($service['triggerid']) && !zbx_is_int($service['triggerid'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect trigger ID for service "%1$s".', $service['name']));
		}
	}

	/**
	 * Validates the "parentid" field. Assumes the "name" field is valid.
	 *
	 * @throws APIException if the value is incorrect
	 *
	 * @param array $service
	 */
	protected function checkParentId(array $service) {
		if (!empty($service['parentid']) && !zbx_is_int($service['parentid'])) {
			if (isset($service['name'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect parent for service "%1$s".', $service['name']));
			}
			else {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect parent service.'));
			}
		}

		if (isset($service['serviceid']) && idcmp($service['serviceid'], $service['parentid'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Service cannot be parent and child at the same time.'));
		}
	}

	/**
	 * Validates the "status" field. Assumes the "name" field is valid.
	 *
	 * @throws APIException if the value is incorrect
	 *
	 * @param array $service
	 */
	protected function checkStatus(array $service) {
		if (!empty($service['status']) && !zbx_is_int($service['status'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect status for service "%1$s".', $service['name']));
		}
	}

	/**
	 * Checks that the user has read access to the given triggers.
	 *
	 * @throws APIException if the user doesn't have permission to access any of the triggers
	 *
	 * @param array $services
	 */
	protected function checkTriggerPermissions(array $services) {
		$triggerids = [];
		foreach ($services as $service) {
			if (!empty($service['triggerid'])) {
				$triggerids[$service['triggerid']] = true;
			}
		}

		if ($triggerids) {
			$count = API::Trigger()->get([
				'countOutput' => true,
				'triggerids' => array_keys($triggerids)
			]);

			if ($count != count($triggerids)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}
		}
	}

	/**
	 * Checks that all of the given services are readable.
	 *
	 * @param array $serviceids
	 * @param array $db_services
	 *
	 * @throws APIException if at least one of the services doesn't exist
	 */
	protected function checkServicePermissions(array $serviceids, array &$db_services = null) {
		if ($serviceids) {
			$serviceids = array_keys(array_flip($serviceids));

			$db_services = $this->get([
				'output' => ['serviceid', 'name'],
				'serviceids' => $serviceids,
				'preservekeys' => true
			]);

			if (count($db_services) != count($serviceids)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}
		}
	}

	/**
	 * Checks that none of the given services have any children.
	 *
	 * @param array $serviceids
	 *
	 * @throws APIException if at least one of the services has a child service
	 */
	protected function checkThatServicesDontHaveChildren(array $serviceids) {
		$child = API::getApiService()->select('services_links', [
			'output' => ['serviceupid'],
			'filter' => [
				'serviceupid' => $serviceids,
				'soft' => 0
			],
			'limit' => 1
		]);
		$child = reset($child);
		if ($child) {
			$service = API::getApiService()->select($this->tableName(), [
				'output' => ['name'],
				'serviceids' => $child['serviceupid'],
				'limit' => 1
			]);
			$service = reset($service);
			self::exception(ZBX_API_ERROR_PERMISSIONS,
				_s('Service "%1$s" cannot be deleted, because it is dependent on another service.', $service['name'])
			);
		}
	}

	/**
	 * Checks that the given dependency is valid.
	 *
	 * @throws APIException if the dependency is invalid
	 *
	 * @param array $dependency
	 */
	protected function checkDependency(array $dependency) {
		if (idcmp($dependency['serviceid'], $dependency['dependsOnServiceid'])) {
			$service = API::getApiService()->select($this->tableName(), [
				'output' => ['name'],
				'serviceids' => $dependency['serviceid']
			]);
			$service = reset($service);
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Service "%1$s" cannot be dependent on itself.', $service['name']));
		}

		// check 'soft' field value
		if (!isset($dependency['soft']) || !in_array((int) $dependency['soft'], [0, 1], true)) {
			$service = API::getApiService()->select($this->tableName(), [
				'output' => ['name'],
				'serviceids' => $dependency['serviceid']
			]);
			$service = reset($service);
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Incorrect "soft" field value for dependency for service "%1$s".', $service['name'])
			);
		}
	}

	/**
	 * Checks that that none of the given services are hard linked to a different service.
	 * Assumes the dependencies are valid.
	 *
	 * @throws APIException if at a least one service is hard linked to another service
	 *
	 * @param array $dependencies
	 */
	protected function checkForHardlinkedDependencies(array $dependencies) {
		// only check hard dependencies
		$hardDepServiceIds = [];
		foreach ($dependencies as $dependency) {
			if (!$dependency['soft']) {
				$hardDepServiceIds[] = $dependency['dependsOnServiceid'];
			}
		}

		if ($hardDepServiceIds) {
			// look for at least one hardlinked service among the given
			$hardDepServiceIds = array_unique($hardDepServiceIds);
			$dep = API::getApiService()->select('services_links', [
				'output' => ['servicedownid'],
				'filter' => [
					'soft' => 0,
					'servicedownid' => $hardDepServiceIds
				],
				'limit' => 1
			]);
			if ($dep) {
				$dep = reset($dep);
				$service = API::getApiService()->select($this->tableName(), [
					'output' => ['name'],
					'serviceids' => $dep['servicedownid']
				]);
				$service = reset($service);
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Service "%1$s" is already hardlinked to a different service.', $service['name'])
				);
			}
		}
	}

	/**
	 * Checks that none of the parent services are linked to a trigger. Assumes the dependencies are valid.
	 *
	 * @throws APIException if at least one of the parent services is linked to a trigger
	 *
	 * @param array $dependencies
	 */
	protected function checkThatParentsDontHaveTriggers(array $dependencies) {
		$parentServiceIds = array_unique(zbx_objectValues($dependencies, 'serviceid'));
		if ($parentServiceIds) {
			$query = DBselect(
				'SELECT s.triggerid,s.name'.
					' FROM services s '.
					' WHERE '.dbConditionInt('s.serviceid', $parentServiceIds).
					' AND s.triggerid IS NOT NULL', 1);
			if ($parentService = DBfetch($query)) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Service "%1$s" cannot be linked to a trigger and have children at the same time.', $parentService['name']));
			}
		}
	}

	/**
	 * Checks that dependencies will not create cycles in service dependencies.
	 *
	 * @throws APIException if at least one cycle is possible
	 *
	 * @param array $depsToValid	dependency list to be validated
	 */
	protected function checkForCircularityInDependencies($depsToValid) {
		$dbDeps = API::getApiService()->select('services_links', [
			'output' => ['serviceupid', 'servicedownid']
		]);

		// create existing dependency acyclic graph
		$arr = [];
		foreach ($dbDeps as $dbDep) {
			if (!isset($arr[$dbDep['serviceupid']])) {
				$arr[$dbDep['serviceupid']] = [];
			}
			$arr[$dbDep['serviceupid']][$dbDep['servicedownid']] = $dbDep['servicedownid'];
		}

		// check for circularity and add dependencies to the graph
		foreach ($depsToValid as $dep) {
			$this->DFCircularitySearch($dep['serviceid'], $dep['dependsOnServiceid'], $arr);
			$arr[$dep['serviceid']][$dep['dependsOnServiceid']] = $dep['dependsOnServiceid'];
		}

	}

	/**
	 * Depth First Search recursive function to find circularity and rise exception.
	 *
	 * @throws APIException if cycle is possible
	 *
	 * @param int $id	dependency from id
	 * @param int $depId	dependency to id
	 * @param ref $arr	reference to graph structure. Structure is associative array with keys as "from id"
	 *			and values as arrays with keys and values as "to id".
	 */
	protected function dfCircularitySearch($id, $depId, &$arr) {
		if ($id == $depId) {
			// cycle found
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Services form a circular dependency.'));
		}
		if (isset($arr[$depId])) {
			foreach ($arr[$depId] as $dep) {
				$this->DFCircularitySearch($id, $dep, $arr);
			}
		}
	}

	/**
	 * Checks that the given service time is valid.
	 *
	 * @throws APIException if the service time is invalid
	 *
	 * @param array $serviceTime
	 */
	protected function checkTime(array $serviceTime) {
		if (empty($serviceTime['serviceid'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Invalid method parameters.'));
		}

		checkServiceTime($serviceTime);
	}

	protected function applyQueryFilterOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			// if services with specific trigger IDs were requested, return only the ones accessible to the current user.
			if (is_array($options['filter']) && array_key_exists('triggerid', $options['filter'])) {
				$accessibleTriggers = API::Trigger()->get([
					'output' => ['triggerid'],
					'triggerids' => $options['filter']['triggerid']
				]);
				$options['filter']['triggerid'] = zbx_objectValues($accessibleTriggers, 'triggerid');
			}
			// otherwise return services with either no triggers, or any trigger accessible to the current user
			else {
				$sqlParts = $this->addPermissionFilter($sqlParts);
			}
		}

		$sqlParts = parent::applyQueryFilterOptions($tableName, $tableAlias, $options, $sqlParts);

		// parentids
		if ($options['parentids'] !== null) {
			$sqlParts['from'][] = 'services_links slp';
			$sqlParts['where'][] = $this->fieldId('serviceid').'=slp.servicedownid AND slp.soft=0';
			$sqlParts['where'][] = dbConditionInt('slp.serviceupid', (array) $options['parentids']);
		}
		// childids
		if ($options['childids'] !== null) {
			$sqlParts['from'][] = 'services_links slc';
			$sqlParts['where'][] = $this->fieldId('serviceid').'=slc.serviceupid AND slc.soft=0';
			$sqlParts['where'][] = dbConditionInt('slc.servicedownid', (array) $options['childids']);
		}

		return $sqlParts;
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$serviceIds = array_keys($result);

		// selectDependencies
		if ($options['selectDependencies'] !== null && $options['selectDependencies'] != API_OUTPUT_COUNT) {
			$dependencies = $this->fetchChildDependencies($serviceIds,
				$this->outputExtend($options['selectDependencies'], ['serviceupid', 'linkid'])
			);
			$dependencies = zbx_toHash($dependencies, 'linkid');
			$relationMap = $this->createRelationMap($dependencies, 'serviceupid', 'linkid');

			$dependencies = $this->unsetExtraFields($dependencies, ['serviceupid', 'linkid'], $options['selectDependencies']);
			$result = $relationMap->mapMany($result, $dependencies, 'dependencies');
		}

		// selectParentDependencies
		if ($options['selectParentDependencies'] !== null && $options['selectParentDependencies'] != API_OUTPUT_COUNT) {
			$dependencies = $this->fetchParentDependencies($serviceIds,
				$this->outputExtend($options['selectParentDependencies'], ['servicedownid', 'linkid'])
			);
			$dependencies = zbx_toHash($dependencies, 'linkid');
			$relationMap = $this->createRelationMap($dependencies, 'servicedownid', 'linkid');

			$dependencies = $this->unsetExtraFields($dependencies, ['servicedownid', 'linkid'],
				$options['selectParentDependencies']
			);
			$result = $relationMap->mapMany($result, $dependencies, 'parentDependencies');
		}

		// selectParent
		if ($options['selectParent'] !== null && $options['selectParent'] != API_OUTPUT_COUNT) {
			$dependencies = $this->fetchParentDependencies($serviceIds, ['servicedownid', 'serviceupid'], false);
			$relationMap = $this->createRelationMap($dependencies, 'servicedownid', 'serviceupid');
			$parents = $this->get([
				'output' => $options['selectParent'],
				'serviceids' => $relationMap->getRelatedIds(),
				'preservekeys' => true
			]);
			$result = $relationMap->mapOne($result, $parents, 'parent');
		}

		// selectTimes
		if ($options['selectTimes'] !== null && $options['selectTimes'] != API_OUTPUT_COUNT) {
			$serviceTimes = API::getApiService()->select('services_times', [
				'output' => $this->outputExtend($options['selectTimes'], ['serviceid', 'timeid']),
				'filter' => ['serviceid' => $serviceIds],
				'preservekeys' => true
			]);
			$relationMap = $this->createRelationMap($serviceTimes, 'serviceid', 'timeid');

			$serviceTimes = $this->unsetExtraFields($serviceTimes, ['serviceid', 'timeid'], $options['selectTimes']);
			$result = $relationMap->mapMany($result, $serviceTimes, 'times');
		}

		// selectAlarms
		if ($options['selectAlarms'] !== null && $options['selectAlarms'] != API_OUTPUT_COUNT) {
			$serviceAlarms = API::getApiService()->select('service_alarms', [
				'output' => $this->outputExtend($options['selectAlarms'], ['serviceid', 'servicealarmid']),
				'filter' => ['serviceid' => $serviceIds],
				'preservekeys' => true
			]);
			$relationMap = $this->createRelationMap($serviceAlarms, 'serviceid', 'servicealarmid');

			$serviceAlarms = $this->unsetExtraFields($serviceAlarms, ['serviceid', 'servicealarmid'],
				$options['selectAlarms']
			);
			$result = $relationMap->mapMany($result, $serviceAlarms, 'alarms');
		}

		// selectTrigger
		if ($options['selectTrigger'] !== null && $options['selectTrigger'] != API_OUTPUT_COUNT) {
			$relationMap = $this->createRelationMap($result, 'serviceid', 'triggerid');
			$triggers = API::getApiService()->select('triggers', [
				'output' => $options['selectTrigger'],
				'triggerids' => $relationMap->getRelatedIds(),
				'preservekeys' => true
			]);
			$result = $relationMap->mapOne($result, $triggers, 'trigger');
		}

		return $result;
	}

	protected function applyQueryOutputOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		$sqlParts = parent::applyQueryOutputOptions($tableName, $tableAlias, $options, $sqlParts);

		if (!$options['countOutput']) {
			if ($options['selectTrigger'] !== null) {
				$sqlParts = $this->addQuerySelect($this->fieldId('triggerid'), $sqlParts);
			}
		}

		return $sqlParts;
	}

	/**
	 * Add permission filter SQL query part
	 *
	 * @param array $sqlParts
	 *
	 * @return string
	 */
	protected function addPermissionFilter($sqlParts) {
		$userGroups = getUserGroupsByUserId(self::$userData['userid']);

		$sqlParts['where'][] = '(EXISTS ('.
									'SELECT NULL'.
									' FROM functions f,items i,hosts_groups hgg'.
									' JOIN rights r'.
										' ON r.id=hgg.groupid'.
										' AND '.dbConditionInt('r.groupid', $userGroups).
									' WHERE s.triggerid=f.triggerid'.
										' AND f.itemid=i.itemid'.
										' AND i.hostid=hgg.hostid'.
									' GROUP BY f.triggerid'.
									' HAVING MIN(r.permission)>'.PERM_DENY.
									')'.
								' OR s.triggerid IS NULL)';

		return $sqlParts;
	}
>>>>>>> f7ff6b10458221dd1bff0e88f90ac7c148f76ed2
}
