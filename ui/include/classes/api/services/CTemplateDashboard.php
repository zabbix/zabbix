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
 * Template dashboards API implementation.
 */
class CTemplateDashboard extends CDashboardGeneral {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_ZABBIX_USER],
		'create' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN],
		'update' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN],
		'delete' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN]
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
			'templateids' =>			['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'filter' =>					['type' => API_OBJECT, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => [
				'uuid' =>					['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'dashboardid' =>			['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'name' =>					['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'templateid' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'display_period' =>			['type' => API_INTS32, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'in' => implode(',', DASHBOARD_DISPLAY_PERIODS)],
				'auto_start' =>				['type' => API_INTS32, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'in' => '0,1']
			]],
			'search' =>					['type' => API_OBJECT, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => [
				'name' =>					['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE]
			]],
			'searchByAny' =>			['type' => API_BOOLEAN, 'default' => false],
			'startSearch' =>			['type' => API_FLAG, 'default' => false],
			'excludeSearch' =>			['type' => API_FLAG, 'default' => false],
			'searchWildcardsEnabled' =>	['type' => API_BOOLEAN, 'default' => false],
			// output
			'output' =>					['type' => API_OUTPUT, 'in' => implode(',', ['dashboardid', 'name', 'templateid', 'display_period', 'auto_start', 'uuid']), 'default' => API_OUTPUT_EXTEND],
			'selectPages' =>			['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', ['dashboard_pageid', 'name', 'display_period', 'widgets']), 'default' => null],
			'countOutput' =>			['type' => API_FLAG, 'default' => false],
			'groupCount' =>				['type' => API_FLAG, 'default' => false],
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
			'where' => [],
			'order' => [],
			'group' => []
		];

		if (!$options['countOutput'] && $options['output'] === API_OUTPUT_EXTEND) {
			$options['output'] = $this->getTableSchema()['fields'];
			unset($options['output']['userid'], $options['output']['private']);
			$options['output'] = array_keys($options['output']);
		}

		$options['groupCount'] = ($options['groupCount'] && $options['countOutput']);

		// permissions
		if (in_array(self::$userData['type'], [USER_TYPE_ZABBIX_USER, USER_TYPE_ZABBIX_ADMIN])) {
			if ($options['templateids'] !== null) {
				$options['templateids'] = array_keys(API::Template()->get([
					'output' => [],
					'templateids' => $options['templateids'],
					'editable' => $options['editable'],
					'preservekeys' => true
				]));
			}
			else {
				$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ;
				$user_groups = getUserGroupsByUserId(self::$userData['userid']);

				$sql_parts['where'][] = 'EXISTS ('.
					'SELECT NULL'.
					' FROM hosts_groups hgg'.
						' JOIN rights r'.
							' ON r.id=hgg.groupid'.
								' AND '.dbConditionInt('r.groupid', $user_groups).
					' WHERE d.templateid=hgg.hostid'.
					' GROUP BY hgg.hostid'.
					' HAVING MIN(r.permission)>'.PERM_DENY.
						' AND MAX(r.permission)>='.zbx_dbstr($permission).
					')';
			}
		}

		// dashboardids
		if ($options['dashboardids'] !== null) {
			$sql_parts['where'][] = dbConditionInt('d.dashboardid', $options['dashboardids']);
		}

		// dashboardids
		$sql_parts['where'][] = ($options['templateids'] !== null)
			? dbConditionInt('d.templateid', $options['templateids'])
			: 'd.templateid IS NOT NULL';

		// filter
		if ($options['filter'] !== null) {
			$this->dbFilter('dashboard d', $options, $sql_parts);
		}

		// search
		if ($options['search'] !== null) {
			zbx_db_search('dashboard d', $options, $sql_parts);
		}

		if ($options['groupCount']) {
			$sql_parts['group']['templateid'] = 'd.templateid';
		}

		$sql_parts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sql_parts);
		$sql_parts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sql_parts);

		$result = DBselect(self::createSelectQueryFromParts($sql_parts), $options['limit']);

		$db_dashboards = [];

		while ($row = DBfetch($result)) {
			if ($options['countOutput']) {
				if ($options['groupCount']) {
					$db_dashboards[] = $row;
				}
				else {
					return $row['rowscount'];
				}
			}
			else {
				$db_dashboards[$row['dashboardid']] = $row;
			}
		}

		if ($db_dashboards && !$options['groupCount']) {
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
			unset($dashboard['pages']);
			$ins_dashboards[] = $dashboard;
		}

		$dashboardids = DB::insert('dashboard', $ins_dashboards);

		foreach ($dashboards as $index => &$dashboard) {
			$dashboard['dashboardid'] = $dashboardids[$index];
		}
		unset($dashboard);

		$this->updatePages($dashboards);

		self::addAuditLog(CAudit::ACTION_ADD, CAudit::RESOURCE_TEMPLATE_DASHBOARD, $dashboards);

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

		$this->updatePages($dashboards, $db_dashboards);

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_TEMPLATE_DASHBOARD, $dashboards, $db_dashboards);

		return ['dashboardids' => array_column($dashboards, 'dashboardid')];
	}

	/**
	 * @param array $dashboards
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateCreate(array &$dashboards): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['uuid'], ['templateid', 'name']], 'fields' => [
			'uuid' =>			['type' => API_UUID],
			'name' =>			['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('dashboard', 'name')],
			'templateid' =>		['type' => API_ID, 'flags' => API_REQUIRED | API_NOT_EMPTY],
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

		$templateids = array_column($dashboards, 'templateid', 'templateid');

		$db_templates_count = API::Template()->get([
			'countOutput' => true,
			'templateids' => $templateids
		]);

		if ($db_templates_count != count($templateids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		$this->checkAndAddUuid($dashboards);
		$this->checkDuplicates($dashboards);
		$this->checkWidgets($dashboards);
		$this->checkWidgetFields($dashboards);
	}

	/**
	 * Check that no duplicate UUID is being added. Add UUID to all template dashboards, if it doesn't exist.
	 *
	 * @param array $dashboards_to_create
	 *
	 * @throws APIException
	 */
	protected function checkAndAddUuid(array &$dashboards_to_create): void {
		foreach ($dashboards_to_create as &$dashboard) {
			if (!array_key_exists('uuid', $dashboard)) {
				$dashboard['uuid'] = generateUuidV4();
			}
		}
		unset($dashboard);

		$db_uuid = DB::select('dashboard', [
			'output' => ['uuid'],
			'filter' => ['uuid' => array_column($dashboards_to_create, 'uuid')],
			'limit' => 1
		]);

		if ($db_uuid) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Entry with UUID "%1$s" already exists.', $db_uuid[0]['uuid'])
			);
		}
	}

	/**
	 * @param array      $dashboards
	 * @param array|null $db_dashboards
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateUpdate(array &$dashboards, array &$db_dashboards = null): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['dashboardid']], 'fields' => [
			'dashboardid' =>		['type' => API_ID, 'flags' => API_REQUIRED],
			'name' =>				['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('dashboard', 'name')],
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
			'output' => ['dashboardid', 'name', 'templateid', 'display_period', 'auto_start'],
			'dashboardids' => array_column($dashboards, 'dashboardid'),
			'editable' => true,
			'preservekeys' => true
		]);

		if (count($db_dashboards) != count($dashboards)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		// Copy original dashboard names and templateids when not specified (for validation and error reporting).
		$dashboards = $this->extendObjectsByKey($dashboards, $db_dashboards, 'dashboardid', ['name', 'templateid']);

		$api_input_rules = ['type' => API_OBJECTS, 'uniq' => [['name', 'templateid']], 'fields' => [
			'name' =>		['type' => API_STRING_UTF8],
			'templateid' =>	['type' => API_ID]
		]];

		if (!CApiInputValidator::validateUniqueness($api_input_rules, $dashboards, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		// Add the existing pages, widgets and widget fields to $db_dashboards.
		$this->addAffectedObjects($dashboards, $db_dashboards);

		// Check ownership of the referenced pages and widgets.
		$this->checkReferences($dashboards, $db_dashboards);

		$this->checkDuplicates($dashboards, $db_dashboards);
		$this->checkWidgets($dashboards, $db_dashboards);
		$this->checkWidgetFields($dashboards, $db_dashboards);
	}

	/**
	 * Check for unique dashboard names per template.
	 *
	 * @param array      $dashboards
	 * @param array|null $db_dashboards
	 *
	 * @throws APIException if dashboard names are not unique.
	 */
	protected function checkDuplicates(array $dashboards, array $db_dashboards = null): void {
		$names_by_templateid = [];

		foreach ($dashboards as $dashboard) {
			if ($db_dashboards === null || $dashboard['name'] !== $db_dashboards[$dashboard['dashboardid']]['name']) {
				$names_by_templateid[$dashboard['templateid']][] = $dashboard['name'];
			}
		}

		if (!$names_by_templateid) {
			return;
		}

		$where = [];
		foreach ($names_by_templateid as $templateid => $names) {
			$where[] = '('.dbConditionId('d.templateid', [$templateid]).' AND '.dbConditionString('d.name', $names).')';
		}

		$duplicate = DBfetch(DBselect('SELECT d.name FROM dashboard d WHERE '.implode(' OR ', $where), 1));

		if ($duplicate) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Dashboard "%1$s" already exists.', $duplicate['name']));
		}
	}
}
