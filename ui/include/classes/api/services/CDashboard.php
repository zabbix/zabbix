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
 * Dashboards API implementation.
 */
class CDashboard extends CDashboardGeneral {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_ZABBIX_USER],
		'create' => ['min_user_type' => USER_TYPE_ZABBIX_USER, 'action' => CRoleHelper::ACTIONS_EDIT_DASHBOARDS],
		'update' => ['min_user_type' => USER_TYPE_ZABBIX_USER, 'action' => CRoleHelper::ACTIONS_EDIT_DASHBOARDS],
		'delete' => ['min_user_type' => USER_TYPE_ZABBIX_USER, 'action' => CRoleHelper::ACTIONS_EDIT_DASHBOARDS]
	];

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
			'filter' =>					['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => ['dashboardid', 'name', 'userid', 'private', 'display_period', 'auto_start']],
			'search' =>					['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => ['name']],
			'searchByAny' =>			['type' => API_BOOLEAN, 'default' => false],
			'startSearch' =>			['type' => API_FLAG, 'default' => false],
			'excludeSearch' =>			['type' => API_FLAG, 'default' => false],
			'searchWildcardsEnabled' =>	['type' => API_BOOLEAN, 'default' => false],
			// output
			'output' =>					['type' => API_OUTPUT, 'in' => implode(',', ['dashboardid', 'name', 'userid', 'private', 'display_period', 'auto_start']), 'default' => API_OUTPUT_EXTEND],
			'selectUsers' =>			['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', ['userid', 'permission']), 'default' => null],
			'selectUserGroups' =>		['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', ['usrgrpid', 'permission']), 'default' => null],
			'selectPages' =>			['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', ['dashboard_pageid', 'name', 'display_period', 'widgets']), 'default' => null],
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
			'select' => ['dashboard' => 'd.dashboardid'],
			'from' => ['dashboard' => 'dashboard d'],
			'where' => ['d.templateid IS NULL'],
			'order' => [],
			'group' => []
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

		$sql_parts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sql_parts);
		$sql_parts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sql_parts);

		$result = DBselect(self::createSelectQueryFromParts($sql_parts), $options['limit']);

		$db_dashboards = [];

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
	 * @return array
	 */
	public function create(array $dashboards): array {
		$this->validateCreate($dashboards);

		$ins_dashboards = [];

		foreach ($dashboards as $dashboard) {
			unset($dashboard['users'], $dashboard['userGroups'], $dashboard['pages']);
			$ins_dashboards[] = $dashboard;
		}

		$dashboardids = DB::insert('dashboard', $ins_dashboards);

		foreach ($dashboards as $index => &$dashboard) {
			$dashboard['dashboardid'] = $dashboardids[$index];
		}
		unset($dashboard);

		$this->updateDashboardUser($dashboards, __FUNCTION__);
		$this->updateDashboardUsrgrp($dashboards, __FUNCTION__);
		$this->updatePages($dashboards);

		self::addAuditLog(CAudit::ACTION_ADD, CAudit::RESOURCE_DASHBOARD, $dashboards);

		return ['dashboardids' => $dashboardids];
	}

	/**
	 * @param array $dashboards
	 *
	 * @return array
	 */
	public function update(array $dashboards): array {
		$this->validateUpdate($dashboards, $db_dashboards);

		$upd_dashboards = [];

		foreach ($dashboards as $dashboard) {
			$upd_dashboard = DB::getUpdatedValues('dashboard', $dashboard, $db_dashboards[$dashboard['dashboardid']]);

			if ($upd_dashboard) {
				$upd_dashboards[] = [
					'values' => $upd_dashboard,
					'where' => ['dashboardid' => $dashboard['dashboardid']]
				];
			}
		}

		if ($upd_dashboards) {
			DB::update('dashboard', $upd_dashboards);
		}

		$this->updateDashboardUser($dashboards, __FUNCTION__, $db_dashboards);
		$this->updateDashboardUsrgrp($dashboards, __FUNCTION__, $db_dashboards);
		$this->updatePages($dashboards, $db_dashboards);

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_DASHBOARD, $dashboards, $db_dashboards);

		return ['dashboardids' => array_column($dashboards, 'dashboardid')];
	}

	/**
	 * @param array $dashboards
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateCreate(array &$dashboards): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['name']], 'fields' => [
			'name' =>			['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('dashboard', 'name')],
			'userid' =>			['type' => API_ID, 'default' => self::$userData['userid']],
			'private' =>		['type' => API_INT32, 'in' => implode(',', [PUBLIC_SHARING, PRIVATE_SHARING])],
			'users' =>			['type' => API_OBJECTS, 'uniq' => [['userid']], 'fields' => [
				'userid' =>			['type' => API_ID, 'flags' => API_REQUIRED],
				'permission' =>		['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [PERM_READ, PERM_READ_WRITE])]
			]],
			'userGroups' =>		['type' => API_OBJECTS, 'uniq' => [['usrgrpid']], 'fields' => [
				'usrgrpid' =>		['type' => API_ID, 'flags' => API_REQUIRED],
				'permission' =>		['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [PERM_READ, PERM_READ_WRITE])]
			]],
			'display_period' =>	['type' => API_INT32, 'in' => implode(',', DASHBOARD_DISPLAY_PERIODS)],
			'auto_start' =>		['type' => API_INT32, 'in' => '0,1'],
			'pages' =>			['type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DASHBOARD_MAX_PAGES, 'fields' => [
				'name' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('dashboard_page', 'name')],
				'display_period' =>	['type' => API_INT32, 'in' => implode(',', array_merge([0], DASHBOARD_DISPLAY_PERIODS))],
				'widgets' =>		['type' => API_OBJECTS, 'fields' => [
					'type' =>			['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('widget', 'type')],
					'name' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('widget', 'name')],
					'view_mode' =>		['type' => API_INT32, 'in' => implode(',', [ZBX_WIDGET_VIEW_MODE_NORMAL, ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER])],
					'x' =>				['type' => API_INT32, 'in' => '0:'.(DASHBOARD_MAX_COLUMNS - 1)],
					'y' =>				['type' => API_INT32, 'in' => '0:'.(DASHBOARD_MAX_ROWS - 2)],
					'width' =>			['type' => API_INT32, 'in' => '1:'.DASHBOARD_MAX_COLUMNS],
					'height' =>			['type' => API_INT32, 'in' => '2:'.DASHBOARD_WIDGET_MAX_ROWS],
					'fields' =>			['type' => API_OBJECTS, 'fields' => [
						'type' =>			['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', array_keys(self::WIDGET_FIELD_TYPE_COLUMNS))],
						'name' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('widget_field', 'name'), 'default' => DB::getDefault('widget_field', 'name')],
						'value' =>			['type' => API_MULTIPLE, 'flags' => API_REQUIRED, 'rules' => [
												['if' => ['field' => 'type', 'in' => implode(',', [ZBX_WIDGET_FIELD_TYPE_INT32])], 'type' => API_INT32],
												['if' => ['field' => 'type', 'in' => implode(',', [ZBX_WIDGET_FIELD_TYPE_STR])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('widget_field', 'value_str')],
												['if' => ['field' => 'type', 'in' => implode(',', array_keys(self::WIDGET_FIELD_TYPE_COLUMNS_FK))], 'type' => API_ID]
						]]
					]]
				]]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $dashboards, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$this->checkDuplicates($dashboards);
		$this->checkUsers($dashboards);
		$this->checkUserGroups($dashboards);
		$this->checkWidgets($dashboards);
		$this->checkWidgetFields($dashboards);
	}

	/**
	 * @param array      $dashboards
	 * @param array|null $db_dashboards
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateUpdate(array &$dashboards, array &$db_dashboards = null): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['dashboardid'], ['name']], 'fields' => [
			'dashboardid' =>		['type' => API_ID, 'flags' => API_REQUIRED],
			'name' =>				['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('dashboard', 'name')],
			'userid' =>				['type' => API_ID],
			'private' =>			['type' => API_INT32, 'in' => implode(',', [PUBLIC_SHARING, PRIVATE_SHARING])],
			'users' =>				['type' => API_OBJECTS, 'uniq' => [['userid']], 'fields' => [
				'userid' =>				['type' => API_ID, 'flags' => API_REQUIRED],
				'permission' =>			['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [PERM_READ, PERM_READ_WRITE])]
			]],
			'userGroups' =>			['type' => API_OBJECTS, 'uniq' => [['usrgrpid']], 'fields' => [
				'usrgrpid' =>			['type' => API_ID, 'flags' => API_REQUIRED],
				'permission' =>			['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [PERM_READ, PERM_READ_WRITE])]
			]],
			'display_period' =>		['type' => API_INT32, 'in' => implode(',', DASHBOARD_DISPLAY_PERIODS)],
			'auto_start' =>			['type' => API_INT32, 'in' => '0,1'],
			'pages' =>				['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY, 'uniq' => [['dashboard_pageid']], 'length' => DASHBOARD_MAX_PAGES, 'fields' => [
				'dashboard_pageid' =>	['type' => API_ID],
				'name' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('dashboard_page', 'name')],
				'display_period' =>		['type' => API_INT32, 'in' => implode(',', array_merge([0], DASHBOARD_DISPLAY_PERIODS))],
				'widgets' =>			['type' => API_OBJECTS, 'uniq' => [['widgetid']], 'fields' => [
					'widgetid' =>			['type' => API_ID],
					'type' =>				['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('widget', 'type')],
					'name' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('widget', 'name')],
					'view_mode' =>			['type' => API_INT32, 'in' => implode(',', [ZBX_WIDGET_VIEW_MODE_NORMAL, ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER])],
					'x' =>					['type' => API_INT32, 'in' => '0:'.(DASHBOARD_MAX_COLUMNS - 1)],
					'y' =>					['type' => API_INT32, 'in' => '0:'.(DASHBOARD_MAX_ROWS - 2)],
					'width' =>				['type' => API_INT32, 'in' => '1:'.DASHBOARD_MAX_COLUMNS],
					'height' =>				['type' => API_INT32, 'in' => '2:'.DASHBOARD_WIDGET_MAX_ROWS],
					'fields' =>				['type' => API_OBJECTS, 'fields' => [
						'type' =>				['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', array_keys(self::WIDGET_FIELD_TYPE_COLUMNS))],
						'name' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('widget_field', 'name'), 'default' => DB::getDefault('widget_field', 'name')],
						'value' =>				['type' => API_MULTIPLE, 'flags' => API_REQUIRED, 'rules' => [
													['if' => ['field' => 'type', 'in' => implode(',', [ZBX_WIDGET_FIELD_TYPE_INT32])], 'type' => API_INT32],
													['if' => ['field' => 'type', 'in' => implode(',', [ZBX_WIDGET_FIELD_TYPE_STR])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('widget_field', 'value_str')],
													['if' => ['field' => 'type', 'in' => implode(',', array_keys(self::WIDGET_FIELD_TYPE_COLUMNS_FK))], 'type' => API_ID]
						]]
					]]
				]]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $dashboards, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_dashboards = $this->get([
			'output' => ['dashboardid', 'name', 'userid', 'private', 'display_period', 'auto_start'],
			'dashboardids' => array_column($dashboards, 'dashboardid'),
			'editable' => true,
			'preservekeys' => true
		]);

		if (count($db_dashboards) != count($dashboards)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		// Copy original dashboard names when not specified (for error reporting).
		$dashboards = $this->extendObjectsByKey($dashboards, $db_dashboards, 'dashboardid', ['name']);

		// Add the existing pages, widgets and widget fields to $db_dashboards.
		$this->addAffectedObjects($dashboards, $db_dashboards);

		// Check ownership of the referenced pages and widgets.
		$this->checkReferences($dashboards, $db_dashboards);

		$this->checkDuplicates($dashboards, $db_dashboards);
		$this->checkUsers($dashboards, $db_dashboards);
		$this->checkUserGroups($dashboards);
		$this->checkWidgets($dashboards, $db_dashboards);
		$this->checkWidgetFields($dashboards, $db_dashboards);
	}

	/**
	 * Check for unique dashboard names.
	 *
	 * @param array      $dashboards
	 * @param array|null $db_dashboards
	 *
	 * @throws APIException if dashboard names are not unique.
	 */
	protected function checkDuplicates(array $dashboards, array $db_dashboards = null): void {
		$names = [];

		foreach ($dashboards as $dashboard) {
			if ($db_dashboards === null || $dashboard['name'] !== $db_dashboards[$dashboard['dashboardid']]['name']) {
				$names[] = $dashboard['name'];
			}
		}

		if (!$names) {
			return;
		}

		$duplicate = DBfetch(DBselect(
			'SELECT d.name FROM dashboard d WHERE d.templateid IS NULL AND '.dbConditionString('d.name', $names), 1
		));

		if ($duplicate) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Dashboard "%1$s" already exists.', $duplicate['name']));
		}
	}

	/**
	 * Check for valid users.
	 *
	 * @param array      $dashboards
	 * @param string     $dashboards[]['userid']             (optional)
	 * @param array      $dashboards[]['users']              (optional)
	 * @param string     $dashboards[]['users'][]['userid']
	 * @param array|null $db_dashboards
	 * @param string     $db_dashboards[]['userid']
	 *
	 * @throws APIException if user is not valid.
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
	 * @throws APIException if user group is not valid.
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
	 * Update table "dashboard_user".
	 *
	 * @param array      $dashboards
	 * @param string     $method
	 * @param array|null $db_dashboards
	 */
	protected function updateDashboardUser(array &$dashboards, string $method, array $db_dashboards = null): void {
		$ins_dashboard_users = [];
		$upd_dashboard_users = [];
		$del_dashboard_userids = [];

		foreach ($dashboards as &$dashboard) {
			if (!array_key_exists('users', $dashboard)) {
				continue;
			}

			$dashboardid = $dashboard['dashboardid'];

			$db_dashboard_users = ($method === 'update')
				? array_column($db_dashboards[$dashboardid]['users'], null, 'userid')
				: [];

			foreach ($dashboard['users'] as &$dashboard_user) {
				$userid = $dashboard_user['userid'];

				if (array_key_exists($userid, $db_dashboard_users)) {
					$dashboard_user['dashboard_userid'] = $db_dashboard_users[$userid]['dashboard_userid'];

					if ($dashboard_user['permission'] != $db_dashboard_users[$userid]['permission']) {
						$upd_dashboard_users[] = [
							'values' => ['permission' => $dashboard_user['permission']],
							'where' => ['dashboard_userid' => $db_dashboard_users[$userid]['dashboard_userid']]
						];
					}

					unset($db_dashboard_users[$userid]);
				}
				else {
					$ins_dashboard_users[] = ['dashboardid' => $dashboardid] + $dashboard_user;
				}
			}
			unset($dashboard_user);

			$del_dashboard_userids = array_merge($del_dashboard_userids,
				array_column($db_dashboard_users, 'dashboard_userid')
			);
		}
		unset($dashboard);

		if ($ins_dashboard_users) {
			$dashboard_userids = DB::insert('dashboard_user', $ins_dashboard_users);
		}

		if ($upd_dashboard_users) {
			DB::update('dashboard_user', $upd_dashboard_users);
		}

		if ($del_dashboard_userids) {
			DB::delete('dashboard_user', ['dashboard_userid' => $del_dashboard_userids]);
		}

		foreach ($dashboards as &$dashboard) {
			if (!array_key_exists('users', $dashboard)) {
				continue;
			}

			foreach ($dashboard['users'] as &$user) {
				if (!array_key_exists('dashboard_userid', $user)) {
					$user['dashboard_userid'] = array_shift($dashboard_userids);
				}
			}
			unset($user);
		}
		unset($dashboard);
	}

	/**
	 * Update table "dashboard_usrgrp".
	 *
	 * @param array      $dashboards
	 * @param string     $method
	 * @param array|null $db_dashboards
	 */
	protected function updateDashboardUsrgrp(array &$dashboards, string $method, array $db_dashboards = null): void {
		$ins_dashboard_usrgrps = [];
		$upd_dashboard_usrgrps = [];
		$del_dashboard_usrgrpids = [];

		foreach ($dashboards as &$dashboard) {
			if (!array_key_exists('userGroups', $dashboard)) {
				continue;
			}

			$dashboardid = $dashboard['dashboardid'];

			$db_dashboard_groups = ($method === 'update')
				? array_column($db_dashboards[$dashboardid]['userGroups'], null, 'usrgrpid')
				: [];

			foreach ($dashboard['userGroups'] as &$dashboard_group) {
				$usrgrpid = $dashboard_group['usrgrpid'];

				if (array_key_exists($usrgrpid, $db_dashboard_groups)) {
					$dashboard_group['dashboard_usrgrpid'] = $db_dashboard_groups[$usrgrpid]['dashboard_usrgrpid'];

					if ($dashboard_group['permission'] != $db_dashboard_groups[$usrgrpid]['permission']) {
						$upd_dashboard_usrgrps[] = [
							'values' => ['permission' => $dashboard_group['permission']],
							'where' => ['dashboard_usrgrpid' => $db_dashboard_groups[$usrgrpid]['dashboard_usrgrpid']]
						];
					}

					unset($db_dashboard_groups[$usrgrpid]);
				}
				else {
					$ins_dashboard_usrgrps[] = ['dashboardid' => $dashboardid] + $dashboard_group;
				}
			}
			unset($dashboard_group);

			$del_dashboard_usrgrpids = array_merge($del_dashboard_usrgrpids,
				array_column($db_dashboard_groups, 'dashboard_usrgrpid')
			);
		}
		unset($dashboard);

		if ($ins_dashboard_usrgrps) {
			$dashboard_usrgrpids = DB::insert('dashboard_usrgrp', $ins_dashboard_usrgrps);
		}

		if ($upd_dashboard_usrgrps) {
			DB::update('dashboard_usrgrp', $upd_dashboard_usrgrps);
		}

		if ($del_dashboard_usrgrpids) {
			DB::delete('dashboard_usrgrp', ['dashboard_usrgrpid' => $del_dashboard_usrgrpids]);
		}

		foreach ($dashboards as &$dashboard) {
			if (!array_key_exists('userGroups', $dashboard)) {
				continue;
			}

			foreach ($dashboard['userGroups'] as &$usrgrp) {
				if (!array_key_exists('dashboard_usrgrpid', $usrgrp)) {
					$usrgrp['dashboard_usrgrpid'] = array_shift($dashboard_usrgrpids);
				}
			}
			unset($usrgrp);
		}
		unset($dashboard);
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$dashboardids = array_keys($result);

		// Adding user shares.
		if ($options['selectUsers'] !== null) {
			$relation_map = $this->createRelationMap($result, 'dashboardid', 'userid', 'dashboard_user');
			$related_ids = $relation_map->getRelatedIds();
			$db_users = [];

			if ($related_ids) {
				// Get all allowed users.
				$db_users = API::User()->get([
					'output' => [],
					'userids' => $related_ids,
					'preservekeys' => true
				]);
			}

			if ($db_users) {
				$db_dashboard_users = API::getApiService()->select('dashboard_user', [
					'output' => $this->outputExtend($options['selectUsers'], ['dashboardid', 'userid']),
					'filter' => ['dashboardid' => $dashboardids, 'userid' => array_keys($db_users)],
					'preservekeys' => true
				]);

				$relation_map = $this->createRelationMap($db_dashboard_users, 'dashboardid', 'dashboard_userid');

				$db_dashboard_users = $this->unsetExtraFields($db_dashboard_users, ['userid'], $options['selectUsers']);

				foreach ($db_dashboard_users as &$db_dashboard_user) {
					unset($db_dashboard_user['dashboard_userid'], $db_dashboard_user['dashboardid']);
				}
				unset($db_dashboard_user);

				$result = $relation_map->mapMany($result, $db_dashboard_users, 'users');
			}
			else {
				foreach ($result as &$row) {
					$row['users'] = [];
				}
				unset($row);
			}
		}

		// Adding user group shares.
		if ($options['selectUserGroups'] !== null) {
			$relation_map = $this->createRelationMap($result, 'dashboardid', 'usrgrpid', 'dashboard_usrgrp');
			$related_ids = $relation_map->getRelatedIds();
			$db_usrgrps = [];

			if ($related_ids) {
				// Get all allowed groups.
				$db_usrgrps = API::UserGroup()->get([
					'output' => [],
					'usrgrpids' => $related_ids,
					'preservekeys' => true
				]);
			}

			if ($db_usrgrps) {
				$db_dashboard_usrgrps = API::getApiService()->select('dashboard_usrgrp', [
					'output' => $this->outputExtend($options['selectUserGroups'], ['dashboardid', 'usrgrpid']),
					'filter' => ['dashboardid' => $dashboardids, 'usrgrpid' => array_keys($db_usrgrps)],
					'preservekeys' => true
				]);

				$relation_map = $this->createRelationMap($db_dashboard_usrgrps, 'dashboardid', 'dashboard_usrgrpid');

				$db_dashboard_usrgrps =
					$this->unsetExtraFields($db_dashboard_usrgrps, ['usrgrpid'], $options['selectUserGroups']);

				foreach ($db_dashboard_usrgrps as &$db_dashboard_usrgrp) {
					unset($db_dashboard_usrgrp['dashboard_usrgrpid'], $db_dashboard_usrgrp['dashboardid']);
				}
				unset($db_dashboard_usrgrp);

				$result = $relation_map->mapMany($result, $db_dashboard_usrgrps, 'userGroups');
			}
			else {
				foreach ($result as &$row) {
					$row['userGroups'] = [];
				}
				unset($row);
			}
		}

		return $result;
	}
}
