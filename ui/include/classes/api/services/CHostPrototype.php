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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
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
			'selectGroupPrototypes' =>	['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', ['name']), 'default' => null],
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
			$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ;

			$sqlParts['where'][] = 'EXISTS ('.
				'SELECT NULL'.
				' FROM '.
					'host_discovery hd,items i,hosts_groups hgg'.
					' JOIN rights r'.
						' ON r.id=hgg.groupid'.
						' AND '.dbConditionId('r.groupid', getUserGroupsByUserId(self::$userData['userid'])).
				' WHERE h.hostid=hd.hostid'.
					' AND hd.parent_itemid=i.itemid'.
					' AND i.hostid=hgg.hostid'.
				' GROUP BY hgg.hostid'.
				' HAVING MIN(r.permission)>'.PERM_DENY.
				' AND MAX(r.permission)>='.zbx_dbstr($permission).
				')';
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

		// adding discovery rule
		if ($options['selectDiscoveryRule'] !== null && $options['selectDiscoveryRule'] != API_OUTPUT_COUNT) {
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

		// adding host
		if ($options['selectParentHost'] !== null && $options['selectParentHost'] != API_OUTPUT_COUNT) {
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

		// adding templates
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

		// adding tags
		if ($options['selectTags'] !== null && $options['selectTags'] !== API_OUTPUT_COUNT) {
			$tags = API::getApiService()->select('host_tag', [
				'output' => $this->outputExtend($options['selectTags'], ['hostid', 'hosttagid']),
				'filter' => ['hostid' => $hostids],
				'preservekeys' => true
			]);

			$relation_map = $this->createRelationMap($tags, 'hostid', 'hosttagid');
			$tags = $this->unsetExtraFields($tags, ['hostid', 'hosttagid'], []);
			$result = $relation_map->mapMany($result, $tags, 'tags');
		}

		if ($options['selectInterfaces'] !== null && $options['selectInterfaces'] != API_OUTPUT_COUNT) {
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
				' AND '.dbConditionId('gp.groupid', [0], true)
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
			$output = ['hostid', 'name'];
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
				' AND '.dbConditionString('gp.name', [''], true)
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
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['uuid'], ['ruleid', 'host'], ['ruleid', 'name']], 'fields' => [
			'uuid' =>				['type' => API_UUID],
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
			'groupPrototypes' =>	['type' => API_OBJECTS, 'uniq' => [['name']], 'fields' => [
				'name' =>				['type' => API_HG_NAME, 'flags' => API_REQUIRED | API_REQUIRED_LLD_MACRO, 'length' => DB::getFieldLength('group_prototype', 'name')]
			]],
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
											['if' => ['field' => 'type', 'in' => implode(',', [ZBX_MACRO_TYPE_VAULT])], 'type' => API_VAULT_SECRET, 'length' => DB::getFieldLength('hostmacro', 'value')],
											['else' => true, 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hostmacro', 'value')]
				]],
				'description' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hostmacro', 'description')]
			]],
			'inventory_mode' =>		['type' => API_INT32, 'in' => implode(',', [HOST_INVENTORY_DISABLED, HOST_INVENTORY_MANUAL, HOST_INVENTORY_AUTOMATIC])]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $hosts, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		self::checkDiscoveryRuleAccess($hosts);
		self::checkRulesAndAddParentDetails($hosts);

		self::checkAndAddUuid($hosts);
		self::checkDuplicates($hosts);
		self::checkMainInterfaces($hosts);
		self::checkGroupLinks($hosts);
		$this->checkTemplates($hosts);
		self::addFlags($hosts, ZBX_FLAG_DISCOVERY_PROTOTYPE);
	}

	/**
	 * Check that LLD rule IDs of given prototypes are valid. Add parent_hostid and parent_status properties to $hosts.
	 *
	 * @param array      $hosts
	 *
	 * @throws APIException
	 */
	protected static function checkRulesAndAddParentDetails(array &$hosts): void {
		$rows = DBfetchArray(DBselect(
			'SELECT i.itemid AS ruleid,h.hostid AS parent_hostid'.
			' FROM items i,hosts h'.
			' WHERE i.hostid=h.hostid'.
				' AND '.dbConditionId('i.itemid', array_column($hosts, 'ruleid', 'ruleid'))
		));

		$hostids = array_column($rows, 'parent_hostid');

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

		$lld_links = array_column($rows, 'parent_hostid', 'ruleid');
		self::addParentDetails($hosts, $lld_links, $db_hosts, $db_templates);
	}

	/**
	 * Add parent_hostid and parent_status properties from their discovery rule's parent host or template.
	 *
	 * @param array $hosts
	 * @param array $ldd_links
	 * @param array $db_hosts
	 * @param array $db_templates
	 */
	private static function addParentDetails(array &$hosts, array $ldd_links, array $db_hosts,
			array $db_templates): void {
		foreach ($hosts as &$host) {
			$parent_hostid = $ldd_links[$host['ruleid']];

			$host['parent_hostid'] = $parent_hostid;
			$host['parent_status'] = array_key_exists($parent_hostid, $db_templates)
				? HOST_STATUS_TEMPLATE
				: $db_hosts[$parent_hostid]['status'];
		}
		unset($host);
	}

	/**
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
	 * @param array $hosts
	 * @param bool  $inherited
	 */
	protected function createForce(array &$hosts, bool $inherited = false): void {
		$hostids = DB::insert('hosts', $hosts);

		$parent_fields = [];

		foreach ($hosts as $i => &$host) {
			$host['hostid'] = $hostids[$i];

			$parent_fields[$i] = array_intersect_key($host, array_flip(['parent_status', 'parent_hostid']));
			$host = array_diff_key($host, $parent_fields[$i]);
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
		$this->updateTagsNew($hosts);
		$this->updateMacros($hosts);
		self::updateHostInventories($hosts);

		self::addAuditLog(CAudit::ACTION_ADD, CAudit::RESOURCE_HOST_PROTOTYPE, $hosts);

		foreach ($hosts as $i => &$host) {
			$host += $parent_fields[$i];
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
	protected function validateUpdate(array &$hosts, array &$db_hosts = null): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE | API_ALLOW_UNEXPECTED, 'uniq' => [['hostid']], 'fields' => [
			'hostid' =>	['type' => API_ID, 'flags' => API_REQUIRED],
			'ruleid' => ['type' => API_UNEXPECTED]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $hosts, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$count = $this->get([
			'countOutput' => true,
			'hostids' => array_column($hosts, 'hostid'),
			'editable' => true
		]);

		if (count($hosts) != $count) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		$db_hosts = DBfetchArrayAssoc(DBselect(
			'SELECT h.hostid,h.host,h.name,h.custom_interfaces,h.status,h.discover,h.templateid,'.
				'hd.parent_itemid AS ruleid,'.
				dbConditionCoalesce('hi.inventory_mode', HOST_INVENTORY_DISABLED, 'inventory_mode').
			' FROM hosts h'.
			' INNER JOIN host_discovery hd ON h.hostid=hd.hostid'.
			' LEFT JOIN host_inventory hi ON h.hostid=hi.hostid'.
			' WHERE '.dbConditionId('h.hostid', array_column($hosts, 'hostid', 'hostid'))
		), 'hostid');

		foreach ($hosts as $i => &$host) {
			if ($db_hosts[$host['hostid']]['templateid'] == 0) {
				$host += array_intersect_key($db_hosts[$host['hostid']],
					array_flip(['host', 'name', 'custom_interfaces'])
				);

				$api_input_rules = self::getValidationRules();
			}
			else {
				$api_input_rules = self::getInheritedValidationRules();
			}

			if (!CApiInputValidator::validate($api_input_rules, $host, '/'.($i + 1), $error)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, $error);
			}
		}
		unset($host);

		$hosts = $this->extendObjectsByKey($hosts, $db_hosts, 'hostid',
			['host', 'name', 'ruleid', 'custom_interfaces']
		);

		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_ALLOW_UNEXPECTED, 'uniq' => [['ruleid', 'host'], ['ruleid', 'name']], 'fields' => [
			'ruleid' =>	['type' => API_ID],
			'host' =>	['type' => API_H_NAME],
			'name' =>	['type' => API_STRING_UTF8]
		]];

		if (!CApiInputValidator::validateUniqueness($api_input_rules, $hosts, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		self::checkRulesAndAddParentDetails($hosts);
		$this->addAffectedObjects($hosts, $db_hosts);

		self::checkDuplicates($hosts, $db_hosts);
		self::checkMainInterfaces($hosts);
		self::checkGroupLinks($hosts, $db_hosts);
		$this->checkTemplates($hosts, $db_hosts);
		$this->checkTemplatesLinks($hosts, $db_hosts);
		$hosts = parent::validateHostMacros($hosts, $db_hosts);
	}

	/**
	 * @return array
	 */
	private static function getValidationRules(): array {
		return ['type' => API_OBJECT, 'fields' => [
			'hostid' =>				['type' => API_ID],
			'host' =>				['type' => API_H_NAME, 'flags' => API_REQUIRED_LLD_MACRO, 'length' => DB::getFieldLength('hosts', 'host')],
			'name' =>				['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('hosts', 'name')],
			'custom_interfaces' =>	['type' => API_INT32, 'in' => implode(',', [HOST_PROT_INTERFACES_INHERIT, HOST_PROT_INTERFACES_CUSTOM])],
			'status' =>				['type' => API_INT32, 'in' => implode(',', [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED])],
			'discover' =>			['type' => API_INT32, 'in' => implode(',', [ZBX_PROTOTYPE_DISCOVER, ZBX_PROTOTYPE_NO_DISCOVER])],
			'interfaces' =>			self::getInterfacesValidationRules(),
			'groupLinks' =>			['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY, 'uniq' => [['groupid']], 'fields' => [
				'groupid' =>			['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'groupPrototypes' =>	['type' => API_OBJECTS, 'uniq' => [['name']], 'fields' => [
				'name' =>				['type' => API_HG_NAME, 'flags' => API_REQUIRED | API_REQUIRED_LLD_MACRO, 'length' => DB::getFieldLength('group_prototype', 'name')]
			]],
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
			'hostid' =>				['type' => API_ID],
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
						'version' =>		['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [SNMP_V1, SNMP_V2C, SNMP_V3])],
						'bulk' =>			['type' => API_INT32, 'in' => implode(',', [SNMP_BULK_DISABLED, SNMP_BULK_ENABLED])],
						'community' =>		['type' => API_MULTIPLE, 'rules' => [
												['if' => ['field' => 'version', 'in' => implode(',', [SNMP_V1, SNMP_V2C])], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('interface_snmp', 'community')],
												['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('interface_snmp', 'community')]
						]],
						'contextname' =>	['type' => API_MULTIPLE, 'rules' => [
												['if' => ['field' => 'version', 'in' => SNMP_V3], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('interface_snmp', 'contextname')],
												['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('interface_snmp', 'contextname')]
						]],
						'securityname' =>	['type' => API_MULTIPLE, 'rules' => [
												['if' => ['field' => 'version', 'in' => SNMP_V3], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('interface_snmp', 'securityname')],
												['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('interface_snmp', 'securityname')]
						]],
						'securitylevel' =>	['type' => API_MULTIPLE, 'rules' => [
												['if' => ['field' => 'version', 'in' => SNMP_V3], 'type' => API_INT32, 'in' => implode(',', [ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV, ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV, ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV]), 'default' => DB::getDefault('interface_snmp', 'securitylevel')],
												['else' => true, 'type' => API_INT32, 'in' => DB::getDefault('interface_snmp', 'securitylevel')]
						]],
						'authprotocol' =>	['type' => API_MULTIPLE, 'rules' => [
												['if' => static function (array $data): bool {
													return $data['version'] == SNMP_V3
														&& in_array($data['securitylevel'], [ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV, ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV]);
												}, 'type' => API_INT32, 'in' => implode(',', array_keys(getSnmpV3AuthProtocols()))],
												['else' => true, 'type' => API_INT32, 'in' => DB::getDefault('interface_snmp', 'authprotocol')]
						]],
						'authpassphrase' =>	['type' => API_MULTIPLE, 'rules' => [
												['if' => static function (array $data): bool {
													return $data['version'] == SNMP_V3
														&& in_array($data['securitylevel'], [ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV, ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV]);
												}, 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('interface_snmp', 'authpassphrase')],
												['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('interface_snmp', 'authpassphrase')]
						]],
						'privprotocol' =>	['type' => API_MULTIPLE, 'rules' => [
												['if' => static function (array $data): bool {
													return $data['version'] == SNMP_V3
														&& $data['securitylevel'] == ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV;
												}, 'type' => API_INT32, 'in' => implode(',', array_keys(getSnmpV3PrivProtocols()))],
												['else' => true, 'type' => API_INT32, 'in' => DB::getDefault('interface_snmp', 'privprotocol')]
						]],
						'privpassphrase' =>	['type' => API_MULTIPLE, 'rules' => [
												['if' => static function (array $data): bool {
													return $data['version'] == SNMP_V3
														&& $data['securitylevel'] == ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV;
												}, 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('interface_snmp', 'privpassphrase')],
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
	 * @param array $hosts
	 * @param array $db_hosts
	 */
	protected function updateForce(array &$hosts, array $db_hosts): void {
		// Helps to avoid deadlocks.
		CArrayHelper::sort($hosts, ['hostid', 'order' => ZBX_SORT_DOWN]);

		$upd_hosts = [];
		$upd_hostids = [];

		$internal_fields = array_flip(['hostid', 'host', 'ruleid', 'custom_interfaces', 'inventory_mode']);
		$nested_object_fields = array_flip(['interfaces', 'groupLinks', 'groupPrototypes', 'templates', 'tags',
			'macros', 'host_inventory'
		]);
		$parent_fields = [];

		foreach ($hosts as $i => &$host) {
			$upd_host = DB::getUpdatedValues('hosts', $host, $db_hosts[$host['hostid']]);

			if ($upd_host) {
				$upd_hosts[] = [
					'values' => $upd_host,
					'where' => ['hostid' => $host['hostid']]
				];

				$upd_hostids[$i] = $host['hostid'];
			}

			$parent_fields[$host['hostid']] = array_intersect_key($host,
				array_flip(['parent_hostid', 'parent_status'])
			);

			$host = array_intersect_key($host, $internal_fields + $upd_host + $nested_object_fields);
		}

		if ($upd_hosts) {
			DB::update('hosts', $upd_hosts);
		}

		self::updateInterfaces($hosts, $db_hosts, $upd_hostids);
		self::updateGroupLinks($hosts, $db_hosts, $upd_hostids);
		self::updateGroupPrototypes($hosts, $db_hosts, $upd_hostids);
		$this->updateTemplates($hosts, $db_hosts, $upd_hostids);
		$this->updateTagsNew($hosts, $db_hosts, $upd_hostids);
		$this->updateMacros($hosts, $db_hosts, $upd_hostids);
		self::updateHostInventories($hosts, $db_hosts, $upd_hostids);

		$hosts = array_intersect_key($hosts, $upd_hostids);
		$db_hosts = array_intersect_key($db_hosts, array_flip($upd_hostids));

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_HOST_PROTOTYPE, $hosts, $db_hosts);

		foreach ($hosts as &$host) {
			$host += $parent_fields[$host['hostid']];
		}
		unset($host);
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
	 * @param array $host_prototypes
	 * @param array $db_host_prototypes
	 */
	private static function addAffectedInterfaces(array $host_prototypes, array &$db_host_prototypes): void {
		$hostids = [];

		foreach ($host_prototypes as $host_prototype) {
			$interfaces_set = $host_prototype['custom_interfaces'];
			$db_interfaces_set = $db_host_prototypes[$host_prototype['hostid']]['custom_interfaces'];

			if ((array_key_exists('interfaces', $host_prototype) && $interfaces_set == HOST_PROT_INTERFACES_CUSTOM)
					|| ($interfaces_set != $db_interfaces_set && $db_interfaces_set == HOST_PROT_INTERFACES_CUSTOM)) {
				$hostids[] = $host_prototype['hostid'];
				$db_host_prototypes[$host_prototype['hostid']]['interfaces'] = [];
			}
			elseif (array_key_exists('interfaces', $host_prototype)) {
				$db_host_prototypes[$host_prototype['hostid']]['interfaces'] = [];
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
			$db_host_prototypes[$db_interface['hostid']]['interfaces'][$db_interface['interfaceid']] =
				array_diff_key($db_interface, array_flip(['hostid']));

			if ($db_interface['type'] == INTERFACE_TYPE_SNMP) {
				$details_interfaces[$db_interface['interfaceid']] = $db_interface['hostid'];
			}
		}

		if ($details_interfaces) {
			$options = [
				'output' => ['interfaceid', 'version', 'bulk', 'community', 'securityname', 'securitylevel',
					'authpassphrase', 'privpassphrase', 'authprotocol', 'privprotocol', 'contextname'
				],
				'filter' => ['interfaceid' => array_keys($details_interfaces)]
			];
			$result = DBselect(DB::makeSql('interface_snmp', $options));

			while ($db_details = DBfetch($result)) {
				$hostid = $details_interfaces[$db_details['interfaceid']];
				$db_host_prototypes[$hostid]['interfaces'][$db_details['interfaceid']]['details'] =
					array_diff_key($db_details, array_flip(['interfaceid']));
			}
		}
	}

	/**
	 * @param array $host_prototypes
	 * @param array $db_host_prototypes
	 */
	private static function addAffectedGroupLinks(array $host_prototypes, array &$db_host_prototypes): void {
		$hostids = [];

		foreach ($host_prototypes as $host_prototype) {
			if (array_key_exists('groupLinks', $host_prototype)) {
				$hostids[] = $host_prototype['hostid'];
				$db_host_prototypes[$host_prototype['hostid']]['groupLinks'] = [];
			}
		}

		if (!$hostids) {
			return;
		}

		$options = [
			'output' => ['group_prototypeid', 'hostid', 'groupid', 'templateid'],
			'filter' => ['hostid' => $hostids, 'name' => '']
		];
		$db_links = DBselect(DB::makeSql('group_prototype', $options));

		while ($db_link = DBfetch($db_links)) {
			$db_host_prototypes[$db_link['hostid']]['groupLinks'][$db_link['group_prototypeid']] =
				array_diff_key($db_link, array_flip(['hostid']));
		}
	}

	/**
	 * @param array $host_prototypes
	 * @param array $db_host_prototypes
	 */
	private static function addAffectedGroupPrototypes(array $host_prototypes, array &$db_host_prototypes): void {
		$hostids = [];

		foreach ($host_prototypes as $host_prototype) {
			if (array_key_exists('groupPrototypes', $host_prototype)) {
				$hostids[] = $host_prototype['hostid'];
				$db_host_prototypes[$host_prototype['hostid']]['groupPrototypes'] = [];
			}
		}

		if (!$hostids) {
			return;
		}

		$options = [
			'output' => ['group_prototypeid', 'hostid', 'name', 'templateid'],
			'filter' => ['hostid' => $hostids, 'groupid' => '0']
		];
		$db_groups = DBselect(DB::makeSql('group_prototype', $options));

		while ($db_link = DBfetch($db_groups)) {
			$db_host_prototypes[$db_link['hostid']]['groupPrototypes'][$db_link['group_prototypeid']] =
				array_diff_key($db_link, array_flip(['hostid']));
		}
	}

	/**
	 * Check for unique host prototype names per LLD rule.
	 *
	 * @param array      $host_prototypes
	 * @param array|null $db_host_prototypes
	 * @param bool       $inherited
	 *
	 * @throws APIException
	 */
	private static function checkDuplicates(array $host_prototypes, array $db_host_prototypes = null,
			bool $inherited = false): void {
		$h_names = [];
		$v_names = [];

		foreach ($host_prototypes as $host_prototype) {
			if (array_key_exists('host', $host_prototype)) {
				if ($db_host_prototypes === null
						|| $host_prototype['host'] !== $db_host_prototypes[$host_prototype['hostid']]['host']) {
					$h_names[$host_prototype['ruleid']][] = $host_prototype['host'];
				}
			}

			if (!$inherited && array_key_exists('name', $host_prototype)) {
				if ($db_host_prototypes === null
						|| $host_prototype['name'] !== $db_host_prototypes[$host_prototype['hostid']]['name']) {
					$v_names[$host_prototype['ruleid']][] = $host_prototype['name'];
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
	}

	/**
	 * Check that only templated host prototypes have UUIDs. Generate a UUID for such prototypes, if empty.
	 *
	 * @param array $hosts
	 *
	 * @throws APIException
	 */
	private static function checkAndAddUuid(array &$hosts): void {
		foreach ($hosts as $i => &$host) {
			if ($host['parent_status'] == HOST_STATUS_TEMPLATE) {
				if (!array_key_exists('uuid', $host)) {
					$host['uuid'] = generateUuidV4();
				}
			}
			elseif (array_key_exists('uuid', $host)) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('zInvalid parameter "%1$s": %2$s.', '/' . ($i + 1), _s('unexpected parameter "%1$s"', 'uuid'))
				);
			}
		}
		unset($host);

		$duplicates = DB::select('hosts', [
			'output' => ['uuid'],
			'filter' => [
				'uuid' => array_column($hosts, 'uuid')
			],
			'limit' => 1
		]);

		if ($duplicates) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Entry with UUID "%1$s" already exists.', $duplicates[0]['uuid'])
			);
		}
	}

	/**
	 * @param array $hosts
	 *
	 * @throws APIException
	 */
	private static function checkDiscoveryRuleAccess(array $hosts): void {
		$ruleids = array_column($hosts, 'ruleid', 'ruleid');

		$count = API::DiscoveryRule()->get([
			'countOutput' => true,
			'itemids' => $ruleids,
			'editable' => true
		]);

		if ($count != count($ruleids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		// Check if a rule's parent host is discovered.
		$db_hosts = DBfetchArray(DBselect(
			'SELECT h.host'.
			' FROM items i,hosts h'.
			' WHERE i.hostid=h.hostid'.
				' AND '.dbConditionId('i.itemid', $ruleids).
				' AND h.flags='.ZBX_FLAG_DISCOVERY_CREATED,
			1
		));

		if ($db_hosts) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Cannot create a host prototype on a discovered host "%1$s".', $db_hosts[0]['host'])
			);
		}
	}

	/**
	 * Check for valid host groups.
	 *
	 * @param array $host_prototypes
	 * @param array $db_host_prototypes
	 *
	 * @throws APIException if groups are not valid.
	 */
	private static function checkGroupLinks(array $host_prototypes, array $db_host_prototypes = null): void {
		$edit_groupids = [];

		foreach ($host_prototypes as $host_prototype) {
			if (!array_key_exists('groupLinks', $host_prototype)) {
				continue;
			}

			$groupids = array_column($host_prototype['groupLinks'], 'groupid');

			if ($db_host_prototypes === null) {
				$edit_groupids += array_flip($groupids);
			}
			else {
				$db_groupids = array_column($db_host_prototypes[$host_prototype['hostid']]['groupLinks'], 'groupid');

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
	 * @param array $host_prototypes
	 *
	 * @throws APIException if two main or no main interfaces are given.
	 */
	private static function checkMainInterfaces(array $host_prototypes): void {
		foreach ($host_prototypes as $i => $host_prototype) {
			if ($host_prototype['custom_interfaces'] != HOST_PROT_INTERFACES_CUSTOM
					|| !array_key_exists('interfaces', $host_prototype) || !$host_prototype['interfaces']) {
				continue;
			}

			$interface_types = [];
			$path = '/'.($i + 1).'/interfaces';

			foreach ($host_prototype['interfaces'] as $interface) {
				if (!array_key_exists($interface['type'], $interface_types)) {
					$interface_types[$interface['type']] = [INTERFACE_PRIMARY => 0, INTERFACE_SECONDARY => 0];
				}

				$interface_types[$interface['type']][$interface['main']]++;

				if ($interface_types[$interface['type']][INTERFACE_PRIMARY] > 1) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.', $path,
						_s('cannot have more than one default interface of the same type')
					));
				}
			}

			foreach ($interface_types as $type => $counters) {
				if ($counters[INTERFACE_SECONDARY] > 0 && $counters[INTERFACE_PRIMARY] == 0) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.', $path,
						_s('no default interface for "%1$s" type', hostInterfaceTypeNumToName($type))
					));
				}
			}
		}
	}

	/**
	 * @param array $host_prototypes
	 */
	private static function createHostDiscoveries(array $host_prototypes): void {
		$host_discoveries = [];

		foreach ($host_prototypes as $host_prototype) {
			$host_discoveries[] = [
				'hostid' => $host_prototype['hostid'],
				'parent_itemid' => $host_prototype['ruleid']
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
	private static function updateInterfaces(array &$hosts, array $db_hosts = null,
			array &$upd_hostids = null): void {
		$ins_interfaces = [];
		$del_interfaceids = [];

		foreach ($hosts as $i => &$host) {
			$update = false;

			if ($db_hosts === null) {
				$update = ($host['custom_interfaces'] == HOST_PROT_INTERFACES_CUSTOM
					&& array_key_exists('interfaces', $host)
				);
			}
			else {
				if ($host['custom_interfaces'] == HOST_PROT_INTERFACES_CUSTOM) {
					$update = array_key_exists('interfaces', $host);
				}
				else {
					$db_host = $db_hosts[$host['hostid']];

					if ($db_host['custom_interfaces'] == HOST_PROT_INTERFACES_CUSTOM
							&& $db_host['interfaces']) {
						$update = true;

						if (!array_key_exists('interfaces', $host)) {
							$host['interfaces'] = [];
						}
					}
				}
			}

			if (!$update) {
				continue;
			}

			$changed = false;
			$db_interfaces = ($db_hosts !== null)
				? $db_hosts[$host['hostid']]['interfaces']
				: [];

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

			if ($db_hosts) {
				if ($changed) {
					$upd_hostids[$i] = $host['hostid'];
				}
				else {
					unset($host['interfaces']);
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
			'contextname', 'securityname', 'securitylevel', 'authprotocol', 'authpassphrase', 'privprotocol',
			'privpassphrase'
		]));

		$interface += $def_interface;
		$details = array_key_exists('details', $interface) ? $interface['details'] : [];

		if (array_key_exists('details', $interface)) {
			$details += $def_details;
		}

		foreach ($db_interfaces as $db_interface) {
			$db_details = array_key_exists('details', $db_interface) ? $db_interface['details'] : [];

			if (!DB::getUpdatedValues('interface', $interface, $db_interface)) {
				if (!array_key_exists('details', $interface)) {
					return $db_interface['interfaceid'];
				}

				if (!array_key_exists('details', $db_interface)) {
					continue;
				}

				if (!DB::getUpdatedValues('interface_snmp', $details, $db_details)) {
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
	private static function updateGroupLinks(array &$hosts, array $db_hosts = null, array &$upd_hostids = null): void {
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
					unset($host['groupLinks']);
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
	private static function updateGroupPrototypes(array &$hosts, array $db_hosts = null,
			array &$upd_hostids = null): void {
		$ins_group_prototypes = [];
		$upd_group_prototypes = []; // Used to update templateid value upon inheritance.
		$del_group_prototypeids = [];

		foreach ($hosts as $i => &$host) {
			if (!array_key_exists('groupPrototypes', $host)) {
				continue;
			}

			$db_group_prototypes = ($db_hosts !== null)
				? array_column($db_hosts[$host['hostid']]['groupPrototypes'], null, 'name')
				: [];
			$changed = false;

			foreach ($host['groupPrototypes'] as &$group_prototype) {
				if (array_key_exists($group_prototype['name'], $db_group_prototypes)) {
					$group_prototype['group_prototypeid'] =
						$db_group_prototypes[$group_prototype['name']]['group_prototypeid'];
					$upd_group_prototype = DB::getUpdatedValues('group_prototype', $group_prototype,
						$db_group_prototypes[$group_prototype['name']]
					);

					if ($upd_group_prototype) {
						$upd_group_prototypes[] = [
							'values' => $upd_group_prototype,
							'where' => ['group_prototypeid' => $group_prototype['group_prototypeid']]
						];
						$changed = true;
					}

					unset($db_group_prototypes[$group_prototype['name']]);
				}
				else {
					$ins_group_prototypes[] = ['hostid' => $host['hostid']] + $group_prototype;
					$changed = true;
				}
			}
			unset($group_prototype);

			if ($db_group_prototypes) {
				$del_group_prototypeids = array_merge($del_group_prototypeids,
					array_column($db_group_prototypes, 'group_prototypeid')
				);
				$changed = true;
			}

			if ($db_hosts !== null) {
				if ($changed) {
					$upd_hostids[$i] = $host['hostid'];
				}
				else {
					unset($host['groupPrototypes']);
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
	private static function updateHostInventories(array $hosts, array $db_hosts = null,
			array &$upd_hostids = null): void {
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
		$db_hosts = DBfetchArrayAssoc(DBselect(
			'SELECT hd.parent_itemid AS ruleid,h.hostid,h.host,h.name,h.custom_interfaces,h.flags,h.status,h.discover,'.
				dbConditionCoalesce('hi.inventory_mode', HOST_INVENTORY_DISABLED, 'inventory_mode').
			' FROM host_discovery hd'.
			' INNER JOIN hosts h ON hd.hostid=h.hostid'.
			' LEFT JOIN host_inventory hi ON h.hostid=hi.hostid'.
			' WHERE '.dbConditionId('hd.parent_itemid', $ruleids).
				' AND '.dbConditionInt('h.flags', [ZBX_FLAG_DISCOVERY_PROTOTYPE])
		), 'hostid');

		if (!$db_hosts) {
			return;
		}

		self::checkRulesAndAddParentDetails($db_hosts);

		$hosts = [];

		foreach ($db_hosts as $db_host) {
			$host = $db_host;

			if ($db_host['custom_interfaces'] == HOST_PROT_INTERFACES_CUSTOM) {
				$host += ['interfaces' => []];
			}

			$hosts[] = $host + [
				'groupLinks' => [],
				'groupPrototypes' => [],
				'templates' => [],
				'tags' => [],
				'macros' => []
			];
		}

		$this->addAffectedObjects($hosts, $db_hosts);

		$hosts = array_values($db_hosts);

		foreach ($hosts as &$host) {
			if (array_key_exists('interfaces', $host)) {
				$host['interfaces'] = array_values($host['interfaces']);
			}

			$host['groupLinks'] = array_values($host['groupLinks']);
			$host['groupPrototypes'] = array_values($host['groupPrototypes']);
			$host['templates'] = array_values($host['templates']);
			$host['tags'] = array_values($host['tags']);
			$host['macros'] = array_values($host['macros']);
		}
		unset($host);

		$this->inherit($hosts, [], $hostids);
	}

	/**
	 * @param array      $ruleids
	 * @param array|null $hostids
	 */
	public function unlinkTemplateObjects(array $ruleids, array $hostids = null): void {
		$hostids_condition = $hostids ? ' AND '.dbConditionId('i.hostid', $hostids) : '';

		$result = DBselect(
			'SELECT i.hostid AS parent_hostid,hh.status AS parent_status,hd.hostid,'.
				'h.host,h.custom_interfaces,h.uuid,h.templateid'.
			' FROM items i'.
			' INNER JOIN host_discovery hd ON i.itemid=hd.parent_itemid'.
			' INNER JOIN hosts h ON hd.hostid=h.hostid'.
			' LEFT JOIN host_inventory hi ON h.hostid=hi.hostid'.
			' INNER JOIN hosts hh ON i.hostid=hh.hostid'.
			' WHERE '.dbConditionId('i.itemid', $ruleids).
				$hostids_condition
		);

		$hosts = [];
		$db_hosts = [];
		$i = 0;
		$tpl_hostids = [];

		$nested_objects = [
			'groupLinks' => [],
			'groupPrototypes' => []
		];

		while ($row = DBfetch($result)) {
			$host = ['templateid' => 0]
				+ array_intersect_key($row,	array_flip(['hostid', 'host', 'uuid', 'custom_interfaces']))
				+ $nested_objects;

			if ($row['parent_status'] == HOST_STATUS_TEMPLATE) {
				$host['uuid'] = generateUuidV4();
				$host += array_intersect_key($row, array_flip(['parent_hostid', 'parent_status']));

				$tpl_hostids[$i] = $row['hostid'];
			}

			$hosts[$i++] = $host;
			$db_hosts[$host['hostid']] = $row;
		}

		if ($hosts) {
			$this->addAffectedObjects($hosts, $db_hosts);

			foreach ($hosts as &$host) {
				$host = array_diff_key($host, $nested_objects)
					+ array_intersect_key($db_hosts[$host['hostid']], $nested_objects);

				if (array_key_exists('groupPrototypes', $host)) {
					foreach ($host['groupPrototypes'] as $i => $foo) {
						$host['groupPrototypes'][$i]['templateid'] = 0;
					}
				}

				if (array_key_exists('groupLinks', $host)) {
					foreach ($host['groupLinks'] as $i => $foo) {
						$host['groupLinks'][$i]['templateid'] = 0;
					}
				}
			}

			if ($tpl_hostids) {
				$_hosts = array_intersect_key($hosts, $tpl_hostids);
				$_db_hosts = array_intersect_key($db_hosts, array_flip($tpl_hostids));

				$this->inherit($_hosts, $_db_hosts);
			}

			$this->updateForce($hosts, $db_hosts);
		}
	}

	/**
	 * @inheritDoc
	 */
	protected function inherit(array $hosts, array $db_hosts = [], array $hostids = null): void {
		$tpl_links = self::getTemplateLinks($hosts, $hostids);

		if ($hostids === null) {
			self::filterObjectsToInherit($hosts, $db_hosts, $tpl_links);

			if (!$hosts) {
				return;
			}
		}

		self::checkDoubleInheritedNames($hosts, $db_hosts, $tpl_links);

		$chunks = self::getInheritChunks($hosts, $tpl_links);

		foreach ($chunks as $chunk) {
			$_hosts = array_intersect_key($hosts, array_flip($chunk['host_indexes']));
			$_db_hosts = array_intersect_key($db_hosts, array_flip(array_column($_hosts, 'hostid')));
			$_hostids = array_keys($chunk['hosts']);

			$this->inheritChunk($_hosts, $_db_hosts, $tpl_links, $_hostids);
		}
	}

	/**
	 * Filter out inheritable host prototypes.
	 *
	 * @param array $hosts
	 * @param array $db_hosts
	 * @param array $tpl_links
	 */
	protected static function filterObjectsToInherit(array &$hosts, array &$db_hosts, array $tpl_links): void {
		foreach ($hosts as $i => $host) {
			if (!array_key_exists($host['parent_hostid'], $tpl_links)) {
				unset($hosts[$i]);

				if (array_key_exists($host['hostid'], $db_hosts)) {
					unset($db_hosts[$host['hostid']]);
				}
			}
		}
	}

	/**
	 * @param array      $hosts
	 * @param array|null $hostids
	 *
	 * @return array
	 */
	protected static function getTemplateLinks(array $hosts, ?array $hostids): array {
		if ($hostids !== null) {
			$db_hosts = DB::select('hosts', [
				'output' => ['hostid', 'status'],
				'hostids' => $hostids,
				'preservekeys' => true
			]);

			$tpl_links = [];

			foreach ($hosts as $host) {
				$tpl_links[$host['parent_hostid']] = $db_hosts;
			}
		}
		else {
			$templateids = [];

			foreach ($hosts as $host) {
				if ($host['parent_status'] == HOST_STATUS_TEMPLATE) {
					$templateids[$host['parent_hostid']] = true;
				}
			}

			if (!$templateids) {
				return [];
			}

			$result = DBselect(
				'SELECT ht.templateid,ht.hostid,h.status'.
				' FROM hosts_templates ht,hosts h'.
				' WHERE ht.hostid=h.hostid'.
					' AND '.dbConditionId('ht.templateid', array_keys($templateids)).
					' AND '.dbConditionInt('h.flags', [ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED])
			);

			$tpl_links = [];

			while ($row = DBfetch($result)) {
				$tpl_links[$row['templateid']][$row['hostid']] = [
					'hostid' => $row['hostid'],
					'status' => $row['status']
				];
			}
		}

		return $tpl_links;
	}

	/**
	 * Check that no host prototypes with repeating host name would be inherited to a single host or template.
	 *
	 * @param array $hosts
	 * @param array $db_hosts
	 * @param array $tpl_links
	 *
	 * @throws APIException
	 */
	protected static function checkDoubleInheritedNames(array $hosts, array $db_hosts, array $tpl_links): void {
		$host_indexes = [];

		foreach ($hosts as $i => $host) {
			if (array_key_exists($host['hostid'], $db_hosts) && $host['host'] === $db_hosts[$host['hostid']]['host']) {
				continue;
			}

			$host_indexes[$host['host']][] = $i;
		}

		foreach ($host_indexes as $name => $indexes) {
			if (count($indexes) == 1) {
				continue;
			}

			$tpl_hosts = array_column(array_intersect_key($hosts, array_flip($indexes)), null, 'parent_hostid');
			$templateids = array_keys($tpl_hosts);
			$template_count = count($templateids);

			for ($i = 0; $i < $template_count - 1; $i++) {
				for ($j = $i + 1; $j < $template_count; $j++) {
					$same_hosts = array_intersect_key($tpl_links[$templateids[$i]], $tpl_links[$templateids[$j]]);

					if ($same_hosts) {
						$same_host = reset($same_hosts);

						$hosts = DB::select('hosts', [
							'output' => ['hostid', 'host'],
							'hostids' => [$templateids[$i], $templateids[$j], $same_host['hostid']],
							'preservekeys' => true
						]);

						$target_is_host = in_array($same_host['status'],
							[HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED]
						);

						$error = $target_is_host
							? _('Cannot inherit host prototypes with host name "%1$s" of both "%2$s" and "%3$s" templates, because the key must be unique on host "%4$s".')
							: _('Cannot inherit host prototypes with host name "%1$s" of both "%2$s" and "%3$s" templates, because the key must be unique on template "%4$s".');

						self::exception(ZBX_API_ERROR_PARAMETERS, sprintf($error, $name,
							$hosts[$templateids[$i]]['host'], $hosts[$templateids[$j]]['host'],
							$hosts[$same_host['hostid']]['host']
						));
					}
				}
			}
		}
	}

	/**
	 * Get host prototypes in chunks to inherit.
	 *
	 * @param array $hosts
	 * @param array $tpl_links
	 *
	 * @return array
	 */
	protected static function getInheritChunks(array $hosts, array $tpl_links): array {
		$chunks = [
			[
				'host_indexes' => [],
				'hosts' => [],
				'size' => 0
			]
		];
		$last = 0;

		foreach ($hosts as $i => $host) {
			$hosts_chunks = array_chunk($tpl_links[$host['parent_hostid']], self::INHERIT_CHUNK_SIZE, true);

			foreach ($hosts_chunks as $hosts) {
				if ($chunks[$last]['size'] < self::INHERIT_CHUNK_SIZE) {
					$_hosts = array_slice($hosts, 0, self::INHERIT_CHUNK_SIZE - $chunks[$last]['size'], true);

					$can_add_hosts = true;

					foreach ($chunks[$last]['host_indexes'] as $_i) {
						$new_hosts = array_diff_key($_hosts, $chunks[$last]['hosts']);

						if (array_intersect_key($tpl_links[$hosts[$_i]['parent_hostid']], $new_hosts)) {
							$can_add_hosts = false;
							break;
						}
					}

					if ($can_add_hosts) {
						$chunks[$last]['host_indexes'][] = $i;
						$chunks[$last]['hosts'] += $_hosts;
						$chunks[$last]['size'] += count($_hosts);

						$hosts = array_diff_key($hosts, $_hosts);
					}
				}

				if ($hosts) {
					$chunks[++$last] = [
						'host_indexes' => [$i],
						'hosts' => $hosts,
						'size' => count($hosts)
					];
				}
				else {
					break 2;
				}
			}
		}

		return $chunks;
	}

	/**
	 * @param array $hosts
	 * @param array $db_hosts
	 * @param array $tpl_links
	 * @param array $hostids
	 */
	protected function inheritChunk(array $hosts, array $db_hosts, array $tpl_links, array $hostids): void {
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
			$lld_links = self::getLldLinks($hosts_to_link);

			$upd_db_hosts = $this->getChildObjectsUsingName($hosts_to_link, $hostids, $lld_links);

			if ($upd_db_hosts) {
				$upd_hosts = self::getUpdChildObjectsUsingName($hosts_to_link, $upd_db_hosts);
			}

			$ins_hosts = self::getInsChildObjects($hosts_to_link, $upd_db_hosts, $tpl_links, $hostids, $lld_links);
		}

		if ($hosts_to_update) {
			$_upd_db_hosts = self::getChildObjectsUsingTemplateid($hosts_to_update, $db_hosts, $hostids);
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
	 * @param array $hosts
	 *
	 * @return array
	 */
	private static function getLldLinks(array $hosts): array {
		$result = DBselect(
			'SELECT i.itemid AS ruleid,ht.hostid,ht.templateid'.
			' FROM items i,items ii,hosts h,hosts_templates ht'.
			' WHERE i.templateid=ii.itemid'.
				' AND ii.hostid=h.hostid'.
				' AND h.hostid=ht.templateid'.
				' AND '.dbConditionId('i.templateid', array_column($hosts, 'ruleid', 'ruleid'))
		);

		$lld_links = [];

		while ($row = DBfetch($result)) {
			$lld_links[$row['templateid']][$row['hostid']] = $row['ruleid'];
		}

		return $lld_links;
	}

	/**
	 * @param array $hosts
	 * @param array $db_hosts
	 * @param array $hostids
	 *
	 * @return array
	 */
	private function getChildObjectsUsingTemplateid(array $hosts, array $db_hosts, array $hostids): array {
		$upd_db_hosts = DBfetchArrayAssoc(DBselect(
			'SELECT h.hostid,h.host,h.name,h.custom_interfaces,h.status,h.discover,h.templateid,'.
				'hd.parent_itemid AS ruleid,i.hostid AS parent_hostid,hh.status AS parent_status,'.
				dbConditionCoalesce('hi.inventory_mode', HOST_INVENTORY_DISABLED, 'inventory_mode').
			' FROM hosts h'.
			' INNER JOIN host_discovery hd ON h.hostid=hd.hostid'.
			' LEFT JOIN host_inventory hi ON h.hostid=hi.hostid'.
			' INNER JOIN items i'.
			' INNER JOIN hosts hh'.
			' WHERE hd.parent_itemid=i.itemid'.
				' AND hh.hostid=i.hostid'.
				' AND '.dbConditionId('h.templateid', array_keys($db_hosts)).
				' AND '.dbConditionId('i.hostid', $hostids)
		), 'hostid');

		if ($upd_db_hosts) {
			$hosts = array_column($hosts, null, 'hostid');
			$upd_hosts = [];

			foreach ($upd_db_hosts as $upd_db_host) {
				$host = $hosts[$upd_db_host['templateid']];
				$db_host = $db_hosts[$upd_db_host['templateid']];

				$upd_host = [
					'hostid' => $upd_db_host['hostid'],
					'custom_interfaces' => $host['custom_interfaces']
				] + array_intersect_key($upd_db_host, array_flip(['parent_hostid', 'parent_status']));

				if (array_key_exists('interfaces', $host)) {
					$upd_host += ['interfaces' => []];
				}

				$upd_host += array_intersect_key([
					'groupLinks' => [],
					'groupPrototypes' => [],
					'templates' => [],
					'tags' => [],
					'macros' => []
				], $db_host);

				$upd_hosts[] = $upd_host;
			}

			$this->addAffectedObjects($upd_hosts, $upd_db_hosts);
		}

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
		$upd_hosts = [];

		foreach ($hosts as &$host) {
			if (!array_key_exists($host['hostid'], $db_hosts)) {
				continue;
			}

			$host = self::unsetNestedObjectIds($host);
		}

		$parent_indexes = array_flip(array_column($hosts, 'hostid'));
		$upd_hosts = [];

		foreach ($upd_db_hosts as $upd_db_host) {
			$host = $hosts[$parent_indexes[$upd_db_host['templateid']]];

			$upd_host = array_intersect_key($upd_db_host,
				array_flip(['hostid', 'templateid', 'ruleid', 'parent_hostid', 'parent_status'])
			) + $host;

			if (array_key_exists('groupLinks', $upd_host)) {
				foreach ($upd_host['groupLinks'] as &$group_link) {
					$matched = false;

					foreach ($upd_db_host['groupLinks'] as $db_group_link) {
						if (bccomp($group_link['group_prototypeid'], $db_group_link['templateid']) == 0
								|| bccomp($group_link['groupid'], $db_group_link['groupid']) == 0) {
							$group_link['templateid'] = $group_link['group_prototypeid'];
							$group_link['group_prototypeid'] = $db_group_link['group_prototypeid'];

							$matched = true;
							break;
						}
					}

					if ($matched) {
						continue;
					}

					$group_link['templateid'] = $group_link['group_prototypeid'];
					unset($group_link['group_prototypeid']);
				}
				unset($group_link);
			}

			if (array_key_exists('groupPrototypes', $upd_host)) {
				foreach ($upd_host['groupPrototypes'] as &$group_prototype) {
					$matched = false;

					foreach ($upd_db_host['groupPrototypes'] as $db_group_prototype) {
						if (bccomp($group_prototype['group_prototypeid'], $db_group_prototype['templateid']) == 0
								|| $group_prototype['name'] === $db_group_prototype['name']) {
							$group_prototype['templateid'] = $group_prototype['group_prototypeid'];
							$group_prototype['group_prototypeid'] = $db_group_prototype['group_prototypeid'];

							$matched = true;
							break;
						}
					}

					if ($matched) {
						continue;
					}

					$group_prototype['templateid'] = $group_prototype['group_prototypeid'];
					unset($group_prototype['group_prototypeid']);
				}
				unset($group_prototype);
			}

			if (array_key_exists('macros', $upd_host)) {
				$db_macros = $db_hosts[$host['hostid']]['macros'];

				foreach ($upd_host['macros'] as &$macro) {
					if (array_key_exists($macro['hostmacroid'], $db_macros)) {
						$db_macro = $db_macros[$macro['hostmacroid']];

						$macro['hostmacroid'] = key(array_filter($upd_db_host['macros'],
							static function (array $upd_db_macro) use ($db_macro): bool {
								return $upd_db_macro['macro'] === $db_macro['macro']
									&& $upd_db_macro['type'] == $db_macro['type']
									&& $upd_db_macro['value'] === $db_macro['value']
									&& $upd_db_macro['description'] === $db_macro['description'];
							}
						));
					}
					else {
						unset($macro['hostmacroid']);
					}
				}
				unset($macro);
			}

			$upd_hosts[] = $upd_host;
		}

		return $upd_hosts;
	}

	/**
	 * @param array $host
	 *
	 * @return array
	 */
	private static function unsetNestedObjectIds(array $host): array {
		if (array_key_exists('interfaces', $host)) {
			foreach ($host['interfaces'] as &$interface) {
				unset($interface['interfaceid']);
			}
			unset($interface);
		}

		if (array_key_exists('templates', $host)) {
			foreach ($host['templates'] as &$template) {
				unset($template['hosttemplateid']);
			}
			unset($template);
		}

		if (array_key_exists('tags', $host)) {
			foreach ($host['tags'] as &$tag) {
				unset($tag['hosttagid']);
			}
			unset($tag);
		}

		return $host;
	}

	/**
	 * @param array $items
	 * @param array $hostids
	 * @param array $lld_links
	 *
	 * @return array
	 */
	private function getChildObjectsUsingName(array $hosts, array $hostids, array $lld_links): array {
		$result = DBselect(
			'SELECT i.templateid AS parent_ruleid,i.hostid AS parent_hostid,hh.status AS parent_status,'.
				'i.itemid AS ruleid,'.
				'hd.hostid,h.uuid,h.host,h.name,h.custom_interfaces,h.status,h.discover,h.flags,h.templateid,'.
				dbConditionCoalesce('hi.inventory_mode', HOST_INVENTORY_DISABLED, 'inventory_mode').
			' FROM items i'.
			' INNER JOIN host_discovery hd ON i.itemid=hd.parent_itemid'.
			' INNER JOIN hosts h ON hd.hostid=h.hostid'.
			' LEFT JOIN host_inventory hi ON h.hostid=hi.hostid'.
			' INNER JOIN hosts hh ON i.hostid=hh.hostid'.
			' WHERE '.dbConditionId('i.templateid', array_column($hosts, 'ruleid', 'ruleid')).
				' AND '.dbConditionString('h.host', array_column($hosts, 'host', 'host')).
				' AND '.dbConditionId('i.hostid', $hostids)
		);

		$upd_db_hosts = [];
		$host_indexes = [];

		while ($row = DBfetch($result)) {
			foreach ($hosts as $i => $host) {
				if (bccomp($row['parent_ruleid'], $host['ruleid']) == 0 && $row['host'] === $host['host']) {
					if ($row['flags'] == $host['flags'] &&
							($row['templateid'] == 0 || bccomp($row['templateid'], $host['hostid']) == 0)
							&& bccomp($row['ruleid'], $lld_links[$host['parent_hostid']][$row['parent_hostid']]) == 0) {
						$upd_db_hosts[$row['hostid']] = $row;
						$host_indexes[$row['hostid']] = $i;
					}
					else {
						self::showObjectMismatchError($host, $row);
					}
				}
			}
		}

		if ($upd_db_hosts) {
			$upd_hosts = [];

			foreach ($upd_db_hosts as $upd_db_host) {
				$host = $hosts[$host_indexes[$upd_db_host['hostid']]];

				$upd_host = [
					'hostid' => $upd_db_host['hostid'],
					'custom_interfaces' => $host['custom_interfaces']
				];

				if (array_key_exists('interfaces', $host)) {
					$upd_host += ['interfaces' => []];
				}

				$upd_host += [
					'groupLinks' => [],
					'groupPrototypes' => [],
					'templates' => [],
					'tags' => [],
					'macros' => []
				];

				$upd_hosts[] = $upd_host;
			}

			$this->addAffectedObjects($upd_hosts, $upd_db_hosts);
		}

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

		foreach ($hosts as $i => $host) {
			$host = self::unsetNestedObjectIds($host);
			$parent_indexes[$host['ruleid']][$host['host']] = $i;
		}

		$upd_hosts = [];
		$nested_objects = array_fill_keys(
			['interfaces', 'groupPrototypes', 'templates', 'tags', 'groupLinks', 'macros'], []
		);

		foreach ($upd_db_hosts as $upd_db_host) {
			$host = $hosts[$parent_indexes[$upd_db_host['parent_ruleid']][$upd_db_host['host']]];

			$upd_host = array_intersect_key($upd_db_host,
				array_flip(['hostid', 'ruleid', 'parent_hostid', 'parent_status'])
			);
			$upd_host += ['templateid' => $host['hostid']] + $host + $nested_objects;

			foreach ($upd_host['groupLinks'] as &$group_link) {
				foreach ($upd_db_host['groupLinks'] as $db_group_link) {
					if (bccomp($group_link['groupid'], $db_group_link['groupid']) == 0) {
						$group_link['templateid'] = $group_link['group_prototypeid'];
						$group_link['group_prototypeid'] = $db_group_link['group_prototypeid'];
						break;
					}
				}

				$group_link['templateid'] = $group_link['group_prototypeid'];
				unset($group_link['group_prototypeid']);
			}
			unset($group_link);

			foreach ($upd_host['groupPrototypes'] as &$group_prototype) {
				foreach ($upd_db_host['groupPrototypes'] as $db_group_prototype) {
					if ($group_prototype['name'] === $db_group_prototype['name']) {
						$group_prototype['templateid'] = $group_prototype['group_prototypeid'];
						$group_prototype['group_prototypeid'] = $db_group_prototype['group_prototypeid'];
						break;
					}
				}

				$group_prototype['templateid'] = $group_prototype['group_prototypeid'];
				unset($group_prototype['group_prototypeid']);
			}
			unset($group_prototype);

			foreach ($upd_host['macros'] as &$macro) {
				$hostmacroid = key(array_filter($upd_db_host['macros'],
					static function (array $upd_db_macro) use ($macro): bool {
						return $upd_db_macro['macro'] === $macro['macro']
							&& $upd_db_macro['type'] == $macro['type']
							&& $upd_db_macro['value'] === $macro['value']
							&& (!array_key_exists('description', $macro)
								|| $upd_db_macro['description'] === $macro['description']);
					}
				));

				if ($hostmacroid !== null) {
					$macro['hostmacroid'] = $hostmacroid;
				}
				else {
					unset($macro['hostmacroid']);
				}
			}
			unset($macro);

			$upd_hosts[] = $upd_host;
		}

		return $upd_hosts;
	}

	/**
	 * @param array $host
	 * @param array $upd_db_host
	 *
	 * @throws APIException
	 */
	protected static function showObjectMismatchError(array $host, array $upd_db_host): void {
		$target_is_host = in_array($upd_db_host['parent_status'], [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED]);

		$hosts = DB::select('hosts', [
			'output' => ['host', 'status'],
			'hostids' => [$host['hostid'], $upd_db_host['hostid']],
			'preservekeys' => true
		]);

		$error = '';

		if ($upd_db_host['templateid'] == 0) {
			switch ($upd_db_host['flags']) {
				case ZBX_FLAG_DISCOVERY_NORMAL:
					$error = $target_is_host
						? _('Cannot inherit host prototype with host name "%1$s" of template "%2$s" to host "%3$s", because a host with the same host name already exists.')
						: _('Cannot inherit host prototype with host name "%1$s" of template "%2$s" to template "%3$s", because a host with the same host name already exists.');
					break;

				case ZBX_FLAG_DISCOVERY_PROTOTYPE:
					$error = $target_is_host
						? _('Cannot inherit host prototype with host name "%1$s" of template "%2$s" to host "%3$s", because a host prototype with the same host name already exists.')
						: _('Cannot inherit host prototype with host name "%1$s" of template "%2$s" to template "%3$s", because a host prototype host with the same host name already exists.');
					break;

				case ZBX_FLAG_DISCOVERY_CREATED:
					$error = $target_is_host
						? _('Cannot inherit host prototype with host name "%1$s" of template "%2$s" to host "%3$s", because a discovered host with the same host name already exists.')
						: _('Cannot inherit host prototype with host name "%1$s" of template "%2$s" to template "%3$s", because a discovered host with the same host name already exists.');
					break;
			}
		}

		if ($error) {
			self::exception(ZBX_API_ERROR_PARAMETERS, sprintf($error, $hosts[$host['hostid']]['host'], $upd_db_host['host'],
				$hosts[$upd_db_host['hostid']]['host']
			));
		}

		$template = DBfetch(DBselect(
			'SELECT h.host'.
			' FROM items i,hosts h'.
			' WHERE i.hostid=h.hostid'.
				' AND '.dbConditionId('i.itemid', [$upd_db_host['parent_ruleid']])
		));

		$error = $target_is_host
			? _('Cannot inherit host prototype with host name "%1$s" of template "%2$s" to host "%3$s", because a host prototype with the same host name is already inherited from template "%4$s".')
			: _('Cannot inherit host prototype with host name "%1$s" of template "%2$s" to template "%3$s", because a host prototype with with the same host name is already inherited from template "%4$s".');

		self::exception(ZBX_API_ERROR_PARAMETERS, sprintf($error, $hosts[$host['hostid']]['host'], $upd_db_host['host'],
			$hosts[$upd_db_host['hostid']]['host'], $template['host']
		));
	}

	/**
	 * @param array $hosts
	 * @param array $upd_db_items
	 * @param array $tpl_links
	 * @param array $hostids
	 * @param array $lld_links
	 *
	 * @return array
	 */
	private static function getInsChildObjects(array $hosts, array $upd_db_hosts, array $tpl_links,
			array $hostids, array $lld_links): array {

		$ins_hosts = [];

		$upd_host_names = [];

		foreach ($upd_db_hosts as $upd_db_host) {
			$upd_host_names[$upd_db_host['parent_hostid']][] = $upd_db_host['host'];
		}

		foreach ($hosts as $host) {
			$host['uuid'] = '';
			$host = self::unsetNestedObjectIds($host);

			foreach ($tpl_links[$host['parent_hostid']] as $upd_host) {
					if (!in_array($upd_host['hostid'], $hostids)
						|| (array_key_exists($upd_host['hostid'], $upd_host_names)
								&& in_array($host['host'], $upd_host_names[$upd_host['hostid']]))) {
					continue;
				}

				$ins_host = [
					'ruleid' => $lld_links[$host['parent_hostid']][$upd_host['hostid']],
					'templateid' => $host['hostid'],
					'parent_status' => $upd_host['status'],
					'parent_hostid' => $upd_host['hostid']
				] + array_diff_key($host, array_flip(['hostid']));

				foreach ($ins_host['groupLinks'] as &$group_link) {
					$group_link['templateid'] = $group_link['group_prototypeid'];
					unset($group_link['group_prototypeid']);
				}
				unset($group_link);

				if (array_key_exists('groupPrototypes', $ins_host)) {
					foreach ($ins_host['groupPrototypes'] as &$group_prototype) {
						$group_prototype['templateid'] = $group_prototype['group_prototypeid'];
						unset($group_prototype['group_prototypeid']);
					}
					unset($group_prototype);
				}

				$ins_hosts[] = $ins_host;
			}
		}

		return $ins_hosts;
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
	private function validateDelete(array &$hostids, array &$db_hosts = null): void {
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

		$db_group_prototypes = DBfetchArray(DBselect(
			'SELECT gp.group_prototypeid,gp.name'.
			' FROM group_prototype gp'.
			' WHERE '.dbConditionId('gp.hostid', $hostids)
		));

		if ($db_group_prototypes) {
			self::deleteGroupPrototypes(array_column($db_group_prototypes, 'group_prototypeid'));
		}

		$discovered_hosts = DBfetchArrayAssoc(DBselect(
			'SELECT hd.hostid,h.host'.
			' FROM host_discovery hd,hosts h'.
			' WHERE hd.hostid=h.hostid'.
				' AND '.dbConditionId('hd.parent_hostid', $hostids)
		), 'hostid');

		CHost::validateDeleteForce($discovered_hosts);
		CHost::deleteForce($discovered_hosts);

		DB::delete('interface', ['hostid' => $hostids]);
		DB::delete('hosts_templates', ['hostid' => $hostids]);
		DB::delete('host_tag', ['hostid' => $hostids]);
		DB::delete('hostmacro', ['hostid' => $hostids]);
		DB::delete('host_inventory', ['hostid' => $hostids]);

		DB::delete('hosts', ['hostid' => $hostids]);

		self::addAuditLog(CAudit::ACTION_DELETE, CAudit::RESOURCE_HOST_PROTOTYPE, $db_hosts);
	}

	/**
	 * @param array $db_hosts
	 */
	private static function addInheritedHostPrototypes(array &$db_hosts): void {
		$_db_hosts = $db_hosts;

		do {
			$options = [
				'output' => ['hostid', 'host'],
				'filter' => ['templateid' => array_keys($_db_hosts)],
				'preservekeys' => true
			];
			$_db_hosts = DB::select('hosts', $options);

			$db_hosts += $_db_hosts;
		} while ($_db_hosts);
	}

	/**
	 *@param array $del_group_prototypeids
	 */
	private static function deleteGroupPrototypes(array $del_group_prototypeids): void {
		if (!$del_group_prototypeids) {
			return;
		}

		$_del_group_prototypeids = $del_group_prototypeids;

		do {
			$options = [
				'output' => ['group_prototypeid'],
				'filter' => [
					'templateid' => $_del_group_prototypeids
				]
			];
			$result = DBselect(DB::makeSql('group_prototype', $options));
			$_del_group_prototypeids = [];

			while ($row = DBfetch($result)) {
				$_del_group_prototypeids[] = $row['group_prototypeid'];
				$del_group_prototypeids[] = $row['group_prototypeid'];
			}
		}
		while ($_del_group_prototypeids);

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
			'where' => ['group_prototypeid' => $del_group_prototypeids]
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
			'SELECT gd.groupid,g.name'.
			' FROM group_discovery gd,hstgrp g'.
			' WHERE gd.groupid=g.groupid'.
				' AND '.dbConditionId('gd.parent_group_prototypeid', $group_prototypeids)
		), 'groupid');

		if ($db_groups) {
			CHostGroup::validateDeleteForce($db_groups);
			CHostGroup::deleteForce($db_groups);
		}
	}
}
