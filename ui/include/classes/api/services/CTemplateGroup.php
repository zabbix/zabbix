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
 * Class containing methods for operations with template groups.
 */
class CTemplateGroup extends CApiService {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_ZABBIX_USER],
		'create' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'update' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN],
		'delete' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN],
		'massadd' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN],
		'massupdate' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN],
		'massremove' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN]
	];

	protected $tableName = 'tplgrp';
	protected $tableAlias = 'g';
	protected $sortColumns = ['groupid', 'name'];

	/**
	 * Get template groups.
	 *
	 * @param array $params
	 *
	 * @return array
	 */
	public function get(array $params): array {
		$result = [];

		$sqlParts = [
			'select'	=> ['tplgrp' => 'g.groupid'],
			'from'		=> ['tplgrp' => 'tplgrp g'],
			'where'		=> [],
			'order'		=> [],
			'limit'		=> null
		];

		$defOptions = [
			'groupids'								=> null,
			'templateids'							=> null,
			'graphids'								=> null,
			'triggerids'							=> null,
			'templated_hosts'						=> null,
			'with_items'							=> null,
			'with_item_prototypes'					=> null,
			'with_simple_graph_items'				=> null,
			'with_simple_graph_item_prototypes'		=> null,
			'with_monitored_items'					=> null,
			'with_triggers'							=> null,
			'with_monitored_triggers'				=> null,
			'with_httptests'						=> null,
			'with_monitored_httptests'				=> null,
			'with_graphs'							=> null,
			'with_graph_prototypes'					=> null,
			'editable'								=> false,
			'nopermissions'							=> null,
			// filter
			'filter'								=> null,
			'search'								=> null,
			'searchByAny'							=> null,
			'startSearch'							=> false,
			'excludeSearch'							=> false,
			'searchWildcardsEnabled'				=> null,
			// output
			'output'								=> API_OUTPUT_EXTEND,
			'selectTemplates'						=> null,
			'selectGroupDiscovery'					=> null,
			'selectDiscoveryRule'					=> null,
			'countOutput'							=> false,
			'groupCount'							=> false,
			'preservekeys'							=> false,
			'sortfield'								=> '',
			'sortorder'								=> '',
			'limit'									=> null,
			'limitSelects'							=> null
		];
		$options = zbx_array_merge($defOptions, $params);

		// editable + PERMISSION CHECK
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN && !$options['nopermisions']) {
			$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ;
			$userGroups = getUserGroupsByUserId(self::$userData['userid']);

			$sqlParts['where'][] = 'EXISTS ('.
				'SELECT NULL' .
				' FROM rights r' .
				' WHERE g.groupid=r.id' .
				' AND ' . dbConditionInt('r.groupid', $userGroups) .
				' GROUP BY r.id' .
				' HAVING MIN(r.permission)>' . PERM_DENY .
				' AND MAX(r.permission)>=' . zbx_dbstr($permission) .
				')';
		}

		// groupids
		if (!is_null($options['groupids'])) {
			zbx_value2array($options['groupids']);
			$sqlParts['where']['groupid'] = dbConditionInt('g.groupid', $options['groupids']);
		}

		// templateids
		if (!is_null($options['templateids'])) {
			zbx_value2array($options['templateids']);

			$sqlParts['from']['templates_groups'] = 'templates_groups tg';
			$sqlParts['where'][] = dbConditionInt('tg.templateid', $options['templateids']);
			$sqlParts['where']['tgg'] = 'tg.groupid=g.groupid';
		}

		// triggerids
		if (!is_null($options['triggerids'])) {
			zbx_value2array($options['triggerids']);

			$sqlParts['from']['templates_groups'] = 'templates_groups tg';
			$sqlParts['from']['functions'] = 'functions f';
			$sqlParts['from']['items'] = 'items i';
			$sqlParts['where'][] = dbConditionInt('f.triggerid', $options['triggerids']);
			$sqlParts['where']['fi'] = 'f.itemid=i.itemid';
			$sqlParts['where']['tgi'] = 'tg.templateid=i.hostid';
			$sqlParts['where']['tgg'] = 'tg.groupid=g.groupid';
		}

		// graphids
		if (!is_null($options['graphids'])) {
			zbx_value2array($options['graphids']);

			$sqlParts['from']['gi'] = 'graphs_items gi';
			$sqlParts['from']['i'] = 'items i';
			$sqlParts['from']['tg'] = 'templates_groups tg';
			$sqlParts['where'][] = dbConditionInt('gi.graphid', $options['graphids']);
			$sqlParts['where']['tgg'] = 'tg.groupid=g.groupid';
			$sqlParts['where']['igi'] = 'i.itemid=gi.itemid';
			$sqlParts['where']['tgi'] = 'tg.templateid=i.hostid';
		}

		$sub_sql_common = [];

		// templated_hosts
		if ($options['templated_hosts'] !== null) {
			$sub_sql_common['from']['h'] = 'hosts h';
			$sub_sql_common['where']['tg-h'] = 'tg.templateid=h.hostid';
			$sub_sql_common['where'][] = dbConditionInt('h.status', [HOST_STATUS_TEMPLATE]);
		}

		$sub_sql_parts = $sub_sql_common;

		// with_items, with_monitored_items, with_simple_graph_items
		if ($options['with_items'] !== null) {
			$sub_sql_parts['from']['i'] = 'items i';
			$sub_sql_parts['where']['tg-i'] = 'tg.templateid=i.hostid';
			$sub_sql_parts['where'][] = dbConditionInt('i.flags',
				[ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED]
			);
		}
		elseif ($options['with_monitored_items'] !== null) {
			$sub_sql_parts['from']['i'] = 'items i';
			$sub_sql_parts['from']['h'] = 'hosts h';
			$sub_sql_parts['where']['tg-i'] = 'tg.templateid=i.hostid';
			$sub_sql_parts['where']['tg-h'] = 'tg.templateid=h.hostid';
			$sub_sql_parts['where'][] = dbConditionInt('h.status', [HOST_STATUS_MONITORED]);
			$sub_sql_parts['where'][] = dbConditionInt('i.status', [ITEM_STATUS_ACTIVE]);
			$sub_sql_parts['where'][] = dbConditionInt('i.flags',
				[ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED]
			);
		}
		elseif ($options['with_simple_graph_items'] !== null) {
			$sub_sql_parts['from']['i'] = 'items i';
			$sub_sql_parts['where']['tg-i'] = 'tg.templateid=i.hostid';
			$sub_sql_parts['where'][] = dbConditionInt('i.value_type', [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64]);
			$sub_sql_parts['where'][] = dbConditionInt('i.status', [ITEM_STATUS_ACTIVE]);
			$sub_sql_parts['where'][] = dbConditionInt('i.flags',
				[ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED]
			);
		}

		// with_triggers, with_monitored_triggers
		if ($options['with_triggers'] !== null) {
			$sub_sql_parts['from']['i'] = 'items i';
			$sub_sql_parts['from']['f'] = 'functions f';
			$sub_sql_parts['from']['t'] = 'triggers t';
			$sub_sql_parts['where']['tg-i'] = 'tg.templateid=i.hostid';
			$sub_sql_parts['where']['i-f'] = 'i.itemid=f.itemid';
			$sub_sql_parts['where']['f-t'] = 'f.triggerid=t.triggerid';
			$sub_sql_parts['where'][] = dbConditionInt('t.flags',
				[ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED]
			);
		}
		elseif ($options['with_monitored_triggers'] !== null) {
			$sub_sql_parts['from']['i'] = 'items i';
			$sub_sql_parts['from']['h'] = 'hosts h';
			$sub_sql_parts['from']['f'] = 'functions f';
			$sub_sql_parts['from']['t'] = 'triggers t';
			$sub_sql_parts['where']['tg-i'] = 'tg.templateid=i.hostid';
			$sub_sql_parts['where']['tg-h'] = 'tg.templateid=h.hostid';
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
		if ($options['with_httptests'] !== null) {
			$sub_sql_parts['from']['ht'] = 'httptest ht';
			$sub_sql_parts['where']['tg-ht'] = 'tg.templateid=ht.hostid';
		}
		elseif ($options['with_monitored_httptests'] !== null) {
			$sub_sql_parts['from']['ht'] = 'httptest ht';
			$sub_sql_parts['where']['tg-ht'] = 'tg.templateid=ht.hostid';
			$sub_sql_parts['where'][] = dbConditionInt('ht.status', [HTTPTEST_STATUS_ACTIVE]);
		}

		// with_graphs
		if ($options['with_graphs'] !== null) {
			$sub_sql_parts['from']['i'] = 'items i';
			$sub_sql_parts['from']['gi'] = 'graphs_items gi';
			$sub_sql_parts['from']['gr'] = 'graphs gr';
			$sub_sql_parts['where']['tg-i'] = 'tg.templateid=i.hostid';
			$sub_sql_parts['where']['i-gi'] = 'i.itemid=gi.itemid';
			$sub_sql_parts['where']['gi-gr'] = 'gi.graphid=gr.graphid';
			$sub_sql_parts['where'][] = dbConditionInt('gr.flags',
				[ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED]
			);
		}

		if ($sub_sql_parts) {
			$sub_sql_parts['from']['tg'] = 'templates_groups tg';
			$sub_sql_parts['where']['g-tg'] = 'g.groupid=tg.groupid';

			$sqlParts['where'][] = 'EXISTS ('.
				'SELECT NULL'.
				' FROM '.implode(',', $sub_sql_parts['from']).
				' WHERE '.implode(' AND ', array_unique($sub_sql_parts['where'])).
				')';
		}

		$sub_sql_parts = $sub_sql_common;

		// with_item_prototypes, with_simple_graph_item_prototypes
		if ($options['with_item_prototypes'] !== null) {
			$sub_sql_parts['from']['i'] = 'items i';
			$sub_sql_parts['where']['tg-i'] = 'tg.templateid=i.hostid';
			$sub_sql_parts['where'][] = dbConditionInt('i.flags', [ZBX_FLAG_DISCOVERY_PROTOTYPE]);
		}
		elseif ($options['with_simple_graph_item_prototypes'] !== null) {
			$sub_sql_parts['from']['i'] = 'items i';
			$sub_sql_parts['where']['tg-i'] = 'tg.templateid=i.hostid';
			$sub_sql_parts['where'][] = dbConditionInt('i.value_type', [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64]);
			$sub_sql_parts['where'][] = dbConditionInt('i.status', [ITEM_STATUS_ACTIVE]);
			$sub_sql_parts['where'][] = dbConditionInt('i.flags', [ZBX_FLAG_DISCOVERY_PROTOTYPE]);
		}

		// with_graph_prototypes
		if ($options['with_graph_prototypes'] !== null) {
			$sub_sql_parts['from']['i'] = 'items i';
			$sub_sql_parts['from']['gi'] = 'graphs_items gi';
			$sub_sql_parts['from']['gr'] = 'graphs gr';
			$sub_sql_parts['where']['tg-i'] = 'tg.templateid=i.hostid';
			$sub_sql_parts['where']['i-gi'] = 'i.itemid=gi.itemid';
			$sub_sql_parts['where']['gi-gr'] = 'gi.graphid=gr.graphid';
			$sub_sql_parts['where'][] = dbConditionInt('gr.flags', [ZBX_FLAG_DISCOVERY_PROTOTYPE]);
		}

		if ($sub_sql_parts) {
			$sub_sql_parts['from']['tg'] = 'templates_groups tg';
			$sub_sql_parts['where']['g-tg'] = 'g.groupid=tg.groupid';

			$sqlParts['where'][] = 'EXISTS ('.
				'SELECT NULL'.
				' FROM '.implode(',', $sub_sql_parts['from']).
				' WHERE '.implode(' AND ', array_unique($sub_sql_parts['where'])).
				')';
		}

		// filter
		if (is_array($options['filter'])) {
			$this->dbFilter('tplgrp g', $options, $sqlParts);
		}

		// search
		if (is_array($options['search'])) {
			zbx_db_search('tplgrp g', $options, $sqlParts);
		}

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$res = DBselect(self::createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
		while ($group = DBfetch($res)) {
			if ($options['countOutput']) {
				if ($options['groupCount']) {
					$result[] = $group;
				}
				else {
					$result = $group['rowscount'];
				}
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
		}

		// removing keys (hash -> array)
		if (!$options['preservekeys']) {
			$result = zbx_cleanHashes($result);
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
				_s('No permissions to call "%1$s.%2$s".', 'templategroup', __FUNCTION__)
			);
		}

		self::validateCreate($groups);

		$groupids = DB::insert('tplgrp', $groups);

		foreach ($groups as $index => &$group) {
			$group['groupid'] = $groupids[$index];
		}
		unset($group);

		self::inheritUserGroupsData($groups);

		self::addAuditLog(CAudit::ACTION_ADD, CAudit::RESOURCE_TEMPLATE_GROUP, $groups);

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
			$upd_group = DB::getUpdatedValues('tplgrp', $group, $db_groups[$group['groupid']]);

			if ($upd_group) {
				$upd_groups[] = [
					'values' => $upd_group,
					'where' => ['groupid' => $group['groupid']]
				];
			}
		}

		if ($upd_groups) {
			DB::update('tplgrp', $upd_groups);
		}

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_TEMPLATE_GROUP, $groups, $db_groups);

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

		DB::delete('tplgrp', ['groupid' => $groupids]);

		self::addAuditLog(CAudit::ACTION_DELETE, CAudit::RESOURCE_TEMPLATE_GROUP, $db_groups);
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
			'name' =>	['type' => API_TG_NAME, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('tplgrp', 'name')]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $groups, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		self::checkDuplicates($groups);
		self::checkAndAddUuid($groups);
	}

	/**
	 * @param array $groups
	 * @param array $db_groups
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateUpdate(array &$groups, array &$db_groups = null): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['groupid'], ['name']], 'fields' => [
			'groupid' =>	['type' => API_ID, 'flags' => API_REQUIRED],
			'name' =>		['type' => API_TG_NAME, 'length' => DB::getFieldLength('tplgrp', 'name')]
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
			'output' => ['groupid', 'name', 'internal'],
			'groupids' => $groupids,
			'editable' => true,
			'preservekeys' => true
		]);

		if (count($db_groups) != count($groupids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
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

		$db_templates = API::Template()->get([
			'output' => ['template'],
			'groupids' => $groupids,
			'nopermissions' => true,
			'preservekeys' => true
		]);

		if ($db_templates) {
			self::checkObjectsWithoutGroups($db_templates, $groupids);
		}

		$db_scripts = DB::select('scripts', [
			'output' => ['groupid'],
			'filter' => ['groupid' => $groupids],
			'limit' => 1
		]);

		if ($db_scripts) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Template group "%1$s" cannot be deleted, because it is used in a global script.',
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
	}

	/**
	 * Check for unique template group names.
	 *
	 * @static
	 *
	 * @param array      $groups
	 * @param array|null $db_groups
	 *
	 * @throws APIException if template group names are not unique.
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

		$duplicates = DB::select('tplgrp', [
			'output' => ['name'],
			'filter' => ['name' => $names],
			'limit' => 1
		]);

		if ($duplicates) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Template group "%1$s" already exists.', $duplicates[0]['name']));
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

		$db_uuid = DB::select('tplgrp', [
			'output' => ['uuid'],
			'filter' => ['uuid' => array_column($groups_to_create, 'uuid')],
			'limit' => 1
		]);

		if ($db_uuid) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Entry with UUID "%1$s" already exists.', $db_uuid[0]['uuid'])
			);
		}
	}

	/**
	 * Inherit user groups data of parent template groups.
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
			'filter' => ['name' => array_keys($parent_names)]
		];
		$result = DBselect(DB::makeSql('tplgrp', $options));

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
	 * Prepare rights to inherit from parent template groups.
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

			if (!array_key_exists('rights', $db_usrgrps[$db_right['groupid']])) {
				$db_usrgrps[$db_right['groupid']]['rights'] = [];
			}

			foreach ($group_links[$db_right['id']] as $tplgrpid) {
				$usrgrps[$db_right['groupid']]['rights'][] = [
					'permission' => $db_right['permission'],
					'id' => $tplgrpid
				];
			}
		}
	}

	/**
	 * Add given templates to given template groups.
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function massAdd(array $data): array {
		$this->validateMassAdd($data, $db_groups);

		$groups = self::getGroupsByData($data, $db_groups);
		$ins_templates_groups = self::getInsTemplatesGroups($groups, __FUNCTION__);

		if ($ins_templates_groups) {
			$ids = DB::insertBatch('templates_groups', $ins_templates_groups);
			self::addTemplategroupids($groups, $ids);
		}
		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_TEMPLATE_GROUP, $groups, $db_groups);

		return ['groupids' => array_column($data['groups'], 'groupid')];
	}

	/**
	 * Replace templates on the given template groups.
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function massUpdate(array $data) {
		$this->validateMassUpdate($data, $db_groups);

		$groups = self::getGroupsByData($data, $db_groups);
		$ins_template_groups = self::getInsTemplatesGroups($groups, __FUNCTION__, $db_templategroupids);
		$del_templategroupids = self::getDelTemplategroupids($db_groups, $db_templategroupids);

		if ($ins_template_groups) {
			$ids = DB::insertBatch('templates_groups', $ins_template_groups);
			self::addTemplategroupids($groups, $ids);
		}

		if ($del_templategroupids) {
			DB::delete('templates_groups', ['templategroupid' => $del_templategroupids]);
		}

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_TEMPLATE_GROUP, $groups, $db_groups);

		return ['groupids' => array_column($data['groups'], 'groupid')];
	}

	/**
	 * Remove given templates from given template groups.
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function massRemove(array $data): array {
		$this->validateMassRemove($data, $db_groups);

		$groups = self::getGroupsByData([], $db_groups);
		$del_templategroupids = self::getDelTemplategroupids($db_groups);

		if ($del_templategroupids) {
			DB::delete('templates_groups', ['templategroupid' => $del_templategroupids]);
		}

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_TEMPLATE_GROUP, $groups, $db_groups);

		return ['groupids' => $data['groupids']];
	}

	/**
	 * @param array      $data
	 * @param array|null $db_groups
	 *
	 * @throws APIException if the input is invalid.
	 */
	private function validateMassAdd(array &$data, ?array &$db_groups): void {
		$api_input_rules = ['type' => API_OBJECT, 'flags' => API_NOT_EMPTY, 'fields' => [
			'groups' =>		['type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['groupid']], 'fields' => [
				'groupid' =>	['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'templates' =>	['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['templateid']], 'fields' => [
				'templateid'=>	['type' => API_ID, 'flags' => API_REQUIRED]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $data, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		if (!array_key_exists('templates', $data)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('At least one template must be specified.'));
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

		$templateids = array_column($data['templates'], 'templateid');

		$count = API::Template()->get([
			'countOutput' => true,
			'templateids' => $templateids,
			'editable' => true
		]);

		if ($count != count($templateids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS,
				_('No permissions to referred object or it does not exist!')
			);
		}

		self::addAffectedObjects('templates', $templateids, $db_groups);
	}

	/**
	 * @param array      $data
	 * @param array|null $db_groups
	 *
	 * @throws APIException if the input is invalid.
	 */
	private function validateMassUpdate(array &$data, ?array &$db_groups): void {
		$api_input_rules = ['type' => API_OBJECT, 'flags' => API_NOT_EMPTY, 'fields' => [
			'groups' =>		['type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['groupid']], 'fields' => [
				'groupid' =>	['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'templates' =>	['type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NORMALIZE, 'uniq' => [['templateid']], 'fields' => [
				'templateid'=>	['type' => API_ID, 'flags' => API_REQUIRED]
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

		$templateids = array_column($data['templates'], 'templateid');

		$count = API::Template()->get([
			'countOutput' => true,
			'templateids' => $templateids,
			'editable' => true
		]);

		if ($count != count($templateids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS,
				_('No permissions to referred object or it does not exist!')
			);
		}

		self::addAffectedObjects('templates', [], $db_groups, $db_templateids);

		$del_templateids = array_diff($db_templateids, $templateids);

		if ($del_templateids) {
			self::checkDeletedObjects($del_templateids, $groupids);
		}
	}

	/**
	 * @param array      $data
	 * @param array|null $db_groups
	 *
	 * @throws APIException if the input is invalid.
	 */
	private function validateMassRemove(array &$data, ?array &$db_groups): void {
		$api_input_rules = ['type' => API_OBJECT, 'flags' => API_NOT_EMPTY, 'fields' => [
			'groupids' =>		['type' => API_IDS, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_NORMALIZE, 'uniq' => true],
			'templateids' =>	['type' => API_IDS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => true]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $data, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		if (!array_key_exists('templateids', $data)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('At least one template must be specified.'));
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

		$db_templates = API::Template()->get([
			'output' => ['host'],
			'templateids' => $data['templateids'],
			'editable' => true,
			'preservekeys' => true
		]);

		if (count($db_templates) != count($data['templateids'])) {
			self::exception(ZBX_API_ERROR_PERMISSIONS,
				_('No permissions to referred object or it does not exist!')
			);
		}

		self::checkObjectsWithoutGroups($db_templates, $data['groupids']);

		self::addAffectedObjects('templates', $data['templateids'], $db_groups);
	}

	/**
	 * Check to exclude an opportunity to leave template without groups.
	 *
	 * @static
	 *
	 * @param array  $db_objects
	 * @param string $db_objects[<objectid>]['host']
	 * @param array  $groupids
	 *
	 * @throws APIException
	 */
	public static function checkObjectsWithoutGroups(array $db_objects, array $groupids): void {
		$templateids = array_keys($db_objects);

		$objectids_with_groups = DBfetchColumn(DBselect(
			'SELECT DISTINCT tg.templateid'.
			' FROM templates_groups tg'.
			' WHERE '.dbConditionInt('tg.groupid', $groupids, true).
			' AND '.dbConditionInt('tg.templateid', $templateids)
		), 'templateid');

		$objectids_without_groups = array_diff($templateids, $objectids_with_groups);

		if ($objectids_without_groups) {
			$objectid = reset($objectids_without_groups);
			$error = _s('Template "%1$s" cannot be without template group.', $db_objects[$objectid]['host']);

			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}
	}

	/**
	 * Add the existing templates whether these are affected by the mass methods.
	 * If object IDs passed as empty array, all object links of given groups will be collected from database and all
	 * existing object IDs will be collected in $db_objectids.
	 *
	 * @static
	 *
	 * @param string     $objects
	 * @param array      $objectids
	 * @param array      $db_groups
	 * @param array|null $db_objectids
	 */
	private static function addAffectedObjects(string $objects, array $objectids, array &$db_groups,
												array &$db_objectids = null): void {
		$id_field_name = 'templateid';

		if (!$objectids) {
			$db_objectids = [];
		}

		foreach ($db_groups as &$db_group) {
			$db_group[$objects] = [];
		}
		unset($db_group);

		if ($objectids) {
			$options = [
				'output' => ['templategroupid', 'templateid', 'groupid'],
				'filter' => [
					'templateid' => $objectids,
					'groupid' => array_keys($db_groups)
				]
			];
			$db_template_groups = DBselect(DB::makeSql('templates_groups', $options));
		}
		else {
			$db_template_groups = DBselect(
				'SELECT tg.templategroupid,tg.templateid,tg.groupid'.
				' FROM templates_groups tg,hosts h'.
				' WHERE tg.templateid=h.hostid'.
				' AND '.dbConditionInt('tg.groupid', array_keys($db_groups)).
				' AND '.dbConditionInt('h.status', [HOST_STATUS_TEMPLATE], $objects === 'hosts').
				' AND h.flags='.ZBX_FLAG_DISCOVERY_NORMAL
			);
		}

		while ($link = DBfetch($db_template_groups)) {
			$db_groups[$link['groupid']][$objects][$link['templategroupid']] = [
				'templategroupid' => $link['templategroupid'],
				$id_field_name => $link['templateid']
			];

			if (!$objectids) {
				$db_objectids[$link['templateid']] = true;
			}
		}

		if (!$objectids) {
			$db_objectids = array_keys($db_objectids);
		}
	}

	/**
	 * Check to delete given templates from the given template groups.
	 *
	 * @static
	 *
	 * @param array  $del_objectids
	 * @param array  $groupids
	 *
	 * @throws APIException
	 */
	private static function checkDeletedObjects(array $del_objectids, array $groupids): void {
		$db_objects = API::Template()->get([
			'output' => ['host'],
			'templateids' => $del_objectids,
			'editable' => true,
			'preservekeys' => true
		]);

		if (count($db_objects) != count($del_objectids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		self::checkObjectsWithoutGroups($db_objects, $groupids);
	}

	/**
	 * Get template groups input array based on requested data and database data.
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

			$group['templates'] = [];
			$db_templates = array_column($db_group['templates'], null, 'templateid');

			if (array_key_exists('templates', $data)) {
				foreach ($data['templates'] as $template) {
					if (array_key_exists($template['templateid'], $db_templates)) {
						$group['templates'][] = $db_templates[$template['templateid']];
					}
					else {
						$group['templates'][] = ['templateid' => $template['templateid']];
					}
				}
			}

			$groups[] = $group;
		}

		return $groups;
	}

	/**
	 * Get rows to insert templates on the given template groups.
	 *
	 * @static
	 *
	 * @param array      $groups
	 * @param string     $method
	 * @param array|null $db_templategroupids
	 *
	 * @return array
	 */
	private static function getInsTemplatesGroups(array $groups, string $method, array &$db_templategroupids = null): array {
		$ins_templates_groups = [];

		if ($method === 'massUpdate') {
			$db_templategroupids = [];
		}

		foreach ($groups as $group) {
			foreach ($group['templates'] as $template) {
				if (!array_key_exists('templategroupid', $template)) {
					$ins_templates_groups[] = [
						'templateid' => $template['templateid'],
						'groupid' => $group['groupid']
					];
				}
				elseif ($method === 'massUpdate') {
					$db_templategroupids[$template['templategroupid']] = true;
				}
			}
		}

		return $ins_templates_groups;
	}

	/**
	 * Add IDs of inserted templates on the given template groups.
	 *
	 * @param array $groups
	 * @param array $ids
	 */
	private static function addTemplategroupids(array &$groups, array $ids): void {
		foreach ($groups as &$group) {
			foreach ($group['templates'] as &$template) {
				if (!array_key_exists('templategroupid', $template)) {
					$template['templategroupid'] = array_shift($ids);
				}
			}
			unset($template);
		}
		unset($group);
	}

	/**
	 * Get IDs to delete templates from the given template groups.
	 *
	 * @static
	 *
	 * @param array $db_groups
	 * @param array $db_templategroupids
	 *
	 * @return array
	 */
	private static function getDelTemplategroupids(array $db_groups, array $db_templategroupids = []): array {
		$del_templategroupids = [];

		foreach ($db_groups as $db_group) {
			$del_templategroupids += array_diff_key($db_group['templates'], $db_templategroupids);
		}

		$del_templategroupids = array_keys($del_templategroupids);

		return $del_templategroupids;
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$groupIds = array_keys($result);
		sort($groupIds);

		// adding templates
		if ($options['selectTemplates'] !== null) {
			if ($options['selectTemplates'] !== API_OUTPUT_COUNT) {
				$templates = [];
				$relationMap = $this->createRelationMap($result, 'groupid', 'templateid', 'templates_groups');
				$related_ids = $relationMap->getRelatedIds();

				if ($related_ids) {
					$templates = API::Template()->get([
						'output' => $options['selectTemplates'],
						'templateids' => $related_ids,
						'preservekeys' => true
					]);
					if (!is_null($options['limitSelects'])) {
						order_result($templates, 'template');
					}
				}

				$result = $relationMap->mapMany($result, $templates, 'templates', $options['limitSelects']);
			}
			else {
				$templates = API::Template()->get([
					'groupids' => $groupIds,
					'countOutput' => true,
					'groupCount' => true
				]);
				$templates = zbx_toHash($templates, 'groupid');
				foreach ($result as $groupid => $group) {
					$result[$groupid]['templates'] = array_key_exists($groupid, $templates)
						? $templates[$groupid]['rowscount']
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
}
