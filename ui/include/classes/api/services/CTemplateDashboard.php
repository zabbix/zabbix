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

	protected const AUDIT_RESOURCE = AUDIT_RESOURCE_TEMPLATE_DASHBOARD;

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
				'dashboardid' =>			['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'name' =>					['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'templateid' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE]
			]],
			'search' =>					['type' => API_OBJECT, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => [
				'name' =>					['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE]
			]],
			'searchByAny' =>			['type' => API_BOOLEAN, 'default' => false],
			'startSearch' =>			['type' => API_FLAG, 'default' => false],
			'excludeSearch' =>			['type' => API_FLAG, 'default' => false],
			'searchWildcardsEnabled' =>	['type' => API_BOOLEAN, 'default' => false],
			// output
			'output' =>					['type' => API_OUTPUT, 'in' => implode(',', ['dashboardid', 'name', 'templateid']), 'default' => API_OUTPUT_EXTEND],
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
			$options['output'] = array_diff(array_keys($this->getTableSchema()['fields']), ['userid', 'private']);
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
	 * @throws APIException if the input is invalid.
	 */
	protected function validateCreate(array &$dashboards): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['templateid', 'name']], 'fields' => [
			'name' =>			['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('dashboard', 'name')],
			'templateid' =>		['type' => API_ID, 'flags' => API_REQUIRED | API_NOT_EMPTY],
			'display_period' =>	['type' => API_INT32, 'in' => implode(',', self::DISPLAY_PERIODS)],
			'auto_start' =>		['type' => API_INT32, 'in' => '0,1'],
			'pages' =>			['type' => API_OBJECTS, 'fields' => [
				'name' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('dashboard_page', 'name')],
				'display_period' =>	['type' => API_INT32, 'in' => implode(',', array_merge([0], self::DISPLAY_PERIODS))],
				'widgets' =>		['type' => API_OBJECTS, 'fields' => [
					'type' =>			['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('widget', 'type')],
					'name' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('widget', 'name')],
					'view_mode' =>		['type' => API_INT32, 'in' => implode(',', [ZBX_WIDGET_VIEW_MODE_NORMAL, ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER])],
					'x' =>				['type' => API_INT32, 'in' => '0:'.self::MAX_X],
					'y' =>				['type' => API_INT32, 'in' => '0:'.self::MAX_Y],
					'width' =>			['type' => API_INT32, 'in' => '1:'.DASHBOARD_MAX_COLUMNS],
					'height' =>			['type' => API_INT32, 'in' => '2:'.DASHBOARD_WIDGET_MAX_ROWS],
					'fields' =>			['type' => API_OBJECTS, 'fields' => [
						'type' =>			['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', array_keys(self::WIDGET_FIELD_TYPE_COLUMNS))],
						'name' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('widget_field', 'name')],
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

		$this->checkDuplicates($names_by_templateid);
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
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['dashboardid']], 'fields' => [
			'dashboardid' =>		['type' => API_ID, 'flags' => API_REQUIRED],
			'name' =>				['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('dashboard', 'name')],
			'display_period' =>		['type' => API_INT32, 'in' => implode(',', self::DISPLAY_PERIODS)],
			'auto_start' =>			['type' => API_INT32, 'in' => '0,1'],
			'pages' =>				['type' => API_OBJECTS, 'uniq' => [['dashboard_pageid']], 'fields' => [
				'dashboard_pageid' =>	['type' => API_ID],
				'name' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('dashboard_page', 'name')],
				'display_period' =>		['type' => API_INT32, 'in' => implode(',', array_merge([0], self::DISPLAY_PERIODS))],
				'widgets' =>			['type' => API_OBJECTS, 'uniq' => [['widgetid']], 'fields' => [
					'widgetid' =>			['type' => API_ID],
					'type' =>				['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('widget', 'type')],
					'name' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('widget', 'name')],
					'view_mode' =>			['type' => API_INT32, 'in' => implode(',', [ZBX_WIDGET_VIEW_MODE_NORMAL, ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER])],
					'x' =>					['type' => API_INT32, 'in' => '0:'.self::MAX_X],
					'y' =>					['type' => API_INT32, 'in' => '0:'.self::MAX_Y],
					'width' =>				['type' => API_INT32, 'in' => '1:'.DASHBOARD_MAX_COLUMNS],
					'height' =>				['type' => API_INT32, 'in' => '2:'.DASHBOARD_WIDGET_MAX_ROWS],
					'fields' =>				['type' => API_OBJECTS, 'fields' => [
						'type' =>				['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', array_keys(self::WIDGET_FIELD_TYPE_COLUMNS))],
						'name' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('widget_field', 'name')],
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

		$api_input_rules = ['type' => API_OBJECTS, 'uniq' => [['name', 'templateid']]];
		if (!CApiInputValidator::validateUniqueness($api_input_rules, $dashboards, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$this->addAffectedObjects($dashboards, $db_dashboards);

		$this->checkDuplicates($dashboards, $db_dashboards);
		$this->checkWidgets($dashboards, $db_dashboards);
		$this->checkWidgetFields($dashboards, $db_dashboards);
	}
}
