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
 * Connector API implementation.
 */
class CConnector extends CApiService {

	public const ACCESS_RULES = [
		'get' =>	['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'create' =>	['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'update' =>	['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'delete' =>	['min_user_type' => USER_TYPE_SUPER_ADMIN]
	];

	protected $tableName = 'connector';
	protected $tableAlias = 'c';
	protected $sortColumns = ['connectorid', 'name', 'data_type', 'status'];

	private array $output_fields = ['connectorid', 'name', 'protocol', 'data_type', 'url', 'item_value_type',
		'authtype', 'username', 'password', 'token', 'max_records', 'max_senders', 'max_attempts', 'attempt_interval',
		'timeout', 'http_proxy', 'verify_peer', 'verify_host', 'ssl_cert_file', 'ssl_key_file', 'ssl_key_password',
		'description', 'status', 'tags_evaltype'
	];

	/**
	 * @param array $options
	 *
	 * @throws APIException
	 *
	 * @return array|string
	 */
	public function get(array $options = []) {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS,
				_s('No permissions to call "%1$s.%2$s".', 'connector', __FUNCTION__)
			);
		}

		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			// filter
			'connectorids' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'filter' =>						['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => ['connectorid', 'name', 'protocol', 'data_type', 'url', 'item_value_type', 'authtype', 'username', 'token', 'max_records', 'max_senders', 'max_attempts', 'attempt_interval', 'timeout', 'http_proxy', 'verify_peer', 'verify_host', 'ssl_cert_file', 'ssl_key_file', 'status', 'tags_evaltype']],
			'search' =>						['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => ['name', 'url', 'username', 'token', 'attempt_interval', 'timeout', 'http_proxy', 'ssl_cert_file', 'ssl_key_file', 'description']],
			'searchByAny' =>				['type' => API_BOOLEAN, 'default' => false],
			'startSearch' =>				['type' => API_FLAG, 'default' => false],
			'excludeSearch' =>				['type' => API_FLAG, 'default' => false],
			'searchWildcardsEnabled' =>		['type' => API_BOOLEAN, 'default' => false],
			// output
			'output' =>						['type' => API_OUTPUT, 'in' => implode(',', $this->output_fields), 'default' => API_OUTPUT_EXTEND],
			'countOutput' =>				['type' => API_FLAG, 'default' => false],
			'selectTags' =>					['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL | API_ALLOW_COUNT, 'in' => implode(',', ['tag', 'operator', 'value']), 'default' => null],
			// sort and limit
			'sortfield' =>					['type' => API_STRINGS_UTF8, 'flags' => API_NORMALIZE, 'in' => implode(',', $this->sortColumns), 'uniq' => true, 'default' => []],
			'sortorder' =>					['type' => API_SORTORDER, 'default' => []],
			'limit' =>						['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => '1:'.ZBX_MAX_INT32, 'default' => null],
			// flags
			'preservekeys' =>				['type' => API_BOOLEAN, 'default' => false]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_connectors = [];

		if ($options['output'] === API_OUTPUT_EXTEND) {
			$options['output'] = $this->output_fields;
		}

		$resource = DBselect($this->createSelectQuery('connector', $options), $options['limit']);

		while ($row = DBfetch($resource)) {
			if ($options['countOutput']) {
				return $row['rowscount'];
			}

			$db_connectors[$row['connectorid']] = $row;
		}

		if ($db_connectors) {
			$db_connectors = $this->addRelatedObjects($options, $db_connectors);
			$db_connectors = $this->unsetExtraFields($db_connectors, ['connectorid'], $options['output']);

			if (!$options['preservekeys']) {
				$db_connectors = array_values($db_connectors);
			}
		}

		return $db_connectors;
	}

	/**
	 * @param array $options
	 * @param array $result
	 *
	 * @return array
	 */
	protected function addRelatedObjects(array $options, array $result): array {
		$result = parent::addRelatedObjects($options, $result);

		if ($options['selectTags'] !== null) {
			foreach ($result as &$row) {
				$row['tags'] = [];
			}
			unset($row);

			if ($options['selectTags'] === API_OUTPUT_COUNT) {
				$output = ['connector_tagid', 'connectorid'];
			}
			elseif ($options['selectTags'] === API_OUTPUT_EXTEND) {
				$output = ['connector_tagid', 'connectorid', 'tag', 'operator', 'value'];
			}
			else {
				$output = array_unique(array_merge(['connector_tagid', 'connectorid'], $options['selectTags']));
			}

			$sql_options = [
				'output' => $output,
				'filter' => ['connectorid' => array_keys($result)]
			];
			$db_tags = DBselect(DB::makeSql('connector_tag', $sql_options));

			while ($db_tag = DBfetch($db_tags)) {
				$result[$db_tag['connectorid']]['tags'][] =
					array_diff_key($db_tag, array_flip(['connector_tagid', 'connectorid']));
			}

			if ($options['selectTags'] === API_OUTPUT_COUNT) {
				foreach ($result as &$row) {
					$row['tags'] = (string) count($row['tags']);
				}
				unset($row);
			}
		}

		return $result;
	}

	/**
	 * @param array $connectors
	 *
	 * @throws APIException
	 *
	 * @return array
	 */
	public function create(array $connectors): array {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS,
				_s('No permissions to call "%1$s.%2$s".', 'connector', __FUNCTION__)
			);
		}

		self::validateCreate($connectors);

		$connectorids = DB::insert('connector', $connectors);

		foreach ($connectors as $index => &$connector) {
			$connector['connectorid'] = $connectorids[$index];
		}
		unset($connector);

		self::updateTags($connectors);

		self::addAuditLog(CAudit::ACTION_ADD, CAudit::RESOURCE_CONNECTOR, $connectors);

		return ['connectorids' => $connectorids];
	}

	/**
	 * @param array $connectors
	 *
	 * @throws APIException
	 */
	private static function validateCreate(array &$connectors): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['name']], 'fields' => [
			'name' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('connector', 'name')],
			'protocol' =>			['type' => API_INT32, 'in' => ZBX_STREAMING_PROTOCOL_V1],
			'data_type' =>			['type' => API_INT32, 'in' => implode(',', [ZBX_CONNECTOR_DATA_TYPE_ITEM_VALUES, ZBX_CONNECTOR_DATA_TYPE_EVENTS]), 'default' => DB::getDefault('connector', 'data_type')],
			'url' =>				['type' => API_URL, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_ALLOW_USER_MACRO, 'length' => DB::getFieldLength('connector', 'url')],
			'item_value_type' => 	['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'data_type', 'in' => ZBX_CONNECTOR_DATA_TYPE_ITEM_VALUES], 'type' => API_INT32, 'in' => ZBX_CONNECTOR_ITEM_VALUE_TYPE_FLOAT.':'.(ZBX_CONNECTOR_ITEM_VALUE_TYPE_FLOAT | ZBX_CONNECTOR_ITEM_VALUE_TYPE_STR | ZBX_CONNECTOR_ITEM_VALUE_TYPE_LOG | ZBX_CONNECTOR_ITEM_VALUE_TYPE_UINT64 | ZBX_CONNECTOR_ITEM_VALUE_TYPE_TEXT | ZBX_CONNECTOR_ITEM_VALUE_TYPE_BIN)],
										['else' => true, 'type' => API_INT32, 'in' => DB::getDefault('connector', 'item_value_type')]
			]],
			'authtype' =>			['type' => API_INT32, 'in' => implode(',', [ZBX_HTTP_AUTH_NONE, ZBX_HTTP_AUTH_BASIC, ZBX_HTTP_AUTH_NTLM, ZBX_HTTP_AUTH_KERBEROS, ZBX_HTTP_AUTH_DIGEST, ZBX_HTTP_AUTH_BEARER]), 'default' => DB::getDefault('connector', 'authtype')],
			'username' =>			['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'authtype', 'in' => implode(',', [ZBX_HTTP_AUTH_BASIC, ZBX_HTTP_AUTH_NTLM, ZBX_HTTP_AUTH_KERBEROS, ZBX_HTTP_AUTH_DIGEST])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('connector', 'username')],
										['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('connector', 'username')]
			]],
			'password' =>			['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'authtype', 'in' => implode(',', [ZBX_HTTP_AUTH_BASIC, ZBX_HTTP_AUTH_NTLM, ZBX_HTTP_AUTH_KERBEROS, ZBX_HTTP_AUTH_DIGEST])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('connector', 'password')],
										['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('connector', 'password')]
			]],
			'token' =>				['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'authtype', 'in' => ZBX_HTTP_AUTH_BEARER], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('connector', 'token')],
										['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('connector', 'token')]
			]],
			'max_records' =>		['type' => API_INT32, 'in' => '0:'.ZBX_MAX_INT32],
			'max_senders' =>		['type' => API_INT32, 'in' => '1:100'],
			'max_attempts' =>		['type' => API_INT32, 'in' => '1:5', 'default' => DB::getDefault('connector', 'max_attempts')],
			'attempt_interval' =>	['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'max_attempts', 'in' =>'2:5'], 'type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY, 'in' => '0:10'],
										['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('connector', 'attempt_interval')]
			]],
			'timeout' =>			['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO, 'in' => '1:'.SEC_PER_MIN],
			'http_proxy' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('connector', 'http_proxy')],
			'verify_peer' =>		['type' => API_INT32, 'in' => implode(',', [ZBX_HTTP_VERIFY_PEER_OFF, ZBX_HTTP_VERIFY_PEER_ON])],
			'verify_host' =>		['type' => API_INT32, 'in' => implode(',', [ZBX_HTTP_VERIFY_HOST_OFF, ZBX_HTTP_VERIFY_HOST_ON])],
			'ssl_cert_file' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('connector', 'ssl_cert_file')],
			'ssl_key_file' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('connector', 'ssl_key_file')],
			'ssl_key_password' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('connector', 'ssl_key_password')],
			'description' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('connector', 'description')],
			'status' =>				['type' => API_INT32, 'in' => implode(',', [ZBX_CONNECTOR_STATUS_DISABLED, ZBX_CONNECTOR_STATUS_ENABLED])],
			'tags_evaltype' =>		['type' => API_INT32, 'in' => implode(',', [CONDITION_EVAL_TYPE_AND_OR, CONDITION_EVAL_TYPE_OR])],
			'tags' =>				['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['tag', 'operator', 'value']], 'fields' => [
				'tag' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('connector_tag', 'tag')],
				'operator' =>			['type' => API_INT32, 'in' => implode(',', [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL, CONDITION_OPERATOR_LIKE, CONDITION_OPERATOR_NOT_LIKE, CONDITION_OPERATOR_EXISTS, CONDITION_OPERATOR_NOT_EXISTS]), 'default' => DB::getDefault('connector_tag', 'operator')],
				'value' =>				['type' => API_MULTIPLE, 'default' => DB::getDefault('connector_tag', 'value'), 'rules' => [
											['if' => ['field' => 'operator', 'in' => implode(',', [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL, CONDITION_OPERATOR_LIKE, CONDITION_OPERATOR_NOT_LIKE])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('connector_tag', 'value')],
											['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('connector_tag', 'value')]
				]]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $connectors, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		self::checkDuplicates($connectors);
	}

	/**
	 * Add default values for fields that became unnecessary as the result of the change of the type fields.
	 *
	 * @param array $connectors
	 */
	private static function addFieldDefaultsByType(array &$connectors): void {
		$db_defaults = DB::getDefaults('connector');

		foreach ($connectors as &$connector) {
			if ($connector['data_type'] != ZBX_CONNECTOR_DATA_TYPE_ITEM_VALUES) {
				$connector += ['item_value_type' => $db_defaults['item_value_type']];
			}

			if ($connector['authtype'] == ZBX_HTTP_AUTH_NONE || $connector['authtype'] == ZBX_HTTP_AUTH_BEARER) {
				$connector += [
					'username' => $db_defaults['username'],
					'password' => $db_defaults['password']
				];
			}

			if ($connector['authtype'] != ZBX_HTTP_AUTH_BEARER) {
				$connector += ['token' => $db_defaults['token']];
			}

			if ($connector['max_attempts'] == 1) {
				$connector += ['attempt_interval' => $db_defaults['attempt_interval']];
			}
		}
		unset($connector);
	}

	/**
	 * @param array $connectors
	 *
	 * @return array
	 */
	public function update(array $connectors): array {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS,
				_s('No permissions to call "%1$s.%2$s".', 'connector', __FUNCTION__)
			);
		}

		$this->validateUpdate($connectors, $db_connectors);
		self::addFieldDefaultsByType($connectors);

		$connectorids = array_column($connectors, 'connectorid');

		$upd_connectors = [];
		$upd_connectorids = [];

		$internal_fields = array_flip(['connectorid', 'authtype']);
		$nested_object_fields = array_flip(['tags']);

		foreach ($connectors as $i => &$connector) {
			$upd_connector = DB::getUpdatedValues('connector', $connector, $db_connectors[$connector['connectorid']]);

			if ($upd_connector) {
				$upd_connectors[] = [
					'values' => $upd_connector,
					'where' => ['connectorid' => $connector['connectorid']]
				];

				$connector = array_intersect_key($connector, $internal_fields + $upd_connector + $nested_object_fields);

				$upd_connectorids[$i] = $connector['connectorid'];
			}
			else {
				$connector = array_intersect_key($connector, $internal_fields + $nested_object_fields);
			}
		}
		unset($connector);

		if ($upd_connectors) {
			DB::update('connector', $upd_connectors);
		}

		self::updateTags($connectors, $db_connectors, $upd_connectorids);

		$connectors = array_intersect_key($connectors, $upd_connectorids);
		$db_connectors = array_intersect_key($db_connectors, array_flip($upd_connectorids));

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_CONNECTOR, $connectors, $db_connectors);

		return ['connectorids' => $connectorids];
	}

	/**
	 * @param array      $connectors
	 * @param array|null $db_connectors
	 *
	 * @throws APIException
	 */
	private function validateUpdate(array &$connectors, ?array &$db_connectors): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE | API_ALLOW_UNEXPECTED, 'uniq' => [['connectorid']], 'fields' => [
			'connectorid' =>	['type' => API_ID, 'flags' => API_REQUIRED]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $connectors, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_connectors = DB::select('connector', [
			'output' => $this->output_fields,
			'connectorids' => array_column($connectors, 'connectorid'),
			'preservekeys' => true
		]);

		if (count($connectors) != count($db_connectors)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
		}

		$connectors = $this->extendObjectsByKey($connectors, $db_connectors, 'connectorid', ['data_type', 'authtype',
			'max_attempts'
		]);

		$api_input_rules = ['type' => API_OBJECTS, 'uniq' => [['connectorid'], ['name']], 'fields' => [
			'connectorid' =>		['type' => API_ID],
			'name' =>				['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('connector', 'name')],
			'protocol' =>			['type' => API_INT32, 'in' => ZBX_STREAMING_PROTOCOL_V1],
			'data_type' =>			['type' => API_INT32, 'in' => implode(',', [ZBX_CONNECTOR_DATA_TYPE_ITEM_VALUES, ZBX_CONNECTOR_DATA_TYPE_EVENTS])],
			'url' =>				['type' => API_URL, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO, 'length' => DB::getFieldLength('connector', 'url')],
			'item_value_type' => 	['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'data_type', 'in' => ZBX_CONNECTOR_DATA_TYPE_ITEM_VALUES], 'type' => API_INT32, 'in' => ZBX_CONNECTOR_ITEM_VALUE_TYPE_FLOAT.':'.(ZBX_CONNECTOR_ITEM_VALUE_TYPE_FLOAT | ZBX_CONNECTOR_ITEM_VALUE_TYPE_STR | ZBX_CONNECTOR_ITEM_VALUE_TYPE_LOG | ZBX_CONNECTOR_ITEM_VALUE_TYPE_UINT64 | ZBX_CONNECTOR_ITEM_VALUE_TYPE_TEXT | ZBX_CONNECTOR_ITEM_VALUE_TYPE_BIN)],
										['else' => true, 'type' => API_INT32, 'in' => DB::getDefault('connector', 'item_value_type')]
			]],
			'authtype' =>			['type' => API_INT32, 'in' => implode(',', [ZBX_HTTP_AUTH_NONE, ZBX_HTTP_AUTH_BASIC, ZBX_HTTP_AUTH_NTLM, ZBX_HTTP_AUTH_KERBEROS, ZBX_HTTP_AUTH_DIGEST, ZBX_HTTP_AUTH_BEARER])],
			'username' =>			['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'authtype', 'in' => implode(',', [ZBX_HTTP_AUTH_BASIC, ZBX_HTTP_AUTH_NTLM, ZBX_HTTP_AUTH_KERBEROS, ZBX_HTTP_AUTH_DIGEST])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('connector', 'username')],
										['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('connector', 'username')]
			]],
			'password' =>			['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'authtype', 'in' => implode(',', [ZBX_HTTP_AUTH_BASIC, ZBX_HTTP_AUTH_NTLM, ZBX_HTTP_AUTH_KERBEROS, ZBX_HTTP_AUTH_DIGEST])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('connector', 'password')],
										['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('connector', 'password')]
			]],
			'token' =>				['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'authtype', 'in' => ZBX_HTTP_AUTH_BEARER], 'type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('connector', 'token')],
										['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('connector', 'token')]
			]],
			'max_records' =>		['type' => API_INT32, 'in' => '0:'.ZBX_MAX_INT32],
			'max_senders' =>		['type' => API_INT32, 'in' => '1:100'],
			'max_attempts' =>		['type' => API_INT32, 'in' => '1:5'],
			'attempt_interval' =>	['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'max_attempts', 'in' =>'2:5'], 'type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY, 'in' => '0:10'],
										['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('connector', 'attempt_interval')]
			]],
			'timeout' =>			['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO, 'in' => '1:'.SEC_PER_MIN],
			'http_proxy' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('connector', 'http_proxy')],
			'verify_peer' =>		['type' => API_INT32, 'in' => implode(',', [ZBX_HTTP_VERIFY_PEER_OFF, ZBX_HTTP_VERIFY_PEER_ON])],
			'verify_host' =>		['type' => API_INT32, 'in' => implode(',', [ZBX_HTTP_VERIFY_HOST_OFF, ZBX_HTTP_VERIFY_HOST_ON])],
			'ssl_cert_file' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('connector', 'ssl_cert_file')],
			'ssl_key_file' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('connector', 'ssl_key_file')],
			'ssl_key_password' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('connector', 'ssl_key_password')],
			'description' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('connector', 'description')],
			'status' =>				['type' => API_INT32, 'in' => implode(',', [ZBX_CONNECTOR_STATUS_DISABLED, ZBX_CONNECTOR_STATUS_ENABLED])],
			'tags_evaltype' =>		['type' => API_INT32, 'in' => implode(',', [CONDITION_EVAL_TYPE_AND_OR, CONDITION_EVAL_TYPE_OR])],
			'tags' =>				['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['tag', 'operator', 'value']], 'fields' => [
				'tag' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('connector_tag', 'tag')],
				'operator' =>			['type' => API_INT32, 'in' => implode(',', [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL, CONDITION_OPERATOR_LIKE, CONDITION_OPERATOR_NOT_LIKE, CONDITION_OPERATOR_EXISTS, CONDITION_OPERATOR_NOT_EXISTS]), 'default' => DB::getDefault('connector_tag', 'operator')],
				'value' =>				['type' => API_MULTIPLE, 'default' => DB::getDefault('connector_tag', 'value'), 'rules' => [
											['if' => ['field' => 'operator', 'in' => implode(',', [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL, CONDITION_OPERATOR_LIKE, CONDITION_OPERATOR_NOT_LIKE])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('connector_tag', 'value')],
											['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('connector_tag', 'value')]
				]]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $connectors, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		self::checkDuplicates($connectors, $db_connectors);

		self::addAffectedObjects($connectors, $db_connectors);
	}

	/**
	 * @param array $connectors
	 * @param array $db_connectors
	 */
	private static function addAffectedObjects(array $connectors, array &$db_connectors): void {
		$connectorids = [];

		foreach ($connectors as $connector) {
			if (array_key_exists('tags', $connector)) {
				$connectorids[] = $connector['connectorid'];
				$db_connectors[$connector['connectorid']]['tags'] = [];
			}
		}

		if (!$connectorids) {
			return;
		}

		$options = [
			'output' => ['connector_tagid', 'connectorid', 'tag', 'operator', 'value'],
			'filter' => ['connectorid' => $connectorids]
		];
		$db_tags = DBselect(DB::makeSql('connector_tag', $options));

		while ($db_tag = DBfetch($db_tags)) {
			$db_connectors[$db_tag['connectorid']]['tags'][$db_tag['connector_tagid']] =
				array_diff_key($db_tag, array_flip(['connectorid']));
		}
	}

	/**
	 * @param array      $connectors
	 * @param array|null $db_connectors
	 *
	 * @throws APIException
	 */
	private static function checkDuplicates(array $connectors, array $db_connectors = null): void {
		$names = [];

		foreach ($connectors as $connector) {
			if (!array_key_exists('name', $connector)) {
				continue;
			}

			if ($db_connectors === null || $connector['name'] !== $db_connectors[$connector['connectorid']]['name']) {
				$names[] = $connector['name'];
			}
		}

		if (!$names) {
			return;
		}

		$duplicates = DB::select('connector', [
			'output' => ['name'],
			'filter' => ['name' => $names],
			'limit' => 1
		]);

		if ($duplicates) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Connector "%1$s" already exists.', $duplicates[0]['name']));
		}
	}

	/**
	 * @param array      $connectors
	 * @param array|null $db_connectors
	 * @param array|null $upd_connectorids
	 */
	private static function updateTags(array &$connectors, array $db_connectors = null,
			array &$upd_connectorids = null): void {
		$ins_tags = [];
		$del_tagids = [];

		foreach ($connectors as $i => &$connector) {
			if (!array_key_exists('tags', $connector)) {
				continue;
			}

			$changed = false;
			$db_tags = $db_connectors !== null ? $db_connectors[$connector['connectorid']]['tags'] : [];

			foreach ($connector['tags'] as &$tag) {
				$db_tagid = key(
					array_filter($db_tags, static function (array $db_tag) use ($tag): bool {
						return $tag['tag'] === $db_tag['tag']
							&& $tag['operator'] == $db_tag['operator']
							&& $tag['value'] === $db_tag['value'];
					})
				);

				if ($db_tagid !== null) {
					$tag['connector_tagid'] = $db_tagid;
					unset($db_tags[$db_tagid]);
				}
				else {
					$ins_tags[] = ['connectorid' => $connector['connectorid']] + $tag;
					$changed = true;
				}
			}
			unset($tag);

			if ($db_tags) {
				$del_tagids = array_merge($del_tagids, array_keys($db_tags));
				$changed = true;
			}

			if ($db_connectors !== null) {
				if ($changed) {
					$upd_connectorids[$i] = $connector['connectorid'];
				}
				else {
					unset($connector['tags']);
				}
			}
		}
		unset($connector);

		if ($del_tagids) {
			DB::delete('connector_tag', ['connector_tagid' => $del_tagids]);
		}

		if ($ins_tags) {
			$tagids = DB::insert('connector_tag', $ins_tags);
		}

		foreach ($connectors as &$connector) {
			if (!array_key_exists('tags', $connector)) {
				continue;
			}

			foreach ($connector['tags'] as &$tag) {
				if (!array_key_exists('connector_tagid', $tag)) {
					$tag['connector_tagid'] = array_shift($tagids);
				}
			}
			unset($tag);
		}
		unset($connector);
	}

	/**
	 * @param array $connectorids
	 *
	 * @throws APIException
	 *
	 * @return array
	 */
	public function delete(array $connectorids): array {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS,
				_s('No permissions to call "%1$s.%2$s".', 'connector', __FUNCTION__)
			);
		}

		self::validateDelete($connectorids, $db_connectors);

		DB::delete('connector_tag', ['connectorid' => $connectorids]);
		DB::delete('connector', ['connectorid' => $connectorids]);

		self::addAuditLog(CAudit::ACTION_DELETE, CAudit::RESOURCE_CONNECTOR, $db_connectors);

		return ['connectorids' => $connectorids];
	}

	/**
	 * @param array      $connectorids
	 * @param array|null $db_connectors
	 *
	 * @throws APIException
	 */
	private static function validateDelete(array $connectorids, ?array &$db_connectors): void {
		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];

		if (!CApiInputValidator::validate($api_input_rules, $connectorids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_connectors = DB::select('connector', [
			'output' => ['connectorid', 'name'],
			'connectorids' => $connectorids,
			'preservekeys' => true
		]);

		if (count($db_connectors) != count($connectorids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}
	}
}
