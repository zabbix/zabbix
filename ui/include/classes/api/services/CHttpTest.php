<?php
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
			$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ;
			$userGroups = getUserGroupsByUserId(self::$userData['userid']);

			$sqlParts['where'][] = 'EXISTS ('.
					'SELECT NULL'.
					' FROM hosts_groups hgg'.
						' JOIN rights r'.
							' ON r.id=hgg.groupid'.
								' AND '.dbConditionInt('r.groupid', $userGroups).
					' WHERE ht.hostid=hgg.hostid'.
					' GROUP BY hgg.hostid'.
					' HAVING MIN(r.permission)>'.PERM_DENY.
						' AND MAX(r.permission)>='.zbx_dbstr($permission).
					')';
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
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['uuid'], ['hostid', 'name']], 'fields' => [
			'hostid' =>				['type' => API_ID, 'flags' => API_REQUIRED],
			'uuid' =>				['type' => API_UUID],
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
			'authentication' =>		['type' => API_INT32, 'in' => implode(',', [HTTPTEST_AUTH_NONE, HTTPTEST_AUTH_BASIC, HTTPTEST_AUTH_NTLM, HTTPTEST_AUTH_KERBEROS, HTTPTEST_AUTH_DIGEST])],
			'http_user' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('httptest', 'http_user')],
			'http_password' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('httptest', 'http_password')],
			'verify_peer' =>		['type' => API_INT32, 'in' => implode(',', [HTTPTEST_VERIFY_PEER_OFF, HTTPTEST_VERIFY_PEER_ON])],
			'verify_host' =>		['type' => API_INT32, 'in' => implode(',', [HTTPTEST_VERIFY_HOST_OFF, HTTPTEST_VERIFY_HOST_ON])],
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

		$this->checkAndAddUuid($httptests);
		$this->checkHostPermissions(array_keys($names_by_hostid));
		$this->checkDuplicates($names_by_hostid);
		$this->validateAuthParameters($httptests, __FUNCTION__);
		$this->validateSslParameters($httptests, __FUNCTION__);
		$this->validateSteps($httptests, __FUNCTION__);
	}

	/**
	 * Check that only httptests on templates have UUID. Add UUID to all httptests on templates, if it does not exists.
	 *
	 * @param array $httptests_to_create
	 *
	 * @throws APIException
	 */
	protected function checkAndAddUuid(array &$httptests_to_create): void {
		$db_templateids = API::Template()->get([
			'output' => [],
			'templateids' => array_column($httptests_to_create, 'hostid'),
			'preservekeys' => true
		]);

		foreach ($httptests_to_create as $index => &$httptest) {
			if (!array_key_exists($httptest['hostid'], $db_templateids) && array_key_exists('uuid', $httptest)) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Invalid parameter "%1$s": %2$s.', '/' . ($index + 1), _s('unexpected parameter "%1$s"', 'uuid'))
				);
			}

			if (array_key_exists($httptest['hostid'], $db_templateids) && !array_key_exists('uuid', $httptest)) {
				$httptest['uuid'] = generateUuidV4();
			}
		}
		unset($httptest);

		$db_uuid = DB::select('httptest', [
			'output' => ['uuid'],
			'filter' => ['uuid' => array_column($httptests_to_create, 'uuid')],
			'limit' => 1
		]);

		if ($db_uuid) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Entry with UUID "%1$s" already exists.', $db_uuid[0]['uuid'])
			);
		}
	}

	/**
	 * @param $httptests
	 *
	 * @return array
	 */
	public function update($httptests) {
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
	protected function validateUpdate(array &$httptests, array &$db_httptests = null) {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['httptestid']], 'fields' => [
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
			'authentication' =>		['type' => API_INT32, 'in' => implode(',', [HTTPTEST_AUTH_NONE, HTTPTEST_AUTH_BASIC, HTTPTEST_AUTH_NTLM, HTTPTEST_AUTH_KERBEROS, HTTPTEST_AUTH_DIGEST])],
			'http_user' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('httptest', 'http_user')],
			'http_password' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('httptest', 'http_password')],
			'verify_peer' =>		['type' => API_INT32, 'in' => implode(',', [HTTPTEST_VERIFY_PEER_OFF, HTTPTEST_VERIFY_PEER_ON])],
			'verify_host' =>		['type' => API_INT32, 'in' => implode(',', [HTTPTEST_VERIFY_HOST_OFF, HTTPTEST_VERIFY_HOST_ON])],
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
			'output' => ['httptestid', 'hostid', 'name', 'delay', 'retries', 'agent', 'http_proxy',
				'status', 'authentication', 'http_user', 'http_password', 'verify_peer', 'verify_host',
				'ssl_cert_file', 'ssl_key_file', 'ssl_key_password', 'templateid'
			],
			'selectSteps' => ['httpstepid', 'name', 'no', 'url', 'timeout', 'posts', 'required',
				'status_codes', 'follow_redirects', 'retrieve_mode', 'post_type'
			],
			'httptestids' => zbx_objectValues($httptests, 'httptestid'),
			'editable' => true,
			'preservekeys' => true
		]);

		foreach ($db_httptests as &$db_httptest) {
			$db_httptest['headers'] = [];
			$db_httptest['variables'] = [];
			$db_httptest['steps'] = zbx_toHash($db_httptest['steps'], 'httpstepid');
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
				// unexpected patameters for templated web scenario steps
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
	 * Delete web scenario.
	 *
	 * @param array $httptestids
	 * @param bool  $nopermissions
	 *
	 * @return array
	 */
	public function delete(array $httptestids, $nopermissions = false) {
		// TODO: remove $nopermissions hack

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

		if (!$nopermissions) {
			foreach ($httptestids as $httptestid) {
				if (!array_key_exists($httptestid, $db_httptests)) {
					self::exception(ZBX_API_ERROR_PERMISSIONS,
						_('No permissions to referred object or it does not exist!')
					);
				}

				$db_httptest = $db_httptests[$httptestid];

				if ($db_httptest['templateid'] != 0) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Cannot delete templated web scenario "%1$s".', $db_httptest['name'])
					);
				}
			}
		}

		$parent_httptestids = $httptestids;
		$child_httptestids = [];
		do {
			$parent_httptestids = array_keys(DB::select('httptest', [
				'output' => [],
				'filter' => ['templateid' => $parent_httptestids],
				'preservekeys' => true
			]));

			$child_httptestids = array_merge($child_httptestids, $parent_httptestids);
		}
		while ($parent_httptestids);

		$del_httptestids = array_merge($httptestids, $child_httptestids);
		$del_itemids = [];

		$db_httptestitems = DBselect(
			'SELECT hti.itemid'.
			' FROM httptestitem hti'.
			' WHERE '.dbConditionInt('hti.httptestid', $del_httptestids)
		);
		while ($db_httptestitem = DBfetch($db_httptestitems)) {
			$del_itemids[] = $db_httptestitem['itemid'];
		}

		$db_httpstepitems = DBselect(
			'SELECT hsi.itemid'.
			' FROM httpstepitem hsi,httpstep hs'.
			' WHERE hsi.httpstepid=hs.httpstepid'.
				' AND '.dbConditionInt('hs.httptestid', $del_httptestids)
		);
		while ($db_httpstepitem = DBfetch($db_httpstepitems)) {
			$del_itemids[] = $db_httpstepitem['itemid'];
		}

		if ($del_itemids) {
			CItemManager::delete($del_itemids);
		}

		DB::delete('httptest', ['httptestid' => $del_httptestids]);

		$this->addAuditBulk(CAudit::ACTION_DELETE, CAudit::RESOURCE_SCENARIO, $db_httptests);

		return ['httptestids' => $httptestids];
	}

	/**
	 * Checks if the current user has access to the given hosts and templates.
	 *
	 * @param array $hostids  an array of host or template IDs
	 *
	 * @throws APIException if the user doesn't have write permissions for the given hosts.
	 */
	private function checkHostPermissions(array $hostids) {
		if ($hostids) {
			$count = API::Host()->get([
				'countOutput' => true,
				'hostids' => $hostids,
				'editable' => true
			]);

			if ($count == count($hostids)) {
				return;
			}

			$count += API::Template()->get([
				'countOutput' => true,
				'templateids' => $hostids,
				'editable' => true
			]);

			if ($count != count($hostids)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}
		}
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
	protected function validateSteps(array &$httptests, $method, array $db_httptests = null) {
		if ($method === 'validateUpdate') {
			foreach ($httptests as $httptest) {
				if (!array_key_exists('steps', $httptest)) {
					continue;
				}

				$db_httptest = $db_httptests[$httptest['httptestid']];

				if ($db_httptest['templateid'] != 0) {
					if (count($httptest['steps']) != count($db_httptest['steps'])) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect templated web scenario step count.'));
					}

					foreach ($httptest['steps'] as $httpstep) {
						if (!array_key_exists('httpstepid', $httpstep)) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s(
								'Cannot update step for a templated web scenario "%1$s": %2$s.', $httptest['name'],
								_s('the parameter "%1$s" is missing', 'httpstepid')
							));
						}
						elseif (!array_key_exists($httpstep['httpstepid'], $db_httptest['steps'])) {
							self::exception(ZBX_API_ERROR_PARAMETERS,
								_('No permissions to referred object or it does not exist!')
							);
						}
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
	private function validateAuthParameters(array &$httptests, $method, array $db_httptests = null) {
		foreach ($httptests as &$httptest) {
			if (array_key_exists('authentication', $httptest) || array_key_exists('http_user', $httptest)
					|| array_key_exists('http_password', $httptest)) {
				$httptest += [
					'authentication' => ($method === 'validateUpdate')
						? $db_httptests[$httptest['httptestid']]['authentication']
						: HTTPTEST_AUTH_NONE
				];

				if ($httptest['authentication'] == HTTPTEST_AUTH_NONE) {
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
	private function validateSslParameters(array &$httptests, $method, array $db_httptests = null) {
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
	private function validateRetrieveMode(array &$httptests, $method, array $db_httptests = null) {
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
}
