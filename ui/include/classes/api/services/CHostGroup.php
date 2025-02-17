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

	public const OUTPUT_FIELDS = ['groupid', 'name', 'flags', 'uuid'];

	private const GROUP_DISCOVERY_FIELDS = ['parent_group_prototypeid', 'name', 'lastcheck', 'ts_delete', 'status'];

	/**
	 * Get host groups.
	 *
	 * @param array $options
	 *
	 * @return array|int
	 */
	public function get(array $options) {
		$result = [];

		$host_fields = ['hostid', 'host', 'name', 'description', 'proxyid', 'status', 'ipmi_authtype',
			'ipmi_privilege', 'ipmi_password', 'ipmi_username', 'inventory_mode', 'tls_connect', 'tls_accept',
			'tls_psk_identity', 'tls_psk', 'tls_issuer', 'tls_subject', 'maintenanceid', 'maintenance_type',
			'maintenance_from', 'maintenance_status', 'flags'
		];
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
			'with_monitored_hosts' =>				['type' => API_BOOLEAN, 'default' => false],
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
			'output' =>								['type' => API_OUTPUT, 'in' => implode(',', self::OUTPUT_FIELDS), 'default' => API_OUTPUT_EXTEND],
			'selectHosts' =>						['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL | API_ALLOW_COUNT, 'in' => implode(',', $host_fields), 'default' => null],
			'selectGroupDiscoveries' =>				['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', self::GROUP_DISCOVERY_FIELDS), 'default' => null],
			'selectDiscoveryRules' =>				['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', $discovery_rule_fields), 'default' => null],
			'selectHostPrototypes' =>				['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', $host_prototype_fields), 'default' => null],
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
			$options['output'] = self::OUTPUT_FIELDS;
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
	 * @param array $groups
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

		$this->deleteForce($db_groups);

		return ['groupids' => $groupids];
	}

	/**
	 * @param array $db_groups
	 */
	public function deleteForce(array $db_groups): void {
		self::validateDeleteForce($db_groups);

		$groupids = array_keys($db_groups);

		// delete sysmap element
		DB::delete('sysmaps_elements', ['elementtype' => SYSMAP_ELEMENT_TYPE_HOST_GROUP, 'elementid' => $groupids]);

		$this->unlinkHosts($db_groups);

		DB::delete('hstgrp', ['groupid' => $groupids]);

		self::addAuditLog(CAudit::ACTION_DELETE, CAudit::RESOURCE_HOST_GROUP, $db_groups);
	}

	private function unlinkHosts(array $db_groups): void {
		$data = [
			'groups' => [],
			'hosts' => []
		];

		foreach ($db_groups as $db_group) {
			$data['groups'][] = ['groupid' => $db_group['groupid']];
		}

		$this->massUpdate($data);
	}

	/**
	 * @param array $groups
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

		self::addUuid($groups);

		self::checkUuidDuplicates($groups);
		self::checkDuplicates($groups);
	}

	/**
	 * Validates input data for update method.
	 *
	 * @param array $groups
	 * @param array $db_groups
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateUpdate(array &$groups, ?array &$db_groups = null): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['uuid'], ['groupid'], ['name']], 'fields' => [
			'uuid' => 		['type' => API_UUID],
			'groupid' =>	['type' => API_ID, 'flags' => API_REQUIRED],
			'name' =>		['type' => API_HG_NAME, 'length' => DB::getFieldLength('hstgrp', 'name')]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $groups, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_groups = $this->get([
			'output' => ['uuid', 'groupid', 'name', 'flags'],
			'groupids' => array_column($groups, 'groupid'),
			'editable' => true,
			'preservekeys' => true
		]);

		if (count($db_groups) != count($groups)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		self::checkGroupsNotDiscovered($db_groups);
		self::checkUuidDuplicates($groups, $db_groups);
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
	private function validateDelete(array $groupids, ?array &$db_groups = null): void {
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
	}

	/**
	 * @param array $db_groups
	 *
	 * @throws APIException if unable to delete groups.
	 */
	private static function validateDeleteForce(array $db_groups): void {
		$groupids = array_keys($db_groups);

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

		self::checkUsedInActions($db_groups);
		self::checkMaintenances($groupids);
	}

	/**
	 * Check for unique host group names.
	 *
	 * @param array      $groups
	 * @param array|null $db_groups
	 *
	 * @throws APIException if host group names are not unique.
	 */
	private static function checkDuplicates(array $groups, ?array $db_groups = null): void {
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
	 * Add the UUID to those of the given host groups that don't have the 'uuid' parameter set.
	 *
	 * @param array $groups
	 */
	private static function addUuid(array &$groups): void {
		foreach ($groups as &$group) {
			if (!array_key_exists('uuid', $group)) {
				$group['uuid'] = generateUuidV4();
			}
		}
		unset($group);
	}

	/**
	 * Verify host group UUIDs are not repeated.
	 *
	 * @param array      $groups
	 * @param array|null $db_groups
	 *
	 * @throws APIException
	 */
	private static function checkUuidDuplicates(array $groups, ?array $db_groups = null): void {
		$group_indexes = [];

		foreach ($groups as $i => $group) {
			if (!array_key_exists('uuid', $group)) {
				continue;
			}

			if ($db_groups === null || $group['uuid'] !== $db_groups[$group['groupid']]['uuid']) {
				$group_indexes[$group['uuid']] = $i;
			}
		}

		if (!$group_indexes) {
			return;
		}

		$duplicates = DB::select('hstgrp', [
			'output' => ['uuid'],
			'filter' => [
				'type' => HOST_GROUP_TYPE_HOST_GROUP,
				'uuid' => array_keys($group_indexes)
			],
			'limit' => 1
		]);

		if ($duplicates) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Invalid parameter "%1$s": %2$s.', '/'.($group_indexes[$duplicates[0]['uuid']] + 1),
					_('host group with the same UUID already exists')
				)
			);
		}
	}

	/**
	 * Check whether no one of passed groups are discovered host.
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

	private static function checkUsedInActions(array $db_groups): void {
		$groupids = array_keys($db_groups);

		$row = DBfetch(DBselect(
			'SELECT c.value AS groupid,a.name'.
			' FROM conditions c'.
			' JOIN actions a ON c.actionid=a.actionid'.
			' WHERE c.conditiontype='.ZBX_CONDITION_TYPE_HOST_GROUP.
				' AND '.dbConditionString('c.value', $groupids),
			1
		));

		if (!$row) {
			$row = DBfetch(DBselect(
				'SELECT og.groupid,a.name'.
				' FROM opgroup og'.
				' JOIN operations o ON og.operationid=o.operationid'.
				' JOIN actions a ON o.actionid=a.actionid'.
				' WHERE '.dbConditionId('og.groupid', $groupids),
				1
			));
		}

		if (!$row) {
			$row = DBfetch(DBselect(
				'SELECT ocg.groupid,a.name'.
				' FROM opcommand_grp ocg'.
				' JOIN operations o ON ocg.operationid=o.operationid'.
				' JOIN actions a ON o.actionid=a.actionid'.
				' WHERE '.dbConditionId('ocg.groupid', $groupids),
				1
			));
		}

		if ($row) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Cannot delete host group "%1$s": %2$s.',
				$db_groups[$row['groupid']]['name'], _s('action "%1$s" uses this host group', $row['name'])
			));
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
			'SELECT mg.maintenanceid,m.name'.
			' FROM maintenances_groups mg'.
			' JOIN maintenances m ON mg.maintenanceid=m.maintenanceid'.
			' WHERE '.dbConditionId('mg.groupid', $groupids).
				' AND NOT EXISTS ('.
					'SELECT NULL'.
					' FROM maintenances_groups mg1'.
					' WHERE mg.maintenanceid=mg1.maintenanceid'.
						' AND '.dbConditionId('mg1.groupid', $groupids, true).
				')'.
				' AND NOT EXISTS ('.
					'SELECT NULL'.
					' FROM maintenances_hosts mh'.
					' WHERE mg.maintenanceid=mh.maintenanceid'.
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
		$this->validateMassAdd($data);

		API::Host()->massAdd($data);

		return ['groupids' => array_column($data['groups'], 'groupid')];
	}

	private function validateMassAdd(array &$data): void {
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
	}

	/**
	 * Replace hosts on the given host groups.
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function massUpdate(array $data): array {
		$this->validateMassUpdate($data, $hosts, $db_hosts);

		API::Host()->updateForce($hosts, $db_hosts);

		return ['groupids' => array_column($data['groups'], 'groupid')];
	}

	private function validateMassUpdate(array &$data, ?array &$hosts, ?array &$db_hosts): void {
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

		$db_groups = $this->get([
			'output' => [],
			'groupids' => array_column($data['groups'], 'groupid'),
			'editable' => true,
			'preservekeys' => true
		]);

		foreach ($data['groups'] as $i => $group) {
			if (!array_key_exists($group['groupid'], $db_groups)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _s('Invalid parameter "%1$s": %2$s.',
					'/groups/'.($i + 1), _('object does not exist, or you have no permissions to it')
				));
			}
		}

		$db_hosts = API::Host()->get([
			'output' => ['hostid', 'host', 'flags'],
			'groupids' => array_column($data['groups'], 'groupid'),
			'editable' => true,
			'preservekeys' => true
		]);

		if ($data['hosts']) {
			$db_hosts += API::Host()->get([
				'output' => ['hostid', 'host', 'flags'],
				'hostids' => array_column($data['hosts'], 'hostid'),
				'editable' => true,
				'preservekeys' => true
			]);
		}

		foreach ($data['hosts'] as $i => $host) {
			if (!array_key_exists($host['hostid'], $db_hosts)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _s('Invalid parameter "%1$s": %2$s.',
					'/hosts/'.($i + 1), _('object does not exist, or you have no permissions to it')
				));
			}
			elseif ($db_hosts[$host['hostid']]['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
					'/hosts/'.($i + 1),
					_s('cannot update readonly parameter "%1$s" of discovered object', 'groups')
				));
			}
		}

		$hosts = [];
		$del_hosts = [];

		if (!$db_hosts) {
			return;
		}

		$hostids = array_column($data['hosts'], 'hostid');

		foreach ($db_hosts as $db_host) {
			if (in_array($db_host['hostid'], $hostids)) {
				$hosts[$db_host['hostid']] = [
					'hostid' => $db_host['hostid'],
					'groups' => $data['groups']
				];
			}
			else {
				$del_hosts[$db_host['hostid']] = [
					'hostid' => $db_host['hostid'],
					'groups' => []
				];
			}
		}

		API::Host()->addAffectedGroups($hosts + $del_hosts, $db_hosts);

		if ($hosts) {
			API::Host()->addUnchangedGroups($hosts, $db_hosts);
		}

		if ($del_hosts) {
			API::Host()->addUnchangedGroups($del_hosts, $db_hosts,
				['groupids' => array_column($data['groups'], 'groupid')]
			);
		}

		$hosts += $del_hosts;

		API::Host()->checkHostsWithoutGroups($hosts, $db_hosts);
	}

	/**
	 * Remove given hosts from given host groups.
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function massRemove(array $data): array {
		$this->validateMassRemove($data);

		API::Host()->massRemove($data);

		return ['groupids' => $data['groupids']];
	}

	private function validateMassRemove(array &$data): void {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'groupids' =>	['type' => API_IDS, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_NORMALIZE, 'uniq' => true],
			'hostids' =>	['type' => API_IDS, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_NORMALIZE, 'uniq' => true]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $data, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}
	}

	protected function addRelatedObjects(array $options, array $result): array {
		$result = parent::addRelatedObjects($options, $result);

		$groupids = array_keys($result);
		sort($groupids);

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
					'groupids' => $groupids,
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
		if ($options['selectDiscoveryRules'] !== null) {
			// discovered items
			$discovery_rules = DBFetchArray(DBselect(
				'SELECT gd.groupid,hd.parent_itemid'.
					' FROM group_discovery gd,group_prototype gp,host_discovery hd'.
					' WHERE '.dbConditionInt('gd.groupid', $groupids).
					' AND gd.parent_group_prototypeid=gp.group_prototypeid'.
					' AND gp.hostid=hd.hostid'
			));
			$relation_map = $this->createRelationMap($discovery_rules, 'groupid', 'parent_itemid');

			$discovery_rules = API::DiscoveryRule()->get([
				'output' => $options['selectDiscoveryRules'],
				'itemids' => $relation_map->getRelatedIds(),
				'preservekeys' => true
			]);

			$result = $relation_map->mapMany($result, $discovery_rules, 'discoveryRules');
		}

		// adding host prototype
		if ($options['selectHostPrototypes'] !== null) {
			$db_links = DBFetchArray(DBselect(
				'SELECT gd.groupid,gp.hostid'.
					' FROM group_discovery gd,group_prototype gp'.
					' WHERE '.dbConditionInt('gd.groupid', $groupids).
					' AND gd.parent_group_prototypeid=gp.group_prototypeid'
			));

			$host_prototypes = API::HostPrototype()->get([
				'output' => $options['selectHostPrototypes'],
				'hostids' => array_column($db_links, 'hostid'),
				'preservekeys' => true
			]);

			foreach ($result as &$row) {
				$row['hostPrototypes'] = [];
			}
			unset($row);

			foreach ($db_links as $row) {
				if (array_key_exists($row['hostid'], $host_prototypes)) {
					$result[$row['groupid']]['hostPrototypes'][] = $host_prototypes[$row['hostid']];
				}
			}
		}

		// adding group discovery
		if ($options['selectGroupDiscoveries'] !== null) {
			$output = $options['selectGroupDiscoveries'] === API_OUTPUT_EXTEND
				? self::GROUP_DISCOVERY_FIELDS
				: $options['selectGroupDiscoveries'];

			$group_discoveries = API::getApiService()->select('group_discovery', [
				'output' => $this->outputExtend($output, ['groupid', 'groupdiscoveryid']),
				'filter' => ['groupid' => $groupids],
				'preservekeys' => true
			]);
			$relation_map = $this->createRelationMap($group_discoveries, 'groupid', 'groupdiscoveryid');

			$group_discoveries = $this->unsetExtraFields($group_discoveries, ['groupid', 'groupdiscoveryid'], $output);

			$result = $relation_map->mapMany($result, $group_discoveries, 'groupDiscoveries');
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
	private function validatePropagate(array &$data, ?array &$db_groups = null): void {
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
