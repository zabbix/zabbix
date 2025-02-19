<?php
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
 * Class containing methods for operations with http tests.
 */
class CHttpTest extends CApiService {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_ZABBIX_USER],
		'create' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN],
		'update' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN],
		'delete' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN]
	];

	protected $tableName = 'httptest';
	protected $tableAlias = 'ht';
	protected $sortColumns = ['httptestid', 'name'];

	/**
	 * Get data about web scenarios.
	 *
	 * @param array $options
	 *
	 * @return array
	 */
	public function get($options = []) {
		$result = [];

		$sqlParts = [
			'select'	=> ['httptests' => 'ht.httptestid'],
			'from'		=> ['httptest' => 'httptest ht'],
			'where'		=> [],
			'group'		=> [],
			'order'		=> [],
			'limit'		=> null
		];

		$defOptions = [
			'httptestids'    => null,
			'hostids'        => null,
			'groupids'       => null,
			'templateids'    => null,
			'editable'       => false,
			'inherited'      => null,
			'templated'      => null,
			'monitored'      => null,
			'nopermissions'  => null,
			'evaltype'		=> TAG_EVAL_TYPE_AND_OR,
			'tags'			=> null,
			// filter
			'filter'         => null,
			'search'         => null,
			'searchByAny'    => null,
			'startSearch'    => false,
			'excludeSearch'  => false,
			// output
			'output'         => API_OUTPUT_EXTEND,
			'expandName'     => null,
			'expandStepName' => null,
			'selectHosts'    => null,
			'selectSteps'    => null,
			'selectTags'	 => null,
			'countOutput'    => false,
			'groupCount'     => false,
			'preservekeys'   => false,
			'sortfield'      => '',
			'sortorder'      => '',
			'limit'          => null
		];
		$options = zbx_array_merge($defOptions, $options);

		// editable + PERMISSION CHECK
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN && !$options['nopermissions']) {
			if (self::$userData['ugsetid'] == 0) {
				return $options['countOutput'] ? '0' : [];
			}

			$sqlParts['from'][] = 'host_hgset hh';
			$sqlParts['from'][] = 'permission p';
			$sqlParts['where'][] = 'ht.hostid=hh.hostid';
			$sqlParts['where'][] = 'hh.hgsetid=p.hgsetid';
			$sqlParts['where'][] = 'p.ugsetid='.self::$userData['ugsetid'];

			if ($options['editable']) {
				$sqlParts['where'][] = 'p.permission='.PERM_READ_WRITE;
			}
		}

		// httptestids
		if (!is_null($options['httptestids'])) {
			zbx_value2array($options['httptestids']);

			$sqlParts['where']['httptestid'] = dbConditionInt('ht.httptestid', $options['httptestids']);
		}

		// templateids
		if (!is_null($options['templateids'])) {
			zbx_value2array($options['templateids']);

			if (!is_null($options['hostids'])) {
				zbx_value2array($options['hostids']);
				$options['hostids'] = array_merge($options['hostids'], $options['templateids']);
			}
			else {
				$options['hostids'] = $options['templateids'];
			}
		}
		// hostids
		if (!is_null($options['hostids'])) {
			zbx_value2array($options['hostids']);

			$sqlParts['where']['hostid'] = dbConditionInt('ht.hostid', $options['hostids']);

			if ($options['groupCount']) {
				$sqlParts['group']['hostid'] = 'ht.hostid';
			}
		}

		// tags
		if ($options['tags'] !== null && $options['tags']) {
			$sqlParts['where'][] = CApiTagHelper::addWhereCondition($options['tags'], $options['evaltype'], 'ht',
				'httptest_tag', 'httptestid'
			);
		}

		// groupids
		if (!is_null($options['groupids'])) {
			zbx_value2array($options['groupids']);

			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['where'][] = dbConditionInt('hg.groupid', $options['groupids']);
			$sqlParts['where'][] = 'hg.hostid=ht.hostid';

			if ($options['groupCount']) {
				$sqlParts['group']['hg'] = 'hg.groupid';
			}
		}

		// inherited
		if (isset($options['inherited'])) {
			$sqlParts['where'][] = $options['inherited'] ? 'ht.templateid IS NOT NULL' : 'ht.templateid IS NULL';
		}

		// templated
		if (isset($options['templated'])) {
			$sqlParts['from']['hosts'] = 'hosts h';
			$sqlParts['where']['ha'] = 'h.hostid=ht.hostid';
			if ($options['templated']) {
				$sqlParts['where'][] = 'h.status='.HOST_STATUS_TEMPLATE;
			}
			else {
				$sqlParts['where'][] = 'h.status<>'.HOST_STATUS_TEMPLATE;
			}
		}

		// monitored
		if (!is_null($options['monitored'])) {
			$sqlParts['from']['hosts'] = 'hosts h';
			$sqlParts['where']['hht'] = 'h.hostid=ht.hostid';

			if ($options['monitored']) {
				$sqlParts['where'][] = 'h.status='.HOST_STATUS_MONITORED;
				$sqlParts['where'][] = 'ht.status='.ITEM_STATUS_ACTIVE;
			}
			else {
				$sqlParts['where'][] = '(h.status<>'.HOST_STATUS_MONITORED.' OR ht.status<>'.ITEM_STATUS_ACTIVE.')';
			}
		}

		// search
		if (is_array($options['search'])) {
			zbx_db_search('httptest ht', $options, $sqlParts);
		}

		// filter
		if (is_array($options['filter'])) {
			if (array_key_exists('delay', $options['filter']) && $options['filter']['delay'] !== null) {
				$options['filter']['delay'] = getTimeUnitFilters($options['filter']['delay']);
			}

			$this->dbFilter('httptest ht', $options, $sqlParts);
		}

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$res = DBselect(self::createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
		while ($httpTest = DBfetch($res)) {
			if ($options['countOutput']) {
				if ($options['groupCount']) {
					$result[] = $httpTest;
				}
				else {
					$result = $httpTest['rowscount'];
				}
			}
			else {
				$result[$httpTest['httptestid']] = $httpTest;
			}
		}

		if ($options['countOutput']) {
			return $result;
		}

		if ($result) {
			$result = $this->addRelatedObjects($options, $result);

			// expandName
			$nameRequested = (is_array($options['output']) && in_array('name', $options['output']))
				|| $options['output'] == API_OUTPUT_EXTEND;
			$expandName = $options['expandName'] !== null && $nameRequested;

			// expandStepName
			$stepNameRequested = $options['selectSteps'] == API_OUTPUT_EXTEND
				|| (is_array($options['selectSteps']) && in_array('name', $options['selectSteps']));
			$expandStepName = $options['expandStepName'] !== null && $stepNameRequested;

			if ($expandName || $expandStepName) {
				$result = resolveHttpTestMacros($result, $expandName, $expandStepName);
			}

			$result = $this->unsetExtraFields($result, ['hostid'], $options['output']);
		}

		// removing keys (hash -> array)
		if (!$options['preservekeys']) {
			$result = zbx_cleanHashes($result);
		}

		return $result;
	}

	/**
	 * Create web scenario.
	 *
	 * @param $httptests
	 *
	 * @return array
	 *
	 * @throws APIException
	 */
	public function create($httptests) {
		$this->validateCreate($httptests);

		$httptests = Manager::HttpTest()->persist($httptests);

		$this->addAuditBulk(CAudit::ACTION_ADD, CAudit::RESOURCE_SCENARIO, $httptests);

		return ['httptestids' => zbx_objectValues($httptests, 'httptestid')];
	}

	/**
	 * @param array $httptests
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateCreate(array &$httptests): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['hostid', 'name']], 'fields' => [
			'hostid' =>				['type' => API_ID, 'flags' => API_REQUIRED],
			'uuid' =>				['type' => API_ANY],
			'name' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('httptest', 'name')],
			'delay' =>				['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO, 'in' => '1:'.SEC_PER_DAY],
			'retries' =>			['type' => API_INT32, 'in' => '1:10'],
			'agent' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('httptest', 'agent')],
			'http_proxy' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('httptest', 'http_proxy')],
			'variables' =>			['type' => API_OBJECTS, 'uniq' => [['name']], 'fields' => [
				'name' =>				['type' => API_VARIABLE_NAME, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('httptest_field', 'name')],
				'value' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('httptest_field', 'value')]
			]],
			'headers' =>			['type' => API_OBJECTS, 'fields' => [
				'name' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('httptest_field', 'name')],
				'value' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('httptest_field', 'value')]
			]],
			'status' =>				['type' => API_INT32, 'in' => implode(',', [HTTPTEST_STATUS_ACTIVE, HTTPTEST_STATUS_DISABLED])],
			'authentication' =>		['type' => API_INT32, 'in' => implode(',', [ZBX_HTTP_AUTH_NONE, ZBX_HTTP_AUTH_BASIC, ZBX_HTTP_AUTH_NTLM, ZBX_HTTP_AUTH_KERBEROS, ZBX_HTTP_AUTH_DIGEST])],
			'http_user' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('httptest', 'http_user')],
			'http_password' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('httptest', 'http_password')],
			'verify_peer' =>		['type' => API_INT32, 'in' => implode(',', [ZBX_HTTP_VERIFY_PEER_OFF, ZBX_HTTP_VERIFY_PEER_ON])],
			'verify_host' =>		['type' => API_INT32, 'in' => implode(',', [ZBX_HTTP_VERIFY_HOST_OFF, ZBX_HTTP_VERIFY_HOST_ON])],
			'ssl_cert_file' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('httptest', 'ssl_cert_file')],
			'ssl_key_file' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('httptest', 'ssl_key_file')],
			'ssl_key_password' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('httptest', 'ssl_key_password')],
			'steps' =>				['type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'uniq' => [['name'], ['no']], 'fields' => [
				'name' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('httpstep', 'name')],
				'no' =>					['type' => API_INT32, 'flags' => API_REQUIRED],
				'url' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('httpstep', 'url')],
				'query_fields' =>		['type' => API_OBJECTS, 'fields' => [
					'name' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('httpstep_field', 'name')],
					'value' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('httpstep_field', 'value')]
				]],
				'posts' =>				['type' => API_HTTP_POST, 'length' => DB::getFieldLength('httpstep', 'posts'), 'name-length' => DB::getFieldLength('httpstep_field', 'name'), 'value-length' => DB::getFieldLength('httpstep_field', 'value')],
				'variables' =>			['type' => API_OBJECTS, 'uniq' => [['name']], 'fields' => [
					'name' =>				['type' => API_VARIABLE_NAME, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('httpstep_field', 'name')],
					'value' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('httpstep_field', 'value')]
				]],
				'headers' =>			['type' => API_OBJECTS, 'fields' => [
					'name' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('httpstep_field', 'name')],
					'value' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('httpstep_field', 'value')]
				]],
				'follow_redirects' =>	['type' => API_INT32, 'in' => implode(',', [HTTPTEST_STEP_FOLLOW_REDIRECTS_OFF, HTTPTEST_STEP_FOLLOW_REDIRECTS_ON])],
				'retrieve_mode' =>		['type' => API_INT32, 'in' => implode(',', [HTTPTEST_STEP_RETRIEVE_MODE_CONTENT, HTTPTEST_STEP_RETRIEVE_MODE_HEADERS, HTTPTEST_STEP_RETRIEVE_MODE_BOTH])],
				'timeout' =>			['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO, 'in' => '1:'.SEC_PER_HOUR],
				'required' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('httpstep', 'required')],
				'status_codes' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('httpstep', 'status_codes')]
			]],
			'tags' =>				['type' => API_OBJECTS, 'uniq' => [['tag', 'value']], 'fields' => [
				'tag' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('httptest_tag', 'tag')],
				'value' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('httptest_tag', 'value'), 'default' => DB::getDefault('httptest_tag', 'value')]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $httptests, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$names_by_hostid = [];

		foreach ($httptests as $httptest) {
			$names_by_hostid[$httptest['hostid']][] = $httptest['name'];
		}

		$this->checkHostsAndTemplates($httptests, $db_hosts, $db_templates);
		self::addHostStatus($httptests, $db_hosts, $db_templates);

		self::validateUuid($httptests, $db_hosts + $db_templates);

		self::addUuid($httptests, $db_templates);

		self::checkUuidDuplicates($httptests);
		$this->checkDuplicates($names_by_hostid);
		$this->validateAuthParameters($httptests, __FUNCTION__);
		$this->validateSslParameters($httptests, __FUNCTION__);
		$this->validateSteps($httptests, __FUNCTION__);
	}

	/**
	 * @param array $httptests
	 * @param array $db_hosts
	 *
	 * @throws APIException
	 */
	private static function validateUuid(array $httptests, array $db_hosts): void {
		foreach ($httptests as &$httptest) {
			$httptest['host_status'] = array_key_exists('status', $db_hosts[$httptest['hostid']])
				? $db_hosts[$httptest['hostid']]['status']
				: HOST_STATUS_TEMPLATE;
		}
		unset($httptest);

		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_ALLOW_UNEXPECTED, 'uniq' => [['uuid']], 'fields' => [
			'host_status' =>	['type' => API_ANY],
			'uuid' =>			['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'host_status', 'in' => HOST_STATUS_TEMPLATE], 'type' => API_UUID],
									['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('httptest', 'uuid'), 'unset' => true]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $httptests, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}
	}

	/**
	 * Add the UUID to those of the given web scenarios that belong to a template and don't have the 'uuid' parameter
	 * set.
	 *
	 * @param array $httptests
	 * @param array $db_templates
	 */
	private static function addUuid(array &$httptests, array $db_templates): void {
		foreach ($httptests as &$httptest) {
			if (array_key_exists($httptest['hostid'], $db_templates) && !array_key_exists('uuid', $httptest)) {
				$httptest['uuid'] = generateUuidV4();
			}
		}
		unset($httptest);
	}

	/**
	 * Verify web scenario UUIDs are not repeated.
	 *
	 * @param array      $httptests
	 * @param array|null $db_httptests
	 *
	 * @throws APIException
	 */
	private static function checkUuidDuplicates(array $httptests, ?array $db_httptests = null): void {
		$httptest_indexes = [];

		foreach ($httptests as $i => $httptest) {
			if (!array_key_exists('uuid', $httptest) || $httptest['uuid'] === '') {
				continue;
			}

			if ($db_httptests === null || $httptest['uuid'] !== $db_httptests[$httptest['httptestid']]['uuid']) {
				$httptest_indexes[$httptest['uuid']] = $i;
			}
		}

		if (!$httptest_indexes) {
			return;
		}

		$duplicates = DB::select('httptest', [
			'output' => ['uuid'],
			'filter' => [
				'uuid' => array_keys($httptest_indexes)
			],
			'limit' => 1
		]);

		if ($duplicates) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Invalid parameter "%1$s": %2$s.', '/'.($httptest_indexes[$duplicates[0]['uuid']] + 1),
					_('web scenario with the same UUID already exists')
				)
			);
		}
	}

	/**
	 * @param array $httptests
	 *
	 * @return array
	 */
	public function update(array $httptests) {
		$this->validateUpdate($httptests, $db_httptests);

		Manager::HttpTest()->persist($httptests);

		foreach ($db_httptests as &$db_httptest) {
			unset($db_httptest['headers'], $db_httptest['variables'], $db_httptest['steps']);
		}
		unset($db_httptest);

		$this->addAuditBulk(CAudit::ACTION_UPDATE, CAudit::RESOURCE_SCENARIO, $httptests, $db_httptests);

		return ['httptestids' => zbx_objectValues($httptests, 'httptestid')];
	}

	/**
	 * @param array $httptests
	 * @param array $db_httptests
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateUpdate(array &$httptests, ?array &$db_httptests = null) {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['httptestid']], 'fields' => [
			'uuid' => 				['type' => API_ANY],
			'httptestid' =>			['type' => API_ID, 'flags' => API_REQUIRED],
			'name' =>				['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('httptest', 'name')],
			'delay' =>				['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO, 'in' => '1:'.SEC_PER_DAY],
			'retries' =>			['type' => API_INT32, 'in' => '1:10'],
			'agent' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('httptest', 'agent')],
			'http_proxy' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('httptest', 'http_proxy')],
			'variables' =>			['type' => API_OBJECTS, 'uniq' => [['name']], 'fields' => [
				'name' =>				['type' => API_VARIABLE_NAME, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('httptest_field', 'name')],
				'value' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('httptest_field', 'value')]
			]],
			'headers' =>			['type' => API_OBJECTS, 'fields' => [
				'name' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('httptest_field', 'name')],
				'value' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('httptest_field', 'value')]
			]],
			'status' =>				['type' => API_INT32, 'in' => implode(',', [HTTPTEST_STATUS_ACTIVE, HTTPTEST_STATUS_DISABLED])],
			'authentication' =>		['type' => API_INT32, 'in' => implode(',', [ZBX_HTTP_AUTH_NONE, ZBX_HTTP_AUTH_BASIC, ZBX_HTTP_AUTH_NTLM, ZBX_HTTP_AUTH_KERBEROS, ZBX_HTTP_AUTH_DIGEST])],
			'http_user' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('httptest', 'http_user')],
			'http_password' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('httptest', 'http_password')],
			'verify_peer' =>		['type' => API_INT32, 'in' => implode(',', [ZBX_HTTP_VERIFY_PEER_OFF, ZBX_HTTP_VERIFY_PEER_ON])],
			'verify_host' =>		['type' => API_INT32, 'in' => implode(',', [ZBX_HTTP_VERIFY_HOST_OFF, ZBX_HTTP_VERIFY_HOST_ON])],
			'ssl_cert_file' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('httptest', 'ssl_cert_file')],
			'ssl_key_file' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('httptest', 'ssl_key_file')],
			'ssl_key_password' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('httptest', 'ssl_key_password')],
			'steps' =>				['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY, 'uniq' => [['httpstepid'], ['name'], ['no']], 'fields' => [
				'httpstepid' =>			['type' => API_ID],
				'name' =>				['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('httpstep', 'name')],
				'no' =>					['type' => API_INT32],
				'url' =>				['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('httpstep', 'url')],
				'query_fields' =>		['type' => API_OBJECTS, 'fields' => [
					'name' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('httpstep_field', 'name')],
					'value' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('httpstep_field', 'value')]
				]],
				'posts' =>				['type' => API_HTTP_POST, 'length' => DB::getFieldLength('httpstep', 'posts'), 'name-length' => DB::getFieldLength('httpstep_field', 'name'), 'value-length' => DB::getFieldLength('httpstep_field', 'value')],
				'variables' =>			['type' => API_OBJECTS, 'uniq' => [['name']], 'fields' => [
					'name' =>				['type' => API_VARIABLE_NAME, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('httpstep_field', 'name')],
					'value' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('httpstep_field', 'value')]
				]],
				'headers' =>			['type' => API_OBJECTS, 'fields' => [
					'name' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('httpstep_field', 'name')],
					'value' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('httpstep_field', 'value')]
				]],
				'follow_redirects' =>	['type' => API_INT32, 'in' => implode(',', [HTTPTEST_STEP_FOLLOW_REDIRECTS_OFF, HTTPTEST_STEP_FOLLOW_REDIRECTS_ON])],
				'retrieve_mode' =>		['type' => API_INT32, 'in' => implode(',', [HTTPTEST_STEP_RETRIEVE_MODE_CONTENT, HTTPTEST_STEP_RETRIEVE_MODE_HEADERS, HTTPTEST_STEP_RETRIEVE_MODE_BOTH])],
				'timeout' =>			['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO, 'in' => '1:'.SEC_PER_HOUR],
				'required' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('httpstep', 'required')],
				'status_codes' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('httpstep', 'status_codes')]
			]],
			'tags' =>				['type' => API_OBJECTS, 'uniq' => [['tag', 'value']], 'fields' => [
				'tag' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('httptest_tag', 'tag')],
				'value' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('httptest_tag', 'value'), 'default' => DB::getDefault('httptest_tag', 'value')]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $httptests, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		// permissions
		$db_httptests = $this->get([
			'output' => ['uuid', 'httptestid', 'hostid', 'name', 'delay', 'retries', 'agent', 'http_proxy',
				'status', 'authentication', 'http_user', 'http_password', 'verify_peer', 'verify_host',
				'ssl_cert_file', 'ssl_key_file', 'ssl_key_password', 'templateid'
			],
			'selectSteps' => ['httpstepid', 'name', 'no', 'url', 'timeout', 'posts', 'required',
				'status_codes', 'follow_redirects', 'retrieve_mode', 'post_type'
			],
			'selectHosts' => ['status'],
			'httptestids' => zbx_objectValues($httptests, 'httptestid'),
			'editable' => true,
			'preservekeys' => true
		]);

		$db_hosts = [];

		foreach ($db_httptests as &$db_httptest) {
			$db_httptest['headers'] = [];
			$db_httptest['variables'] = [];
			$db_httptest['steps'] = zbx_toHash($db_httptest['steps'], 'httpstepid');

			$db_hosts[$db_httptest['hostid']] = $db_httptest['hosts'][0]['status'] == HOST_STATUS_TEMPLATE
				? []
				: $db_httptest['hosts'][0];
		}
		unset($db_httptest);

		$names_by_hostid = [];

		foreach ($httptests as $httptest) {
			if (!array_key_exists($httptest['httptestid'], $db_httptests)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}

			$db_httptest = $db_httptests[$httptest['httptestid']];

			if (array_key_exists('name', $httptest)) {
				if ($db_httptest['templateid'] != 0) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s(
						'Cannot update a templated web scenario "%1$s": %2$s.', $httptest['name'],
						_s('unexpected parameter "%1$s"', 'name')
					));
				}

				if ($httptest['name'] !== $db_httptest['name']) {
					$names_by_hostid[$db_httptest['hostid']][] = $httptest['name'];
				}
			}
		}

		$httptests = $this->extendObjectsByKey($httptests, $db_httptests, 'httptestid', ['hostid', 'name']);

		// uniqueness
		foreach ($httptests as &$httptest) {
			$db_httptest = $db_httptests[$httptest['httptestid']];

			if (array_key_exists('steps', $httptest)) {
				// unexpected parameters for templated web scenario steps
				if ($db_httptest['templateid'] != 0) {
					foreach ($httptest['steps'] as $httpstep) {
						foreach (['name', 'no'] as $field_name) {
							if (array_key_exists($field_name, $httpstep)) {
								self::exception(ZBX_API_ERROR_PARAMETERS, _s(
									'Cannot update step for a templated web scenario "%1$s": %2$s.', $httptest['name'],
									_s('unexpected parameter "%1$s"', $field_name)
								));
							}
						}
					}
				}

				$httptest['steps'] =
					$this->extendObjectsByKey($httptest['steps'], $db_httptest['steps'], 'httpstepid', ['name']);
			}
		}
		unset($httptest);

		$api_input_rules = ['type' => API_OBJECTS, 'uniq' => [['hostid', 'name']], 'fields' => [
			'hostid' =>	['type' => API_ID],
			'name' =>	['type' => API_STRING_UTF8],
			'steps' =>	['type' => API_OBJECTS, 'uniq' => [['name']], 'fields' => [
				'name' =>	['type' => API_STRING_UTF8]
			]]
		]];

		if (!CApiInputValidator::validateUniqueness($api_input_rules, $httptests, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		self::validateUuid($httptests, $db_hosts);

		self::checkUuidDuplicates($httptests, $db_httptests);

		// validation
		if ($names_by_hostid) {
			$this->checkDuplicates($names_by_hostid);
		}
		$this->validateAuthParameters($httptests, __FUNCTION__, $db_httptests);
		$this->validateSslParameters($httptests, __FUNCTION__, $db_httptests);
		$this->validateSteps($httptests, __FUNCTION__, $db_httptests);

		return $httptests;
	}

	/**
	 * @param array $httptestids
	 *
	 * @return array
	 */
	public function delete(array $httptestids) {
		$this->validateDelete($httptestids, $db_httptests);

		self::deleteForce($db_httptests);

		return ['httptestids' => $httptestids];
	}

	/**
	 * @param array      $httptestids
	 * @param array|null $db_httptests
	 */
	private function validateDelete(array $httptestids, ?array &$db_httptests): void {
		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];

		if (!CApiInputValidator::validate($api_input_rules, $httptestids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_httptests = $this->get([
			'output' => ['httptestid', 'name', 'templateid'],
			'httptestids' => $httptestids,
			'editable' => true,
			'preservekeys' => true
		]);

		if (count($db_httptests) != count($httptestids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		foreach ($httptestids as $httptestid) {
			if ($db_httptests[$httptestid]['templateid'] != 0) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Cannot delete templated web scenario "%1$s".', $db_httptests[$httptestid]['name'])
				);
			}
		}
	}

	/**
	 * @param array $db_httptests
	 */
	public static function deleteForce(array $db_httptests): void {
		self::addInheritedHttptests($db_httptests);

		$del_httptestids = array_keys($db_httptests);

		self::deleteAffectedItems($del_httptestids);
		self::deleteAffectedSteps($del_httptestids);

		DB::delete('httptest_field', ['httptestid' => $del_httptestids]);
		DB::delete('httptest_tag', ['httptestid' => $del_httptestids]);
		DB::update('httptest', [
			'values' => ['templateid' => 0],
			'where' => ['httptestid' => $del_httptestids]
		]);
		DB::delete('httptest', ['httptestid' => $del_httptestids]);

		self::addAuditLog(CAudit::ACTION_DELETE, CAudit::RESOURCE_SCENARIO, $db_httptests);
	}

	/**
	 * Add the inherited web scenarios of the given web scenarios to the given web scenario array.
	 *
	 * @param array $db_httptests
	 */
	private static function addInheritedHttptests(array &$db_httptests): void {
		$templateids = array_keys($db_httptests);

		do {
			$options = [
				'output' => ['httptestid', 'name'],
				'filter' => ['templateid' => $templateids]
			];
			$result = DBselect(DB::makeSql('httptest', $options));

			$templateids = [];

			while ($row = DBfetch($result)) {
				if (!array_key_exists($row['httptestid'], $db_httptests)) {
					$templateids[] = $row['httptestid'];

					$db_httptests[$row['httptestid']] = $row;
				}
			}
		} while ($templateids);
	}

	/**
	 * Delete items, which would remain without web scenarios after the given web scenarios deletion.
	 *
	 * @param array $del_httptestids
	 */
	private static function deleteAffectedItems(array $del_httptestids): void {
		$db_items = DBfetchArrayAssoc(DBselect(
			'SELECT hti.itemid,i.name'.
			' FROM httptestitem hti,items i'.
			' WHERE hti.itemid=i.itemid'.
				' AND '.dbConditionId('hti.httptestid', $del_httptestids)
		), 'itemid');

		CItem::addInheritedItems($db_items);
		DB::delete('httptestitem', ['itemid' => array_keys($db_items)]);
		CItem::deleteForce($db_items);
	}

	/**
	 * Delete steps of the given web scenarios.
	 *
	 * @param array $del_httptestids
	 */
	private static function deleteAffectedSteps(array $del_httptestids): void {
		$del_stepids = array_column(DB::select('httpstep', [
			'output' => ['httpstepid'],
			'filter' => ['httptestid' => $del_httptestids]
		]), 'httpstepid');

		self::deleteAffectedStepItems($del_stepids);

		DB::delete('httpstep_field', ['httpstepid' => $del_stepids]);
		DB::delete('httpstep', ['httpstepid' => $del_stepids]);
	}

	/**
	 * Delete items of the given web scenario steps.
	 *
	 * @param array $del_stepids
	 */
	private static function deleteAffectedStepItems(array $del_stepids): void {
		$db_items = DBfetchArrayAssoc(DBselect(
			'SELECT hsi.itemid,i.name'.
			' FROM httpstepitem hsi,items i'.
			' WHERE hsi.itemid=i.itemid'.
				' AND '.dbConditionId('hsi.httpstepid', $del_stepids)
		), 'itemid');

		CItem::addInheritedItems($db_items);
		DB::delete('httpstepitem', ['itemid' => array_keys($db_items)]);
		CItem::deleteForce($db_items);
	}

	/**
	 * Check that host IDs of given web scenarios are valid.
	 * If host IDs are valid, $db_hosts and $db_templates parameters will be filled with found hosts and templates.
	 *
	 * @param array      $httptests
	 * @param array|null $db_hosts
	 * @param array|null $db_templates
	 *
	 * @throws APIException
	 */
	protected static function checkHostsAndTemplates(array $httptests, ?array &$db_hosts = null,
			?array &$db_templates = null): void {
		$hostids = array_unique(array_column($httptests, 'hostid'));

		$db_templates = API::Template()->get([
			'output' => [],
			'templateids' => $hostids,
			'editable' => true,
			'preservekeys' => true
		]);

		$_hostids = array_diff($hostids, array_keys($db_templates));

		$db_hosts = $_hostids
			? API::Host()->get([
				'output' => ['status'],
				'hostids' => $_hostids,
				'editable' => true,
				'preservekeys' => true
			])
			: [];

		if (count($db_templates) + count($db_hosts) != count($hostids)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
		}
	}

	/**
	 * Add host_status property to given web scenarios in accordance of given hosts and templates statuses.
	 *
	 * @param array $httptests
	 * @param array $db_hosts
	 * @param array $db_templates
	 */
	protected static function addHostStatus(array &$httptests, array $db_hosts, array $db_templates): void {
		foreach ($httptests as &$httptest) {
			if (array_key_exists($httptest['hostid'], $db_templates)) {
				$httptest['host_status'] = HOST_STATUS_TEMPLATE;
			}
			else {
				$httptest['host_status'] = $db_hosts[$httptest['hostid']]['status'];
			}
		}
		unset($httptest);
	}

	/**
	 * Check for duplicated web scenarios.
	 *
	 * @param array $names_by_hostid
	 *
	 * @throws APIException  if web scenario already exists.
	 */
	private function checkDuplicates(array $names_by_hostid) {
		$sql_where = [];
		foreach ($names_by_hostid as $hostid => $names) {
			$sql_where[] = '(ht.hostid='.$hostid.' AND '.dbConditionString('ht.name', $names).')';
		}

		$db_httptests = DBfetchArray(
			DBselect('SELECT ht.name FROM httptest ht WHERE '.implode(' OR ', $sql_where), 1)
		);

		if ($db_httptests) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Web scenario "%1$s" already exists.', $db_httptests[0]['name'])
			);
		}
	}

	/**
	 * @param array  $httptests
	 * @param string $method
	 * @param array  $db_httptests
	 *
	 * @throws APIException
	 */
	protected function validateSteps(array &$httptests, $method, ?array $db_httptests = null) {
		if ($method === 'validateUpdate') {
			foreach ($httptests as $httptest) {
				if (!array_key_exists('steps', $httptest)) {
					continue;
				}

				$db_httptest = $db_httptests[$httptest['httptestid']];

				if ($db_httptest['templateid'] != 0 && count($httptest['steps']) != count($db_httptest['steps'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect templated web scenario step count.'));
				}

				foreach ($httptest['steps'] as $step) {
					if (!array_key_exists('httpstepid', $step)) {
						if ($db_httptest['templateid'] == 0) {
							continue;
						}
						else {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s(
								'Cannot update step for a templated web scenario "%1$s": %2$s.', $httptest['name'],
								_s('the parameter "%1$s" is missing', 'httpstepid')
							));
						}
					}

					if (!array_key_exists($step['httpstepid'], $db_httptest['steps'])) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_('No permissions to referred object or it does not exist!')
						);
					}
				}
			}
		}

		$this->checkStatusCodes($httptests);
		$this->validateRetrieveMode($httptests, $method, $db_httptests);
	}

	/**
	 * Validate http response code range.
	 * Range can be empty string or list of comma separated numeric strings or user macros.
	 *
	 * Examples: '100-199, 301, 404, 500-550, {$MACRO}-200, {$MACRO}-{$MACRO}'
	 *
	 * @param array $httptests
	 *
	 * @throws APIException if the status code range is invalid.
	 */
	private function checkStatusCodes(array $httptests) {
		$ranges_parser = new CRangesParser(['usermacros' => true]);

		foreach ($httptests as $httptest) {
			if (!array_key_exists('steps', $httptest)) {
				continue;
			}

			foreach ($httptest['steps'] as $httpstep) {
				if (!array_key_exists('status_codes', $httpstep) || $httpstep['status_codes'] === '') {
					continue;
				}

				if ($ranges_parser->parse($httpstep['status_codes']) != CParser::PARSE_SUCCESS) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Invalid response code "%1$s".', $httpstep['status_codes'])
					);
				}
			}
		}
	}

	protected function applyQueryOutputOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		$sqlParts = parent::applyQueryOutputOptions($tableName, $tableAlias, $options, $sqlParts);

		if (!$options['countOutput']) {
			// make sure we request the hostid to be able to expand macros
			if ($options['expandName'] !== null || $options['expandStepName'] !== null || $options['selectHosts'] !== null) {
				$sqlParts = $this->addQuerySelect($this->fieldId('hostid'), $sqlParts);
			}
		}

		return $sqlParts;
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$httpTestIds = array_keys($result);

		// adding headers and variables
		$fields = [
			ZBX_HTTPFIELD_HEADER => 'headers',
			ZBX_HTTPFIELD_VARIABLE => 'variables'
		];
		foreach ($fields as $type => $field) {
			if (!$this->outputIsRequested($field, $options['output'])) {
				unset($fields[$type]);
			}
		}

		if ($fields) {
			$db_httpfields = DB::select('httptest_field', [
				'output' => ['httptestid', 'name', 'value', 'type'],
				'filter' => [
					'httptestid' => $httpTestIds,
					'type' => array_keys($fields)
				],
				'sortfield' => ['httptest_fieldid']
			]);

			foreach ($result as &$httptest) {
				foreach ($fields as $field) {
					$httptest[$field] = [];
				}
			}
			unset($httptest);

			foreach ($db_httpfields as $db_httpfield) {
				$result[$db_httpfield['httptestid']][$fields[$db_httpfield['type']]][] = [
					'name' => $db_httpfield['name'],
					'value' => $db_httpfield['value']
				];
			}
		}

		// adding hosts
		if ($options['selectHosts'] !== null && $options['selectHosts'] != API_OUTPUT_COUNT) {
			$relationMap = $this->createRelationMap($result, 'httptestid', 'hostid');
			$hosts = API::Host()->get([
				'output' => $options['selectHosts'],
				'hostid' => $relationMap->getRelatedIds(),
				'nopermissions' => true,
				'templated_hosts' => true,
				'preservekeys' => true
			]);
			$result = $relationMap->mapMany($result, $hosts, 'hosts');
		}

		// adding steps
		if ($options['selectSteps'] !== null) {
			if ($options['selectSteps'] != API_OUTPUT_COUNT) {
				$fields = [
					ZBX_HTTPFIELD_HEADER => 'headers',
					ZBX_HTTPFIELD_VARIABLE => 'variables',
					ZBX_HTTPFIELD_QUERY_FIELD => 'query_fields',
					ZBX_HTTPFIELD_POST_FIELD => 'posts'
				];
				foreach ($fields as $type => $field) {
					if (!$this->outputIsRequested($field, $options['selectSteps'])) {
						unset($fields[$type]);
					}
				}

				$db_httpsteps = API::getApiService()->select('httpstep', [
					'output' => $this->outputExtend($options['selectSteps'], ['httptestid', 'httpstepid', 'post_type']),
					'filter' => ['httptestid' => $httpTestIds],
					'preservekeys' => true
				]);
				$relationMap = $this->createRelationMap($db_httpsteps, 'httptestid', 'httpstepid');

				if ($fields) {
					foreach ($db_httpsteps as &$db_httpstep) {
						foreach ($fields as $type => $field) {
							if ($type != ZBX_HTTPFIELD_POST_FIELD || $db_httpstep['post_type'] == ZBX_POSTTYPE_FORM) {
								$db_httpstep[$field] = [];
							}
						}
					}
					unset($db_httpstep);

					$db_httpstep_fields = DB::select('httpstep_field', [
						'output' => ['httpstepid', 'name', 'value', 'type'],
						'filter' => [
							'httpstepid' => array_keys($db_httpsteps),
							'type' => array_keys($fields)
						],
						'sortfield' => ['httpstep_fieldid']
					]);

					foreach ($db_httpstep_fields as $db_httpstep_field) {
						$db_httpstep = &$db_httpsteps[$db_httpstep_field['httpstepid']];

						if ($db_httpstep_field['type'] != ZBX_HTTPFIELD_POST_FIELD
								|| $db_httpstep['post_type'] == ZBX_POSTTYPE_FORM) {
							$db_httpstep[$fields[$db_httpstep_field['type']]][] = [
								'name' => $db_httpstep_field['name'],
								'value' => $db_httpstep_field['value']
							];
						}
					}
					unset($db_httpstep);
				}

				$db_httpsteps = $this->unsetExtraFields($db_httpsteps, ['httptestid', 'httpstepid', 'post_type'],
					$options['selectSteps']
				);
				$result = $relationMap->mapMany($result, $db_httpsteps, 'steps');
			}
			else {
				$dbHttpSteps = DBselect(
					'SELECT hs.httptestid,COUNT(hs.httpstepid) AS stepscnt'.
						' FROM httpstep hs'.
						' WHERE '.dbConditionInt('hs.httptestid', $httpTestIds).
						' GROUP BY hs.httptestid'
				);
				while ($dbHttpStep = DBfetch($dbHttpSteps)) {
					$result[$dbHttpStep['httptestid']]['steps'] = $dbHttpStep['stepscnt'];
				}
			}
		}

		// Adding web scenario tags.
		if ($options['selectTags'] !== null) {
			$options['selectTags'] = ($options['selectTags'] !== API_OUTPUT_EXTEND)
				? (array) $options['selectTags']
				: ['tag', 'value'];

			$options['selectTags'] = array_intersect(['tag', 'value'], $options['selectTags']);
			$requested_output = array_flip($options['selectTags']);

			$db_tags = DBselect(
				'SELECT '.implode(',', array_merge($options['selectTags'], ['httptestid'])).
				' FROM httptest_tag'.
				' WHERE '.dbConditionInt('httptestid', $httpTestIds)
			);

			array_walk($result, function (&$http_test) {
				$http_test['tags'] = [];
			});

			while ($db_tag = DBfetch($db_tags)) {
				$result[$db_tag['httptestid']]['tags'][] = array_intersect_key($db_tag, $requested_output);
			}
		}

		return $result;
	}

	/**
	 * @param array  $httptests
	 * @param string $method
	 * @param array  $db_httptests
	 *
	 * @throws APIException  if auth parameters are invalid.
	 */
	private function validateAuthParameters(array &$httptests, $method, ?array $db_httptests = null) {
		foreach ($httptests as &$httptest) {
			if (array_key_exists('authentication', $httptest) || array_key_exists('http_user', $httptest)
					|| array_key_exists('http_password', $httptest)) {
				$httptest += [
					'authentication' => ($method === 'validateUpdate')
						? $db_httptests[$httptest['httptestid']]['authentication']
						: ZBX_HTTP_AUTH_NONE
				];

				if ($httptest['authentication'] == ZBX_HTTP_AUTH_NONE) {
					foreach (['http_user', 'http_password'] as $field_name) {
						$httptest += [$field_name => ''];

						if ($httptest[$field_name] !== '') {
							self::exception(ZBX_API_ERROR_PARAMETERS,
								_s('Incorrect value for field "%1$s": %2$s.', $field_name, _('should be empty'))
							);
						}
					}
				}
			}
		}
		unset($httptest);
	}

	/**
	 * @param array  $httptests
	 * @param string $method
	 * @param array  $db_httptests
	 *
	 * @throws APIException if SSL cert is present but SSL key is not.
	 */
	private function validateSslParameters(array &$httptests, $method, ?array $db_httptests = null) {
		foreach ($httptests as &$httptest) {
			if (array_key_exists('ssl_key_password', $httptest)
					|| array_key_exists('ssl_key_file', $httptest)
					|| array_key_exists('ssl_cert_file', $httptest)) {
				if ($method === 'validateCreate') {
					$httptest += [
						'ssl_key_password' => '',
						'ssl_key_file' => '',
						'ssl_cert_file' => ''
					];
				}
				else {
					$db_httptest = $db_httptests[$httptest['httptestid']];
					$httptest += [
						'ssl_key_password' => $db_httptest['ssl_key_password'],
						'ssl_key_file' => $db_httptest['ssl_key_file'],
						'ssl_cert_file' => $db_httptest['ssl_cert_file']
					];
				}

				if ($httptest['ssl_key_password'] != '' && $httptest['ssl_key_file'] == '') {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Empty SSL key file for web scenario "%1$s".', $httptest['name'])
					);
				}

				if ($httptest['ssl_key_file'] != '' && $httptest['ssl_cert_file'] == '') {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Empty SSL certificate file for web scenario "%1$s".', $httptest['name'])
					);
				}
			}
		}
		unset($httptest);
	}

	/**
	 * @param array  $httptests
	 * @param string $method
	 * @param array  $db_httptests
	 *
	 * @throws APIException if parameters is invalid.
	 */
	private function validateRetrieveMode(array &$httptests, $method, ?array $db_httptests = null) {
		foreach ($httptests as &$httptest) {
			if (!array_key_exists('steps', $httptest)) {
				continue;
			}

			foreach ($httptest['steps'] as &$httpstep) {
				if (array_key_exists('retrieve_mode', $httpstep)
						|| array_key_exists('posts', $httpstep)
						|| array_key_exists('required', $httpstep)) {

					if ($method === 'validateCreate' || !array_key_exists('httpstepid', $httpstep)) {
						$httpstep += [
							'retrieve_mode' => HTTPTEST_STEP_RETRIEVE_MODE_CONTENT,
							'posts' => '',
							'required' => ''
						];
					}
					else {
						$db_httptest = $db_httptests[$httptest['httptestid']];
						$db_httpstep = $db_httptest['steps'][$httpstep['httpstepid']];
						$httpstep += [
							'retrieve_mode' => $db_httpstep['retrieve_mode'],
							'required' => $db_httpstep['required'],
							'posts' => ($db_httpstep['retrieve_mode'] != HTTPTEST_STEP_RETRIEVE_MODE_HEADERS)
								? $db_httpstep['posts']
								: ''
						];
					}

					if ($httpstep['retrieve_mode'] == HTTPTEST_STEP_RETRIEVE_MODE_HEADERS) {
						if ($httpstep['posts'] !== '' && $httpstep['posts'] !== []) {
							$field_name = $httpstep['required'] !== '' ? 'required' : 'posts';

							self::exception(ZBX_API_ERROR_PARAMETERS,
								_s('Incorrect value for field "%1$s": %2$s.', 'posts', _('should be empty'))
							);
						}
					}
				}
			}
			unset($httpstep);
		}
		unset($httptest);
	}

	/**
	 * @param array      $templateids
	 * @param array|null $hostids
	 */
	public static function unlinkTemplateObjects(array $templateids, ?array $hostids = null): void {
		$hostids_condition = $hostids ? ' AND '.dbConditionId('hht.hostid', $hostids) : '';

		$result = DBselect(
			'SELECT hht.httptestid,hht.name,h.status AS host_status'.
			' FROM httptest ht,httptest hht,hosts h'.
			' WHERE ht.httptestid=hht.templateid'.
				' AND hht.hostid=h.hostid'.
				' AND '.dbConditionId('ht.hostid', $templateids).
				$hostids_condition
		);

		$httptests = [];

		while ($row = DBfetch($result)) {
			$httptest = [
				'httptestid' => $row['httptestid'],
				'name' => $row['name'],
				'templateid' => 0
			];

			if ($row['host_status'] == HOST_STATUS_TEMPLATE) {
				$httptest += ['uuid' => generateUuidV4()];
			}

			$httptests[] = $httptest;
		}

		if ($httptests) {
			Manager::HttpTest()->update($httptests);
		}
	}

	/**
	 * @param array      $templateids
	 * @param array|null $hostids
	 */
	public static function clearTemplateObjects(array $templateids, ?array $hostids = null): void {
		$hostids_condition = $hostids ? ' AND '.dbConditionId('hht.hostid', $hostids) : '';

		$db_httptests = DBfetchArrayAssoc(DBselect(
			'SELECT hht.httptestid,hht.name'.
			' FROM httptest ht,httptest hht'.
			' WHERE ht.httptestid=hht.templateid'.
				' AND '.dbConditionId('ht.hostid', $templateids).
				$hostids_condition
		), 'httptestid');

		if ($db_httptests) {
			self::deleteForce($db_httptests);
		}
	}
}
