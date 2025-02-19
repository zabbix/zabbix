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
 * Class containing methods for operations with host prototypes.
 */
class CHostPrototype extends CHostBase {

	protected $sortColumns = ['hostid', 'host', 'name', 'status', 'discover'];

	/**
	 * Maximum number of inheritable items per iteration.
	 *
	 * @var int
	 */
	protected const INHERIT_CHUNK_SIZE = 1000;

	/**
	 * Get host prototypes.
	 *
	 * @param array        $options
	 * @param bool         $options['selectMacros']      Array of macros fields to be selected or string "extend".
	 * @param string|array $options['selectInterfaces']  Return an "interfaces" property with host interfaces.
	 *
	 * @return array
	 */
	public function get(array $options) {
		$hosts_fields = array_keys($this->getTableSchema('hosts')['fields']);
		$output_fields = ['hostid', 'host', 'name', 'status', 'templateid', 'inventory_mode', 'discover',
			'custom_interfaces', 'uuid'
		];
		$discovery_fields = array_keys($this->getTableSchema('items')['fields']);
		$hostmacro_fields = array_keys($this->getTableSchema('hostmacro')['fields']);
		$interface_fields = ['type', 'useip', 'ip', 'dns', 'port', 'main', 'details'];

		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			// filter
			'hostids' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'discoveryids' =>			['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'filter' =>					['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => ['hostid', 'host', 'name', 'status', 'templateid', 'inventory_mode']],
			'search' =>					['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => ['host', 'name']],
			'searchByAny' =>			['type' => API_BOOLEAN, 'default' => false],
			'startSearch' =>			['type' => API_FLAG, 'default' => false],
			'excludeSearch' =>			['type' => API_FLAG, 'default' => false],
			'searchWildcardsEnabled' =>	['type' => API_BOOLEAN, 'default' => false],
			// output
			'output' =>					['type' => API_OUTPUT, 'in' => implode(',', $output_fields), 'default' => $output_fields],
			'countOutput' =>			['type' => API_FLAG, 'default' => false],
			'groupCount' =>				['type' => API_FLAG, 'default' => false],
			'selectGroupLinks' =>		['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', ['groupid']), 'default' => null],
			'selectGroupPrototypes' =>	['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', ['group_prototypeid', 'name']), 'default' => null],
			'selectDiscoveryRule' =>	['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', $discovery_fields), 'default' => null],
			'selectParentHost' =>		['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', $hosts_fields), 'default' => null],
			'selectInterfaces' =>		['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', $interface_fields), 'default' => null],
			'selectTemplates' =>		['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL | API_ALLOW_COUNT, 'in' => implode(',', $hosts_fields), 'default' => null],
			'selectMacros' =>			['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', $hostmacro_fields), 'default' => null],
			'selectTags' =>				['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', ['tag', 'value']), 'default' => null],
			// sort and limit
			'sortfield' =>				['type' => API_STRINGS_UTF8, 'flags' => API_NORMALIZE, 'in' => implode(',', $this->sortColumns), 'uniq' => true, 'default' => []],
			'sortorder' =>				['type' => API_SORTORDER, 'default' => []],
			'limit' =>					['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => '1:'.ZBX_MAX_INT32, 'default' => null],
			// flags
			'inherited'	=>				['type' => API_BOOLEAN, 'flags' => API_ALLOW_NULL, 'default' => null],
			'editable' =>				['type' => API_BOOLEAN, 'default' => false],
			'preservekeys' =>			['type' => API_BOOLEAN, 'default' => false],
			'nopermissions' =>			['type' => API_BOOLEAN, 'default' => false]	// TODO: This property and frontend usage SHOULD BE removed.
		]];
		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$options['filter']['flags'] = ZBX_FLAG_DISCOVERY_PROTOTYPE;

		if ($options['output'] === API_OUTPUT_EXTEND) {
			$options['output'] = $output_fields;
		}

		// build and execute query
		$sql = $this->createSelectQuery($this->tableName(), $options);
		$res = DBselect($sql, $options['limit']);

		// fetch results
		$result = [];
		while ($row = DBfetch($res)) {
			// a count query, return a single result
			if ($options['countOutput']) {
				if ($options['groupCount']) {
					$result[] = $row;
				}
				else {
					$result = $row['rowscount'];
				}
			}
			// a normal select query
			else {
				$result[$row[$this->pk()]] = $row;
			}
		}

		if ($options['countOutput']) {
			return $result;
		}

		if ($result) {
			$result = $this->addRelatedObjects($options, $result);
			$result = $this->unsetExtraFields($result, ['triggerid'], $options['output']);
		}

		if (!$options['preservekeys']) {
			$result = zbx_cleanHashes($result);
		}

		return $result;
	}

	protected function applyQueryOutputOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		$sqlParts = parent::applyQueryOutputOptions($tableName, $tableAlias, $options, $sqlParts);

		if (!$options['countOutput'] && $this->outputIsRequested('inventory_mode', $options['output'])) {
			$sqlParts['select']['inventory_mode'] =
				dbConditionCoalesce('hinv.inventory_mode', HOST_INVENTORY_DISABLED, 'inventory_mode');
		}

		if ((!$options['countOutput'] && $this->outputIsRequested('inventory_mode', $options['output']))
				|| ($options['filter'] && array_key_exists('inventory_mode', $options['filter']))) {
			$sqlParts['left_join'][] = ['alias' => 'hinv', 'table' => 'host_inventory', 'using' => 'hostid'];
			$sqlParts['left_table'] = ['alias' => $this->tableAlias, 'table' => $this->tableName];
		}

		return $sqlParts;
	}

	protected function applyQueryFilterOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		$sqlParts = parent::applyQueryFilterOptions($tableName, $tableAlias, $options, $sqlParts);

		// do not return host prototypes from discovered hosts
		$sqlParts['from'][] = 'host_discovery hd';
		$sqlParts['from'][] = 'items i';
		$sqlParts['from'][] = 'hosts ph';
		$sqlParts['where'][] = $this->fieldId('hostid').'=hd.hostid';
		$sqlParts['where'][] = 'hd.parent_itemid=i.itemid';
		$sqlParts['where'][] = 'i.hostid=ph.hostid';
		$sqlParts['where'][] = 'ph.flags='.ZBX_FLAG_DISCOVERY_NORMAL;

		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN && !$options['nopermissions']) {
			if (self::$userData['ugsetid'] == 0) {
				$sql_parts['where'][] = '1=0';
			}
			else {
				$sqlParts['from'][] = 'host_hgset hh';
				$sqlParts['from'][] = 'permission p';
				$sqlParts['where'][] = 'i.hostid=hh.hostid';
				$sqlParts['where'][] = 'hh.hgsetid=p.hgsetid';
				$sqlParts['where'][] = 'p.ugsetid='.self::$userData['ugsetid'];

				if ($options['editable']) {
					$sqlParts['where'][] = 'p.permission='.PERM_READ_WRITE;
				}
			}
		}

		// discoveryids
		if ($options['discoveryids'] !== null) {
			$sqlParts['where'][] = dbConditionInt('hd.parent_itemid', (array) $options['discoveryids']);

			if ($options['groupCount']) {
				$sqlParts['group']['hd'] = 'hd.parent_itemid';
			}
		}

		// inherited
		if ($options['inherited'] !== null) {
			$sqlParts['where'][] = ($options['inherited']) ? 'h.templateid IS NOT NULL' : 'h.templateid IS NULL';
		}

		if ($options['filter'] && array_key_exists('inventory_mode', $options['filter'])) {
			if ($options['filter']['inventory_mode'] !== null) {
				$inventory_mode_query = (array) $options['filter']['inventory_mode'];

				$inventory_mode_where = [];
				$null_position = array_search(HOST_INVENTORY_DISABLED, $inventory_mode_query);

				if ($null_position !== false) {
					unset($inventory_mode_query[$null_position]);
					$inventory_mode_where[] = 'hinv.inventory_mode IS NULL';
				}

				if ($null_position === false || $inventory_mode_query) {
					$inventory_mode_where[] = dbConditionInt('hinv.inventory_mode', $inventory_mode_query);
				}

				$sqlParts['where'][] = (count($inventory_mode_where) > 1)
					? '('.implode(' OR ', $inventory_mode_where).')'
					: $inventory_mode_where[0];
			}
		}

		return $sqlParts;
	}

	/**
	 * Retrieves and adds additional requested data to the result set.
	 *
	 * @param array $options
	 * @param array $result
	 *
	 * @return array
	 */
	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$hostids = array_keys($result);

		if ($options['selectDiscoveryRule'] !== null) {
			$relationMap = $this->createRelationMap($result, 'hostid', 'parent_itemid', 'host_discovery');
			$discoveryRules = API::DiscoveryRule()->get([
				'output' => $options['selectDiscoveryRule'],
				'itemids' => $relationMap->getRelatedIds(),
				'nopermissions' => true,
				'preservekeys' => true
			]);
			$result = $relationMap->mapOne($result, $discoveryRules, 'discoveryRule');
		}

		self::addRelatedGroupLinks($options, $result);
		self::addRelatedGroupPrototypes($options, $result);

		if ($options['selectParentHost'] !== null) {
			$hosts = [];
			$relationMap = new CRelationMap();
			$dbRules = DBselect(
				'SELECT hd.hostid,i.hostid AS parent_hostid'.
					' FROM host_discovery hd,items i'.
					' WHERE '.dbConditionId('hd.hostid', $hostids).
					' AND hd.parent_itemid=i.itemid'
			);
			while ($relation = DBfetch($dbRules)) {
				$relationMap->addRelation($relation['hostid'], $relation['parent_hostid']);
			}

			$related_ids = $relationMap->getRelatedIds();

			if ($related_ids) {
				$hosts = API::Host()->get([
					'output' => $options['selectParentHost'],
					'hostids' => $related_ids,
					'templated_hosts' => true,
					'nopermissions' => true,
					'preservekeys' => true
				]);
			}

			$result = $relationMap->mapOne($result, $hosts, 'parentHost');
		}

		if ($options['selectTemplates'] !== null) {
			if ($options['selectTemplates'] != API_OUTPUT_COUNT) {
				$templates = [];
				$relationMap = $this->createRelationMap($result, 'hostid', 'templateid', 'hosts_templates');
				$related_ids = $relationMap->getRelatedIds();

				if ($related_ids) {
					$templates = API::Template()->get([
						'output' => $options['selectTemplates'],
						'templateids' => $related_ids,
						'preservekeys' => true
					]);
				}

				$result = $relationMap->mapMany($result, $templates, 'templates');
			}
			else {
				$templates = API::Template()->get([
					'hostids' => $hostids,
					'countOutput' => true,
					'groupCount' => true
				]);
				$templates = zbx_toHash($templates, 'hostid');
				foreach ($result as $hostid => $host) {
					$result[$hostid]['templates'] = array_key_exists($hostid, $templates)
						? $templates[$hostid]['rowscount']
						: '0';
				}
			}
		}

		if ($options['selectTags'] !== null) {
			foreach ($result as &$row) {
				$row['tags'] = [];
			}
			unset($row);

			if ($options['selectTags'] === API_OUTPUT_EXTEND) {
				$output = ['hosttagid', 'hostid', 'tag', 'value'];
			}
			else {
				$output = array_unique(array_merge(['hosttagid', 'hostid'], $options['selectTags']));
			}

			$sql_options = [
				'output' => $output,
				'filter' => ['hostid' => $hostids]
			];
			$db_tags = DBselect(DB::makeSql('host_tag', $sql_options));

			while ($db_tag = DBfetch($db_tags)) {
				$hostid = $db_tag['hostid'];

				unset($db_tag['hosttagid'], $db_tag['hostid']);

				$result[$hostid]['tags'][] = $db_tag;
			}
		}

		if ($options['selectInterfaces'] !== null) {
			$interfaces = API::HostInterface()->get([
				'output' => $this->outputExtend($options['selectInterfaces'], ['hostid', 'interfaceid']),
				'hostids' => $hostids,
				'sortfield' => 'interfaceid',
				'nopermissions' => true,
				'preservekeys' => true
			]);

			foreach (array_keys($result) as $hostid) {
				$result[$hostid]['interfaces'] = [];
			}

			foreach ($interfaces as $interface) {
				$hostid = $interface['hostid'];
				unset($interface['hostid'], $interface['interfaceid']);
				$result[$hostid]['interfaces'][] = $interface;
			}
		}

		return $result;
	}

	/**
	 * @param array $options
	 * @param array $result
	 */
	private static function addRelatedGroupLinks(array $options, array &$result): void {
		if ($options['selectGroupLinks'] === null) {
			return;
		}

		foreach ($result as &$host_prototype) {
			$host_prototype['groupLinks'] = [];
		}
		unset($host_prototype);

		if ($options['selectGroupLinks'] === API_OUTPUT_EXTEND) {
			$output = ['hostid', 'groupid'];
		}
		else {
			$output = array_unique(array_merge(['hostid'], $options['selectGroupLinks']));
		}

		foreach ($output as &$field_name) {
			$field_name = 'gp.'.$field_name;
		}
		unset($field_name);

		$db_group_prototypes = DBselect(
			'SELECT '.implode(',', $output).
			' FROM group_prototype gp'.
			' WHERE '.dbConditionId('gp.hostid', array_keys($result)).
				' AND gp.groupid IS NOT NULL'
		);

		while ($db_group_prototype = DBfetch($db_group_prototypes)) {
			$hostid = $db_group_prototype['hostid'];

			unset($db_group_prototype['hostid']);

			$result[$hostid]['groupLinks'][] = $db_group_prototype;
		}
	}

	/**
	 * @param array $options
	 * @param array $result
	 */
	private static function addRelatedGroupPrototypes(array $options, array &$result): void {
		if ($options['selectGroupPrototypes'] === null) {
			return;
		}

		foreach ($result as &$host_prototype) {
			$host_prototype['groupPrototypes'] = [];
		}
		unset($host_prototype);

		if ($options['selectGroupPrototypes'] === API_OUTPUT_EXTEND) {
			$output = ['group_prototypeid', 'hostid', 'name'];
		}
		else {
			$output = array_unique(array_merge(['hostid'], $options['selectGroupPrototypes']));
		}

		foreach ($output as &$field_name) {
			$field_name = 'gp.'.$field_name;
		}
		unset($field_name);

		$db_group_prototypes = DBselect(
			'SELECT '.implode(',', $output).
			' FROM group_prototype gp'.
			' WHERE '.dbConditionId('gp.hostid', array_keys($result)).
				' AND gp.groupid IS NULL'
		);

		while ($db_group_prototype = DBfetch($db_group_prototypes)) {
			$hostid = $db_group_prototype['hostid'];

			unset($db_group_prototype['hostid']);

			$result[$hostid]['groupPrototypes'][] = $db_group_prototype;
		}
	}

	/**
	 * @param array $hosts
	 *
	 * @return array
	 */
	public function create(array $hosts): array {
		$this->validateCreate($hosts);

		$this->createForce($hosts);
		$this->inherit($hosts);

		return ['hostids' => array_column($hosts, 'hostid')];
	}

	/**
	 * @param array $hosts
	 *
	 * @throws APIException
	 */
	private function validateCreate(array &$hosts): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE | API_ALLOW_UNEXPECTED, 'fields' => [
			'ruleid' =>	['type' => API_ID, 'flags' => API_REQUIRED]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $hosts, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		self::checkDiscoveryRules($hosts, $db_lld_rules);
		self::addHostStatus($hosts, $db_lld_rules);

		$api_input_rules = ['type' => API_OBJECTS, 'uniq' => [['uuid'], ['ruleid', 'host'], ['ruleid', 'name']], 'fields' => [
			'host_status' =>		['type' => API_ANY],
			'uuid' =>				['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'host_status', 'in' => HOST_STATUS_TEMPLATE], 'type' => API_UUID],
										['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('hosts', 'uuid'), 'unset' => true]
			]],
			'ruleid' =>				['type' => API_ID, 'flags' => API_REQUIRED],
			'host' =>				['type' => API_H_NAME, 'flags' => API_REQUIRED | API_REQUIRED_LLD_MACRO, 'length' => DB::getFieldLength('hosts', 'host')],
			'name' =>				['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('hosts', 'name'), 'default_source' => 'host'],
			'custom_interfaces' =>	['type' => API_INT32, 'in' => implode(',', [HOST_PROT_INTERFACES_INHERIT, HOST_PROT_INTERFACES_CUSTOM]), 'default' => DB::getDefault('hosts', 'custom_interfaces')],
			'status' =>				['type' => API_INT32, 'in' => implode(',', [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED])],
			'discover' =>			['type' => API_INT32, 'in' => implode(',', [ZBX_PROTOTYPE_DISCOVER, ZBX_PROTOTYPE_NO_DISCOVER])],
			'interfaces' =>			self::getInterfacesValidationRules(),
			'groupLinks' =>			['type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'uniq' => [['groupid']], 'fields' => [
				'groupid' =>			['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'groupPrototypes' =>	['type' => API_OBJECTS, 'uniq' => [['name']], 'fields' => self::getGroupPrototypeValidationFields()],
			'templates' =>			['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['templateid']], 'fields' => [
				'templateid' =>			['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'tags' =>				['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['tag', 'value']], 'fields' => [
				'tag' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('host_tag', 'tag')],
				'value' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('host_tag', 'value'), 'default' => DB::getDefault('host_tag', 'value')]
			]],
			'macros' =>				['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['macro']], 'fields' => [
				'macro' =>				['type' => API_USER_MACRO, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('hostmacro', 'macro')],
				'type' =>				['type' => API_INT32, 'in' => implode(',', [ZBX_MACRO_TYPE_TEXT, ZBX_MACRO_TYPE_SECRET, ZBX_MACRO_TYPE_VAULT]), 'default' => ZBX_MACRO_TYPE_TEXT],
				'value' =>				['type' => API_MULTIPLE, 'flags' => API_REQUIRED, 'rules' => [
											['if' => ['field' => 'type', 'in' => implode(',', [ZBX_MACRO_TYPE_VAULT])], 'type' => API_VAULT_SECRET, 'provider' => CSettingsHelper::get(CSettingsHelper::VAULT_PROVIDER), 'length' => DB::getFieldLength('hostmacro', 'value')],
											['else' => true, 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hostmacro', 'value')]
				]],
				'description' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hostmacro', 'description')]
			]],
			'inventory_mode' =>		['type' => API_INT32, 'in' => implode(',', [HOST_INVENTORY_DISABLED, HOST_INVENTORY_MANUAL, HOST_INVENTORY_AUTOMATIC])]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $hosts, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		self::addUuid($hosts);

		self::checkUuidDuplicates($hosts);
		self::checkDuplicates($hosts);
		self::checkMainInterfaces($hosts);
		self::checkGroupLinks($hosts);
		$this->checkTemplates($hosts);
	}

	/**
	 * @param array $hosts
	 * @param bool  $inherited
	 */
	protected function createForce(array &$hosts, bool $inherited = false): void {
		self::addFlags($hosts, ZBX_FLAG_DISCOVERY_PROTOTYPE);

		$hostids = DB::insert('hosts', $hosts);

		$host_statuses = [];

		foreach ($hosts as &$host) {
			$host['hostid'] = array_shift($hostids);

			$host_statuses[] = $host['host_status'];
			unset($host['host_status'], $host['flags']);
		}
		unset($host);

		if (!$inherited) {
			$this->checkTemplatesLinks($hosts);
		}

		self::createHostDiscoveries($hosts);

		self::updateInterfaces($hosts);
		self::updateGroupLinks($hosts);
		self::updateGroupPrototypes($hosts);
		$this->updateTemplates($hosts);
		$this->updateTags($hosts);
		$this->updateMacros($hosts);
		self::updateHostInventories($hosts);

		self::addAuditLog(CAudit::ACTION_ADD, CAudit::RESOURCE_HOST_PROTOTYPE, $hosts);

		foreach ($hosts as &$host) {
			$host['host_status'] = array_shift($host_statuses);
		}
		unset($host);
	}

	/**
	 * @param array $hosts
	 *
	 * @return array
	 */
	public function update(array $hosts): array {
		$this->validateUpdate($hosts, $db_hosts);

		$hostids = array_column($hosts, 'hostid');

		$this->updateForce($hosts, $db_hosts);
		$this->inherit($hosts, $db_hosts);

		return ['hostids' => $hostids];
	}

	/**
	 * @param array      $hosts
	 * @param array|null $db_hosts
	 *
	 * @throws APIException
	 */
	protected function validateUpdate(array &$hosts, ?array &$db_hosts = null): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE | API_ALLOW_UNEXPECTED, 'uniq' => [['hostid']], 'fields' => [
			'hostid' =>				['type' => API_ID, 'flags' => API_REQUIRED],
			'groupPrototypes' =>	['type' => API_OBJECTS, 'flags' => API_NORMALIZE | API_ALLOW_UNEXPECTED, 'uniq' => [['group_prototypeid']], 'fields' => [
				'group_prototypeid' =>	['type' => API_ID]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $hosts, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_hosts = $this->get([
			'output' => ['uuid', 'hostid', 'host', 'name', 'custom_interfaces', 'status', 'discover', 'inventory_mode'],
			'hostids' => array_column($hosts, 'hostid'),
			'editable' => true,
			'preservekeys' => true
		]);

		if (count($db_hosts) != count($hosts)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		self::addInternalFields($db_hosts);
		self::addAffectedGroupPrototypes($hosts, $db_hosts);

		foreach ($hosts as $i => &$host) {
			$db_host = $db_hosts[$host['hostid']];
			$host['host_status'] = $db_host['host_status'];

			if ($db_host['templateid'] == 0) {
				$host += array_intersect_key($db_host, array_flip(['custom_interfaces']));

				$api_input_rules = self::getValidationRules();
			}
			else {
				$api_input_rules = self::getInheritedValidationRules();
			}

			$path = '/'.($i + 1);

			if (!CApiInputValidator::validate($api_input_rules, $host, $path, $error)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, $error);
			}

			self::validateGroupPrototypes($host, $db_host, $path.'/groupPrototypes');
		}
		unset($host);

		$hosts = $this->extendObjectsByKey($hosts, $db_hosts, 'hostid', ['custom_interfaces', 'ruleid']);

		self::validateUniqueness($hosts);

		$this->addAffectedObjects($hosts, $db_hosts);

		self::checkUuidDuplicates($hosts, $db_hosts);
		self::checkDuplicates($hosts, $db_hosts);
		self::checkMainInterfaces($hosts);
		self::checkGroupLinks($hosts, $db_hosts);
		$this->checkTemplates($hosts, $db_hosts);
		$this->checkTemplatesLinks($hosts, $db_hosts);
		$hosts = parent::validateHostMacros($hosts, $db_hosts);
	}

	/**
	 * Add the internally used fields to the given $db_hosts.
	 *
	 * @param array $db_hosts
	 */
	private static function addInternalFields(array &$db_hosts): void {
		$result = DBselect(
			'SELECT h.hostid,h.templateid,hd.parent_itemid AS ruleid,hh.status AS host_status'.
			' FROM hosts h,host_discovery hd,items i,hosts hh'.
			' WHERE h.hostid=hd.hostid'.
				' AND hd.parent_itemid=i.itemid'.
				' AND i.hostid=hh.hostid'.
				' AND '.dbConditionId('h.hostid', array_keys($db_hosts))
		);

		while ($row = DBfetch($result)) {
			$db_hosts[$row['hostid']] += $row;
		}
	}

	/**
	 * @return array
	 */
	private static function getValidationRules(): array {
		return ['type' => API_OBJECT, 'fields' => [
			'host_status' =>		['type' => API_ANY],
			'uuid' =>				['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'host_status', 'in' => HOST_STATUS_TEMPLATE], 'type' => API_UUID],
										['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('hosts', 'uuid'), 'unset' => true]
			]],
			'hostid' =>				['type' => API_ANY],
			'host' =>				['type' => API_H_NAME, 'flags' => API_REQUIRED_LLD_MACRO, 'length' => DB::getFieldLength('hosts', 'host')],
			'name' =>				['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('hosts', 'name')],
			'custom_interfaces' =>	['type' => API_INT32, 'in' => implode(',', [HOST_PROT_INTERFACES_INHERIT, HOST_PROT_INTERFACES_CUSTOM])],
			'status' =>				['type' => API_INT32, 'in' => implode(',', [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED])],
			'discover' =>			['type' => API_INT32, 'in' => implode(',', [ZBX_PROTOTYPE_DISCOVER, ZBX_PROTOTYPE_NO_DISCOVER])],
			'interfaces' =>			self::getInterfacesValidationRules(),
			'groupLinks' =>			['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY, 'uniq' => [['groupid']], 'fields' => [
				'groupid' =>			['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'groupPrototypes' =>	['type' => API_ANY],
			'templates' =>			['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['templateid']], 'fields' => [
				'templateid' =>			['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'tags' =>				['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['tag', 'value']], 'fields' => [
				'tag' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('host_tag', 'tag')],
				'value' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('host_tag', 'value'), 'default' => DB::getDefault('host_tag', 'value')]
			]],
			'macros' =>				['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['hostmacroid']], 'fields' => [
				'hostmacroid' =>		['type' => API_ID],
				'macro' =>				['type' => API_USER_MACRO, 'length' => DB::getFieldLength('hostmacro', 'macro')],
				'type' =>				['type' => API_INT32, 'in' => implode(',', [ZBX_MACRO_TYPE_TEXT, ZBX_MACRO_TYPE_SECRET, ZBX_MACRO_TYPE_VAULT])],
				'value' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hostmacro', 'value')],
				'description' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hostmacro', 'description')]
			]],
			'inventory_mode' =>		['type' => API_INT32, 'in' => implode(',', [HOST_INVENTORY_DISABLED, HOST_INVENTORY_MANUAL, HOST_INVENTORY_AUTOMATIC])]
		]];
	}

	/**
	 * @return array
	 */
	private static function getInheritedValidationRules(): array {
		return ['type' => API_OBJECT, 'fields' => [
			'host_status' =>		['type' => API_ANY],
			'uuid' =>				['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED],
			'hostid' =>				['type' => API_ANY],
			'host' =>				['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED],
			'name' =>				['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED],
			'custom_interfaces' =>	['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED],
			'status' =>				['type' => API_INT32, 'in' => implode(',', [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED])],
			'discover' =>			['type' => API_INT32, 'in' => implode(',', [ZBX_PROTOTYPE_DISCOVER, ZBX_PROTOTYPE_NO_DISCOVER])],
			'interfaces' =>			['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED],
			'groupLinks' =>			['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED],
			'groupPrototypes' =>	['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED],
			'templates' =>			['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED],
			'tags' =>				['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED],
			'macros' =>				['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED],
			'inventory_mode' =>		['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED]
		]];
	}

	/**
	 * @return array
	 */
	private static function getInterfacesValidationRules(): array {
		return ['type' => API_MULTIPLE, 'rules' => [
			['if' => ['field' => 'custom_interfaces', 'in' => HOST_PROT_INTERFACES_CUSTOM], 'type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'fields' => [
				'type' =>		['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [INTERFACE_TYPE_AGENT, INTERFACE_TYPE_SNMP, INTERFACE_TYPE_IPMI, INTERFACE_TYPE_JMX])],
				'useip' =>		['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [INTERFACE_USE_DNS, INTERFACE_USE_IP])],
				'ip' =>			['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'useip', 'in' => INTERFACE_USE_IP], 'type' => API_IP, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO | API_ALLOW_MACRO, 'length' => DB::getFieldLength('interface', 'ip')],
									['else' => true, 'type' => API_IP, 'flags' => API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO | API_ALLOW_MACRO, 'length' => DB::getFieldLength('interface', 'ip')]
				]],
				'dns' =>		['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'useip', 'in' => INTERFACE_USE_DNS], 'type' => API_DNS, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO | API_ALLOW_MACRO, 'length' => DB::getFieldLength('interface', 'dns')],
									['else' => true, 'type' => API_DNS, 'flags' => API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO | API_ALLOW_MACRO, 'length' => DB::getFieldLength('interface', 'dns')]
				]],
				'port' =>		['type' => API_PORT, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO, 'length' => DB::getFieldLength('interface', 'port')],
				'main' =>		['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [INTERFACE_SECONDARY, INTERFACE_PRIMARY])],
				'details' =>	['type' => API_MULTIPLE, 'rules' => [
					['if' => ['field' => 'type', 'in' => INTERFACE_TYPE_SNMP], 'type' => API_OBJECT, 'flags' => API_REQUIRED, 'fields' => [
						'version' =>			['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [SNMP_V1, SNMP_V2C, SNMP_V3])],
						'bulk' =>				['type' => API_INT32, 'in' => implode(',', [SNMP_BULK_DISABLED, SNMP_BULK_ENABLED])],
						'community' =>			['type' => API_MULTIPLE, 'rules' => [
													['if' => ['field' => 'version', 'in' => implode(',', [SNMP_V1, SNMP_V2C])], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('interface_snmp', 'community')],
													['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('interface_snmp', 'community')]
						]],
						'max_repetitions' =>	['type' => API_MULTIPLE, 'rules' => [
													['if' => ['field' => 'version', 'in' => implode(',', [SNMP_V2C, SNMP_V3])], 'type' => API_INT32, 'in' => implode(':', [1, ZBX_MAX_INT32])],
													['else' => true, 'type' => API_INT32, 'in' => DB::getDefault('interface_snmp', 'max_repetitions')]
						]],
						'contextname' =>		['type' => API_MULTIPLE, 'rules' => [
													['if' => ['field' => 'version', 'in' => SNMP_V3], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('interface_snmp', 'contextname')],
													['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('interface_snmp', 'contextname')]
						]],
						'securityname' =>		['type' => API_MULTIPLE, 'rules' => [
													['if' => ['field' => 'version', 'in' => SNMP_V3], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('interface_snmp', 'securityname')],
													['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('interface_snmp', 'securityname')]
						]],
						'securitylevel' =>		['type' => API_MULTIPLE, 'rules' => [
													['if' => ['field' => 'version', 'in' => SNMP_V3], 'type' => API_INT32, 'in' => implode(',', [ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV, ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV, ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV]), 'default' => DB::getDefault('interface_snmp', 'securitylevel')],
													['else' => true, 'type' => API_INT32, 'in' => DB::getDefault('interface_snmp', 'securitylevel')]
						]],
						'authprotocol' =>		['type' => API_MULTIPLE, 'rules' => [
													['if' => ['field' => 'version', 'in' => SNMP_V3], 'type' => API_MULTIPLE, 'rules' => [
														['if' => ['field' => 'securitylevel', 'in' => implode(',', [ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV, ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV])], 'type' => API_INT32, 'in' => implode(',', array_keys(getSnmpV3AuthProtocols()))],
														['else' => true, 'type' => API_INT32, 'in' => DB::getDefault('interface_snmp', 'authprotocol')]
													]],
													['else' => true, 'type' => API_INT32, 'in' => DB::getDefault('interface_snmp', 'authprotocol')]
						]],
						'authpassphrase' =>		['type' => API_MULTIPLE, 'rules' => [
													['if' => ['field' => 'version', 'in' => SNMP_V3], 'type' => API_MULTIPLE, 'rules' => [
														['if' => ['field' => 'securitylevel', 'in' => implode(',', [ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV, ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('interface_snmp', 'authpassphrase')],
														['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('interface_snmp', 'authpassphrase')]
													]],
													['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('interface_snmp', 'authpassphrase')]
						]],
						'privprotocol' =>		['type' => API_MULTIPLE, 'rules' => [
													['if' => ['field' => 'version', 'in' => SNMP_V3], 'type' => API_MULTIPLE, 'rules' => [
														['if' => ['field' => 'securitylevel', 'in' => ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV], 'type' => API_INT32, 'in' => implode(',', array_keys(getSnmpV3PrivProtocols()))],
														['else' => true, 'type' => API_INT32, 'in' => DB::getDefault('interface_snmp', 'privprotocol')]
													]],
													['else' => true, 'type' => API_INT32, 'in' => DB::getDefault('interface_snmp', 'privprotocol')]
						]],
						'privpassphrase' =>		['type' => API_MULTIPLE, 'rules' => [
													['if' => ['field' => 'version', 'in' => SNMP_V3], 'type' => API_MULTIPLE, 'rules' => [
														['if' => ['field' => 'securitylevel', 'in' => ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('interface_snmp', 'privpassphrase')],
														['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('interface_snmp', 'privpassphrase')]
													]],
													['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('interface_snmp', 'privpassphrase')]
						]]
					]],
					['else' => true, 'type' => API_OBJECT, 'fields' => []]
				]]
			]],
			['else' => true, 'type' => API_OBJECTS, 'length' => 0]
		]];
	}

	/**
	 * @param bool $is_update
	 */
	private static function getGroupPrototypeValidationFields(bool $is_update = false): array {
		$api_required = $is_update ? 0 : API_REQUIRED;

		return ($is_update ? ['group_prototypeid' =>	['type' => API_ANY]] : []) + [
			'name' =>	['type' => API_HG_NAME, 'flags' => $api_required | API_REQUIRED_LLD_MACRO, 'length' => DB::getFieldLength('group_prototype', 'name')]
		];
	}

	/**
	 * @param array  $host
	 * @param array  $db_host
	 * @param string $path
	 *
	 * @throws APIException
	 */
	private static function validateGroupPrototypes(array &$host, array $db_host, string $path): void {
		if (!array_key_exists('groupPrototypes', $host)) {
			return;
		}

		foreach ($host['groupPrototypes'] as $i => &$group_prototype) {
			if (array_key_exists('group_prototypeid', $group_prototype)) {
				if (!array_key_exists($group_prototype['group_prototypeid'], $db_host['groupPrototypes'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.', $path.'/'.($i + 1),
						_('object does not exist or belongs to another object')
					));
				}

				$db_group_prototype = $db_host['groupPrototypes'][$group_prototype['group_prototypeid']];

				$group_prototype += array_intersect_key($db_group_prototype, array_flip(['name']));

				$api_input_rules = ['type' => API_OBJECT, 'fields' => self::getGroupPrototypeValidationFields(true)];
			}
			else {
				$api_input_rules = ['type' => API_OBJECT, 'fields' => self::getGroupPrototypeValidationFields()];
			}

			if (!CApiInputValidator::validate($api_input_rules, $group_prototype, $path.'/'.($i + 1), $error)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, $error);
			}
		}
		unset($group_prototype);

		$api_input_rules = ['type' => API_OBJECTS, 'uniq' => [['name']], 'fields' => [
			'name' =>	['type' => API_HG_NAME]
		]];

		if (!CApiInputValidator::validateUniqueness($api_input_rules, $host['groupPrototypes'], $path, $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}
	}

	/**
	 * @param array $hosts
	 *
	 * @throws APIException
	 */
	private static function validateUniqueness(array &$hosts): void {
		$api_input_rules = ['type' => API_OBJECTS, 'uniq' => [['uuid'], ['ruleid', 'host'], ['ruleid', 'name']], 'fields' => [
			'uuid' =>	['type' => API_ANY],
			'ruleid' =>	['type' => API_ANY],
			'host' =>	['type' => API_ANY],
			'name' =>	['type' => API_ANY]
		]];

		if (!CApiInputValidator::validateUniqueness($api_input_rules, $hosts, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}
	}

	/**
	 * @param array $hosts
	 * @param array $db_hosts
	 */
	private function updateForce(array &$hosts, array &$db_hosts): void {
		$upd_hosts = [];
		$upd_hostids = [];

		$internal_fields = array_flip(['hostid', 'custom_interfaces', 'ruleid']);
		$inventory_fields = array_flip(['inventory_mode']);
		$nested_object_fields = array_flip(
			['interfaces', 'groupLinks', 'groupPrototypes', 'templates', 'tags', 'macros']
		);

		foreach ($hosts as $i => &$host) {
			$upd_host = DB::getUpdatedValues('hosts', $host, $db_hosts[$host['hostid']]);

			if ($upd_host) {
				$upd_hosts[] = [
					'values' => $upd_host,
					'where' => ['hostid' => $host['hostid']]
				];

				$upd_hostids[$i] = $host['hostid'];
			}

			$host = array_intersect_key($host,
				$internal_fields + $upd_host + $nested_object_fields + $inventory_fields
			);
		}
		unset($host);

		if ($upd_hosts) {
			DB::update('hosts', $upd_hosts);
		}

		self::updateInterfaces($hosts, $db_hosts, $upd_hostids);
		self::updateGroupLinks($hosts, $db_hosts, $upd_hostids);
		self::updateGroupPrototypes($hosts, $db_hosts, $upd_hostids);
		$this->updateTemplates($hosts, $db_hosts, $upd_hostids);
		$this->updateTags($hosts, $db_hosts, $upd_hostids);
		$this->updateMacros($hosts, $db_hosts, $upd_hostids);
		self::updateHostInventories($hosts, $db_hosts, $upd_hostids);

		$hosts = array_intersect_key($hosts, $upd_hostids);
		$db_hosts = array_intersect_key($db_hosts, array_flip($upd_hostids));

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_HOST_PROTOTYPE, $hosts, $db_hosts);
	}

	/**
	 * @param array $hosts
	 * @param array $db_hosts
	 */
	protected function addAffectedObjects(array $hosts, array &$db_hosts): void {
		self::addAffectedInterfaces($hosts, $db_hosts);
		self::addAffectedGroupLinks($hosts, $db_hosts);
		self::addAffectedGroupPrototypes($hosts, $db_hosts);
		parent::addAffectedObjects($hosts, $db_hosts);
	}

	/**
	 * @param array $hosts
	 * @param array $db_hosts
	 */
	private static function addAffectedInterfaces(array $hosts, array &$db_hosts): void {
		$hostids = [];

		foreach ($hosts as $host) {
			if (!array_key_exists('custom_interfaces', $host)) {
				continue;
			}

			$db_custom_interfaces = $db_hosts[$host['hostid']]['custom_interfaces'];

			if ((array_key_exists('interfaces', $host) && $host['custom_interfaces'] == HOST_PROT_INTERFACES_CUSTOM)
					|| ($host['custom_interfaces'] != $db_custom_interfaces
						&& $db_custom_interfaces == HOST_PROT_INTERFACES_CUSTOM)) {
				$hostids[] = $host['hostid'];
				$db_hosts[$host['hostid']]['interfaces'] = [];
			}
			elseif (array_key_exists('interfaces', $host)) {
				$db_hosts[$host['hostid']]['interfaces'] = [];
			}
		}

		if (!$hostids) {
			return;
		}

		$details_interfaces = [];
		$options = [
			'output' => ['interfaceid', 'hostid', 'main', 'type', 'useip', 'ip', 'dns', 'port'],
			'filter' => ['hostid' => $hostids]
		];
		$db_interfaces = DBselect(DB::makeSql('interface', $options));

		while ($db_interface = DBfetch($db_interfaces)) {
			$db_hosts[$db_interface['hostid']]['interfaces'][$db_interface['interfaceid']] =
				array_diff_key($db_interface, array_flip(['hostid'])) + ['details' => []];

			if ($db_interface['type'] == INTERFACE_TYPE_SNMP) {
				$details_interfaces[$db_interface['interfaceid']] = $db_interface['hostid'];
			}
		}

		if ($details_interfaces) {
			$options = [
				'output' => ['interfaceid', 'version', 'bulk', 'community', 'securityname', 'securitylevel',
					'authpassphrase', 'privpassphrase', 'authprotocol', 'privprotocol', 'contextname', 'max_repetitions'
				],
				'filter' => ['interfaceid' => array_keys($details_interfaces)]
			];
			$result = DBselect(DB::makeSql('interface_snmp', $options));

			while ($db_details = DBfetch($result)) {
				$hostid = $details_interfaces[$db_details['interfaceid']];
				$db_hosts[$hostid]['interfaces'][$db_details['interfaceid']]['details'] =
					array_diff_key($db_details, array_flip(['interfaceid']));
			}
		}
	}

	/**
	 * @param array $hosts
	 * @param array $db_hosts
	 */
	private static function addAffectedGroupLinks(array $hosts, array &$db_hosts): void {
		$hostids = [];

		foreach ($hosts as $host) {
			if (array_key_exists('groupLinks', $host)) {
				$hostids[] = $host['hostid'];
				$db_hosts[$host['hostid']]['groupLinks'] = [];
			}
		}

		if (!$hostids) {
			return;
		}

		$db_links = DBselect(
			'SELECT gp.group_prototypeid,gp.hostid,gp.groupid,gp.templateid'.
			' FROM group_prototype gp'.
			' WHERE '.dbConditionId('gp.hostid', $hostids).
				' AND gp.groupid IS NOT NULL'
		);

		while ($db_link = DBfetch($db_links)) {
			$db_hosts[$db_link['hostid']]['groupLinks'][$db_link['group_prototypeid']] =
				array_diff_key($db_link, array_flip(['hostid']));
		}
	}

	/**
	 * @param array $hosts
	 * @param array $db_hosts
	 */
	private static function addAffectedGroupPrototypes(array $hosts, array &$db_hosts): void {
		$hostids = [];

		foreach ($hosts as $host) {
			if (array_key_exists('groupPrototypes', $host)
					&& !array_key_exists('groupPrototypes', $db_hosts[$host['hostid']])) {
				$hostids[] = $host['hostid'];
				$db_hosts[$host['hostid']]['groupPrototypes'] = [];
			}
		}

		if (!$hostids) {
			return;
		}

		$db_links = DBselect(
			'SELECT gp.group_prototypeid,gp.hostid,gp.name,gp.templateid'.
			' FROM group_prototype gp'.
			' WHERE '.dbConditionId('gp.hostid', $hostids).
				' AND gp.groupid IS NULL'
		);

		while ($db_link = DBfetch($db_links)) {
			$db_hosts[$db_link['hostid']]['groupPrototypes'][$db_link['group_prototypeid']] =
				array_diff_key($db_link, array_flip(['hostid']));
		}
	}

	/**
	 * Check for unique host prototype names per LLD rule.
	 *
	 * @param array      $hosts
	 * @param array|null $db_hosts
	 * @param bool       $inherited
	 *
	 * @throws APIException
	 */
	private static function checkDuplicates(array $hosts, ?array $db_hosts = null, bool $inherited = false): void {
		$h_names = [];
		$v_names = [];

		foreach ($hosts as $host) {
			if (array_key_exists('host', $host)) {
				if ($db_hosts === null
						|| $host['host'] !== $db_hosts[$host['hostid']]['host']) {
					$h_names[$host['ruleid']][] = $host['host'];
				}
			}

			if (array_key_exists('name', $host)) {
				if ($db_hosts === null
						|| $host['name'] !== $db_hosts[$host['hostid']]['name']) {
					$v_names[$host['ruleid']][] = $host['name'];
				}
			}
		}

		if ($h_names) {
			$where = [];
			foreach ($h_names as $ruleid => $names) {
				$where[] = '('.dbConditionId('i.itemid', [$ruleid]).' AND '.dbConditionString('h.host', $names).')';
			}

			if (!$inherited) {
				$duplicates = DBfetchArray(DBselect(
					'SELECT i.name AS rule,h.host'.
					' FROM items i,host_discovery hd,hosts h'.
					' WHERE i.itemid=hd.parent_itemid'.
						' AND hd.hostid=h.hostid'.
						' AND ('.implode(' OR ', $where).')',
					1
				));

				if ($duplicates) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s(
						'Host prototype with host name "%1$s" already exists in discovery rule "%2$s".',
						$duplicates[0]['host'], $duplicates[0]['rule']
					));
				}
			}
			else {
				$duplicates = DBfetchArray(DBselect(
					'SELECT i.name AS rule,h.host,hh.host AS parent_host,hh.status'.
					' FROM items i,host_discovery hd,hosts h,hosts hh'.
					' WHERE i.itemid=hd.parent_itemid'.
						' AND hd.hostid=h.hostid'.
						' AND i.hostid=hh.hostid'.
						' AND ('.implode(' OR ', $where).')',
					1
				));

				if ($duplicates) {
					if ($duplicates[0]['status'] == HOST_STATUS_TEMPLATE) {
						$error = _('Host prototype with host name "%1$s" already exists in discovery rule "%2$s" of template "%3$s".');
					}
					else {
						$error = _('Host prototype with host name "%1$s" already exists in discovery rule "%2$s" of host "%3$s".');
					}

					self::exception(ZBX_API_ERROR_PARAMETERS, sprintf($error, $duplicates[0]['host'],
						$duplicates[0]['rule'], $duplicates[0]['parent_host']
					));
				}
			}
		}

		if ($v_names) {
			$where = [];
			foreach ($v_names as $ruleid => $names) {
				$where[] = '('.dbConditionId('i.itemid', [$ruleid]).' AND '.dbConditionString('h.name', $names).')';
			}

			if (!$inherited) {
				$duplicates = DBfetchArray(DBselect(
					'SELECT i.name AS rule,h.name'.
					' FROM items i,host_discovery hd,hosts h'.
					' WHERE i.itemid=hd.parent_itemid'.
						' AND hd.hostid=h.hostid'.
						' AND ('.implode(' OR ', $where).')',
					1
				));

				if ($duplicates) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s(
						'Host prototype with visible name "%1$s" already exists in discovery rule "%2$s".',
						$duplicates[0]['name'], $duplicates[0]['rule']
					));
				}
			}
			else {
				$duplicates = DBfetchArray(DBselect(
					'SELECT i.name AS rule,h.name,hh.host AS parent_host,hh.status'.
					' FROM items i,host_discovery hd,hosts h,hosts hh'.
					' WHERE i.itemid=hd.parent_itemid'.
						' AND hd.hostid=h.hostid'.
						' AND i.hostid=hh.hostid'.
						' AND ('.implode(' OR ', $where).')',
					1
				));

				if ($duplicates) {
					if ($duplicates[0]['status'] == HOST_STATUS_TEMPLATE) {
						$error = _('Host prototype with visible name "%1$s" already exists in discovery rule "%2$s" of template "%3$s".');
					}
					else {
						$error = _('Host prototype with visible name "%1$s" already exists in discovery rule "%2$s" of host "%3$s".');
					}

					self::exception(ZBX_API_ERROR_PARAMETERS, sprintf($error, $duplicates[0]['name'],
						$duplicates[0]['rule'], $duplicates[0]['parent_host']
					));
				}
			}
		}
	}

	/**
	 * Add the UUID to those of the given host prototypes that belong to a template and don't have the 'uuid' parameter
	 * set.
	 *
	 * @param array $hosts
	 */
	private static function addUuid(array &$hosts): void {
		foreach ($hosts as &$host) {
			if ($host['host_status'] == HOST_STATUS_TEMPLATE && !array_key_exists('uuid', $host)) {
				$host['uuid'] = generateUuidV4();
			}
		}
		unset($host);
	}

	/**
	 * Verify host prototype UUIDs are not repeated.
	 *
	 * @param array      $hosts
	 * @param array|null $db_hosts
	 *
	 * @throws APIException
	 */
	private static function checkUuidDuplicates(array $hosts, ?array $db_hosts = null): void {
		$host_indexes = [];

		foreach ($hosts as $i => $host) {
			if (!array_key_exists('uuid', $host)) {
				continue;
			}

			if ($db_hosts === null || $host['uuid'] !== $db_hosts[$host['hostid']]['uuid']) {
				$host_indexes[$host['uuid']] = $i;
			}
		}

		if (!$host_indexes) {
			return;
		}

		$duplicates = DB::select('hosts', [
			'output' => ['uuid'],
			'filter' => [
				'flags' => ZBX_FLAG_DISCOVERY_PROTOTYPE,
				'uuid' => array_keys($host_indexes)
			],
			'limit' => 1
		]);

		if ($duplicates) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Invalid parameter "%1$s": %2$s.', '/'.($host_indexes[$duplicates[0]['uuid']] + 1),
					_('host prototype with the same UUID already exists')
				)
			);
		}
	}

	/**
	 * @param array      $hosts
	 * @param array|null $db_lld_rules
	 *
	 * @throws APIException
	 */
	private static function checkDiscoveryRules(array $hosts, ?array &$db_lld_rules = null): void {
		$ruleids = array_unique(array_column($hosts, 'ruleid'));

		$count = API::DiscoveryRule()->get([
			'countOutput' => true,
			'itemids' => $ruleids,
			'editable' => true
		]);

		if ($count != count($ruleids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		$result = DBselect(
			'SELECT i.itemid,i.hostid,h.status,h.flags'.
			' FROM items i,hosts h'.
			' WHERE i.hostid=h.hostid'.
				' AND '.dbConditionId('i.itemid', $ruleids)
		);

		$db_lld_rules = [];

		while ($row = DBfetch($result)) {
			if ($row['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
				$parent_hosts = DB::select('hosts', [
					'output' => ['host'],
					'hostids' => $row['hostid']
				]);

				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Cannot create a host prototype on a discovered host "%1$s".', $parent_hosts[0]['host'])
				);
			}

			$db_lld_rules[$row['itemid']] = ['host_status' => $row['status']];
		}
	}


	/**
	 * Add host_status property to given host prototypes based on given LLD rules.
	 *
	 * @param array $items
	 * @param array $db_lld_rules
	 */
	private static function addHostStatus(array &$hosts, array $db_lld_rules): void {
		foreach ($hosts as &$host) {
			$host['host_status'] = $db_lld_rules[$host['ruleid']]['host_status'];
		}
		unset($host);
	}

	/**
	 * Assign given flags value to the flags property of given host prototypes.
	 *
	 * @param array $hosts
	 * @param int   $flags
	 */
	private static function addFlags(array &$hosts, int $flags): void {
		foreach ($hosts as &$host) {
			$host['flags'] = $flags;
		}
		unset($host);
	}

	/**
	 * Check if host groups links are valid.
	 *
	 * @param array      $hosts
	 * @param array|null $db_hosts
	 *
	 * @throws APIException
	 */
	private static function checkGroupLinks(array $hosts, ?array $db_hosts = null): void {
		$edit_groupids = [];

		foreach ($hosts as $host) {
			if (!array_key_exists('groupLinks', $host)) {
				continue;
			}

			$groupids = array_column($host['groupLinks'], 'groupid');

			if ($db_hosts === null) {
				$edit_groupids += array_flip($groupids);
			}
			else {
				$db_groupids = array_column($db_hosts[$host['hostid']]['groupLinks'], 'groupid');

				$ins_groupids = array_flip(array_diff($groupids, $db_groupids));
				$del_groupids = array_flip(array_diff($db_groupids, $groupids));

				$edit_groupids += $ins_groupids + $del_groupids;
			}
		}

		if (!$edit_groupids) {
			return;
		}

		$db_groups = API::HostGroup()->get([
			'output' => ['name', 'flags'],
			'groupids' => array_keys($edit_groupids),
			'editable' => true,
			'preservekeys' => true
		]);

		if (count($db_groups) != count($edit_groupids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		// Check if group prototypes use discovered host groups.
		foreach ($db_groups as $db_group) {
			if ($db_group['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Group prototype cannot be based on a discovered host group "%1$s".', $db_group['name'])
				);
			}
		}
	}

	/**
	 * Check if main interfaces are correctly set for every interface type. Each host must either have only one main
	 * interface for each interface type, or have no interface of that type at all.
	 *
	 * @param array $hosts
	 *
	 * @throws APIException if two main or no main interfaces are given.
	 */
	private static function checkMainInterfaces(array $hosts): void {
		foreach ($hosts as $i => $host) {
			if ($host['custom_interfaces'] != HOST_PROT_INTERFACES_CUSTOM
					|| !array_key_exists('interfaces', $host) || !$host['interfaces']) {
				continue;
			}

			$primary_interfaces = [];
			$path = '/'.($i + 1).'/interfaces';

			foreach ($host['interfaces'] as $interface) {
				if (!array_key_exists($interface['type'], $primary_interfaces)) {
					$primary_interfaces[$interface['type']] = 0;
				}

				if ($interface['main'] == INTERFACE_PRIMARY) {
					$primary_interfaces[$interface['type']]++;
				}

				if ($primary_interfaces[$interface['type']] > 1) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.', $path,
						_s('cannot have more than one default interface of the same type')
					));
				}
			}

			foreach ($primary_interfaces as $type => $count) {
				if ($count == 0) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.', $path,
						_s('no default interface for "%1$s" type', hostInterfaceTypeNumToName($type))
					));
				}
			}
		}
	}

	/**
	 * @param array $hosts
	 */
	private static function createHostDiscoveries(array $hosts): void {
		$host_discoveries = [];

		foreach ($hosts as $host) {
			$host_discoveries[] = [
				'hostid' => $host['hostid'],
				'parent_itemid' => $host['ruleid']
			];
		}

		if ($host_discoveries) {
			DB::insertBatch('host_discovery', $host_discoveries, false);
		}
	}

	/**
	 * @param array      $hosts
	 * @param array|null $db_hosts
	 * @param array|null $upd_hostids
	 */
	private static function updateInterfaces(array &$hosts, ?array &$db_hosts = null, ?array &$upd_hostids = null): void {
		$ins_interfaces = [];
		$del_interfaceids = [];

		foreach ($hosts as $i => &$host) {
			$update = false;

			if ($db_hosts === null) {
				if ($host['custom_interfaces'] == HOST_PROT_INTERFACES_CUSTOM && array_key_exists('interfaces', $host)
						&& $host['interfaces']) {
					$update = true;
				}
			}
			else {
				if (!array_key_exists('custom_interfaces', $db_hosts[$host['hostid']])) {
					continue;
				}

				if ($host['custom_interfaces'] == HOST_PROT_INTERFACES_CUSTOM) {
					if (array_key_exists('interfaces', $host)) {
						$update = true;
					}
				}
				elseif ($db_hosts[$host['hostid']]['custom_interfaces'] == HOST_PROT_INTERFACES_CUSTOM
						&& $db_hosts[$host['hostid']]['interfaces']) {
					$update = true;
					$host['interfaces'] = [];
				}
			}

			if (!$update) {
				continue;
			}

			$changed = false;
			$db_interfaces = ($db_hosts !== null) ? $db_hosts[$host['hostid']]['interfaces'] : [];

			foreach ($host['interfaces'] as &$interface) {
				$db_interfaceid = self::getInterfaceId($interface, $db_interfaces);

				if ($db_interfaceid !== null) {
					$interface['interfaceid'] = $db_interfaceid;
					unset($db_interfaces[$db_interfaceid]);
				}
				else {
					$ins_interfaces[] = ['hostid' => $host['hostid']] + $interface;
					$changed = true;
				}
			}
			unset($interface);

			if ($db_interfaces) {
				$del_interfaceids = array_merge($del_interfaceids, array_keys($db_interfaces));
				$changed = true;
			}

			if ($db_hosts !== null) {
				if ($changed) {
					$upd_hostids[$i] = $host['hostid'];
				}
				else {
					unset($host['interfaces'], $db_hosts[$host['hostid']]['interfaces']);
				}
			}
		}
		unset($host);

		if ($del_interfaceids) {
			DB::delete('interface_snmp', ['interfaceid' => $del_interfaceids]);
			DB::delete('interface', ['interfaceid' => $del_interfaceids]);
		}

		if ($ins_interfaces) {
			$interfaceids = DB::insert('interface', $ins_interfaces);
		}

		$ins_interfaces_snmp = [];

		foreach ($hosts as &$host) {
			if (!array_key_exists('interfaces', $host)) {
				continue;
			}

			foreach ($host['interfaces'] as &$interface) {
				if (!array_key_exists('interfaceid', $interface)) {
					$interface['interfaceid'] = array_shift($interfaceids);

					if ($interface['type'] == INTERFACE_TYPE_SNMP) {
						$ins_interfaces_snmp[] = ['interfaceid' => $interface['interfaceid']] + $interface['details'];
					}
				}
			}
			unset($interface);
		}
		unset($host);

		if ($ins_interfaces_snmp) {
			DB::insert('interface_snmp', $ins_interfaces_snmp, false);
		}
	}

	/**
	 * Get the ID of interface if all fields of given interface are equal to all fields of one of existing interfaces.
	 *
	 * @param array $interface
	 * @param array $db_interfaces
	 *
	 * @return string|null
	 */
	private static function getInterfaceId(array $interface, array $db_interfaces): ?string {
		$def_interface = array_intersect_key(DB::getDefaults('interface'), array_flip(['ip', 'dns']));
		$def_details = array_intersect_key(DB::getDefaults('interface_snmp'), array_flip(['bulk', 'community',
			'max_repetitions', 'contextname', 'securityname', 'securitylevel', 'authprotocol', 'authpassphrase',
			'privprotocol', 'privpassphrase'
		]));

		$interface += $def_interface;
		$details = array_key_exists('details', $interface) ? $interface['details'] : [];

		if ($interface['type'] == INTERFACE_TYPE_SNMP && array_key_exists('details', $interface)) {
			$details += $def_details;
		}

		foreach ($db_interfaces as $db_interface) {
			if (!DB::getUpdatedValues('interface', $interface, $db_interface)) {
				if ($interface['type'] == INTERFACE_TYPE_SNMP) {
					if (!DB::getUpdatedValues('interface_snmp', $details, $db_interface['details'])) {
						return $db_interface['interfaceid'];
					}
				}
				else {
					return $db_interface['interfaceid'];
				}
			}
		}

		return null;
	}

	/**
	 * @param array      $hosts
	 * @param array|null $db_hosts
	 * @param array|null $upd_hostids
	 */
	private static function updateGroupLinks(array &$hosts, ?array &$db_hosts = null, ?array &$upd_hostids = null): void {
		$ins_group_links = [];
		$upd_group_links = []; // Used to update templateid value upon inheritance.
		$del_group_prototypeids = [];

		foreach ($hosts as $i => &$host) {
			if (!array_key_exists('groupLinks', $host)) {
				continue;
			}

			$changed = false;
			$db_group_links = $db_hosts !== null
				? array_column($db_hosts[$host['hostid']]['groupLinks'], null, 'groupid')
				: [];

			foreach ($host['groupLinks'] as &$group_link) {
				if (array_key_exists($group_link['groupid'], $db_group_links)) {
					$group_link['group_prototypeid'] = $db_group_links[$group_link['groupid']]['group_prototypeid'];
					$upd_group_link = DB::getUpdatedValues('group_prototype', $group_link,
						$db_group_links[$group_link['groupid']]
					);

					if ($upd_group_link) {
						$upd_group_links[] = [
							'values' => $upd_group_link,
							'where' => ['group_prototypeid' => $group_link['group_prototypeid']]
						];
						$changed = true;
					}

					unset($db_group_links[$group_link['groupid']]);
				}
				else {
					$ins_group_links[] = ['hostid' => $host['hostid']] + $group_link;
					$changed = true;
				}
			}
			unset($group_link);

			if ($db_group_links) {
				$del_group_prototypeids = array_merge($del_group_prototypeids,
					array_column($db_group_links, 'group_prototypeid')
				);
				$changed = true;
			}

			if ($db_hosts !== null) {
				if ($changed) {
					$upd_hostids[$i] = $host['hostid'];
				}
				else {
					unset($host['groupLinks'], $db_hosts[$host['hostid']]['groupLinks']);
				}
			}
		}
		unset($host);

		if ($del_group_prototypeids) {
			self::deleteGroupPrototypes($del_group_prototypeids);
		}

		if ($upd_group_links) {
			DB::update('group_prototype', $upd_group_links);
		}

		if ($ins_group_links) {
			$group_prototypeids = DB::insert('group_prototype', $ins_group_links);
		}

		foreach ($hosts as &$host) {
			if (!array_key_exists('groupLinks', $host)) {
				continue;
			}

			foreach ($host['groupLinks'] as &$group_link) {
				if (!array_key_exists('group_prototypeid', $group_link)) {
					$group_link['group_prototypeid'] = array_shift($group_prototypeids);
				}
			}
			unset($group_link);
		}
		unset($host);
	}

	/**
	 * @param array      $hosts
	 * @param array|null $db_hosts
	 * @param array|null $upd_hostids
	 */
	private static function updateGroupPrototypes(array &$hosts, ?array &$db_hosts = null,
			?array &$upd_hostids = null): void {
		$ins_group_prototypes = [];
		$upd_group_prototypes = []; // Used to update templateid value upon inheritance.
		$del_group_prototypeids = [];

		foreach ($hosts as $i => &$host) {
			if (!array_key_exists('groupPrototypes', $host)) {
				continue;
			}

			$db_group_prototypes = ($db_hosts !== null) ? $db_hosts[$host['hostid']]['groupPrototypes'] : [];
			$changed = false;

			foreach ($host['groupPrototypes'] as &$group_prototype) {
				if (array_key_exists('group_prototypeid', $group_prototype)) {
					$upd_group_prototype = DB::getUpdatedValues('group_prototype', $group_prototype,
						$db_group_prototypes[$group_prototype['group_prototypeid']]
					);

					if ($upd_group_prototype) {
						$upd_group_prototypes[] = [
							'values' => $upd_group_prototype,
							'where' => ['group_prototypeid' => $group_prototype['group_prototypeid']]
						];
						$changed = true;
					}

					unset($db_group_prototypes[$group_prototype['group_prototypeid']]);
				}
				else {
					$ins_group_prototypes[] = ['hostid' => $host['hostid']] + $group_prototype;
					$changed = true;
				}
			}
			unset($group_prototype);

			if ($db_group_prototypes) {
				$del_group_prototypeids = array_merge($del_group_prototypeids, array_keys($db_group_prototypes));
				$changed = true;
			}

			if ($db_hosts !== null) {
				if ($changed) {
					$upd_hostids[$i] = $host['hostid'];
				}
				else {
					unset($host['groupPrototypes'], $db_hosts[$host['hostid']]['groupPrototypes']);
				}
			}
		}
		unset($host);

		if ($del_group_prototypeids) {
			self::deleteGroupPrototypes($del_group_prototypeids);
		}

		if ($upd_group_prototypes) {
			DB::update('group_prototype', $upd_group_prototypes);
		}

		if ($ins_group_prototypes) {
			$group_prototypeids = DB::insert('group_prototype', $ins_group_prototypes);
		}

		foreach ($hosts as &$host) {
			if (!array_key_exists('groupPrototypes', $host)) {
				continue;
			}

			foreach ($host['groupPrototypes'] as &$group_prototype) {
				if (!array_key_exists('group_prototypeid', $group_prototype)) {
					$group_prototype['group_prototypeid'] = array_shift($group_prototypeids);
				}
			}
			unset($group_prototype);
		}
		unset($host);
	}

	/**
	 * @param array      $hosts
	 * @param array|null $db_hosts
	 * @param array|null $upd_hostids
	 */
	private static function updateHostInventories(array $hosts, ?array $db_hosts = null,
			?array &$upd_hostids = null): void {
		$ins_inventories = [];
		$upd_inventories = [];
		$del_hostids = [];

		foreach ($hosts as $i => $host) {
			if (!array_key_exists('inventory_mode', $host)) {
				continue;
			}

			$db_inventory_mode = ($db_hosts !== null)
				? $db_hosts[$host['hostid']]['inventory_mode']
				: HOST_INVENTORY_DISABLED;

			if ($host['inventory_mode'] == $db_inventory_mode) {
				continue;
			}

			if ($host['inventory_mode'] == HOST_INVENTORY_DISABLED) {
				$del_hostids[] = $host['hostid'];
			}
			elseif ($db_inventory_mode != HOST_INVENTORY_DISABLED) {
				$upd_inventories = [
					'values' =>['inventory_mode' => $host['inventory_mode']],
					'where' => ['hostid' => $host['hostid']]
				];
			}
			else {
				$ins_inventories[] = [
					'hostid' => $host['hostid'],
					'inventory_mode' => $host['inventory_mode']
				];
			}

			$upd_hostids[$i] = $host['hostid'];
		}

		if ($del_hostids) {
			DB::delete('host_inventory', ['hostid' => $del_hostids]);
		}

		if ($upd_inventories) {
			DB::update('host_inventory', $upd_inventories);
		}

		if ($ins_inventories) {
			DB::insertBatch('host_inventory', $ins_inventories, false);
		}
	}

	/**
	 * @param array $ruleids
	 * @param array $hostids
	 */
	public function linkTemplateObjects(array $ruleids, array $hostids): void {
		$db_host_prototypes = $this->get([
			'output' => ['hostid', 'host', 'name', 'custom_interfaces', 'status', 'discover', 'inventory_mode'],
			'discoveryids' => $ruleids,
			'nopermissions' => true,
			'preservekeys' => true
		]);

		if (!$db_host_prototypes) {
			return;
		}

		self::addInternalFields($db_host_prototypes);

		$_host_prototypes = [];

		foreach ($db_host_prototypes as $db_host_prototype) {
			$_host_prototype = array_intersect_key($db_host_prototype, array_flip(['hostid', 'custom_interfaces']));

			if ($db_host_prototype['custom_interfaces'] == HOST_PROT_INTERFACES_CUSTOM) {
				$_host_prototype += ['interfaces' => []];
			}

			$_host_prototypes[] = $_host_prototype + [
				'groupLinks' => [],
				'groupPrototypes' => [],
				'templates' => [],
				'tags' => [],
				'macros' => []
			];
		}

		$this->addAffectedObjects($_host_prototypes, $db_host_prototypes);

		$host_prototypes = array_values($db_host_prototypes);

		foreach ($host_prototypes as &$host_prototype) {
			if (array_key_exists('interfaces', $host_prototype)) {
				$host_prototype['interfaces'] = array_values($host_prototype['interfaces']);
			}

			$host_prototype['groupLinks'] = array_values($host_prototype['groupLinks']);
			$host_prototype['groupPrototypes'] = array_values($host_prototype['groupPrototypes']);
			$host_prototype['templates'] = array_values($host_prototype['templates']);
			$host_prototype['tags'] = array_values($host_prototype['tags']);
			$host_prototype['macros'] = array_values($host_prototype['macros']);
		}
		unset($host_prototype);

		$lld_links = self::getLldLinks($ruleids, $hostids);

		$this->inherit($host_prototypes, [], $lld_links);
	}

	/**
	 * @param array $ruleids
	 */
	public function unlinkTemplateObjects(array $ruleids): void {
		$result = DBselect(
			'SELECT hd.hostid,h.host,h.uuid,h.templateid,hd.parent_itemid AS ruleid,hh.status AS host_status'.
			' FROM host_discovery hd,hosts h,items i,hosts hh'.
			' WHERE hd.hostid=h.hostid'.
				' AND hd.parent_itemid=i.itemid'.
				' AND i.hostid=hh.hostid'.
				' AND '.dbConditionId('hd.parent_itemid', $ruleids)
		);

		$hosts = [];
		$db_hosts = [];

		while ($row = DBfetch($result)) {
			$host = [
				'hostid' => $row['hostid'],
				'groupLinks' => [],
				'groupPrototypes' => [],
				'templateid' => 0,
				'ruleid' => $row['ruleid'],
				'host_status' => $row['host_status']
			];

			if ($row['host_status'] == HOST_STATUS_TEMPLATE) {
				$host += ['uuid' => generateUuidV4()];
			}

			$hosts[] = $host;

			$db_hosts[$row['hostid']] = array_intersect_key($row,
				array_flip(['hostid', 'host', 'uuid', 'templateid', 'ruleid', 'host_status'])
			);
		}

		if ($hosts) {
			$this->addAffectedObjects($hosts, $db_hosts);

			foreach ($hosts as &$host) {
				foreach ($db_hosts[$host['hostid']]['groupLinks'] as $group_link) {
					$host['groupLinks'][] = [
						'groupid' => $group_link['groupid'],
						'templateid' => 0
					];
				}

				foreach ($db_hosts[$host['hostid']]['groupPrototypes'] as $group_prototype) {
					$host['groupPrototypes'][] = [
						'name' => $group_prototype['name'],
						'templateid' => 0
					];
				}
			}
			unset($host);

			self::updateForce($hosts, $db_hosts);
		}
	}

	/**
	 * @param array      $hosts
	 * @param array      $db_hosts
	 * @param array|null $lld_links
	 */
	private function inherit(array $hosts, array $db_hosts = [], ?array $lld_links = null): void {
		if ($lld_links === null) {
			$lld_links = self::getLldLinks(array_unique(array_column($hosts, 'ruleid')));

			self::filterObjectsToInherit($hosts, $db_hosts, $lld_links);

			if (!$hosts) {
				return;
			}
		}

		$chunks = self::getInheritChunks($hosts, $lld_links);

		foreach ($chunks as $chunk) {
			$_hosts = array_intersect_key($hosts, array_flip($chunk['host_indexes']));
			$_db_hosts = array_intersect_key($db_hosts, array_flip(array_column($_hosts, 'hostid')));
			$ruleids = array_keys($chunk['lld_rules']);

			$this->inheritChunk($_hosts, $_db_hosts, $lld_links, $ruleids);
		}
	}

	/**
	 * @param array      $ruleids
	 * @param array|null $hostids
	 *
	 * @param array
	 */
	private static function getLldLinks(array $ruleids, ?array $hostids = null): array {
		$hostids_condition = $hostids !== null
			? ' AND '.dbConditionId('i.hostid', $hostids)
			: '';

		$result = DBselect(
			'SELECT i.templateid,i.itemid,h.status AS host_status'.
			' FROM items i,hosts h'.
			' WHERE i.hostid=h.hostid'.
				' AND '.dbConditionId('i.templateid', $ruleids).
				$hostids_condition
		);

		$lld_links = [];

		while ($row = DBfetch($result)) {
			$lld_links[$row['templateid']][$row['itemid']] = [
				'itemid' => $row['itemid'],
				'host_status' => $row['host_status']
			];
		}

		return $lld_links;
	}

	/**
	 * Filter out inheritable host prototypes.
	 *
	 * @param array $hosts
	 * @param array $db_hosts
	 * @param array $lld_links
	 */
	private static function filterObjectsToInherit(array &$hosts, array &$db_hosts, array $lld_links): void {
		foreach ($hosts as $i => $host) {
			if (!array_key_exists($host['ruleid'], $lld_links)) {
				unset($hosts[$i]);

				if (array_key_exists($host['hostid'], $db_hosts)) {
					unset($db_hosts[$host['hostid']]);
				}
			}
		}
	}

	/**
	 * Get host prototype chunks to inherit.
	 *
	 * @param array $hosts
	 * @param array $lld_links
	 *
	 * @return array
	 */
	private static function getInheritChunks(array $hosts, array $lld_links): array {
		$chunks = [
			[
				'host_indexes' => [],
				'lld_rules' => [],
				'size' => 0
			]
		];
		$last = 0;

		foreach ($hosts as $i => $host) {
			$lld_rules_chunks = array_chunk($lld_links[$host['ruleid']], self::INHERIT_CHUNK_SIZE, true);

			foreach ($lld_rules_chunks as $lld_rules) {
				if ($chunks[$last]['size'] < self::INHERIT_CHUNK_SIZE) {
					$_lld_rules = array_slice($lld_rules, 0, self::INHERIT_CHUNK_SIZE - $chunks[$last]['size'], true);

					$new_lld_rules = array_diff_key($_lld_rules, $chunks[$last]['lld_rules']);
					$can_add_lld_rules = true;

					foreach ($chunks[$last]['host_indexes'] as $_i) {
						if (array_intersect_key($lld_links[$hosts[$_i]['ruleid']], $new_lld_rules)) {
							$can_add_lld_rules = false;
							break;
						}
					}

					if ($can_add_lld_rules) {
						$chunks[$last]['host_indexes'][] = $i;
						$chunks[$last]['lld_rules'] += $_lld_rules;
						$chunks[$last]['size'] += count($_lld_rules);

						$lld_rules = array_diff_key($lld_rules, $_lld_rules);
					}
				}

				if ($lld_rules) {
					$chunks[++$last] = [
						'host_indexes' => [$i],
						'lld_rules' => $lld_rules,
						'size' => count($lld_rules)
					];
				}
			}
		}

		return $chunks;
	}

	/**
	 * @param array $hosts
	 * @param array $db_hosts
	 * @param array $lld_links
	 * @param array $ruleids
	 */
	private function inheritChunk(array $hosts, array $db_hosts, array $lld_links, array $ruleids): void {
		$hosts_to_link = [];
		$hosts_to_update = [];

		foreach ($hosts as $i => $host) {
			if (!array_key_exists($host['hostid'], $db_hosts)) {
				$hosts_to_link[] = $host;
			}
			else {
				$hosts_to_update[] = $host;
			}

			unset($hosts[$i]);
		}

		$ins_hosts = [];
		$upd_hosts = [];
		$upd_db_hosts = [];

		if ($hosts_to_link) {
			$upd_db_hosts = $this->getChildObjectsUsingName($hosts_to_link, $ruleids);

			if ($upd_db_hosts) {
				$upd_hosts = self::getUpdChildObjectsUsingName($hosts_to_link, $upd_db_hosts);
			}

			$ins_hosts = self::getInsChildObjects($hosts_to_link, $upd_db_hosts, $lld_links, $ruleids);
		}

		if ($hosts_to_update) {
			$_upd_db_hosts = self::getChildObjectsUsingTemplateid($hosts_to_update, $db_hosts, $ruleids);
			$_upd_hosts = self::getUpdChildObjectsUsingTemplateid($hosts_to_update, $db_hosts, $_upd_db_hosts);

			self::checkDuplicates($_upd_hosts, $_upd_db_hosts, true);

			$upd_hosts = array_merge($upd_hosts, $_upd_hosts);
			$upd_db_hosts += $_upd_db_hosts;
		}

		if ($upd_hosts) {
			$this->updateForce($upd_hosts, $upd_db_hosts);
		}

		if ($ins_hosts) {
			$this->createForce($ins_hosts);
		}

		$this->inherit(array_merge($upd_hosts, $ins_hosts), $upd_db_hosts);
	}

	/**
	 * @param array $items
	 * @param array $ruleids
	 *
	 * @return array
	 */
	private function getChildObjectsUsingName(array $hosts, array $ruleids): array {
		$result = DBselect(
			'SELECT h.hostid,h.host,h.templateid,i.itemid AS ruleid,i.templateid AS parent_ruleid,'.
				'hh.status AS host_status'.
			' FROM items i,host_discovery hd,hosts h,hosts hh'.
			' WHERE i.itemid=hd.parent_itemid'.
				' AND hd.hostid=h.hostid'.
				' AND i.hostid=hh.hostid'.
				' AND '.dbConditionId('i.itemid', $ruleids).
				' AND '.dbConditionString('h.host', array_unique(array_column($hosts, 'host')))
		);

		$upd_db_hosts = [];
		$parent_indexes = [];

		while ($row = DBfetch($result)) {
			foreach ($hosts as $i => $host) {
				if (bccomp($row['parent_ruleid'], $host['ruleid']) == 0 && $row['host'] === $host['host']) {
					$upd_db_hosts[$row['hostid']] = $row;
					$parent_indexes[$row['hostid']] = $i;
				}
			}
		}

		if (!$upd_db_hosts) {
			return [];
		}

		$result = DBselect(
			'SELECT h.uuid,h.hostid,h.host,h.name,h.custom_interfaces,h.status,h.discover,'.
				dbConditionCoalesce('hi.inventory_mode', HOST_INVENTORY_DISABLED, 'inventory_mode').
			' FROM hosts h'.
			' LEFT JOIN host_inventory hi ON h.hostid=hi.hostid'.
			' WHERE '.dbConditionId('h.hostid', array_keys($upd_db_hosts))
		);

		while ($row = DBfetch($result)) {
			$upd_db_hosts[$row['hostid']] = $row + $upd_db_hosts[$row['hostid']];
		}

		$_upd_hosts = [];

		foreach ($upd_db_hosts as $upd_db_host) {
			$host = $hosts[$parent_indexes[$upd_db_host['hostid']]];

			$_upd_hosts[] = [
				'hostid' => $upd_db_host['hostid'],
				'custom_interfaces' => $host['custom_interfaces'],
				'interfaces' => [],
				'groupLinks' => [],
				'groupPrototypes' => [],
				'templates' => [],
				'tags' => [],
				'macros' => []
			];
		}

		$this->addAffectedObjects($_upd_hosts, $upd_db_hosts);

		return $upd_db_hosts;
	}

	/**
	 * @param array $hosts
	 * @param array $upd_db_hosts
	 *
	 * @return array
	 */
	private static function getUpdChildObjectsUsingName(array $hosts, array $upd_db_hosts): array {
		$parent_indexes = [];

		foreach ($hosts as $i => &$host) {
			$parent_indexes[$host['ruleid']][$host['host']] = $i;
		}
		unset($host);

		$upd_hosts = [];

		foreach ($upd_db_hosts as $upd_db_host) {
			$host = $hosts[$parent_indexes[$upd_db_host['parent_ruleid']][$upd_db_host['host']]];

			$upd_host = [
				'uuid' => '',
				'hostid' => $upd_db_host['hostid'],
				'templateid' => $host['hostid'],
				'ruleid' => $upd_db_host['ruleid'],
				'host_status' => $upd_db_host['host_status']
			];

			self::addInheritedFields($upd_host, $host, $upd_db_host);

			$upd_host += [
				'interfaces' => [],
				'groupLinks' => [],
				'groupPrototypes' => [],
				'templates' => [],
				'tags' => [],
				'macros' => []
			];

			$upd_hosts[] = $upd_host;
		}

		return $upd_hosts;
	}

	/**
	 * @param array $hosts
	 * @param array $upd_db_items
	 * @param array $lld_links
	 * @param array $ruleids
	 *
	 * @return array
	 */
	private static function getInsChildObjects(array $hosts, array $upd_db_hosts, array $lld_links,
			array $ruleids): array {
		$ins_hosts = [];

		$upd_host_names = [];

		foreach ($upd_db_hosts as $upd_db_host) {
			$upd_host_names[$upd_db_host['ruleid']][] = $upd_db_host['host'];
		}

		foreach ($hosts as $host) {
			foreach ($lld_links[$host['ruleid']] as $lld_rule) {
				if (!in_array($lld_rule['itemid'], $ruleids)
						|| (array_key_exists($lld_rule['itemid'], $upd_host_names)
							&& in_array($host['name'], $upd_host_names[$lld_rule['itemid']]))) {
					continue;
				}

				$ins_host = [
					'uuid' => '',
					'templateid' => $host['hostid'],
					'ruleid' => $lld_rule['itemid'],
					'host_status' => $lld_rule['host_status']
				];

				self::addInheritedFields($ins_host, $host);

				$ins_hosts[] = $ins_host;
			}
		}

		return $ins_hosts;
	}

	/**
	 * @param array $hosts
	 * @param array $db_hosts
	 * @param array $ruleids
	 *
	 * @return array
	 */
	private function getChildObjectsUsingTemplateid(array $hosts, array $db_hosts, array $ruleids): array {
		$upd_db_hosts = $this->get([
			'output' => ['hostid', 'host', 'name', 'custom_interfaces', 'status', 'discover', 'inventory_mode'],
			'filter' => [
				'templateid' => array_column($hosts, 'hostid')
			],
			'discoveryids' => $ruleids,
			'nopermissions' => true,
			'preservekeys' => true
		]);

		self::addInternalFields($upd_db_hosts);

		$parent_indexes = array_flip(array_column($hosts, 'hostid'));

		$_upd_hosts = [];

		foreach ($upd_db_hosts as $upd_db_host) {
			$host = $hosts[$parent_indexes[$upd_db_host['templateid']]];
			$db_host = $db_hosts[$upd_db_host['templateid']];

			$_upd_host = [
				'hostid' => $upd_db_host['hostid'],
				'custom_interfaces' => $host['custom_interfaces']
			];

			$_upd_host += array_intersect_key([
				'interfaces' => [],
				'groupLinks' => [],
				'groupPrototypes' => [],
				'templates' => [],
				'tags' => [],
				'macros' => []
			], $db_host);

			$_upd_hosts[] = $_upd_host;
		}

		$this->addAffectedObjects($_upd_hosts, $upd_db_hosts);

		return $upd_db_hosts;
	}

	/**
	 * @param array $hosts
	 * @param array $db_hosts
	 * @param array $upd_db_hosts
	 *
	 * @return array
	 */
	private static function getUpdChildObjectsUsingTemplateid(array $hosts, array $db_hosts,
			array $upd_db_hosts): array {
		$parent_indexes = array_flip(array_column($hosts, 'hostid'));

		$upd_hosts = [];

		foreach ($upd_db_hosts as $upd_db_host) {
			$upd_host = array_intersect_key($upd_db_host,
				array_flip(['hostid', 'ruleid', 'host_status'])
			);
			$host = $hosts[$parent_indexes[$upd_db_host['templateid']]];
			$db_host = $db_hosts[$host['hostid']];

			self::addInheritedFields($upd_host, $host, $upd_db_host, $db_host);

			$upd_hosts[] = $upd_host;
		}

		return $upd_hosts;
	}

	/**
	 * @param array      $inh_host
	 * @param array      $host
	 * @param array|null $inh_db_host
	 * @param array|null $db_host
	 */
	private static function addInheritedFields(array &$inh_host, array $host, ?array $inh_db_host = null,
			?array $db_host = null): void {
		$inh_host += array_intersect_key($host,
			array_flip(['host', 'name', 'custom_interfaces', 'status', 'discover', 'inventory_mode'])
		);

		if (array_key_exists('interfaces', $host)) {
			$inh_host['interfaces'] = [];

			foreach ($host['interfaces'] as $interface) {
				$inh_host['interfaces'][] = array_diff_key($interface, array_flip(['interfaceid']));
			}
		}

		if (array_key_exists('groupLinks', $host)) {
			$inh_host['groupLinks'] = [];

			foreach ($host['groupLinks'] as $group_link) {
				$inh_host['groupLinks'][] = [
					'groupid' => $group_link['groupid'],
					'templateid' => $group_link['group_prototypeid']
				];
			}
		}

		if (array_key_exists('groupPrototypes', $host)) {
			$inh_host['groupPrototypes'] = [];

			$inh_group_prototypeids = $inh_db_host !== null
				? array_column($inh_db_host['groupPrototypes'], 'group_prototypeid', 'name')
				: [];

			foreach ($host['groupPrototypes'] as $group_prototype) {
				if ($db_host === null) {
					$name = $group_prototype['name'];
				}
				else {
					$name = array_key_exists($group_prototype['group_prototypeid'], $db_host['groupPrototypes'])
						? $db_host['groupPrototypes'][$group_prototype['group_prototypeid']]['name']
						: $group_prototype['name'];
				}

				$inh_group_prototype = [
					'name' => $group_prototype['name'],
					'templateid' => $group_prototype['group_prototypeid']
				];

				if (array_key_exists($name, $inh_group_prototypeids)) {
					$inh_group_prototype = ['group_prototypeid' => $inh_group_prototypeids[$name]]
						+ $inh_group_prototype;
				}

				$inh_host['groupPrototypes'][] = $inh_group_prototype;
			}
		}

		if (array_key_exists('templates', $host)) {
			$inh_host['templates'] = [];

			foreach ($host['templates'] as $template) {
				$inh_host['templates'][] = array_diff_key($template, array_flip(['hosttemplateid']));
			}
		}

		if (array_key_exists('tags', $host)) {
			$inh_host['tags'] = [];

			foreach ($host['tags'] as $tag) {
				$inh_host['tags'][] = array_diff_key($tag, array_flip(['hosttagid']));
			}
		}

		if (array_key_exists('macros', $host)) {
			$inh_host['macros'] = [];

			$inh_hostmacroids = $inh_db_host !== null
				? array_column($inh_db_host['macros'], 'hostmacroid', 'macro')
				: [];

			foreach ($host['macros'] as $host_macro) {
				if ($db_host === null) {
					$macro = $host_macro['macro'];
				}
				else {
					$macro = array_key_exists($host_macro['hostmacroid'], $db_host['macros'])
						? $db_host['macros'][$host_macro['hostmacroid']]['macro']
						: $host_macro['macro'];
				}

				if (array_key_exists($macro, $inh_hostmacroids)) {
					$inh_host['macros'][] = ['hostmacroid' => $inh_hostmacroids[$macro]] + $host_macro;
				}
				else {
					$inh_host['macros'][] =  array_diff_key($host_macro, array_flip(['hostmacroid']));
				}
			}
		}
	}

	/**
	 * @param array $hostids
	 *
	 * @return array
	 */
	public function delete(array $hostids): array {
		$this->validateDelete($hostids, $db_hosts);

		self::deleteForce($db_hosts);

		return ['hostids' => $hostids];
	}

	/**
	 * @param array      $hostids
	 * @param array|null $db_hosts
	 *
	 * @throws APIException if the input is invalid.
	 */
	private function validateDelete(array &$hostids, ?array &$db_hosts = null): void {
		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];

		if (!CApiInputValidator::validate($api_input_rules, $hostids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_hosts = $this->get([
			'output' => ['hostid', 'host', 'templateid'],
			'hostids' => $hostids,
			'editable' => true,
			'preservekeys' => true
		]);

		if (count($db_hosts) != count($hostids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		foreach ($hostids as $i => $hostid) {
			if ($db_hosts[$hostid]['templateid'] != 0) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.', '/'.($i + 1),
					_('cannot delete templated host prototype')
				));
			}
		}
	}

	/**
	 * @param array $db_hosts
	 */
	public static function deleteForce(array $db_hosts): void {
		self::addInheritedHostPrototypes($db_hosts);

		$hostids = array_keys($db_hosts);

		// Lock host prototypes before deletion to prevent server from adding new LLD hosts.
		DBselect(
			'SELECT NULL'.
			' FROM hosts h'.
			' WHERE '.dbConditionId('h.hostid', $hostids).
			' FOR UPDATE'
		);

		$options = [
			'output' => ['group_prototypeid'],
			'filter' => [
				'hostid' => $hostids
			]
		];
		$del_group_prototypeids =
			DBfetchColumn(DBselect(DB::makeSql('group_prototype', $options)), 'group_prototypeid');

		if ($del_group_prototypeids) {
			self::deleteGroupPrototypes($del_group_prototypeids);
		}

		$discovered_hosts = DBfetchArrayAssoc(DBselect(
			'SELECT hd.hostid,h.host'.
			' FROM host_discovery hd,hosts h'.
			' WHERE hd.hostid=h.hostid'.
				' AND '.dbConditionId('hd.parent_hostid', $hostids)
		), 'hostid');

		CHost::deleteForce($discovered_hosts);

		DB::delete('interface', ['hostid' => $hostids]);
		DB::delete('hosts_templates', ['hostid' => $hostids]);
		DB::delete('host_tag', ['hostid' => $hostids]);
		DB::delete('hostmacro', ['hostid' => $hostids]);
		DB::delete('host_inventory', ['hostid' => $hostids]);
		DB::update('hosts', [
			'values' => ['templateid' => 0],
			'where' => ['hostid' => $hostids]
		]);
		DB::delete('hosts', ['hostid' => $hostids]);

		self::addAuditLog(CAudit::ACTION_DELETE, CAudit::RESOURCE_HOST_PROTOTYPE, $db_hosts);
	}

	/**
	 * @param array $db_hosts
	 */
	private static function addInheritedHostPrototypes(array &$db_hosts): void {
		$_db_hosts = $db_hosts;

		do {
			$_db_hosts = DB::select('hosts', [
				'output' => ['hostid', 'host'],
				'filter' => ['templateid' => array_keys($_db_hosts)],
				'preservekeys' => true
			]);

			$db_hosts += $_db_hosts;
		} while ($_db_hosts);
	}

	/**
	 *@param array $del_group_prototypeids
	 */
	private static function deleteGroupPrototypes(array $del_group_prototypeids): void {
		// Lock group prototypes before the deletion to prevent server from adding new LLD elements.
		DBselect(
			'SELECT NULL'.
			' FROM group_prototype gp'.
			' WHERE '.dbConditionId('gp.group_prototypeid', $del_group_prototypeids).
			' FOR UPDATE'
		);

		self::deleteDiscoveredGroups($del_group_prototypeids);

		DB::update('group_prototype', [
			'values' => ['templateid' => 0],
			'where' => ['templateid' => $del_group_prototypeids]
		]);
		DB::delete('group_prototype', ['group_prototypeid' => $del_group_prototypeids]);
	}

	/**
	 * Delete the discovered host groups of the given group prototypes.
	 *
	 * @param array $group_prototypeids
	 */
	private static function deleteDiscoveredGroups(array $group_prototypeids): void {
		$db_groups = DBfetchArrayAssoc(DBselect(
			'SELECT DISTINCT gd.groupid,g.name'.
			' FROM group_discovery gd,hstgrp g'.
			' WHERE gd.groupid=g.groupid'.
				' AND '.dbConditionId('gd.parent_group_prototypeid', $group_prototypeids).
				' AND NOT EXISTS ('.
					'SELECT NULL'.
					' FROM group_discovery gd2'.
					' WHERE gd.groupid=gd2.groupid'.
						' AND '.dbConditionId('gd2.parent_group_prototypeid', $group_prototypeids, true).
				')'
		), 'groupid');

		if ($db_groups) {
			API::HostGroup()->deleteForce($db_groups);
		}

		DB::delete('group_discovery', ['parent_group_prototypeid' => $group_prototypeids]);
	}
}
