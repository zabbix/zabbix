<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
 * Class containing methods for operations with dashboards.
 */
class CDashboard extends CDashboardGeneral {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_ZABBIX_USER],
		'create' => ['min_user_type' => USER_TYPE_ZABBIX_USER, 'action' => CRoleHelper::ACTIONS_EDIT_DASHBOARDS],
		'update' => ['min_user_type' => USER_TYPE_ZABBIX_USER, 'action' => CRoleHelper::ACTIONS_EDIT_DASHBOARDS],
		'delete' => ['min_user_type' => USER_TYPE_ZABBIX_USER, 'action' => CRoleHelper::ACTIONS_EDIT_DASHBOARDS]
	];

	protected const AUDIT_RESOURCE = AUDIT_RESOURCE_DASHBOARD;

	/**
	 * @param array $options
	 *
	 * @throws APIException if the input is invalid.
	 *
	 * @return array|int
	 */
	public function get(array $options = []) {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			// filter
			'dashboardids' =>			['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'filter' =>					['type' => API_OBJECT, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => [
				'dashboardid' =>			['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'name' =>					['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'userid' =>					['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'private' =>				['type' => API_INTS32, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'in' => implode(',', [PUBLIC_SHARING, PRIVATE_SHARING])]
			]],
			'search' =>					['type' => API_OBJECT, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => [
				'name' =>					['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE]
			]],
			'searchByAny' =>			['type' => API_BOOLEAN, 'default' => false],
			'startSearch' =>			['type' => API_FLAG, 'default' => false],
			'excludeSearch' =>			['type' => API_FLAG, 'default' => false],
			'searchWildcardsEnabled' =>	['type' => API_BOOLEAN, 'default' => false],
			// output
			'output' =>					['type' => API_OUTPUT, 'in' => implode(',', ['dashboardid', 'name', 'userid', 'private']), 'default' => API_OUTPUT_EXTEND],
			'selectUsers' =>			['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', ['userid', 'permission']), 'default' => null],
			'selectUserGroups' =>		['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', ['usrgrpid', 'permission']), 'default' => null],
			'selectWidgets' =>			['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', ['widgetid', 'type', 'name', 'view_mode', 'x', 'y', 'width', 'height', 'fields']), 'default' => null],
			'countOutput' =>			['type' => API_FLAG, 'default' => false],
			// sort and limit
			'sortfield' =>				['type' => API_STRINGS_UTF8, 'flags' => API_NORMALIZE, 'in' => implode(',', $this->sortColumns), 'uniq' => true, 'default' => []],
			'sortorder' =>				['type' => API_SORTORDER, 'default' => []],
			'limit' =>					['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => '1:'.ZBX_MAX_INT32, 'default' => null],
			// flags
			'editable' =>				['type' => API_BOOLEAN, 'default' => false],
			'preservekeys' =>			['type' => API_BOOLEAN, 'default' => false]
		]];
		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$sql_parts = [
			'select'	=> ['dashboard' => 'd.dashboardid'],
			'from'		=> ['dashboard' => 'dashboard d'],
			'where'		=> ['d.templateid IS NULL'],
			'order'		=> [],
			'group'		=> []
		];

		if (!$options['countOutput'] && $options['output'] === API_OUTPUT_EXTEND) {
			$options['output'] = $this->getTableSchema()['fields'];
			unset($options['output']['templateid']);
			$options['output'] = array_keys($options['output']);
		}

		// permissions
		if (in_array(self::$userData['type'], [USER_TYPE_ZABBIX_USER, USER_TYPE_ZABBIX_ADMIN])) {
			$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ;

			$user_groups = getUserGroupsByUserId(self::$userData['userid']);

			$sql_where = ['d.userid='.self::$userData['userid']];
			if (!$options['editable']) {
				$sql_where[] = 'd.private='.PUBLIC_SHARING;
			}
			$sql_where[] = 'EXISTS ('.
				'SELECT NULL'.
				' FROM dashboard_user du'.
				' WHERE d.dashboardid=du.dashboardid'.
					' AND du.userid='.self::$userData['userid'].
					' AND du.permission>='.$permission.
			')';
			$sql_where[] = 'EXISTS ('.
				'SELECT NULL'.
				' FROM dashboard_usrgrp dug'.
				' WHERE d.dashboardid=dug.dashboardid'.
					' AND '.dbConditionInt('dug.usrgrpid', $user_groups).
					' AND dug.permission>='.$permission.
			')';

			$sql_parts['where'][] = '('.implode(' OR ', $sql_where).')';
		}

		// dashboardids
		if ($options['dashboardids'] !== null) {
			$sql_parts['where'][] = dbConditionInt('d.dashboardid', $options['dashboardids']);
		}

		// filter
		if ($options['filter'] !== null) {
			$this->dbFilter('dashboard d', $options, $sql_parts);
		}

		// search
		if ($options['search'] !== null) {
			zbx_db_search('dashboard d', $options, $sql_parts);
		}

		$db_dashboards = [];

		$sql_parts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sql_parts);
		$sql_parts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sql_parts);

		$result = DBselect(self::createSelectQueryFromParts($sql_parts), $options['limit']);

		while ($row = DBfetch($result)) {
			if ($options['countOutput']) {
				return $row['rowscount'];
			}

			$db_dashboards[$row['dashboardid']] = $row;
		}

		if ($db_dashboards) {
			$db_dashboards = $this->addRelatedObjects($options, $db_dashboards);
			$db_dashboards = $this->unsetExtraFields($db_dashboards, ['dashboardid'], $options['output']);

			if (!$options['preservekeys']) {
				$db_dashboards = array_values($db_dashboards);
			}
		}

		return $db_dashboards;
	}

	/**
	 * @param array $dashboards
	 *
	 * @throws APIException if the input is invalid
	 */
	protected function validateCreate(array &$dashboards): void {
		$ids_widget_field_types = [ZBX_WIDGET_FIELD_TYPE_GROUP, ZBX_WIDGET_FIELD_TYPE_HOST, ZBX_WIDGET_FIELD_TYPE_ITEM,
			ZBX_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE, ZBX_WIDGET_FIELD_TYPE_GRAPH, ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE,
			ZBX_WIDGET_FIELD_TYPE_MAP
		];
		$widget_field_types = array_merge($ids_widget_field_types,
			[ZBX_WIDGET_FIELD_TYPE_INT32, ZBX_WIDGET_FIELD_TYPE_STR]
		);

		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['name']], 'fields' => [
			'name' =>			['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('dashboard', 'name')],
			'userid' =>			['type' => API_ID, 'default' => self::$userData['userid']],
			'private' =>		['type' => API_INT32, 'in' => implode(',', [PUBLIC_SHARING, PRIVATE_SHARING])],
			'users' =>			['type' => API_OBJECTS, 'fields' => [
				'userid' =>			['type' => API_ID, 'flags' => API_REQUIRED],
				'permission' =>		['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [PERM_READ, PERM_READ_WRITE])]
			]],
			'userGroups' =>		['type' => API_OBJECTS, 'fields' => [
				'usrgrpid' =>		['type' => API_ID, 'flags' => API_REQUIRED],
				'permission' =>		['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [PERM_READ, PERM_READ_WRITE])]
			]],
			'widgets' =>		['type' => API_OBJECTS, 'fields' => [
				'type' =>			['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('widget', 'type')],
				'name' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('widget', 'name'), 'default' => DB::getDefault('widget', 'name')],
				'view_mode' =>		['type' => API_INT32, 'in' => implode(',', [ZBX_WIDGET_VIEW_MODE_NORMAL, ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER]), 'default' => DB::getDefault('widget', 'view_mode')],
				'x' =>				['type' => API_INT32, 'in' => '0:'.self::MAX_X, 'default' => DB::getDefault('widget', 'x')],
				'y' =>				['type' => API_INT32, 'in' => '0:'.self::MAX_Y, 'default' => DB::getDefault('widget', 'y')],
				'width' =>			['type' => API_INT32, 'in' => '1:'.DASHBOARD_MAX_COLUMNS, 'default' => DB::getDefault('widget', 'width')],
				'height' =>			['type' => API_INT32, 'in' => '2:'.DASHBOARD_WIDGET_MAX_ROWS, 'default' => DB::getDefault('widget', 'height')],
				'fields' =>			['type' => API_OBJECTS, 'fields' => [
					'type' =>			['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', $widget_field_types)],
					'name' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('widget_field', 'name'), 'default' => DB::getDefault('widget_field', 'name')],
					'value' =>			['type' => API_MULTIPLE, 'flags' => API_REQUIRED, 'rules' => [
											['if' => ['field' => 'type', 'in' => implode(',', [ZBX_WIDGET_FIELD_TYPE_INT32])], 'type' => API_INT32],
											['if' => ['field' => 'type', 'in' => implode(',', [ZBX_WIDGET_FIELD_TYPE_STR])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('widget_field', 'value_str')],
											['if' => ['field' => 'type', 'in' => implode(',', $ids_widget_field_types)], 'type' => API_ID]
					]]
				]]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $dashboards, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$this->checkDuplicates(array_column($dashboards, 'name'));
		$this->checkUsers($dashboards);
		$this->checkUserGroups($dashboards);
		$this->checkWidgets($dashboards);
		$this->checkWidgetFields($dashboards, __FUNCTION__);
	}

	/**
	 * @param array $dashboards
	 * @param array $db_dashboards
	 *
	 * @throws APIException if the input is invalid
	 */
	protected function validateUpdate(array &$dashboards, array &$db_dashboards = null): void {
		$ids_widget_field_types = [ZBX_WIDGET_FIELD_TYPE_GROUP, ZBX_WIDGET_FIELD_TYPE_HOST, ZBX_WIDGET_FIELD_TYPE_ITEM,
			ZBX_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE, ZBX_WIDGET_FIELD_TYPE_GRAPH, ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE,
			ZBX_WIDGET_FIELD_TYPE_MAP
		];
		$widget_field_types = array_merge($ids_widget_field_types,
			[ZBX_WIDGET_FIELD_TYPE_INT32, ZBX_WIDGET_FIELD_TYPE_STR]
		);

		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['dashboardid'], ['name']], 'fields' => [
			'dashboardid' =>	['type' => API_ID, 'flags' => API_REQUIRED],
			'name' =>			['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('dashboard', 'name')],
			'userid' =>			['type' => API_ID],
			'private' =>		['type' => API_INT32, 'in' => implode(',', [PUBLIC_SHARING, PRIVATE_SHARING])],
			'users' =>			['type' => API_OBJECTS, 'fields' => [
				'userid' =>			['type' => API_ID, 'flags' => API_REQUIRED],
				'permission' =>		['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [PERM_READ, PERM_READ_WRITE])]
			]],
			'userGroups' =>		['type' => API_OBJECTS, 'fields' => [
				'usrgrpid' =>		['type' => API_ID, 'flags' => API_REQUIRED],
				'permission' =>		['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [PERM_READ, PERM_READ_WRITE])]
			]],
			'widgets' =>		['type' => API_OBJECTS, 'fields' => [
				'widgetid' =>		['type' => API_ID],
				'type' =>			['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('widget', 'type')],
				'name' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('widget', 'name')],
				'view_mode' =>		['type' => API_INT32, 'in' => implode(',', [ZBX_WIDGET_VIEW_MODE_NORMAL, ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER]), 'default' => DB::getDefault('widget', 'view_mode')],
				'x' =>				['type' => API_INT32, 'in' => '0:'.self::MAX_X],
				'y' =>				['type' => API_INT32, 'in' => '0:'.self::MAX_Y],
				'width' =>			['type' => API_INT32, 'in' => '1:'.DASHBOARD_MAX_COLUMNS],
				'height' =>			['type' => API_INT32, 'in' => '2:'.DASHBOARD_WIDGET_MAX_ROWS],
				'fields' =>			['type' => API_OBJECTS, 'fields' => [
					'type' =>			['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', $widget_field_types)],
					'name' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('widget_field', 'name'), 'default' => DB::getDefault('widget_field', 'name')],
					'value' =>			['type' => API_MULTIPLE, 'flags' => API_REQUIRED, 'rules' => [
											['if' => ['field' => 'type', 'in' => implode(',', [ZBX_WIDGET_FIELD_TYPE_INT32])], 'type' => API_INT32],
											['if' => ['field' => 'type', 'in' => implode(',', [ZBX_WIDGET_FIELD_TYPE_STR])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('widget_field', 'value_str')],
											['if' => ['field' => 'type', 'in' => implode(',', $ids_widget_field_types)], 'type' => API_ID]
					]]
				]]
			]]
		]];
		if (!CApiInputValidator::validate($api_input_rules, $dashboards, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		// Check dashboard names.
		$db_dashboards = $this->get([
			'output' => ['dashboardid', 'name', 'userid', 'private'],
			'dashboardids' => array_column($dashboards, 'dashboardid'),
			'selectWidgets' => ['widgetid', 'type', 'name', 'view_mode', 'x', 'y', 'width', 'height'],
			'editable' => true,
			'preservekeys' => true
		]);

		$dashboards = $this->extendObjectsByKey($dashboards, $db_dashboards, 'dashboardid', ['name']);

		$names = [];

		$widget_defaults = [
			'name' => DB::getDefault('widget', 'name'),
			'view_mode' => DB::getDefault('widget', 'view_mode'),
			'x' => DB::getDefault('widget', 'x'),
			'y' => DB::getDefault('widget', 'y'),
			'width' => DB::getDefault('widget', 'width'),
			'height' => DB::getDefault('widget', 'height')
		];

		foreach ($dashboards as &$dashboard) {
			// Check if this dashboard exists.
			if (!array_key_exists($dashboard['dashboardid'], $db_dashboards)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}

			$db_dashboard = $db_dashboards[$dashboard['dashboardid']];

			if ($dashboard['name'] !== $db_dashboard['name']) {
				$names[] = $dashboard['name'];
			}

			if (array_key_exists('widgets', $dashboard)) {
				$db_widgets = zbx_toHash($db_dashboard['widgets'], 'widgetid');

				foreach ($dashboard['widgets'] as &$widget) {
					if (!array_key_exists('widgetid', $widget)) {
						if (!array_key_exists('type', $widget)) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s(
								'Cannot create widget: %1$s.', _s('the parameter "%1$s" is missing', 'type')
							));
						}

						$widget += $widget_defaults;
					}
					elseif (!array_key_exists($widget['widgetid'], $db_widgets)) {
						self::exception(ZBX_API_ERROR_PERMISSIONS,
							_('No permissions to referred object or it does not exist!')
						);
					}

				}
				unset($widget);

				$dashboard['widgets'] = $this->extendObjectsByKey($dashboard['widgets'], $db_widgets, 'widgetid',
					['x', 'y', 'width', 'height']
				);
			}
		}
		unset($dashboard);

		if ($names) {
			$this->checkDuplicates($names);
		}
		$this->checkUsers($dashboards, $db_dashboards);
		$this->checkUserGroups($dashboards);
		$this->checkWidgets($dashboards);
		$this->checkWidgetFields($dashboards, __FUNCTION__);
	}

	/**
	 * Check for duplicated dashboards.
	 *
	 * @param array  $names
	 *
	 * @throws APIException  if dashboard already exists.
	 */
	protected function checkDuplicates(array $names): void {
		$db_dashboards = DBfetchArray(DBselect(
			'SELECT d.name'.
			' FROM dashboard d'.
			' WHERE d.templateid IS NULL'.
				' AND '.dbConditionString('d.name', $names), 1
		));

		if ($db_dashboards) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Dashboard "%1$s" already exists.', $db_dashboards[0]['name'])
			);
		}
	}

	/**
	 * Check for valid users.
	 *
	 * @param array  $dashboards
	 * @param string $dashboards[]['userid']             (optional)
	 * @param array  $dashboards[]['users']              (optional)
	 * @param string $dashboards[]['users'][]['userid']
	 * @param array  $db_dashboards
	 * @param string $db_dashboards[]['userid']
	 *
	 * @throws APIException  if user is not valid.
	 */
	protected function checkUsers(array $dashboards, array $db_dashboards = null): void {
		$userids = [];

		foreach ($dashboards as $dashboard) {
			$db_dashboard = ($db_dashboards !== null) ? $db_dashboards[$dashboard['dashboardid']] : null;

			if (array_key_exists('userid', $dashboard)
					&& ($db_dashboard === null || bccomp($dashboard['userid'], $db_dashboard['userid']) != 0)) {
				if (bccomp($dashboard['userid'], self::$userData['userid']) != 0
						&& in_array(self::$userData['type'], [USER_TYPE_ZABBIX_USER, USER_TYPE_ZABBIX_ADMIN])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Only super admins can set dashboard owner.'));
				}

				$userids[$dashboard['userid']] = true;
			}

			if (array_key_exists('users', $dashboard)) {
				foreach ($dashboard['users'] as $user) {
					$userids[$user['userid']] = true;
				}
			}
		}

		unset($userids[self::$userData['userid']]);

		if (!$userids) {
			return;
		}

		$userids = array_keys($userids);

		$db_users = API::User()->get([
			'output' => [],
			'userids' => $userids,
			'preservekeys' => true
		]);

		foreach ($userids as $userid) {
			if (!array_key_exists($userid, $db_users)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('User with ID "%1$s" is not available.', $userid));
			}
		}
	}

	/**
	 * Check for valid user groups.
	 *
	 * @param array  $dashboards
	 * @param array  $dashboards[]['userGroups']                (optional)
	 * @param string $dashboards[]['userGroups'][]['usrgrpid']
	 *
	 * @throws APIException  if user group is not valid.
	 */
	protected function checkUserGroups(array $dashboards): void {
		$usrgrpids = [];

		foreach ($dashboards as $dashboard) {
			if (array_key_exists('userGroups', $dashboard)) {
				foreach ($dashboard['userGroups'] as $usrgrp) {
					$usrgrpids[$usrgrp['usrgrpid']] = true;
				}
			}
		}

		if (!$usrgrpids) {
			return;
		}

		$usrgrpids = array_keys($usrgrpids);

		$db_usrgrps = API::UserGroup()->get([
			'output' => [],
			'usrgrpids' => $usrgrpids,
			'preservekeys' => true
		]);

		foreach ($usrgrpids as $usrgrpid) {
			if (!array_key_exists($usrgrpid, $db_usrgrps)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('User group with ID "%1$s" is not available.', $usrgrpid));
			}
		}
	}

	/**
	 * Returns widget field name by field type.
	 *
	 * @return array
	 */
	protected static function getFieldNamesByType(): array {
		return [
			ZBX_WIDGET_FIELD_TYPE_INT32 => 'value_int',
			ZBX_WIDGET_FIELD_TYPE_STR => 'value_str',
			ZBX_WIDGET_FIELD_TYPE_GROUP => 'value_groupid',
			ZBX_WIDGET_FIELD_TYPE_HOST => 'value_hostid',
			ZBX_WIDGET_FIELD_TYPE_ITEM => 'value_itemid',
			ZBX_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE => 'value_itemid',
			ZBX_WIDGET_FIELD_TYPE_GRAPH => 'value_graphid',
			ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE => 'value_graphid',
			ZBX_WIDGET_FIELD_TYPE_MAP => 'value_sysmapid'
		];
	}

	/**
	 * Update table "dashboard_user".
	 *
	 * @param array  $dashboards
	 * @param string $method
	 */
	protected function updateDashboardUser(array $dashboards, string $method): void {
		$dashboards_users = [];

		foreach ($dashboards as $dashboard) {
			if (array_key_exists('users', $dashboard)) {
				$dashboards_users[$dashboard['dashboardid']] = [];

				foreach ($dashboard['users'] as $user) {
					$dashboards_users[$dashboard['dashboardid']][$user['userid']] = [
						'permission' => $user['permission']
					];
				}
			}
		}

		if (!$dashboards_users) {
			return;
		}

		$db_dashboard_users = ($method === 'update')
			? DB::select('dashboard_user', [
				'output' => ['dashboard_userid', 'dashboardid', 'userid', 'permission'],
				'filter' => ['dashboardid' => array_keys($dashboards_users)]
			])
			: [];

		$userids = [];

		foreach ($db_dashboard_users as $db_dashboard_user) {
			$userids[$db_dashboard_user['userid']] = true;
		}

		// get list of accessible users
		$db_users = $userids
			? API::User()->get([
				'output' => [],
				'preservekeys' => true
			])
			: [];

		$ins_dashboard_users = [];
		$upd_dashboard_users = [];
		$del_dashboard_userids = [];

		foreach ($db_dashboard_users as $db_dashboard_user) {
			$dashboardid = $db_dashboard_user['dashboardid'];
			$userid = $db_dashboard_user['userid'];

			if (array_key_exists($userid, $dashboards_users[$dashboardid])) {
				if ($dashboards_users[$dashboardid][$userid]['permission'] != $db_dashboard_user['permission']) {
					$upd_dashboard_users[] = [
						'values' => ['permission' => $dashboards_users[$dashboardid][$userid]['permission']],
						'where' => ['dashboard_userid' => $db_dashboard_user['dashboard_userid']]
					];
				}

				unset($dashboards_users[$dashboardid][$userid]);
			}
			elseif (array_key_exists($userid, $db_users)) {
				$del_dashboard_userids[] = $db_dashboard_user['dashboard_userid'];
			}
		}

		foreach ($dashboards_users as $dashboardid => $users) {
			foreach ($users as $userid => $user) {
				$ins_dashboard_users[] = [
					'dashboardid' => $dashboardid,
					'userid' => $userid,
					'permission' => $user['permission']
				];
			}
		}

		if ($ins_dashboard_users) {
			DB::insertBatch('dashboard_user', $ins_dashboard_users);
		}

		if ($upd_dashboard_users) {
			DB::update('dashboard_user', $upd_dashboard_users);
		}

		if ($del_dashboard_userids) {
			DB::delete('dashboard_user', ['dashboard_userid' => $del_dashboard_userids]);
		}
	}

	/**
	 * Update table "dashboard_usrgrp".
	 *
	 * @param array  $dashboards
	 * @param string $method
	 */
	protected function updateDashboardUsrgrp(array $dashboards, string $method): void {
		$dashboards_usrgrps = [];

		foreach ($dashboards as $dashboard) {
			if (array_key_exists('userGroups', $dashboard)) {
				$dashboards_usrgrps[$dashboard['dashboardid']] = [];

				foreach ($dashboard['userGroups'] as $usrgrp) {
					$dashboards_usrgrps[$dashboard['dashboardid']][$usrgrp['usrgrpid']] = [
						'permission' => $usrgrp['permission']
					];
				}
			}
		}

		if (!$dashboards_usrgrps) {
			return;
		}

		$db_dashboard_usrgrps = ($method === 'update')
			? DB::select('dashboard_usrgrp', [
				'output' => ['dashboard_usrgrpid', 'dashboardid', 'usrgrpid', 'permission'],
				'filter' => ['dashboardid' => array_keys($dashboards_usrgrps)]
			])
			: [];

		$usrgrpids = [];

		foreach ($db_dashboard_usrgrps as $db_dashboard_usrgrp) {
			$usrgrpids[$db_dashboard_usrgrp['usrgrpid']] = true;
		}

		// get list of accessible user groups
		$db_usrgrps = $usrgrpids
			? API::UserGroup()->get([
				'output' => [],
				'preservekeys' => true
			])
			: [];

		$ins_dashboard_usrgrps = [];
		$upd_dashboard_usrgrps = [];
		$del_dashboard_usrgrpids = [];

		foreach ($db_dashboard_usrgrps as $db_dashboard_usrgrp) {
			$dashboardid = $db_dashboard_usrgrp['dashboardid'];
			$usrgrpid = $db_dashboard_usrgrp['usrgrpid'];

			if (array_key_exists($usrgrpid, $dashboards_usrgrps[$dashboardid])) {
				if ($dashboards_usrgrps[$dashboardid][$usrgrpid]['permission'] != $db_dashboard_usrgrp['permission']) {
					$upd_dashboard_usrgrps[] = [
						'values' => ['permission' => $dashboards_usrgrps[$dashboardid][$usrgrpid]['permission']],
						'where' => ['dashboard_usrgrpid' => $db_dashboard_usrgrp['dashboard_usrgrpid']]
					];
				}

				unset($dashboards_usrgrps[$dashboardid][$usrgrpid]);
			}
			elseif (array_key_exists($usrgrpid, $db_usrgrps)) {
				$del_dashboard_usrgrpids[] = $db_dashboard_usrgrp['dashboard_usrgrpid'];
			}
		}

		foreach ($dashboards_usrgrps as $dashboardid => $usrgrps) {
			foreach ($usrgrps as $usrgrpid => $usrgrp) {
				$ins_dashboard_usrgrps[] = [
					'dashboardid' => $dashboardid,
					'usrgrpid' => $usrgrpid,
					'permission' => $usrgrp['permission']
				];
			}
		}

		if ($ins_dashboard_usrgrps) {
			DB::insertBatch('dashboard_usrgrp', $ins_dashboard_usrgrps);
		}

		if ($upd_dashboard_usrgrps) {
			DB::update('dashboard_usrgrp', $upd_dashboard_usrgrps);
		}

		if ($del_dashboard_usrgrpids) {
			DB::delete('dashboard_usrgrp', ['dashboard_usrgrpid' => $del_dashboard_usrgrpids]);
		}
	}
}
