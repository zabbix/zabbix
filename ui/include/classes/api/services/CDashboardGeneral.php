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
 * Common class for dashboards API and template dashboards API.
 */
abstract class CDashboardGeneral extends CApiService {

	protected const WIDGET_FIELD_TYPE_COLUMNS_FK = [
		ZBX_WIDGET_FIELD_TYPE_GROUP => 'value_groupid',
		ZBX_WIDGET_FIELD_TYPE_HOST => 'value_hostid',
		ZBX_WIDGET_FIELD_TYPE_ITEM => 'value_itemid',
		ZBX_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE => 'value_itemid',
		ZBX_WIDGET_FIELD_TYPE_GRAPH => 'value_graphid',
		ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE => 'value_graphid',
		ZBX_WIDGET_FIELD_TYPE_MAP => 'value_sysmapid',
		ZBX_WIDGET_FIELD_TYPE_SERVICE => 'value_serviceid',
		ZBX_WIDGET_FIELD_TYPE_SLA => 'value_slaid',
		ZBX_WIDGET_FIELD_TYPE_USER => 'value_userid',
		ZBX_WIDGET_FIELD_TYPE_ACTION => 'value_actionid',
		ZBX_WIDGET_FIELD_TYPE_MEDIA_TYPE => 'value_mediatypeid'
	];

	protected const WIDGET_FIELD_TYPE_COLUMNS = [
		ZBX_WIDGET_FIELD_TYPE_INT32 => 'value_int',
		ZBX_WIDGET_FIELD_TYPE_STR => 'value_str'
	] + self::WIDGET_FIELD_TYPE_COLUMNS_FK;

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
	abstract public function get(array $options = []);

	/**
	 * @param array $dashboardids
	 *
	 * @throws APIException if the input is invalid.
	 *
	 * @return array
	 */
	public function delete(array $dashboardids): array {
		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];

		if (!CApiInputValidator::validate($api_input_rules, $dashboardids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_dashboards = $this->get([
			'output' => ['dashboardid', 'name'],
			'dashboardids' => $dashboardids,
			'editable' => true,
			'preservekeys' => true
		]);

		if (count($db_dashboards) != count($dashboardids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		// Check if dashboards are used in scheduled reports.
		if ($this instanceof CDashboard) {
			$db_reports = DB::select('report', [
				'output' => ['name', 'dashboardid'],
				'filter' => ['dashboardid' => $dashboardids],
				'limit' => 1
			]);

			if ($db_reports) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Dashboard "%1$s" is used in report "%2$s".',
					$db_dashboards[$db_reports[0]['dashboardid']]['name'], $db_reports[0]['name']
				));
			}
		}

		$db_dashboard_pages = DB::select('dashboard_page', [
			'output' => [],
			'filter' => ['dashboardid' => $dashboardids],
			'preservekeys' => true
		]);

		if ($db_dashboard_pages) {
			$db_widgets = DB::select('widget', [
				'output' => [],
				'filter' => ['dashboard_pageid' => array_keys($db_dashboard_pages)],
				'preservekeys' => true
			]);

			if ($db_widgets) {
				self::deleteWidgets(array_keys($db_widgets));
			}

			DB::delete('dashboard_page', ['dashboard_pageid' => array_keys($db_dashboard_pages)]);
		}

		DB::delete('dashboard', ['dashboardid' => $dashboardids]);

		$resource = ($this instanceof CDashboard) ? CAudit::RESOURCE_DASHBOARD : CAudit::RESOURCE_TEMPLATE_DASHBOARD;
		self::addAuditLog(CAudit::ACTION_DELETE, $resource, $db_dashboards);

		return ['dashboardids' => $dashboardids];
	}

	/**
	 * Add the existing users, user groups, pages, widgets and widget fields to $db_dashboards whether
	 * these are affected by the update.
	 *
	 * @param array $dashboards
	 * @param array $db_dashboards
	 */
	protected function addAffectedObjects(array $dashboards, array &$db_dashboards): void {
		// Select pages, users and user groups of these dashboards.
		$dashboardids = ['pages' => [], 'users' => [], 'userGroups' => []];

		// Select widgets of these pages.
		$dashboard_pageids = [];

		// Select fields of these widgets.
		$widgetids = [];

		foreach ($dashboards as $dashboard) {
			if (array_key_exists('users', $dashboard)) {
				$dashboardids['users'][] = $dashboard['dashboardid'];
				$db_dashboards[$dashboard['dashboardid']]['users'] = [];
			}

			if (array_key_exists('userGroups', $dashboard)) {
				$dashboardids['userGroups'][] = $dashboard['dashboardid'];
				$db_dashboards[$dashboard['dashboardid']]['userGroups'] = [];
			}

			if (array_key_exists('pages', $dashboard)) {
				$dashboardids['pages'][] = $dashboard['dashboardid'];
				$db_dashboards[$dashboard['dashboardid']]['pages'] = [];

				foreach ($dashboard['pages'] as $dashboard_page) {
					if (array_key_exists('dashboard_pageid', $dashboard_page)
							&& array_key_exists('widgets', $dashboard_page)) {
						$dashboard_pageids[] = $dashboard_page['dashboard_pageid'];

						foreach ($dashboard_page['widgets'] as $widget) {
							if (array_key_exists('widgetid', $widget) && array_key_exists('fields', $widget)) {
								$widgetids[] = $widget['widgetid'];
							}
						}
					}
				}
			}
		}

		if ($dashboardids['users']) {
			$options = [
				'output' => ['dashboard_userid', 'dashboardid', 'userid', 'permission'],
				'filter' => ['dashboardid' => $dashboardids['users']]
			];
			$db_users = DBselect(DB::makeSql('dashboard_user', $options));

			while ($db_user = DBfetch($db_users)) {
				$db_dashboards[$db_user['dashboardid']]['users'][$db_user['dashboard_userid']] =
					array_diff_key($db_user, array_flip(['dashboardid']));
			}
		}

		if ($dashboardids['userGroups']) {
			$options = [
				'output' => ['dashboard_usrgrpid', 'dashboardid', 'usrgrpid', 'permission'],
				'filter' => ['dashboardid' => $dashboardids['userGroups']]
			];
			$db_groups = DBselect(DB::makeSql('dashboard_usrgrp', $options));

			while ($db_group = DBfetch($db_groups)) {
				$db_dashboards[$db_group['dashboardid']]['userGroups'][$db_group['dashboard_usrgrpid']] =
					array_diff_key($db_group, array_flip(['dashboardid']));
			}
		}

		if ($dashboardids['pages']) {
			$db_dashboard_pages = DB::select('dashboard_page', [
				'output' => ['dashboard_pageid', 'dashboardid', 'name', 'display_period', 'sortorder'],
				'filter' => ['dashboardid' => $dashboardids['pages']],
				'preservekeys' => true
			]);

			foreach ($db_dashboard_pages as &$db_dashboard_page) {
				$db_dashboard_page['widgets'] = [];
			}
			unset($db_dashboard_page);

			if ($dashboard_pageids) {
				$db_widgets = DB::select('widget', [
					'output' => ['widgetid', 'dashboard_pageid', 'type', 'name', 'x', 'y', 'width', 'height',
						'view_mode'
					],
					'filter' => ['dashboard_pageid' => $dashboard_pageids],
					'preservekeys' => true
				]);

				foreach ($db_widgets as &$db_widget) {
					$db_widget['fields'] = [];
				}
				unset($db_widget);

				if ($widgetids) {
					$options = [
						'output' => ['widget_fieldid', 'widgetid', 'type', 'name', 'value_int', 'value_str',
							'value_groupid', 'value_hostid', 'value_itemid', 'value_graphid', 'value_serviceid',
							'value_slaid', 'value_userid', 'value_actionid', 'value_mediatypeid', 'value_sysmapid'
						],
						'filter' => ['widgetid' => $widgetids]
					];
					$db_widget_fields = DBselect(DB::makeSql('widget_field', $options));

					while ($db_widget_field = DBfetch($db_widget_fields)) {
						$db_widgets[$db_widget_field['widgetid']]['fields'][$db_widget_field['widget_fieldid']] = [
							'widget_fieldid' => $db_widget_field['widget_fieldid'],
							'type' => $db_widget_field['type'],
							'name' => $db_widget_field['name'],
							'value' => $db_widget_field[self::WIDGET_FIELD_TYPE_COLUMNS[$db_widget_field['type']]]
						];
					}
				}

				foreach ($db_widgets as $widgetid => $db_widget) {
					$db_dashboard_pages[$db_widget['dashboard_pageid']]['widgets'][$widgetid] =
						array_diff_key($db_widget, array_flip(['dashboard_pageid']));
				}
			}

			foreach ($db_dashboard_pages as $dashboard_pageid => $db_dashboard_page) {
				$db_dashboards[$db_dashboard_page['dashboardid']]['pages'][$dashboard_pageid] =
					array_diff_key($db_dashboard_page, array_flip(['dashboardid']));
			}
		}
	}

	/**
	 * Check ownership of the referenced pages and widgets.
	 *
	 * @param array $dashboards
	 * @param array $db_dashboards
	 *
	 * @throws APIException.
	 */
	protected function checkReferences(array $dashboards, array $db_dashboards): void {
		foreach ($dashboards as $dashboard) {
			if (!array_key_exists('pages', $dashboard)) {
				continue;
			}

			$db_dashboard_pages = $db_dashboards[$dashboard['dashboardid']]['pages'];

			foreach ($dashboard['pages'] as $dashboard_page) {
				if (array_key_exists('dashboard_pageid', $dashboard_page)
						&& !array_key_exists($dashboard_page['dashboard_pageid'], $db_dashboard_pages)) {
					self::exception(ZBX_API_ERROR_PERMISSIONS,
						_('No permissions to referred object or it does not exist!')
					);
				}

				if (!array_key_exists('widgets', $dashboard_page)) {
					continue;
				}

				$db_widgets = array_key_exists('dashboard_pageid', $dashboard_page)
					? $db_dashboard_pages[$dashboard_page['dashboard_pageid']]['widgets']
					: [];

				foreach ($dashboard_page['widgets'] as $widget) {
					if (array_key_exists('widgetid', $widget) && !array_key_exists($widget['widgetid'], $db_widgets)) {
						self::exception(ZBX_API_ERROR_PERMISSIONS,
							_('No permissions to referred object or it does not exist!')
						);
					}
				}
			}
		}
	}

	/**
	 * Check widgets.
	 *
	 * Note: For any object with ID in $dashboards a corresponding object in $db_dashboards must exist.
	 *
	 * @param array      $dashboards
	 * @param array|null $db_dashboards
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function checkWidgets(array $dashboards, ?array $db_dashboards = null): void {
		$widget_defaults = DB::getDefaults('widget');

		foreach ($dashboards as $dashboard) {
			if (!array_key_exists('pages', $dashboard)) {
				continue;
			}

			$db_dashboard_pages = ($db_dashboards !== null) ? $db_dashboards[$dashboard['dashboardid']]['pages'] : null;

			foreach ($dashboard['pages'] as $index => $dashboard_page) {
				if (!array_key_exists('widgets', $dashboard_page)) {
					continue;
				}

				$filled = [];

				foreach ($dashboard_page['widgets'] as $widget) {
					$widget += array_key_exists('widgetid', $widget)
						? $db_dashboard_pages[$dashboard_page['dashboard_pageid']]['widgets'][$widget['widgetid']]
						: $widget_defaults;

					for ($x = $widget['x']; $x < $widget['x'] + $widget['width']; $x++) {
						for ($y = $widget['y']; $y < $widget['y'] + $widget['height']; $y++) {
							if (array_key_exists($x, $filled) && array_key_exists($y, $filled[$x])) {
								self::exception(ZBX_API_ERROR_PARAMETERS,
									_s('Overlapping widgets at X:%3$d, Y:%4$d on page #%2$d of dashboard "%1$s".',
										$dashboard['name'], $index + 1, $widget['x'], $widget['y']
									)
								);
							}

							$filled[$x][$y] = true;
						}
					}

					if ($widget['x'] + $widget['width'] > DASHBOARD_MAX_COLUMNS
							|| $widget['y'] + $widget['height'] > DASHBOARD_MAX_ROWS) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Widget at X:%3$d, Y:%4$d on page #%2$d of dashboard "%1$s" is out of bounds.',
								$dashboard['name'], $index + 1, $widget['x'], $widget['y']
							)
						);
					}
				}
			}
		}
	}

	/**
	 * Check widget fields.
	 *
	 * Note: For any object with ID in $dashboards a corresponding object in $db_dashboards must exist.
	 *
	 * @param array      $dashboards
	 * @param array|null $db_dashboards
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function checkWidgetFields(array $dashboards, ?array $db_dashboards = null): void {
		$ids = [
			ZBX_WIDGET_FIELD_TYPE_ITEM => [],
			ZBX_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE => [],
			ZBX_WIDGET_FIELD_TYPE_GRAPH => [],
			ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE => [],
			ZBX_WIDGET_FIELD_TYPE_GROUP => [],
			ZBX_WIDGET_FIELD_TYPE_HOST => [],
			ZBX_WIDGET_FIELD_TYPE_MAP => [],
			ZBX_WIDGET_FIELD_TYPE_SERVICE => [],
			ZBX_WIDGET_FIELD_TYPE_SLA => [],
			ZBX_WIDGET_FIELD_TYPE_USER => [],
			ZBX_WIDGET_FIELD_TYPE_ACTION => [],
			ZBX_WIDGET_FIELD_TYPE_MEDIA_TYPE => []
		];

		foreach ($dashboards as $dashboard) {
			if (!array_key_exists('pages', $dashboard)) {
				continue;
			}

			$db_dashboard_pages = ($db_dashboards !== null) ? $db_dashboards[$dashboard['dashboardid']]['pages'] : null;

			foreach ($dashboard['pages'] as $dashboard_page) {
				if (!array_key_exists('widgets', $dashboard_page)) {
					continue;
				}

				foreach ($dashboard_page['widgets'] as $widget) {
					if (!array_key_exists('fields', $widget)) {
						continue;
					}

					$widgetid = array_key_exists('widgetid', $widget) ? $widget['widgetid'] : null;

					// Skip testing linked object availability of already stored widget fields.
					$stored_widget_fields = [];

					if ($widgetid !== null) {
						$db_widget = $db_dashboard_pages[$dashboard_page['dashboard_pageid']]['widgets'][$widgetid];

						foreach ($db_widget['fields'] as $db_widget_field) {
							if (array_key_exists($db_widget_field['type'], $ids)) {
								$stored_widget_fields[$db_widget_field['type']][$db_widget_field['value']] = true;
							}
						}
					}

					foreach ($widget['fields'] as $widget_field) {
						if (array_key_exists($widget_field['type'], $ids)) {
							if ($widgetid === null
									|| !array_key_exists($widget_field['type'], $stored_widget_fields)
									|| !array_key_exists($widget_field['value'],
										$stored_widget_fields[$widget_field['type']]
									)) {
								if ($this instanceof CTemplateDashboard) {
									$ids[$widget_field['type']][$widget_field['value']][$dashboard['templateid']] =
										true;
								}
								else {
									$ids[$widget_field['type']][$widget_field['value']] = true;
								}
							}
						}
					}
				}
			}
		}

		if ($ids[ZBX_WIDGET_FIELD_TYPE_ITEM]) {
			$itemids = array_keys($ids[ZBX_WIDGET_FIELD_TYPE_ITEM]);

			$db_items = API::Item()->get([
				'output' => ($this instanceof CTemplateDashboard) ? ['hostid'] : [],
				'itemids' => $itemids,
				'webitems' => true,
				'preservekeys' => true
			]);

			foreach ($itemids as $itemid) {
				if (!array_key_exists($itemid, $db_items)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Item with ID "%1$s" is not available.', $itemid));
				}

				if ($this instanceof CTemplateDashboard) {
					foreach (array_keys($ids[ZBX_WIDGET_FIELD_TYPE_ITEM][$itemid]) as $templateid) {
						if ($db_items[$itemid]['hostid'] != $templateid) {
							self::exception(ZBX_API_ERROR_PARAMETERS,
								_s('Item with ID "%1$s" is not available.', $itemid)
							);
						}
					}
				}
			}
		}

		if ($ids[ZBX_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE]) {
			$item_prototypeids = array_keys($ids[ZBX_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE]);

			$db_item_prototypes = API::ItemPrototype()->get([
				'output' => ($this instanceof CTemplateDashboard) ? ['hostid'] : [],
				'itemids' => $item_prototypeids,
				'preservekeys' => true
			]);

			foreach ($item_prototypeids as $item_prototypeid) {
				if (!array_key_exists($item_prototypeid, $db_item_prototypes)) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Item prototype with ID "%1$s" is not available.', $item_prototypeid)
					);
				}

				if ($this instanceof CTemplateDashboard) {
					foreach (array_keys($ids[ZBX_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE][$item_prototypeid]) as $templateid) {
						if ($db_item_prototypes[$item_prototypeid]['hostid'] != $templateid) {
							self::exception(ZBX_API_ERROR_PARAMETERS,
								_s('Item prototype with ID "%1$s" is not available.', $item_prototypeid)
							);
						}
					}
				}
			}
		}

		if ($ids[ZBX_WIDGET_FIELD_TYPE_GRAPH]) {
			$graphids = array_keys($ids[ZBX_WIDGET_FIELD_TYPE_GRAPH]);

			$db_graphs = API::Graph()->get([
				'output' => [],
				'selectHosts' => ($this instanceof CTemplateDashboard) ? ['hostid'] : null,
				'graphids' => $graphids,
				'preservekeys' => true
			]);

			foreach ($graphids as $graphid) {
				if (!array_key_exists($graphid, $db_graphs)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Graph with ID "%1$s" is not available.', $graphid));
				}

				if ($this instanceof CTemplateDashboard) {
					foreach (array_keys($ids[ZBX_WIDGET_FIELD_TYPE_GRAPH][$graphid]) as $templateid) {
						if (!in_array($templateid, array_column($db_graphs[$graphid]['hosts'], 'hostid'))) {
							self::exception(ZBX_API_ERROR_PARAMETERS,
								_s('Graph with ID "%1$s" is not available.', $graphid)
							);
						}
					}
				}
			}
		}

		if ($ids[ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE]) {
			$graph_prototypeids = array_keys($ids[ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE]);

			$db_graph_prototypes = API::GraphPrototype()->get([
				'output' => [],
				'selectHosts' => ($this instanceof CTemplateDashboard) ? ['hostid'] : null,
				'graphids' => $graph_prototypeids,
				'preservekeys' => true
			]);

			foreach ($graph_prototypeids as $graph_prototypeid) {
				if (!array_key_exists($graph_prototypeid, $db_graph_prototypes)) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Graph prototype with ID "%1$s" is not available.', $graph_prototypeid)
					);
				}

				if ($this instanceof CTemplateDashboard) {
					$templateids = array_keys($ids[ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE][$graph_prototypeid]);
					foreach ($templateids as $templateid) {
						$hostids = array_column($db_graph_prototypes[$graph_prototypeid]['hosts'], 'hostid');
						if (!in_array($templateid, $hostids)) {
							self::exception(ZBX_API_ERROR_PARAMETERS,
								_s('Graph prototype with ID "%1$s" is not available.', $graph_prototypeid)
							);
						}
					}
				}
			}
		}

		if ($ids[ZBX_WIDGET_FIELD_TYPE_GROUP]) {
			$groupids = array_keys($ids[ZBX_WIDGET_FIELD_TYPE_GROUP]);

			$db_groups = API::HostGroup()->get([
				'output' => [],
				'groupids' => $groupids,
				'preservekeys' => true
			]);

			foreach ($groupids as $groupid) {
				if (!array_key_exists($groupid, $db_groups)) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Host group with ID "%1$s" is not available.', $groupid)
					);
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

		if ($ids[ZBX_WIDGET_FIELD_TYPE_MAP]) {
			$sysmapids = array_keys($ids[ZBX_WIDGET_FIELD_TYPE_MAP]);

			$db_sysmaps = API::Map()->get([
				'output' => [],
				'sysmapids' => $sysmapids,
				'preservekeys' => true
			]);

			foreach ($sysmapids as $sysmapid) {
				if (!array_key_exists($sysmapid, $db_sysmaps)) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Map with ID "%1$s" is not available.', $sysmapid)
					);
				}
			}
		}

		if ($ids[ZBX_WIDGET_FIELD_TYPE_SERVICE]) {
			$serviceids = array_keys($ids[ZBX_WIDGET_FIELD_TYPE_SERVICE]);

			$db_services = API::Service()->get([
				'output' => [],
				'serviceids' => $serviceids,
				'preservekeys' => true
			]);

			foreach ($serviceids as $serviceid) {
				if (!array_key_exists($serviceid, $db_services)) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Service with ID "%1$s" is not available.', $serviceid)
					);
				}
			}
		}

		if ($ids[ZBX_WIDGET_FIELD_TYPE_SLA]) {
			$slaids = array_keys($ids[ZBX_WIDGET_FIELD_TYPE_SLA]);

			$db_slas = API::Sla()->get([
				'output' => [],
				'slaids' => $slaids,
				'preservekeys' => true
			]);

			foreach ($slaids as $slaid) {
				if (!array_key_exists($slaid, $db_slas)) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('SLA with ID "%1$s" is not available.', $slaid)
					);
				}
			}
		}

		if ($ids[ZBX_WIDGET_FIELD_TYPE_USER]) {
			$userids = array_keys($ids[ZBX_WIDGET_FIELD_TYPE_USER]);

			$db_users = API::User()->get([
				'output' => [],
				'userids' => $userids,
				'preservekeys' => true
			]);

			foreach ($userids as $userid) {
				if (!array_key_exists($userid, $db_users)) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('User with ID "%1$s" is not available.', $userid)
					);
				}
			}
		}

		if ($ids[ZBX_WIDGET_FIELD_TYPE_ACTION]) {
			$actionids = array_keys($ids[ZBX_WIDGET_FIELD_TYPE_ACTION]);

			$db_actions = API::Action()->get([
				'output' => [],
				'actionids' => $actionids,
				'preservekeys' => true
			]);

			foreach ($actionids as $actionid) {
				if (!array_key_exists($actionid, $db_actions)) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Action with ID "%1$s" is not available.', $actionid)
					);
				}
			}
		}

		if ($ids[ZBX_WIDGET_FIELD_TYPE_MEDIA_TYPE]) {
			$mediatypeids = array_keys($ids[ZBX_WIDGET_FIELD_TYPE_MEDIA_TYPE]);

			$db_media_types = API::MediaType()->get([
				'output' => [],
				'mediatypeids' => $mediatypeids,
				'preservekeys' => true
			]);

			foreach ($mediatypeids as $mediatypeid) {
				if (!array_key_exists($mediatypeid, $db_media_types)) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Media type with ID "%1$s" is not available.', $mediatypeid)
					);
				}
			}
		}
	}

	/**
	 * Update table "dashboard_page".
	 *
	 * Note: For any object with ID in $dashboards a corresponding object in $db_dashboards must exist.
	 *
	 * @param array      $dashboards
	 * @param array|null $db_dashboards
	 */
	protected function updatePages(array &$dashboards, ?array $db_dashboards = null): void {
		$db_dashboard_pages = [];

		if ($db_dashboards !== null) {
			foreach ($dashboards as $dashboard) {
				if (array_key_exists('pages', $dashboard)) {
					$db_dashboard_pages += $db_dashboards[$dashboard['dashboardid']]['pages'];
				}
			}
		}

		$ins_dashboard_pages = [];
		$upd_dashboard_pages = [];

		foreach ($dashboards as $dashboard) {
			if (!array_key_exists('pages', $dashboard)) {
				continue;
			}

			foreach ($dashboard['pages'] as $index => $dashboard_page) {
				$dashboard_page['sortorder'] = $index;

				if (array_key_exists('dashboard_pageid', $dashboard_page)) {
					$upd_dashboard_page = DB::getUpdatedValues('dashboard_page', $dashboard_page,
						$db_dashboard_pages[$dashboard_page['dashboard_pageid']]
					);

					if ($upd_dashboard_page) {
						$upd_dashboard_pages[] = [
							'values' => $upd_dashboard_page,
							'where' => ['dashboard_pageid' => $dashboard_page['dashboard_pageid']]
						];
					}

					unset($db_dashboard_pages[$dashboard_page['dashboard_pageid']]);
				}
				else {
					unset($dashboard_page['widgets']);
					$ins_dashboard_pages[] = ['dashboardid' => $dashboard['dashboardid']] + $dashboard_page;
				}
			}
		}

		if ($ins_dashboard_pages) {
			$dashboard_pageids = DB::insert('dashboard_page', $ins_dashboard_pages);
		}

		if ($upd_dashboard_pages) {
			DB::update('dashboard_page', $upd_dashboard_pages);
		}

		foreach ($dashboards as &$dashboard) {
			if (!array_key_exists('pages', $dashboard)) {
				continue;
			}

			foreach ($dashboard['pages'] as &$dashboard_page) {
				if (!array_key_exists('dashboard_pageid', $dashboard_page)) {
					$dashboard_page['dashboard_pageid'] = array_shift($dashboard_pageids);
				}
			}
			unset($dashboard_page);
		}
		unset($dashboard);

		$this->updateWidgets($dashboards, $db_dashboards);

		if ($db_dashboard_pages) {
			DB::delete('dashboard_page', ['dashboard_pageid' => array_keys($db_dashboard_pages)]);
		}
	}

	/**
	 * Update table "widget".
	 *
	 * Note: For any object with ID in $dashboards a corresponding object in $db_dashboards must exist.
	 *
	 * @param array      $dashboards
	 * @param array|null $db_dashboards
	 */
	protected function updateWidgets(array &$dashboards, ?array $db_dashboards = null): void {
		$db_widgets = [];

		if ($db_dashboards !== null) {
			foreach ($dashboards as $dashboard) {
				if (!array_key_exists('pages', $dashboard)) {
					continue;
				}

				$db_dashboard_pages = $db_dashboards[$dashboard['dashboardid']]['pages'];

				foreach ($dashboard['pages'] as $dashboard_page) {
					if (!array_key_exists('widgets', $dashboard_page)) {
						continue;
					}

					if (array_key_exists($dashboard_page['dashboard_pageid'], $db_dashboard_pages)) {
						$db_widgets += $db_dashboard_pages[$dashboard_page['dashboard_pageid']]['widgets'];
					}
				}
			}
		}

		$ins_widgets = [];
		$upd_widgets = [];

		foreach ($dashboards as $dashboard) {
			if (!array_key_exists('pages', $dashboard)) {
				continue;
			}

			foreach ($dashboard['pages'] as $dashboard_page) {
				if (!array_key_exists('widgets', $dashboard_page)) {
					continue;
				}

				foreach ($dashboard_page['widgets'] as $widget) {
					if (array_key_exists('widgetid', $widget)) {
						$upd_widget = DB::getUpdatedValues('widget', $widget, $db_widgets[$widget['widgetid']]);

						if ($upd_widget) {
							$upd_widgets[] = [
								'values' => $upd_widget,
								'where' => ['widgetid' => $widget['widgetid']]
							];
						}

						unset($db_widgets[$widget['widgetid']]);
					}
					else {
						unset($widget['fields']);
						$ins_widgets[] = ['dashboard_pageid' => $dashboard_page['dashboard_pageid']] + $widget;
					}
				}
			}
		}

		if ($ins_widgets) {
			$widgetids = DB::insert('widget', $ins_widgets);
		}

		if ($upd_widgets) {
			DB::update('widget', $upd_widgets);
		}

		foreach ($dashboards as &$dashboard) {
			if (!array_key_exists('pages', $dashboard)) {
				continue;
			}

			foreach ($dashboard['pages'] as &$dashboard_page) {
				if (!array_key_exists('widgets', $dashboard_page)) {
					continue;
				}

				foreach ($dashboard_page['widgets'] as &$widget) {
					if (!array_key_exists('widgetid', $widget)) {
						$widget['widgetid'] = array_shift($widgetids);
					}
				}
				unset($widget);
			}
			unset($dashboard_page);
		}
		unset($dashboard);

		$this->updateWidgetFields($dashboards, $db_dashboards);

		if ($db_widgets) {
			self::deleteWidgets(array_keys($db_widgets));
		}
	}

	/**
	 * Update table "widget_field".
	 *
	 * Note: For any object with ID in $dashboards a corresponding object in $db_dashboards must exist.
	 *
	 * @param array      $dashboards
	 * @param array|null $db_dashboards
	 */
	protected function updateWidgetFields(array &$dashboards, ?array $db_dashboards = null): void {
		$ins_widget_fields = [];
		$upd_widget_fields = [];
		$del_widget_fieldids = [];

		foreach ($dashboards as &$dashboard) {
			if (!array_key_exists('pages', $dashboard)) {
				continue;
			}

			$db_dashboard_pages = ($db_dashboards !== null) ? $db_dashboards[$dashboard['dashboardid']]['pages'] : [];

			foreach ($dashboard['pages'] as &$dashboard_page) {
				if (!array_key_exists('widgets', $dashboard_page)) {
					continue;
				}

				$db_widgets = array_key_exists($dashboard_page['dashboard_pageid'], $db_dashboard_pages)
					? $db_dashboard_pages[$dashboard_page['dashboard_pageid']]['widgets']
					: [];

				foreach ($dashboard_page['widgets'] as &$widget) {
					if (!array_key_exists('fields', $widget)) {
						continue;
					}

					$db_widget_fields = array_key_exists($widget['widgetid'], $db_widgets)
						? $db_widgets[$widget['widgetid']]['fields']
						: [];

					foreach ($widget['fields'] as &$widget_field) {
						$db_widget_field = current(
							array_filter($db_widget_fields,
								static function (array $db_widget_field) use ($widget_field): bool {
									return ($widget_field['type'] == $db_widget_field['type']
										&& $widget_field['name'] === $db_widget_field['name']);
								}
							)
						);

						if ($db_widget_field) {
							$widget_field['widget_fieldid'] = $db_widget_field['widget_fieldid'];

							$upd_widget_field = DB::getUpdatedValues('widget_field',
								CArrayHelper::renameKeys($widget_field, [
									'value' => self::WIDGET_FIELD_TYPE_COLUMNS[$widget_field['type']]
								]),
								CArrayHelper::renameKeys($db_widget_field, [
									'value' => self::WIDGET_FIELD_TYPE_COLUMNS[$db_widget_field['type']]
								])
							);

							if ($upd_widget_field) {
								$upd_widget_fields[] = [
									'values' => $upd_widget_field,
									'where' => ['widget_fieldid' => $db_widget_field['widget_fieldid']]
								];
							}

							unset($db_widget_fields[$db_widget_field['widget_fieldid']]);
						}
						else {
							$ins_widget_fields[] = ['widgetid' => $widget['widgetid']] +
								CArrayHelper::renameKeys($widget_field, [
									'value' => self::WIDGET_FIELD_TYPE_COLUMNS[$widget_field['type']]
								]);
						}
					}
					unset($widget_field);

					$del_widget_fieldids += $db_widget_fields;
				}
				unset($widget);
			}
			unset($dashboard_page);
		}
		unset($dashboard);

		if ($ins_widget_fields) {
			$widget_fieldids = DB::insert('widget_field', $ins_widget_fields);
		}

		if ($upd_widget_fields) {
			DB::update('widget_field', $upd_widget_fields);
		}

		if ($del_widget_fieldids) {
			DB::delete('widget_field', ['widget_fieldid' => array_keys($del_widget_fieldids)]);
		}

		foreach ($dashboards as &$dashboard) {
			if (!array_key_exists('pages', $dashboard)) {
				continue;
			}

			foreach ($dashboard['pages'] as &$dashboard_page) {
				if (!array_key_exists('widgets', $dashboard_page)) {
					continue;
				}

				foreach ($dashboard_page['widgets'] as &$widget) {
					if (!array_key_exists('fields', $widget)) {
						continue;
					}

					foreach ($widget['fields'] as &$widget_field) {
						if (!array_key_exists('widget_fieldid', $widget_field)) {
							$widget_field['widget_fieldid'] = array_shift($widget_fieldids);
						}
					}
					unset($widget_field);
				}
				unset($widget);
			}
			unset($dashboard_page);
		}
		unset($dashboard);
	}

	/**
	 * Delete widgets.
	 *
	 * This will also delete profile keys related to the specified widgets, including the standard ones:
	 *   - web.dashboard.widget.rf_rate
	 *   - web.dashboard.widget.navtree.item.selected
	 *   - web.dashboard.widget.navtree.item-*.toggle
	 *
	 * @param array $widgetids
	 */
	protected static function deleteWidgets(array $widgetids): void {
		DBexecute(
			'DELETE FROM profiles'.
				' WHERE idx LIKE '.zbx_dbstr('web.dashboard.widget.%').
					' AND '.dbConditionId('idx2', $widgetids)
		);

		DB::delete('widget', ['widgetid' => $widgetids]);
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		if ($options['selectPages'] !== null) {
			foreach ($result as &$row) {
				$row['pages'] = [];
			}
			unset($row);

			$widgets_requested = $this->outputIsRequested('widgets', $options['selectPages']);

			if ($widgets_requested && is_array($options['selectPages'])) {
				$options['selectPages'] = array_diff($options['selectPages'], ['widgets']);
			}

			$db_dashboard_pages = API::getApiService()->select('dashboard_page', [
				'output' => $this->outputExtend($options['selectPages'], ['dashboardid', 'sortorder']),
				'filter' => ['dashboardid' => array_keys($result)],
				'preservekeys' => true
			]);

			if ($db_dashboard_pages) {
				uasort($db_dashboard_pages, function (array $db_dashboard_page_1, array $db_dashboard_page_2): int {
					return $db_dashboard_page_1['sortorder'] <=> $db_dashboard_page_2['sortorder'];
				});

				if ($widgets_requested) {
					foreach ($db_dashboard_pages as &$db_dashboard_page) {
						$db_dashboard_page['widgets'] = [];
					}
					unset($db_dashboard_page);

					$db_widgets = DB::select('widget', [
						'output' => ['widgetid', 'type', 'name', 'x', 'y', 'width', 'height', 'view_mode',
							'dashboard_pageid'
						],
						'filter' => ['dashboard_pageid' => array_keys($db_dashboard_pages)],
						'preservekeys' => true
					]);

					if ($db_widgets) {
						foreach ($db_widgets as &$db_widget) {
							$db_widget['fields'] = [];
						}
						unset($db_widget);

						$db_widget_fields = DB::select('widget_field', [
							'output' => ['widget_fieldid', 'widgetid', 'type', 'name', 'value_int', 'value_str',
								'value_groupid', 'value_hostid', 'value_itemid', 'value_graphid', 'value_serviceid',
								'value_slaid', 'value_userid', 'value_actionid', 'value_mediatypeid', 'value_sysmapid'
							],
							'filter' => [
								'widgetid' => array_keys($db_widgets),
								'type' => array_keys(self::WIDGET_FIELD_TYPE_COLUMNS)
							]
						]);

						foreach ($db_widget_fields as $db_widget_field) {
							$db_widgets[$db_widget_field['widgetid']]['fields'][] = [
								'type' => $db_widget_field['type'],
								'name' => $db_widget_field['name'],
								'value' => $db_widget_field[self::WIDGET_FIELD_TYPE_COLUMNS[$db_widget_field['type']]]
							];
						}
					}

					foreach ($db_widgets as $db_widget) {
						$dashboard_pageid = $db_widget['dashboard_pageid'];
						unset($db_widget['dashboard_pageid']);
						$db_dashboard_pages[$dashboard_pageid]['widgets'][] = $db_widget;
					}
				}

				$db_dashboard_pages = $this->unsetExtraFields($db_dashboard_pages, ['dashboard_pageid'],
					$options['selectPages']
				);

				foreach ($db_dashboard_pages as $db_dashboard_page) {
					$dashboardid = $db_dashboard_page['dashboardid'];
					unset($db_dashboard_page['dashboardid'], $db_dashboard_page['sortorder']);
					$result[$dashboardid]['pages'][] = $db_dashboard_page;
				}
			}
		}

		return $result;
	}
}
