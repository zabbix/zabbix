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
		'massremove' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN],
		'propagate' => ['min_user_type' => USER_TYPE_SUPER_ADMIN]
	];

	protected $tableName = 'hstgrp';
	protected $tableAlias = 'g';
	protected $sortColumns = ['groupid', 'name'];

	/**
	 * Get template groups.
	 *
	 * @param array $options
	 *
	 * @return array|int
	 */
	public function get(array $options) {
		$result = [];

		$output_fields = ['groupid', 'name', 'uuid'];
		$template_fields = ['templateid', 'host', 'name', 'description', 'uuid'];

		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			// filter
			'groupids' =>							['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'templateids' =>						['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'graphids' =>							['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'triggerids' =>							['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'with_templates' =>						['type' => API_BOOLEAN, 'default' => false],
			'with_items' =>							['type' => API_BOOLEAN, 'default' => false],
			'with_item_prototypes' =>				['type' => API_BOOLEAN, 'default' => false],
			'with_simple_graph_items' =>			['type' => API_BOOLEAN, 'default' => false],
			'with_simple_graph_item_prototypes' =>	['type' => API_BOOLEAN, 'default' => false],
			'with_triggers' =>						['type' => API_BOOLEAN, 'default' => false],
			'with_httptests' =>						['type' => API_BOOLEAN, 'default' => false],
			'with_graphs' =>						['type' => API_BOOLEAN, 'default' => false],
			'with_graph_prototypes' =>				['type' => API_BOOLEAN, 'default' => false],
			'filter' =>								['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => ['groupid', 'name', 'uuid']],
			'search' =>								['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => ['name']],
			'searchByAny' =>						['type' => API_BOOLEAN, 'default' => false],
			'startSearch' =>						['type' => API_BOOLEAN, 'default' => false],
			'excludeSearch' =>						['type' => API_BOOLEAN, 'default' => false],
			'searchWildcardsEnabled' =>				['type' => API_BOOLEAN, 'default' => false],
			// output
			'output' =>								['type' => API_OUTPUT, 'in' => implode(',', $output_fields), 'default' => API_OUTPUT_EXTEND],
			'selectTemplates' =>					['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL | API_ALLOW_COUNT, 'in' => implode(',', $template_fields), 'default' => null],
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
			'where' => ['g.type='.HOST_GROUP_TYPE_TEMPLATE_GROUP],
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

		// templateids
		if ($options['templateids'] !== null) {
			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['where'][] = dbConditionInt('hg.hostid', $options['templateids']);
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

		$sub_sql_common = [];

		// with_templates
		if ($options['with_templates']) {
			$sub_sql_common['from']['h'] = 'hosts h';
			$sub_sql_common['where']['hg-h'] = 'hg.hostid=h.hostid';
			$sub_sql_common['where'][] = dbConditionInt('h.status', [HOST_STATUS_TEMPLATE]);
		}

		$sub_sql_parts = $sub_sql_common;

		// with_items, with_simple_graph_items
		if ($options['with_items']) {
			$sub_sql_parts['from']['i'] = 'items i';
			$sub_sql_parts['where']['hg-i'] = 'hg.hostid=i.hostid';
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

		// with_triggers
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

		// with_httptests,
		if ($options['with_httptests']) {
			$sub_sql_parts['from']['ht'] = 'httptest ht';
			$sub_sql_parts['where']['hg-ht'] = 'hg.hostid=ht.hostid';
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
				_s('No permissions to call "%1$s.%2$s".', 'templategroup', __FUNCTION__)
			);
		}

		self::validateCreate($groups);

		$ins_groups = [];

		foreach ($groups as $group) {
			$ins_groups[] = $group + ['type' => HOST_GROUP_TYPE_TEMPLATE_GROUP];
		}

		$groupids = DB::insert('hstgrp', $ins_groups);

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

		$this->unlinkTemplates($db_groups);

		DB::delete('hstgrp', ['groupid' => $groupids]);

		self::addAuditLog(CAudit::ACTION_DELETE, CAudit::RESOURCE_TEMPLATE_GROUP, $db_groups);

		return ['groupids' => $groupids];
	}

	/**
	 * Validates input for create function.
	 *
	 * @param array $groups  [IN/OUT]
	 *
	 * @throws APIException if the input is invalid.
	 */
	private static function validateCreate(array &$groups): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['uuid'], ['name']], 'fields' => [
			'uuid' =>	['type' => API_UUID],
			'name' =>	['type' => API_TG_NAME, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('hstgrp', 'name')]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $groups, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		self::addUuid($groups);

		self::checkUuidDuplicates($groups);
		self::checkDuplicates($groups);
	}

	/**
	 * Validates input for create function.
	 *
	 * @param array $groups     [IN/OUT]
	 * @param array $db_groups  [OUT]
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateUpdate(array &$groups, ?array &$db_groups = null): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['uuid'], ['groupid'], ['name']], 'fields' => [
			'uuid' => 		['type' => API_UUID],
			'groupid' =>	['type' => API_ID, 'flags' => API_REQUIRED],
			'name' =>		['type' => API_TG_NAME, 'length' => DB::getFieldLength('hstgrp', 'name')]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $groups, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_groups = $this->get([
			'output' => ['uuid', 'groupid', 'name'],
			'groupids' => array_column($groups, 'groupid'),
			'editable' => true,
			'preservekeys' => true
		]);

		if (count($db_groups) != count($groups)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		self::checkUuidDuplicates($groups, $db_groups);
		self::checkDuplicates($groups, $db_groups);
	}

	/**
	 * Validates delete function input fields.
	 *
	 * @param array      $groupids   [IN]
	 * @param array|null $db_groups  [OUT]
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
	}

	private function unlinkTemplates(array $db_groups): void {
		$data = [
			'groups' => [],
			'templates' => []
		];

		foreach ($db_groups as $db_group) {
			$data['groups'][] = ['groupid' => $db_group['groupid']];
		}

		$this->massUpdate($data);
	}

	/**
	 * Check for unique template group names.
	 *
	 * @param array      $groups
	 * @param array|null $db_groups
	 *
	 * @throws APIException if template group names are not unique.
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
			'filter' => ['name' => $names, 'type' => HOST_GROUP_TYPE_TEMPLATE_GROUP],
			'limit' => 1
		]);

		if ($duplicates) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Template group "%1$s" already exists.', $duplicates[0]['name'])
			);
		}
	}

	/**
	 * Add the UUID to those of the given template groups that don't have the 'uuid' parameter set.
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
	 * Verify template group UUIDs are not repeated.
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
				'type' => HOST_GROUP_TYPE_TEMPLATE_GROUP,
				'uuid' => array_keys($group_indexes)
			],
			'limit' => 1
		]);

		if ($duplicates) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Invalid parameter "%1$s": %2$s.', '/'.($group_indexes[$duplicates[0]['uuid']] + 1),
					_('template group with the same UUID already exists')
				)
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

			if ($usrgrps) {
				CUserGroup::updateForce(array_values($usrgrps), $db_usrgrps);
			}
		}
	}

	/**
	 * Get links of parent groups to given groups.
	 *
	 * @param array $groups  Template groups which group links need to be identified.
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
			'filter' => ['name' => array_keys($parent_names), 'type' => HOST_GROUP_TYPE_TEMPLATE_GROUP]
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
	 * Prepare rights to inherit from parent template groups.
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

			if (!array_key_exists('templategroup_rights', $db_usrgrps[$db_right['groupid']])) {
				$db_usrgrps[$db_right['groupid']]['templategroup_rights'] = [];
			}

			foreach ($group_links[$db_right['id']] as $hstgrpid) {
				$usrgrps[$db_right['groupid']]['templategroup_rights'][] = [
					'permission' => $db_right['permission'],
					'id' => $hstgrpid
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
		$this->validateMassAdd($data);

		API::Template()->massAdd($data);

		return ['groupids' => array_column($data['groups'], 'groupid')];
	}

	private function validateMassAdd(array &$data): void {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'groups' =>		['type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['groupid']], 'fields' => [
				'groupid' =>	['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'templates' =>	['type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['templateid']], 'fields' => [
				'templateid'=>	['type' => API_ID, 'flags' => API_REQUIRED]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $data, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}
	}

	/**
	 * Replace templates on the given template groups.
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function massUpdate(array $data): array {
		$this->validateMassUpdate($data, $templates, $db_templates);

		API::Template()->updateForce($templates, $db_templates);

		return ['groupids' => array_column($data['groups'], 'groupid')];
	}

	private function validateMassUpdate(array &$data, ?array &$templates, ?array &$db_templates): void {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
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

		$db_templates = API::Template()->get([
			'output' => ['templateid', 'host'],
			'groupids' => array_column($data['groups'], 'groupid'),
			'editable' => true,
			'preservekeys' => true
		]);

		if ($data['templates']) {
			$db_templates += API::Template()->get([
				'output' => ['templateid', 'host'],
				'templateids' => array_column($data['templates'], 'templateid'),
				'editable' => true,
				'preservekeys' => true
			]);
		}

		foreach ($data['templates'] as $i => $template) {
			if (!array_key_exists($template['templateid'], $db_templates)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _s('Invalid parameter "%1$s": %2$s.',
					'/templates/'.($i + 1), _('object does not exist, or you have no permissions to it')
				));
			}
		}

		$templates = [];
		$del_templates = [];

		if (!$db_templates) {
			return;
		}

		$templateids = array_column($data['templates'], 'templateid');

		foreach ($db_templates as $db_template) {
			if (in_array($db_template['templateid'], $templateids)) {
				$templates[$db_template['templateid']] = [
					'templateid' => $db_template['templateid'],
					'groups' => $data['groups']
				];
			}
			else {
				$del_templates[$db_template['templateid']] = [
					'templateid' => $db_template['templateid'],
					'groups' => []
				];
			}
		}

		API::Template()->addAffectedGroups($templates + $del_templates, $db_templates);

		if ($templates) {
			API::Template()->addUnchangedGroups($templates, $db_templates);
		}

		if ($del_templates) {
			API::Template()->addUnchangedGroups($del_templates, $db_templates,
				['groupids' => array_column($data['groups'], 'groupid')]
			);
		}

		$templates += $del_templates;

		API::Template()->checkHostsWithoutGroups($templates, $db_templates);
	}

	/**
	 * Remove given templates from given template groups.
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function massRemove(array $data): array {
		$this->validateMassRemove($data);

		API::Template()->massRemove($data);

		return ['groupids' => $data['groupids']];
	}

	private function validateMassRemove(array &$data): void {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'groupids' =>		['type' => API_IDS, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_NORMALIZE, 'uniq' => true],
			'templateids' =>	['type' => API_IDS, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_NORMALIZE, 'uniq' => true]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $data, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}
	}

	protected function addRelatedObjects(array $options, array $result): array {
		$result = parent::addRelatedObjects($options, $result);

		$groupIds = array_keys($result);
		sort($groupIds);

		// adding templates
		if ($options['selectTemplates'] !== null) {
			if ($options['selectTemplates'] !== API_OUTPUT_COUNT) {
				$templates = [];
				$relationMap = $this->createRelationMap($result, 'groupid', 'hostid', 'hosts_groups');
				$related_ids = $relationMap->getRelatedIds();

				if ($related_ids) {
					$templates = API::Template()->get([
						'output' => $options['selectTemplates'],
						'templateids' => $related_ids,
						'preservekeys' => true
					]);
					if ($options['limitSelects'] !== null) {
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

		return $result;
	}

	/**
	 *  Apply permissions to all template group's subgroups.
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function propagate(array $data): array {
		$this->validatePropagate($data, $db_groups);

		foreach ($db_groups as $db_group) {
			if ($data['permissions']) {
				$this->inheritPermissions($db_group['groupid'], $db_group['name']);
			}
		}

		return ['groupids' => array_column($data['groups'], 'groupid')];
	}

	/**
	 * Validates propagate function's input fields.
	 *
	 * @param array $data
	 * @param array $db_groups
	 *
	 * @throws APIException if the input is invalid.
	 */
	private function validatePropagate(array &$data, ?array &$db_groups = null): void {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'groups' =>			['type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['groupid']], 'fields' => [
				'groupid' =>		['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'permissions' =>	['type' => API_BOOLEAN, 'flags' => API_REQUIRED]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $data, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		if ($data['permissions'] != true) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Parameter "%1$s" must be enabled.', 'permissions'));
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
	 * Apply template group rights to all subgroups.
	 *
	 * @param string $groupid  Template group ID.
	 * @param string $name     Template group name.
	 */
	private function inheritPermissions(string $groupid, string $name): void {
		$child_groupids = $this->getChildGroupIds($name);

		if (!$child_groupids) {
			return;
		}

		$usrgrps = API::UserGroup()->get([
			'output' => ['usrgrpid'],
			'selectTemplateGroupRights' => ['id', 'permission']
		]);

		$upd_usrgrps = [];

		foreach ($usrgrps as $usrgrp) {
			$rights = array_column($usrgrp['templategroup_rights'], null, 'id');

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

			if ($usrgrp['templategroup_rights'] !== $rights) {
				$upd_usrgrps[] = [
					'usrgrpid' => $usrgrp['usrgrpid'],
					'templategroup_rights' => $rights
				];
			}
		}

		if ($upd_usrgrps) {
			API::UserGroup()->update($upd_usrgrps);
		}
	}

	/**
	 * Returns list of child groups for template group with given name.
	 *
	 * @param string $name  Template group name.
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
