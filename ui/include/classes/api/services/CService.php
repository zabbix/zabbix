<?php declare(strict_types = 1);
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
		'create' => ['min_user_type' => USER_TYPE_ZABBIX_USER],
		'update' => ['min_user_type' => USER_TYPE_ZABBIX_USER],
		'delete' => ['min_user_type' => USER_TYPE_ZABBIX_USER]
	];

	protected $tableName = 'services';
	protected $tableAlias = 's';
	protected $sortColumns = ['sortorder', 'name'];

	/**
	 * @param array $options
	 *
	 * @return array|string

	 * @throws APIException
	 */
	public function get(array $options = []) {
		return $this->doGet($options, self::getPermissions());
	}

	/**
	 * @param array      $options
	 * @param array|null $permissions
	 * @param bool       $preserve_related_ids
	 *
	 * @return array|string
	 * @throws APIException
	 */
	private function doGet(array $options = [], array $permissions = null, bool $preserve_related_ids = false) {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			// filter
			'serviceids' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'parentids' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'deep_parentids' =>			['type' => API_BOOLEAN, 'default' => false],
			'childids' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'evaltype' =>				['type' => API_INT32, 'in' => implode(',', [TAG_EVAL_TYPE_AND_OR, TAG_EVAL_TYPE_OR]), 'default' => TAG_EVAL_TYPE_AND_OR],
			'tags' =>					['type' => API_OBJECTS, 'default' => [], 'fields' => [
				'tag' =>					['type' => API_STRING_UTF8, 'flags' => API_REQUIRED],
				'value' =>					['type' => API_STRING_UTF8],
				'operator' =>				['type' => API_INT32, 'in' => implode(',', [TAG_OPERATOR_LIKE, TAG_OPERATOR_EQUAL, TAG_OPERATOR_NOT_LIKE, TAG_OPERATOR_NOT_EQUAL, TAG_OPERATOR_EXISTS, TAG_OPERATOR_NOT_EXISTS])]
			]],
			'problem_tags' =>			['type' => API_OBJECTS, 'default' => [], 'fields' => [
				'tag' =>					['type' => API_STRING_UTF8, 'flags' => API_REQUIRED],
				'value' =>					['type' => API_STRING_UTF8],
				'operator' =>				['type' => API_INT32, 'in' => implode(',', [TAG_OPERATOR_LIKE, TAG_OPERATOR_EQUAL, TAG_OPERATOR_NOT_LIKE, TAG_OPERATOR_NOT_EQUAL, TAG_OPERATOR_EXISTS, TAG_OPERATOR_NOT_EXISTS])]
			]],
			'without_problem_tags' =>	['type' => API_BOOLEAN, 'default' => false],
			'filter' =>					['type' => API_OBJECT, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => [
				'serviceid' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'name' =>					['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'status' =>					['type' => API_INTS32, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'in' => implode(',', array_merge([ZBX_SEVERITY_OK], range(TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_COUNT - 1)))],
				'algorithm' =>				['type' => API_INTS32, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'in' => implode(',', [ZBX_SERVICE_STATUS_CALC_SET_OK, ZBX_SERVICE_STATUS_CALC_MOST_CRITICAL_ALL, ZBX_SERVICE_STATUS_CALC_MOST_CRITICAL_ONE])],
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
			'output' =>					['type' => API_OUTPUT, 'in' => implode(',', ['serviceid', 'name', 'status', 'algorithm', 'showsla', 'goodsla', 'sortorder', 'weight', 'propagation_rule', 'propagation_value', 'readonly']), 'default' => API_OUTPUT_EXTEND],
			'countOutput' =>			['type' => API_FLAG, 'default' => false],
			'selectParents' =>			['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL | API_ALLOW_COUNT, 'in' => implode(',', ['serviceid', 'name', 'status', 'algorithm', 'showsla', 'goodsla', 'sortorder', 'weight', 'propagation_rule', 'propagation_value', 'readonly']), 'default' => null],
			'selectChildren' =>			['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL | API_ALLOW_COUNT, 'in' => implode(',', ['serviceid', 'name', 'status', 'algorithm', 'showsla', 'goodsla', 'sortorder', 'weight', 'propagation_rule', 'propagation_value', 'readonly']), 'default' => null],
			'selectTags' =>				['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL | API_ALLOW_COUNT, 'in' => implode(',', ['tag', 'value']), 'default' => null],
			'selectProblemTags' =>		['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL | API_ALLOW_COUNT, 'in' => implode(',', ['tag', 'operator', 'value']), 'default' => null],
			'selectProblemEvents' =>	['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL | API_ALLOW_COUNT, 'in' => implode(',', ['eventid', 'severity', 'name']), 'default' => null],
			'selectTimes' =>			['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL | API_ALLOW_COUNT, 'in' => implode(',', ['type', 'ts_from', 'ts_to', 'note']), 'default' => null],
			'selectStatusRules' =>		['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL | API_ALLOW_COUNT, 'in' => implode(',', ['type', 'limit_value', 'limit_status', 'new_status']), 'default' => null],
			'selectAlarms' =>			['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL | API_ALLOW_COUNT, 'in' => implode(',', ['clock', 'value']), 'default' => null],
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

		if ($permissions === null) {
			$accessible_services = null;
		}
		elseif ($options['editable']) {
			$accessible_services = $permissions['rw_services'];
		}
		elseif ($permissions['r_services'] === null || $permissions['rw_services'] === null) {
			$accessible_services = null;
		}
		else {
			$accessible_services = $permissions['r_services'] + $permissions['rw_services'];
		}

		if ($options['parentids'] !== null && $accessible_services !== null) {
			$options['parentids'] = array_intersect($options['parentids'],
				array_keys([0 => true] + $accessible_services)
			);
		}

		if ($options['parentids'] !== null && $options['deep_parentids']) {
			$limit_services = self::getServicesByDeepParents($options['parentids']);

			if ($accessible_services !== null) {
				$limit_services = $limit_services !== null
					? array_intersect_key($limit_services, $accessible_services)
					: $accessible_services;
			}

			$options['parentids'] = null;
		}
		else {
			$limit_services = $accessible_services;
		}

		$options['root_services'] = $permissions !== null ? $permissions['root_services'] : null;

		$db_services = [];

		$sql = $this->createSelectQuery('services', $options);
		$resource = DBselect($sql);

		while (($options['limit'] === null || count($db_services) < $options['limit']) && $row = DBfetch($resource)) {
			if ($options['countOutput']) {
				return $row['rowscount'];
			}

			if ($limit_services !== null && !array_key_exists($row['serviceid'], $limit_services)) {
				continue;
			}

			if ($this->outputIsRequested('readonly', $options['output'])) {
				$row['readonly'] = $permissions !== null && $permissions['rw_services'] !== null
					&& !array_key_exists($row['serviceid'], $permissions['rw_services']);
			}

			$db_services[$row['serviceid']] = $row;
		}

		if ($db_services) {
			$db_services = $this->addRelatedObjects($options, $db_services, $permissions, $preserve_related_ids);
			$db_services = $this->unsetExtraFields($db_services, ['serviceid'], $options['output']);

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

		$this->updateTags($services);
		$this->updateProblemTags($services);
		$this->updateParents($services);
		$this->updateChildren($services);
		$this->updateTimes($services);
		$this->updateStatusRules($services);

		foreach ($services as $serviceid => &$service) {
			$service['serviceid'] = $serviceid;
		}
		unset($service);

		self::addAuditLog(CAudit::ACTION_ADD, CAudit::RESOURCE_IT_SERVICE, $services);

		return ['serviceids' => $serviceids];
	}

	/**
	 * @param array $services
	 *
	 * @throws APIException
	 */
	private function validateCreate(array &$services): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'fields' => [
			'name' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('services', 'name')],
			'algorithm' =>			['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [ZBX_SERVICE_STATUS_CALC_SET_OK, ZBX_SERVICE_STATUS_CALC_MOST_CRITICAL_ALL, ZBX_SERVICE_STATUS_CALC_MOST_CRITICAL_ONE])],
			'showsla' =>			['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [SERVICE_SHOW_SLA_OFF, SERVICE_SHOW_SLA_ON])],
			'goodsla' =>			['type' => API_FLOAT, 'in' => '0:100'],
			'sortorder' =>			['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '0:999'],
			'weight' =>				['type' => API_INT32, 'in' => '0:1000000'],
			'propagation_rule' =>	['type' => API_INT32, 'in' => implode(',', [ZBX_SERVICE_STATUS_PROPAGATION_AS_IS, ZBX_SERVICE_STATUS_PROPAGATION_INCREASE, ZBX_SERVICE_STATUS_PROPAGATION_DECREASE, ZBX_SERVICE_STATUS_PROPAGATION_IGNORE, ZBX_SERVICE_STATUS_PROPAGATION_FIXED])],
			'propagation_value' =>	['type' => API_INT32],
			'tags' =>				['type' => API_OBJECTS, 'uniq' => [['tag', 'value']], 'fields' => [
				'tag' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('service_tag', 'tag')],
				'value' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('service_tag', 'value'), 'default' => DB::getDefault('service_tag', 'value')]
			]],
			'problem_tags' =>		['type' => API_OBJECTS, 'uniq' => [['tag', 'value']], 'fields' => [
				'tag' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('service_problem_tag', 'tag')],
				'operator' =>			['type' => API_INT32, 'in' => implode(',', [SERVICE_TAG_OPERATOR_EQUAL, SERVICE_TAG_OPERATOR_LIKE]), 'default' => DB::getDefault('service_problem_tag', 'operator')],
				'value' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('service_problem_tag', 'value'), 'default' => DB::getDefault('service_problem_tag', 'value')]
			]],
			'parents' =>			['type' => API_OBJECTS, 'uniq' => [['serviceid']], 'fields' => [
				'serviceid' =>			['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'children' =>			['type' => API_OBJECTS, 'uniq' => [['serviceid']], 'fields' => [
				'serviceid' =>			['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'times' =>				['type' => API_OBJECTS, 'uniq' => [['type', 'ts_from', 'ts_to']], 'fields' => [
				'type' =>				['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [SERVICE_TIME_TYPE_UPTIME, SERVICE_TIME_TYPE_DOWNTIME, SERVICE_TIME_TYPE_ONETIME_DOWNTIME])],
				'ts_from' =>			['type' => API_MULTIPLE, 'flags' => API_REQUIRED, 'rules' => [
					['if' =>				['field' => 'type', 'in' => implode(',', [SERVICE_TIME_TYPE_DOWNTIME, SERVICE_TIME_TYPE_UPTIME])], 'type' => API_INT32, 'in' => '0:'.SEC_PER_WEEK],
					['if' =>				['field' => 'type', 'in' => implode(',', [SERVICE_TIME_TYPE_ONETIME_DOWNTIME])], 'type' => API_INT32, 'in' => '0:'.ZBX_MAX_DATE]
				]],
				'ts_to' =>				['type' => API_MULTIPLE, 'flags' => API_REQUIRED, 'rules' => [
					['if' =>				['field' => 'type', 'in' => implode(',', [SERVICE_TIME_TYPE_DOWNTIME, SERVICE_TIME_TYPE_UPTIME])], 'type' => API_INT32, 'in' => '0:'.SEC_PER_WEEK],
					['if' =>				['field' => 'type', 'in' => implode(',', [SERVICE_TIME_TYPE_ONETIME_DOWNTIME])], 'type' => API_INT32, 'in' => '0:'.ZBX_MAX_DATE]
				]],
				'note' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('services_times', 'note'), 'default' => DB::getDefault('services_times', 'note')]
			]],
			'status_rules' =>		['type' => API_OBJECTS, 'uniq' => [['type', 'limit_value', 'limit_status']], 'fields' => [
				'type' =>				['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [ZBX_SERVICE_STATUS_RULE_TYPE_N_GE, ZBX_SERVICE_STATUS_RULE_TYPE_NP_GE, ZBX_SERVICE_STATUS_RULE_TYPE_N_L, ZBX_SERVICE_STATUS_RULE_TYPE_NP_L, ZBX_SERVICE_STATUS_RULE_TYPE_W_GE, ZBX_SERVICE_STATUS_RULE_TYPE_WP_GE, ZBX_SERVICE_STATUS_RULE_TYPE_W_L, ZBX_SERVICE_STATUS_RULE_TYPE_WP_L])],
				'limit_value' =>		['type' => API_MULTIPLE, 'flags' => API_REQUIRED, 'rules' => [
					['if' =>				['field' => 'type', 'in' => implode(',', [ZBX_SERVICE_STATUS_RULE_TYPE_N_GE, ZBX_SERVICE_STATUS_RULE_TYPE_N_L, ZBX_SERVICE_STATUS_RULE_TYPE_W_GE, ZBX_SERVICE_STATUS_RULE_TYPE_W_L])], 'type' => API_INT32, 'in' => '1:1000000'],
					['if' =>				['field' => 'type', 'in' => implode(',', [ZBX_SERVICE_STATUS_RULE_TYPE_NP_GE, ZBX_SERVICE_STATUS_RULE_TYPE_NP_L, ZBX_SERVICE_STATUS_RULE_TYPE_WP_GE, ZBX_SERVICE_STATUS_RULE_TYPE_WP_L])], 'type' => API_INT32, 'in' => '1:100']
				]],
				'limit_status' =>		['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', array_merge([ZBX_SEVERITY_OK], range(TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_COUNT - 1)))],
				'new_status' =>			['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', range(TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_COUNT - 1))]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $services, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$this->checkPermissions($services);

		$this->checkGoodSla($services);
		$this->checkStatusPropagation($services);
		$this->checkAlgorithmDependencies($services);
		$this->checkChildrenOrProblemTags($services);
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

		$upd_services = [];

		foreach ($services as $service) {
			$upd_service = DB::getUpdatedValues('services', $service, $db_services[$service['serviceid']]);

			if ($upd_service) {
				$upd_services[] = [
					'values' => $upd_service,
					'where' => ['serviceid' => $service['serviceid']]
				];
			}
		}

		if ($upd_services) {
			DB::update('services', $upd_services);
		}

		$services = array_column($services, null, 'serviceid');

		$this->updateTags($services, $db_services);
		$this->updateProblemTags($services, $db_services);
		$this->updateParents($services, $db_services);
		$this->updateChildren($services, $db_services);
		$this->updateTimes($services, $db_services);
		$this->updateStatusRules($services, $db_services);

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_IT_SERVICE, $services, $db_services);

		return ['serviceids' => array_column($services, 'serviceid')];
	}

	/**
	 * @param array      $services
	 * @param array|null $db_services
	 *
	 * @throws APIException
	 */
	private function validateUpdate(array &$services, ?array &$db_services): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['serviceid']], 'fields' => [
			'serviceid' =>			['type' => API_ID, 'flags' => API_REQUIRED],
			'name' =>				['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('services', 'name')],
			'algorithm' =>			['type' => API_INT32, 'in' => implode(',', [ZBX_SERVICE_STATUS_CALC_SET_OK, ZBX_SERVICE_STATUS_CALC_MOST_CRITICAL_ALL, ZBX_SERVICE_STATUS_CALC_MOST_CRITICAL_ONE])],
			'showsla' =>			['type' => API_INT32, 'in' => implode(',', [SERVICE_SHOW_SLA_OFF, SERVICE_SHOW_SLA_ON])],
			'goodsla' =>			['type' => API_FLOAT, 'in' => '0:100'],
			'sortorder' =>			['type' => API_INT32, 'in' => '0:999'],
			'weight' =>				['type' => API_INT32, 'in' => '0:1000000'],
			'propagation_rule' =>	['type' => API_INT32, 'in' => implode(',', [ZBX_SERVICE_STATUS_PROPAGATION_AS_IS, ZBX_SERVICE_STATUS_PROPAGATION_INCREASE, ZBX_SERVICE_STATUS_PROPAGATION_DECREASE, ZBX_SERVICE_STATUS_PROPAGATION_IGNORE, ZBX_SERVICE_STATUS_PROPAGATION_FIXED])],
			'propagation_value' =>	['type' => API_INT32],
			'tags' =>				['type' => API_OBJECTS, 'uniq' => [['tag', 'value']], 'fields' => [
				'tag' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('service_tag', 'tag')],
				'value' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('service_tag', 'value'), 'default' => DB::getDefault('service_tag', 'value')]
			]],
			'problem_tags' =>		['type' => API_OBJECTS, 'uniq' => [['tag', 'value']], 'fields' => [
				'tag' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('service_problem_tag', 'tag')],
				'operator' =>			['type' => API_INT32, 'in' => implode(',', [SERVICE_TAG_OPERATOR_EQUAL, SERVICE_TAG_OPERATOR_LIKE]), 'default' => DB::getDefault('service_problem_tag', 'operator')],
				'value' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('service_problem_tag', 'value'), 'default' => DB::getDefault('service_problem_tag', 'value')]
			]],
			'parents' =>			['type' => API_OBJECTS, 'uniq' => [['serviceid']], 'fields' => [
				'serviceid' =>			['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'children' =>			['type' => API_OBJECTS, 'uniq' => [['serviceid']], 'fields' => [
				'serviceid' =>			['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'times' =>				['type' => API_OBJECTS, 'uniq' => [['type', 'ts_from', 'ts_to']], 'fields' => [
				'type' =>			['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [SERVICE_TIME_TYPE_UPTIME, SERVICE_TIME_TYPE_DOWNTIME, SERVICE_TIME_TYPE_ONETIME_DOWNTIME])],
				'ts_from' =>			['type' => API_MULTIPLE, 'flags' => API_REQUIRED, 'rules' => [
					['if' =>				['field' => 'type', 'in' => implode(',', [SERVICE_TIME_TYPE_DOWNTIME, SERVICE_TIME_TYPE_UPTIME])], 'type' => API_INT32, 'in' => '0:'.SEC_PER_WEEK],
					['if' =>				['field' => 'type', 'in' => implode(',', [SERVICE_TIME_TYPE_ONETIME_DOWNTIME])], 'type' => API_INT32, 'in' => '0:'.ZBX_MAX_DATE]
				]],
				'ts_to' =>				['type' => API_MULTIPLE, 'flags' => API_REQUIRED, 'rules' => [
					['if' =>				['field' => 'type', 'in' => implode(',', [SERVICE_TIME_TYPE_DOWNTIME, SERVICE_TIME_TYPE_UPTIME])], 'type' => API_INT32, 'in' => '0:'.SEC_PER_WEEK],
					['if' =>				['field' => 'type', 'in' => implode(',', [SERVICE_TIME_TYPE_ONETIME_DOWNTIME])], 'type' => API_INT32, 'in' => '0:'.ZBX_MAX_DATE]
				]],
				'note' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('services_times', 'note'), 'default' => DB::getDefault('services_times', 'note')]
			]],
			'status_rules' =>		['type' => API_OBJECTS, 'uniq' => [['type', 'limit_value', 'limit_status']], 'fields' => [
				'type' =>				['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [ZBX_SERVICE_STATUS_RULE_TYPE_N_GE, ZBX_SERVICE_STATUS_RULE_TYPE_NP_GE, ZBX_SERVICE_STATUS_RULE_TYPE_N_L, ZBX_SERVICE_STATUS_RULE_TYPE_NP_L, ZBX_SERVICE_STATUS_RULE_TYPE_W_GE, ZBX_SERVICE_STATUS_RULE_TYPE_WP_GE, ZBX_SERVICE_STATUS_RULE_TYPE_W_L, ZBX_SERVICE_STATUS_RULE_TYPE_WP_L])],
				'limit_value' =>		['type' => API_MULTIPLE, 'flags' => API_REQUIRED, 'rules' => [
					['if' =>				['field' => 'type', 'in' => implode(',', [ZBX_SERVICE_STATUS_RULE_TYPE_N_GE, ZBX_SERVICE_STATUS_RULE_TYPE_N_L, ZBX_SERVICE_STATUS_RULE_TYPE_W_GE, ZBX_SERVICE_STATUS_RULE_TYPE_W_L])], 'type' => API_INT32, 'in' => '1:1000000'],
					['if' =>				['field' => 'type', 'in' => implode(',', [ZBX_SERVICE_STATUS_RULE_TYPE_NP_GE, ZBX_SERVICE_STATUS_RULE_TYPE_NP_L, ZBX_SERVICE_STATUS_RULE_TYPE_WP_GE, ZBX_SERVICE_STATUS_RULE_TYPE_WP_L])], 'type' => API_INT32, 'in' => '1:100']
				]],
				'limit_status' =>		['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', array_merge([ZBX_SEVERITY_OK], range(TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_COUNT - 1)))],
				'new_status' =>			['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', range(TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_COUNT - 1))]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $services, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_services = $this->doGet([
			'output' => ['serviceid', 'name', 'status', 'algorithm', 'showsla', 'goodsla', 'sortorder', 'weight',
				'propagation_rule', 'propagation_value'
			],
			'selectParents' => ['serviceid'],
			'selectChildren' => ['serviceid'],
			'selectTags' => ['tag', 'value'],
			'selectProblemTags' => ['tag', 'operator', 'value'],
			'selectTimes' => ['type', 'ts_from', 'ts_to', 'note'],
			'selectStatusRules' => ['type', 'limit_value', 'limit_status', 'new_status'],
			'serviceids' => array_column($services, 'serviceid'),
			'preservekeys' => true
		], null, true);

		if (count($db_services) != count($services)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		$this->checkPermissions($services, $db_services);

		$this->checkParentChildRelations($services, $db_services);
		$this->checkStatusPropagation($services, $db_services);
		$this->checkGoodSla($services, $db_services);
		$this->checkAlgorithmDependencies($services, $db_services);
		$this->checkChildrenOrProblemTags($services);
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
	public function delete(array $serviceids): array {
		$this->validateDelete($serviceids, $db_services);

		DB::delete('services', ['serviceid' => $serviceids]);

		$ins_housekeeper = [];

		foreach ($serviceids as $serviceid) {
			$ins_housekeeper[] = [
				'tablename' => 'events',
				'field' => 'serviceid',
				'value' => $serviceid
			];
		}

		DB::insertBatch('housekeeper', $ins_housekeeper);

		self::addAuditLog(CAudit::ACTION_DELETE, CAudit::RESOURCE_IT_SERVICE, $db_services);

		return ['serviceids' => $serviceids];
	}

	/**
	 * @param array      $serviceids
	 * @param array|null $db_services
	 *
	 * @throws APIException
	 */
	private function validateDelete(array $serviceids, ?array &$db_services): void {
		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];

		if (!CApiInputValidator::validate($api_input_rules, $serviceids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$permissions = self::getPermissions();

		$db_services = $this->doGet([
			'output' => ['serviceid', 'name', 'readonly'],
			'selectChildren' => $permissions['rw_services'] !== null ? ['serviceid', 'name'] : null,
			'serviceids' => $serviceids
		], $permissions);

		if (count($db_services) != count($serviceids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		if ($permissions['rw_services'] === null) {
			return;
		}

		foreach ($db_services as $db_service) {
			if ($db_service['readonly'] == 1) {
				$error_detail = _('read-write access to the service is required');
				$error = _s('Cannot delete service "%1$s": %2$s.', $db_service['name'], $error_detail);

				self::exception(ZBX_API_ERROR_PERMISSIONS, $error);
			}
		}

		foreach ($db_services as $db_service) {
			foreach ($db_service['children'] as $child_service) {
				if ($permissions['rw_services'][$child_service['serviceid']] !== null) {
					$permissions['rw_services'][$child_service['serviceid']]--;

					if ($permissions['rw_services'][$child_service['serviceid']] == 0) {
						$error_detail = _s('read-write access to the child service "%1$s" must be retained',
							$child_service['name']
						);
						$error = _s('Cannot delete service "%1$s": %2$s.', $db_service['name'], $error_detail);

						self::exception(ZBX_API_ERROR_PERMISSIONS, $error);
					}
				}
			}
		}
	}

	/**
	 * @static
	 *
	 * @param array $parentids
	 *
	 * @return array|null
	 */
	private static function getServicesByDeepParents(array $parentids): ?array {
		if (in_array(0, $parentids)) {
			return null;
		}

		$parents = array_fill_keys($parentids, true);

		$_options = [
			'output' => ['serviceupid', 'servicedownid']
		];
		$db_links = DBselect(DB::makeSql('services_links', $_options));

		$relations = [];

		while ($db_link = DBfetch($db_links)) {
			$relations[$db_link['serviceupid']][$db_link['servicedownid']] = true;
		}

		$limit_services = [];

		while ($parents) {
			$next_parents = [];

			foreach (array_intersect_key($relations, $parents) as $children) {
				$next_parents += $children;
			}

			$parents = $next_parents;
			$limit_services += $next_parents;
		}

		return $limit_services;
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

		if ($options['parentids'] !== null) {
			if (in_array(0, $options['parentids']) && $options['root_services'] !== null) {
				if (count($options['parentids']) > 1) {
					$conditions = [
						'slp.serviceupid' => dbConditionId('slp.serviceupid', array_diff($options['parentids'], [0])),
						's.serviceid' => dbConditionId('s.serviceid', array_keys($options['root_services']))
					];
				}
				else {
					$conditions = [
						's.serviceid' => dbConditionId('s.serviceid', array_keys($options['root_services']))
					];
				}
			}
			else {
				$conditions = [
					'slp.serviceupid' => dbConditionId('slp.serviceupid', $options['parentids'])
				];
			}

			if (array_key_exists('slp.serviceupid', $conditions)) {
				$sql_parts['left_table'] = ['table' => 'services', 'alias' => 's'];
				$sql_parts['left_join'][] = [
					'table' => 'services_links',
					'alias' => 'slp',
					'using' => 'servicedownid'
				];
			}

			$sql_parts['where'][] = count($conditions) > 1 ? '('.implode(' OR ', $conditions).')' : reset($conditions);
		}

		if ($options['childids'] !== null) {
			$sql_parts['left_table'] = ['table' => 'services', 'alias' => 's'];
			$sql_parts['left_join'][] = [
				'table' => 'services_links',
				'alias' => 'slc',
				'using' => 'serviceupid'
			];
			$sql_parts['where'][] = dbConditionId('slc.servicedownid', $options['childids']);
		}

		if ($options['tags']) {
			$sql_parts['where'][] = CApiTagHelper::addWhereCondition($options['tags'], $options['evaltype'], 's',
				'service_tag', 'serviceid'
			);
		}

		if ($options['problem_tags']) {
			$sql_parts['where'][] = CApiTagHelper::addWhereCondition($options['problem_tags'], $options['evaltype'],
				's', 'service_problem_tag', 'serviceid'
			);
		}
		elseif ($options['without_problem_tags']) {
			$sql_parts['left_table'] = ['table' => 'services', 'alias' => 's'];
			$sql_parts['left_join'][] = [
				'table' => 'service_problem_tag',
				'alias' => 'spt',
				'using' => 'serviceid'
			];
			$sql_parts['where'][] = dbConditionId('spt.service_problem_tagid', [0]);
		}

		return $sql_parts;
	}

	/**
	 * @param array      $options
	 * @param array      $result
	 * @param array|null $permissions
	 * @param bool       $preserve_ids
	 *
	 * @return array
	 *
	 * @throws APIException
	 */
	protected function addRelatedObjects(array $options, array $result, array $permissions = null,
			bool $preserve_ids = false): array {
		$result = parent::addRelatedObjects($options, $result);

		$this->addRelatedParents($options, $result, $permissions, $preserve_ids);
		$this->addRelatedChildren($options, $result, $permissions, $preserve_ids);
		$this->addRelatedTags($options, $result, $preserve_ids);
		$this->addRelatedProblemTags($options, $result, $preserve_ids);
		$this->addRelatedProblemEvents($options, $result);
		$this->addRelatedTimes($options, $result, $preserve_ids);
		$this->addRelatedStatusRules($options, $result, $preserve_ids);
		$this->addRelatedAlarms($options, $result);

		return $result;
	}

	/**
	 * @param array      $options
	 * @param array      $result
	 * @param array|null $permissions
	 * @param bool       $preserve_ids
	 *
	 * @throws APIException
	 */
	private function addRelatedParents(array $options, array &$result, ?array $permissions, bool $preserve_ids): void {
		if ($options['selectParents'] === null) {
			return;
		}

		$_options = [
			'output' => ['linkid', 'serviceupid', 'servicedownid']
		];
		$db_links = DBselect(DB::makeSql('services_links', $_options));

		$relations = [];

		while ($db_link = DBfetch($db_links)) {
			$relations[$db_link['servicedownid']][$db_link['serviceupid']] = $db_link['linkid'];
		}

		/*
		 * Performance optimized:
		 * - Not filtering the output by the related service IDs.
		 */
		$parents = $this->doGet([
			'output' => ($options['selectParents'] === API_OUTPUT_COUNT) ? [] : $options['selectParents'],
			'sortfield' => $options['sortfield'],
			'sortorder' => $options['sortorder'],
			'preservekeys' => true
		], $permissions);

		foreach ($result as $serviceid => &$row) {
			$row['parents'] = array_key_exists($serviceid, $relations)
				? array_intersect_key($parents, $relations[$serviceid])
				: [];

			if ($options['selectParents'] === API_OUTPUT_COUNT) {
				$row['parents'] = (string) count($row['parents']);
			}
			else {
				if ($preserve_ids) {
					foreach ($row['parents'] as $parent_serviceid => &$parent) {
						$parent['linkid'] = $relations[$serviceid][$parent_serviceid];
					}
					unset($parent);
				}

				$row['parents'] = array_values($row['parents']);
			}
		}
		unset($row);
	}

	/**
	 * @param array      $options
	 * @param array      $result
	 * @param array|null $permissions
	 * @param bool       $preserve_ids
	 *
	 * @throws APIException
	 */
	private function addRelatedChildren(array $options, array &$result, ?array $permissions, bool $preserve_ids): void {
		if ($options['selectChildren'] === null) {
			return;
		}

		$_options = [
			'output' => ['linkid', 'serviceupid', 'servicedownid']
		];
		$db_links = DBselect(DB::makeSql('services_links', $_options));

		$relations = [];

		while ($db_link = DBfetch($db_links)) {
			$relations[$db_link['serviceupid']][$db_link['servicedownid']] = $db_link['linkid'];
		}

		/*
		 * Performance optimized:
		 * - Not filtering the output by the related service IDs.
		 */
		$children = $this->doGet([
			'output' => ($options['selectChildren'] === API_OUTPUT_COUNT) ? [] : $options['selectChildren'],
			'sortfield' => $options['sortfield'],
			'sortorder' => $options['sortorder'],
			'preservekeys' => true
		], $permissions);

		foreach ($result as $serviceid => &$row) {
			$row['children'] = array_key_exists($serviceid, $relations)
				? array_intersect_key($children, $relations[$serviceid])
				: [];

			if ($options['selectChildren'] === API_OUTPUT_COUNT) {
				$row['children'] = (string) count($row['children']);
			}
			else {
				if ($preserve_ids) {
					foreach ($row['children'] as $child_serviceid => &$child) {
						$child['linkid'] = $relations[$serviceid][$child_serviceid];
					}
					unset($child);
				}

				$row['children'] = array_values($row['children']);
			}
		}
		unset($row);
	}

	/**
	 * @param array $options
	 * @param array $result
	 * @param bool  $preserve_ids
	 */
	private function addRelatedTags(array $options, array &$result, bool $preserve_ids): void {
		if ($options['selectTags'] === null) {
			return;
		}

		foreach ($result as &$row) {
			$row['tags'] = [];
		}
		unset($row);

		if ($options['selectTags'] === API_OUTPUT_COUNT) {
			$output = ['servicetagid', 'serviceid'];
		}
		elseif ($options['selectTags'] === API_OUTPUT_EXTEND) {
			$output = ['servicetagid', 'serviceid', 'tag', 'value'];
		}
		else {
			$output = array_unique(array_merge(['servicetagid', 'serviceid'], $options['selectTags']));
		}

		$_options = [
			'output' => $output,
			'filter' => ['serviceid' => array_keys($result)]
		];
		$db_tags = DBselect(DB::makeSql('service_tag', $_options));

		while ($db_tag = DBfetch($db_tags)) {
			$serviceid = $db_tag['serviceid'];

			if (!$this->outputIsRequested('serviceid', $output)) {
				unset($db_tag['serviceid']);
			}

			if (!$preserve_ids) {
				unset($db_tag['servicetagid']);
			}

			$result[$serviceid]['tags'][] = $db_tag;
		}

		if ($options['selectTags'] === API_OUTPUT_COUNT) {
			foreach ($result as &$row) {
				$row['tags'] = (string) count($row['tags']);
			}
			unset($row);
		}
	}

	/**
	 * @param array $options
	 * @param array $result
	 * @param bool  $preserve_ids
	 */
	private function addRelatedProblemTags(array $options, array &$result, bool $preserve_ids): void {
		if ($options['selectProblemTags'] === null) {
			return;
		}

		foreach ($result as &$row) {
			$row['problem_tags'] = [];
		}
		unset($row);

		if ($options['selectProblemTags'] === API_OUTPUT_COUNT) {
			$output = ['service_problem_tagid', 'serviceid'];
		}
		elseif ($options['selectProblemTags'] === API_OUTPUT_EXTEND) {
			$output = ['service_problem_tagid', 'serviceid', 'tag', 'operator', 'value'];
		}
		else {
			$output = array_unique(array_merge(['service_problem_tagid', 'serviceid'], $options['selectProblemTags']));
		}

		$_options = [
			'output' => $output,
			'filter' => ['serviceid' => array_keys($result)]
		];
		$db_problem_tags = DBselect(DB::makeSql('service_problem_tag', $_options));

		while ($db_problem_tag = DBfetch($db_problem_tags)) {
			$serviceid = $db_problem_tag['serviceid'];

			if (!$this->outputIsRequested('serviceid', $output)) {
				unset($db_problem_tag['serviceid']);
			}

			if (!$preserve_ids) {
				unset($db_problem_tag['service_problem_tagid']);
			}

			$result[$serviceid]['problem_tags'][] = $db_problem_tag;
		}

		if ($options['selectProblemTags'] === API_OUTPUT_COUNT) {
			foreach ($result as &$row) {
				$row['problem_tags'] = (string) count($row['problem_tags']);
			}
			unset($row);
		}
	}

	/**
	 * @param array $options
	 * @param array $result
	 *
	 * @throws APIException
	 */
	private function addRelatedProblemEvents(array $options, array &$result): void {
		if ($options['selectProblemEvents'] === null) {
			return;
		}

		$_options = [
			'output' => ['serviceupid', 'servicedownid']
		];
		$db_links = DBselect(DB::makeSql('services_links', $_options));

		$relations = [];

		while ($db_link = DBfetch($db_links)) {
			$relations[$db_link['serviceupid']][$db_link['servicedownid']] = true;
		}

		$services_without_children = [];

		$parents = $result;

		while ($parents) {
			$next_parents = [];

			foreach (array_keys($parents) as $serviceid) {
				if (array_key_exists($serviceid, $relations)) {
					$next_parents += $relations[$serviceid];
				}
				else {
					$services_without_children[$serviceid] = true;
				}
			}

			$parents = $next_parents;
		}

		/*
		 * Performance optimized:
		 * - Not filtering the output by the related service IDs.
		 */
		$services = $this->doGet([
			'output' => ['status', 'algorithm', 'weight', 'propagation_rule', 'propagation_value'],
			'selectStatusRules' => ['type', 'limit_value', 'limit_status', 'new_status'],
			'preservekeys' => true
		]);

		if ($options['selectProblemEvents'] === API_OUTPUT_COUNT) {
			$output = ['serviceid'];
		}
		elseif ($options['selectProblemEvents'] === API_OUTPUT_EXTEND) {
			$output = ['serviceid', 'eventid', 'severity', 'name'];
		}
		else {
			$output = array_unique(array_merge(['serviceid', 'eventid'], $options['selectProblemEvents']));
		}

		$do_output_name = in_array('name', $output);

		if ($do_output_name) {
			$output = array_diff($output, ['name']);
		}

		$_options = [
			'output' => $output
		];
		$db_service_problems = DBselect(DB::makeSql('service_problem', $_options));

		$service_problems = array_fill_keys(array_keys($services_without_children), []);

		while ($db_service_problem = DBfetch($db_service_problems)) {
			$service_problems[$db_service_problem['serviceid']][] = $db_service_problem;
		}

		$problem_events = [];
		$problem_events_ungrouped = [];

		foreach (array_keys($result) as $serviceid) {
			$problem_events[$serviceid] = $services[$serviceid]['status'] != ZBX_SEVERITY_OK
				? self::getProblemEvents((string) $serviceid, $services, $relations, $service_problems)
				: [];

			$problem_events_ungrouped += $problem_events[$serviceid];
		}

		if ($do_output_name && $problem_events_ungrouped) {
			$events = API::Event()->get([
				'output' => ['name'],
				'eventids' => array_keys($problem_events_ungrouped),
				'source' => EVENT_SOURCE_TRIGGERS,
				'object' => EVENT_OBJECT_TRIGGER,
				'value' => TRIGGER_VALUE_TRUE,
				'nopermissions' => true,
				'preservekeys' => true
			]);

			if (count($events) != count($problem_events_ungrouped)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}
		}

		foreach ($result as $serviceid => &$service) {
			if ($options['selectProblemEvents'] === API_OUTPUT_COUNT) {
				$service['problem_events'] = count($problem_events[$serviceid]);
			}
			else {
				$service_problem_events = $problem_events[$serviceid];
				$service_problem_events = $this->unsetExtraFields($service_problem_events, ['serviceid']);
				$service_problem_events = $this->unsetExtraFields($service_problem_events, ['eventid'],
					$options['selectProblemEvents']
				);

				if ($do_output_name) {
					foreach ($service_problem_events as $eventid => &$problem_event) {
						$problem_event['name'] = $events[$eventid]['name'];
					}
					unset($problem_event);
				}

				$service['problem_events'] = array_values($service_problem_events);
			}
		}
		unset($service);
	}

	/**
	 * @param array $options
	 * @param array $result
	 * @param bool  $preserve_ids
	 */
	private function addRelatedTimes(array $options, array &$result, bool $preserve_ids): void {
		if ($options['selectTimes'] === null) {
			return;
		}

		foreach ($result as &$row) {
			$row['times'] = [];
		}
		unset($row);

		if ($options['selectTimes'] === API_OUTPUT_COUNT) {
			$output = ['timeid', 'serviceid'];
		}
		elseif ($options['selectTimes'] === API_OUTPUT_EXTEND) {
			$output = ['timeid', 'serviceid', 'type', 'ts_from', 'ts_to', 'note'];
		}
		else {
			$output = array_unique(array_merge(['timeid', 'serviceid'], $options['selectTimes']));
		}

		$_options = [
			'output' => $output,
			'filter' => ['serviceid' => array_keys($result)]
		];
		$db_times = DBselect(DB::makeSql('services_times', $_options));

		while ($db_time = DBfetch($db_times)) {
			$serviceid = $db_time['serviceid'];

			if (!$this->outputIsRequested('serviceid', $output)) {
				unset($db_time['serviceid']);
			}

			if (!$preserve_ids) {
				unset($db_time['timeid']);
			}

			$result[$serviceid]['times'][] = $db_time;
		}

		if ($options['selectTimes'] === API_OUTPUT_COUNT) {
			foreach ($result as &$row) {
				$row['times'] = (string) count($row['times']);
			}
			unset($row);
		}
	}

	/**
	 * @param array $options
	 * @param array $result
	 * @param bool  $preserve_ids
	 */
	private function addRelatedStatusRules(array $options, array &$result, bool $preserve_ids): void {
		if ($options['selectStatusRules'] === null) {
			return;
		}

		foreach ($result as &$row) {
			$row['status_rules'] = [];
		}
		unset($row);

		if ($options['selectStatusRules'] === API_OUTPUT_COUNT) {
			$output = ['service_status_ruleid', 'serviceid'];
		}
		elseif ($options['selectStatusRules'] === API_OUTPUT_EXTEND) {
			$output = ['service_status_ruleid', 'serviceid', 'type', 'limit_value', 'limit_status', 'new_status'];
		}
		else {
			$output = array_unique(array_merge(['service_status_ruleid', 'serviceid'], $options['selectStatusRules']));
		}

		$_options = [
			'output' => $output,
			'filter' => ['serviceid' => array_keys($result)]
		];
		$db_status_rules = DBselect(DB::makeSql('service_status_rule', $_options));

		while ($db_status_rule = DBfetch($db_status_rules)) {
			$serviceid = $db_status_rule['serviceid'];

			if (!$this->outputIsRequested('serviceid', $output)) {
				unset($db_status_rule['serviceid']);
			}

			if (!$preserve_ids) {
				unset($db_status_rule['service_status_ruleid']);
			}

			$result[$serviceid]['status_rules'][] = $db_status_rule;
		}

		if ($options['selectStatusRules'] === API_OUTPUT_COUNT) {
			foreach ($result as &$row) {
				$row['status_rules'] = (string) count($row['status_rules']);
			}
			unset($row);
		}
	}

	/**
	 * @param array $options
	 * @param array $result
	 */
	private function addRelatedAlarms(array $options, array &$result): void {
		if ($options['selectAlarms'] === null) {
			return;
		}

		foreach ($result as &$row) {
			$row['alarms'] = [];
		}
		unset($row);

		if ($options['selectAlarms'] === API_OUTPUT_COUNT) {
			$output = ['servicealarmid', 'serviceid'];
		}
		elseif ($options['selectAlarms'] === API_OUTPUT_EXTEND) {
			$output = ['servicealarmid', 'serviceid', 'clock', 'value'];
		}
		else {
			$output = array_unique(array_merge(['servicealarmid', 'serviceid'], $options['selectAlarms']));
		}

		$_options = [
			'output' => $output,
			'filter' => ['serviceid' => array_keys($result)]
		];
		$db_alarms = DBselect(DB::makeSql('service_alarms', $_options));

		while ($db_alarm = DBfetch($db_alarms)) {
			$serviceid = $db_alarm['serviceid'];

			unset($db_alarm['servicealarmid']);
			unset($db_alarm['serviceid']);

			$result[$serviceid]['alarms'][] = $db_alarm;
		}

		if ($options['selectAlarms'] === API_OUTPUT_COUNT) {
			foreach ($result as &$row) {
				$row['alarms'] = (string) count($row['alarms']);
			}
			unset($row);
		}
	}

	/**
	 * @static
	 *
	 * @param string   $parent_serviceid
	 * @param array    $services
	 * @param array    $relations
	 * @param array    $service_problems
	 * @param int|null $min_status
	 *
	 * @return array
	 */
	private static function getProblemEvents(string $parent_serviceid, array $services, array $relations,
			array $service_problems, int $min_status = null): array {
		$parent = $services[$parent_serviceid];

		if (!array_key_exists($parent_serviceid, $relations)) {
			if ($min_status !== null) {
				$problem_events = array_filter($service_problems[$parent_serviceid],
					static function (array $problem) use ($min_status): bool {
						return $problem['severity'] >= $min_status;
					}
				);
			}
			else {
				$problem_events = $service_problems[$parent_serviceid];
			}

			return array_column($problem_events, null, 'eventid');
		}

		$children = array_filter(array_intersect_key($services, $relations[$parent_serviceid]),
			static function (array $service): bool {
				return $service['propagation_rule'] != ZBX_SERVICE_STATUS_PROPAGATION_IGNORE;
			}
		);

		$children_upstream_status = [];

		foreach ($children as $child_serviceid => $child) {
			if ($child['status'] == ZBX_SEVERITY_OK) {
				$status = ZBX_SEVERITY_OK;
			}
			else {
				switch ($child['propagation_rule']) {
					case ZBX_SERVICE_STATUS_PROPAGATION_INCREASE:
						$status = min(TRIGGER_SEVERITY_COUNT - 1, $child['status'] + $child['propagation_value']);
						break;

					case ZBX_SERVICE_STATUS_PROPAGATION_DECREASE:
						$status = max(TRIGGER_SEVERITY_NOT_CLASSIFIED, $child['status'] - $child['propagation_value']);
						break;

					case ZBX_SERVICE_STATUS_PROPAGATION_FIXED:
						$status = $child['propagation_value'];
						break;

					default:
						$status = $child['status'];
						break;
				}
			}

			$children_upstream_status[$child_serviceid] = $status;
		}

		$not_ok_children = array_intersect_key($children, array_filter($children_upstream_status,
			static function (int $status): bool {
				return $status != ZBX_SEVERITY_OK;
			}
		));

		switch ($parent['algorithm']) {
			case ZBX_SERVICE_STATUS_CALC_MOST_CRITICAL_ALL:
				if (count($not_ok_children) == count($children)) {
					$evaluate_children = array_fill_keys(array_keys($not_ok_children), null);
					$evaluate_additional_rules = false;
				}
				else {
					$evaluate_children = [];
					$evaluate_additional_rules = true;
				}

				break;

			case ZBX_SERVICE_STATUS_CALC_MOST_CRITICAL_ONE:
				if ($min_status !== null) {
					$evaluate_children = [];

					foreach ($not_ok_children as $child_serviceid => $child) {
						if ($child['status'] < $min_status) {
							continue;
						}

						switch ($child['propagation_rule']) {
							case ZBX_SERVICE_STATUS_PROPAGATION_INCREASE:
								$reverse_min_status = max(TRIGGER_SEVERITY_NOT_CLASSIFIED,
									$min_status - $child['propagation_value']
								);
								break;

							case ZBX_SERVICE_STATUS_PROPAGATION_DECREASE:
								$reverse_min_status = min(TRIGGER_SEVERITY_COUNT - 1,
									$min_status + $child['propagation_value']
								);
								break;

							case ZBX_SERVICE_STATUS_PROPAGATION_FIXED:
								$reverse_min_status = null;
								break;

							default:
								$reverse_min_status = $min_status;
								break;
						}

						$evaluate_children[$child_serviceid] = $reverse_min_status;
					}

					$evaluate_additional_rules = true;
				}
				else {
					$evaluate_children = array_fill_keys(array_keys($not_ok_children), null);
					$evaluate_additional_rules = false;
				}

				break;

			default:
				$evaluate_children = [];
				$evaluate_additional_rules = true;
		}

		if ($evaluate_additional_rules) {
			foreach ($parent['status_rules'] as $status_rule) {
				if ($min_status !== null && $status_rule['new_status'] < $min_status) {
					continue;
				}

				$is_less_than = in_array($status_rule['type'], [
					ZBX_SERVICE_STATUS_RULE_TYPE_N_L,
					ZBX_SERVICE_STATUS_RULE_TYPE_NP_L,
					ZBX_SERVICE_STATUS_RULE_TYPE_W_L,
					ZBX_SERVICE_STATUS_RULE_TYPE_WP_L
				]);

				$is_weight = in_array($status_rule['type'], [
					ZBX_SERVICE_STATUS_RULE_TYPE_W_GE,
					ZBX_SERVICE_STATUS_RULE_TYPE_WP_GE,
					ZBX_SERVICE_STATUS_RULE_TYPE_W_L,
					ZBX_SERVICE_STATUS_RULE_TYPE_WP_L
				]);

				$is_percentage = in_array($status_rule['type'], [
					ZBX_SERVICE_STATUS_RULE_TYPE_NP_GE,
					ZBX_SERVICE_STATUS_RULE_TYPE_NP_L,
					ZBX_SERVICE_STATUS_RULE_TYPE_WP_GE,
					ZBX_SERVICE_STATUS_RULE_TYPE_WP_L
				]);

				$rule_children = [];

				foreach ($children as $child_serviceid => $child) {
					$status_matched = $is_less_than
						? $children_upstream_status[$child_serviceid] > $status_rule['limit_status']
						: $children_upstream_status[$child_serviceid] >= $status_rule['limit_status'];

					$weight_matched = !$is_weight || $child['weight'] > 0;

					if ($status_matched && $weight_matched) {
						$rule_children[$child_serviceid] = $child;
					}
				}

				if ($is_weight) {
					$value = 0;

					foreach ($rule_children as $child) {
						$value += $child['weight'];
					}

					$value_total = 0;

					foreach ($children as $child) {
						$value_total += $child['weight'];
					}
				}
				else {
					$value = count($rule_children);
					$value_total = count($children);
				}

				$limit_value = $is_percentage
					? $status_rule['limit_value'] * $value_total / 100
					: $status_rule['limit_value'];

				$rule_qualifies = $is_less_than ? $value_total - $value < $limit_value : $value >= $limit_value;

				if ($rule_qualifies) {
					$rule_min_status = $is_less_than ? $status_rule['limit_status'] + 1 : $status_rule['limit_status'];

					foreach ($rule_children as $child_serviceid => $child) {
						switch ($child['propagation_rule']) {
							case ZBX_SERVICE_STATUS_PROPAGATION_INCREASE:
								$reverse_rule_min_status = max(TRIGGER_SEVERITY_NOT_CLASSIFIED,
									$rule_min_status - $child['propagation_value']
								);
								break;

							case ZBX_SERVICE_STATUS_PROPAGATION_DECREASE:
								$reverse_rule_min_status = min(TRIGGER_SEVERITY_COUNT - 1,
									$rule_min_status + $child['propagation_value']
								);
								break;

							case ZBX_SERVICE_STATUS_PROPAGATION_FIXED:
								$reverse_rule_min_status = null;
								break;

							default:
								$reverse_rule_min_status = $rule_min_status;
								break;
						}

						if (array_key_exists($child_serviceid, $evaluate_children)) {
							if ($evaluate_children[$child_serviceid] !== null) {
								$evaluate_children[$child_serviceid] = min($evaluate_children[$child_serviceid],
									$reverse_rule_min_status
								);
							}
						}
						else {
							$evaluate_children[$child_serviceid] = $reverse_rule_min_status;
						}
					}
				}
			}
		}

		$problem_events = [];

		foreach ($evaluate_children as $child_serviceid => $child_min_status) {
			$problem_events += self::getProblemEvents((string) $child_serviceid, $services, $relations,
				$service_problems, (int) $child_min_status
			);
		}

		return $problem_events;
	}

	/**
	 * @param array $services
	 * @param array $db_services
	 *
	 * @throws APIException
	 */
	private function checkParentChildRelations(array $services, array $db_services): void {
		$services = array_column($services, null, 'serviceid');

		foreach ($services as $serviceid => $service) {
			if (!array_key_exists('parents', $service)) {
				continue;
			}

			$parent_services = array_intersect_key($services,
				array_column($service['parents'], 'serviceid', 'serviceid')
			);

			foreach ($parent_services as $parent_serviceid => $parent_service) {
				if (!array_key_exists('children', $parent_service)) {
					continue;
				}

				$parent_child_services = array_column($parent_service['children'], 'serviceid', 'serviceid');

				if (!array_key_exists($serviceid, $parent_child_services)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s(
						'Parent-child relation conflict in services "%1$s" and "%2$s".',
						$db_services[$parent_serviceid]['name'], $db_services[$serviceid]['name']
					));
				}
			}
		}
	}

	/**
	 * @param array      $services
	 * @param array|null $db_services
	 *
	 * @throws APIException
	 */
	private function checkStatusPropagation(array $services, array $db_services = null): void {
		foreach ($services as $service) {
			$name = $db_services !== null ? $db_services[$service['serviceid']]['name'] : $service['name'];

			if (array_key_exists('propagation_rule', $service) && !array_key_exists('propagation_value', $service)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s(
					'Cannot specify "propagation_rule" parameter without specifying "propagation_value" parameter for service "%1$s".',
					$name
				));
			}

			if (!array_key_exists('propagation_value', $service)) {
				continue;
			}

			if (array_key_exists('propagation_rule', $service)) {
				$propagation_rule = $service['propagation_rule'];
			}
			elseif ($db_services !== null) {
				$propagation_rule = $db_services[$service['serviceid']]['propagation_rule'];
			}
			else {
				$propagation_rule = DB::getDefault('services', 'propagation_rule');
			}

			switch ($propagation_rule) {
				case ZBX_SERVICE_STATUS_PROPAGATION_INCREASE:
				case ZBX_SERVICE_STATUS_PROPAGATION_DECREASE:
					$propagation_values = range(1, TRIGGER_SEVERITY_COUNT - 1);
					break;

				case ZBX_SERVICE_STATUS_PROPAGATION_FIXED:
					$propagation_values = array_merge([ZBX_SEVERITY_OK],
						range(TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_COUNT - 1)
					);
					break;

				default:
					$propagation_values = [0];
					break;
			}

			if (!in_array($service['propagation_value'], $propagation_values)) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Incompatible "propagation_rule" and "propagation_value" parameters for service "%1$s".', $name)
				);
			}
		}
	}

	/**
	 * @param array      $services
	 * @param array|null $db_services
	 *
	 * @throws APIException
	 */
	private function checkGoodSla(array $services, array $db_services = null): void {
		foreach ($services as $service) {
			$name = $db_services !== null ? $db_services[$service['serviceid']]['name'] : $service['name'];

			if (array_key_exists('goodsla', $service)
					&& round($service['goodsla'], 4) != $service['goodsla']) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Service "%1$s" acceptable SLA must have no more than 4 digits after the decimal point.', $name)
				);
			}
		}
	}

	/**
	 * @param array      $services
	 * @param array|null $db_services
	 *
	 * @throws APIException
	 */
	private function checkAlgorithmDependencies(array $services, array $db_services = null): void {
		foreach ($services as $service) {
			$name = $db_services !== null ? $db_services[$service['serviceid']]['name'] : $service['name'];

			$algorithm = array_key_exists('algorithm', $service)
				? $service['algorithm']
				: $db_services[$service['serviceid']]['algorithm'];

			if ($algorithm == ZBX_SERVICE_STATUS_CALC_SET_OK) {
				$showsla = array_key_exists('showsla', $service)
					? $service['showsla']
					: $db_services[$service['serviceid']]['showsla'];

				if ($showsla == SERVICE_SHOW_SLA_ON) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Service "%1$s" cannot show SLA for the selected status calculation rule.', $name)
					);
				}
			}

			if (array_key_exists('problem_tags', $service)) {
				$has_problem_tags = count($service['problem_tags']) > 0;
			}
			elseif ($db_services !== null) {
				$has_problem_tags = count($db_services[$service['serviceid']]['problem_tags']) > 0;
			}
			else {
				$has_problem_tags = false;
			}

			if (array_key_exists('children', $service)) {
				$has_children = count($service['children']) > 0;
			}
			elseif ($db_services !== null) {
				$has_children = count($db_services[$service['serviceid']]['children']) > 0;
			}
			else {
				$has_children = false;
			}

			if ($has_problem_tags && $has_children) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Service "%1$s" cannot have problem tags and children at the same time.', $name)
				);
			}
		}
	}

	/**
	 * @param array $services
	 *
	 * @throws APIException
	 */
	private function checkChildrenOrProblemTags(array $services): void {
		$parent_serviceids = [];

		foreach ($services as $service) {
			if (array_key_exists('parents', $service)) {
				$parent_serviceids += array_column($service['parents'], 'serviceid', 'serviceid');
			}
		}

		if (!$parent_serviceids) {
			return;
		}

		$db_parent_services = $this->doGet([
			'output' => ['name'],
			'selectProblemTags' => API_OUTPUT_COUNT,
			'serviceids' => $parent_serviceids,
			'preservekeys' => true
		]);

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
	 * @param array      $services
	 * @param array|null $db_services
	 *
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

		foreach (array_keys(array_intersect_key($add_references, $del_references)) as $parent_serviceid) {
			$common_references = array_intersect_key($add_references[$parent_serviceid],
				$del_references[$parent_serviceid]
			);

			$add_references[$parent_serviceid] = array_diff_key($add_references[$parent_serviceid], $common_references);
			$del_references[$parent_serviceid] = array_diff_key($del_references[$parent_serviceid], $common_references);
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

		foreach ($add_references as $parent_serviceid => $children) {
			foreach (array_keys($children) as $child_serviceid) {
				$reverse_references[$child_serviceid][$parent_serviceid] = true;
			}
		}

		while ($add_references) {
			$db_links = DB::select('services_links', [
				'output' => ['serviceupid', 'servicedownid'],
				'filter' => ['servicedownid' => array_keys($add_references)]
			]);

			$db_parents = [];

			foreach ($db_links as $db_link) {
				if (!array_key_exists($db_link['serviceupid'], $del_references)
						|| !array_key_exists($db_link['servicedownid'], $del_references[$db_link['serviceupid']])) {
					$db_parents[$db_link['servicedownid']][$db_link['serviceupid']] = true;
				}
			}

			$next_references = [];

			foreach ($add_references as $parent_serviceid => $children) {
				foreach (array_keys($children) as $child_serviceid) {
					if ((string) $child_serviceid === (string) $parent_serviceid) {
						return true;
					}

					if (array_key_exists($parent_serviceid, $reverse_references)) {
						foreach (array_keys($reverse_references[$parent_serviceid]) as $serviceid) {
							$next_references[$serviceid][$child_serviceid] = true;
						}
					}

					if (array_key_exists($parent_serviceid, $db_parents)) {
						foreach (array_keys($db_parents[$parent_serviceid]) as $serviceid) {
							$next_references[$serviceid][$child_serviceid] = true;
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
	 * @param array      $services
	 * @param array|null $db_services
	 */
	private function updateTags(array &$services, array $db_services = null): void {
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
		$ins_tags_ref = [];

		if ($db_services !== null) {
			foreach (array_keys($serviceids) as $serviceid) {
				$tags_ref = [];

				foreach ($services[$serviceid]['tags'] as &$tag) {
					$tags_ref[$tag['tag']][$tag['value']] = &$tag;
				}
				unset($tag);

				foreach ($db_services[$serviceid]['tags'] as $db_tag) {
					if (array_key_exists($db_tag['tag'], $tags_ref)
							&& array_key_exists($db_tag['value'], $tags_ref[$db_tag['tag']])) {
						$tags_ref[$db_tag['tag']][$db_tag['value']]['servicetagid'] = $db_tag['servicetagid'];
					}
					else {
						$del_tags[$db_tag['servicetagid']] = true;
					}
				}
			}
		}

		foreach (array_keys($serviceids) as $serviceid) {
			foreach ($services[$serviceid]['tags'] as &$tag) {
				if (array_key_exists('servicetagid', $tag)) {
					continue;
				}

				$ins_tags[] = ['serviceid' => $serviceid] + $tag;
				$ins_tags_ref[] = &$tag;
			}
			unset($tag);
		}

		if ($del_tags) {
			DB::delete('service_tag', ['servicetagid' => array_keys($del_tags)]);
		}

		if ($ins_tags) {
			$servicetagids = DB::insertBatch('service_tag', $ins_tags);

			foreach ($servicetagids as $index => $servicetagid) {
				$ins_tags_ref[$index]['servicetagid'] = $servicetagid;
			}
		}
	}

	/**
	 * @param array      $services
	 * @param array|null $db_services
	 */
	private function updateProblemTags(array &$services, array $db_services = null): void {
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
		$ins_problem_tags_ref = [];

		if ($db_services !== null) {
			foreach (array_keys($serviceids) as $serviceid) {
				$problem_tags_ref = [];

				foreach ($services[$serviceid]['problem_tags'] as &$problem_tag) {
					$problem_tags_ref[$problem_tag['tag']][$problem_tag['operator']][$problem_tag['value']] =
						&$problem_tag;
				}
				unset($problem_tag);

				foreach ($db_services[$serviceid]['problem_tags'] as $db_problem_tag) {
					if (array_key_exists($db_problem_tag['tag'], $problem_tags_ref)
							&& array_key_exists($db_problem_tag['operator'], $problem_tags_ref[$db_problem_tag['tag']])
							&& array_key_exists($db_problem_tag['value'],
								$problem_tags_ref[$db_problem_tag['tag']][$db_problem_tag['operator']]
							)) {
						$problem_tags_ref[$db_problem_tag['tag']][$db_problem_tag['operator']][$db_problem_tag['value']]
							['service_problem_tagid'] = $db_problem_tag['service_problem_tagid'];
					}
					else {
						$del_problem_tags[$db_problem_tag['service_problem_tagid']] = true;
					}
				}
			}
		}

		foreach (array_keys($serviceids) as $serviceid) {
			foreach ($services[$serviceid]['problem_tags'] as &$problem_tag) {
				if (array_key_exists('service_problem_tagid', $problem_tag)) {
					continue;
				}

				$ins_problem_tags[] = ['serviceid' => $serviceid] + $problem_tag;
				$ins_problem_tags_ref[] = &$problem_tag;
			}
			unset($problem_tag);
		}

		if ($del_problem_tags) {
			DB::delete('service_problem_tag', ['service_problem_tagid' => array_keys($del_problem_tags)]);
		}

		if ($ins_problem_tags) {
			$service_problem_tagids = DB::insertBatch('service_problem_tag', $ins_problem_tags);

			foreach ($service_problem_tagids as $index => $service_problem_tagid) {
				$ins_problem_tags_ref[$index]['service_problem_tagid'] = $service_problem_tagid;
			}
		}
	}

	/**
	 * @param array      $services
	 * @param array|null $db_services
	 */
	private function updateParents(array &$services, array $db_services = null): void {
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
		$ins_parents_ref = [];

		if ($db_services !== null) {
			foreach (array_keys($serviceids) as $serviceid) {
				$parents_ref = [];

				foreach ($services[$serviceid]['parents'] as &$parent) {
					$parents_ref[$parent['serviceid']] = &$parent;
				}
				unset($parent);

				foreach ($db_services[$serviceid]['parents'] as $db_parent) {
					if (array_key_exists($db_parent['serviceid'], $parents_ref)) {
						$parents_ref[$db_parent['serviceid']]['linkid'] = $db_parent['linkid'];
					}
					else {
						$del_parents[$db_parent['linkid']] = true;
					}
				}
			}
		}

		foreach (array_keys($serviceids) as $serviceid) {
			foreach ($services[$serviceid]['parents'] as &$parent) {
				if (array_key_exists('linkid', $parent)) {
					continue;
				}

				$ins_parents[] = ['servicedownid' => $serviceid, 'serviceupid' => $parent['serviceid']];
				$ins_parents_ref[] = &$parent;
			}
			unset($parent);
		}

		if ($del_parents) {
			DB::delete('services_links', ['linkid' => array_keys($del_parents)]);
		}

		if ($ins_parents) {
			$linkids = DB::insertBatch('services_links', $ins_parents);

			foreach ($linkids as $index => $linkid) {
				$ins_parents_ref[$index]['linkid'] = $linkid;
			}
		}
	}

	/**
	 * @param array      $services
	 * @param array|null $db_services
	 */
	private function updateChildren(array &$services, array $db_services = null): void {
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
		$ins_children_ref = [];

		if ($db_services !== null) {
			foreach (array_keys($serviceids) as $serviceid) {
				$children_ref = [];

				foreach ($services[$serviceid]['children'] as &$child) {
					$children_ref[$child['serviceid']] = &$child;
				}
				unset($child);

				foreach ($db_services[$serviceid]['children'] as $db_child) {
					if (array_key_exists($db_child['serviceid'], $children_ref)) {
						$children_ref[$db_child['serviceid']]['linkid'] = $db_child['linkid'];
					}
					else {
						$del_children[$db_child['linkid']] = true;
					}
				}
			}
		}

		foreach (array_keys($serviceids) as $serviceid) {
			foreach ($services[$serviceid]['children'] as &$child) {
				if (array_key_exists('linkid', $child)) {
					continue;
				}

				$ins_children[] = ['serviceupid' => $serviceid, 'servicedownid' => $child['serviceid']];
				$ins_children_ref[] = &$child;
			}
			unset($child);
		}

		if ($del_children) {
			DB::delete('services_links', ['linkid' => array_keys($del_children)]);
		}

		if ($ins_children) {
			$linkids = DB::insertBatch('services_links', $ins_children);

			foreach ($linkids as $index => $linkid) {
				$ins_children_ref[$index]['linkid'] = $linkid;
			}
		}
	}

	/**
	 * @param array      $services
	 * @param array|null $db_services
	 */
	private function updateTimes(array &$services, array $db_services = null): void {
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
		$ins_times_ref = [];
		$upd_times = [];

		if ($db_services !== null) {
			foreach (array_keys($serviceids) as $serviceid) {
				$times_ref = [];

				foreach ($services[$serviceid]['times'] as &$time) {
					$times_ref[$time['type']][$time['ts_from']][$time['ts_to']] = &$time;
				}
				unset($time);

				foreach ($db_services[$serviceid]['times'] as $db_time) {
					if (array_key_exists($db_time['type'], $times_ref)
							&& array_key_exists($db_time['ts_from'], $times_ref[$db_time['type']])
							&& array_key_exists($db_time['ts_to'], $times_ref[$db_time['type']][$db_time['ts_from']])) {
						$time_ref = &$times_ref[$db_time['type']][$db_time['ts_from']][$db_time['ts_to']];

						$time_ref['timeid'] = $db_time['timeid'];

						$upd_time = DB::getUpdatedValues('services_times', $db_time, $time_ref);

						if ($upd_time) {
							$upd_times[] = [
								'values' => $upd_time,
								'where' => ['timeid' => $db_time['timeid']]
							];
						}
					}
					else {
						$del_times[$db_time['timeid']] = true;
					}
				}
			}
		}

		foreach (array_keys($serviceids) as $serviceid) {
			foreach ($services[$serviceid]['times'] as &$time) {
				if (array_key_exists('timeid', $time)) {
					continue;
				}

				$ins_times[] = ['serviceid' => $serviceid] + $time;
				$ins_times_ref[] = &$time;
			}
			unset($time);
		}

		if ($del_times) {
			DB::delete('services_times', ['timeid' => array_keys($del_times)]);
		}

		if ($ins_times) {
			$timeids = DB::insertBatch('services_times', $ins_times);

			foreach ($timeids as $index => $timeid) {
				$ins_times_ref[$index]['timeid'] = $timeid;
			}
		}

		if ($upd_times) {
			DB::update('services_times', $upd_times);
		}
	}

	/**
	 * @param array      $services
	 * @param array|null $db_services
	 */
	private function updateStatusRules(array &$services, array $db_services = null): void {
		$serviceids = [];

		foreach ($services as $serviceid => $service) {
			if (array_key_exists('status_rules', $service)) {
				$serviceids[$serviceid] = true;
			}
		}

		if (!$serviceids) {
			return;
		}

		$del_status_rules = [];
		$ins_status_rules = [];
		$ins_status_rules_ref = [];
		$upd_status_rules = [];

		if ($db_services !== null) {
			foreach (array_keys($serviceids) as $serviceid) {
				$status_rules_ref = [];

				foreach ($services[$serviceid]['status_rules'] as &$status_rule) {
					$status_rules_ref[$status_rule['type']][$status_rule['limit_value']][$status_rule['limit_status']] =
						&$status_rule;
				}
				unset($status_rule);

				foreach ($db_services[$serviceid]['status_rules'] as $db_status_rule) {
					if (array_key_exists($db_status_rule['type'], $status_rules_ref)
							&& array_key_exists($db_status_rule['limit_value'],
								$status_rules_ref[$db_status_rule['type']]
							)
							&& array_key_exists($db_status_rule['limit_status'],
								$status_rules_ref[$db_status_rule['type']][$db_status_rule['limit_value']]
							)) {
						$status_rule_ref = &$status_rules_ref[$db_status_rule['type']][$db_status_rule['limit_value']]
							[$db_status_rule['limit_status']];

						$status_rule_ref['service_status_ruleid'] = $db_status_rule['service_status_ruleid'];

						$upd_status_rule = DB::getUpdatedValues('service_status_rule', $db_status_rule,
							$status_rule_ref
						);

						if ($upd_status_rule) {
							$upd_status_rules[] = [
								'values' => $upd_status_rule,
								'where' => ['service_status_ruleid' => $db_status_rule['service_status_ruleid']]
							];
						}
					}
					else {
						$del_status_rules[$db_status_rule['service_status_ruleid']] = true;
					}
				}
			}
		}

		foreach (array_keys($serviceids) as $serviceid) {
			foreach ($services[$serviceid]['status_rules'] as &$status_rule) {
				if (array_key_exists('service_status_ruleid', $status_rule)) {
					continue;
				}

				$ins_status_rules[] = ['serviceid' => $serviceid] + $status_rule;
				$ins_status_rules_ref[] = &$status_rule;
			}
			unset($status_rule);
		}

		if ($del_status_rules) {
			DB::delete('service_status_rule', ['service_status_ruleid' => array_keys($del_status_rules)]);
		}

		if ($ins_status_rules) {
			$service_status_ruleids = DB::insertBatch('service_status_rule', $ins_status_rules);

			foreach ($service_status_ruleids as $index => $service_status_ruleid) {
				$ins_status_rules_ref[$index]['service_status_ruleid'] = $service_status_ruleid;
			}
		}

		if ($upd_status_rules) {
			DB::update('service_status_rule', $upd_status_rules);
		}
	}

	/**
	 * @static
	 *
	 * @return array
	 *
	 * @throws APIException
	 */
	private static function getPermissions(): array {
		$role = API::Role()->get([
			'output' => [],
			'selectRules' => ['services.read.mode', 'services.read.list', 'services.read.tag', 'services.write.mode',
				'services.write.list', 'services.write.tag'
			],
			'roleids' => self::$userData['roleid']
		]);

		if (!$role) {
			return [
				'r_services' => [],
				'rw_services' => [],
				'root_services' => [],
				'rw_tag' => ['tag' => '', 'value' => '']
			];
		}

		$rules = $role[0]['rules'];

		if ($rules['services.write.mode'] == ZBX_ROLE_RULE_SERVICES_ACCESS_ALL) {
			return [
				'r_services' => null,
				'rw_services' => null,
				'root_services' => null,
				'rw_tag' => ['tag' => '', 'value' => '']
			];
		}

		if ($rules['services.read.mode'] == ZBX_ROLE_RULE_SERVICES_ACCESS_ALL) {
			$r_services = null;
		}
		else {
			$r_services = array_column($rules['services.read.list'], 'serviceid', 'serviceid');

			if ($rules['services.read.tag']['tag'] !== '') {
				$tags = DB::select('service_tag', [
					'output' => ['serviceid'],
					'filter' => $rules['services.read.tag']['value'] !== ''
						? ['tag' => $rules['services.read.tag']['tag'], 'value' => $rules['services.read.tag']['value']]
						: ['tag' => $rules['services.read.tag']['tag']]
				]);

				$r_services += array_column($tags, 'serviceid', 'serviceid');
			}
		}

		$rw_services = array_fill_keys(array_column($rules['services.write.list'], 'serviceid'), null);

		if ($rules['services.write.tag']['tag'] !== '') {
			$tags = DB::select('service_tag', [
				'output' => ['serviceid'],
				'filter' => $rules['services.write.tag']['value'] !== ''
					? ['tag' => $rules['services.write.tag']['tag'], 'value' => $rules['services.write.tag']['value']]
					: ['tag' => $rules['services.write.tag']['tag']]
			]);

			$rw_services += array_fill_keys(array_column($tags, 'serviceid'), 0);
		}

		$_options = [
			'output' => ['serviceupid', 'servicedownid']
		];
		$db_links = DBselect(DB::makeSql('services_links', $_options));

		$relations = [];

		while ($db_link = DBfetch($db_links)) {
			$relations[$db_link['serviceupid']][$db_link['servicedownid']] = true;
		}

		$root_r_services = $r_services;
		$root_rw_services = $rw_services;

		if ($r_services !== null) {
			$services = $r_services;

			while ($services) {
				$_services = [];

				foreach (array_intersect_key($relations, $services) as $child_services) {
					$root_r_services = array_diff_key($root_r_services, $child_services);
					$root_rw_services = array_diff_key($root_rw_services, $child_services);
					$r_services += $child_services;
					$_services += $child_services;
				}

				$services = $_services;
			}

			$r_services = array_diff_key($r_services, $rw_services);
		}

		$services = $rw_services;

		while ($services) {
			$_services = [];

			foreach (array_intersect_key($relations, $services) as $child_services) {
				if ($root_r_services !== null) {
					$root_r_services = array_diff_key($root_r_services, $child_services);
				}

				$root_rw_services = array_diff_key($root_rw_services, $child_services);
				$_services += $child_services;

				foreach (array_keys($child_services) as $serviceid) {
					if (array_key_exists($serviceid, $rw_services)) {
						if ($rw_services[$serviceid] !== null) {
							$rw_services[$serviceid]++;
						}
					}
					else {
						$rw_services[$serviceid] = 1;
					}
				}
			}

			$services = $_services;
		}

		$root_services = $root_r_services === null ? null : $root_r_services + $root_rw_services;

		return [
			'r_services' => $r_services,
			'rw_services' => $rw_services,
			'root_services' => $root_services,
			'rw_tag' => $rules['services.write.tag']
		];
	}

	/**
	 * @param array      $services
	 * @param array|null $db_services
	 *
	 * @throws APIException
	 */
	private function checkPermissions(array $services, array $db_services = null): void {
		[
			'r_services' => $r_services,
			'rw_services' => $rw_services,
			'rw_tag' => $rw_tag
		] = self::getPermissions();

		if ($r_services === null || $rw_services === null) {
			$accessible_services = null;
		}
		else {
			$accessible_services = $r_services + $rw_services;
		}

		$referred_services = $db_services !== null ? array_column($services, 'serviceid', 'serviceid') : [];

		foreach ($services as $service) {
			if (array_key_exists('parents', $service)) {
				$referred_services += array_column($service['parents'], 'serviceid', 'serviceid');
			}

			if (array_key_exists('children', $service)) {
				$referred_services += array_column($service['children'], 'serviceid', 'serviceid');
			}
		}

		if ($referred_services) {
			if ($accessible_services !== null) {
				if (array_diff_key($referred_services, $accessible_services)) {
					self::exception(ZBX_API_ERROR_PERMISSIONS,
						_('No permissions to referred object or it does not exist!')
					);
				}
			}
			else {
				$count = $this->doGet([
					'countOutput' => true,
					'serviceids' => $referred_services
				]);

				if ($count != count($referred_services)) {
					self::exception(ZBX_API_ERROR_PERMISSIONS,
						_('No permissions to referred object or it does not exist!')
					);
				}
			}
		}

		if ($rw_services === null) {
			return;
		}

		foreach ($services as $service) {
			$name = $db_services !== null ? $db_services[$service['serviceid']]['name'] : $service['name'];

			if ($db_services !== null && !array_key_exists($service['serviceid'], $rw_services)) {
				$error_detail = _('read-write access to the service is required');
				$error = _s('Cannot update service "%1$s": %2$s.', $name, $error_detail);

				self::exception(ZBX_API_ERROR_PERMISSIONS, $error);
			}

			$is_rw_service = $db_services !== null && array_key_exists($service['serviceid'], $rw_services)
				&& $rw_services[$service['serviceid']] === null;

			if (!$is_rw_service) {
				$has_rw_tag = false;

				if ($rw_tag['tag'] !== '') {
					if (array_key_exists('tags', $service)) {
						$tags = $service['tags'];
					}
					elseif ($db_services !== null) {
						$tags = $db_services[$service['serviceid']]['tags'];
					}
					else {
						$tags = [];
					}

					foreach ($tags as $tag) {
						if ($tag['tag'] === $rw_tag['tag']
								&& ($tag['value'] === $rw_tag['value'] || $rw_tag['value'] === '')) {
							$has_rw_tag = true;

							break;
						}
					}
				}

				if ($has_rw_tag && $db_services !== null) {
					$rw_services[$service['serviceid']] = null;
				}

				$is_rw_service = $has_rw_tag;
			}

			if (!$is_rw_service) {
				if (array_key_exists('parents', $service)) {
					$parent_services = array_column($service['parents'], 'serviceid', 'serviceid');

					$has_rw_parents = (bool) array_intersect_key($parent_services, $rw_services);

					if ($has_rw_parents && $db_services !== null) {
						$rw_services[$service['serviceid']] = null;
					}
				}
				else {
					$has_rw_parents = $db_services !== null;
				}

				$is_rw_service = $has_rw_parents;
			}

			if (!$is_rw_service) {
				$error_detail = $db_services !== null
					? _('read-write access to the service must be retained')
					: _('read-write access to the service is required');

				$error = $db_services !== null
					? _s('Cannot update service "%1$s": %2$s.', $name, $error_detail)
					: _s('Cannot create service "%1$s": %2$s.', $name, $error_detail);

				self::exception(ZBX_API_ERROR_PERMISSIONS, $error);
			}

			if (array_key_exists('children', $service)) {
				$new_child_services = array_column($service['children'], 'serviceid', 'serviceid');
				$old_child_services = $db_services !== null
					? array_column($db_services[$service['serviceid']]['children'], 'serviceid', 'serviceid')
					: [];

				$inaccessible_services = array_diff_key($new_child_services, $rw_services);

				if ($inaccessible_services) {
					$inaccessible_service = $this->doGet([
						'output' => ['name'],
						'serviceids' => array_keys($inaccessible_services)[0]
					])[0];

					$error_detail = _s('read-write access to the child service "%1$s" is required',
						$inaccessible_service['name']
					);
					$error = $db_services !== null
						? _s('Cannot update service "%1$s": %2$s.', $name, $error_detail)
						: _s('Cannot create service "%1$s": %2$s.', $name, $error_detail);

					self::exception(ZBX_API_ERROR_PERMISSIONS, $error);
				}

				if ($db_services !== null) {
					foreach (array_keys(array_diff_key($old_child_services, $new_child_services)) as $serviceid) {
						if ($rw_services[$serviceid] !== null) {
							$rw_services[$serviceid]--;
						}
					}

					foreach (array_keys(array_diff_key($new_child_services, $old_child_services)) as $serviceid) {
						if ($rw_services[$serviceid] !== null) {
							$rw_services[$serviceid]++;
						}
					}
				}
			}
		}

		if ($db_services !== null) {
			$affected_rw_services = array_intersect_key($rw_services, array_column($services, 'serviceid',
				'serviceid'
			));

			foreach ($affected_rw_services as $serviceid => $num_rw_parents) {
				if ($num_rw_parents !== null && $num_rw_parents < 1) {
					$inaccessible_service = $this->doGet([
						'output' => ['name'],
						'serviceids' => $serviceid
					])[0];

					$error_detail = _('read-write access to the service must be retained');
					$error = _s('Cannot update service "%1$s": %2$s.', $inaccessible_service['name'], $error_detail);

					self::exception(ZBX_API_ERROR_PERMISSIONS, $error);
				}
			}
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
	 * Returns an array of requested intervals with SLA information:
	 *  - from              - the beginning of the interval;
	 *  - to                - the end of the interval;
	 *  - okTime            - the time the service was in OK state, in seconds;
	 *  - problemTime       - the time the service was in problem state, in seconds;
	 *  - downtimeTime      - the time the service was down, in seconds.
	 *
	 * If the service calculation algorithm is set to SERVICE_ALGORITHM_NONE, the method will return an empty 'problems'
	 * array and null for all of the calculated values.
	 *
	 * @param array $options
	 *
	 * @return array    as array(serviceId2 => data1, serviceId2 => data2, ...)
	 *
	 * @throws APIException
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
				if ($service['algorithm'] != ZBX_SERVICE_STATUS_CALC_SET_OK) {
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
				$rs[$service['serviceid']] = [];
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

						$rs[$service['serviceid']][] = [
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
}
