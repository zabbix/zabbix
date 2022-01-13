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
	 * @param array         $options
	 * @param bool          $options['selectMacros']      Array of macros fields to be selected or string "extend".
	 * @param string|array  $options['selectInterfaces']  Return an "interfaces" property with host interfaces.
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


	/**
	 * Check for duplicated names.
	 *
	 * @param string $field_name
	 * @param array  $names_by_ruleid
	 *
	 * @throws APIException  if host prototype with same name already exists.
	 */
	private function checkDuplicates($field_name, array $names_by_ruleid) {
		$sql_where = [];
		foreach ($names_by_ruleid as $ruleid => $names) {
			$sql_where[] = '(i.itemid='.$ruleid.' AND '.dbConditionString('h.'.$field_name, $names).')';
		}

		$db_host_prototypes = DBfetchArray(DBselect(
				'SELECT i.name AS rule,h.'.$field_name.
				' FROM items i,host_discovery hd,hosts h'.
				' WHERE i.itemid=hd.parent_itemid'.
					' AND hd.hostid=h.hostid'.
					' AND ('.implode(' OR ', $sql_where).')',
				1
		));

		if ($db_host_prototypes) {
			$error = ($field_name === 'host')
				? _('Host prototype with host name "%1$s" already exists in discovery rule "%2$s".')
				: _('Host prototype with visible name "%1$s" already exists in discovery rule "%2$s".');

			self::exception(ZBX_API_ERROR_PARAMETERS,
				sprintf($error, $db_host_prototypes[0][$field_name], $db_host_prototypes[0]['rule'])
			);
		}
	}

	/**
	 * Validates the input parameters for the create() method.
	 *
	 * @param array $host_prototypes
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateCreate(array &$host_prototypes) {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['uuid'], ['ruleid', 'host'], ['ruleid', 'name']], 'fields' => [
			'uuid' =>				['type' => API_UUID],
			'ruleid' =>				['type' => API_ID, 'flags' => API_REQUIRED],
			'host' =>				['type' => API_H_NAME, 'flags' => API_REQUIRED | API_REQUIRED_LLD_MACRO, 'length' => DB::getFieldLength('hosts', 'host')],
			'name' =>				['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('hosts', 'name'), 'default_source' => 'host'],
			'custom_interfaces' =>	['type' => API_INT32, 'in' => implode(',', [HOST_PROT_INTERFACES_INHERIT, HOST_PROT_INTERFACES_CUSTOM]), 'default' => HOST_PROT_INTERFACES_INHERIT],
			'status' =>				['type' => API_INT32, 'in' => implode(',', [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED])],
			'discover' =>			['type' => API_INT32, 'in' => implode(',', [HOST_DISCOVER, HOST_NO_DISCOVER])],
			'interfaces' =>			['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'fields' => [
				'type' =>				['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [INTERFACE_TYPE_AGENT, INTERFACE_TYPE_SNMP, INTERFACE_TYPE_IPMI, INTERFACE_TYPE_JMX])],
				'useip' => 				['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [INTERFACE_USE_DNS, INTERFACE_USE_IP])],
				'ip' => 				['type' => API_IP, 'flags' => API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO | API_ALLOW_MACRO, 'length' => DB::getFieldLength('interface', 'ip')],
				'dns' =>				['type' => API_DNS, 'flags' => API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO | API_ALLOW_MACRO, 'length' => DB::getFieldLength('interface', 'dns')],
				'port' =>				['type' => API_PORT, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO, 'length' => DB::getFieldLength('interface', 'port')],
				'main' => 				['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [INTERFACE_SECONDARY, INTERFACE_PRIMARY])],
				'details' =>			['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'type', 'in' => (string) INTERFACE_TYPE_SNMP], 'type' => API_OBJECT, 'fields' => [
					'version' =>				['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [SNMP_V1, SNMP_V2C, SNMP_V3])],
					'bulk' =>					['type' => API_INT32, 'in' => implode(',', [SNMP_BULK_DISABLED, SNMP_BULK_ENABLED])],
					'community' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('interface_snmp', 'community')],
					'securityname' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('interface_snmp', 'securityname')],
					'securitylevel' =>			['type' => API_INT32, 'in' => implode(',', [ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV, ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV, ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV])],
					'authpassphrase' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('interface_snmp', 'authpassphrase')],
					'privpassphrase' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('interface_snmp', 'privpassphrase')],
					'authprotocol' =>			['type' => API_INT32, 'in' => implode(',', array_keys(getSnmpV3AuthProtocols()))],
					'privprotocol' =>			['type' => API_INT32, 'in' => implode(',', array_keys(getSnmpV3PrivProtocols()))],
					'contextname' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('interface_snmp', 'contextname')]
											]],
											['if' => ['field' => 'type', 'in' => implode(',', [INTERFACE_TYPE_AGENT, INTERFACE_TYPE_IPMI, INTERFACE_TYPE_JMX])], 'type' => API_OBJECT, 'fields' => []]
				]]
			]],
			'groupLinks' =>			['type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'uniq' => [['groupid']], 'fields' => [
				'groupid' =>			['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'groupPrototypes' =>	['type' => API_OBJECTS, 'uniq' => [['name']], 'fields' => [
				'name' =>				['type' => API_HG_NAME, 'flags' => API_REQUIRED | API_REQUIRED_LLD_MACRO, 'length' => DB::getFieldLength('hstgrp', 'name')]
			]],
			'templates' =>			['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['templateid']], 'fields' => [
				'templateid' =>			['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'tags' =>				['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['tag', 'value']], 'fields' => [
				'tag' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('host_tag', 'tag')],
				'value' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('host_tag', 'value'), 'default' => DB::getDefault('host_tag', 'value')]
			]],
			'macros' =>				['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['macro']], 'fields' => [
				'macro' =>			['type' => API_USER_MACRO, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('hostmacro', 'macro')],
				'type' =>			['type' => API_INT32, 'in' => implode(',', [ZBX_MACRO_TYPE_TEXT, ZBX_MACRO_TYPE_SECRET, ZBX_MACRO_TYPE_VAULT]), 'default' => ZBX_MACRO_TYPE_TEXT],
				'value' =>			['type' => API_MULTIPLE, 'flags' => API_REQUIRED, 'rules' => [
										['if' => ['field' => 'type', 'in' => implode(',', [ZBX_MACRO_TYPE_TEXT, ZBX_MACRO_TYPE_SECRET])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hostmacro', 'value')],
										['if' => ['field' => 'type', 'in' => implode(',', [ZBX_MACRO_TYPE_VAULT])], 'type' => API_VAULT_SECRET, 'length' => DB::getFieldLength('hostmacro', 'value')]
				]],
				'description' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hostmacro', 'description')]
			]],
			'inventory_mode' =>		['type' => API_INT32, 'in' => implode(',', [HOST_INVENTORY_DISABLED, HOST_INVENTORY_MANUAL, HOST_INVENTORY_AUTOMATIC])]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $host_prototypes, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$hosts_by_ruleid = [];
		$names_by_ruleid = [];
		$groupids = [];

		foreach ($host_prototypes as $host_prototype) {
			// Collect host group ID links for latter validation.
			foreach ($host_prototype['groupLinks'] as $group_prototype) {
				$groupids[$group_prototype['groupid']] = true;
			}

			$hosts_by_ruleid[$host_prototype['ruleid']][] = $host_prototype['host'];
			$names_by_ruleid[$host_prototype['ruleid']][] = $host_prototype['name'];
		}

		$ruleids = array_unique(zbx_objectValues($host_prototypes, 'ruleid'));
		$groupids = array_keys($groupids);

		$this->checkDiscoveryRulePermissions($ruleids);
		$this->checkHostGroupsPermissions($groupids);

		// Check if the host is discovered.
		$db_discovered_hosts = DBfetchArray(DBselect(
			'SELECT h.host'.
			' FROM items i,hosts h'.
			' WHERE i.hostid=h.hostid'.
				' AND '.dbConditionInt('i.itemid', $ruleids).
				' AND h.flags='.ZBX_FLAG_DISCOVERY_CREATED,
			1
		));

		if ($db_discovered_hosts) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Cannot create a host prototype on a discovered host "%1$s".', $db_discovered_hosts[0]['host'])
			);
		}

		$this->validateInterfaces($host_prototypes);

		$this->checkAndAddUuid($host_prototypes);
		$this->checkDuplicates('host', $hosts_by_ruleid);
		$this->checkDuplicates('name', $names_by_ruleid);
	}

	/**
	 * Check that only host prototypes on templates have UUID. Add UUID to all host prototypes on templates,
	 *   if it doesn't exist.
	 *
	 * @param array $host_prototypes_to_create
	 *
	 * @throws APIException
	 */
	protected function checkAndAddUuid(array &$host_prototypes_to_create): void {
		$discovery_ruleids = array_flip(array_column($host_prototypes_to_create, 'ruleid'));

		$db_templated_rules = DBfetchArrayAssoc(DBselect(
			'SELECT i.itemid, h.status'.
			' FROM items i, hosts h'.
			' WHERE '.dbConditionInt('i.itemid', array_keys($discovery_ruleids)).
			' AND i.hostid=h.hostid'.
			' AND h.status = ' . HOST_STATUS_TEMPLATE
		), 'itemid');

		foreach ($host_prototypes_to_create as $index => &$host_prototype) {
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

		$db_uuid = DB::select('hosts', [
			'output' => ['uuid'],
			'filter' => ['uuid' => array_column($host_prototypes_to_create, 'uuid')],
			'limit' => 1
		]);

		if ($db_uuid) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Entry with UUID "%1$s" already exists.', $db_uuid[0]['uuid'])
			);
		}
	}

	/**
	 * Creates the given host prototypes.
	 *
	 * @param array $host_prototypes
	 *
	 * @return array
	 */
	public function create(array $host_prototypes) {
		// 'templateid' validation happens during linkage.
		$this->validateCreate($host_prototypes);

		// Merge groups into group prototypes.
		foreach ($host_prototypes as &$host_prototype) {
			$host_prototype['groupPrototypes'] = array_merge(
				array_key_exists('groupPrototypes', $host_prototype) ? $host_prototype['groupPrototypes'] : [],
				$host_prototype['groupLinks']
			);
			unset($host_prototype['groupLinks']);
		}
		unset($host_prototype);

		$this->createReal($host_prototypes);
		$this->inherit($host_prototypes);

		$this->addAuditBulk(CAudit::ACTION_ADD, CAudit::RESOURCE_HOST_PROTOTYPE, $host_prototypes);

		return ['hostids' => zbx_objectValues($host_prototypes, 'hostid')];
	}

	/**
	 * Creates the host prototypes and inherits them to linked hosts and templates.
	 *
	 * @param array $hostPrototypes
	 */
	protected function createReal(array &$hostPrototypes) {
		foreach ($hostPrototypes as &$hostPrototype) {
			$hostPrototype['flags'] = ZBX_FLAG_DISCOVERY_PROTOTYPE;
		}
		unset($hostPrototype);

		// save the host prototypes
		$hostPrototypeIds = DB::insert($this->tableName(), $hostPrototypes);

		$groupPrototypes = [];
		$hostPrototypeDiscoveryRules = [];
		$hostPrototypeInventory = [];
		$hosts_tags = [];
		foreach ($hostPrototypes as $key => &$hostPrototype) {
			$hostPrototype['hostid'] = $hostPrototypeIds[$key];

			// save group prototypes
			foreach ($hostPrototype['groupPrototypes'] as $groupPrototype) {
				$groupPrototype['hostid'] = $hostPrototype['hostid'];
				$groupPrototypes[] = $groupPrototype;
			}

			// discovery rules
			$hostPrototypeDiscoveryRules[] = [
				'hostid' => $hostPrototype['hostid'],
				'parent_itemid' => $hostPrototype['ruleid']
			];

			// inventory
			if (array_key_exists('inventory_mode', $hostPrototype)
					&& $hostPrototype['inventory_mode'] != HOST_INVENTORY_DISABLED) {
				$hostPrototypeInventory[] = [
					'hostid' => $hostPrototype['hostid'],
					'inventory_mode' => $hostPrototype['inventory_mode']
				];
			}

			// tags
			if (array_key_exists('tags', $hostPrototype)) {
				foreach (zbx_toArray($hostPrototype['tags']) as $tag) {
					$hosts_tags[] = ['hostid' => $hostPrototype['hostid']] + $tag;
				}
			}
		}
		unset($hostPrototype);

		// save group prototypes
		$groupPrototypes = DB::save('group_prototype', $groupPrototypes);
		$i = 0;
		foreach ($hostPrototypes as &$hostPrototype) {
			foreach ($hostPrototype['groupPrototypes'] as &$groupPrototype) {
				$groupPrototype['group_prototypeid'] = $groupPrototypes[$i]['group_prototypeid'];
				$i++;
			}
			unset($groupPrototype);
		}
		unset($hostPrototype);

		// link host prototypes to discovery rules
		DB::insert('host_discovery', $hostPrototypeDiscoveryRules, false);

		// save inventory
		DB::insertBatch('host_inventory', $hostPrototypeInventory, false);

		// save tags
		if ($hosts_tags) {
			DB::insert('host_tag', $hosts_tags);
		}

		// link templates
		foreach ($hostPrototypes as $hostPrototype) {
			if (isset($hostPrototype['templates']) && $hostPrototype['templates']) {
				$this->link(zbx_objectValues($hostPrototype['templates'], 'templateid'), [$hostPrototype['hostid']]);
			}
		}

		$this->createInterfaces($hostPrototypes);
		$this->createHostMacros($hostPrototypes);
	}

	/**
	 * Validates the input parameters for the update() method.
	 *
	 * @param array $host_prototypes
	 * @param array $db_host_prototypes
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateUpdate(array &$host_prototypes, array &$db_host_prototypes = null) {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['hostid']], 'fields' => [
			'hostid' =>				['type' => API_ID, 'flags' => API_REQUIRED],
			'host' =>				['type' => API_H_NAME, 'flags' => API_REQUIRED_LLD_MACRO, 'length' => DB::getFieldLength('hosts', 'host')],
			'name' =>				['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('hosts', 'name')],
			'custom_interfaces' =>	['type' => API_INT32, 'in' => implode(',', [HOST_PROT_INTERFACES_INHERIT, HOST_PROT_INTERFACES_CUSTOM])],
			'status' =>				['type' => API_INT32, 'in' => implode(',', [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED])],
			'discover' =>			['type' => API_INT32, 'in' => implode(',', [ZBX_PROTOTYPE_DISCOVER, ZBX_PROTOTYPE_NO_DISCOVER])],
			'interfaces' =>			['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'fields' => [
				'type' =>				['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [INTERFACE_TYPE_AGENT, INTERFACE_TYPE_SNMP, INTERFACE_TYPE_IPMI, INTERFACE_TYPE_JMX])],
				'useip' => 				['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [INTERFACE_USE_DNS, INTERFACE_USE_IP])],
				'ip' => 				['type' => API_IP, 'flags' => API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO | API_ALLOW_MACRO, 'length' => DB::getFieldLength('interface', 'ip')],
				'dns' =>				['type' => API_DNS, 'flags' => API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO | API_ALLOW_MACRO, 'length' => DB::getFieldLength('interface', 'dns')],
				'port' =>				['type' => API_PORT, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO, 'length' => DB::getFieldLength('interface', 'port')],
				'main' => 				['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [INTERFACE_SECONDARY, INTERFACE_PRIMARY])],
				'details' =>			['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'type', 'in' => (string) INTERFACE_TYPE_SNMP], 'type' => API_OBJECT, 'fields' => [
					'version' =>				['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [SNMP_V1, SNMP_V2C, SNMP_V3])],
					'bulk' =>					['type' => API_INT32, 'in' => implode(',', [SNMP_BULK_DISABLED, SNMP_BULK_ENABLED])],
					'community' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('interface_snmp', 'community')],
					'securityname' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('interface_snmp', 'securityname')],
					'securitylevel' =>			['type' => API_INT32, 'in' => implode(',', [ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV, ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV, ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV])],
					'authpassphrase' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('interface_snmp', 'authpassphrase')],
					'privpassphrase' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('interface_snmp', 'privpassphrase')],
					'authprotocol' =>			['type' => API_INT32, 'in' => implode(',', array_keys(getSnmpV3AuthProtocols()))],
					'privprotocol' =>			['type' => API_INT32, 'in' => implode(',', array_keys(getSnmpV3PrivProtocols()))],
					'contextname' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('interface_snmp', 'contextname')]
											]],
											['if' => ['field' => 'type', 'in' => implode(',', [INTERFACE_TYPE_AGENT, INTERFACE_TYPE_IPMI, INTERFACE_TYPE_JMX])], 'type' => API_OBJECT, 'fields' => []]
				]]
			]],
			'groupLinks' =>			['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY, 'uniq' => [['group_prototypeid'], ['groupid']], 'fields' => [
				'group_prototypeid' =>	['type' => API_ID],
				'groupid' =>			['type' => API_ID]
			]],
			'groupPrototypes' =>	['type' => API_OBJECTS, 'uniq' => [['group_prototypeid'], ['name']], 'fields' => [
				'group_prototypeid' =>	['type' => API_ID],
				'name' =>				['type' => API_HG_NAME, 'flags' => API_REQUIRED_LLD_MACRO, 'length' => DB::getFieldLength('hstgrp', 'name')]
			]],
			'templates' =>			['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['templateid']], 'fields' => [
				'templateid' =>			['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'tags' =>				['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['tag', 'value']], 'fields' => [
				'tag' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('host_tag', 'tag')],
				'value' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('host_tag', 'value'), 'default' => DB::getDefault('host_tag', 'value')]
			]],
			'macros'  =>			['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['hostmacroid']], 'fields' => [
				'hostmacroid' =>		['type' => API_ID],
				'macro' =>				['type' => API_USER_MACRO, 'length' => DB::getFieldLength('hostmacro', 'macro')],
				'type' =>				['type' => API_INT32, 'in' => implode(',', [ZBX_MACRO_TYPE_TEXT, ZBX_MACRO_TYPE_SECRET, ZBX_MACRO_TYPE_VAULT])],
				'value' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hostmacro', 'value')],
				'description' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hostmacro', 'description')]
			]],
			'inventory_mode' =>		['type' => API_INT32, 'in' => implode(',', [HOST_INVENTORY_DISABLED, HOST_INVENTORY_MANUAL, HOST_INVENTORY_AUTOMATIC])]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $host_prototypes, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_host_prototypes = $this->get([
			'output' => ['hostid', 'host', 'name', 'custom_interfaces', 'status'],
			'selectDiscoveryRule' => ['itemid'],
			'selectGroupLinks' => ['group_prototypeid', 'groupid'],
			'selectGroupPrototypes' => ['group_prototypeid', 'name'],
			'selectInterfaces' => ['type', 'useip', 'ip', 'dns', 'port', 'main', 'details'],
			'hostids' => array_column($host_prototypes, 'hostid'),
			'editable' => true,
			'preservekeys' => true
		]);

		if (count($host_prototypes) != count($db_host_prototypes)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		if (array_column($host_prototypes, 'macros')) {
			$db_host_prototypes = $this->getHostMacros($db_host_prototypes);
			$host_prototypes = $this->validateHostMacros($host_prototypes, $db_host_prototypes);
		}

		$hosts_by_ruleid = [];
		$names_by_ruleid = [];

		foreach ($host_prototypes as &$host_prototype) {
			$db_host_prototype = $db_host_prototypes[$host_prototype['hostid']];
			$host_prototype['ruleid'] = $db_host_prototype['discoveryRule']['itemid'];

			if (array_key_exists('host', $host_prototype) && $host_prototype['host'] !== $db_host_prototype['host']) {
				$hosts_by_ruleid[$host_prototype['ruleid']][] = $host_prototype['host'];
			}

			if (array_key_exists('name', $host_prototype) && $host_prototype['name'] !== $db_host_prototype['name']) {
				$names_by_ruleid[$host_prototype['ruleid']][] = $host_prototype['name'];
			}
		}
		unset($host_prototype);

		$api_input_rules = ['type' => API_OBJECTS, 'uniq' => [['ruleid', 'host'], ['ruleid', 'name']], 'fields' => [
			'ruleid' =>	['type' => API_ID],
			'host' =>	['type' => API_H_NAME],
			'name' =>	['type' => API_STRING_UTF8]
		]];

		if (!CApiInputValidator::validateUniqueness($api_input_rules, $host_prototypes, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$groupids = [];
		$db_groupids = [];

		foreach ($host_prototypes as $host_prototype) {
			$db_host_prototype = $db_host_prototypes[$host_prototype['hostid']];

			foreach ($db_host_prototype['groupLinks'] as $db_group_link) {
				$db_groupids[$db_group_link['groupid']] = true;
			}

			$db_group_links = zbx_toHash($db_host_prototype['groupLinks'], 'group_prototypeid');
			$db_group_prototypes = zbx_toHash($db_host_prototype['groupPrototypes'], 'group_prototypeid');

			// Validate 'group_prototypeid' in 'groupLinks' property.
			if (array_key_exists('groupLinks', $host_prototype)) {
				foreach ($host_prototype['groupLinks'] as $group_link) {
					if (!$group_link) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
					}

					// Don't allow invalid 'group_prototypeid' parameters which do not belong to this 'hostid'.
					if (array_key_exists('group_prototypeid', $group_link)
							&& !array_key_exists($group_link['group_prototypeid'], $db_group_links)) {
						self::exception(ZBX_API_ERROR_PERMISSIONS,
							_('No permissions to referred object or it does not exist!')
						);
					}

					if (array_key_exists('groupid', $group_link)) {
						$groupids[$group_link['groupid']] = true;
					}
				}
			}

			// Validate 'group_prototypeid' in 'groupPrototypes' property.
			if (array_key_exists('groupPrototypes', $host_prototype)) {
				foreach ($host_prototype['groupPrototypes'] as $group_prototype) {
					if (!$group_prototype) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
					}

					// Don't allow invalid 'group_prototypeid' parameters which do not belong to this 'hostid'.
					if (array_key_exists('group_prototypeid', $group_prototype)
							&& !array_key_exists($group_prototype['group_prototypeid'], $db_group_prototypes)) {
						self::exception(ZBX_API_ERROR_PERMISSIONS,
							_('No permissions to referred object or it does not exist!')
						);
					}
				}
			}
		}

		// Collect only new given groupids for validation.
		$groupids = array_diff_key($groupids, $db_groupids);

		if ($groupids) {
			$this->checkHostGroupsPermissions(array_keys($groupids));
		}

		$host_prototypes = $this->extendObjectsByKey($host_prototypes, $db_host_prototypes, 'hostid',
			['host', 'name', 'custom_interfaces', 'groupLinks', 'groupPrototypes']
		);

		$this->validateInterfaces($host_prototypes);

		if ($hosts_by_ruleid) {
			$this->checkDuplicates('host', $hosts_by_ruleid);
		}
		if ($names_by_ruleid) {
			$this->checkDuplicates('name', $names_by_ruleid);
		}
	}

	/**
	 * Updates the given host prototypes.
	 *
	 * @param array $host_prototypes
	 *
	 * @return array
	 */
	public function update(array $host_prototypes) {
		$this->validateUpdate($host_prototypes, $db_host_prototypes);

		// merge group links into group prototypes
		foreach ($host_prototypes as &$host_prototype) {
			$host_prototype['groupPrototypes'] =
				array_merge($host_prototype['groupPrototypes'], $host_prototype['groupLinks']);
			unset($host_prototype['groupLinks']);
		}
		unset($host_prototype);

		$this->updateHostMacros($host_prototypes, $db_host_prototypes);

		$host_prototypes = $this->updateReal($host_prototypes);
		$this->updateInterfaces($host_prototypes);
		$this->inherit($host_prototypes);

		foreach ($db_host_prototypes as &$db_host_prototype) {
			unset($db_host_prototype['discoveryRule'], $db_host_prototype['groupLinks'],
				$db_host_prototype['groupPrototypes']
			);
		}
		unset($db_host_prototype);

		$this->addAuditBulk(CAudit::ACTION_UPDATE, CAudit::RESOURCE_HOST_PROTOTYPE, $host_prototypes,
			$db_host_prototypes
		);

		return ['hostids' => zbx_objectValues($host_prototypes, 'hostid')];
	}

	/**
	 * Updates the host prototypes and propagates the changes to linked hosts and templates.
	 *
	 * @param array $host_prototypes
	 *
	 * @return array
	 */
	protected function updateReal(array $host_prototypes) {
		// save the host prototypes
		foreach ($host_prototypes as $host_prototype) {
			DB::updateByPk($this->tableName(), $host_prototype['hostid'], $host_prototype);
		}

		$ex_host_prototypes = $this->get([
			'output' => ['hostid', 'inventory_mode'],
			'selectGroupLinks' => API_OUTPUT_EXTEND,
			'selectGroupPrototypes' => API_OUTPUT_EXTEND,
			'selectTemplates' => ['templateid'],
			'hostids' => zbx_objectValues($host_prototypes, 'hostid'),
			'preservekeys' => true
		]);

		// update related objects
		$inventory_create = [];
		$inventory_deleteids = [];
		foreach ($host_prototypes as $key => $host_prototype) {
			$ex_host_prototype = $ex_host_prototypes[$host_prototype['hostid']];

			// group prototypes
			if (isset($host_prototype['groupPrototypes'])) {
				foreach ($host_prototype['groupPrototypes'] as &$group_prototype) {
					$group_prototype['hostid'] = $host_prototype['hostid'];
				}
				unset($group_prototype);

				// save group prototypes
				$ex_group_prototypes = zbx_toHash(
					array_merge($ex_host_prototype['groupLinks'], $ex_host_prototype['groupPrototypes']),
					'group_prototypeid'
				);
				$modified_group_prototypes = [];
				foreach ($host_prototype['groupPrototypes'] as $group_prototype) {
					if (isset($group_prototype['group_prototypeid'])) {
						unset($ex_group_prototypes[$group_prototype['group_prototypeid']]);
					}

					$modified_group_prototypes[] = $group_prototype;
				}
				if ($ex_group_prototypes) {
					$this->deleteGroupPrototypes(array_keys($ex_group_prototypes));
				}
				$host_prototypes[$key]['groupPrototypes'] = DB::save('group_prototype', $modified_group_prototypes);
			}

			// templates
			if (isset($host_prototype['templates'])) {
				$existing_templateids = zbx_objectValues($ex_host_prototype['templates'], 'templateid');
				$new_templateids = zbx_objectValues($host_prototype['templates'], 'templateid');
				$this->unlink(array_diff($existing_templateids, $new_templateids), [$host_prototype['hostid']]);
				$this->link(array_diff($new_templateids, $existing_templateids), [$host_prototype['hostid']]);
			}

			// inventory
			if (array_key_exists('inventory_mode', $host_prototype)) {
				if ($host_prototype['inventory_mode'] == HOST_INVENTORY_DISABLED) {
					$inventory_deleteids[] = $host_prototype['hostid'];
				}
				else {
					$inventory = ['inventory_mode' => $host_prototype['inventory_mode']];

					if ($ex_host_prototype['inventory_mode'] != HOST_INVENTORY_DISABLED) {
						if ($host_prototype['inventory_mode'] != $ex_host_prototype['inventory_mode']) {
							DB::update('host_inventory', [
								'values' => $inventory,
								'where' => ['hostid' => $host_prototype['hostid']]
							]);
						}
					}
					else {
						$inventory_create[] = $inventory + ['hostid' => $host_prototype['hostid']];
					}
				}
			}
		}

		// save inventory
		DB::insertBatch('host_inventory', $inventory_create, false);
		DB::delete('host_inventory', ['hostid' => $inventory_deleteids]);

		$this->updateTags(array_column($host_prototypes, 'tags', 'hostid'));

		return $host_prototypes;
	}

	/**
	 * Updates the children of the host prototypes on the given hosts and propagates the inheritance to the child hosts.
	 *
	 * @param array $hostPrototypes		array of host prototypes to inherit
	 * @param array $hostids   			array of hosts to inherit to; if set to null, the children will be updated on all
	 *                              	child hosts
	 *
	 * @return bool
	 */
	protected function inherit(array $hostPrototypes, array $hostids = null) {
		if (empty($hostPrototypes)) {
			return true;
		}

		// prepare the child host prototypes
		$newHostPrototypes = $this->prepareInheritedObjects($hostPrototypes, $hostids);
		if (!$newHostPrototypes) {
			return true;
		}

		$insertHostPrototypes = [];
		$updateHostPrototypes = [];
		foreach ($newHostPrototypes as $newHostPrototype) {
			if (isset($newHostPrototype['hostid'])) {
				$updateHostPrototypes[] = $newHostPrototype;
			}
			else {
				$insertHostPrototypes[] = $newHostPrototype;
			}
		}

		// save the new host prototypes
		if ($insertHostPrototypes) {
			$this->createReal($insertHostPrototypes);
		}

		if ($updateHostPrototypes) {
			$updateHostPrototypes = $this->updateReal($updateHostPrototypes);
			$this->updateInterfaces($updateHostPrototypes);
			$macros = array_column($updateHostPrototypes, 'macros', 'hostid');

			if ($macros) {
				$this->updateMacros($macros);
			}
		}

		$host_prototypes = array_merge($updateHostPrototypes, $insertHostPrototypes);

		if ($host_prototypes) {
			$sql = 'SELECT hd.hostid'.
					' FROM host_discovery hd,items i,hosts h'.
					' WHERE hd.parent_itemid=i.itemid'.
						' AND i.hostid=h.hostid'.
						' AND h.status='.HOST_STATUS_TEMPLATE.
						' AND '.dbConditionInt('hd.hostid', zbx_objectValues($host_prototypes, 'hostid'));
			$valid_prototypes = DBfetchArrayAssoc(DBselect($sql), 'hostid');

			foreach ($host_prototypes as $key => $host_prototype) {
				if (!array_key_exists($host_prototype['hostid'], $valid_prototypes)) {
					unset($host_prototypes[$key]);
				}
			}
		}

		// propagate the inheritance to the children
		return $this->inherit($host_prototypes);
	}


	/**
	 * Prepares and returns an array of child host prototypes, inherited from host prototypes $host_prototypes
	 * on the given hosts.
	 *
	 * Each host prototype must have the "ruleid" parameter set.
	 *
	 * @param array     $host_prototypes
	 * @param array		$hostIds
	 *
	 * @return array 	an array of unsaved child host prototypes
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
			'filter' => [
				'templateid' => array_keys($discoveryRules)
			],
			'nopermissions' => true,
			'preservekeys' => true
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
			'discoveryids' => zbx_objectValues($childDiscoveryRules, 'itemid'),
			'nopermissions' => true
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
			'hostids' => $data['templateids'],
			'nopermissions' => true
		]);
		$hostPrototypes = $this->get([
			'output' => API_OUTPUT_EXTEND,
			'selectGroupLinks' => API_OUTPUT_EXTEND,
			'selectGroupPrototypes' => API_OUTPUT_EXTEND,
			'selectTags' => ['tag', 'value'],
			'selectTemplates' => ['templateid'],
			'selectDiscoveryRule' => ['itemid'],
			'selectInterfaces' => ['main', 'type', 'useip', 'ip', 'dns', 'port', 'details'],
			'discoveryids' => zbx_objectValues($discoveryRules, 'itemid'),
			'preservekeys' => true,
			'nopermissions' => true
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
	 * Validates the input parameters for the delete() method.
	 *
	 * @throws APIException if the input is invalid
	 *
	 * @param array $hostPrototypeIds
	 * @param bool 	$nopermissions
	 */
	protected function validateDelete($hostPrototypeIds, $nopermissions) {
		if (!$hostPrototypeIds) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		if (!$nopermissions) {
			$this->checkHostPrototypePermissions($hostPrototypeIds);
			$this->checkNotInherited($hostPrototypeIds);
		}
	}

	/**
	 * Delete host prototypes.
	 *
	 * @param array 	$hostPrototypeIds
	 * @param bool 		$nopermissions		if set to true, permission and template checks will be skipped
	 *
	 * @return array
	 */
	public function delete(array $hostPrototypeIds, $nopermissions = false) {
		$this->validateDelete($hostPrototypeIds, $nopermissions);

		// include child IDs
		$parentHostPrototypeIds = $hostPrototypeIds;
		$childHostPrototypeIds = [];
		do {
			$query = DBselect('SELECT h.hostid FROM hosts h WHERE '.dbConditionInt('h.templateid', $parentHostPrototypeIds));
			$parentHostPrototypeIds = [];
			while ($hostPrototype = DBfetch($query)) {
				$parentHostPrototypeIds[] = $hostPrototype['hostid'];
				$childHostPrototypeIds[] = $hostPrototype['hostid'];
			}
		} while (!empty($parentHostPrototypeIds));

		$hostPrototypeIds = array_merge($hostPrototypeIds, $childHostPrototypeIds);

		// Lock host prototypes before delete to prevent server from adding new LLD hosts.
		DBselect(
			'SELECT NULL'.
			' FROM hosts h'.
			' WHERE '.dbConditionInt('h.hostid', $hostPrototypeIds).
			' FOR UPDATE'
		);

		$deleteHostPrototypes = $this->get([
			'hostids' => $hostPrototypeIds,
			'output' => ['host'],
			'selectGroupPrototypes' => ['group_prototypeid'],
			'selectParentHost' => ['host'],
			'nopermissions' => true
		]);

		// delete discovered hosts
		$discoveredHosts = DBfetchArray(DBselect(
			'SELECT hostid FROM host_discovery WHERE '.dbConditionInt('parent_hostid', $hostPrototypeIds)
		));
		if ($discoveredHosts) {
			API::Host()->delete(zbx_objectValues($discoveredHosts, 'hostid'), true);
		}

		// delete group prototypes and discovered groups
		$groupPrototypeIds = [];
		foreach ($deleteHostPrototypes as $groupPrototype) {
			foreach ($groupPrototype['groupPrototypes'] as $groupPrototype) {
				$groupPrototypeIds[] = $groupPrototype['group_prototypeid'];
			}
		}
		$this->deleteGroupPrototypes($groupPrototypeIds);

		// delete host prototypes
		DB::delete($this->tableName(), ['hostid' => $hostPrototypeIds]);

		// TODO: REMOVE info
		foreach ($deleteHostPrototypes as $hostProtototype) {
			info(_s('Deleted: Host prototype "%1$s" on "%2$s".', $hostProtototype['host'], $hostProtototype['parentHost']['host']));
		}

		return ['hostids' => $hostPrototypeIds];
	}

	protected function link(array $templateids, array $targetids) {
		$this->checkHostPrototypePermissions($targetids);

		$links = parent::link($templateids, $targetids);

		foreach ($targetids as $targetid) {
			$linked_templates = API::Template()->get([
				'output' => [],
				'hostids' => [$targetid],
				'nopermissions' => true
			]);

			$result = DBselect(
				'SELECT i.key_,count(*)'.
				' FROM items i'.
				' WHERE '.dbConditionInt('i.hostid', array_merge($templateids, array_keys($linked_templates))).
				' GROUP BY i.key_'.
				' HAVING count(*)>1',
				1
			);
			if ($row = DBfetch($result)) {
				$target_templates = API::HostPrototype()->get([
					'output' => ['name'],
					'hostids' => [$targetid],
					'nopermissions' => true
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
	 * Checks if the current user has access to the given LLD rules.
	 *
	 * @throws APIException if the user doesn't have write permissions for the given LLD rules
	 *
	 * @param array $ruleids
	 */
	protected function checkDiscoveryRulePermissions(array $ruleids) {
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
	 * @throws APIException if the user doesn't have write permissions for the given host groups
	 *
	 * @param array $groupids
	 */
	protected function checkHostGroupsPermissions(array $groupids) {
		$db_groups = API::HostGroup()->get([
			'output' => ['name', 'flags'],
			'groupids' => $groupids,
			'editable' => true,
			'preservekeys' => true
		]);

		foreach ($groupids as $groupid) {
			if (!array_key_exists($groupid, $db_groups)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}

			$db_group = $db_groups[$groupid];

			// Check if group prototypes use discovered host groups.
			if ($db_group['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Group prototype cannot be based on a discovered host group "%1$s".', $db_group['name'])
				);
			}
		}
	}

	/**
	 * Checks if the current user has access to the given host prototypes.
	 *
	 * @throws APIException if the user doesn't have write permissions for the host prototypes.
	 *
	 * @param array $hostPrototypeIds
	 */
	protected function checkHostPrototypePermissions(array $hostPrototypeIds) {
		if ($hostPrototypeIds) {
			$hostPrototypeIds = array_unique($hostPrototypeIds);

			$count = $this->get([
				'countOutput' => true,
				'hostids' => $hostPrototypeIds,
				'editable' => true
			]);

			if ($count != count($hostPrototypeIds)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}
		}
	}

	/**
	 * Checks if the given host prototypes are not inherited from a template.
	 *
	 * @throws APIException 	if at least one host prototype is inherited
	 *
	 * @param array $hostPrototypeIds
	 */
	protected function checkNotInherited(array $hostPrototypeIds) {
		$query = DBSelect('SELECT hostid FROM hosts h WHERE h.templateid>0 AND '.dbConditionInt('h.hostid', $hostPrototypeIds), 1);

		if ($hostPrototype = DBfetch($query)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('Cannot delete templated host prototype.'));
		}
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
	 * @param array  $options
	 * @param array  $result
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
	 * Deletes the given group prototype and all discovered groups.
	 * Deletes also group prototype children.
	 *
	 * @param array $groupPrototypeIds
	 */
	protected function deleteGroupPrototypes(array $groupPrototypeIds) {
		// Lock group prototypes before delete to prevent server from adding new LLD elements.
		DBselect(
			'SELECT NULL'.
			' FROM group_prototype gp'.
			' WHERE '.dbConditionInt('gp.group_prototypeid', $groupPrototypeIds).
			' FOR UPDATE'
		);

		// delete child group prototypes
		$groupPrototypeChildren = DBfetchArray(DBselect(
			'SELECT gp.group_prototypeid FROM group_prototype gp WHERE '.dbConditionInt('templateid', $groupPrototypeIds)
		));
		if ($groupPrototypeChildren) {
			$this->deleteGroupPrototypes(zbx_objectValues($groupPrototypeChildren, 'group_prototypeid'));
		}

		// delete discovered groups
		$hostGroups = DBfetchArray(DBselect(
			'SELECT groupid FROM group_discovery WHERE '.dbConditionInt('parent_group_prototypeid', $groupPrototypeIds)
		));
		if ($hostGroups) {
			API::HostGroup()->delete(zbx_objectValues($hostGroups, 'groupid'), true);
		}

		// delete group prototypes
		DB::delete('group_prototype', ['group_prototypeid' => $groupPrototypeIds]);
	}

	/**
	 * Update host prototype macros, key is host prototype id and value is array of arrays with macro objects.
	 *
	 * @param array $update_macros  Array with macros objects.
	 */
	protected function updateMacros(array $update_macros): void {
		$ins_hostmacros = [];
		$upd_hostmacros = [];

		$db_hostmacros = DB::select('hostmacro', [
			'output' => ['hostid', 'macro', 'type', 'value', 'description'],
			'filter' => ['hostid' => array_keys($update_macros)],
			'preservekeys' => true
		]);
		$host_macros = array_fill_keys(array_keys($update_macros), []);

		foreach ($db_hostmacros as $hostmacroid => $db_hostmacro) {
			$host_macros[$db_hostmacro['hostid']][$db_hostmacro['macro']] = $hostmacroid;
		}

		foreach ($update_macros as $hostid => $hostmacros) {
			foreach ($hostmacros as $hostmacro) {
				if (array_key_exists($hostmacro['macro'], $host_macros[$hostid])) {
					$hostmacroid = $host_macros[$hostid][$hostmacro['macro']];

					$upd_hostmacro = DB::getUpdatedValues('hostmacro', $hostmacro, $db_hostmacro);

					if ($upd_hostmacro) {
						$upd_hostmacros[] = [
							'values' => $upd_hostmacro,
							'where' => ['hostmacroid' => $hostmacroid]
						];
					}

					unset($db_hostmacros[$hostmacroid], $host_macros[$hostid][$hostmacro['macro']]);
				}
				else {
					$ins_hostmacros[] = ['hostid' => $hostid] + $hostmacro;
				}
			}
		}

		if ($db_hostmacros) {
			DB::delete('hostmacro', ['hostmacroid' => array_keys($db_hostmacros)]);
		}

		if ($upd_hostmacros) {
			DB::update('hostmacro', $upd_hostmacros);
		}

		if ($ins_hostmacros) {
			DB::insert('hostmacro', $ins_hostmacros);
		}
	}

	/**
	 * Validate host prototype interfaces on create and update.
	 *
	 * @param array $host_prototypes                           Array of host prototype data.
	 * @param array $host_prototype[]['interfaces']            Host prototype interfaces.
	 * @param int   $host_prototype[]['custom_interfaces']     Use custom or inherited interfaces.
	 *
	 * @throws APIException if the interfaces input is invalid.
	 */
	private function validateInterfaces(array $host_prototypes): void {
		foreach ($host_prototypes as $hp_idx => $host_prototype) {
			if ($host_prototype['custom_interfaces'] == HOST_PROT_INTERFACES_CUSTOM) {
				if (array_key_exists('interfaces', $host_prototype) && $host_prototype['interfaces']) {
					foreach ($host_prototype['interfaces'] as $if_idx => $interface) {
						$path = '/'.($hp_idx + 1).'/interfaces/'.($if_idx + 1);

						if ($interface['useip'] == INTERFACE_USE_DNS) {
							if (!array_key_exists('dns', $interface)) {
								self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.', $path,
									_s('the parameter "%1$s" is missing', 'dns')
								));
							}
							elseif ($interface['dns'] === '') {
								self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
									$path.'/dns', _('cannot be empty')
								));
							}
						}
						else {
							if (!array_key_exists('ip', $interface)) {
								self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.', $path,
									_s('the parameter "%1$s" is missing', 'ip')
								));
							}
							elseif ($interface['ip'] === '') {
								self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
									$path.'/ip', _('cannot be empty')
								));
							}
						}

						if ($interface['type'] == INTERFACE_TYPE_SNMP) {
							if (array_key_exists('details', $interface)) {
								if ($interface['details']['version'] == SNMP_V1
										|| $interface['details']['version'] == SNMP_V2C) {
									if (!array_key_exists('community', $interface['details'])) {
										self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
											$path.'/details', _s('the parameter "%1$s" is missing', 'community')
										));
									}
									elseif ($interface['details']['community'] === '') {
										self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
											$path.'/details/community', _('cannot be empty')
										));
									}
								}
							}
							else {
								self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.', $path,
									_s('the parameter "%1$s" is missing', 'details')
								));
							}
						}
						elseif (array_key_exists('details', $interface) && $interface['details']) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.', $path,
								_s('unexpected parameter "%1$s"', 'details')
							));
						}
					}

					$this->checkMainInterfaces($host_prototype, $host_prototype['interfaces']);
				}
			}
			elseif (array_key_exists('interfaces', $host_prototype) && $host_prototype['interfaces']) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
					'/'.($hp_idx + 1).'/interfaces', _('should be empty')
				));
			}
		}
	}

	/**
	 * Check if main interfaces are correctly set for every interface type. Each host must either have only one main
	 * interface for each interface type, or have no interface of that type at all.
	 *
	 * @param array  $host_prototype          Host prototype object.
	 * @param string $host_prototype['name']  Host prototype name.
	 * @param array  $interfaces              All single host prototype interfaces including existing ones in DB.
	 * @param int    $interfaces[]['type']    Interface type.
	 * @param int    $interfaces[]['main']    If interface type is main.
	 *
	 * @throws APIException if two main or no main interfaces are given.
	 */
	private function checkMainInterfaces(array $host_prototype, array $interfaces): void {
		$interface_types = [];

		foreach ($interfaces as $interface) {
			if (!array_key_exists($interface['type'], $interface_types)) {
				$interface_types[$interface['type']] = ['main' => 0, 'all' => 0];
			}

			if ($interface['main'] == INTERFACE_PRIMARY) {
				$interface_types[$interface['type']]['main']++;
			}
			else {
				$interface_types[$interface['type']]['all']++;
			}
		}

		foreach ($interface_types as $type => $counters) {
			if ($counters['all'] && !$counters['main']) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('No default interface for "%1$s" type on "%2$s".',
					hostInterfaceTypeNumToName($type), $host_prototype['name']
				));
			}

			if ($counters['main'] > 1) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_('Host prototype cannot have more than one default interface of the same type.')
				);
			}
		}
	}

	/**
	 * Create host prototype interfaces.
	 *
	 * @param array $host_prototypes                  Array of host prototypes.
	 * @param array $host_prototypes[]['hostid']      Host prototype ID.
	 * @param array $host_prototypes[]['interfaces']  Host prototype interfaces data.
	 */
	private function createInterfaces(array $host_prototypes): void {
		$interfaces = [];
		foreach ($host_prototypes as $host_prototype) {
			if (array_key_exists('interfaces', $host_prototype)) {
				foreach ($host_prototype['interfaces'] as $interface) {
					$interface['hostid'] = $host_prototype['hostid'];
					$interfaces[] = $interface;
				}
			}
		}

		if ($interfaces) {
			$this->createInterfacesReal($interfaces);
		}
	}

	/**
	 * Update host prototype interfaces.
	 *
	 * @param array $host_prototypes                     Array of host prototypes.
	 * @param array $host_prototypes[]['hostid']         Host prototype ID.
	 * @param array $host_prototypes[]['interfaces']     Host prototype interfaces data.
	 * @param array $db_host_prototypes                  Array of host prototypes from DB.
	 * @param array $db_host_prototypes[]['interfaces']  Host prototype interfaces data from DB.
	 */
	private function updateInterfaces(array $host_prototypes): void {
		// We need to get interfaces with their interfaceid's.
		$interfaces = API::HostInterface()->get([
			'output' => ['hostid', 'interfaceid', 'type', 'useip', 'ip', 'dns', 'port', 'main', 'details'],
			'hostids' => array_column($host_prototypes, 'hostid'),
			'nopermissions' => true
		]);

		$db_host_prototype_interfaces = [];
		foreach($interfaces as $interface) {
			$db_host_prototype_interfaces[$interface['hostid']][] = $interface;
		}

		$interfaces_to_create = [];
		$interfaces_to_update = [];
		$interfaceids_to_delete = [];

		foreach ($host_prototypes as $host_prototype) {
			$db_interfaces = array_key_exists($host_prototype['hostid'], $db_host_prototype_interfaces)
				? $db_host_prototype_interfaces[$host_prototype['hostid']]
				: [];

			if ($host_prototype['custom_interfaces'] == HOST_PROT_INTERFACES_CUSTOM) {
				if (array_key_exists('interfaces', $host_prototype)) {
					if ($host_prototype['interfaces']) {
						CArrayHelper::sort($host_prototype['interfaces'], ['type', 'ip', 'dns']);
						CArrayHelper::sort($db_interfaces, ['type', 'ip', 'dns']);
						$host_prototype['interfaces'] = array_values($host_prototype['interfaces']);
						$db_interfaces = array_values($db_interfaces);

						foreach ($host_prototype['interfaces'] as $index => $interface) {
							if (array_key_exists($index, $db_interfaces)) {
								if (!$this->compareInterface($interface, $db_interfaces[$index])) {
									$interface['interfaceid'] = $db_interfaces[$index]['interfaceid'];
									$interface['hostid'] = $host_prototype['hostid'];
									$interfaces_to_update[] = $interface;
								}

								unset($db_interfaces[$index]);
							}
							else {
								// All remaining interfaces should be created.
								$interface['hostid'] = $host_prototype['hostid'];
								$interfaces_to_create[] = $interface;
							}
						}
					}
				}
				else {
					// Interfaces have not changed and should not be deleted;
					$db_interfaces = [];
				}
			}

			$interfaceids_to_delete += array_flip(array_column($db_interfaces, 'interfaceid'));
		}

		if ($interfaceids_to_delete) {
			$interfaceids_to_delete = array_flip($interfaceids_to_delete);
			DB::delete('interface_snmp', ['interfaceid' => $interfaceids_to_delete]);
			DB::delete('interface', ['interfaceid' => $interfaceids_to_delete]);
		}

		if ($interfaces_to_update) {
			$this->updateInterfacesReal($interfaces_to_update);
		}

		if ($interfaces_to_create) {
			$this->createInterfacesReal($interfaces_to_create);
		}
	}

	/**
	 * Compare two interface. Return true if they are same, return false otherwise.
	 *
	 * @param array $host_interface
	 * @param array $db_interface
	 *
	 * @return boolean
	 */
	private function compareInterface(array $host_interface, array $db_interface): bool {
		$interface_fields = ['type', 'ip', 'dns', 'useip', 'port', 'main'];
		$snmp_fields = ['version', 'community', 'bulk', 'securityname', 'securitylevel', 'authpassphrase',
			'privpassphrase', 'authprotocol', 'privprotocol', 'contextname'
		];

		foreach ($interface_fields as $field) {
			if (array_key_exists($field, $db_interface)
					&& (!array_key_exists($field, $host_interface)
						|| $host_interface[$field] != $db_interface[$field])) {
				return false;
			}
		}

		if ($db_interface['type'] == INTERFACE_TYPE_SNMP) {
			foreach ($snmp_fields as $field) {
				if (array_key_exists($field, $db_interface['details'])
						&& (!array_key_exists($field, $host_interface['details'])
							|| $host_interface['details'][$field] != $db_interface['details'][$field])) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Insert host prototype interfaces into DB.
	 */
	private function createInterfacesReal(array $interfaces): void {
		$interfaceids = DB::insert('interface', $interfaces);

		$snmp_interfaces = [];
		foreach ($interfaceids as $key => $id) {
			if ($interfaces[$key]['type'] == INTERFACE_TYPE_SNMP) {
				$snmp_interfaces[] = ['interfaceid' => $id] + $interfaces[$key]['details'];
			}
		}

		$this->createSnmpInterfaceDetails($snmp_interfaces);
	}

	/**
	 * Update host prototype interfaces in DB.
	 */
	private function updateInterfacesReal(array $interfaces): void {
		DB::delete('interface_snmp', ['interfaceid' => array_column($interfaces, 'interfaceid')]);

		$data = [];
		$snmp_interfaces = [];

		foreach ($interfaces as $interface) {
			if ($interface['type'] == INTERFACE_TYPE_SNMP) {
				$snmp_interfaces[] = ['interfaceid' => $interface['interfaceid']] + $interface['details'];
			}

			unset($interface['details']);

			$data[] = [
				'values' => $interface,
				'where' => ['interfaceid' => $interface['interfaceid']]
			];
		}

		DB::update('interface', $data);
		$this->createSnmpInterfaceDetails($snmp_interfaces);
	}

	/**
	 * Create host prototype SNMP interface details.
	 *
	 * @param array $snmp_interfaces                   Array of host prototype interface details.
	 * @param int   $snmp_interfaces[]['interfaceid']  Interface id.
	 */
	private function createSnmpInterfaceDetails(array $snmp_interfaces): void {
		if ($snmp_interfaces) {
			$snmp_interfaces = $this->sanitizeSnmpFields($snmp_interfaces);
			DB::insert('interface_snmp', $snmp_interfaces, false);
		}
	}

	/**
	 * Sanitize SNMP fields by version.
	 *
	 * @param array $interfaces
	 *
	 * @return array
	 */
	private function sanitizeSnmpFields(array $interfaces): array {
		$default_fields = [
			'community' => '',
			'securityname' => '',
			'securitylevel' => DB::getDefault('interface_snmp', 'securitylevel'),
			'authpassphrase' => '',
			'privpassphrase' => '',
			'authprotocol' => DB::getDefault('interface_snmp', 'authprotocol'),
			'privprotocol' => DB::getDefault('interface_snmp', 'privprotocol'),
			'contextname' => ''
		];

		foreach ($interfaces as &$interface) {
			if ($interface['version'] == SNMP_V1 || $interface['version'] == SNMP_V2C) {
				unset($interface['securityname'], $interface['securitylevel'], $interface['authpassphrase'],
					$interface['privpassphrase'], $interface['authprotocol'], $interface['privprotocol'],
					$interface['contextname']
				);
			}
			else {
				unset($interface['community']);
			}

			$interface = $interface + $default_fields;
		}

		return $interfaces;
	}
}
