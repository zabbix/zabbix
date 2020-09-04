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
class CTemplateDashboard extends CApiService {

	private const MAX_X = 23; // DASHBOARD_MAX_COLUMNS - 1;
	private const MAX_Y = 62; // DASHBOARD_MAX_ROWS - 2;

	protected $tableName = 'dashboard';
	protected $tableAlias = 'd';
	protected $sortColumns = ['dashboardid', 'name'];

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
			'templateids' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'filter' =>					['type' => API_OBJECT, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => [
				'dashboardid' =>			['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'name' =>					['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'templateid' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE]
			]],
			'search' =>					['type' => API_OBJECT, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => [
				'name' =>					['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
			]],
			'searchByAny' =>			['type' => API_BOOLEAN, 'default' => false],
			'startSearch' =>			['type' => API_FLAG, 'default' => false],
			'excludeSearch' =>			['type' => API_FLAG, 'default' => false],
			'searchWildcardsEnabled' =>	['type' => API_BOOLEAN, 'default' => false],
			// output
			'output' =>					['type' => API_OUTPUT, 'in' => implode(',', ['dashboardid', 'name', 'templateid']), 'default' => API_OUTPUT_EXTEND],
			'selectWidgets' =>			['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', ['widgetid', 'type', 'name', 'view_mode', 'x', 'y', 'width', 'height', 'fields']), 'default' => null],
			'countOutput' =>			['type' => API_FLAG, 'default' => false],
			// sort and limit
			'sortfield' =>				['type' => API_STRINGS_UTF8, 'flags' => API_NORMALIZE, 'in' => implode(',', $this->sortColumns), 'uniq' => true, 'default' => []],
			'sortorder' =>				['type' => API_SORTORDER, 'default' => []],
			'limit' =>					['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => '1:'.ZBX_MAX_INT32, 'default' => null],
			// flags
			'editable' =>				['type' => API_BOOLEAN, 'default' => false],
			'preservekeys' =>			['type' => API_BOOLEAN, 'default' => false],
			'groupCount' =>				['type' => API_BOOLEAN, 'default' => false]
		]];
		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$sql_parts = [
			'select'	=> ['dashboard' => 'd.dashboardid'],
			'from'		=> ['dashboard' => 'dashboard d'],
			'where'		=> ['template' => 'd.templateid IS NOT NULL'],
			'order'		=> [],
			'group'		=> []
		];

		// permissions
		if (in_array(self::$userData['type'], [USER_TYPE_ZABBIX_USER, USER_TYPE_ZABBIX_ADMIN])) {
			if (!is_null($options['templateids'])) {
				unset($options['hostids']);

				$options['templateids'] = API::Template()->get([
					'output' => ['templateid'],
					'templateids' => $options['templateids'],
					'editable' => $options['editable'],
					'preservekeys' => true
				]);
				$options['templateids'] = array_keys($options['templateids']);
			}
			else {
				$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ;
				$user_groups = getUserGroupsByUserId(self::$userData['userid']);

				$sqlParts['where'][] = 'EXISTS ('.
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
		if ($options['templateids'] !== null) {
			$sql_parts['where'][] = dbConditionInt('d.templateid', $options['templateids']);
		}

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

		if ($db_dashboards) {
			$db_dashboards = $this->addRelatedObjects($options, $db_dashboards);
			$db_dashboards = $this->unsetExtraFields($db_dashboards, ['dashboardid'], $options['output']);
			$db_dashboards = $this->unsetExtraFields($db_dashboards, ['userid', 'private'], []);

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
	public function create(array $dashboards) {
		$this->validateCreate($dashboards);

		$ins_dashboards = [];

		foreach ($dashboards as $dashboard) {
			unset($dashboard['users'], $dashboard['userGroups'], $dashboard['widgets']);
			$ins_dashboards[] = $dashboard;
		}

		$dashboardids = DB::insert('dashboard', $ins_dashboards);

		foreach ($dashboards as $index => &$dashboard) {
			$dashboard['dashboardid'] = $dashboardids[$index];
		}
		unset($dashboard);

		$this->updateWidget($dashboards, __FUNCTION__);

		$this->addAuditBulk(AUDIT_ACTION_ADD, AUDIT_RESOURCE_TEMPLATE_DASHBOARD, $dashboards);

		return ['dashboardids' => $dashboardids];
	}

	/**
	 * @param array $dashboards
	 *
	 * @throws APIException if the input is invalid
	 */
	private function validateCreate(array &$dashboards) {
		$widget_types = [WIDGET_CLOCK, WIDGET_GRAPH, WIDGET_GRAPH_PROTOTYPE, WIDGET_PLAIN_TEXT, WIDGET_URL];
		$ids_widget_field_types = [ZBX_WIDGET_FIELD_TYPE_HOST, ZBX_WIDGET_FIELD_TYPE_ITEM,
			ZBX_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE, ZBX_WIDGET_FIELD_TYPE_GRAPH, ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE
		];
		$widget_field_types = array_merge($ids_widget_field_types,
			[ZBX_WIDGET_FIELD_TYPE_INT32, ZBX_WIDGET_FIELD_TYPE_STR]
		);

		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['name']], 'fields' => [
			'name' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('dashboard', 'name')],
			'templateid' =>			['type' => API_ID, 'flags' => API_REQUIRED | API_NOT_EMPTY],
			'widgets' =>			['type' => API_OBJECTS, 'fields' => [
				'type' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'in' => implode(',', $widget_types)],
				'name' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('widget', 'name'), 'default' => DB::getDefault('widget', 'name')],
				'view_mode' =>			['type' => API_INT32, 'in' => implode(',', [ZBX_WIDGET_VIEW_MODE_NORMAL, ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER]), 'default' => DB::getDefault('widget', 'view_mode')],
				'x' =>					['type' => API_INT32, 'in' => '0:'.self::MAX_X, 'default' => DB::getDefault('widget', 'x')],
				'y' =>					['type' => API_INT32, 'in' => '0:'.self::MAX_Y, 'default' => DB::getDefault('widget', 'y')],
				'width' =>				['type' => API_INT32, 'in' => '1:'.DASHBOARD_MAX_COLUMNS, 'default' => DB::getDefault('widget', 'width')],
				'height' =>				['type' => API_INT32, 'in' => '2:'.DASHBOARD_WIDGET_MAX_ROWS, 'default' => DB::getDefault('widget', 'height')],
				'fields' =>				['type' => API_OBJECTS, 'fields' => [
					'type' =>				['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', $widget_field_types)],
					'name' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('widget_field', 'name'), 'default' => DB::getDefault('widget_field', 'name')],
					'value' =>				['type' => API_MULTIPLE, 'flags' => API_REQUIRED, 'rules' => [
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

		$this->checkDuplicates(array_column($dashboards, 'name'), array_column($dashboards, 'templateid'));
		$this->checkWidgets($dashboards);
		$this->checkWidgetFields($dashboards, __FUNCTION__);
	}

	/**
	 * @param array $dashboards
	 *
	 * @return array
	 */
	public function update(array $dashboards) {
		$this->validateUpdate($dashboards, $db_dashboards);

		$upd_dashboards = [];

		foreach ($dashboards as $dashboard) {
			$db_dashboard = $db_dashboards[$dashboard['dashboardid']];

			$upd_dashboard = [];

			if (array_key_exists('name', $dashboard) && $dashboard['name'] !== $db_dashboard['name']) {
				$upd_dashboard['name'] = $dashboard['name'];
			}
			if (array_key_exists('templateid', $dashboard)
					&& bccomp($dashboard['templateid'], $db_dashboard['templateid']) != 0) {
				$upd_dashboard['templateid'] = $dashboard['templateid'];
			}
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

		$this->updateWidget($dashboards, __FUNCTION__, $db_dashboards);

		foreach ($db_dashboards as &$db_dashboard) {
			unset($db_dashboard['widgets']);
		}
		unset($db_dashboard);

		$this->addAuditBulk(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_TEMPLATE_DASHBOARD, $dashboards, $db_dashboards);

		return ['dashboardids' => array_column($dashboards, 'dashboardid')];
	}

	/**
	 * @param array $dashboards
	 * @param array $db_dashboards
	 *
	 * @throws APIException if the input is invalid
	 */
	private function validateUpdate(array &$dashboards, array &$db_dashboards = null) {
		$widget_types = [WIDGET_CLOCK, WIDGET_GRAPH, WIDGET_GRAPH_PROTOTYPE, WIDGET_PLAIN_TEXT, WIDGET_URL];
		$ids_widget_field_types = [ZBX_WIDGET_FIELD_TYPE_HOST, ZBX_WIDGET_FIELD_TYPE_ITEM,
			ZBX_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE, ZBX_WIDGET_FIELD_TYPE_GRAPH, ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE
		];
		$widget_field_types = array_merge($ids_widget_field_types,
			[ZBX_WIDGET_FIELD_TYPE_INT32, ZBX_WIDGET_FIELD_TYPE_STR]
		);

		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['dashboardid'], ['name']], 'fields' => [
			'dashboardid' =>		['type' => API_ID, 'flags' => API_REQUIRED],
			'name' =>				['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('dashboard', 'name')],
			'templateid' =>			['type' => API_ID],
			'widgets' =>			['type' => API_OBJECTS, 'fields' => [
				'widgetid' =>			['type' => API_ID],
				'type' =>				['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'in' => implode(',', $widget_types), 'length' => DB::getFieldLength('widget', 'type')],
				'name' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('widget', 'name')],
				'view_mode' =>			['type' => API_INT32, 'in' => implode(',', [ZBX_WIDGET_VIEW_MODE_NORMAL, ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER]), 'default' => DB::getDefault('widget', 'view_mode')],
				'x' =>					['type' => API_INT32, 'in' => '0:'.self::MAX_X],
				'y' =>					['type' => API_INT32, 'in' => '0:'.self::MAX_Y],
				'width' =>				['type' => API_INT32, 'in' => '1:'.DASHBOARD_MAX_COLUMNS],
				'height' =>				['type' => API_INT32, 'in' => '2:'.DASHBOARD_WIDGET_MAX_ROWS],
				'fields' =>				['type' => API_OBJECTS, 'fields' => [
					'type' =>				['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', $widget_field_types)],
					'name' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('widget_field', 'name'), 'default' => DB::getDefault('widget_field', 'name')],
					'value' =>				['type' => API_MULTIPLE, 'flags' => API_REQUIRED, 'rules' => [
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

		$dashboards = $this->extendObjectsByKey($dashboards, $db_dashboards, 'dashboardid', ['name']);

		$names = [];
		$templateids = [];

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
				$templateids[] = $dashboard['templateid'];
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
			$this->checkDuplicates($names, $templateids);
		}
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
	private function checkDuplicates(array $names, array $templateids) {
		$db_dashboards = DB::select('dashboard', [
			'output' => ['name'],
			'filter' => ['name' => $names, 'templateid' => $templateids],
			'limit' => 1
		]);

		if ($db_dashboards) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Dashboard "%1$s" already exists.', $db_dashboards[0]['name']) // FIXME: fix message
			);
		}
	}

	/**
	 * Check duplicates widgets in one cell.
	 *
	 * @param array  $dashboards
	 * @param string $dashboards[]['name']
	 * @param array  $dashboards[]['widgets']              (optional)
	 * @param int    $dashboards[]['widgets'][]['x']
	 * @param int    $dashboards[]['widgets'][]['y']
	 * @param int    $dashboards[]['widgets'][]['width']
	 * @param int    $dashboards[]['widgets'][]['height']
	 *
	 * @throws APIException if input is invalid.
	 */
	private function checkWidgets(array $dashboards) {
		foreach ($dashboards as $dashboard) {
			if (array_key_exists('widgets', $dashboard)) {
				$filled = [];

				foreach ($dashboard['widgets'] as $widget) {
					for ($x = $widget['x']; $x < $widget['x'] + $widget['width']; $x++) {
						for ($y = $widget['y']; $y < $widget['y'] + $widget['height']; $y++) {
							if (array_key_exists($x, $filled) && array_key_exists($y, $filled[$x])) {
								self::exception(ZBX_API_ERROR_PARAMETERS,
									_s('Dashboard "%1$s" cell X - %2$s Y - %3$s is already taken.',
										$dashboard['name'], $widget['x'], $widget['y']
									)
								);
							}

							$filled[$x][$y] = true;
						}
					}

					if ($widget['x'] + $widget['width'] > DASHBOARD_MAX_COLUMNS
							|| $widget['y'] + $widget['height'] > DASHBOARD_MAX_ROWS) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Dashboard "%1$s" widget in cell X - %2$s Y - %3$s is out of bounds.',
								$dashboard['name'], $widget['x'], $widget['y']
							)
						);
					}
				}
			}
		}
	}

	/**
	 * Returns widget field name by field type.
	 *
	 * @return array
	 */
	private static function getFieldNamesByType() {
		return [
			ZBX_WIDGET_FIELD_TYPE_INT32 => 'value_int',
			ZBX_WIDGET_FIELD_TYPE_STR => 'value_str',
			ZBX_WIDGET_FIELD_TYPE_HOST => 'value_hostid',
			ZBX_WIDGET_FIELD_TYPE_ITEM => 'value_itemid',
			ZBX_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE => 'value_itemid',
			ZBX_WIDGET_FIELD_TYPE_GRAPH => 'value_graphid',
			ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE => 'value_graphid',
		];
	}

	/**
	 * Check widget fields.
	 *
	 * @param array  $dashboards
	 * @param string $dashboards[]['name']
	 * @param array  $dashboards[]['widgets']
	 * @param string $dashboards[]['widgets'][]['widgetid']  (optional)
	 * @param array  $dashboards[]['widgets'][]['fields']
	 * @param int    $dashboards[]['widgets'][]['type']
	 * @param mixed  $dashboards[]['widgets'][]['value']
	 * @param string $method
	 *
	 * @throws APIException if input is invalid.
	 */
	private function checkWidgetFields(array $dashboards, $method) {
		$widget_fields = [];

		if ($method === 'validateUpdate') {
			$widgetids = [];

			foreach ($dashboards as $dashboard) {
				if (array_key_exists('widgets', $dashboard)) {
					foreach ($dashboard['widgets'] as $widget) {
						if (array_key_exists('widgetid', $widget)) {
							$widgetids[] = $widget['widgetid'];
						}
					}
				}
			}

			if ($widgetids) {
				$db_widget_fields = DB::select('widget_field', [
					'output' => ['widgetid', 'type', 'value_hostid', 'value_itemid', 'value_graphid'],
					'filter' => [
						'widgetid' => $widgetids,
						'type' => [ZBX_WIDGET_FIELD_TYPE_HOST, ZBX_WIDGET_FIELD_TYPE_ITEM,
							ZBX_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE, ZBX_WIDGET_FIELD_TYPE_GRAPH,
							ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE
						]
					]
				]);

				$field_names_by_type = self::getFieldNamesByType();

				foreach ($db_widget_fields as $db_widget_field) {
					$widgetid = $db_widget_field['widgetid'];
					$type = $db_widget_field['type'];
					$value = $db_widget_field[$field_names_by_type[$db_widget_field['type']]];

					$widget_fields[$widgetid][$type][$value] = true;
				}
			}
		}

		$ids = [
			ZBX_WIDGET_FIELD_TYPE_HOST => [],
			ZBX_WIDGET_FIELD_TYPE_ITEM => [],
			ZBX_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE => [],
			ZBX_WIDGET_FIELD_TYPE_GRAPH => [],
			ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE => [],
		];

		foreach ($dashboards as $dashboard) {
			if (array_key_exists('widgets', $dashboard)) {
				foreach ($dashboard['widgets'] as $widget) {
					$widgetid = array_key_exists('widgetid', $widget) ? $widget['widgetid'] : 0;

					if (array_key_exists('fields', $widget)) {
						foreach ($widget['fields'] as $field) {
							if ($widgetid == 0 || !array_key_exists($widgetid, $widget_fields)
									|| !array_key_exists($field['type'], $widget_fields[$widgetid])
									|| !array_key_exists($field['value'], $widget_fields[$widgetid][$field['type']])) {
								$ids[$field['type']][$field['value']] = true;
							}
						}
					}
				}
			}
		}

		if ($ids[ZBX_WIDGET_FIELD_TYPE_HOST]) {
			$hostids = array_keys($ids[ZBX_WIDGET_FIELD_TYPE_HOST]);

			$db_hosts = API::Host()->get([
				'output' => [],
				'hostids' => $hostids,
				'preservekeys' => true
			]);

			foreach ($hostids as $hostid) {
				if (!array_key_exists($hostid, $db_hosts)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Host with ID "%1$s" is not available.', $hostid));
				}
			}
		}

		if ($ids[ZBX_WIDGET_FIELD_TYPE_ITEM]) {
			$itemids = array_keys($ids[ZBX_WIDGET_FIELD_TYPE_ITEM]);

			$db_items = API::Item()->get([
				'output' => [],
				'itemids' => $itemids,
				'webitems' => true,
				'preservekeys' => true
			]);

			foreach ($itemids as $itemid) {
				if (!array_key_exists($itemid, $db_items)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Item with ID "%1$s" is not available.', $itemid));
				}
			}
		}

		if ($ids[ZBX_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE]) {
			$item_prototypeids = array_keys($ids[ZBX_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE]);

			$db_item_prototypes = API::ItemPrototype()->get([
				'output' => [],
				'itemids' => $item_prototypeids,
				'preservekeys' => true
			]);

			foreach ($item_prototypeids as $item_prototypeid) {
				if (!array_key_exists($item_prototypeid, $db_item_prototypes)) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Item prototype with ID "%1$s" is not available.', $item_prototypeid)
					);
				}
			}
		}

		if ($ids[ZBX_WIDGET_FIELD_TYPE_GRAPH]) {
			$graphids = array_keys($ids[ZBX_WIDGET_FIELD_TYPE_GRAPH]);

			$db_graphs = API::Graph()->get([
				'output' => [],
				'graphids' => $graphids,
				'preservekeys' => true
			]);

			foreach ($graphids as $graphid) {
				if (!array_key_exists($graphid, $db_graphs)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Graph with ID "%1$s" is not available.', $graphid));
				}
			}
		}

		if ($ids[ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE]) {
			$graph_prototypeids = array_keys($ids[ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE]);

			$db_graph_prototypes = API::GraphPrototype()->get([
				'output' => [],
				'graphids' => $graph_prototypeids,
				'preservekeys' => true
			]);

			foreach ($graph_prototypeids as $graph_prototypeid) {
				if (!array_key_exists($graph_prototypeid, $db_graph_prototypes)) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Graph prototype with ID "%1$s" is not available.', $graph_prototypeid)
					);
				}
			}
		}
	}

	/**
	 * Update table "widget".
	 *
	 * @param array  $dashboards
	 * @param string $method
	 * @param array  $db_dashboards
	 */
	private function updateWidget(array $dashboards, $method, array $db_dashboards = null) {
		$db_widgets = [];

		if ($db_dashboards !== null) {
			foreach ($dashboards as $dashboard) {
				if (array_key_exists('widgets', $dashboard)) {
					$db_widgets += zbx_toHash($db_dashboards[$dashboard['dashboardid']]['widgets'], 'widgetid');
				}
			}
		}

		$ins_widgets = [];
		$upd_widgets = [];

		$field_names = [
			'str' => ['type', 'name'],
			'int' => ['view_mode', 'x', 'y', 'width', 'height']
		];

		foreach ($dashboards as $dashboard) {
			if (array_key_exists('widgets', $dashboard)) {
				foreach ($dashboard['widgets'] as $widget) {
					if (array_key_exists('widgetid', $widget)) {
						$db_widget = $db_widgets[$widget['widgetid']];
						unset($db_widgets[$widget['widgetid']]);

						$upd_widget = [];

						foreach ($field_names['str'] as $field_name) {
							if (array_key_exists($field_name, $widget)) {
								if ($widget[$field_name] !== $db_widget[$field_name]) {
									$upd_widget[$field_name] = $widget[$field_name];
								}
							}
						}
						foreach ($field_names['int'] as $field_name) {
							if (array_key_exists($field_name, $widget)) {
								if ($widget[$field_name] != $db_widget[$field_name]) {
									$upd_widget[$field_name] = $widget[$field_name];
								}
							}
						}

						if ($upd_widget) {
							$upd_widgets[] = [
								'values' => $upd_widget,
								'where' => ['widgetid' => $db_widget['widgetid']]
							];
						}
					}
					else {
						$ins_widgets[] = ['dashboardid' => $dashboard['dashboardid']] + $widget;
					}
				}
			}
		}

		if ($ins_widgets) {
			$widgetids = DB::insert('widget', $ins_widgets);
			$index = 0;

			foreach ($dashboards as &$dashboard) {
				if (array_key_exists('widgets', $dashboard)) {
					foreach ($dashboard['widgets'] as &$widget) {
						if (!array_key_exists('widgetid', $widget)) {
							$widget['widgetid'] = $widgetids[$index++];
						}
					}
					unset($widget);
				}
			}
			unset($dashboard);
		}

		if ($upd_widgets) {
			DB::update('widget', $upd_widgets);
		}

		if ($db_widgets) {
			self::deleteWidgets(array_keys($db_widgets));
		}

		$this->updateWidgetField($dashboards, $method);
	}

	/**
	 * Delete widgets.
	 *
	 * @static
	 *
	 * @param array  $widgetids
	 */
	private static function deleteWidgets(array $widgetids) {
		DB::delete('profiles', [
			'idx' => 'web.dashbrd.widget.rf_rate',
			'idx2' => $widgetids
		]);

		DB::delete('widget', ['widgetid' => $widgetids]);
	}

	/**
	 * Update table "widget_field".
	 *
	 * @param array  $dashboards
	 * @param array  $dashboards[]['widgets']              (optional)
	 * @param array  $dashboards[]['widgets'][]['fields']  (optional)
	 * @param string $method
	 */
	private function updateWidgetField(array $dashboards, $method) {
		$widgets_fields = [];
		$field_names_by_type = self::getFieldNamesByType();
		$def_values = [];
		foreach ($field_names_by_type as $field_name) {
			$def_values[$field_name] = DB::getDefault('widget_field', $field_name);
		}

		foreach ($dashboards as $dashboard) {
			if (array_key_exists('widgets', $dashboard)) {
				foreach ($dashboard['widgets'] as $widget) {
					if (array_key_exists('fields', $widget)) {
						CArrayHelper::sort($widget['fields'], ['type', 'name']);
						$widgets_fields[$widget['widgetid']] = $widget['fields'];
					}
				}
			}
		}

		foreach ($widgets_fields as &$widget_fields) {
			foreach ($widget_fields as &$widget_field) {
				$widget_field[$field_names_by_type[$widget_field['type']]] = $widget_field['value'];
				unset($widget_field['value']);
				$widget_field += $def_values;
			}
			unset($widget_field);
		}
		unset($widget_fields);

		$db_widget_fields = ($method === 'update')
			? DB::select('widget_field', [
				'output' => ['widget_fieldid', 'widgetid', 'type', 'name', 'value_int', 'value_str', 'value_hostid',
					'value_itemid', 'value_graphid'
				],
				'filter' => ['widgetid' => array_keys($widgets_fields)],
				'sortfield' => ['widgetid', 'type', 'name']
			])
			: [];

		$ins_widget_fields = [];
		$upd_widget_fields = [];
		$del_widget_fieldids = [];

		$field_names = [
			'str' => ['name', 'value_str'],
			'int' => ['type', 'value_int'],
			'ids' => ['value_hostid', 'value_itemid', 'value_graphid']
		];

		foreach ($db_widget_fields as $db_widget_field) {
			if ($widgets_fields[$db_widget_field['widgetid']]) {
				$widget_field = array_shift($widgets_fields[$db_widget_field['widgetid']]);

				$upd_widget_field = [];

				foreach ($field_names['str'] as $field_name) {
					if (array_key_exists($field_name, $widget_field)) {
						if ($widget_field[$field_name] !== $db_widget_field[$field_name]) {
							$upd_widget_field[$field_name] = $widget_field[$field_name];
						}
					}
				}
				foreach ($field_names['int'] as $field_name) {
					if (array_key_exists($field_name, $widget_field)) {
						if ($widget_field[$field_name] != $db_widget_field[$field_name]) {
							$upd_widget_field[$field_name] = $widget_field[$field_name];
						}
					}
				}
				foreach ($field_names['ids'] as $field_name) {
					if (array_key_exists($field_name, $widget_field)) {
						if (bccomp($widget_field[$field_name], $db_widget_field[$field_name]) != 0) {
							$upd_widget_field[$field_name] = $widget_field[$field_name];
						}
					}
				}

				if ($upd_widget_field) {
					$upd_widget_fields[] = [
						'values' => $upd_widget_field,
						'where' => ['widget_fieldid' => $db_widget_field['widget_fieldid']]
					];
				}
			}
			else {
				$del_widget_fieldids[] = $db_widget_field['widget_fieldid'];
			}
		}

		foreach ($widgets_fields as $widgetid => $widget_fields) {
			foreach ($widget_fields as $widget_field) {
				$ins_widget_fields[] = ['widgetid' => $widgetid] + $widget_field;
			}
		}

		if ($ins_widget_fields) {
			DB::insert('widget_field', $ins_widget_fields);
		}

		if ($upd_widget_fields) {
			DB::update('widget_field', $upd_widget_fields);
		}

		if ($del_widget_fieldids) {
			DB::delete('widget_field', ['widget_fieldid' => $del_widget_fieldids]);
		}
	}

	/**
	 * @param array $dashboardids
	 *
	 * @return array
	 */
	public function delete(array $dashboardids) {
		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];
		if (!CApiInputValidator::validate($api_input_rules, $dashboardids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_dashboards = $this->get([
			'output' => ['dashboardid', 'name'],
			'selectWidgets' => ['widgetid'],
			'dashboardids' => $dashboardids,
			'editable' => true,
			'preservekeys' => true
		]);

		$widgetids = [];

		foreach ($dashboardids as $dashboardid) {
			if (!array_key_exists($dashboardid, $db_dashboards)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}

			$widgetids = array_merge($widgetids, array_column($db_dashboards[$dashboardid]['widgets'], 'widgetid'));
		}

		if ($widgetids) {
			self::deleteWidgets($widgetids);
		}

		DB::delete('dashboard', ['dashboardid' => $dashboardids]);

		$this->addAuditBulk(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_TEMPLATE_DASHBOARD, $db_dashboards);

		return ['dashboardids' => $dashboardids];
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$dashboardids = array_keys($result);

		// Adding widgets.
		if ($options['selectWidgets'] !== null) {
			$fields_requested = $this->outputIsRequested('fields', $options['selectWidgets']);
			if ($fields_requested && is_array($options['selectWidgets'])) {
				$key = array_search('fields', $options['selectWidgets']);
				unset($options['selectWidgets'][$key]);
			}

			$db_widgets = API::getApiService()->select('widget', [
				'output' => $this->outputExtend($options['selectWidgets'], ['widgetid', 'dashboardid']),
				'filter' => ['dashboardid' => $dashboardids],
				'preservekeys' => true
			]);

			if ($db_widgets && $fields_requested) {
				foreach ($db_widgets as &$db_widget) {
					$db_widget['fields'] = [];
				}
				unset($db_widget);

				$db_widget_fields = DB::select('widget_field', [
					'output' => ['widgetid', 'type', 'name', 'value_int', 'value_str', 'value_groupid', 'value_hostid',
						'value_itemid', 'value_graphid', 'value_sysmapid'
					],
					'filter' => ['widgetid' => array_keys($db_widgets)]
				]);

				$field_names_by_type = self::getFieldNamesByType();

				foreach ($db_widget_fields as $db_widget_field) {
					$db_widgets[$db_widget_field['widgetid']]['fields'][] = [
						'type' => $db_widget_field['type'],
						'name' => $db_widget_field['name'],
						'value' => $db_widget_field[$field_names_by_type[$db_widget_field['type']]]

					];
				}
			}

			foreach ($result as &$row) {
				$row['widgets'] = [];
			}
			unset($row);

			$db_widgets = $this->unsetExtraFields($db_widgets, ['widgetid'], $options['selectWidgets']);

			foreach ($db_widgets as $db_widget) {
				$dashboardid = $db_widget['dashboardid'];
				unset($db_widget['dashboardid']);

				$result[$dashboardid]['widgets'][] = $db_widget;
			}
		}

		return $result;
	}
}
