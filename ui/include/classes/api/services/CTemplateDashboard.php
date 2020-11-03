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
 * Class containing methods for operations with template dashboards.
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
			'selectWidgets' =>			['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', ['widgetid', 'type', 'name', 'view_mode', 'x', 'y', 'width', 'height', 'fields']), 'default' => null],
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
			'select'	=> ['dashboard' => 'd.dashboardid'],
			'from'		=> ['dashboard' => 'dashboard d'],
			'where'		=> [],
			'order'		=> [],
			'group'		=> []
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

		$db_dashboards = [];

		$sql_parts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sql_parts);
		$sql_parts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sql_parts);

		$result = DBselect(self::createSelectQueryFromParts($sql_parts), $options['limit']);

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
	 * @throws APIException if the input is invalid
	 */
	protected function validateCreate(array &$dashboards): void {
		$widget_types = [WIDGET_CLOCK, WIDGET_GRAPH, WIDGET_GRAPH_PROTOTYPE, WIDGET_PLAIN_TEXT, WIDGET_URL];
		$ids_widget_field_types = [ZBX_WIDGET_FIELD_TYPE_ITEM, ZBX_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE,
			ZBX_WIDGET_FIELD_TYPE_GRAPH, ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE
		];
		$widget_field_types = array_merge($ids_widget_field_types,
			[ZBX_WIDGET_FIELD_TYPE_INT32, ZBX_WIDGET_FIELD_TYPE_STR]
		);

		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['templateid', 'name']], 'fields' => [
			'name' =>		['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('dashboard', 'name')],
			'templateid' =>	['type' => API_ID, 'flags' => API_REQUIRED | API_NOT_EMPTY],
			'widgets' =>	['type' => API_OBJECTS, 'fields' => [
				'type' =>		['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'in' => implode(',', $widget_types), 'length' => DB::getFieldLength('widget', 'type')],
				'name' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('widget', 'name'), 'default' => DB::getDefault('widget', 'name')],
				'view_mode' =>	['type' => API_INT32, 'in' => implode(',', [ZBX_WIDGET_VIEW_MODE_NORMAL, ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER]), 'default' => DB::getDefault('widget', 'view_mode')],
				'x' =>			['type' => API_INT32, 'in' => '0:'.self::MAX_X, 'default' => DB::getDefault('widget', 'x')],
				'y' =>			['type' => API_INT32, 'in' => '0:'.self::MAX_Y, 'default' => DB::getDefault('widget', 'y')],
				'width' =>		['type' => API_INT32, 'in' => '1:'.DASHBOARD_MAX_COLUMNS, 'default' => DB::getDefault('widget', 'width')],
				'height' =>		['type' => API_INT32, 'in' => '2:'.DASHBOARD_WIDGET_MAX_ROWS, 'default' => DB::getDefault('widget', 'height')],
				'fields' =>		['type' => API_OBJECTS, 'fields' => [
					'type' =>		['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', $widget_field_types)],
					'name' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('widget_field', 'name'), 'default' => DB::getDefault('widget_field', 'name')],
					'value' =>		['type' => API_MULTIPLE, 'flags' => API_REQUIRED, 'rules' => [
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

		$names_by_templateid = [];
		foreach ($dashboards as $dashboard) {
			$names_by_templateid[$dashboard['templateid']][] = $dashboard['name'];
		}

		$templateids = array_keys($names_by_templateid);

		// Check template exists.
		$db_templates = API::Template()->get([
			'countOutput' => true,
			'templateids' => $templateids
		]);

		if ($db_templates != count($templateids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		$this->checkDuplicates($names_by_templateid);
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
		$widget_types = [WIDGET_CLOCK, WIDGET_GRAPH, WIDGET_GRAPH_PROTOTYPE, WIDGET_PLAIN_TEXT, WIDGET_URL];
		$ids_widget_field_types = [ZBX_WIDGET_FIELD_TYPE_ITEM, ZBX_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE,
			ZBX_WIDGET_FIELD_TYPE_GRAPH, ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE
		];
		$widget_field_types = array_merge($ids_widget_field_types,
			[ZBX_WIDGET_FIELD_TYPE_INT32, ZBX_WIDGET_FIELD_TYPE_STR]
		);

		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['dashboardid']], 'fields' => [
			'dashboardid' =>	['type' => API_ID, 'flags' => API_REQUIRED],
			'name' =>			['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('dashboard', 'name')],
			'widgets' =>		['type' => API_OBJECTS, 'fields' => [
				'widgetid' =>		['type' => API_ID],
				'type' =>			['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'in' => implode(',', $widget_types), 'length' => DB::getFieldLength('widget', 'type')],
				'name' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('widget', 'name')],
				'view_mode' =>		['type' => API_INT32, 'in' => implode(',', [ZBX_WIDGET_VIEW_MODE_NORMAL, ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER])],
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
			'output' => ['dashboardid', 'name', 'templateid'],
			'dashboardids' => array_column($dashboards, 'dashboardid'),
			'selectWidgets' => ['widgetid', 'type', 'name', 'view_mode', 'x', 'y', 'width', 'height'],
			'editable' => true,
			'preservekeys' => true
		]);

		foreach ($dashboards as $dashboard) {
			if (!array_key_exists($dashboard['dashboardid'], $db_dashboards)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}
		}

		$dashboards = $this->extendObjectsByKey($dashboards, $db_dashboards, 'dashboardid', ['templateid']);

		$api_input_rules = ['type' => API_OBJECTS, 'uniq' => [['templateid', 'name']]];
		if (!CApiInputValidator::validateUniqueness($api_input_rules, $dashboards, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$dashboards = $this->extendObjectsByKey($dashboards, $db_dashboards, 'dashboardid', ['name']);

		$names_by_templateid = [];

		$widget_defaults = [
			'name' => DB::getDefault('widget', 'name'),
			'view_mode' => DB::getDefault('widget', 'view_mode'),
			'x' => DB::getDefault('widget', 'x'),
			'y' => DB::getDefault('widget', 'y'),
			'width' => DB::getDefault('widget', 'width'),
			'height' => DB::getDefault('widget', 'height')
		];

		foreach ($dashboards as &$dashboard) {
			$db_dashboard = $db_dashboards[$dashboard['dashboardid']];

			if ($dashboard['name'] !== $db_dashboard['name']) {
				$names_by_templateid[$dashboard['templateid']][] = $dashboard['name'];
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

		if ($names_by_templateid) {
			$this->checkDuplicates($names_by_templateid);
		}
		$this->checkWidgets($dashboards);
		$this->checkWidgetFields($dashboards, __FUNCTION__);
	}

	/**
	 * Check for duplicated dashboards.
	 *
	 * @param array  $names_by_templateid
	 *
	 * @throws APIException  if dashboard already exists.
	 */
	protected function checkDuplicates(array $names_by_templateid): void {
		$where = [];
		foreach ($names_by_templateid as $templateid => $names) {
			$where[] = '('.dbConditionId('templateid', [$templateid]).' AND '.dbConditionString('name', $names).')';
		}

		if ($db_dashboard = DBfetch(DBselect('SELECT name FROM dashboard WHERE '.implode(' OR ', $where), 1))) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Dashboard "%1$s" already exists.', $db_dashboard['name']));
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
			ZBX_WIDGET_FIELD_TYPE_ITEM => 'value_itemid',
			ZBX_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE => 'value_itemid',
			ZBX_WIDGET_FIELD_TYPE_GRAPH => 'value_graphid',
			ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE => 'value_graphid',
		];
	}
}
