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
			'output' =>					['type' => API_OUTPUT, 'in' => implode(',', ['serviceid', 'name', 'status', 'algorithm', 'showsla', 'goodsla', 'sortorder', 'weight', 'propagation_rule', 'propagation_value']), 'default' => API_OUTPUT_EXTEND],
			'countOutput' =>			['type' => API_FLAG, 'default' => false],
			'selectParents' =>			['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL | API_ALLOW_COUNT, 'in' => implode(',', ['serviceid', 'name', 'status', 'algorithm', 'showsla', 'goodsla', 'sortorder', 'weight', 'propagation_rule', 'propagation_value']), 'default' => null],
			'selectChildren' =>			['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL | API_ALLOW_COUNT, 'in' => implode(',', ['serviceid', 'name', 'status', 'algorithm', 'showsla', 'goodsla', 'sortorder', 'weight', 'propagation_rule', 'propagation_value']), 'default' => null],
			'selectTags' =>				['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL | API_ALLOW_COUNT, 'in' => implode(',', ['tag', 'value']), 'default' => null],
			'selectProblemTags' =>		['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL | API_ALLOW_COUNT, 'in' => implode(',', ['tag', 'operator', 'value']), 'default' => null],
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
		$this->updateStatusRules($services, __FUNCTION__);

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

		$this->checkGoodSla($services);
		$this->checkStatusPropagation($services);
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

		$this->updateTags($services, __FUNCTION__);
		$this->updateProblemTags($services, __FUNCTION__);
		$this->updateParents($services, __FUNCTION__);
		$this->updateChildren($services, __FUNCTION__);
		$this->updateTimes($services, __FUNCTION__);
		$this->updateStatusRules($services, __FUNCTION__);

		$this->addAuditBulk(AUDIT_ACTION_UPDATE, self::AUDIT_RESOURCE, $services, $db_services);

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

		$db_services = $this->get([
			'output' => ['serviceid', 'name', 'status', 'algorithm', 'showsla', 'goodsla', 'sortorder', 'weight',
				'propagation_rule', 'propagation_value'
			],
			'selectParents' => ['serviceid'],
			'selectChildren' => ['serviceid'],
			'selectProblemTags' => API_OUTPUT_COUNT,
			'serviceids' => array_column($services, 'serviceid'),
			'editable' => true,
			'preservekeys' => true
		]);

		if (count($db_services) != count($services)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		$this->checkStatusPropagation($services, $db_services);
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
	public function delete(array $serviceids): array {
		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];

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

		$ins_housekeeper = [];

		foreach ($serviceids as $serviceid) {
			$ins_housekeeper[] = [
				'tablename' => 'events',
				'field' => 'serviceid',
				'value' => $serviceid
			];
		}

		DB::insertBatch('housekeeper', $ins_housekeeper);

		$this->addAuditBulk(AUDIT_ACTION_DELETE, self::AUDIT_RESOURCE, $db_services);

		return ['serviceids' => $serviceids];
	}

	protected function applyQueryFilterOptions($tableName, $tableAlias, array $options, array $sqlParts): array {
		$sqlParts = parent::applyQueryFilterOptions($tableName, $tableAlias, $options, $sqlParts);

		if ($options['parentids'] !== null) {
			$sqlParts['left_table'] = ['table' => 'services', 'alias' => 's'];
			$sqlParts['left_join'][] = [
				'table' => 'services_links',
				'alias' => 'slp',
				'using' => 'servicedownid'
			];
			$sqlParts['where'][] = dbConditionId('slp.serviceupid', $options['parentids']);
		}

		if ($options['childids'] !== null) {
			$sqlParts['left_table'] = ['table' => 'services', 'alias' => 's'];
			$sqlParts['left_join'][] = [
				'table' => 'services_links',
				'alias' => 'slc',
				'using' => 'serviceupid'
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
				'using' => 'serviceid'
			];
			$sqlParts['where'][] = dbConditionId('spt.service_problem_tagid', [0]);
		}

		return $sqlParts;
	}

	protected function addRelatedObjects(array $options, array $result): array {
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
			$tags = $this->unsetExtraFields($tags, ['servicetagid', 'serviceid']);
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
			$problem_tags = $this->unsetExtraFields($problem_tags, ['service_problem_tagid', 'serviceid']);
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
			$times = $this->unsetExtraFields($times, ['timeid', 'serviceid']);
			$result = $relation_map->mapMany($result, $times, 'times');

			if ($options['selectTimes'] === API_OUTPUT_COUNT) {
				foreach ($result as &$row) {
					$row['times'] = (string) count($row['times']);
				}
				unset($row);
			}
		}

		if ($options['selectStatusRules'] !== null) {
			if ($options['selectStatusRules'] === API_OUTPUT_COUNT) {
				$output = ['service_status_ruleid', 'serviceid'];
			}
			elseif ($options['selectStatusRules'] === API_OUTPUT_EXTEND) {
				$output = ['service_status_ruleid', 'serviceid', 'type', 'limit_value', 'limit_status', 'new_status'];
			}
			else {
				$output = array_unique(array_merge(['service_status_ruleid', 'serviceid'],
					$options['selectStatusRules']
				));
			}

			$status_rules = DB::select('service_status_rule', [
				'output' => $output,
				'filter' => ['serviceid' => $serviceids],
				'preservekeys' => true
			]);
			$relation_map = $this->createRelationMap($status_rules, 'serviceid', 'service_status_ruleid');
			$status_rules = $this->unsetExtraFields($status_rules, ['service_status_ruleid', 'serviceid']);
			$result = $relation_map->mapMany($result, $status_rules, 'status_rules');

			if ($options['selectStatusRules'] === API_OUTPUT_COUNT) {
				foreach ($result as &$row) {
					$row['status_rules'] = (string) count($row['status_rules']);
				}
				unset($row);
			}
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
			$alarms = $this->unsetExtraFields($alarms, ['servicealarmid', 'serviceid']);
			$result = $relation_map->mapMany($result, $alarms, 'alarms');

			if ($options['selectAlarms'] === API_OUTPUT_COUNT) {
				foreach ($result as &$row) {
					$row['alarms'] = (string) count($row['alarms']);
				}
				unset($row);
			}
		}

		return $result;
	}

	/**
	 * @param array      $services
	 * @param array|null $db_services
	 *
	 * @throws APIException
	 */
	private function checkStatusPropagation(array $services, array $db_services = null): void {
		foreach ($services as $service) {
			$name = array_key_exists('name', $service)
				? $service['name']
				: $db_services[$service['serviceid']]['name'];

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
			$name = array_key_exists('name', $service)
				? $service['name']
				: $db_services[$service['serviceid']]['name'];

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
			$name = array_key_exists('name', $service)
				? $service['name']
				: $db_services[$service['serviceid']]['name'];

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
				$has_problem_tags = $db_services[$service['serviceid']]['problem_tags'] > 0;
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
						foreach ($del_times as $time) {
							$del_timeids[$time['timeid']] = true;
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

	/**
	 * @param array  $services
	 * @param string $method
	 */
	private function updateStatusRules(array $services, string $method): void {
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
		$upd_status_rules = [];

		if ($method === 'update') {
			$db_status_rules = DB::select('service_status_rule', [
				'output' => ['service_status_ruleid', 'serviceid', 'type', 'limit_value', 'limit_status', 'new_status'],
				'filter' => ['serviceid' => array_keys($serviceids)]
			]);

			foreach ($db_status_rules as $db_status_rule) {
				$del_status_rules[$db_status_rule['serviceid']][$db_status_rule['type']][$db_status_rule['limit_value']]
					[$db_status_rule['limit_status']] = [
						'service_status_ruleid' => $db_status_rule['service_status_ruleid'],
						'fields' => [
							'new_status' => $db_status_rule['new_status']
						]
					];
			}
		}

		foreach (array_keys($serviceids) as $serviceid) {
			foreach ($services[$serviceid]['status_rules'] as $status_rule) {
				if (array_key_exists($serviceid, $del_status_rules)
						&& array_key_exists($status_rule['type'], $del_status_rules[$serviceid])
						&& array_key_exists($status_rule['limit_value'],
							$del_status_rules[$serviceid][$status_rule['type']]
						)
						&& array_key_exists($status_rule['limit_status'],
							$del_status_rules[$serviceid][$status_rule['type']][$status_rule['limit_value']]
						)) {
					$db_status_rule = $del_status_rules[$serviceid][$status_rule['type']][$status_rule['limit_value']]
						[$status_rule['limit_status']];

					$upd_status_rule = DB::getUpdatedValues('service_status_rule', $status_rule,
						$db_status_rule['fields']
					);

					if ($upd_status_rule) {
						$upd_status_rules[] = [
							'values' => $upd_status_rule,
							'where' => ['service_status_ruleid' => $db_status_rule['service_status_ruleid']]
						];
					}

					unset($del_status_rules[$serviceid][$status_rule['type']][$status_rule['limit_value']]
						[$status_rule['limit_status']]
					);
				}
				else {
					$ins_status_rules[] = ['serviceid' => $serviceid] + $status_rule;
				}
			}
		}

		if ($del_status_rules) {
			$del_service_status_ruleids = [];

			foreach ($del_status_rules as $del_status_rules) {
				foreach ($del_status_rules as $del_status_rules) {
					foreach ($del_status_rules as $del_status_rules) {
						foreach ($del_status_rules as $status_rule) {
							$del_service_status_ruleids[$status_rule['service_status_ruleid']] = true;
						}
					}
				}
			}

			DB::delete('service_status_rule', ['service_status_ruleid' => array_keys($del_service_status_ruleids)]);
		}

		if ($ins_status_rules) {
			DB::insertBatch('service_status_rule', $ins_status_rules);
		}

		if ($upd_status_rules) {
			DB::update('service_status_rule', $upd_status_rules);
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
				$rs[$service['serviceid']] = [
					'status' => $service['algorithm'] != ZBX_SERVICE_STATUS_CALC_SET_OK ? $service['status'] : null,
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

				// add problem events
				$deep_problem_serviceids = $problemServiceIds;
				$deep_services = $services;

				while ($problemServiceIds) {
					$child_services = $this->get([
						'output' => ['serviceid', 'name', 'status', 'algorithm'],
						'selectParents' => ['serviceid'],
						'parentids' => $problemServiceIds,
						'preservekeys' => true
					]);

					$child_services = array_filter($child_services, function (array $service): bool {
						return $service['algorithm'] != ZBX_SERVICE_STATUS_CALC_SET_OK && $service['status'] > 0;
					});

					$deep_services += $child_services;

					$problemServiceIds = array_keys($child_services);
					$deep_problem_serviceids = array_merge($deep_problem_serviceids, $problemServiceIds);
				}

				if ($deep_problem_serviceids) {
					$deep_problem_events = [];

					$query = DBSelect(
						'SELECT sp.eventid, sp.serviceid, sp.severity'.
						' FROM service_problem sp'.
						' WHERE '.dbConditionId('sp.serviceid', $deep_problem_serviceids)
					);

					while ($row = DBfetch($query)) {
						$deep_problem_events[$row['serviceid']][$row['eventid']] = ['eventid' => $row['eventid']];
					}

					$rs = $this->escalateProblems($deep_services, $deep_problem_events, $rs);
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
	 * Escalate problem events from the child services to their parents and adds them to $slaData.
	 * The escalation will stop if a service has status calculation disabled or is in OK state.
	 *
	 * @param array $services
	 * @param array $problem_events  An array of service problems.
	 * @param array $slaData
	 *
	 * @return array
	 */
	protected function escalateProblems(array $services, array $problem_events, array $slaData) {
		$parentProblems = [];
		foreach ($problem_events as $serviceId => $service_problem_events) {
			$service = $services[$serviceId];

			// add the problem events of the current service to the data
			if (array_key_exists($serviceId, $slaData)) {
				$slaData[$serviceId]['problems'] = zbx_array_merge($slaData[$serviceId]['problems'],
					$service_problem_events
				);
			}

			// add the same problem events to the parent services
			foreach ($service['parents'] as $parent) {
				$parentServiceId = $parent['serviceid'];

				if (isset($services[$parentServiceId])) {
					$parentService = $services[$parentServiceId];

					// escalate only if status calculation is enabled for the parent service and it's in problem state
					if ($parentService['algorithm'] != ZBX_SERVICE_STATUS_CALC_SET_OK && $parentService['status'] > 0) {
						if (!isset($parentProblems[$parentServiceId])) {
							$parentProblems[$parentServiceId] = [];
						}
						$parentProblems[$parentServiceId] = zbx_array_merge($parentProblems[$parentServiceId],
							$service_problem_events
						);
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
}
