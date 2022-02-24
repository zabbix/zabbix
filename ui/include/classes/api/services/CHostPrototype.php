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
			'filter' =>					['type' => API_OBJECT, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => [
				'hostid' =>					['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'host' =>					['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'name' =>					['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'status' =>					['type' => API_INTS32, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'in' => implode(',', [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED])],
				'templateid' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'inventory_mode' =>			['type' => API_INTS32, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'in' => implode(',', [HOST_INVENTORY_DISABLED, HOST_INVENTORY_MANUAL, HOST_INVENTORY_AUTOMATIC])]
			]],
			'search' =>					['type' => API_OBJECT, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => [
				'host' =>					['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'name' =>					['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE]
			]],
			'searchByAny' =>			['type' => API_BOOLEAN, 'default' => false],
			'startSearch' =>			['type' => API_FLAG, 'default' => false],
			'excludeSearch' =>			['type' => API_FLAG, 'default' => false],
			'searchWildcardsEnabled' =>	['type' => API_BOOLEAN, 'default' => false],
			// output
			'output' =>					['type' => API_OUTPUT, 'in' => 'inventory_mode,'.implode(',', $output_fields), 'default' => $output_fields],
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
	 * @param array $host_prototypes
	 *
	 * @return array
	 */
	public function create(array $host_prototypes): array {
		$this->validateCreate($host_prototypes);

		$this->createForce($host_prototypes);
		[$tpl_host_prototypes] = $this->getTemplatedObjects($host_prototypes);

		if ($tpl_host_prototypes) {
			$this->inherit($tpl_host_prototypes);
		}

		return ['hostids' => array_column($host_prototypes, 'hostid')];
	}

	/**
	 * @param array $host_prototypes
	 *
	 * @throws APIException if the input is invalid.
	 */
	private function validateCreate(array &$host_prototypes): void {
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

		if (!CApiInputValidator::validate($api_input_rules, $host_prototypes, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		self::checkAndAddUuid($host_prototypes);
		self::checkDiscoveryRules($host_prototypes);
		self::checkDuplicates($host_prototypes);
		self::checkMainInterfaces($host_prototypes);
		self::checkGroupLinks($host_prototypes);
		$this->checkTemplates($host_prototypes);
	}

	/**
	 * @param array $host_prototypes
	 * @param bool  $inherited
	 */
	protected function createForce(array &$host_prototypes, bool $inherited = false): void {
		foreach ($host_prototypes as &$host_prototype) {
			$host_prototype['flags'] = ZBX_FLAG_DISCOVERY_PROTOTYPE;
		}
		unset($host_prototype);

		$hostids = DB::insert('hosts', $host_prototypes);

		foreach ($host_prototypes as $index => &$host_prototype) {
			$host_prototype['hostid'] = $hostids[$index];
		}
		unset($host_prototype);

		if (!$inherited) {
			$this->checkTemplatesLinks($host_prototypes);
		}

		self::createHostDiscoveries($host_prototypes);

		self::updateInterfaces($host_prototypes);
		self::updateGroupLinks($host_prototypes);
		self::updateGroupPrototypes($host_prototypes);
		$this->updateTemplates($host_prototypes);
		$this->updateTagsNew($host_prototypes);
		$this->updateMacros($host_prototypes);
		self::updateHostInventories($host_prototypes);

		self::addAuditLog(CAudit::ACTION_ADD, CAudit::RESOURCE_HOST_PROTOTYPE, $host_prototypes);
	}

	/**
	 * @param array $host_prototypes
	 *
	 * @return array
	 */
	public function update(array $host_prototypes): array {
		$this->validateUpdate($host_prototypes, $db_host_prototypes);

		$this->updateForce($host_prototypes, $db_host_prototypes);

		[$tpl_host_prototypes, $tpl_db_host_prototypes] =
			$this->getTemplatedObjects($host_prototypes, $db_host_prototypes);

		if ($tpl_host_prototypes) {
			$this->inherit($tpl_host_prototypes, $tpl_db_host_prototypes);
		}

		return ['hostids' => array_column($host_prototypes, 'hostid')];
	}

	/**
	 * @param array      $host_prototypes
	 * @param array|null $db_host_prototypes
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateUpdate(array &$host_prototypes, array &$db_host_prototypes = null): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE | API_ALLOW_UNEXPECTED, 'uniq' => [['hostid']], 'fields' => [
			'hostid' =>	['type' => API_ID, 'flags' => API_REQUIRED],
			'ruleid' => ['type' => API_UNEXPECTED]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $host_prototypes, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$count = $this->get([
			'countOutput' => true,
			'hostids' => array_column($host_prototypes, 'hostid'),
			'editable' => true
		]);

		if (count($host_prototypes) != $count) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		$db_host_prototypes = DBfetchArrayAssoc(DBselect(
			'SELECT h.hostid,h.host,h.name,h.custom_interfaces,h.status,h.discover,h.templateid,'.
				'hd.parent_itemid AS ruleid,'.
				dbConditionCoalesce('hi.inventory_mode', HOST_INVENTORY_DISABLED, 'inventory_mode').
			' FROM hosts h'.
			' INNER JOIN host_discovery hd ON h.hostid=hd.hostid'.
			' LEFT JOIN host_inventory hi ON h.hostid=hi.hostid'.
			' WHERE '.dbConditionId('h.hostid', array_column($host_prototypes, 'hostid'))
		), 'hostid');

		foreach ($host_prototypes as $i => &$host_prototype) {
			if ($db_host_prototypes[$host_prototype['hostid']]['templateid'] == 0) {
				$host_prototype += array_intersect_key($db_host_prototypes[$host_prototype['hostid']],
					array_flip(['host', 'name', 'custom_interfaces'])
				);

				$api_input_rules = self::getValidationRules();
			}
			else {
				$api_input_rules = self::getInheritedValidationRules();
			}

			if (!CApiInputValidator::validate($api_input_rules, $host_prototype, '/'.($i + 1), $error)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, $error);
			}
		}
		unset($host_prototype);

		$host_prototypes = $this->extendObjectsByKey($host_prototypes, $db_host_prototypes, 'hostid',
			['ruleid', 'custom_interfaces']
		);

		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_ALLOW_UNEXPECTED, 'uniq' => [['ruleid', 'host'], ['ruleid', 'name']], 'fields' => [
			'ruleid' =>	['type' => API_ID],
			'host' =>	['type' => API_H_NAME],
			'name' =>	['type' => API_STRING_UTF8]
		]];

		if (!CApiInputValidator::validateUniqueness($api_input_rules, $host_prototypes, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$this->addAffectedObjects($host_prototypes, $db_host_prototypes);

		self::checkDuplicates($host_prototypes, $db_host_prototypes);
		self::checkMainInterfaces($host_prototypes);
		self::checkGroupLinks($host_prototypes, $db_host_prototypes);
		$this->checkTemplates($host_prototypes, $db_host_prototypes);
		$this->checkTemplatesLinks($host_prototypes, $db_host_prototypes);
		$host_prototypes = parent::validateHostMacros($host_prototypes, $db_host_prototypes);
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
												['else' => true, 'type' => API_UNEXPECTED]
						]],
						'contextname' =>	['type' => API_MULTIPLE, 'rules' => [
												['if' => ['field' => 'version', 'in' => SNMP_V3], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('interface_snmp', 'contextname')],
												['else' => true, 'type' => API_UNEXPECTED]
						]],
						'securityname' =>	['type' => API_MULTIPLE, 'rules' => [
												['if' => ['field' => 'version', 'in' => SNMP_V3], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('interface_snmp', 'securityname')],
												['else' => true, 'type' => API_UNEXPECTED]
						]],
						'securitylevel' =>	['type' => API_MULTIPLE, 'rules' => [
												['if' => ['field' => 'version', 'in' => SNMP_V3], 'type' => API_INT32, 'in' => implode(',', [ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV, ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV, ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV]), 'default' => DB::getDefault('interface_snmp', 'securitylevel')],
												['else' => true, 'type' => API_UNEXPECTED]
						]],
						'authprotocol' =>	['type' => API_MULTIPLE, 'rules' => [
												['if' => function (array $data): bool {
													return $data['version'] == SNMP_V3
														&& in_array($data['securitylevel'], [ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV, ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV]);
												}, 'type' => API_INT32, 'in' => implode(',', array_keys(getSnmpV3AuthProtocols()))],
												['else' => true, 'type' => API_UNEXPECTED]
						]],
						'authpassphrase' =>	['type' => API_MULTIPLE, 'rules' => [
												['if' => function (array $data): bool {
													return $data['version'] == SNMP_V3
														&& in_array($data['securitylevel'], [ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV, ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV]);
												}, 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('interface_snmp', 'authpassphrase')],
												['else' => true, 'type' => API_UNEXPECTED]
						]],
						'privprotocol' =>	['type' => API_MULTIPLE, 'rules' => [
												['if' => function (array $data): bool {
													return $data['version'] == SNMP_V3
														&& $data['securitylevel'] == ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV;
												}, 'type' => API_INT32, 'in' => implode(',', array_keys(getSnmpV3PrivProtocols()))],
												['else' => true, 'type' => API_UNEXPECTED]
						]],
						'privpassphrase' =>	['type' => API_MULTIPLE, 'rules' => [
												['if' => function (array $data): bool {
													return $data['version'] == SNMP_V3
														&& $data['securitylevel'] == ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV;
												}, 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('interface_snmp', 'privpassphrase')],
												['else' => true, 'type' => API_UNEXPECTED]
						]]
					]],
					['else' => true, 'type' => API_UNEXPECTED]
				]]
			]],
			['else' => true, 'type' => API_UNEXPECTED]
		]];
	}

	/**
	 * @param array $host_prototypes
	 * @param array $db_host_prototypes
	 */
	protected function updateForce(array &$host_prototypes, array $db_host_prototypes): void {
		$upd_host_prototypes = [];

		// save the host prototypes
		foreach ($host_prototypes as $host_prototype) {
			$upd_host_prototype = DB::getUpdatedValues('hosts', $host_prototype,
				$db_host_prototypes[$host_prototype['hostid']]
			);

			if ($upd_host_prototype) {
				$upd_host_prototypes[] = [
					'values' => $upd_host_prototype,
					'where' => ['hostid' => $host_prototype['hostid']]
				];
			}
		}

		if ($upd_host_prototypes) {
			DB::update('hosts', $upd_host_prototypes);
		}

		self::updateInterfaces($host_prototypes, $db_host_prototypes);
		self::updateGroupLinks($host_prototypes, $db_host_prototypes);
		self::updateGroupPrototypes($host_prototypes, $db_host_prototypes);
		$this->updateTemplates($host_prototypes, $db_host_prototypes);
		$this->updateTagsNew($host_prototypes, $db_host_prototypes);
		$this->updateMacros($host_prototypes, $db_host_prototypes);
		self::updateHostInventories($host_prototypes, $db_host_prototypes);

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_HOST_PROTOTYPE, $host_prototypes,
			$db_host_prototypes
		);
	}

	/**
	 * @param array $host_prototypes
	 * @param array $db_host_prototypes
	 */
	protected function addAffectedObjects(array $host_prototypes, array &$db_host_prototypes): void {
		self::addAffectedInterfaces($host_prototypes, $db_host_prototypes);
		self::addAffectedGroupLinks($host_prototypes, $db_host_prototypes);
		self::addAffectedGroupPrototypes($host_prototypes, $db_host_prototypes);
		parent::addAffectedObjects($host_prototypes, $db_host_prototypes);
	}

	/**
	 * @param array $host_prototypes
	 * @param array $db_host_prototypes
	 */
	private static function addAffectedInterfaces(array $host_prototypes, array &$db_host_prototypes): void {
		$hostids = [];

		foreach ($host_prototypes as $host_prototype) {
			$db_custom_interfaces = $db_host_prototypes[$host_prototype['hostid']]['custom_interfaces'];

			if (array_key_exists('interfaces', $host_prototype)
					|| ($host_prototype['custom_interfaces'] != $db_custom_interfaces
						&& $db_custom_interfaces == HOST_PROT_INTERFACES_CUSTOM)) {
				$hostids[] = $host_prototype['hostid'];
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
	 * Check for unique host prototype names.
	 *
	 * @param array      $host_prototypes
	 * @param array|null $db_host_prototypes
	 * @param bool       $inherited
	 *
	 * @throws APIException if host prototype names are not unique.
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
	 * Check that only host prototypes on templates have UUID. Add UUID to all host prototypes on templates,
	 * if it doesn't exist.
	 *
	 * @param array $host_prototypes
	 *
	 * @throws APIException
	 */
	private static function checkAndAddUuid(array &$host_prototypes): void {
		$templated_ruleids = DBfetchColumn(DBselect(
			'SELECT i.itemid'.
			' FROM items i,hosts h'.
			' WHERE i.hostid=h.hostid'.
			' AND '.dbConditionId('i.itemid', array_unique(array_column($host_prototypes, 'ruleid'))).
			' AND h.status='.HOST_STATUS_TEMPLATE
		), 'itemid');

		foreach ($host_prototypes as $index => &$host_prototype) {
			if (!in_array($host_prototype['ruleid'], $templated_ruleids) && array_key_exists('uuid', $host_prototype)) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Invalid parameter "%1$s": %2$s.', '/' . ($index + 1), _s('unexpected parameter "%1$s"', 'uuid'))
				);
			}

			if (in_array($host_prototype['ruleid'], $templated_ruleids) && !array_key_exists('uuid', $host_prototype)) {
				$host_prototype['uuid'] = generateUuidV4();
			}
		}
		unset($host_prototype);

		$duplicates = DB::select('hosts', [
			'output' => ['uuid'],
			'filter' => [
				'uuid' => array_column($host_prototypes, 'uuid')
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
	 * Checks if the current user has access to the given LLD rules.
	 *
	 * @param array $host_prototypes
	 *
	 * @throws APIException if the user doesn't have write permissions for the given LLD rules
	 */
	private static function checkDiscoveryRules(array $host_prototypes): void {
		$ruleids = array_keys(array_flip(array_column($host_prototypes, 'ruleid')));

		$count = API::DiscoveryRule()->get([
			'countOutput' => true,
			'itemids' => $ruleids,
			'editable' => true
		]);

		if ($count != count($ruleids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		// Check if the host is discovered.
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
			if (!array_key_exists('interfaces', $host_prototype)) {
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
						_s('no default interface for "%1$s" type.', hostInterfaceTypeNumToName($type))
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
	 * @param array      $host_prototypes
	 * @param array|null $db_host_prototypes
	 */
	private static function updateInterfaces(array &$host_prototypes, array $db_host_prototypes = null): void {
		$ins_interfaces = [];
		$del_interfaceids = [];

		foreach ($host_prototypes as &$host_prototype) {
			if (($db_host_prototypes === null && !array_key_exists('interfaces', $host_prototype))
					|| ($db_host_prototypes !== null
						&& !array_key_exists('interfaces', $db_host_prototypes[$host_prototype['hostid']]))) {
				continue;
			}

			if ($db_host_prototypes !== null && !array_key_exists('interfaces', $host_prototype)) {
				$host_prototype['interfaces'] = [];
			}

			$db_interfaces = ($db_host_prototypes !== null)
				? $db_host_prototypes[$host_prototype['hostid']]['interfaces']
				: [];

			foreach ($host_prototype['interfaces'] as &$interface) {
				$db_interfaceid = self::getInterfaceId($interface, $db_interfaces);

				if ($db_interfaceid !== null) {
					$interface['interfaceid'] = $db_interfaceid;
					unset($db_interfaces[$db_interfaceid]);
				}
				else {
					$ins_interfaces[] = ['hostid' => $host_prototype['hostid']] + $interface;
				}
			}
			unset($interface);

			$del_interfaceids = array_merge($del_interfaceids, array_keys($db_interfaces));
		}
		unset($host_prototype);

		if ($del_interfaceids) {
			DB::delete('interface_snmp', ['interfaceid' => $del_interfaceids]);
			DB::delete('interface', ['interfaceid' => $del_interfaceids]);
		}

		if ($ins_interfaces) {
			$interfaceids = DB::insert('interface', $ins_interfaces);
		}

		$ins_interfaces_snmp = [];

		foreach ($host_prototypes as &$host_prototype) {
			if (!array_key_exists('interfaces', $host_prototype)) {
				continue;
			}

			foreach ($host_prototype['interfaces'] as &$interface) {
				if (!array_key_exists('interfaceid', $interface)) {
					$interface['interfaceid'] = array_shift($interfaceids);

					if ($interface['type'] == INTERFACE_TYPE_SNMP) {
						$ins_interfaces_snmp[] = ['interfaceid' => $interface['interfaceid']] + $interface['details'];
					}
				}
			}
			unset($interface);
		}
		unset($host_prototype);

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
		return key(array_filter($db_interfaces, static function (array $db_interface) use ($interface): bool {
			return $interface['type'] == $db_interface['type']
				&& $interface['useip'] == $db_interface['useip']
				&& (!array_key_exists('ip', $interface) || $interface['ip'] === $db_interface['ip'])
				&& (!array_key_exists('dns', $interface) || $interface['dns'] === $db_interface['dns'])
				&& $interface['port'] === $db_interface['port']
				&& $interface['main'] == $db_interface['main']
				&& (!array_key_exists('details', $interface)
					|| ($interface['details']['version'] == $db_interface['details']['version'])
						&& (!array_key_exists('bulk', $interface['details'])
							|| $interface['details']['bulk'] == $db_interface['details']['bulk'])
						&& (!array_key_exists('community', $interface['details'])
							|| $interface['details']['community'] === $db_interface['details']['community'])
						&& (!array_key_exists('contextname', $interface['details'])
							|| $interface['details']['contextname'] === $db_interface['details']['contextname'])
						&& (!array_key_exists('securityname', $interface['details'])
							|| $interface['details']['securityname'] === $db_interface['details']['securityname'])
						&& (!array_key_exists('securitylevel', $interface['details'])
							|| $interface['details']['securitylevel'] == $db_interface['details']['securitylevel'])
						&& (!array_key_exists('authprotocol', $interface['details'])
							|| $interface['details']['authprotocol'] == $db_interface['details']['authprotocol'])
						&& (!array_key_exists('authpassphrase', $interface['details'])
							|| $interface['details']['authpassphrase'] === $db_interface['details']['authpassphrase'])
						&& (!array_key_exists('privprotocol', $interface['details'])
							|| $interface['details']['privprotocol'] == $db_interface['details']['privprotocol'])
						&& (!array_key_exists('privpassphrase', $interface['details'])
							|| $interface['details']['privpassphrase'] === $db_interface['details']['privpassphrase']));
		}));
	}

	/**
	 * @param array      $host_prototypes
	 * @param array|null $db_host_prototypes
	 */
	private static function updateGroupLinks(array &$host_prototypes, array $db_host_prototypes = null): void {
		$ins_group_links = [];
		$upd_group_links = []; // Used to update templateid value upon inheritance.
		$del_group_prototypeids = [];

		foreach ($host_prototypes as &$host_prototype) {
			if (!array_key_exists('groupLinks', $host_prototype)) {
				continue;
			}

			$db_group_links = ($db_host_prototypes !== null)
				? array_column($db_host_prototypes[$host_prototype['hostid']]['groupLinks'], null, 'groupid')
				: [];

			foreach ($host_prototype['groupLinks'] as &$group_link) {
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
					}

					unset($db_group_links[$group_link['groupid']]);
				}
				else {
					$ins_group_links[] = ['hostid' => $host_prototype['hostid']] + $group_link;
				}
			}
			unset($group_link);

			$del_group_prototypeids = array_merge($del_group_prototypeids,
				array_column($db_group_links, 'group_prototypeid')
			);
		}
		unset($host_prototype);

		if ($del_group_prototypeids) {
			// Lock group prototypes before the deletion to prevent server from adding new LLD elements.
			DBselect(
				'SELECT NULL'.
				' FROM group_prototype gp'.
				' WHERE '.dbConditionId('gp.group_prototypeid', $del_group_prototypeids).
				' FOR UPDATE'
			);

			DB::delete('group_prototype', ['group_prototypeid' => $del_group_prototypeids]);
		}

		if ($upd_group_links) {
			DB::update('group_prototype', $upd_group_links);
		}

		if ($ins_group_links) {
			$group_prototypeids = DB::insert('group_prototype', $ins_group_links);
		}

		foreach ($host_prototypes as &$host_prototype) {
			if (!array_key_exists('groupLinks', $host_prototype)) {
				continue;
			}

			foreach ($host_prototype['groupLinks'] as &$group_link) {
				if (!array_key_exists('group_prototypeid', $group_link)) {
					$group_link['group_prototypeid'] = array_shift($group_prototypeids);
				}
			}
			unset($group_link);
		}
		unset($host_prototype);
	}

	/**
	 * @param array      $host_prototypes
	 * @param array|null $db_host_prototypes
	 */
	private static function updateGroupPrototypes(array &$host_prototypes, array $db_host_prototypes = null): void {
		$ins_group_prototypes = [];
		$upd_group_prototypes = []; // Used to update templateid value upon inheritance.
		$del_group_prototypeids = [];

		foreach ($host_prototypes as &$host_prototype) {
			if (!array_key_exists('groupPrototypes', $host_prototype)) {
				continue;
			}

			$db_group_prototypes = ($db_host_prototypes !== null)
				? array_column($db_host_prototypes[$host_prototype['hostid']]['groupPrototypes'], null, 'name')
				: [];

			foreach ($host_prototype['groupPrototypes'] as &$group_prototype) {
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
					}

					unset($db_group_prototypes[$group_prototype['name']]);
				}
				else {
					$ins_group_prototypes[] = ['hostid' => $host_prototype['hostid']] + $group_prototype;
				}
			}
			unset($group_prototype);

			$del_group_prototypeids = array_merge($del_group_prototypeids,
				array_column($db_group_prototypes, 'group_prototypeid')
			);
		}
		unset($host_prototype);

		if ($del_group_prototypeids) {
			// Lock group prototypes before the deletion to prevent server from adding new LLD elements.
			DBselect(
				'SELECT NULL'.
				' FROM group_prototype gp'.
				' WHERE '.dbConditionId('gp.group_prototypeid', $del_group_prototypeids).
				' FOR UPDATE'
			);

			self::deleteDiscoveredGroups($del_group_prototypeids);

			DB::delete('group_prototype', ['group_prototypeid' => $del_group_prototypeids]);
		}

		if ($upd_group_prototypes) {
			DB::update('group_prototype', $upd_group_prototypes);
		}

		if ($ins_group_prototypes) {
			$group_prototypeids = DB::insert('group_prototype', $ins_group_prototypes);
		}

		foreach ($host_prototypes as &$host_prototype) {
			if (!array_key_exists('groupPrototypes', $host_prototype)) {
				continue;
			}

			foreach ($host_prototype['groupPrototypes'] as &$group_prototype) {
				if (!array_key_exists('group_prototypeid', $group_prototype)) {
					$group_prototype['group_prototypeid'] = array_shift($group_prototypeids);
				}
			}
			unset($group_prototype);
		}
		unset($host_prototype);
	}

	/**
	 * @param array      $host_prototypes
	 * @param array|null $db_host_prototypes
	 */
	private static function updateHostInventories(array $host_prototypes, array $db_host_prototypes = null): void {
		$ins_inventories = [];
		$upd_inventories = [];
		$del_hostids = [];

		foreach ($host_prototypes as $host_prototype) {
			if (!array_key_exists('inventory_mode', $host_prototype)) {
				continue;
			}

			$db_inventory_mode = ($db_host_prototypes !== null)
				? $db_host_prototypes[$host_prototype['hostid']]['inventory_mode']
				: HOST_INVENTORY_DISABLED;

			if ($host_prototype['inventory_mode'] == $db_inventory_mode) {
				continue;
			}

			if ($host_prototype['inventory_mode'] == HOST_INVENTORY_DISABLED) {
				$del_hostids[] = $host_prototype['hostid'];
			}
			elseif ($db_inventory_mode != HOST_INVENTORY_DISABLED) {
				$upd_inventories = [
					'values' =>['inventory_mode' => $host_prototype['inventory_mode']],
					'where' => ['hostid' => $host_prototype['hostid']]
				];
			}
			else {
				$ins_inventories[] = [
					'hostid' => $host_prototype['hostid'],
					'inventory_mode' => $host_prototype['inventory_mode']
				];
			}
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
	 * @param array      $host_prototypes
	 * @param array|null $db_host_prototypes
	 *
	 * @param array
	 */
	private static function getTemplatedObjects(array $host_prototypes, array $db_host_prototypes = null): array {
		$templated_ruleids = DBfetchColumn(DBselect(
			'SELECT DISTINCT i.itemid'.
			' FROM items i,hosts_templates ht'.
			' WHERE i.hostid=ht.templateid'.
				' AND '.dbConditionId('i.itemid', array_unique(array_column($host_prototypes, 'ruleid')))
		), 'itemid');

		foreach ($host_prototypes as $i => $host_prototype) {
			if (!in_array($host_prototype['ruleid'], $templated_ruleids)) {
				unset($host_prototypes[$i]);

				if ($db_host_prototypes !== null && array_key_exists($host_prototype['hostid'], $db_host_prototypes)) {
					unset($db_host_prototypes[$host_prototype['hostid']]);
				}
			}
		}

		$host_prototypes = array_values($host_prototypes);

		return ($db_host_prototypes === null) ? [$host_prototypes] : [$host_prototypes, $db_host_prototypes];
	}

	/**
	 * Inherits all host prototypes from the templates given in "templateids" to hosts or templates given in "hostids".
	 *
	 * @param array $ruleids
	 * @param array $hostids
	 */
	public function syncTemplates(array $ruleids, array $hostids): void {
		$db_host_prototypes = DBfetchArrayAssoc(DBselect(
			'SELECT hd.parent_itemid AS ruleid,h.hostid,h.host,h.name,h.custom_interfaces,h.status,h.discover,'.
				dbConditionCoalesce('hi.inventory_mode', HOST_INVENTORY_DISABLED, 'inventory_mode').
			' FROM host_discovery hd'.
			' INNER JOIN hosts h ON hd.hostid=h.hostid'.
			' LEFT JOIN host_inventory hi ON h.hostid=hi.hostid'.
			' WHERE '.dbConditionId('hd.parent_itemid', $ruleids)
		), 'hostid');

		if (!$db_host_prototypes) {
			return;
		}

		$host_prototypes = [];

		foreach ($db_host_prototypes as $db_host_prototype) {
			$host_prototype = array_intersect_key($db_host_prototype, array_flip(['hostid', 'custom_interfaces']));

			if ($db_host_prototype['custom_interfaces'] == HOST_PROT_INTERFACES_CUSTOM) {
				$host_prototype += ['interfaces' => []];
			}

			$host_prototypes[] = $host_prototype + [
				'groupLinks' => [],
				'groupPrototypes' => [],
				'templates' => [],
				'tags' => [],
				'macros' => []
			];
		}

		$this->addAffectedObjects($host_prototypes, $db_host_prototypes);

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

		$this->inherit($host_prototypes, [], $hostids);
	}

	/**
	 * Updates the children of the host prototypes on the given hosts and propagates the inheritance to the child hosts.
	 *
	 * @param array      $host_prototypes
	 * @param array      $db_host_prototypes
	 * @param array|null $hostids            Array of hosts to inherit to; if set to null, the children will be updated
	 *                                       on all child hosts.
	 */
	protected function inherit(array $host_prototypes, array $db_host_prototypes = [], array $hostids = null): void {
		$ins_host_prototypes = [];
		$upd_host_prototypes = [];
		$upd_db_host_prototypes = [];

		if ($db_host_prototypes) {
			$_upd_db_host_prototypes = $this->getChildObjectsUsingTemplateid($host_prototypes, $db_host_prototypes);

			if ($_upd_db_host_prototypes) {
				$_upd_host_prototypes = self::getUpdChildObjectsUsingTemplateid($host_prototypes, $db_host_prototypes,
					$_upd_db_host_prototypes
				);

				self::checkDuplicates($_upd_host_prototypes, $_upd_db_host_prototypes, true);

				$upd_host_prototypes = array_merge($upd_host_prototypes, $_upd_host_prototypes);
				$upd_db_host_prototypes += $_upd_db_host_prototypes;
			}
		}

		if (count($host_prototypes) != count($db_host_prototypes)) {
			$_upd_db_host_prototypes = $this->getChildObjectsUsingName($host_prototypes, $hostids);

			if ($_upd_db_host_prototypes) {
				$_upd_host_prototypes = self::getUpdChildObjectsUsingName($host_prototypes, $db_host_prototypes,
					$_upd_db_host_prototypes
				);

				$upd_host_prototypes = array_merge($upd_host_prototypes, $_upd_host_prototypes);
				$upd_db_host_prototypes += $_upd_db_host_prototypes;
			}

			$ins_host_prototypes = self::getInsChildObjects($host_prototypes, $_upd_db_host_prototypes, $hostids);
		}

		if ($upd_host_prototypes) {
			$this->updateForce($upd_host_prototypes, $upd_db_host_prototypes);
		}

		if ($ins_host_prototypes) {
			$this->createForce($ins_host_prototypes, true);
		}

		[$tpl_host_prototypes, $tpl_db_host_prototypes] = $this->getTemplatedObjects(
			array_merge($upd_host_prototypes, $ins_host_prototypes), $upd_db_host_prototypes
		);

		if ($tpl_host_prototypes) {
			$this->inherit($tpl_host_prototypes, $tpl_db_host_prototypes);
		}
	}

	/**
	 * @param array $host_prototypes
	 * @param array $db_host_prototypes
	 *
	 * @return array
	 */
	private function getChildObjectsUsingTemplateid(array $host_prototypes, array $db_host_prototypes): array {
		$upd_db_host_prototypes = DBfetchArrayAssoc(DBselect(
			'SELECT h.hostid,h.host,h.name,h.custom_interfaces,h.status,h.discover,h.templateid,'.
				'hd.parent_itemid AS ruleid,'.
				dbConditionCoalesce('hi.inventory_mode', HOST_INVENTORY_DISABLED, 'inventory_mode').
			' FROM hosts h'.
			' INNER JOIN host_discovery hd ON h.hostid=hd.hostid'.
			' LEFT JOIN host_inventory hi ON h.hostid=hi.hostid'.
			' WHERE '.dbConditionId('h.templateid', array_keys($db_host_prototypes))
		), 'hostid');

		if ($upd_db_host_prototypes) {
			$host_prototypes = array_column($host_prototypes, null, 'hostid');
			$upd_host_prototypes = [];

			foreach ($upd_db_host_prototypes as $upd_db_host_prototype) {
				$host_prototype = $host_prototypes[$upd_db_host_prototype['templateid']];
				$db_host_prototype = $db_host_prototypes[$upd_db_host_prototype['templateid']];

				$upd_host_prototype = [
					'hostid' => $upd_db_host_prototype['hostid'],
					'custom_interfaces' => $host_prototype['custom_interfaces']
				];

				if (array_key_exists('interfaces', $host_prototype)) {
					$upd_host_prototype += ['interfaces' => []];
				}

				$upd_host_prototype += array_intersect_key([
					'groupLinks' => [],
					'groupPrototypes' => [],
					'templates' => [],
					'tags' => [],
					'macros' => []
				], $db_host_prototype);

				$upd_host_prototypes[] = $upd_host_prototype;
			}

			$this->addAffectedObjects($upd_host_prototypes, $upd_db_host_prototypes);
		}

		return $upd_db_host_prototypes;
	}

	/**
	 * @param array $host_prototypes
	 * @param array $db_host_prototypes
	 * @param array $upd_db_host_prototypes
	 *
	 * @return array
	 */
	private static function getUpdChildObjectsUsingTemplateid(array $host_prototypes, array $db_host_prototypes,
			array $upd_db_host_prototypes): array {
		$upd_host_prototypes = [];

		foreach ($host_prototypes as $host_prototype) {
			if (!array_key_exists($host_prototype['hostid'], $db_host_prototypes)) {
				continue;
			}

			$host_prototype['uuid'] = '';
			$host_prototype = self::unsetNestedObjectIds($host_prototype);

			foreach ($upd_db_host_prototypes as $upd_db_host_prototype) {
				if (bccomp($host_prototype['hostid'], $upd_db_host_prototype['templateid']) != 0) {
					continue;
				}

				$upd_host_prototype = array_intersect_key($upd_db_host_prototype,
					array_flip(['hostid', 'templateid', 'ruleid'])
				) + $host_prototype;

				if (array_key_exists('groupLinks', $upd_host_prototype)) {
					foreach ($upd_host_prototype['groupLinks'] as &$group_link) {
						foreach ($upd_db_host_prototype['groupLinks'] as $db_group_link) {
							if (bccomp($group_link['group_prototypeid'], $db_group_link['templateid']) == 0
									|| bccomp($group_link['groupid'], $db_group_link['groupid']) == 0) {
								$group_link['templateid'] = $group_link['group_prototypeid'];
								$group_link['group_prototypeid'] = $db_group_link['group_prototypeid'];
								break 2;
							}
						}

						$group_link['templateid'] = $group_link['group_prototypeid'];
						unset($group_link['group_prototypeid']);
					}
					unset($group_link);
				}

				if (array_key_exists('groupPrototypes', $upd_host_prototype)) {
					foreach ($upd_host_prototype['groupPrototypes'] as &$group_prototype) {
						foreach ($upd_db_host_prototype['groupPrototypes'] as $db_group_prototype) {
							if (bccomp($group_prototype['group_prototypeid'], $db_group_prototype['templateid']) == 0
									|| $group_prototype['name'] === $db_group_prototype['name']) {
								$group_prototype['templateid'] = $group_prototype['group_prototypeid'];
								$group_prototype['group_prototypeid'] = $db_group_prototype['group_prototypeid'];
								break 2;
							}
						}

						$group_prototype['templateid'] = $group_prototype['group_prototypeid'];
						unset($group_prototype['group_prototypeid']);
					}
					unset($group_prototype);
				}

				if (array_key_exists('macros', $upd_host_prototype)) {
					$db_macros = $db_host_prototypes[$host_prototype['hostid']]['macros'];

					foreach ($upd_host_prototype['macros'] as &$macro) {
						if (array_key_exists($macro['hostmacroid'], $db_macros)) {
							$db_macro = $db_macros[$macro['hostmacroid']];

							$macro['hostmacroid'] = key(array_filter($upd_db_host_prototype['macros'],
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

				$upd_host_prototypes[] = $upd_host_prototype;
			}
		}

		return $upd_host_prototypes;
	}

	/**
	 * @param array $host_prototype
	 *
	 * @return array
	 */
	private static function unsetNestedObjectIds(array $host_prototype): array {
		if (array_key_exists('interfaces', $host_prototype)) {
			foreach ($host_prototype['interfaces'] as &$interface) {
				unset($interface['interfaceid']);
			}
			unset($interface);
		}

		if (array_key_exists('templates', $host_prototype)) {
			foreach ($host_prototype['templates'] as &$template) {
				unset($template['hosttemplateid']);
			}
			unset($template);
		}

		if (array_key_exists('tags', $host_prototype)) {
			foreach ($host_prototype['tags'] as &$tag) {
				unset($tag['hosttagid']);
			}
			unset($tag);
		}

		return $host_prototype;
	}

	/**
	 * @param array      $host_prototypes
	 * @param array|null $hostids
	 *
	 * @return array
	 */
	private function getChildObjectsUsingName(array $host_prototypes, ?array $hostids): array {
		$upd_db_host_prototypes = [];
		$parent_indexes = [];

		$hostids_condition = ($hostids !== null) ? ' AND '.dbConditionId('i.hostid', $hostids) : '';

		$result = DBselect(
			'SELECT i.templateid AS parent_ruleid,i.itemid AS ruleid,hd.hostid,h.uuid,h.host,h.name,'.
				'h.custom_interfaces,h.status,h.discover,h.templateid,'.
				dbConditionCoalesce('hi.inventory_mode', HOST_INVENTORY_DISABLED, 'inventory_mode').
			' FROM items i'.
			' INNER JOIN host_discovery hd ON i.itemid=hd.parent_itemid'.
			' INNER JOIN hosts h ON hd.hostid=h.hostid'.
			' LEFT JOIN host_inventory hi ON h.hostid=hi.hostid'.
			' WHERE '.dbConditionId('i.templateid', array_unique(array_column($host_prototypes, 'ruleid'))).
				' AND '.dbConditionString('h.host', array_unique(array_column($host_prototypes, 'host'))).
				$hostids_condition
		);

		while ($row = DBfetch($result)) {
			foreach ($host_prototypes as $i => $host_prototype) {
				if (bccomp($row['parent_ruleid'], $host_prototype['ruleid']) == 0
						&& $row['host'] === $host_prototype['host']) {
					$upd_db_host_prototypes[$row['hostid']] = $row;
					$parent_indexes[$row['hostid']] = $i;
				}
			}
		}

		if ($upd_db_host_prototypes) {
			$upd_host_prototypes = [];

			foreach ($upd_db_host_prototypes as $upd_db_host_prototype) {
				$host_prototype = $host_prototypes[$parent_indexes[$upd_db_host_prototype['hostid']]];

				$upd_host_prototype = [
					'hostid' => $upd_db_host_prototype['hostid'],
					'custom_interfaces' => $host_prototype['custom_interfaces']
				];

				if (array_key_exists('interfaces', $host_prototype)) {
					$upd_host_prototype += ['interfaces' => []];
				}

				$upd_host_prototype += [
					'groupLinks' => [],
					'groupPrototypes' => [],
					'templates' => [],
					'tags' => [],
					'macros' => []
				];

				$upd_host_prototypes[] = $upd_host_prototype;
			}

			$this->addAffectedObjects($upd_host_prototypes, $upd_db_host_prototypes);
		}

		return $upd_db_host_prototypes;
	}

	/**
	 * @param array $host_prototypes
	 * @param array $db_host_prototypes
	 * @param array $upd_db_host_prototypes
	 *
	 * @return array
	 */
	private static function getUpdChildObjectsUsingName(array $host_prototypes, array $db_host_prototypes,
			array $upd_db_host_prototypes): array {
		$upd_host_prototypes = [];

		foreach ($host_prototypes as $host_prototype) {
			if (array_key_exists($host_prototype['hostid'], $db_host_prototypes)) {
				continue;
			}

			$host_prototype['uuid'] = '';
			$host_prototype = self::unsetNestedObjectIds($host_prototype);

			foreach ($upd_db_host_prototypes as $upd_db_host_prototype) {
				if (bccomp($host_prototype['ruleid'], $upd_db_host_prototype['parent_ruleid']) != 0
						|| $host_prototype['name'] !== $upd_db_host_prototype['name']) {
					continue;
				}

				$upd_host_prototype = array_intersect_key($upd_db_host_prototype, array_flip(['hostid', 'ruleid']));
				$upd_host_prototype += ['templateid' => $host_prototype['hostid']];
				$upd_host_prototype += $host_prototype;

				if (array_key_exists('interfaces', $upd_db_host_prototype)) {
					$upd_host_prototype += $upd_db_host_prototype['interfaces'] ? ['interfaces' => []] : [];
				}

				$upd_host_prototype += $upd_db_host_prototype['groupPrototypes'] ? ['groupPrototypes' => []] : [];
				$upd_host_prototype += $upd_db_host_prototype['templates'] ? ['templates' => []] : [];
				$upd_host_prototype += $upd_db_host_prototype['tags'] ? ['tags' => []] : [];

				if (array_key_exists('groupLinks', $upd_host_prototype)) {
					foreach ($upd_host_prototype['groupLinks'] as &$group_link) {
						foreach ($upd_db_host_prototype['groupLinks'] as $db_group_link) {
							if (bccomp($group_link['groupid'], $db_group_link['groupid']) == 0) {
								$group_link['templateid'] = $group_link['group_prototypeid'];
								$group_link['group_prototypeid'] = $db_group_link['group_prototypeid'];
								break 2;
							}
						}

						$group_link['templateid'] = $group_link['group_prototypeid'];
						unset($group_link['group_prototypeid']);
					}
					unset($group_link);
				}

				if (array_key_exists('groupPrototypes', $upd_host_prototype)) {
					foreach ($upd_host_prototype['groupPrototypes'] as &$group_prototype) {
						foreach ($upd_db_host_prototype['groupPrototypes'] as $db_group_prototype) {
							if ($group_prototype['name'] === $db_group_prototype['name']) {
								$group_prototype['templateid'] = $group_prototype['group_prototypeid'];
								$group_prototype['group_prototypeid'] = $db_group_prototype['group_prototypeid'];
								break 2;
							}
						}

						$group_prototype['templateid'] = $group_prototype['group_prototypeid'];
						unset($group_prototype['group_prototypeid']);
					}
					unset($group_prototype);
				}

				if (array_key_exists('macros', $upd_host_prototype)) {
					foreach ($upd_host_prototype['macros'] as &$macro) {
						$hostmacroid = key(array_filter($upd_db_host_prototype['macros'],
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
				}
				elseif ($upd_db_host_prototype['macros']) {
					$upd_host_prototype += ['macros' => []];
				}

				$upd_host_prototypes[] = $upd_host_prototype;
			}
		}

		return $upd_host_prototypes;
	}

	/**
	 * @param array      $host_prototypes
	 * @param array      $upd_db_host_prototypes
	 * @param array|null $hostids
	 *
	 * @return array
	 */
	private static function getInsChildObjects(array $host_prototypes, array $upd_db_host_prototypes,
			?array $hostids): array {
		$ins_host_prototypes = [];
		$rule_links =[];

		$hostids_condition = ($hostids !== null) ? ['hostid' => $hostids] : [];

		$options = [
			'output' => ['itemid', 'templateid'],
			'filter' => [
				'templateid' => array_unique(array_column($host_prototypes, 'ruleid'))
			] + $hostids_condition
		];
		$result = DBselect(DB::makeSql('items', $options));

		while ($row = DBfetch($result)) {
			$rule_links[$row['templateid']][] = $row['itemid'];
		}

		if (!$rule_links) {
			return $ins_host_prototypes;
		}

		foreach ($host_prototypes as $host_prototype) {
			if (!array_key_exists($host_prototype['ruleid'], $rule_links)) {
				continue;
			}

			$host_prototype['uuid'] = '';
			$host_prototype = self::unsetNestedObjectIds($host_prototype);

			if (array_key_exists('macros', $host_prototype)) {
				foreach ($host_prototype['macros'] as &$macro) {
					unset($macro['hostmacroid']);
				}
				unset($macro);
			}

			foreach ($rule_links[$host_prototype['ruleid']] as $ruleid) {
				foreach ($upd_db_host_prototypes as $upd_db_host_prototype) {
					if (bccomp($ruleid, $upd_db_host_prototype['ruleid']) == 0
							&& $host_prototype['host'] == $upd_db_host_prototype['host']) {
						continue 2;
					}
				}

				$ins_host_prototype = [
					'ruleid' => $ruleid,
					'templateid' => $host_prototype['hostid']
				] + array_diff_key($host_prototype, array_flip(['hostid']));

				foreach ($ins_host_prototype['groupLinks'] as &$group_link) {
					$group_link['templateid'] = $group_link['group_prototypeid'];
					unset($group_link['group_prototypeid']);
				}
				unset($group_link);

				if (array_key_exists('groupPrototypes', $ins_host_prototype)) {
					foreach ($ins_host_prototype['groupPrototypes'] as &$group_prototype) {
						$group_prototype['templateid'] = $group_prototype['group_prototypeid'];
						unset($group_prototype['group_prototypeid']);
					}
					unset($group_prototype);
				}

				$ins_host_prototypes[] = $ins_host_prototype;
			}
		}

		return $ins_host_prototypes;
	}

	/**
	 * @param array $hostids
	 *
	 * @return array
	 */
	public function delete(array $hostids): array {
		$this->validateDelete($hostids, $db_host_prototypes);

		self::deleteForce($db_host_prototypes);

		return ['hostids' => $hostids];
	}

	/**
	 * @param array      $hostids
	 * @param array|null $db_host_prototypes
	 *
	 * @throws APIException if the input is invalid.
	 */
	private function validateDelete(array &$hostids, array &$db_host_prototypes = null): void {
		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];

		if (!CApiInputValidator::validate($api_input_rules, $hostids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_host_prototypes = $this->get([
			'output' => ['hostid', 'host', 'templateid'],
			'hostids' => $hostids,
			'editable' => true,
			'preservekeys' => true
		]);

		if (count($db_host_prototypes) != count($hostids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		foreach ($hostids as $i => $hostid) {
			if ($db_host_prototypes[$hostid]['templateid'] != 0) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.', '/'.($i + 1),
					_('cannot delete templated host prototype')
				));
			}
		}
	}

	/**
	 * @param array $db_host_prototypes
	 */
	public static function deleteForce(array $db_host_prototypes): void {
		$hostids = array_keys($db_host_prototypes);

		// Lock host prototypes before the deletion to prevent server from adding new LLD hosts.
		DBselect(
			'SELECT NULL'.
			' FROM hosts h'.
			' WHERE '.dbConditionId('h.hostid', $hostids).
			' FOR UPDATE'
		);

		$_db_host_prototypes = $db_host_prototypes;

		do {
			// Lock also inherited host prototypes before the deletion to prevent server from adding new LLD hosts.
			$_db_host_prototypes = DBfetchArrayAssoc(DBselect(
				'SELECT hostid,host'.
				' FROM hosts h'.
				' WHERE '.dbConditionId('h.templateid', array_keys($_db_host_prototypes)).
				' FOR UPDATE'
			), 'hostid');

			$db_host_prototypes += $_db_host_prototypes;
		}
		while ($_db_host_prototypes);

		$hostids = array_keys($db_host_prototypes);

		$discovered_hosts = DBfetchArrayAssoc(DBselect(
			'SELECT hd.hostid,h.host'.
			' FROM host_discovery hd,hosts h'.
			' WHERE hd.hostid=h.hostid'.
				' AND '.dbConditionId('hd.parent_hostid', $hostids)
		), 'hostid');

		CHost::validateDeleteForce($discovered_hosts);
		CHost::deleteForce($discovered_hosts);

		// Lock group prototypes before the deletion to prevent server from adding new LLD elements.
		$db_group_prototypes = DBfetchArray(DBselect(
			'SELECT gp.group_prototypeid,gp.name'.
			' FROM group_prototype gp'.
			' WHERE '.dbConditionId('gp.hostid', $hostids).
			' FOR UPDATE'
		));

		$group_prototypeids = [];

		foreach ($db_group_prototypes as $db_group_prototype) {
			if ($db_group_prototype['name'] !== '') {
				$group_prototypeids[] = $db_group_prototype['group_prototypeid'];
			}
		}

		if ($group_prototypeids) {
			self::deleteDiscoveredGroups($group_prototypeids);
		}

		DB::delete('interface', ['hostid' => $hostids]);
		DB::delete('group_prototype', ['group_prototypeid' => array_column($db_host_prototypes, 'group_prototypeid')]);
		DB::delete('hosts_templates', ['hostid' => $hostids]);
		DB::delete('host_tag', ['hostid' => $hostids]);
		DB::delete('hostmacro', ['hostid' => $hostids]);
		DB::delete('host_inventory', ['hostid' => $hostids]);

		DB::delete('hosts', ['hostid' => $hostids]);

		self::addAuditLog(CAudit::ACTION_DELETE, CAudit::RESOURCE_HOST_PROTOTYPE, $db_host_prototypes);
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
