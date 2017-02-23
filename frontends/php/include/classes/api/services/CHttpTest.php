<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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
		$userType = self::$userData['type'];
		$userid = self::$userData['userid'];

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
			'applicationids' => null,
			'hostids'        => null,
			'groupids'       => null,
			'templateids'    => null,
			'editable'       => null,
			'inherited'      => null,
			'templated'      => null,
			'monitored'      => null,
			'nopermissions'  => null,
			// filter
			'filter'         => null,
			'search'         => null,
			'searchByAny'    => null,
			'startSearch'    => null,
			'excludeSearch'  => null,
			// output
			'output'         => API_OUTPUT_EXTEND,
			'expandName'     => null,
			'expandStepName' => null,
			'selectHosts'    => null,
			'selectSteps'    => null,
			'countOutput'    => null,
			'groupCount'     => null,
			'preservekeys'   => null,
			'sortfield'      => '',
			'sortorder'      => '',
			'limit'          => null
		];
		$options = zbx_array_merge($defOptions, $options);

		// editable + PERMISSION CHECK
		if ($userType != USER_TYPE_SUPER_ADMIN && !$options['nopermissions']) {
			$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ;

			$userGroups = getUserGroupsByUserId($userid);

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

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['hostid'] = 'ht.hostid';
			}
		}

		// groupids
		if (!is_null($options['groupids'])) {
			zbx_value2array($options['groupids']);

			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['where'][] = dbConditionInt('hg.groupid', $options['groupids']);
			$sqlParts['where'][] = 'hg.hostid=ht.hostid';

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['hg'] = 'hg.groupid';
			}
		}

		// applicationids
		if (!is_null($options['applicationids'])) {
			zbx_value2array($options['applicationids']);

			$sqlParts['where'][] = dbConditionInt('ht.applicationid', $options['applicationids']);
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
			$this->dbFilter('httptest ht', $options, $sqlParts);
		}

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		$headersRequested = (is_array($options['output']) && in_array('headers', $options['output']))
					|| $options['output'] == API_OUTPUT_EXTEND;

		$variablesRequested = (is_array($options['output']) && in_array('variables', $options['output']))
					|| $options['output'] == API_OUTPUT_EXTEND;

		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$res = DBselect($this->createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
		while ($httpTest = DBfetch($res)) {
			if ($headersRequested) {
				$httpTest['headers'] = [];
			}

			if ($variablesRequested) {
				$httpTest['variables'] = [];
			}

			if (!is_null($options['countOutput'])) {
				if (!is_null($options['groupCount'])) {
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

		if (!is_null($options['countOutput'])) {
			return $result;
		}

		if ($headersRequested || $variablesRequested) {
			$db_httpfields = DB::select('httptest_field', [
				'output' => ['httptest_fieldid', 'httptestid', 'type', 'name', 'value'],
				'filter' => ['httptestid' => array_keys($result)]
			]);

			foreach ($db_httpfields as $db_httpfield) {
				if (HTTPFIELD_TYPE_HEADER == $db_httpfield['type'] && $headersRequested) {
					$type = 'headers';
				} elseif(HTTPFIELD_TYPE_VARIABLE == $db_httpfield['type'] && $variablesRequested) {
					$type = 'variables';
				} else {
					continue;
				}

				$result[$db_httpfield['httptestid']][$type][$db_httpfield['httptest_fieldid']] = [
					'name' => $db_httpfield['name'],
					'value' => $db_httpfield['value']
				];
			}
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
		if (is_null($options['preservekeys'])) {
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
	 */
	public function create($httptests) {
		$this->validateCreate($httptests);

		$httptests = Manager::HttpTest()->persist($httptests);

		$this->addAuditBulk(AUDIT_ACTION_ADD, AUDIT_RESOURCE_SCENARIO, $httptests);

		return ['httptestids' => zbx_objectValues($httptests, 'httptestid')];
	}

	protected static function formatHttpPairError($path, $error) {
		if (mb_strpos($path, '/1/steps/') === 0 ) {
			$path = mb_substr($path, mb_strlen('/1/steps/'));
			$step = mb_substr($path, 0, mb_strpos($path, '/'));
			$target = _s('step #%1$s of web scenario', $step);
		}
		else {
			$target = _('web scenario');
		}

		return _s('Incorrect parameter of #%1$s: %2$s.', $target, $error);
	}

	/**
	 * HTTP pair validator.
	 *
	 * @param mixed  $data
	 * @param array  $type
	 * @param string $error
	 * @param string $path
	 *
	 * @return bool
	 */
	public static function validateHTTPPairs(&$data = null, $type, &$error, $path) {
		// $data can be NULL when API_ALLOW_NULL is set
		if ($data === null) {
			return true;
		}

		/* converts to pair array */
		if (!is_array($data)) {
			$delimiter = ($type === HTTPFIELD_TYPE_HEADER)?':':'=';

			$pairs = array_filter(explode("\n", str_replace("\r", "\n", $data)));
			foreach ($pairs as &$pair) {
				$pos = mb_strpos($pair, $delimiter);
				if (false !== $pos) {
					$pair = [
						'name' => mb_substr($pair, 0, $delimiter),
						'value' => mb_substr($pair, $delimiter+1),
					];
				}
				else {
					$pair = [
						'name' => $pair,
						'value' => '',
					];
				}
			}
			unset($pair);

			$data = $pairs;
		}

		$typeNames = [
			HTTPFIELD_TYPE_HEADER => _('header'),
			HTTPFIELD_TYPE_VARIABLE => _('variable'),
			HTTPFIELD_TYPE_POST => _('post field'),
			HTTPFIELD_TYPE_QUERY => _('query field')
		];

		$names = [];
		foreach ($data as &$pair) {
			if (!array_key_exists('name', $pair) || empty($pair['name'])) {
				$error = self::formatHttpPairError($path, _s('%1$s name cannot be empty', $typeNames[$type]));
				return false;
			}

			if (mb_strlen($pair['name']) > 255) {
				$error = self::formatHttpPairError($path, _s('%1$s is too long', $typeNames[$type]));
				return false;
			}

			if (HTTPFIELD_TYPE_VARIABLE === $type && 1 !== preg_match('/^{[^{}]+}$/', $pair['name'])) {
				$error = self::formatHttpPairError($path, _s('%1$s name "%2$s" is not enclosed in {} or is malformed', $typeNames[$type], $pair['name']));
				return false;
			}

			if ((HTTPFIELD_TYPE_HEADER === $type) && (!array_key_exists('value', $pair) || empty($pair['value']))) {
				$error = self::formatHttpPairError($path, _s('%1$s value cannot be empty', $typeNames[$type]));
				return false;
			}

			/* normalize pair */
			$pair = [
				'name' => $pair['name'],
				'value' => $pair['value'],
				'type' => $type
			];

			if (HTTPFIELD_TYPE_VARIABLE !== $type) {
				continue;
			}

			/* variable names should be unique */
			if (in_array($pair['name'], $names)) {
				$error = self::formatHttpPairError($path, $path, _s('%1$s name "%2$s" already exists', $typeNames[$type], $pair['name']));
				return false;
			}
			$names[] = $pair['name'];
		}
		unset($pair);

		return true;
	}

	/**
	 * HTTP POST validator. Posts can be set to string (raw post) or to http pairs (form fields)
	 *
	 * @param mixed  &$data
	 * @param string &$error
	 * @param string $path
	 *
	 * @return bool
	 */
	public static function validatePosts(&$data = null, &$error, $path) {
		// $data can be NULL when API_ALLOW_NULL is set
		if ($data === null) {
			return true;
		}

		if (is_array($data)) {
			return self::validateHTTPPairs($data, HTTPFIELD_TYPE_POST, $error, $path);
		}

		$rule = ['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('httpstep', 'posts')];
		return CApiInputValidator::validate($rule, $data, $path, $error);
	}

	/**
	 * @param array $httpTests
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateCreate(array &$httptests) {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['hostid', 'name']], 'fields' => [
			'hostid' =>				['type' => API_ID, 'flags' => API_REQUIRED],
			'name' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('httptest', 'name')],
			'applicationid' =>		['type' => API_ID, 'flags' => API_ALLOW_NULL],
			'delay' =>				['type' => API_INT32, 'in' => '1:'.SEC_PER_DAY],
			'retries' =>			['type' => API_INT32, 'in' => '1:10'],
			'agent' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('httptest', 'agent')],
			'http_proxy' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('httptest', 'http_proxy')],
			'variables' =>			['type' => API_CUSTOM, 'validator' => [$this,'validateHTTPPairs'], 'params' => ['type' => HTTPFIELD_TYPE_VARIABLE]],
			'headers' =>			['type' => API_CUSTOM, 'validator' => [$this,'validateHTTPPairs'], 'params' => ['type' => HTTPFIELD_TYPE_HEADER]],
			'status' =>				['type' => API_INT32, 'in' => implode(',', [HTTPTEST_STATUS_ACTIVE, HTTPTEST_STATUS_DISABLED])],
			'authentication' =>		['type' => API_INT32, 'in' => implode(',', [HTTPTEST_AUTH_NONE, HTTPTEST_AUTH_BASIC, HTTPTEST_AUTH_NTLM])],
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
				'query_fields' =>			['type' => API_CUSTOM, 'validator' => [$this,'validateHTTPPairs'], 'params' => ['type' => HTTPFIELD_TYPE_QUERY]],
				'posts' =>				['type' => API_CUSTOM, 'validator' => [$this,'validatePosts']],
				'post_fields' =>			['type' => API_CUSTOM, 'validator' => [$this,'validateHTTPPairs'], 'params' => ['type' => HTTPFIELD_TYPE_POST]],
				'variables' =>			['type' => API_CUSTOM, 'validator' => [$this,'validateHTTPPairs'], 'params' => ['type' => HTTPFIELD_TYPE_VARIABLE]],
				'headers' =>			['type' => API_CUSTOM, 'validator' => [$this,'validateHTTPPairs'], 'params' => ['type' => HTTPFIELD_TYPE_HEADER]],
				'follow_redirects' =>	['type' => API_INT32, 'in' => implode(',', [HTTPTEST_STEP_FOLLOW_REDIRECTS_OFF, HTTPTEST_STEP_FOLLOW_REDIRECTS_ON])],
				'retrieve_mode' =>		['type' => API_INT32, 'in' => implode(',', [HTTPTEST_STEP_RETRIEVE_MODE_CONTENT, HTTPTEST_STEP_RETRIEVE_MODE_HEADERS])],
				'timeout' =>			['type' => API_INT32, 'in' => '0:65535'],
				'required' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('httpstep', 'required')],
				'status_codes' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('httpstep', 'status_codes')]
			]]
		]];
		if (!CApiInputValidator::validate($api_input_rules, $httptests, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$names_by_hostid = [];

		foreach ($httptests as $httptest) {
			$names_by_hostid[$httptest['hostid']][] = $httptest['name'];
		}

		$this->checkHostPermissions(array_keys($names_by_hostid));
		$this->checkDuplicates($names_by_hostid);
		$this->checkApplications($httptests, __FUNCTION__);
		$this->validateAuthParameters($httptests, __FUNCTION__);
		$this->validateSslParameters($httptests, __FUNCTION__);
		$this->validateSteps($httptests, __FUNCTION__);
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
			unset($db_httptest['steps']);
		}
		unset($db_httptest);

		$this->addAuditBulk(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_SCENARIO, $httptests, $db_httptests);

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
			'applicationid' =>		['type' => API_ID, 'flags' => API_ALLOW_NULL],
			'delay' =>				['type' => API_INT32, 'in' => '1:'.SEC_PER_DAY],
			'retries' =>			['type' => API_INT32, 'in' => '1:10'],
			'agent' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('httptest', 'agent')],
			'http_proxy' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('httptest', 'http_proxy')],
			'variables' =>			['type' => API_CUSTOM, 'validator' => [$this,'validateHTTPPairs'], 'params' => ['type' => HTTPFIELD_TYPE_VARIABLE]],
			'headers' =>			['type' => API_CUSTOM, 'validator' => [$this,'validateHTTPPairs'], 'params' => ['type' => HTTPFIELD_TYPE_HEADER]],
			'status' =>				['type' => API_INT32, 'in' => implode(',', [HTTPTEST_STATUS_ACTIVE, HTTPTEST_STATUS_DISABLED])],
			'authentication' =>		['type' => API_INT32, 'in' => implode(',', [HTTPTEST_AUTH_NONE, HTTPTEST_AUTH_BASIC, HTTPTEST_AUTH_NTLM])],
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
				'query_fields' =>			['type' => API_CUSTOM, 'validator' => [$this,'validateHTTPPairs'], 'params' => ['type' => HTTPFIELD_TYPE_QUERY]],
				'posts' =>				['type' => API_CUSTOM, 'validator' => [$this,'validatePosts']],
				'post_fields' =>			['type' => API_CUSTOM, 'validator' => [$this,'validateHTTPPairs'], 'params' => ['type' => HTTPFIELD_TYPE_POST]],
				'variables' =>			['type' => API_CUSTOM, 'validator' => [$this,'validateHTTPPairs'], 'params' => ['type' => HTTPFIELD_TYPE_VARIABLE]],
				'headers' =>			['type' => API_CUSTOM, 'validator' => [$this,'validateHTTPPairs'], 'params' => ['type' => HTTPFIELD_TYPE_HEADER]],
				'follow_redirects' =>	['type' => API_INT32, 'in' => implode(',', [HTTPTEST_STEP_FOLLOW_REDIRECTS_OFF, HTTPTEST_STEP_FOLLOW_REDIRECTS_ON])],
				'retrieve_mode' =>		['type' => API_INT32, 'in' => implode(',', [HTTPTEST_STEP_RETRIEVE_MODE_CONTENT, HTTPTEST_STEP_RETRIEVE_MODE_HEADERS])],
				'timeout' =>			['type' => API_INT32, 'in' => '0:65535'],
				'required' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('httpstep', 'required')],
				'status_codes' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('httpstep', 'status_codes')]
			]]
		]];
		if (!CApiInputValidator::validate($api_input_rules, $httptests, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		// permissions
		$db_httptests = $this->get([
			'output' => ['httptestid', 'hostid', 'name', 'applicationid', 'delay', 'retries', 'agent', 'http_proxy',
				'status', 'authentication', 'http_user', 'http_password', 'verify_peer', 'verify_host',
				'ssl_cert_file', 'ssl_key_file', 'ssl_key_password', 'templateid'
			],
			'httptestids' => zbx_objectValues($httptests, 'httptestid'),
			'editable' => true,
			'preservekeys' => true
		]);

		foreach ($db_httptests as &$db_httptest) {
			$db_httptest['headers'] = [];
			$db_httptest['variables'] = [];
			$db_httptest['steps'] = [];
		}
		unset($db_httptest);

		$db_httpsteps = DB::select('httpstep', [
			'output' => ['httpstepid', 'httptestid', 'name', 'no', 'url', 'timeout', 'posts', 'required',
				'status_codes', 'follow_redirects', 'retrieve_mode'
			],
			'filter' => ['httptestid' => array_keys($db_httptests)]
		]);

		foreach ($db_httpsteps as $db_httpstep) {
			$db_httptests[$db_httpstep['httptestid']]['steps'][$db_httpstep['httpstepid']] = $db_httpstep;
		}

		$names_by_hostid = [];

		foreach ($httptests as $httptest) {
			if (!array_key_exists($httptest['httptestid'], $db_httptests)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}

			$db_httptest = $db_httptests[$httptest['httptestid']];

			if (array_key_exists('name', $httptest) && $httptest['name'] !== $db_httptest['name']) {
				$names_by_hostid[$db_httptest['hostid']][] = $httptest['name'];
			}
		}

		$httptests = $this->extendObjectsByKey($httptests, $db_httptests, 'httptestid', ['hostid', 'name']);

		// uniqueness
		foreach ($httptests as &$httptest) {
			$db_httptest = $db_httptests[$httptest['httptestid']];

			if (array_key_exists('steps', $httptest)) {
				$httptest['steps'] =
					$this->extendObjectsByKey($httptest['steps'], $db_httptest['steps'], 'httpstepid', ['name']);
			}
		}
		unset($httptest);

		$api_input_rules = ['type' => API_OBJECTS, 'uniq' => [['hostid', 'name']], 'fields' => [
			'steps' =>	['type' => API_OBJECTS, 'uniq' => [['name']]]
		]];
		if (!CApiInputValidator::validateUniqueness($api_input_rules, $httptests, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		// validation
		if ($names_by_hostid) {
			$this->checkDuplicates($names_by_hostid);
		}
		$this->checkApplications($httptests, __FUNCTION__, $db_httptests);
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
			API::Item()->delete($del_itemids, true);
		}

		DB::delete('httptest', ['httptestid' => $del_httptestids]);

		$this->addAuditBulk(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_SCENARIO, $db_httptests);

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
	 * Check that application belongs to web scenario host.
	 *
	 * @param array  $httptests
	 * @param string $method
	 * @param array  $db_httptests
	 *
	 * @throws APIException  if application does not exists or belongs to another host.
	 */
	private function checkApplications(array $httptests, $method, array $db_httptests = null) {
		$applicationids = [];

		foreach ($httptests as $index => $httptest) {
			if (array_key_exists('applicationid', $httptest) && $httptest['applicationid'] != 0) {
				$applicationids[$httptest['applicationid']] = true;
			}
		}

		if (!$applicationids) {
			return;
		}

		$db_applications = DB::select('applications', [
			'output' => ['applicationid', 'hostid', 'name', 'flags'],
			'applicationids' => array_keys($applicationids),
			'preservekeys' => true
		]);

		foreach ($httptests as $index => $httptest) {
			if (array_key_exists('applicationid', $httptest) && $httptest['applicationid'] != 0) {
				if (!array_key_exists($httptest['applicationid'], $db_applications)) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Application with applicationid "%1$s" does not exist.', $httptest['applicationid'])
					);
				}

				$db_application = $db_applications[$httptest['applicationid']];

				if ($db_application['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s(
						'Cannot add a discovered application "%1$s" to a web scenario.', $db_application['name']
					));
				}

				$hostid = ($method === 'validateCreate')
					? $httptest['hostid']
					: $db_httptests[$httptest['httptestid']]['hostid'];

				if (bccomp($db_application['hostid'], $hostid) != 0) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_('The web scenario application belongs to a different host than the web scenario host.')
					);
				}
			}
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
			$_httptests = [];

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

						$unexpected_parameters = array_diff(['name', 'no'], array_keys($httpstep));

						if ($unexpected_parameters) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s(
								'Cannot update step for a templated web scenario "%1$s": %2$s.', $httptest['name'],
								_s('unexpected parameter "%1$s"', $unexpected_parameters[0])
							));
						}
					}

					foreach ($httptest['steps'] as $httpstep) {
						if (array_key_exists('httpstepid', $httpstep)
								&& !array_key_exists($httpstep['httpstepid'], $db_httptest['steps'])) {
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
	 * Range can be empty string, can be set as user macro or be numeric and contain ',' and '-'.
	 *
	 * Examples: '100-199, 301, 404, 500-550' or '{$USER_MACRO123}'
	 *
	 * @param array $httptests
	 *
	 * @throws APIException if the status code range is invalid.
	 */
	private function checkStatusCodes(array $httptests) {
		$user_macro_parser = new CUserMacroParser();

		foreach ($httptests as $httptest) {
			if (!array_key_exists('steps', $httptest)) {
				continue;
			}

			foreach ($httptest['steps'] as $httpstep) {
				if (!array_key_exists('status_codes', $httpstep)) {
					continue;
				}

				$status_codes = $httpstep['status_codes'];

				if ($status_codes === '' || $user_macro_parser->parse($status_codes) == CParser::PARSE_SUCCESS) {
					continue;
				}

				foreach (explode(',', $status_codes) as $range) {
					$range = explode('-', $range);
					if (count($range) > 2) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid response code "%1$s".', $status_codes));
					}

					foreach ($range as $value) {
						if (!is_numeric($value)) {
							self::exception(ZBX_API_ERROR_PARAMETERS,
								_s('Invalid response code "%1$s".', $status_codes)
							);
						}
					}
				}
			}
		}
	}

	protected function applyQueryOutputOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		$sqlParts = parent::applyQueryOutputOptions($tableName, $tableAlias, $options, $sqlParts);

		if ($options['countOutput'] === null) {
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
				$httpSteps = API::getApiService()->select('httpstep', [
					'output' => $this->outputExtend($options['selectSteps'], ['httptestid', 'httpstepid', 'post_type']),
					'filters' => ['httptestid' => $httpTestIds],
					'preservekeys' => true
				]);
				$relationMap = $this->createRelationMap($httpSteps, 'httptestid', 'httpstepid');

				$requestedFields = [];
				foreach (['headers', 'variables', 'query_fields'] as $field) {
					if ((is_array($options['selectSteps']) && in_array($field, $options['selectSteps'])) || $options['selectSteps'] == API_OUTPUT_EXTEND) {
						$requestedFields[] = $field;
					}
				}
				if ((is_array($options['selectSteps']) && in_array('posts', $options['selectSteps'])) || $options['selectSteps'] == API_OUTPUT_EXTEND) {
					$requestedFields[] = 'post_fields';
				}

				if (!empty($requestedFields)) {
					$dbHttpStepFields = DBfetchArray(DBselect(
						'SELECT htf.*'.
						' FROM httpstep_field htf'.
						' WHERE htf.httpstepid IN'.
						' (SELECT h.httpstepid'.
						' FROM httpstep h WHERE '.dbConditionInt('h.httptestid', $httpTestIds).')'.
						' ORDER BY type, htf.httpstep_fieldid'
					));

					foreach($httpSteps as &$step) {
						foreach ($requestedFields as $field) {
							$step[$field] = [];
						}

						$stepFields = array_filter($dbHttpStepFields, function($field) use ($step) {
							return $field['httpstepid'] === $step['httpstepid'];
						});

						foreach ($stepFields as $field) {
							if (HTTPFIELD_TYPE_HEADER == $field['type']) {
								$pairType = 'headers';
							}
							else if (HTTPFIELD_TYPE_VARIABLE == $field['type']) {
								$pairType = 'variables';
							}
							else if (HTTPFIELD_TYPE_QUERY == $field['type']) {
								$pairType = 'query_fields';
							}
							else if (HTTPFIELD_TYPE_POST == $field['type']) {
								$pairType = 'post_fields';
							}
							else {
								continue;
							}

							if (!in_array($pairType, $requestedFields)) {
								continue;
							}

							$step[$pairType][] = [
								'name' => $field['name'],
								'value' => $field['value']
							];
						}

						if (in_array('post_fields', $requestedFields)) {
							if (HTTPSTEP_POST_TYPE_FORM == $step['post_type']) {
								$step['posts'] = $step['post_fields'];
							}
							unset($step['post_fields']);
						}
					}
					unset($step);
				}

				$httpSteps = $this->unsetExtraFields($httpSteps, ['httptestid', 'httpstepid', 'post_type'], $options['selectSteps']);
				$result = $relationMap->mapMany($result, $httpSteps, 'steps');
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
			if (array_key_exists('authentication', $httptest)
					|| array_key_exists('http_user', $httptest)
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
				else {
					foreach (['http_user', 'http_password'] as $field_name) {
						$_httptest += [
							$field_name => ($method === 'validateUpdate')
								? $db_httptests[$httptest['httptestid']][$field_name]
								: ''
						];

						if ($_httptest[$field_name] === '') {
							self::exception(ZBX_API_ERROR_PARAMETERS,
								_s('Incorrect value for field "%1$s": %2$s.', $field_name, _('cannot be empty'))
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
							'posts' => $db_httpstep['posts'],
							'required' => $db_httpstep['required']
						];
					}

					if ($httpstep['retrieve_mode'] == HTTPTEST_STEP_RETRIEVE_MODE_HEADERS) {
						foreach (['posts', 'required'] as $field_name) {
							if ($httpstep[$field_name] !== '') {
								self::exception(ZBX_API_ERROR_PARAMETERS,
									_s('Incorrect value for field "%1$s": %2$s.', $field_name, _('should be empty'))
								);
							}
						}
					}
				}
			}
			unset($httpstep);
		}
		unset($httptest);
	}
}
