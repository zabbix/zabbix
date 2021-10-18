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
		$link_fields = ['group_prototypeid', 'groupid', 'hostid', 'templateid'];
		$group_fields = ['group_prototypeid', 'name', 'hostid', 'templateid'];
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
			'selectGroupLinks' =>		['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', $link_fields), 'default' => null],
			'selectGroupPrototypes' =>	['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', $group_fields), 'default' => null],
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
						' AND '.dbConditionInt('r.groupid', getUserGroupsByUserId(self::$userData['userid'])).
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

		$hostPrototypeIds = array_keys($result);

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

		// adding group links
		if ($options['selectGroupLinks'] !== null && $options['selectGroupLinks'] != API_OUTPUT_COUNT) {
			$groupPrototypes = DBFetchArray(DBselect(
				'SELECT hg.group_prototypeid,hg.hostid'.
					' FROM group_prototype hg'.
					' WHERE '.dbConditionInt('hg.hostid', $hostPrototypeIds).
					' AND hg.groupid IS NOT NULL'
			));
			$relationMap = $this->createRelationMap($groupPrototypes, 'hostid', 'group_prototypeid');
			$groupPrototypes = API::getApiService()->select('group_prototype', [
				'output' => $options['selectGroupLinks'],
				'group_prototypeids' => $relationMap->getRelatedIds(),
				'preservekeys' => true
			]);
			foreach ($groupPrototypes as &$groupPrototype) {
				unset($groupPrototype['name']);
			}
			unset($groupPrototype);
			$result = $relationMap->mapMany($result, $groupPrototypes, 'groupLinks');
		}

		// adding group prototypes
		if ($options['selectGroupPrototypes'] !== null && $options['selectGroupPrototypes'] != API_OUTPUT_COUNT) {
			$groupPrototypes = DBFetchArray(DBselect(
				'SELECT hg.group_prototypeid,hg.hostid'.
				' FROM group_prototype hg'.
				' WHERE '.dbConditionInt('hg.hostid', $hostPrototypeIds).
					' AND hg.groupid IS NULL'
			));
			$relationMap = $this->createRelationMap($groupPrototypes, 'hostid', 'group_prototypeid');
			$groupPrototypes = API::getApiService()->select('group_prototype', [
				'output' => $options['selectGroupPrototypes'],
				'group_prototypeids' => $relationMap->getRelatedIds(),
				'preservekeys' => true
			]);
			foreach ($groupPrototypes as &$groupPrototype) {
				unset($groupPrototype['groupid']);
			}
			unset($groupPrototype);
			$result = $relationMap->mapMany($result, $groupPrototypes, 'groupPrototypes');
		}

		// adding host
		if ($options['selectParentHost'] !== null && $options['selectParentHost'] != API_OUTPUT_COUNT) {
			$hosts = [];
			$relationMap = new CRelationMap();
			$dbRules = DBselect(
				'SELECT hd.hostid,i.hostid AS parent_hostid'.
					' FROM host_discovery hd,items i'.
					' WHERE '.dbConditionInt('hd.hostid', $hostPrototypeIds).
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
					'hostids' => $hostPrototypeIds,
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
				'filter' => ['hostid' => $hostPrototypeIds],
				'preservekeys' => true
			]);

			$relation_map = $this->createRelationMap($tags, 'hostid', 'hosttagid');
			$tags = $this->unsetExtraFields($tags, ['hostid', 'hosttagid'], []);
			$result = $relation_map->mapMany($result, $tags, 'tags');
		}

		if ($options['selectInterfaces'] !== null && $options['selectInterfaces'] != API_OUTPUT_COUNT) {
			$interfaces = API::HostInterface()->get([
				'output' => $this->outputExtend($options['selectInterfaces'], ['hostid', 'interfaceid']),
				'hostids' => $hostPrototypeIds,
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
	 * Creates the given host prototypes.
	 *
	 * @param array $host_prototypes
	 *
	 * @return array
	 */
	public function create(array $host_prototypes) {
		self::validateCreate($host_prototypes);

		$this->createReal($host_prototypes);
		$this->inherit($host_prototypes, null, []);

		return ['hostids' => array_column($host_prototypes, 'hostid')];
	}

	/**
	 * Validates the input parameters for the create() method.
	 *
	 * @static
	 *
	 * @param array $host_prototypes
	 *
	 * @throws APIException if the input is invalid.
	 */
	private static function validateCreate(array &$host_prototypes): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['uuid'], ['ruleid', 'host'], ['ruleid', 'name']], 'fields' => [
			'uuid' =>				['type' => API_UUID],
			'ruleid' =>				['type' => API_ID, 'flags' => API_REQUIRED],
			'host' =>				['type' => API_H_NAME, 'flags' => API_REQUIRED | API_REQUIRED_LLD_MACRO, 'length' => DB::getFieldLength('hosts', 'host')],
			'name' =>				['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('hosts', 'name'), 'default_source' => 'host'],
			'custom_interfaces' =>	['type' => API_INT32, 'in' => implode(',', [HOST_PROT_INTERFACES_INHERIT, HOST_PROT_INTERFACES_CUSTOM]), 'default' => DB::getDefault('hosts', 'custom_interfaces')],
			'status' =>				['type' => API_INT32, 'in' => implode(',', [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED]), 'default' => DB::getDefault('hosts', 'status')],
			'discover' =>			['type' => API_INT32, 'in' => implode(',', [ZBX_PROTOTYPE_DISCOVER, ZBX_PROTOTYPE_NO_DISCOVER]), 'default' => DB::getDefault('hosts', 'discover')],
			'interfaces' =>			self::getInterfacesValidationRules(),
			'groupLinks' =>			['type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'uniq' => [['groupid']], 'fields' => [
				'groupid' =>			['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'groupPrototypes' =>	['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY, 'uniq' => [['name']], 'fields' => [
				'name' =>				['type' => API_HG_NAME, 'flags' => API_REQUIRED | API_REQUIRED_LLD_MACRO, 'length' => DB::getFieldLength('hstgrp', 'name')]
			]],
			'templates' =>			['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY, 'uniq' => [['templateid']], 'fields' => [
				'templateid' =>			['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'tags' =>				['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY, 'uniq' => [['tag', 'value']], 'fields' => [
				'tag' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('host_tag', 'tag')],
				'value' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('host_tag', 'value'), 'default' => DB::getDefault('host_tag', 'value')]
			]],
			'macros' =>				['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY, 'uniq' => [['macro']], 'fields' => [
				'macro' =>				['type' => API_USER_MACRO, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('hostmacro', 'macro')],
				'type' =>				['type' => API_INT32, 'in' => implode(',', [ZBX_MACRO_TYPE_TEXT, ZBX_MACRO_TYPE_SECRET, ZBX_MACRO_TYPE_VAULT]), 'default' => ZBX_MACRO_TYPE_TEXT],
				'value' =>				['type' => API_MULTIPLE, 'flags' => API_REQUIRED, 'rules' => [
											['if' => ['field' => 'type', 'in' => implode(',', [ZBX_MACRO_TYPE_TEXT, ZBX_MACRO_TYPE_SECRET])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hostmacro', 'value')],
											['if' => ['field' => 'type', 'in' => ZBX_MACRO_TYPE_VAULT], 'type' => API_VAULT_SECRET, 'length' => DB::getFieldLength('hostmacro', 'value')]
				]],
				'description' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hostmacro', 'description')]
			]],
			'inventory_mode' =>		['type' => API_INT32, 'in' => implode(',', [HOST_INVENTORY_DISABLED, HOST_INVENTORY_MANUAL, HOST_INVENTORY_AUTOMATIC]), 'default' => HOST_INVENTORY_DISABLED]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $host_prototypes, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$ruleids = array_keys(array_flip(array_column($host_prototypes, 'ruleid')));

		self::checkDuplicates($host_prototypes);
		self::checkDiscoveryRulePermissions($ruleids);
		self::checkHostGroupsPermissions($host_prototypes);
		self::checkHostDiscovery($ruleids);
		self::checkAndAddUuid($host_prototypes);
		self::checkMainInterfaces($host_prototypes);
	}

	/**
	 * Creates the host prototypes and inherits them to linked hosts and templates.
	 *
	 * @param array $host_prototypes
	 */
	protected function createReal(array &$host_prototypes): void {
		foreach ($host_prototypes as &$host_prototype) {
			$host_prototype['flags'] = ZBX_FLAG_DISCOVERY_PROTOTYPE;
		}
		unset($host_prototype);

		$host_prototypeids = DB::insert($this->tableName(), $host_prototypes);

		foreach ($host_prototypes as $index => &$host_prototype) {
			$host_prototype['hostid'] = $host_prototypeids[$index];
		}
		unset($host_prototype);

		self::createHostDiscoveries($host_prototypes);

		self::updateGroupLinks($host_prototypes, 'create');
		self::updateGroupPrototypes($host_prototypes, 'create');
		$this->updateTemplates($host_prototypes, 'create');
		self::updateHostInventories($host_prototypes, 'create');

		$this->updateTagsNew($host_prototypes, 'create');
		$this->updateHostMacrosNew($host_prototypes, 'create');

		self::updateInterfaces($host_prototypes, 'create');

		self::addAuditLog(CAudit::ACTION_ADD, CAudit::RESOURCE_HOST_PROTOTYPE, $host_prototypes);
	}

	/**
	 * Updates the given host prototypes.
	 *
	 * @param array $host_prototypes
	 *
	 * @return array
	 */
	public function update(array $host_prototypes): array {
		$this->validateUpdate($host_prototypes, $db_host_prototypes);

		$this->updateReal($host_prototypes, $db_host_prototypes);
		$this->inherit($host_prototypes, null, $db_host_prototypes);

		return ['hostids' => array_column($host_prototypes, 'hostid')];
	}

	/**
	 * Validates the input parameters for the update() method.
	 *
	 * @param array      $host_prototypes
	 * @param array|null $db_host_prototypes
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateUpdate(array &$host_prototypes, array &$db_host_prototypes = null): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['hostid'], ['host'], ['name']], 'fields' => [
			'hostid' =>				['type' => API_ID, 'flags' => API_REQUIRED],
			'host' =>				['type' => API_H_NAME, 'flags' => API_REQUIRED_LLD_MACRO, 'length' => DB::getFieldLength('hosts', 'host')],
			'name' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hosts', 'name')],
			'custom_interfaces' =>	['type' => API_INT32, 'in' => implode(',', [HOST_PROT_INTERFACES_INHERIT, HOST_PROT_INTERFACES_CUSTOM])],
			'status' =>				['type' => API_INT32, 'in' => implode(',', [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED])],
			'discover' =>			['type' => API_INT32, 'in' => implode(',', [ZBX_PROTOTYPE_DISCOVER, ZBX_PROTOTYPE_NO_DISCOVER])],
			'interfaces' =>			self::getInterfacesValidationRules(),
			'groupLinks' =>			['type' => API_OBJECTS, 'uniq' => [['groupid']], 'fields' => [
				'groupid' =>			['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'groupPrototypes' =>	['type' => API_OBJECTS, 'uniq' => [['name']], 'fields' => [
				'name' =>				['type' => API_HG_NAME, 'flags' => API_REQUIRED | API_REQUIRED_LLD_MACRO, 'length' => DB::getFieldLength('hstgrp', 'name')]
			]],
			'templates' =>			['type' => API_OBJECTS, 'uniq' => [['templateid']], 'fields' => [
				'templateid' =>			['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'tags' =>				['type' => API_OBJECTS, 'uniq' => [['tag', 'value']], 'fields' => [
				'tag' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('host_tag', 'tag')],
				'value' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('host_tag', 'value'), 'default' => DB::getDefault('host_tag', 'value')]
			]],
			'macros' =>				['type' => API_OBJECTS, 'uniq' => [['macro']], 'fields' => [
				'macro' =>				['type' => API_USER_MACRO, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('hostmacro', 'macro')],
				'type' =>				['type' => API_INT32, 'in' => implode(',', [ZBX_MACRO_TYPE_TEXT, ZBX_MACRO_TYPE_SECRET, ZBX_MACRO_TYPE_VAULT])],
				'value' =>				['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'type', 'in' => ZBX_MACRO_TYPE_TEXT], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('hostmacro', 'value')],
											['if' => ['field' => 'type', 'in' => ZBX_MACRO_TYPE_SECRET], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hostmacro', 'value')],
											['if' => ['field' => 'type', 'in' => ZBX_MACRO_TYPE_VAULT], 'type' => API_VAULT_SECRET, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('hostmacro', 'value')]
				]],
				'description' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hostmacro', 'description')]
			]],
			'inventory_mode' =>		['type' => API_INT32, 'in' => implode(',', [HOST_INVENTORY_DISABLED, HOST_INVENTORY_MANUAL, HOST_INVENTORY_AUTOMATIC])]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $host_prototypes, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_host_prototypes = $this->get([
			'output' => ['hostid', 'host', 'name', 'custom_interfaces', 'status', 'discover', 'inventory_mode'],
			'selectDiscoveryRule' => ['itemid'],
			'hostids' => array_column($host_prototypes, 'hostid'),
			'editable' => true,
			'preservekeys' => true
		]);

		if (count($host_prototypes) != count($db_host_prototypes)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		self::addAffectedObjects($host_prototypes, $db_host_prototypes);

		$host_prototypes = $this->extendObjectsByKey($host_prototypes, $db_host_prototypes, 'hostid',
			['host', 'name', 'custom_interfaces', 'ruleid']
		);

		self::checkDuplicates($host_prototypes, $db_host_prototypes);
		self::checkHostGroupsPermissions($host_prototypes);
		self::checkMainInterfaces($host_prototypes);
	}

	/**
	 * Updates the host prototypes and propagates the changes to linked hosts and templates.
	 *
	 * @param array $host_prototypes
	 * @param array $db_host_prototypes
	 */
	protected function updateReal(array &$host_prototypes, array $db_host_prototypes): void {
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

		self::updateGroupLinks($host_prototypes, 'update', $db_host_prototypes);
		self::updateGroupPrototypes($host_prototypes, 'update', $db_host_prototypes);
		$this->updateTemplates($host_prototypes, 'update', $db_host_prototypes);
		self::updateHostInventories($host_prototypes, 'update', $db_host_prototypes);

		$this->updateTagsNew($host_prototypes, 'update', $db_host_prototypes);
		$this->updateHostMacrosNew($host_prototypes, 'update', $db_host_prototypes);

		self::updateInterfaces($host_prototypes, 'update', $db_host_prototypes);

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_HOST_PROTOTYPE, $host_prototypes,
			$db_host_prototypes
		);
	}

	/**
	 * Updates the children of the host prototypes on the given hosts and propagates the inheritance to the child hosts.
	 *
	 * @param array      $host_prototypes      array of host prototypes to inherit
	 * @param array|null $hostids              array of hosts to inherit to; if set to null, the children will be updated
	 *                                         on all child hosts
	 * @param array|null $db_host_prototypes
	 *
	 * @return bool
	 */
	protected function inherit(array $host_prototypes, array $hostids = null, array $db_host_prototypes = null): bool {
		if (!$host_prototypes) {
			return true;
		}

		// prepare the child host prototypes
		$new_host_prototypes = $this->prepareInheritedObjects($host_prototypes, $hostids);
		if (!$new_host_prototypes) {
			return true;
		}

		$ins_host_prototypes = [];
		$upd_host_prototypes = [];
		foreach ($new_host_prototypes as $new_host_prototype) {
			if (array_key_exists('hostid', $new_host_prototype)) {
				$upd_host_prototypes[] = $new_host_prototype;
			}
			else {
				$ins_host_prototypes[] = $new_host_prototype;
			}
		}

		// save the new host prototypes
		if ($ins_host_prototypes) {
			$this->createReal($ins_host_prototypes);
		}

		if ($upd_host_prototypes) {
			$upd_host_prototypes = $this->updateReal($upd_host_prototypes, $db_host_prototypes);
		}

		$host_prototypes = array_merge($upd_host_prototypes, $ins_host_prototypes);

		if ($host_prototypes) {
			$sql = 'SELECT hd.hostid'.
					' FROM host_discovery hd,items i,hosts h'.
					' WHERE hd.parent_itemid=i.itemid'.
						' AND i.hostid=h.hostid'.
						' AND h.status='.HOST_STATUS_TEMPLATE.
						' AND '.dbConditionInt('hd.hostid', array_column($host_prototypes, 'hostid'));
			$valid_prototypes = DBfetchArrayAssoc(DBselect($sql), 'hostid');

			foreach ($host_prototypes as $key => $host_prototype) {
				if (!array_key_exists($host_prototype['hostid'], $valid_prototypes)) {
					unset($host_prototypes[$key]);
				}
			}
		}

		return $this->inherit($host_prototypes, $hostids, $db_host_prototypes);
	}


	/**
	 * Prepares and returns an array of child host prototypes, inherited from host prototypes $host_prototypes
	 * on the given hosts.
	 *
	 * Each host prototype must have the "ruleid" parameter set.
	 *
	 * @param array      $host_prototypes
	 * @param array|null $hostIds
	 *
	 * @return array  an array of unsaved child host prototypes
	 */
	protected function prepareInheritedObjects(array $host_prototypes, array $hostIds = null) {
		// Fetch the related discovery rules with their hosts.
		$discoveryRules = API::DiscoveryRule()->get([
			'output' => ['itemid', 'hostid'],
			'selectHosts' => ['hostid'],
			'itemids' => array_column($host_prototypes, 'ruleid'),
			'templated' => true,
			'nopermissions' => true,
			'preservekeys' => true
		]);

		// Remove host prototypes which don't belong to templates, so they cannot be inherited.
		$host_prototypes = array_filter($host_prototypes, function ($host_prototype) use ($discoveryRules) {
			return array_key_exists($host_prototype['ruleid'], $discoveryRules);
		});

		// Fetch all child hosts to inherit to. Do not inherit host prototypes on discovered hosts.
		$chdHosts = API::Host()->get([
			'output' => ['hostid', 'host', 'status'],
			'selectParentTemplates' => ['templateid'],
			'templateids' => zbx_objectValues($discoveryRules, 'hostid'),
			'hostids' => $hostIds,
			'nopermissions' => true,
			'templated_hosts' => true,
			'filter' => ['flags' => ZBX_FLAG_DISCOVERY_NORMAL]
		]);
		if (empty($chdHosts)) {
			return [];
		}

		// Fetch the child discovery rules.
		$childDiscoveryRules = API::DiscoveryRule()->get([
			'output' => ['itemid', 'templateid', 'hostid'],
			'preservekeys' => true,
			'filter' => [
				'templateid' => array_keys($discoveryRules)
			]
		]);

		/*
		 * Fetch child host prototypes and group them by discovery rule. "selectInterfaces" is not required, because
		 * all child are rewritten when updating parents.
		 */
		$childHostPrototypes = API::HostPrototype()->get([
			'output' => ['hostid', 'host', 'templateid'],
			'selectGroupLinks' => API_OUTPUT_EXTEND,
			'selectGroupPrototypes' => API_OUTPUT_EXTEND,
			'selectDiscoveryRule' => ['itemid'],
			'discoveryids' => zbx_objectValues($childDiscoveryRules, 'itemid')
		]);
		foreach ($childDiscoveryRules as &$childDiscoveryRule) {
			$childDiscoveryRule['hostPrototypes'] = [];
		}
		unset($childDiscoveryRule);
		foreach ($childHostPrototypes as $childHostPrototype) {
			$discoveryRuleId = $childHostPrototype['discoveryRule']['itemid'];
			unset($childHostPrototype['discoveryRule']);

			$childDiscoveryRules[$discoveryRuleId]['hostPrototypes'][] = $childHostPrototype;
		}

		// match each discovery that the parent host prototypes belong to to the child discovery rule for each host
		$discoveryRuleChildren = [];
		foreach ($childDiscoveryRules as $childRule) {
			$discoveryRuleChildren[$childRule['templateid']][$childRule['hostid']] = $childRule['itemid'];
		}

		$newHostPrototypes = [];
		foreach ($chdHosts as $host) {
			$hostId = $host['hostid'];

			// skip items not from parent templates of current host
			$templateIds = zbx_toHash($host['parentTemplates'], 'templateid');
			$parentHostPrototypes = [];
			foreach ($host_prototypes as $inum => $parentHostPrototype) {
				$parentTemplateId = $discoveryRules[$parentHostPrototype['ruleid']]['hostid'];

				if (isset($templateIds[$parentTemplateId])) {
					$parentHostPrototypes[$inum] = $parentHostPrototype;
				}
			}

			foreach ($parentHostPrototypes as $parentHostPrototype) {
				$childDiscoveryRuleId = $discoveryRuleChildren[$parentHostPrototype['ruleid']][$hostId];
				$exHostPrototype = null;

				// check if the child discovery rule already has host prototypes
				$exHostPrototypes = $childDiscoveryRules[$childDiscoveryRuleId]['hostPrototypes'];
				if ($exHostPrototypes) {
					$exHostPrototypesHosts = zbx_toHash($exHostPrototypes, 'host');
					$exHostPrototypesTemplateIds = zbx_toHash($exHostPrototypes, 'templateid');

					// look for an already created inherited host prototype
					// if one exists - update it
					if (isset($exHostPrototypesTemplateIds[$parentHostPrototype['hostid']])) {
						$exHostPrototype = $exHostPrototypesTemplateIds[$parentHostPrototype['hostid']];

						// check if there's a host prototype on the target host with the same host name but from a different template
						// or no template
						if (isset($exHostPrototypesHosts[$parentHostPrototype['host']])
							&& !idcmp($exHostPrototypesHosts[$parentHostPrototype['host']]['templateid'], $parentHostPrototype['hostid'])) {

							$discoveryRule = DBfetch(DBselect('SELECT i.name FROM items i WHERE i.itemid='.zbx_dbstr($exHostPrototype['discoveryRule']['itemid'])));
							self::exception(ZBX_API_ERROR_PARAMETERS, _s('Host prototype "%1$s" already exists on "%2$s".', $parentHostPrototype['host'], $discoveryRule['name']));
						}
					}

					// look for a host prototype with the same host name
					// if one exists - convert it to an inherited host prototype
					if (isset($exHostPrototypesHosts[$parentHostPrototype['host']])) {
						$exHostPrototype = $exHostPrototypesHosts[$parentHostPrototype['host']];

						// check that this host prototype is not inherited from a different template
						if ($exHostPrototype['templateid'] > 0 && !idcmp($exHostPrototype['templateid'], $parentHostPrototype['hostid'])) {
							$discoveryRule = DBfetch(DBselect('SELECT i.name FROM items i WHERE i.itemid='.zbx_dbstr($exHostPrototype['discoveryRule']['itemid'])));
							self::exception(ZBX_API_ERROR_PARAMETERS, _s('Host prototype "%1$s" already exists on "%2$s", inherited from another template.', $parentHostPrototype['host'], $discoveryRule['name']));
						}
					}
				}

				// copy host prototype
				$newHostPrototype = $parentHostPrototype;
				$newHostPrototype['uuid'] = '';
				$newHostPrototype['ruleid'] = $discoveryRuleChildren[$parentHostPrototype['ruleid']][$hostId];
				$newHostPrototype['templateid'] = $parentHostPrototype['hostid'];

				if (array_key_exists('macros', $newHostPrototype)) {
					foreach ($newHostPrototype['macros'] as &$hostmacro) {
						unset($hostmacro['hostmacroid']);
					}
					unset($hostmacro);
				}

				// update an existing inherited host prototype
				if ($exHostPrototype) {
					// look for existing group prototypes to update
					$exGroupPrototypesByTemplateId = zbx_toHash($exHostPrototype['groupPrototypes'], 'templateid');
					$exGroupPrototypesByName = zbx_toHash($exHostPrototype['groupPrototypes'], 'name');
					$exGroupPrototypesByGroupId = zbx_toHash($exHostPrototype['groupLinks'], 'groupid');

					// look for a group prototype that can be updated
					foreach ($newHostPrototype['groupPrototypes'] as &$groupPrototype) {
						// updated an inherited item prototype by templateid
						if (isset($exGroupPrototypesByTemplateId[$groupPrototype['group_prototypeid']])) {
							$groupPrototype['group_prototypeid'] = $exGroupPrototypesByTemplateId[$groupPrototype['group_prototypeid']]['group_prototypeid'];
						}
						// updated an inherited item prototype by name
						elseif (isset($groupPrototype['name']) && !zbx_empty($groupPrototype['name'])
								&& isset($exGroupPrototypesByName[$groupPrototype['name']])) {

							$groupPrototype['templateid'] = $groupPrototype['group_prototypeid'];
							$groupPrototype['group_prototypeid'] = $exGroupPrototypesByName[$groupPrototype['name']]['group_prototypeid'];
						}
						// updated an inherited item prototype by group ID
						elseif (isset($groupPrototype['groupid']) && $groupPrototype['groupid']
								&& isset($exGroupPrototypesByGroupId[$groupPrototype['groupid']])) {

							$groupPrototype['templateid'] = $groupPrototype['group_prototypeid'];
							$groupPrototype['group_prototypeid'] = $exGroupPrototypesByGroupId[$groupPrototype['groupid']]['group_prototypeid'];
						}
						// create a new child group prototype
						else {
							$groupPrototype['templateid'] = $groupPrototype['group_prototypeid'];
							unset($groupPrototype['group_prototypeid']);
						}

						unset($groupPrototype['hostid']);
					}
					unset($groupPrototype);

					$newHostPrototype['hostid'] = $exHostPrototype['hostid'];
				}
				// create a new inherited host prototype
				else {
					foreach ($newHostPrototype['groupPrototypes'] as &$groupPrototype) {
						$groupPrototype['templateid'] = $groupPrototype['group_prototypeid'];
						unset($groupPrototype['group_prototypeid'], $groupPrototype['hostid']);
					}
					unset($groupPrototype);

					unset($newHostPrototype['hostid']);
				}
				$newHostPrototypes[] = $newHostPrototype;
			}
		}

		return $newHostPrototypes;
	}

	/**
	 * Inherits all host prototypes from the templates given in "templateids" to hosts or templates given in "hostids".
	 *
	 * @param array $data
	 *
	 * @return bool
	 */
	public function syncTemplates(array $data) {
		$data['templateids'] = zbx_toArray($data['templateids']);
		$data['hostids'] = zbx_toArray($data['hostids']);

		$discoveryRules = API::DiscoveryRule()->get([
			'output' => ['itemid'],
			'hostids' => $data['templateids']
		]);
		$hostPrototypes = $this->get([
			'discoveryids' => zbx_objectValues($discoveryRules, 'itemid'),
			'preservekeys' => true,
			'output' => API_OUTPUT_EXTEND,
			'selectGroupLinks' => API_OUTPUT_EXTEND,
			'selectGroupPrototypes' => API_OUTPUT_EXTEND,
			'selectTags' => ['tag', 'value'],
			'selectTemplates' => ['templateid'],
			'selectDiscoveryRule' => ['itemid'],
			'selectInterfaces' => ['main', 'type', 'useip', 'ip', 'dns', 'port', 'details']
		]);

		$hostPrototypes = $this->getHostMacros($hostPrototypes);

		foreach ($hostPrototypes as &$hostPrototype) {
			// merge group links into group prototypes
			foreach ($hostPrototype['groupLinks'] as $group) {
				$hostPrototype['groupPrototypes'][] = $group;
			}
			unset($hostPrototype['groupLinks']);

			// the ID of the discovery rule must be passed in the "ruleid" parameter
			$hostPrototype['ruleid'] = $hostPrototype['discoveryRule']['itemid'];
			unset($hostPrototype['discoveryRule']);
		}
		unset($hostPrototype);

		$this->inherit($hostPrototypes, $data['hostids']);

		return true;
	}

	/**
	 * Delete host prototypes.
	 *
	 * @param array $host_prototypeids
	 * @param bool  $nopermissions     if set to true, permission and template checks will be skipped
	 *
	 * @return array
	 */
	public function delete(array $host_prototypeids, bool $nopermissions = false): array {
		$this->validateDelete($host_prototypeids, $db_host_prototypes, $nopermissions);

		$db_discovery = DB::select('host_discovery', [
			'output' => ['hostid'],
			'filter' => [
				'parent_hostid' => array_column($db_host_prototypes, 'hostid')
			]
		]);

		if ($db_discovery) {
			API::Host()->delete(array_column($db_discovery, 'hostid'), true);
		}

		$db_groups = DB::select('group_prototype', [
			'output' => ['group_prototypeid'],
			'filter' => [
				'hostid' => array_column($db_host_prototypes, 'hostid')
			]
		]);

		if ($db_groups) {
			$this->deleteGroupPrototypes(array_column($db_groups, 'group_prototypeid'));
		}

		DB::delete($this->tableName(), ['hostid' => array_column($db_host_prototypes, 'hostid')]);

		self::addAuditLog(CAudit::ACTION_DELETE, CAudit::RESOURCE_HOST_PROTOTYPE, $db_host_prototypes);

		return ['hostids' => array_column($db_host_prototypes, 'hostid')];
	}

	/**
	 * Validates the input parameters for the delete() method.
	 *
	 * @param array      $host_prototypeids
	 * @param array|null $db_host_prototypes
	 * @param bool       $nopermissions
	 *
	 * @throws APIException  if the input is invalid
	 */
	private function validateDelete(array $host_prototypeids, array &$db_host_prototypes = null, bool $nopermissions)
			: void {
		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];

		if (!CApiInputValidator::validate($api_input_rules, $host_prototypeids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$host_prototypeids = array_merge($host_prototypeids, self::getChildIds($host_prototypeids));

		// Lock host prototypes before delete to prevent server from adding new LLD hosts.
		DBselect(
			'SELECT NULL'.
			' FROM hosts h'.
			' WHERE '.dbConditionInt('h.hostid', $host_prototypeids).
			' FOR UPDATE'
		);

		$db_host_prototypes = $this->get([
			'output' => ['hostid', 'host'],
			'hostids' => $host_prototypeids,
			'editable' => true
		]);

		if (count($db_host_prototypes) != count($host_prototypeids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		if (!$nopermissions) {
			self::checkHostPrototypePermissions($host_prototypeids);
			self::checkNotInherited($host_prototypeids);
		}
	}

	/**
	 * @static
	 *
	 * @param array $host_prototypeids
	 *
	 * @return array
	 */
	private static function getChildIds(array $host_prototypeids): array {
		$parent_host_prototypeids = $host_prototypeids;
		$child_hostprototypeids = [];

		do {
			$db_childs = DB::select('hosts', [
				'output' => ['hostid'],
				'filter' => [
					'templateid' => $parent_host_prototypeids
				]
			]);

			$parent_host_prototypeids = array_column($db_childs, 'hostid');
			$child_hostprototypeids = array_merge($child_hostprototypeids, array_column($db_childs, 'hostid'));
		} while ($parent_host_prototypeids);

		return $child_hostprototypeids;
	}

	/**
	 * @param array $templateids
	 * @param array $targetids
	 *
	 * @return array|null
	 */
	protected function link(array $templateids, array $targetids): ?array {
		$this->checkHostPrototypePermissions($targetids);

		$links = parent::link($templateids, $targetids);

		foreach ($targetids as $targetid) {
			$linked_templates = DB::select('hosts', [
				'output' => ['hostid', 'name'],
				'hostids' => $targetid
			]);

			$row = DBfetch(DBselect(
				'SELECT i.key_,count(*)'.
				' FROM items i'.
				' WHERE '.dbConditionInt('i.hostid', array_merge($templateids, [$linked_templates[0]['hostid']])).
				' GROUP BY i.key_'.
				' HAVING count(*)>1',
				1
			));
			if ($row) {
				$target_templates = DB::select('hosts', [
					'output' => ['name'],
					'hostids' => $targetid,
					'limit' => 1
				]);

				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Item "%1$s" already exists on "%2$s", inherited from another template.', $row['key_'],
						$target_templates[0]['name']
					)
				);
			}
		}

		return $links;
	}

	/**
	 * Check for duplicated names and hosts.
	 *
	 * @static
	 *
	 * @param array      $host_prototypes
	 * @param array|null $db_host_prototypes
	 *
	 * @throws APIException  if host prototype with same host or name already exists.
	 */
	private static function checkDuplicates(array $host_prototypes, array $db_host_prototypes = null): void {
		$names_by_field = [];
		$messages = [
			'host' => _('Host prototype with host name "%1$s" already exists in discovery rule "%2$s".'),
			'name' => _('Host prototype with visible name "%1$s" already exists in discovery rule "%2$s".')
		];

		foreach ($host_prototypes as $host_prototype) {
			$db_host_prototype = ($db_host_prototypes !== null) ? $db_host_prototypes[$host_prototype['hostid']] : null;

			foreach (['host', 'name'] as $field) {
				if ($db_host_prototype === null || $host_prototype[$field] !== $db_host_prototype[$field]) {
					$names_by_field[$field][$host_prototype['ruleid']][] = $host_prototype[$field];
				}
			}
		}

		foreach ($names_by_field as $field => $names_by_ruleid) {
			$sql_where = [];
			foreach ($names_by_ruleid as $ruleid => $names) {
				$sql_where[] = '(i.itemid='.$ruleid.' AND '.dbConditionString('h.'.$field, $names).')';
			}

			$duplicates = DBfetchArray(DBselect(
					'SELECT i.name AS rule,h.'.$field.
					' FROM items i,host_discovery hd,hosts h'.
					' WHERE i.itemid=hd.parent_itemid'.
						' AND hd.hostid=h.hostid'.
						' AND ('.implode(' OR ', $sql_where).')',
					1
			));

			if ($duplicates) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_params($messages[$field], [$duplicates[0][$field], $duplicates[0]['rule']])
				);
			}
		}
	}

	/**
	 * @static
	 *
	 * @param array $ruleids
	 *
	 * @throws APIException
	 */
	private static function checkHostDiscovery(array $ruleids): void {
		// Check if the host is discovered.
		$duplicates = DBfetchArray(DBselect(
			'SELECT h.host'.
			' FROM items i,hosts h'.
			' WHERE i.hostid=h.hostid'.
				' AND '.dbConditionInt('i.itemid', $ruleids).
				' AND h.flags='.ZBX_FLAG_DISCOVERY_CREATED,
			1
		));

		if ($duplicates) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Cannot create a host prototype on a discovered host "%1$s".', $duplicates[0]['host'])
			);
		}
	}

	/**
	 * Check that only host prototypes on templates have UUID. Add UUID to all host prototypes on templates,
	 * if it doesn't exist.
	 *
	 * @static
	 *
	 * @param array $host_prototypes
	 *
	 * @throws APIException
	 */
	private static function checkAndAddUuid(array &$host_prototypes): void {
		$discovery_ruleids = array_keys(array_flip(array_column($host_prototypes, 'ruleid')));

		$db_templated_rules = DBfetchArrayAssoc(DBselect(
			'SELECT i.itemid, h.status'.
			' FROM items i, hosts h'.
			' WHERE '.dbConditionInt('i.itemid', $discovery_ruleids).
			' AND i.hostid=h.hostid'.
			' AND h.status = ' . HOST_STATUS_TEMPLATE
		), 'itemid');

		foreach ($host_prototypes as $index => &$host_prototype) {
			if (!array_key_exists($host_prototype['ruleid'], $db_templated_rules)
					&& array_key_exists('uuid', $host_prototype)) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Invalid parameter "%1$s": %2$s.', '/' . ($index + 1), _s('unexpected parameter "%1$s"', 'uuid'))
				);
			}

			if (array_key_exists($host_prototype['ruleid'], $db_templated_rules)
					&& !array_key_exists('uuid', $host_prototype)) {
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
	 * @static
	 *
	 * @param array $ruleids
	 *
	 * @throws APIException if the user doesn't have write permissions for the given LLD rules
	 */
	private static function checkDiscoveryRulePermissions(array $ruleids): void {
		$count = API::DiscoveryRule()->get([
			'countOutput' => true,
			'itemids' => $ruleids,
			'editable' => true
		]);

		if ($count != count($ruleids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}
	}

	/**
	 * Checks if the current user has access to the given host groups.
	 *
	 * @static
	 *
	 * @param array $host_prototypes
	 *
	 * @throws APIException if the user doesn't have write permissions for the given host groups
	 */
	private static function checkHostGroupsPermissions(array $host_prototypes): void {
		$groupids = [];

		foreach ($host_prototypes as $host_prototype) {
			if (!array_key_exists('groupLinks', $host_prototype)) {
				continue;
			}

			foreach ($host_prototype['groupLinks'] as $group_link) {
				if (array_key_exists('groupid', $group_link)) {
					continue;
				}

				$groupids[$group_link['groupid']] = true;
			}
		}

		if (!$groupids) {
			return;
		}

		$groupids = array_keys($groupids);

		$db_groups = API::HostGroup()->get([
			'output' => ['name', 'flags'],
			'groupids' => $groupids,
			'editable' => true,
			'preservekeys' => true
		]);

		if (count($db_groups) != count($groupids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		foreach ($groupids as $groupid) {
			// Check if group prototypes use discovered host groups.
			if ($db_groups[$groupid]['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Group prototype cannot be based on a discovered host group "%1$s".',
						$db_groups[$groupid]['name']
					)
				);
			}
		}
	}

	/**
	 * Checks if the current user has access to the given host prototypes.
	 *
	 * @static
	 *
	 * @param array $host_prototypeids
	 *
	 * @throws APIException if the user doesn't have write permissions for the host prototypes.
	 */
	private static function checkHostPrototypePermissions(array $host_prototypeids): void {
		if ($host_prototypeids) {
			$host_prototypeids = array_keys(array_flip($host_prototypeids));

			$count = API::HostPrototype()->get([
				'countOutput' => true,
				'hostids' => $host_prototypeids,
				'editable' => true
			]);

			if ($count != count($host_prototypeids)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}
		}
	}

	/**
	 * Checks if the given host prototypes are not inherited from a template.
	 *
	 * @static
	 *
	 * @param array $host_prototypeids
	 *
	 * @throws APIException  if at least one host prototype is inherited
	 */
	private static function checkNotInherited(array $host_prototypeids): void {
		$host_prototype = DBfetch(DBSelect('SELECT h.hostid FROM hosts h WHERE h.templateid>0 AND '.dbConditionInt('h.hostid', $host_prototypeids), 1));

		if ($host_prototype) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('Cannot delete templated host prototype.'));
		}
	}

	/**
	 * Check if main interfaces are correctly set for every interface type. Each host must either have only one main
	 * interface for each interface type, or have no interface of that type at all.
	 *
	 * @static
	 *
	 * @param array $host_prototype  Host prototype object.
	 * @param array $interfaces      All single host prototype interfaces including existing ones in DB.
	 *
	 * @throws APIException  if two main or no main interfaces are given.
	 */
	private static function checkMainInterfaces(array $host_prototypes): void {
		foreach ($host_prototypes as $host_prototype) {
			if ($host_prototype['custom_interfaces'] == HOST_PROT_INTERFACES_INHERIT
					|| !array_key_exists('interfaces', $host_prototype)) {
				continue;
			}

			$interface_types = [];

			foreach ($host_prototype['interfaces'] as $interface) {
				if (!array_key_exists($interface['type'], $interface_types)) {
					$interface_types[$interface['type']] = ['main' => 0, 'all' => 0];
				}

				if ($interface['main'] == INTERFACE_PRIMARY) {
					$interface_types[$interface['type']]['main']++;
				}
				else {
					$interface_types[$interface['type']]['all']++;
				}

				if ($interface_types[$interface['type']]['main'] > 1) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_('Host prototype cannot have more than one default interface of the same type.')
					);
				}
			}

			foreach ($interface_types as $type => $counters) {
				if ($counters['all'] && !$counters['main']) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('No default interface for "%1$s" type on "%2$s".',
						hostInterfaceTypeNumToName($type), $host_prototype['name']
					));
				}
			}
		}
	}

	/**
	 * Deletes the given group prototype and all discovered groups.
	 * Deletes also group prototype children.
	 *
	 * @static
	 *
	 * @param array $group_prototypeids
	 */
	private static function deleteGroupPrototypes(array $group_prototypeids): void {
		// Lock group prototypes before delete to prevent server from adding new LLD elements.
		DBselect(
			'SELECT NULL'.
			' FROM group_prototype gp'.
			' WHERE '.dbConditionInt('gp.group_prototypeid', $group_prototypeids).
			' FOR UPDATE'
		);

		$child_group_prototypeids = DB::select('group_prototype', [
			'output' => ['group_prototypeid'],
			'filter' => ['templateid' => $group_prototypeids]
		]);
		if ($child_group_prototypeids) {
			self::deleteGroupPrototypes(array_column($child_group_prototypeids, 'group_prototypeid'));
		}

		$host_groups = DB::select('group_discovery', [
			'output' => ['groupid'],
			'filter' => ['parent_group_prototypeid' => $group_prototypeids]
		]);
		if ($host_groups) {
			API::HostGroup()->delete(array_column($host_groups, 'groupid'), true);
		}

		// delete group prototypes
		DB::delete('group_prototype', ['group_prototypeid' => $group_prototypeids]);
	}

	/**
	 * @static
	 *
	 * @param array      $host_prototypes
	 * @param string     $method
	 * @param array|null $db_host_prototypes
	 */
	private static function updateInterfaces(array &$host_prototypes, string $method, array $db_host_prototypes = null)
			: void {
		$ins_interfaces = [];
		$upd_interfaces = [];
		$del_interfaceids = [];

		foreach ($host_prototypes as &$host_prototype) {
			$db_interfaces = ($method === 'update'
					&& array_key_exists('interfaces', $db_host_prototypes[$host_prototype['hostid']]))
				? $db_host_prototypes[$host_prototype['hostid']]['interfaces']
				: [];

			if ($host_prototype['custom_interfaces'] == HOST_PROT_INTERFACES_INHERIT) {
				$host_prototype['interfaces'] = [];
				$del_interfaceids = array_merge($del_interfaceids, array_keys($db_interfaces));
				continue;
			}

			if (!array_key_exists('interfaces', $host_prototype)) {
				continue;
			}

			if ($host_prototype['interfaces']) {
				foreach ($host_prototype['interfaces'] as &$interface) {
					$index = self::compareInterface($interface, $db_interfaces);
					if ($index != -1) {
						$interface['interfaceid'] = $db_interfaces[$index]['interfaceid'];

						$upd_interface = DB::getUpdatedValues('interface', $interface, $db_interfaces[$index]);

						if ($upd_interface) {
							$upd_interfaces[] = [
								'values' => $upd_interface,
								'where' => ['interfaceid' => $interface['interfaceid']]
							];
						}

						unset($db_interfaces[$index]);
					}
					else {
						$ins_interfaces[] = ['hostid' => $host_prototype['hostid']] + $interface;
					}
				}
				unset($interface);
			}

			$del_interfaceids = array_merge($del_interfaceids, array_column($db_interfaces, 'interfaceid'));
		}
		unset($host_prototype);

		if ($del_interfaceids) {
			DB::delete('interface_snmp', ['interfaceid' => $del_interfaceids]);
			DB::delete('interface', ['interfaceid' => $del_interfaceids]);
		}

		if ($upd_interfaces) {
			DB::update('interface', $upd_interfaces);
		}

		if ($ins_interfaces) {
			$snmp_interfaces = [];
			$interfaceids = DB::insert('interface', $ins_interfaces);

			foreach ($host_prototypes as &$host_prototype) {
				if ($host_prototype['custom_interfaces'] == HOST_PROT_INTERFACES_INHERIT
						|| !array_key_exists('interfaces', $host_prototype)) {
					continue;
				}

				foreach ($host_prototype['interfaces'] as &$interface) {
					if (!array_key_exists('interfaceid', $interface)) {
						$interface['interfaceid'] = array_shift($interfaceids);

						if ($interface['type'] == INTERFACE_TYPE_SNMP) {
							$snmp_interfaces[] = ['interfaceid' => $interface['interfaceid']] + $interface['details'];
						}
					}
				}
				unset($interface);
			}
			unset($host_prototype);

			if ($snmp_interfaces) {
				DB::insert('interface_snmp', $snmp_interfaces, false);
			}
		}
	}

	/**
	 * Compare two interface. Return true if they are same, return false otherwise.
	 *
	 * @static
	 *
	 * @param array $host_interface
	 * @param array $db_interfaces
	 *
	 * @return int
	 */
	private static function compareInterface(array $host_interface, array $db_interfaces): int {
		$interface_fields = ['type', 'ip', 'dns', 'port'];
		$snmp_fields = ['version', 'community', 'bulk', 'securityname', 'securitylevel', 'authpassphrase',
			'privpassphrase', 'authprotocol', 'privprotocol', 'contextname'
		];

		foreach ($db_interfaces as $index => $db_interface) {
			foreach ($interface_fields as $field) {
				if (array_key_exists($field, $host_interface) && ($host_interface[$field] != $db_interface[$field])) {
					continue 2;
				}
			}

			if ($host_interface['type'] == INTERFACE_TYPE_SNMP) {
				foreach ($snmp_fields as $field) {
					if (array_key_exists($field, $host_interface['details'])
							&& ($host_interface['details'][$field] != $db_interface['details'][$field])) {
						continue 2;
					}
				}
			}

			return $index;
		}

		return -1;
	}

	/**
	 * @static
	 *
	 * @param array      $host_prototypes
	 * @param string     $method
	 * @param array|null $db_host_prototypes
	 */
	private static function updateGroupLinks(array &$host_prototypes, string $method, array $db_host_prototypes = null)
			: void {
		$ins_group_links = [];
		$del_group_prototypeids = [];

		foreach ($host_prototypes as &$host_prototype) {
			if (!array_key_exists('groupLinks', $host_prototype)) {
				continue;
			}

			$db_group_links = ($method === 'update')
				? array_column($db_host_prototypes[$host_prototype['hostid']]['groupLinks'], null, 'groupid')
				: [];

			foreach ($host_prototype['groupLinks'] as &$group_link) {
				if (array_key_exists($group_link['groupid'], $db_group_links)) {
					$group_link['group_prototypeid'] = $db_group_links[$group_link['groupid']]['group_prototypeid'];

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

		if ($ins_group_links) {
			$group_prototypeids = DB::insert('group_prototype', $ins_group_links);
		}

		if ($del_group_prototypeids) {
			self::deleteGroupPrototypes($del_group_prototypeids);
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
	 * @static
	 *
	 * @param array      $host_prototypes
	 * @param string     $method
	 * @param array|null $db_host_prototypes
	 */
	private static function updateGroupPrototypes(array &$host_prototypes, string $method,
			array $db_host_prototypes = null): void {
		$ins_group_prototypes = [];
		$del_group_prototypeids = [];

		foreach ($host_prototypes as &$host_prototype) {
			if (!array_key_exists('groupPrototypes', $host_prototype)) {
				continue;
			}

			$db_group_prototypes = ($method === 'update')
				? array_column($db_host_prototypes[$host_prototype['hostid']]['groupPrototypes'], null, 'name')
				: [];

			foreach ($host_prototype['groupPrototypes'] as &$group_prototype) {
				if (array_key_exists($group_prototype['name'], $db_group_prototypes)) {
					$group_prototype['group_prototypeid']
						= $db_group_prototypes[$group_prototype['name']]['group_prototypeid'];

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

		if ($ins_group_prototypes) {
			$group_prototypeids = DB::insert('group_prototype', $ins_group_prototypes);
		}

		if ($del_group_prototypeids) {
			self::deleteGroupPrototypes($del_group_prototypeids);
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
	 * @param string     $method
	 * @param array|null $db_host_prototypes
	 */
	private function updateTemplates(array &$host_prototypes, string $method, array $db_host_prototypes = null): void {
		foreach ($host_prototypes as &$host_prototype) {
			if (!array_key_exists('templates', $host_prototype)) {
				continue;
			}

			$templateids = array_column($host_prototype['templates'], 'templateid');
			$db_templates = ($method === 'update')
				? array_column($db_host_prototypes[$host_prototype['hostid']]['templates'], null, 'templateid')
				: [];
			$db_templateids = ($method === 'update')
				? array_column($db_host_prototypes[$host_prototype['hostid']]['templates'], 'templateid')
				: [];

			foreach ($host_prototype['templates'] as &$template) {
				if (array_key_exists($template['templateid'], $db_templates)) {
					$template['hosttemplateid'] = $db_templates[$template['templateid']]['hosttemplateid'];
				}
			}
			unset($template);

			if ($method === 'update') {
				$this->unlink(array_diff($db_templateids, $templateids), [$host_prototype['hostid']]);
			}

			$templates = $this->link(array_diff($templateids, $db_templateids), [$host_prototype['hostid']]);

			if ($templates) {
				$host_prototype['templates'] = $templates;
			}
		}
		unset($host_prototype);
	}

	/**
	 * @static
	 *
	 * @param array      $host_prototypes
	 * @param string     $method
	 * @param array|null $db_host_prototypes
	 */
	private static function updateHostInventories(array $host_prototypes, string $method,
			array $db_host_prototypes = null): void {
		$ins_inventories = [];
		$upd_inventories = [];
		$del_hostids = [];

		foreach ($host_prototypes as $host_prototype) {
			if (!array_key_exists('inventory_mode', $host_prototype)) {
				continue;
			}

			$db_host_prototype = ($method === 'update')
				? $db_host_prototypes[$host_prototype['hostid']]
				: [];

			if ($host_prototype['inventory_mode'] == HOST_INVENTORY_DISABLED) {
				$del_hostids[] = $host_prototype['hostid'];
			}
			elseif ($method === 'update' && $db_host_prototype['inventory_mode'] != HOST_INVENTORY_DISABLED) {
				if ($host_prototype['inventory_mode'] != $db_host_prototype['inventory_mode']) {
					$upd_inventories = [
						'values' =>['inventory_mode' => $host_prototype['inventory_mode']],
						'where' => ['hostid' => $host_prototype['hostid']]
					];
				}
			}
			else {
				$ins_inventories[] = [
					'hostid' => $host_prototype['hostid'],
					'inventory_mode' => $host_prototype['inventory_mode']
				];
			}
		}

		if ($ins_inventories) {
			DB::insertBatch('host_inventory', $ins_inventories, false);
		}

		if ($upd_inventories) {
			DB::update('host_inventory', $upd_inventories);
		}

		if ($del_hostids) {
			DB::delete('host_inventory', ['hostid' => $del_hostids]);
		}
	}

	/**
	 * @param array      $hosts
	 * @param string     $method
	 * @param array|null $db_hosts
	 */
	protected function updateTagsNew(array &$hosts, string $method, array $db_hosts = null): void {
		$id_field_name = $this instanceof CTemplate ? 'templateid' : 'hostid';

		$ins_tags = [];
		$del_hosttagids = [];

		foreach ($hosts as &$host) {
			if (!array_key_exists('tags', $host)) {
				continue;
			}

			$db_tags = ($method === 'update')
				? $db_hosts[$host[$id_field_name]]['tags']
				: [];

			$hosttagid_by_tag_value = [];
			foreach ($db_tags as $db_tag) {
				$hosttagid_by_tag_value[$db_tag['tag']][$db_tag['value']] = $db_tag['hosttagid'];
			}

			foreach ($host['tags'] as &$tag) {
				if (array_key_exists($tag['tag'], $hosttagid_by_tag_value)
						&& array_key_exists($tag['value'], $hosttagid_by_tag_value[$tag['tag']])) {
					$tag['hosttagid'] = $hosttagid_by_tag_value[$tag['tag']][$tag['value']];
					unset($db_tags[$tag['hosttagid']]);
				}
				else {
					$ins_tags[] = ['hostid' => $host[$id_field_name]] + $tag;
				}
			}
			unset($tag);

			$del_hosttagids = array_merge($del_hosttagids, array_keys($db_tags));
		}
		unset($host);

		if ($del_hosttagids) {
			DB::delete('host_tag', ['hosttagid' => $del_hosttagids]);
		}

		if ($ins_tags) {
			$hosttagids = DB::insert('host_tag', $ins_tags);
		}

		foreach ($hosts as &$host) {
			if (!array_key_exists('tags', $host)) {
				continue;
			}

			foreach ($host['tags'] as &$tag) {
				if (!array_key_exists('hosttagid', $tag)) {
					$tag['hosttagid'] = array_shift($hosttagids);
				}
			}
			unset($tag);
		}
		unset($host);
	}

	/**
	 * @param array      $hosts
	 * @param string     $method
	 * @param array|null $db_hosts
	 */
	protected function updateHostMacrosNew(array &$hosts, string $method, array $db_hosts = null): void {
		$id_field_name = $this instanceof CTemplate ? 'templateid' : 'hostid';

		$ins_hostmacros = [];
		$upd_hostmacros = [];
		$del_hostmacroids = [];

		foreach ($hosts as &$host) {
			if (!array_key_exists('macros', $host)) {
				continue;
			}

			$db_macros = ($method === 'update')
				? array_column($db_hosts[$host[$id_field_name]]['macros'], null, 'macro')
				: [];

			foreach ($host['macros'] as &$macro) {
				if (array_key_exists($macro['macro'], $db_macros)) {
					$db_macro = $db_macros[$macro['macro']];
					$macro['hostmacroid'] = $db_macro['hostmacroid'];
					unset($db_macros[$macro['macro']]);

					$upd_hostmacro = DB::getUpdatedValues('hostmacro', $macro, $db_macro);

					if ($upd_hostmacro) {
						$upd_hostmacros[] = [
							'values' => $upd_hostmacro,
							'where' => ['hostmacroid' => $macro['hostmacroid']]
						];
					}
				}
				else {
					$ins_hostmacros[] = ['hostid' => $host[$id_field_name]] + $macro;
				}
			}
			unset($macro);

			$del_hostmacroids = array_merge($del_hostmacroids, array_column($db_macros, 'hostmacroid'));
		}
		unset($host);

		if ($del_hostmacroids) {
			DB::delete('hostmacro', ['hostmacroid' => $del_hostmacroids]);
		}

		if ($upd_hostmacros) {
			DB::update('hostmacro', $upd_hostmacros);
		}

		if ($ins_hostmacros) {
			$hostmacroids = DB::insert('hostmacro', $ins_hostmacros);
		}

		foreach ($hosts as &$host) {
			if (!array_key_exists('macros', $host)) {
				continue;
			}

			foreach ($host['macros'] as &$macro) {
				if (!array_key_exists('hostmacroid', $macro)) {
					$macro['hostmacroid'] = array_shift($hostmacroids);
				}
			}
			unset($macro);
		}
		unset($host);
	}

	/**
	 * @static
	 *
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
	 * @static
	 *
	 * @return array
	 */
	private static function getInterfacesValidationRules(): array {
		return ['type' => API_MULTIPLE, 'rules' => [
					['if' => ['field' => 'custom_interfaces', 'in' => HOST_PROT_INTERFACES_CUSTOM], 'type' => API_OBJECTS, 'flags' => API_REQUIRED, 'fields' => [
						'type' =>		['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [INTERFACE_TYPE_AGENT, INTERFACE_TYPE_SNMP, INTERFACE_TYPE_IPMI, INTERFACE_TYPE_JMX])],
						'useip' =>		['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [INTERFACE_USE_DNS, INTERFACE_USE_IP])],
						'ip' =>			['type' => API_MULTIPLE, 'flags' => API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO | API_ALLOW_MACRO, 'rules' => [
											['if' => ['field' => 'useip', 'in' => INTERFACE_USE_IP], 'type' => API_IP, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength	('interface', 'ip')],
											['if' => ['field' => 'useip', 'in' => INTERFACE_USE_DNS], 'type' => API_STRING_UTF8, 'in' => '']
						]],
						'dns' =>		['type' => API_MULTIPLE, 'flags' => API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO | API_ALLOW_MACRO, 'rules' => [
											['if' => ['field' => 'useip', 'in' => INTERFACE_USE_DNS], 'type' => API_DNS, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('interface', 'dns')],
											['if' => ['field' => 'useip', 'in' => INTERFACE_USE_IP], 'type' => API_STRING_UTF8, 'in' => '']
						]],
						'port' =>		['type' => API_PORT, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO, 'length' => DB::getFieldLength('interface', 'port')],
						'main' =>		['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [INTERFACE_SECONDARY, INTERFACE_PRIMARY])],
						'details' =>	['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'type', 'in' => INTERFACE_TYPE_SNMP], 'type' => API_OBJECT, 'flags' => API_REQUIRED, 'fields' => [
							'version' =>		['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [SNMP_V1, SNMP_V2C, SNMP_V3])],
							'bulk' =>			['type' => API_INT32, 'in' => implode(',', [SNMP_BULK_DISABLED, SNMP_BULK_ENABLED])],
							'community' =>		['type' => API_MULTIPLE, 'rules' => [
													['if' => ['field' => 'version', 'in' => implode(',', [SNMP_V1, SNMP_V2C])], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('interface_snmp', 'community')],
													['if' => ['field' => 'version', 'in' => SNMP_V3], 'type' => API_STRING_UTF8, 'in' => '']
							]],
							'contextname' =>	['type' => API_MULTIPLE, 'rules' => [
													['if' => ['field' => 'version', 'in' => SNMP_V3], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('interface_snmp', 'contextname')],
													['if' => ['field' => 'version', 'in' => implode(',', [SNMP_V1, SNMP_V2C])], 'type' => API_STRING_UTF8, 'in' => '']
							]],
							'securityname' =>	['type' => API_MULTIPLE, 'rules' => [
													['if' => ['field' => 'version', 'in' => SNMP_V3], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('interface_snmp', 'securityname')],
													['if' => ['field' => 'version', 'in' => implode(',', [SNMP_V1, SNMP_V2C])], 'type' => API_STRING_UTF8, 'in' => '']
							]],
							'securitylevel' =>	['type' => API_MULTIPLE, 'rules' => [
													['if' => ['field' => 'version', 'in' => SNMP_V3], 'type' => API_INT32, 'default' => DB::getDefault('interface_snmp', 'securitylevel'), 'in' => implode(',', [ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV, ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV, ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV])],
													['if' => ['field' => 'version', 'in' => implode(',', [SNMP_V1, SNMP_V2C])], 'type' => API_STRING_UTF8, 'in' => '']
							]],
							'authprotocol' =>	['type' => API_MULTIPLE, 'rules' => [
													['if' => ['field' => 'securitylevel', 'in' => implode(',', [ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV, ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV])], 'type' => API_INT32, 'in' => implode(',', array_keys(getSnmpV3AuthProtocols()))],
													['if' => ['field' => 'securitylevel', 'in' => ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV], 'type' => API_STRING_UTF8, 'in' => '0,']
							]],
							'authpassphrase' =>	['type' => API_MULTIPLE, 'rules' => [
													['if' => ['field' => 'securitylevel', 'in' => implode(',', [ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV, ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('interface_snmp', 'authpassphrase')],
													['if' => ['field' => 'securitylevel', 'in' => ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV], 'type' => API_STRING_UTF8, 'in' => '']
							]],
							'privprotocol' =>	['type' => API_MULTIPLE, 'rules' => [
													['if' => ['field' => 'securitylevel', 'in' => ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV], 'type' => API_INT32, 'in' => implode(',', array_keys(getSnmpV3PrivProtocols()))],
													['if' => ['field' => 'securitylevel', 'in' => implode(',', [ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV, ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV])], 'type' => API_STRING_UTF8, 'in' => '0,']
							]],
							'privpassphrase' =>	['type' => API_MULTIPLE, 'rules' => [
													['if' => ['field' => 'securitylevel', 'in' => ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('interface_snmp', 'privpassphrase')],
													['if' => ['field' => 'securitylevel', 'in' => implode(',', [ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV, ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV])], 'type' => API_STRING_UTF8, 'in' => '']
							]]
											]],
											['if' => ['field' => 'type', 'in' => implode(',', [INTERFACE_TYPE_AGENT, INTERFACE_TYPE_IPMI, INTERFACE_TYPE_JMX])], 'type' => API_OBJECT, 'fields' => []]
						]]
					]],
					['if' => ['field' => 'custom_interfaces', 'in' => HOST_PROT_INTERFACES_INHERIT], 'type' => API_OBJECTS, 'fields' => []]
			]];
	}

	/**
	 * @static
	 *
	 * @param array $host_prototypes
	 * @param array $db_host_prototypes
	 */
	private static function addAffectedObjects(array $host_prototypes, array &$db_host_prototypes): void {
		$hostids = ['interfaces' => [], 'groupLinks' => [], 'groupPrototypes' => [], 'templates' => [], 'tags' => [],
			'macros' => []
		];

		foreach ($host_prototypes as $host_prototype) {
			$hostid = $host_prototype['hostid'];

			if (array_key_exists('interfaces', $host_prototype)
					|| $db_host_prototypes[$hostid]['custom_interfaces'] == HOST_PROT_INTERFACES_CUSTOM) {
				$hostids['interfaces'][] = $hostid;
				$db_host_prototypes[$hostid]['interfaces'] = [];
			}

			if (array_key_exists('groupLinks', $host_prototype)) {
				$hostids['groupLinks'][] = $hostid;
				$db_host_prototypes[$hostid]['groupLinks'] = [];
			}

			if (array_key_exists('groupPrototypes', $host_prototype)) {
				$hostids['groupPrototypes'][] = $hostid;
				$db_host_prototypes[$hostid]['groupPrototypes'] = [];
			}

			if (array_key_exists('templates', $host_prototype)) {
				$hostids['templates'][] = $hostid;
				$db_host_prototypes[$hostid]['templates'] = [];
			}

			if (array_key_exists('tags', $host_prototype)) {
				$hostids['tags'][] = $hostid;
				$db_host_prototypes[$hostid]['tags'] = [];
			}

			if (array_key_exists('macros', $host_prototype)) {
				$hostids['macros'][] = $hostid;
				$db_host_prototypes[$hostid]['macros'] = [];
			}

			$db_host_prototypes[$hostid]['ruleid'] = $db_host_prototypes[$hostid]['discoveryRule']['itemid'];
			unset($db_host_prototypes[$hostid]['discoveryRule']);
		}

		if ($hostids['interfaces']) {
			$hostid_by_interfaceid = [];
			$options = [
				'output' => ['interfaceid', 'hostid', 'main', 'type', 'useip', 'ip', 'dns', 'port'],
				'filter' => ['hostid' => $hostids['interfaces']]
			];
			$db_interfaces = DBselect(DB::makeSql('interface', $options));

			while ($db_interface = DBfetch($db_interfaces)) {
				$db_host_prototypes[$db_interface['hostid']]['interfaces'][$db_interface['interfaceid']]
					= array_diff_key($db_interface, array_flip(['hostid'])
				);

				if ($db_interface['type'] == INTERFACE_TYPE_SNMP) {
					$hostid_by_interfaceid[$db_interface['interfaceid']] = $db_interface['hostid'];
				}
			}

			if ($hostid_by_interfaceid) {
				$options = [
					'output' => ['interfaceid', 'version', 'bulk', 'community', 'securityname', 'securitylevel',
						'authpassphrase', 'privpassphrase', 'authprotocol', 'privprotocol', 'contextname'],
					'filter' => ['interfaceid' => array_keys($hostid_by_interfaceid)]
				];
				$db_snmps = DBselect(DB::makeSql('interface_snmp', $options));

				while ($db_snmp = DBfetch($db_snmps)) {
					$hostid = $hostid_by_interfaceid[$db_snmp['interfaceid']];
					$db_host_prototypes[$hostid]['interfaces'][$db_snmp['interfaceid']]['details']
						= array_diff_key($db_snmp, array_flip(['interfaceid'])
					);
				}
			}
		}

		if ($hostids['groupLinks']) {
			$options = [
				'output' => ['group_prototypeid', 'hostid', 'groupid'],
				'filter' => ['hostid' => $hostids['groupLinks'], 'name' => '']
			];
			$db_links = DBselect(DB::makeSql('group_prototype', $options));

			while ($db_link = DBfetch($db_links)) {
				$db_host_prototypes[$db_link['hostid']]['groupLinks'][$db_link['group_prototypeid']]
					= array_diff_key($db_link, array_flip(['hostid'])
				);
			}
		}

		if ($hostids['groupPrototypes']) {
			$options = [
				'output' => ['group_prototypeid', 'hostid', 'name'],
				'filter' => ['hostid' => $hostids['groupPrototypes'], 'groupid' => '0']
			];
			$db_groups = DBselect(DB::makeSql('group_prototype', $options));

			while ($db_link = DBfetch($db_groups)) {
				$db_host_prototypes[$db_link['hostid']]['groupPrototypes'][$db_link['group_prototypeid']]
					= array_diff_key($db_link, array_flip(['hostid'])
				);
			}
		}

		if ($hostids['templates']) {
			$options = [
				'output' => ['hosttemplateid', 'hostid', 'templateid'],
				'filter' => ['hostid' => $hostids['templates']]
			];
			$db_templates = DBselect(DB::makeSql('hosts_templates', $options));

			while ($db_template = DBfetch($db_templates)) {
				$db_host_prototypes[$db_template['hostid']]['templates'][$db_template['hosttemplateid']]
					= array_diff_key($db_template, array_flip(['hostid'])
				);
			}
		}

		if ($hostids['tags']) {
			$options = [
				'output' => ['hosttagid', 'hostid', 'tag', 'value'],
				'filter' => ['hostid' => $hostids['tags']]
			];
			$db_tags = DBselect(DB::makeSql('host_tag', $options));

			while ($db_tag = DBfetch($db_tags)) {
				$db_host_prototypes[$db_tag['hostid']]['tags'][$db_tag['hosttagid']] = array_diff_key($db_tag,
					array_flip(['hostid'])
				);
			}
		}

		if ($hostids['macros']) {
			$options = [
				'output' => ['hostmacroid', 'hostid', 'macro', 'value', 'description', 'type'],
				'filter' => ['hostid' => $hostids['macros']]
			];
			$db_macros = DBselect(DB::makeSql('hostmacro', $options));

			while ($db_macro = DBfetch($db_macros)) {
				$db_host_prototypes[$db_macro['hostid']]['macros'][$db_macro['hostmacroid']] = array_diff_key($db_macro,
					array_flip(['hostid'])
				);
			}
		}
	}
}
