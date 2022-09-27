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
 * Class containing methods for operations with host groups.
 */
class CHostGroup extends CApiService {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_ZABBIX_USER],
		'create' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'update' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN],
		'delete' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN],
		'massadd' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN],
		'massupdate' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN],
		'massremove' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN],
		'propagate' => ['min_user_type' => USER_TYPE_SUPER_ADMIN]
	];

	protected $tableName = 'hstgrp';
	protected $tableAlias = 'g';
	protected $sortColumns = ['groupid', 'name'];

	/**
	 * Get host groups.
	 *
	 * @param array $options
	 *
	 * @return array|int
	 */
	public function get(array $options) {
		$result = [];

		$output_fields = ['groupid', 'name', 'flags', 'uuid'];
		$host_fields = ['hostid', 'host', 'name', 'description', 'proxy_hostid', 'status', 'ipmi_authtype',
			'ipmi_privilege', 'ipmi_password', 'ipmi_username', 'inventory_mode', 'tls_connect', 'tls_accept',
			'tls_psk_identity', 'tls_psk', 'tls_issuer', 'tls_subject', 'maintenanceid', 'maintenance_type',
			'maintenance_from', 'maintenance_status', 'flags'
		];
		$group_discovery_fields = ['groupid', 'lastcheck', 'name', 'parent_group_prototypeid', 'ts_delete'];
		$discovery_rule_fields = ['itemid', 'hostid', 'name', 'type', 'key_', 'url', 'query_fields', 'request_method',
			'timeout', 'post_type', 'posts', 'headers', 'status_codes', 'follow_redirects', 'retrieve_mode',
			'http_proxy', 'authtype', 'verify_peer', 'verify_host', 'ssl_cert_file', 'ssl_key_file', 'ssl_key_password',
			'ipmi_sensor', 'jmx_endpoint', 'interfaceid', 'username', 'publickey', 'privatekey', 'password', 'snmp_oid',
			'parameters', 'params', 'delay', 'master_itemid', 'lifetime', 'trapper_hosts', 'allow_traps', 'description',
			'status', 'state', 'error', 'templateid'
		];
		$host_prototype_fields = ['hostid', 'host', 'name', 'status', 'templateid', 'inventory_mode', 'discover',
			'custom_interfaces', 'uuid'
		];

		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			// filter
			'groupids' =>							['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'hostids' =>							['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'graphids' =>							['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'triggerids' =>							['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'maintenanceids' =>						['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'monitored_hosts' =>					['type' => API_BOOLEAN, 'flags' => API_DEPRECATED, 'replacement' => 'with_monitored_hosts'],
			'with_monitored_hosts' =>				['type' => API_BOOLEAN, 'default' => false],
			'real_hosts' =>							['type' => API_BOOLEAN, 'flags' => API_DEPRECATED, 'replacement' => 'with_hosts'],
			'with_hosts' =>							['type' => API_BOOLEAN, 'default' => false],
			'with_items' =>							['type' => API_BOOLEAN, 'default' => false],
			'with_item_prototypes' =>				['type' => API_BOOLEAN, 'default' => false],
			'with_simple_graph_items' =>			['type' => API_BOOLEAN, 'default' => false],
			'with_simple_graph_item_prototypes' =>	['type' => API_BOOLEAN, 'default' => false],
			'with_monitored_items' =>				['type' => API_BOOLEAN, 'default' => false],
			'with_triggers' =>						['type' => API_BOOLEAN, 'default' => false],
			'with_monitored_triggers' =>			['type' => API_BOOLEAN, 'default' => false],
			'with_httptests' =>						['type' => API_BOOLEAN, 'default' => false],
			'with_monitored_httptests' =>			['type' => API_BOOLEAN, 'default' => false],
			'with_graphs' =>						['type' => API_BOOLEAN, 'default' => false],
			'with_graph_prototypes' =>				['type' => API_BOOLEAN, 'default' => false],
			'filter' =>								['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => ['groupid', 'name', 'flags', 'uuid']],
			'search' =>								['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => ['name']],
			'searchByAny' =>						['type' => API_BOOLEAN, 'default' => false],
			'startSearch' =>						['type' => API_BOOLEAN, 'default' => false],
			'excludeSearch' =>						['type' => API_BOOLEAN, 'default' => false],
			'searchWildcardsEnabled' =>				['type' => API_BOOLEAN, 'default' => false],
			// output
			'output' =>								['type' => API_OUTPUT, 'in' => implode(',', $output_fields), 'default' => API_OUTPUT_EXTEND],
			'selectHosts' =>						['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL | API_ALLOW_COUNT, 'in' => implode(',', $host_fields), 'default' => null],
			'selectGroupDiscovery' =>				['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', $group_discovery_fields), 'default' => null],
			'selectDiscoveryRule' =>				['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', $discovery_rule_fields), 'default' => null],
			'selectHostPrototype' =>				['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', $host_prototype_fields), 'default' => null],
			'countOutput' =>						['type' => API_BOOLEAN, 'default' => false],
			// sort and limit
			'sortfield' =>							['type' => API_STRINGS_UTF8, 'flags' => API_NORMALIZE, 'in' => implode(',', $this->sortColumns), 'uniq' => true, 'default' => []],
			'sortorder' =>							['type' => API_SORTORDER, 'default' => []],
			'limit' =>								['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => '1:'.ZBX_MAX_INT32, 'default' => null],
			'limitSelects' =>						['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => '1:'.ZBX_MAX_INT32, 'default' => null],
			// flags
			'editable' =>							['type' => API_BOOLEAN, 'default' => false],
			'preservekeys' =>						['type' => API_BOOLEAN, 'default' => false],
			'nopermissions' =>						['type' => API_BOOLEAN, 'default' => false]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$sqlParts = [
			'select' => ['hstgrp' => 'g.groupid'],
			'from' => ['hstgrp' => 'hstgrp g'],
			'where' => ['g.type='.HOST_GROUP_TYPE_HOST_GROUP],
			'order' => []
		];

		if (!$options['countOutput'] && $options['output'] === API_OUTPUT_EXTEND) {
			$options['output'] = $output_fields;
		}

		// editable + PERMISSION CHECK
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN && !$options['nopermissions']) {
			$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ;
			$userGroups = getUserGroupsByUserId(self::$userData['userid']);

			$sqlParts['where'][] = 'EXISTS ('.
				'SELECT NULL'.
				' FROM rights r'.
				' WHERE g.groupid=r.id'.
					' AND '.dbConditionInt('r.groupid', $userGroups).
				' GROUP BY r.id'.
				' HAVING MIN(r.permission)>'.PERM_DENY.
					' AND MAX(r.permission)>='.zbx_dbstr($permission).
				')';
		}

		// groupids
		if ($options['groupids'] !== null) {
			$sqlParts['where']['groupid'] = dbConditionInt('g.groupid', $options['groupids']);
		}

		// hostids
		if ($options['hostids'] !== null) {
			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['where'][] = dbConditionInt('hg.hostid', $options['hostids']);
			$sqlParts['where']['hgg'] = 'hg.groupid=g.groupid';
		}

		// triggerids
		if ($options['triggerids'] !== null) {
			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['from']['functions'] = 'functions f';
			$sqlParts['from']['items'] = 'items i';
			$sqlParts['where'][] = dbConditionInt('f.triggerid', $options['triggerids']);
			$sqlParts['where']['fi'] = 'f.itemid=i.itemid';
			$sqlParts['where']['hgi'] = 'hg.hostid=i.hostid';
			$sqlParts['where']['hgg'] = 'hg.groupid=g.groupid';
		}

		// graphids
		if ($options['graphids'] !== null) {
			$sqlParts['from']['gi'] = 'graphs_items gi';
			$sqlParts['from']['i'] = 'items i';
			$sqlParts['from']['hg'] = 'hosts_groups hg';
			$sqlParts['where'][] = dbConditionInt('gi.graphid', $options['graphids']);
			$sqlParts['where']['hgg'] = 'hg.groupid=g.groupid';
			$sqlParts['where']['igi'] = 'i.itemid=gi.itemid';
			$sqlParts['where']['hgi'] = 'hg.hostid=i.hostid';
		}

		// maintenanceids
		if ($options['maintenanceids'] !== null) {
			$sqlParts['from']['maintenances_groups'] = 'maintenances_groups mg';
			$sqlParts['where'][] = dbConditionInt('mg.maintenanceid', $options['maintenanceids']);
			$sqlParts['where']['hmh'] = 'g.groupid=mg.groupid';
		}

		$sub_sql_common = [];

		// with_monitored_hosts, with_hosts
		if ($options['with_monitored_hosts']) {
			$sub_sql_common['from']['h'] = 'hosts h';
			$sub_sql_common['where']['hg-h'] = 'hg.hostid=h.hostid';
			$sub_sql_common['where'][] = dbConditionInt('h.status', [HOST_STATUS_MONITORED]);
		}
		elseif ($options['with_hosts']) {
			$sub_sql_common['from']['h'] = 'hosts h';
			$sub_sql_common['where']['hg-h'] = 'hg.hostid=h.hostid';
			$sub_sql_common['where'][] = dbConditionInt('h.status', [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED]);
		}

		$sub_sql_parts = $sub_sql_common;

		// with_items, with_monitored_items, with_simple_graph_items
		if ($options['with_items']) {
			$sub_sql_parts['from']['i'] = 'items i';
			$sub_sql_parts['where']['hg-i'] = 'hg.hostid=i.hostid';
			$sub_sql_parts['where'][] = dbConditionInt('i.flags',
				[ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED]
			);
		}
		elseif ($options['with_monitored_items']) {
			$sub_sql_parts['from']['i'] = 'items i';
			$sub_sql_parts['from']['h'] = 'hosts h';
			$sub_sql_parts['where']['hg-i'] = 'hg.hostid=i.hostid';
			$sub_sql_parts['where']['hg-h'] = 'hg.hostid=h.hostid';
			$sub_sql_parts['where'][] = dbConditionInt('h.status', [HOST_STATUS_MONITORED]);
			$sub_sql_parts['where'][] = dbConditionInt('i.status', [ITEM_STATUS_ACTIVE]);
			$sub_sql_parts['where'][] = dbConditionInt('i.flags',
				[ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED]
			);
		}
		elseif ($options['with_simple_graph_items']) {
			$sub_sql_parts['from']['i'] = 'items i';
			$sub_sql_parts['where']['hg-i'] = 'hg.hostid=i.hostid';
			$sub_sql_parts['where'][] = dbConditionInt('i.value_type', [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64]);
			$sub_sql_parts['where'][] = dbConditionInt('i.status', [ITEM_STATUS_ACTIVE]);
			$sub_sql_parts['where'][] = dbConditionInt('i.flags',
				[ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED]
			);
		}

		// with_triggers, with_monitored_triggers
		if ($options['with_triggers']) {
			$sub_sql_parts['from']['i'] = 'items i';
			$sub_sql_parts['from']['f'] = 'functions f';
			$sub_sql_parts['from']['t'] = 'triggers t';
			$sub_sql_parts['where']['hg-i'] = 'hg.hostid=i.hostid';
			$sub_sql_parts['where']['i-f'] = 'i.itemid=f.itemid';
			$sub_sql_parts['where']['f-t'] = 'f.triggerid=t.triggerid';
			$sub_sql_parts['where'][] = dbConditionInt('t.flags',
				[ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED]
			);
		}
		elseif ($options['with_monitored_triggers']) {
			$sub_sql_parts['from']['i'] = 'items i';
			$sub_sql_parts['from']['h'] = 'hosts h';
			$sub_sql_parts['from']['f'] = 'functions f';
			$sub_sql_parts['from']['t'] = 'triggers t';
			$sub_sql_parts['where']['hg-i'] = 'hg.hostid=i.hostid';
			$sub_sql_parts['where']['hg-h'] = 'hg.hostid=h.hostid';
			$sub_sql_parts['where']['i-f'] = 'i.itemid=f.itemid';
			$sub_sql_parts['where']['f-t'] = 'f.triggerid=t.triggerid';
			$sub_sql_parts['where'][] = dbConditionInt('h.status', [HOST_STATUS_MONITORED]);
			$sub_sql_parts['where'][] = dbConditionInt('i.status', [ITEM_STATUS_ACTIVE]);
			$sub_sql_parts['where'][] = dbConditionInt('t.status', [TRIGGER_STATUS_ENABLED]);
			$sub_sql_parts['where'][] = dbConditionInt('t.flags',
				[ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED]
			);
		}

		// with_httptests, with_monitored_httptests
		if ($options['with_httptests']) {
			$sub_sql_parts['from']['ht'] = 'httptest ht';
			$sub_sql_parts['where']['hg-ht'] = 'hg.hostid=ht.hostid';
		}
		elseif ($options['with_monitored_httptests']) {
			$sub_sql_parts['from']['ht'] = 'httptest ht';
			$sub_sql_parts['where']['hg-ht'] = 'hg.hostid=ht.hostid';
			$sub_sql_parts['where'][] = dbConditionInt('ht.status', [HTTPTEST_STATUS_ACTIVE]);
		}

		// with_graphs
		if ($options['with_graphs']) {
			$sub_sql_parts['from']['i'] = 'items i';
			$sub_sql_parts['from']['gi'] = 'graphs_items gi';
			$sub_sql_parts['from']['gr'] = 'graphs gr';
			$sub_sql_parts['where']['hg-i'] = 'hg.hostid=i.hostid';
			$sub_sql_parts['where']['i-gi'] = 'i.itemid=gi.itemid';
			$sub_sql_parts['where']['gi-gr'] = 'gi.graphid=gr.graphid';
			$sub_sql_parts['where'][] = dbConditionInt('gr.flags',
				[ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED]
			);
		}

		if ($sub_sql_parts) {
			$sub_sql_parts['from']['hg'] = 'hosts_groups hg';
			$sub_sql_parts['where']['g-hg'] = 'g.groupid=hg.groupid';

			$sqlParts['where'][] = 'EXISTS ('.
				'SELECT NULL'.
				' FROM '.implode(',', $sub_sql_parts['from']).
				' WHERE '.implode(' AND ', array_unique($sub_sql_parts['where'])).
			')';
		}

		$sub_sql_parts = $sub_sql_common;

		// with_item_prototypes, with_simple_graph_item_prototypes
		if ($options['with_item_prototypes']) {
			$sub_sql_parts['from']['i'] = 'items i';
			$sub_sql_parts['where']['hg-i'] = 'hg.hostid=i.hostid';
			$sub_sql_parts['where'][] = dbConditionInt('i.flags', [ZBX_FLAG_DISCOVERY_PROTOTYPE]);
		}
		elseif ($options['with_simple_graph_item_prototypes']) {
			$sub_sql_parts['from']['i'] = 'items i';
			$sub_sql_parts['where']['hg-i'] = 'hg.hostid=i.hostid';
			$sub_sql_parts['where'][] = dbConditionInt('i.value_type', [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64]);
			$sub_sql_parts['where'][] = dbConditionInt('i.status', [ITEM_STATUS_ACTIVE]);
			$sub_sql_parts['where'][] = dbConditionInt('i.flags', [ZBX_FLAG_DISCOVERY_PROTOTYPE]);
		}

		// with_graph_prototypes
		if ($options['with_graph_prototypes']) {
			$sub_sql_parts['from']['i'] = 'items i';
			$sub_sql_parts['from']['gi'] = 'graphs_items gi';
			$sub_sql_parts['from']['gr'] = 'graphs gr';
			$sub_sql_parts['where']['hg-i'] = 'hg.hostid=i.hostid';
			$sub_sql_parts['where']['i-gi'] = 'i.itemid=gi.itemid';
			$sub_sql_parts['where']['gi-gr'] = 'gi.graphid=gr.graphid';
			$sub_sql_parts['where'][] = dbConditionInt('gr.flags', [ZBX_FLAG_DISCOVERY_PROTOTYPE]);
		}

		if ($sub_sql_parts) {
			$sub_sql_parts['from']['hg'] = 'hosts_groups hg';
			$sub_sql_parts['where']['g-hg'] = 'g.groupid=hg.groupid';

			$sqlParts['where'][] = 'EXISTS ('.
				'SELECT NULL'.
				' FROM '.implode(',', $sub_sql_parts['from']).
				' WHERE '.implode(' AND ', array_unique($sub_sql_parts['where'])).
			')';
		}

		// filter
		if ($options['filter'] !== null) {
			$this->dbFilter('hstgrp g', $options, $sqlParts);
		}

		// search
		if ($options['search'] !== null) {
			zbx_db_search('hstgrp g', $options, $sqlParts);
		}

		// limit
		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$res = DBselect(self::createSelectQueryFromParts($sqlParts), $options['limit']);
		while ($group = DBfetch($res)) {
			if ($options['countOutput']) {
				$result = $group['rowscount'];

			}
			else {
				$result[$group['groupid']] = $group;
			}
		}

		if ($options['countOutput']) {
			return $result;
		}

		if ($result) {
			$result = $this->addRelatedObjects($options, $result);
			$result = $this->unsetExtraFields($result, ['groupid'], $options['output']);

			if (!$options['preservekeys']) {
				$result = array_values($result);
			}
		}

		return $result;
	}

	/**
	 * @param array  $groups
	 *
	 * @return array
	 */
	public function create(array $groups): array {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS,
				_s('No permissions to call "%1$s.%2$s".', 'hostgroup', __FUNCTION__)
			);
		}

		self::validateCreate($groups);

		$groupids = DB::insert('hstgrp', $groups);

		foreach ($groups as $index => &$group) {
			$group['groupid'] = $groupids[$index];
		}
		unset($group);

		self::inheritUserGroupsData($groups);

		self::addAuditLog(CAudit::ACTION_ADD, CAudit::RESOURCE_HOST_GROUP, $groups);

		return ['groupids' => $groupids];
	}

	/**
	 * @param array  $groups
	 *
	 * @return array
	 */
	public function update(array $groups): array {
		$this->validateUpdate($groups, $db_groups);

		$upd_groups = [];

		foreach ($groups as $group) {
			$upd_group = DB::getUpdatedValues('hstgrp', $group, $db_groups[$group['groupid']]);

			if ($upd_group) {
				$upd_groups[] = [
					'values' => $upd_group,
					'where' => ['groupid' => $group['groupid']]
				];
			}
		}

		if ($upd_groups) {
			DB::update('hstgrp', $upd_groups);
		}

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_HOST_GROUP, $groups, $db_groups);

		return ['groupids' => array_column($groups, 'groupid')];
	}

	/**
	 * @param array $groupids
	 *
	 * @return array
	 */
	public function delete(array $groupids): array {
		$this->validateDelete($groupids, $db_groups);

		self::deleteForce($db_groups);

		return ['groupids' => $groupids];
	}

	/**
	 * @param array $db_groups
	 */
	public static function deleteForce(array $db_groups): void {
		$groupids = array_keys($db_groups);

		// delete sysmap element
		DB::delete('sysmaps_elements', ['elementtype' => SYSMAP_ELEMENT_TYPE_HOST_GROUP, 'elementid' => $groupids]);

		// disable actions
		// actions from conditions
		$actionids = [];
		$db_actions = DBselect(
			'SELECT DISTINCT c.actionid'.
			' FROM conditions c'.
			' WHERE c.conditiontype='.CONDITION_TYPE_HOST_GROUP.
				' AND '.dbConditionString('c.value', $groupids)
		);
		while ($db_action = DBfetch($db_actions)) {
			$actionids[$db_action['actionid']] = $db_action['actionid'];
		}

		// actions from operations
		$db_actions = DBselect(
			'SELECT o.actionid'.
			' FROM operations o,opgroup og'.
			' WHERE o.operationid=og.operationid AND '.dbConditionInt('og.groupid', $groupids).
			' UNION'.
			' SELECT o.actionid'.
			' FROM operations o,opcommand_grp ocg'.
			' WHERE o.operationid=ocg.operationid AND '.dbConditionInt('ocg.groupid', $groupids)
		);
		while ($db_action = DBfetch($db_actions)) {
			$actionids[$db_action['actionid']] = $db_action['actionid'];
		}

		if (!empty($actionids)) {
			$update = [];
			$update[] = [
				'values' => ['status' => ACTION_STATUS_DISABLED],
				'where' => ['actionid' => $actionids]
			];
			DB::update('actions', $update);
		}

		// delete action conditions
		DB::delete('conditions', [
			'conditiontype' => CONDITION_TYPE_HOST_GROUP,
			'value' => $groupids
		]);

		// delete action operation groups
		$operationids = [];
		$db_operations = DBselect(
			'SELECT DISTINCT og.operationid'.
			' FROM opgroup og'.
			' WHERE '.dbConditionInt('og.groupid', $groupids)
		);
		while ($db_operation = DBfetch($db_operations)) {
			$operationids[$db_operation['operationid']] = $db_operation['operationid'];
		}
		DB::delete('opgroup', [
			'groupid' => $groupids
		]);

		// delete action operation commands
		$db_operations = DBselect(
			'SELECT DISTINCT ocg.operationid'.
			' FROM opcommand_grp ocg'.
			' WHERE '.dbConditionInt('ocg.groupid', $groupids)
		);
		while ($db_operation = DBfetch($db_operations)) {
			$operationids[$db_operation['operationid']] = $db_operation['operationid'];
		}
		DB::delete('opcommand_grp', [
			'groupid' => $groupids
		]);

		// delete empty operations
		$del_operationids = [];
		$db_operations = DBselect(
			'SELECT DISTINCT o.operationid'.
			' FROM operations o'.
			' WHERE '.dbConditionInt('o.operationid', $operationids).
				' AND NOT EXISTS (SELECT NULL FROM opgroup og WHERE o.operationid=og.operationid)'.
				' AND NOT EXISTS (SELECT NULL FROM opcommand_grp ocg WHERE o.operationid=ocg.operationid)'
		);
		while ($db_operation = DBfetch($db_operations)) {
			$del_operationids[$db_operation['operationid']] = $db_operation['operationid'];
		}

		DB::delete('operations', ['operationid' => $del_operationids]);

		DB::delete('hstgrp', ['groupid' => $groupids]);

		self::addAuditLog(CAudit::ACTION_DELETE, CAudit::RESOURCE_HOST_GROUP, $db_groups);
	}

	/**
	 * @param array $groups
	 *
	 * @static
	 *
	 * @throws APIException if the input is invalid.
	 */
	private static function validateCreate(array &$groups): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['uuid'], ['name']], 'fields' => [
			'uuid' =>	['type' => API_UUID],
			'name' =>	['type' => API_HG_NAME, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('hstgrp', 'name')]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $groups, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		self::checkDuplicates($groups);
		self::checkAndAddUuid($groups);
	}

	/**
	 * Validates input data for update method.
	 *
	 * @param array $groups     [IN/OUT]
	 * @param array $db_groups  [OUT]
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateUpdate(array &$groups, array &$db_groups = null): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['groupid'], ['name']], 'fields' => [
			'groupid' =>	['type' => API_ID, 'flags' => API_REQUIRED],
			'name' =>		['type' => API_HG_NAME, 'length' => DB::getFieldLength('hstgrp', 'name')]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $groups, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_groups = $this->get([
			'output' => ['groupid', 'name', 'flags'],
			'groupids' => array_column($groups, 'groupid'),
			'editable' => true,
			'preservekeys' => true
		]);

		if (count($db_groups) != count($groups)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		self::checkGroupsNotDiscovered($db_groups);
		self::checkDuplicates($groups, $db_groups);
	}

	/**
	 * Validates if groups can be deleted.
	 *
	 * @param array      $groupids
	 * @param array|null $db_groups
	 *
	 * @throws APIException if the input is invalid.
	 */
	private function validateDelete(array $groupids, array &$db_groups = null): void {
		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];

		if (!CApiInputValidator::validate($api_input_rules, $groupids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_groups = $this->get([
			'output' => ['groupid', 'name'],
			'groupids' => $groupids,
			'editable' => true,
			'preservekeys' => true
		]);

		if (count($db_groups) != count($groupids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		$discovery_groupid = CSettingsHelper::get(CSettingsHelper::DISCOVERY_GROUPID);

		if (array_key_exists($discovery_groupid, $db_groups)) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Host group "%1$s" is group for discovered hosts and cannot be deleted.',
					$db_groups[$discovery_groupid]['name']
				)
			);
		}

		// Check if a group is used by a host prototype.
		$group_prototypes = DB::select('group_prototype', [
			'output' => ['groupid'],
			'filter' => ['groupid' => $groupids],
			'limit' => 1
		]);

		if ($group_prototypes) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Group "%1$s" cannot be deleted, because it is used by a host prototype.',
					$db_groups[$group_prototypes[0]['groupid']]['name']
				)
			);
		}

		self::validateDeleteForce($db_groups);
	}

	/**
	 * @param array $db_groups
	 *
	 * @throws APIException if unable to delete groups.
	 */
	public static function validateDeleteForce(array $db_groups): void {
		$groupids = array_keys($db_groups);

		$db_hosts = API::Host()->get([
			'output' => ['host'],
			'groupids' => $groupids,
			'nopermissions' => true,
			'preservekeys' => true
		]);

		if ($db_hosts) {
			self::checkHostsWithoutGroups($db_hosts, $groupids);
		}

		$db_scripts = DB::select('scripts', [
			'output' => ['groupid'],
			'filter' => ['groupid' => $groupids],
			'limit' => 1
		]);

		if ($db_scripts) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Host group "%1$s" cannot be deleted, because it is used in a global script.',
					$db_groups[$db_scripts[0]['groupid']]['name']
				)
			);
		}

		$corr_condition_groups = DB::select('corr_condition_group', [
			'output' => ['groupid'],
			'filter' => ['groupid' => $groupids],
			'limit' => 1
		]);

		if ($corr_condition_groups) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Group "%1$s" cannot be deleted, because it is used in a correlation condition.',
					$db_groups[$corr_condition_groups[0]['groupid']]['name']
				)
			);
		}

		self::checkMaintenances($groupids);
	}

	/**
	 * Check for unique host group names.
	 *
	 * @static
	 *
	 * @param array      $groups
	 * @param array|null $db_groups
	 *
	 * @throws APIException if host group names are not unique.
	 */
	private static function checkDuplicates(array $groups, array $db_groups = null): void {
		$names = [];

		foreach ($groups as $group) {
			if (!array_key_exists('name', $group)) {
				continue;
			}

			if ($db_groups === null || $group['name'] !== $db_groups[$group['groupid']]['name']) {
				$names[] = $group['name'];
			}
		}

		if (!$names) {
			return;
		}

		$duplicates = DB::select('hstgrp', [
			'output' => ['name'],
			'filter' => ['name' => $names, 'type' => HOST_GROUP_TYPE_HOST_GROUP],
			'limit' => 1
		]);

		if ($duplicates) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Host group "%1$s" already exists.', $duplicates[0]['name']));
		}
	}

	/**
	 * Check that new UUIDs are not already used and generate UUIDs where missing.
	 *
	 * @static
	 *
	 * @param array $groups_to_create
	 *
	 * @throws APIException
	 */
	private static function checkAndAddUuid(array &$groups_to_create): void {
		foreach ($groups_to_create as &$group) {
			if (!array_key_exists('uuid', $group)) {
				$group['uuid'] = generateUuidV4();
			}
		}
		unset($group);

		$db_uuid = DB::select('hstgrp', [
			'output' => ['uuid'],
			'filter' => ['uuid' => array_column($groups_to_create, 'uuid'), 'type' => HOST_GROUP_TYPE_HOST_GROUP],
			'limit' => 1
		]);

		if ($db_uuid) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Entry with UUID "%1$s" already exists.', $db_uuid[0]['uuid'])
			);
		}
	}

	/**
	 * Check whether no one of passed groups are discovered host.
	 *
	 * @static
	 *
	 * @param array  $db_groups
	 * @param string $db_groups[][name]
	 * @param int    $db_groups[][flags]
	 *
	 * @throws APIException
	 */
	private static function checkGroupsNotDiscovered(array $db_groups): void {
		foreach ($db_groups as $db_group) {
			if ($db_group['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Cannot update a discovered host group "%1$s".', $db_group['name'])
				);
			}
		}
	}

	/**
	 * Check that no maintenance object will be left without hosts and host groups as the result of the given host
	 * groups deletion.
	 *
	 * @param array $groupids
	 *
	 * @throws APIException
	 */
	private static function checkMaintenances(array $groupids): void {
		$maintenance = DBfetch(DBselect(
			'SELECT m.maintenanceid,m.name'.
			' FROM maintenances m'.
			' WHERE NOT EXISTS ('.
				'SELECT NULL'.
				' FROM maintenances_groups mg'.
				' WHERE m.maintenanceid=mg.maintenanceid'.
					' AND '.dbConditionId('mg.groupid', $groupids, true).
			')'.
				' AND NOT EXISTS ('.
					'SELECT NULL'.
					' FROM maintenances_hosts mh'.
					' WHERE m.maintenanceid=mh.maintenanceid'.
				')'
		, 1));

		if ($maintenance) {
			$maintenance_groups = DBfetchColumn(DBselect(
				'SELECT g.name'.
				' FROM maintenances_groups mg,hstgrp g'.
				' WHERE mg.groupid=g.groupid'.
					' AND '.dbConditionId('mg.maintenanceid', [$maintenance['maintenanceid']])
			), 'name');

			self::exception(ZBX_API_ERROR_PARAMETERS, _n(
				'Cannot delete host group %1$s because maintenance "%2$s" must contain at least one host or host group.',
				'Cannot delete host groups %1$s because maintenance "%2$s" must contain at least one host or host group.',
				'"'.implode('", "', $maintenance_groups).'"', $maintenance['name'], count($maintenance_groups)
			));
		}
	}

	/**
	 * Inherit user groups data of parent host groups.
	 *
	 * @param array $groups
	 */
	private static function inheritUserGroupsData(array $groups): void {
		$group_links = self::getGroupLinks($groups);

		if ($group_links) {
			$usrgrps = [];
			$db_usrgrps = [];

			self::prepareInheritedRights($group_links, $usrgrps, $db_usrgrps);
			self::prepareInheritedTagFilters($group_links, $usrgrps, $db_usrgrps);

			if ($usrgrps) {
				CUserGroup::updateForce(array_values($usrgrps), $db_usrgrps);
			}
		}
	}

	/**
	 * Get links of parent groups to given groups.
	 *
	 * @param array $groups
	 *
	 * @return array Array where keys are parent group IDs and values are the array of child group IDs.
	 */
	private static function getGroupLinks(array $groups): array {
		$parent_names = [];

		foreach ($groups as $group) {
			$name = $group['name'];

			while (($pos = strrpos($name, '/')) !== false) {
				$name = substr($name, 0, $pos);
				$parent_names[$name] = true;
			}
		}

		if (!$parent_names) {
			return [];
		}

		$options = [
			'output' => ['groupid', 'name'],
			'filter' => ['name' => array_keys($parent_names), 'type' => HOST_GROUP_TYPE_HOST_GROUP]
		];
		$result = DBselect(DB::makeSql('hstgrp', $options));

		$parents_groupids = [];

		while ($row = DBfetch($result)) {
			$parents_groupids[$row['name']] = $row['groupid'];
		}

		if (!$parents_groupids) {
			return [];
		}

		$group_links = [];

		foreach ($groups as $group) {
			$name = $group['name'];

			while (($pos = strrpos($name, '/')) !== false) {
				$name = substr($name, 0, $pos);

				if (array_key_exists($name, $parents_groupids)) {
					$group_links[$parents_groupids[$name]][] = $group['groupid'];
					break;
				}
			}
		}

		return $group_links;
	}

	/**
	 * Prepare rights to inherit from parent host groups.
	 *
	 * @static
	 *
	 * @param array  $group_links
	 * @param array  $usrgrps
	 * @param array  $db_usrgrps
	 */
	private static function prepareInheritedRights(array $group_links, array &$usrgrps, array &$db_usrgrps): void {
		$db_rights = DBselect(
			'SELECT r.groupid,r.permission,r.id,g.name'.
			' FROM rights r,usrgrp g'.
			' WHERE r.groupid=g.usrgrpid'.
				' AND '.dbConditionInt('r.id', array_keys($group_links))
		);

		while ($db_right = DBfetch($db_rights)) {
			if (!array_key_exists($db_right['groupid'], $usrgrps)) {
				$usrgrps[$db_right['groupid']] = ['usrgrpid' => $db_right['groupid']];
				$db_usrgrps[$db_right['groupid']] = [
					'usrgrpid' => $db_right['groupid'],
					'name' => $db_right['name']
				];
			}

			if (!array_key_exists('hostgroup_rights', $db_usrgrps[$db_right['groupid']])) {
				$db_usrgrps[$db_right['groupid']]['hostgroup_rights'] = [];
			}

			foreach ($group_links[$db_right['id']] as $hstgrpid) {
				$usrgrps[$db_right['groupid']]['hostgroup_rights'][] = [
					'permission' => $db_right['permission'],
					'id' => $hstgrpid
				];
			}
		}
	}

	/**
	 * Prepare tag filters to inherit from parent host groups.
	 *
	 * @static
	 *
	 * @param array  $group_links
	 * @param array  $usrgrps
	 * @param array  $db_usrgrps
	 */
	private static function prepareInheritedTagFilters(array $group_links, array &$usrgrps,
			array &$db_usrgrps): void {
		$db_tag_filters = DBselect(
			'SELECT t.usrgrpid,t.groupid,t.tag,t.value,g.name'.
			' FROM tag_filter t,usrgrp g'.
			' WHERE t.usrgrpid=g.usrgrpid'.
				' AND '.dbConditionInt('t.groupid', array_keys($group_links))
		);

		while ($db_tag_filter = DBfetch($db_tag_filters)) {
			if (!array_key_exists($db_tag_filter['usrgrpid'], $usrgrps)) {
				$usrgrps[$db_tag_filter['usrgrpid']] = ['usrgrpid' => $db_tag_filter['usrgrpid']];
				$db_usrgrps[$db_tag_filter['usrgrpid']] = [
					'usrgrpid' => $db_tag_filter['usrgrpid'],
					'name' => $db_tag_filter['name']
				];
			}

			if (!array_key_exists('tag_filters', $db_usrgrps[$db_tag_filter['usrgrpid']])) {
				$db_usrgrps[$db_tag_filter['usrgrpid']]['tag_filters'] = [];
			}

			foreach ($group_links[$db_tag_filter['groupid']] as $hstgrpid) {
				$usrgrps[$db_tag_filter['usrgrpid']]['tag_filters'][] = [
					'groupid' => $hstgrpid,
					'tag' => $db_tag_filter['tag'],
					'value' => $db_tag_filter['value']
				];
			}
		}
	}

	/**
	 * Add given hosts to given host groups.
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function massAdd(array $data): array {
		$this->validateMassAdd($data, $db_groups);

		$groups = self::getGroupsByData($data, $db_groups);
		$ins_hosts_groups = self::getInsHostsGroups($groups, __FUNCTION__);

		if ($ins_hosts_groups) {
			$hostgroupids = DB::insertBatch('hosts_groups', $ins_hosts_groups);
			self::addHostgroupids($groups, $hostgroupids);
		}

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_HOST_GROUP, $groups, $db_groups);

		return ['groupids' => array_column($data['groups'], 'groupid')];
	}

	/**
	 * Replace hosts on the given host groups.
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function massUpdate(array $data): array {
		$this->validateMassUpdate($data, $db_groups);

		$groups = self::getGroupsByData($data, $db_groups);
		$ins_hosts_groups = self::getInsHostsGroups($groups, __FUNCTION__, $db_hostgroupids);
		$del_hostgroupids = self::getDelHostgroupids($db_groups, $db_hostgroupids);

		if ($ins_hosts_groups) {
			$hostgroupids = DB::insertBatch('hosts_groups', $ins_hosts_groups);
			self::addHostgroupids($groups, $hostgroupids);
		}

		if ($del_hostgroupids) {
			DB::delete('hosts_groups', ['hostgroupid' => $del_hostgroupids]);
		}

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_HOST_GROUP, $groups, $db_groups);

		return ['groupids' => array_column($data['groups'], 'groupid')];
	}

	/**
	 * Remove given hosts from given host groups.
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function massRemove(array $data): array {
		$this->validateMassRemove($data, $db_groups);

		$groups = self::getGroupsByData([], $db_groups);
		$del_hostgroupids = self::getDelHostgroupids($db_groups);

		if ($del_hostgroupids) {
			DB::delete('hosts_groups', ['hostgroupid' => $del_hostgroupids]);
		}

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_HOST_GROUP, $groups, $db_groups);

		return ['groupids' => $data['groupids']];
	}

	/**
	 * @param array      $data
	 * @param array|null $db_groups
	 *
	 * @throws APIException if the input is invalid.
	 */
	private function validateMassAdd(array &$data, ?array &$db_groups): void {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'groups' =>		['type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['groupid']], 'fields' => [
				'groupid' =>	['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'hosts' =>		['type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['hostid']], 'fields' => [
				'hostid'=>		['type' => API_ID, 'flags' => API_REQUIRED]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $data, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_groups = $this->get([
			'output' => ['groupid','name'],
			'groupids' => array_column($data['groups'], 'groupid'),
			'editable' => true,
			'preservekeys' => true
		]);

		if (count($db_groups) != count($data['groups'])) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		$hostids = array_column($data['hosts'], 'hostid');

		$db_hosts = API::Host()->get([
			'output' => ['host', 'flags'],
			'hostids' => $hostids,
			'editable' => true
		]);

		if (count($db_hosts) != count($hostids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		self::checkHostsNotDiscovered($db_hosts);
		self::addAffectedObjects($hostids, $db_groups);
	}

	/**
	 * Validation of massUpdate input fields.
	 *
	 * @param array      $data       [IN/OUT]
	 * @param array|null $db_groups  [OUT]
	 *
	 * @throws APIException if the input is invalid.
	 */
	private function validateMassUpdate(array &$data, ?array &$db_groups): void {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'groups' =>		['type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['groupid']], 'fields' => [
				'groupid' =>	['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'hosts' =>		['type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NORMALIZE, 'uniq' => [['hostid']], 'fields' => [
				'hostid'=>		['type' => API_ID, 'flags' => API_REQUIRED]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $data, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$groupids = array_column($data['groups'], 'groupid');

		$db_groups = $this->get([
			'output' => ['groupid', 'name'],
			'groupids' => $groupids,
			'editable' => true,
			'preservekeys' => true
		]);

		if (count($db_groups) != count($groupids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		$hostids = array_column($data['hosts'], 'hostid');

		if ($hostids) {
			$db_hosts = API::Host()->get([
				'output' => ['host', 'flags'],
				'hostids' => $hostids,
				'editable' => true
			]);

			if (count($db_hosts) != count($hostids)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}

			self::checkHostsNotDiscovered($db_hosts);
		}

		self::addAffectedObjects([], $db_groups, $db_hostids);

		$del_hostids = array_diff($db_hostids, $hostids);

		if ($del_hostids) {
			self::checkDeletedHosts($del_hostids, $groupids);
		}
	}

	/**
	 * Validation of massRemove input fields.
	 *
	 * @param array      $data       [IN/OUT]
	 * @param array|null $db_groups  [OUT]
	 *
	 * @throws APIException if the input is invalid.
	 */
	private function validateMassRemove(array &$data, ?array &$db_groups): void {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'groupids' =>	['type' => API_IDS, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_NORMALIZE, 'uniq' => true],
			'hostids' =>	['type' => API_IDS, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_NORMALIZE, 'uniq' => true]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $data, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_groups = $this->get([
			'output' => ['groupid', 'name'],
			'groupids' => $data['groupids'],
			'editable' => true,
			'preservekeys' => true
		]);

		if (count($db_groups) != count($data['groupids'])) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		$db_hosts = API::Host()->get([
			'output' => ['host', 'flags'],
			'hostids' => $data['hostids'],
			'editable' => true,
			'preservekeys' => true
		]);

		if (count($db_hosts) != count($data['hostids'])) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		self::checkHostsNotDiscovered($db_hosts);
		self::checkHostsWithoutGroups($db_hosts, $data['groupids']);

		self::addAffectedObjects($data['hostids'], $db_groups);
	}

	/**
	 * Check whether no one of given hosts are discovered host.
	 *
	 * @static
	 *
	 * @param array  $db_hosts
	 * @param string $db_hosts[][host]
	 * @param int    $db_hosts[][flags]
	 *
	 * @throws APIException
	 */
	private static function checkHostsNotDiscovered(array $db_hosts): void {
		foreach ($db_hosts as $db_host) {
			if ($db_host['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Cannot update groups for discovered host "%1$s".', $db_host['host'])
				);
			}
		}
	}

	/**
	 * Check to exclude an opportunity to leave host without groups.
	 *
	 * @static
	 *
	 * @param array  $db_hosts
	 * @param string $db_hosts[<hostid>]['host']
	 * @param array  $groupids
	 *
	 * @throws APIException
	 */
	public static function checkHostsWithoutGroups(array $db_hosts, array $groupids): void {
		$hostids = array_keys($db_hosts);

		$hostids_with_groups = DBfetchColumn(DBselect(
			'SELECT DISTINCT hg.hostid'.
			' FROM hosts_groups hg'.
			' WHERE '.dbConditionInt('hg.groupid', $groupids, true).
				' AND '.dbConditionInt('hg.hostid', $hostids)
		), 'hostid');

		$hostids_without_groups = array_diff($hostids, $hostids_with_groups);

		if ($hostids_without_groups) {
			$hostid = reset($hostids_without_groups);
			$error = _s('Host "%1$s" cannot be without host group.', $db_hosts[$hostid]['host']);

			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}
	}

	/**
	 * Add the existing hosts whether these are affected by the mass methods.
	 * If host IDs passed as empty array, all host links of given groups will be collected from database and all
	 * existing host IDs will be collected in $db_hostids.
	 *
	 * @static
	 *
	 * @param array      $hostids
	 * @param array      $db_groups
	 * @param array|null $db_hostids
	 */
	private static function addAffectedObjects(array $hostids, array &$db_groups, array &$db_hostids = null): void {
		if (!$hostids) {
			$db_hostids = [];
		}

		foreach ($db_groups as &$db_group) {
			$db_group['hosts'] = [];
		}
		unset($db_group);

		if ($hostids) {
			$options = [
				'output' => ['hostgroupid', 'hostid', 'groupid'],
				'filter' => [
					'hostid' => $hostids,
					'groupid' => array_keys($db_groups)
				]
			];
			$db_hosts_groups = DBselect(DB::makeSql('hosts_groups', $options));
		}
		else {
			$db_hosts_groups = DBselect(
				'SELECT hg.hostgroupid,hg.hostid,hg.groupid'.
				' FROM hosts_groups hg,hosts h'.
				' WHERE hg.hostid=h.hostid'.
					' AND '.dbConditionInt('hg.groupid', array_keys($db_groups)).
					' AND h.flags='.ZBX_FLAG_DISCOVERY_NORMAL
			);
		}

		while ($link = DBfetch($db_hosts_groups)) {
			$db_groups[$link['groupid']]['hosts'][$link['hostgroupid']] = [
				'hostgroupid' => $link['hostgroupid'],
				'hostid' => $link['hostid']
			];

			if (!$hostids) {
				$db_hostids[$link['hostid']] = true;
			}
		}

		if (!$hostids) {
			$db_hostids = array_keys($db_hostids);
		}
	}

	/**
	 * Check to delete given hosts from the given host groups.
	 *
	 * @static
	 *
	 * @param array $del_hostids
	 * @param array $groupids
	 *
	 * @throws APIException
	 */
	private static function checkDeletedHosts(array $del_hostids, array $groupids): void {
		$db_hosts = API::Host()->get([
			'output' => ['host'],
			'hostids' => $del_hostids,
			'editable' => true,
			'preservekeys' => true
		]);

		if (count($db_hosts) != count($del_hostids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		self::checkHostsWithoutGroups($db_hosts, $groupids);
	}

	/**
	 * Get host groups input array based on requested data and database data.
	 *
	 * @static
	 *
	 * @param array $data
	 * @param array $db_groups
	 *
	 * @return array
	 */
	private static function getGroupsByData(array $data, array $db_groups): array {
		$groups = [];

		foreach ($db_groups as $db_group) {
			$group = ['groupid' => $db_group['groupid']];

			$group['hosts'] = [];
			$db_hosts = array_column($db_group['hosts'], null, 'hostid');

			if (array_key_exists('hosts', $data)) {
				foreach ($data['hosts'] as $host) {
					if (array_key_exists($host['hostid'], $db_hosts)) {
						$group['hosts'][] = $db_hosts[$host['hostid']];
					}
					else {
						$group['hosts'][] = ['hostid' => $host['hostid']];
					}
				}
			}

			$groups[] = $group;
		}

		return $groups;
	}

	/**
	 * Get rows to insert hosts on the given host groups.
	 *
	 * @static
	 *
	 * @param array      $groups
	 * @param string     $method
	 * @param array|null $db_hostgroupids
	 *
	 * @return array
	 */
	private static function getInsHostsGroups(array $groups, string $method, array &$db_hostgroupids = null): array {
		$ins_hosts_groups = [];

		if ($method === 'massUpdate') {
			$db_hostgroupids = [];
		}

		foreach ($groups as $group) {
			foreach ($group['hosts'] as $host) {
				if (!array_key_exists('hostgroupid', $host)) {
					$ins_hosts_groups[] = [
						'hostid' => $host['hostid'],
						'groupid' => $group['groupid']
					];
				}
				elseif ($method === 'massUpdate') {
					$db_hostgroupids[$host['hostgroupid']] = true;
				}
			}
		}

		return $ins_hosts_groups;
	}

	/**
	 * Add IDs of inserted hosts on the given host groups.
	 *
	 * @param array $groups
	 * @param array $hostgroupids
	 */
	private static function addHostgroupids(array &$groups, array $hostgroupids): void {
		foreach ($groups as &$group) {
			foreach ($group['hosts'] as &$host) {
				if (!array_key_exists('hostgroupid', $host)) {
					$host['hostgroupid'] = array_shift($hostgroupids);
				}
			}
			unset($host);
		}
		unset($group);
	}

	/**
	 * Get IDs to delete hosts from the given host groups.
	 *
	 * @static
	 *
	 * @param array $db_groups
	 * @param array $db_hostgroupids
	 *
	 * @return array
	 */
	private static function getDelHostgroupids(array $db_groups, array $db_hostgroupids = []): array {
		$del_hostgroupids = [];

		foreach ($db_groups as $db_group) {
			$del_hostgroupids += array_diff_key($db_group['hosts'], $db_hostgroupids);
		}

		$del_hostgroupids = array_keys($del_hostgroupids);

		return $del_hostgroupids;
	}

	protected function addRelatedObjects(array $options, array $result): array {
		$result = parent::addRelatedObjects($options, $result);

		$groupIds = array_keys($result);
		sort($groupIds);

		// adding hosts
		if ($options['selectHosts'] !== null) {
			if ($options['selectHosts'] !== API_OUTPUT_COUNT) {
				$hosts = [];
				$relationMap = $this->createRelationMap($result, 'groupid', 'hostid', 'hosts_groups');
				$related_ids = $relationMap->getRelatedIds();

				if ($related_ids) {
					$hosts = API::Host()->get([
						'output' => $options['selectHosts'],
						'hostids' => $related_ids,
						'preservekeys' => true
					]);
					if ($options['limitSelects'] !== null) {
						order_result($hosts, 'host');
					}
				}

				$result = $relationMap->mapMany($result, $hosts, 'hosts', $options['limitSelects']);
			}
			else {
				$hosts = API::Host()->get([
					'groupids' => $groupIds,
					'countOutput' => true,
					'groupCount' => true
				]);
				$hosts = zbx_toHash($hosts, 'groupid');
				foreach ($result as $groupid => $group) {
					$result[$groupid]['hosts'] = array_key_exists($groupid, $hosts)
						? $hosts[$groupid]['rowscount']
						: '0';
				}
			}
		}

		// adding discovery rule
		if ($options['selectDiscoveryRule'] !== null && $options['selectDiscoveryRule'] != API_OUTPUT_COUNT) {
			// discovered items
			$discoveryRules = DBFetchArray(DBselect(
				'SELECT gd.groupid,hd.parent_itemid'.
					' FROM group_discovery gd,group_prototype gp,host_discovery hd'.
					' WHERE '.dbConditionInt('gd.groupid', $groupIds).
					' AND gd.parent_group_prototypeid=gp.group_prototypeid'.
					' AND gp.hostid=hd.hostid'
			));
			$relationMap = $this->createRelationMap($discoveryRules, 'groupid', 'parent_itemid');

			$discoveryRules = API::DiscoveryRule()->get([
				'output' => $options['selectDiscoveryRule'],
				'itemids' => $relationMap->getRelatedIds(),
				'preservekeys' => true
			]);
			$result = $relationMap->mapOne($result, $discoveryRules, 'discoveryRule');
		}

		// adding host prototype
		if ($options['selectHostPrototype'] !== null) {
			$db_links = DBFetchArray(DBselect(
				'SELECT gd.groupid,gp.hostid'.
					' FROM group_discovery gd,group_prototype gp'.
					' WHERE '.dbConditionInt('gd.groupid', $groupIds).
					' AND gd.parent_group_prototypeid=gp.group_prototypeid'
			));

			$host_prototypes = API::HostPrototype()->get([
				'output' => $options['selectHostPrototype'],
				'hostids' => array_column($db_links, 'hostid'),
				'preservekeys' => true
			]);

			foreach ($result as &$row) {
				$row['hostPrototype'] = [];
			}
			unset($row);

			foreach ($db_links as $row) {
				if (array_key_exists($row['hostid'], $host_prototypes)) {
					$result[$row['groupid']]['hostPrototype'] = $host_prototypes[$row['hostid']];
				}
			}
		}

		// adding group discovery
		if ($options['selectGroupDiscovery'] !== null) {
			$groupDiscoveries = API::getApiService()->select('group_discovery', [
				'output' => $this->outputExtend($options['selectGroupDiscovery'], ['groupid']),
				'filter' => ['groupid' => $groupIds],
				'preservekeys' => true
			]);
			$relationMap = $this->createRelationMap($groupDiscoveries, 'groupid', 'groupid');

			$groupDiscoveries = $this->unsetExtraFields($groupDiscoveries, ['groupid'],
				$options['selectGroupDiscovery']
			);
			$result = $relationMap->mapOne($result, $groupDiscoveries, 'groupDiscovery');
		}

		return $result;
	}

	/**
	 * Apply permissions to all host group's subgroups.
	 *
	 * @param array  $data
	 * @param array  $data['groups']             An array with host group IDs.
	 * @param string $data['groups']['groupid']  Host group ID.
	 * @param bool   $data['permissions']        True if want to apply permissions to all subgroups.
	 * @param bool   $data['tag_filters']        True if want to apply tag filters to all subgroups.
	 *
	 * @return array
	 */
	public function propagate(array $data): array {
		$this->validatePropagate($data, $db_groups);

		foreach ($db_groups as $db_group) {
			if ($data['permissions']) {
				$this->inheritPermissions($db_group['groupid'], $db_group['name']);
			}
			if ($data['tag_filters']) {
				$this->inheritTagFilters($db_group['groupid'], $db_group['name']);
			}
		}

		return ['groupids' => array_column($data['groups'], 'groupid')];
	}

	/**
	 * Validation of propagate function input fields.
	 *
	 * @param array $data       [IN/OUT]
	 * @param array $db_groups  [OUT]
	 *
	 * @throws APIException if the input is invalid.
	 */
	private function validatePropagate(array &$data, array &$db_groups = null): void {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'groups' =>			['type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['groupid']], 'fields' => [
				'groupid' =>		['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'permissions' =>	['type' => API_BOOLEAN, 'default' => false],
			'tag_filters' => 	['type' => API_BOOLEAN, 'default' => false]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $data, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		if ($data['permissions'] == false && $data['tag_filters'] == false) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('At least one parameter "%1$s" or "%2$s" must be enabled.', 'permissions', 'tag_filters')
			);
		}

		$groupids = array_column($data['groups'], 'groupid');

		$db_groups = $this->get([
			'output' => ['groupid', 'name'],
			'groupids' => $groupids,
			'editable' => true
		]);

		if (count($db_groups) != count($groupids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}
	}

	/**
	 * Apply host group rights to all subgroups.
	 *
	 * @param string $groupid  Host group ID.
	 * @param string $name     Host group name.
	 */
	private function inheritPermissions(string $groupid, string $name): void {
		$child_groupids = $this->getChildGroupIds($name);

		if (!$child_groupids) {
			return;
		}

		$usrgrps = API::UserGroup()->get([
			'output' => ['usrgrpid'],
			'selectHostGroupRights' => ['id', 'permission']
		]);

		$upd_usrgrps = [];

		foreach ($usrgrps as $usrgrp) {
			$rights = array_column($usrgrp['hostgroup_rights'], null, 'id');

			if (array_key_exists($groupid, $rights)) {
				foreach ($child_groupids as $child_groupid) {
					$rights[$child_groupid] = [
						'id' => $child_groupid,
						'permission' => $rights[$groupid]['permission']
					];
				}
			}
			else {
				foreach ($child_groupids as $child_groupid) {
					unset($rights[$child_groupid]);
				}
			}

			$rights = array_values($rights);

			if ($usrgrp['hostgroup_rights'] !== $rights) {
				$upd_usrgrps[] = [
					'usrgrpid' => $usrgrp['usrgrpid'],
					'hostgroup_rights' => $rights
				];
			}
		}

		if ($upd_usrgrps) {
			API::UserGroup()->update($upd_usrgrps);
		}
	}

	/**
	 * Add subgroups with tag filters inherited from main host group ($groupid) to all user groups in which tag filters
	 * for particular group are created.
	 *
	 * @param string $groupid  Host group ID.
	 * @param string $name     Host group name.
	 */
	public function inheritTagFilters(string $groupid, string $name): void {
		$child_groupids = $this->getChildGroupIds($name);

		if (!$child_groupids) {
			return;
		}

		$usrgrps = API::UserGroup()->get([
			'output' => ['usrgrpid'],
			'selectTagFilters' => ['groupid', 'tag', 'value']
		]);

		$upd_usrgrps = [];

		foreach ($usrgrps as $usrgrp) {
			$tag_filters = [];

			foreach ($usrgrp['tag_filters'] as $tag_filter) {
				$tag_filters[$tag_filter['groupid']][] = [
					'tag' => $tag_filter['tag'],
					'value' => $tag_filter['value']
				];
			}

			if (array_key_exists($groupid, $tag_filters)) {
				foreach ($child_groupids as $child_groupid) {
					$tag_filters[$child_groupid] = $tag_filters[$groupid];
				}
			}
			else {
				foreach ($child_groupids as $child_groupid) {
					unset($tag_filters[$child_groupid]);
				}
			}

			$upd_tag_filters = [];

			foreach ($tag_filters as $tag_filter_groupid => $tags) {
				foreach ($tags as $tag) {
					$upd_tag_filters[] = ['groupid' => (string) $tag_filter_groupid] + $tag;
				}
			}

			if ($usrgrp['tag_filters'] !== $upd_tag_filters) {
				$upd_usrgrps[] = [
					'usrgrpid' => $usrgrp['usrgrpid'],
					'tag_filters' => $upd_tag_filters
				];
			}
		}

		if ($upd_usrgrps) {
			API::UserGroup()->update($upd_usrgrps);
		}
	}

	/**
	 * Returns list of child groups for host group with given name.
	 *
	 * @param string $name  Host group name.
	 *
	 * @return array
	 */
	private function getChildGroupIds(string $name): array {
		$parent = $name.'/';
		$len = strlen($parent);

		$groups = $this->get([
			'output' => ['groupid', 'name'],
			'search' => ['name' => $parent],
			'startSearch' => true
		]);

		$child_groupids = [];
		foreach ($groups as $group) {
			if (substr($group['name'], 0, $len) === $parent) {
				$child_groupids[] = $group['groupid'];
			}
		}

		return $child_groupids;
	}
}
