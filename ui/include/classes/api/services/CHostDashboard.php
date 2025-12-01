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
 * Host dashboards API implementation.
 */
class CHostDashboard extends CApiService {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_ZABBIX_USER]
	];

	public const OUTPUT_FIELDS = ['hostid', 'dashboardid', 'name', 'display_period', 'auto_start', 'templateid'];

	private const SORT_COLUMNS = ['hostid', 'dashboardid', 'name'];

	private static function validateGet(array &$options): void {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			// Filters.
			'hostids' =>				['type' => API_IDS, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_NORMALIZE, 'uniq' => true],
			'dashboardids' =>			['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'filter' =>					['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => ['dashboardid', 'name', 'display_period', 'auto_start', 'templateid']],
			'search' =>					['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => ['name']],
			'searchByAny' =>			['type' => API_BOOLEAN, 'default' => false],
			'startSearch' =>			['type' => API_BOOLEAN, 'default' => false],
			'excludeSearch' =>			['type' => API_BOOLEAN, 'default' => false],
			'searchWildcardsEnabled' =>	['type' => API_BOOLEAN, 'default' => false],
			// Output.
			'output' =>					['type' => API_OUTPUT, 'flags' => API_NOT_EMPTY, 'in' => implode(',', self::OUTPUT_FIELDS), 'default' => API_OUTPUT_EXTEND],
			'selectPages' =>			['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', ['dashboard_pageid', 'name', 'display_period', 'widgets']), 'default' => null],
			'countOutput' =>			['type' => API_BOOLEAN, 'default' => false],
			'groupCount' =>				['type' => API_BOOLEAN, 'default' => false],
			// Sort and limit.
			'sortfield' =>				['type' => API_STRINGS_UTF8, 'flags' => API_NORMALIZE, 'in' => implode(',', self::SORT_COLUMNS), 'uniq' => true, 'default' => []],
			'sortorder' =>				['type' => API_SORTORDER, 'default' => []],
			'limit' =>					['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => '1:'.ZBX_MAX_INT32, 'default' => null],
			// Flags.
			'editable' =>				['type' => API_BOOLEAN, 'default' => false]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}
	}

	/**
	 * @param array $options
	 *
	 * @return array|string
	 *
	 * @throws APIException
	 */
	public function get(array $options = []) {
		self::validateGet($options);

		$hostids = array_keys(API::Host()->get([
			'output' => [],
			'hostids' => $options['hostids'],
			'editable' => $options['editable'],
			'preservekeys' => true
		]));

		if (!$hostids) {
			return [];
		}

		$templates = self::getTemplates($hostids);

		if ($options['countOutput']) {
			return $options['groupCount']
				? $this->getCountsByHost($options, $hostids, $templates)
				: $this->getCount($options, $templates);
		}

		return $this->getObjects($options, $templates);
	}

	private static function getTemplates(array $hostids): array {
		$templates = [];

		$options = [
			'output' => ['templateid', 'hostid'],
			'filter' => ['hostid' => $hostids]
		];
		$result = DBSelect(DB::makeSql('hosts_templates', $options));

		while ($row = DBfetch($result)) {
			$templates[$row['templateid']]['hostids'][$row['hostid']] = true;
		}

		$templateids = $templates;
		$template_parents = [];

		while ($templateids) {
			$options = [
				'output' => ['templateid', 'hostid'],
				'filter' => ['hostid' => array_keys($templateids)]
			];
			$result = DBSelect(DB::makeSql('hosts_templates', $options));

			$templateids = [];

			while ($row = DBfetch($result)) {
				if (!array_key_exists($row['templateid'], $template_parents)) {
					$templateids[$row['templateid']] = true;
				}

				$template_parents[$row['hostid']][] = $row['templateid'];
			}
		}

		$_templateids = $templates;

		while ($templateids = $_templateids) {
			$_templateids = [];

			foreach ($templateids as $templateid => $foo) {
				if (!array_key_exists($templateid, $template_parents)) {
					$templates[$templateid]['root'] = true;
					continue;
				}

				foreach ($template_parents[$templateid] as $parent_templateid) {
					if (array_key_exists('hostids', $templates[$templateid])) {
						$templates[$parent_templateid]['templateids'][$templateid] = $templates[$templateid]['hostids'];
					}

					if (array_key_exists('templateids', $templates[$templateid])) {
						if (!array_key_exists($parent_templateid, $templates)
								|| !array_key_exists('templateids', $templates[$parent_templateid])
								|| !array_key_exists($templateid, $templates[$parent_templateid]['templateids'])) {
							$templates[$parent_templateid]['templateids'][$templateid] = [];
						}

						foreach ($templates[$templateid]['templateids'] as $_hostids) {
							$templates[$parent_templateid]['templateids'][$templateid] += $_hostids;
						}
					}

					$_templateids[$parent_templateid] = true;
				}
			}
		}

		return $templates;
	}

	private function getCountsByHost(array $options, array $hostids, array $templates): array {
		$host_counts = [];

		foreach ($hostids as $hostid) {
			$host_counts[$hostid] = ['hostid' => (string) $hostid, 'rowscount' => 0];
		}

		$td_result = DBselect($this->getTemplateDashboardSelectQuery($options, array_keys($templates)));

		while ($row = DBfetch($td_result)) {
			if (array_key_exists('hostids', $templates[$row['templateid']])) {
				foreach ($templates[$row['templateid']]['hostids'] as $hostid => $foo) {
					$host_counts[$hostid]['rowscount'] += $row['rowscount'];
				}
			}

			if (array_key_exists('templateids', $templates[$row['templateid']])) {
				foreach ($templates[$row['templateid']]['templateids'] as $_hostids) {
					foreach ($_hostids as $hostid => $foo) {
						$host_counts[$hostid]['rowscount'] += $row['rowscount'];
					}
				}
			}
		}

		self::sortObjects($host_counts, $options['sortfield'], $options['sortorder']);

		if ($options['limit'] !== null) {
			$host_counts = array_slice($host_counts, 0, $options['limit']);
		}

		foreach ($host_counts as &$host_count) {
			$host_count['rowscount'] = (string) $host_count['rowscount'];
		}
		unset($host_count);

		return array_values($host_counts);
	}

	private function getCount(array $options, array $templates): string {
		$rowscount = 0;

		$td_result = DBselect($this->getTemplateDashboardSelectQuery($options, array_keys($templates)));

		while ($row = DBfetch($td_result)) {
			if (array_key_exists('hostids', $templates[$row['templateid']])) {
				$rowscount += $row['rowscount'] * count($templates[$row['templateid']]['hostids']);
			}

			if (array_key_exists('templateids', $templates[$row['templateid']])) {
				foreach ($templates[$row['templateid']]['templateids'] as $_hostids) {
					$rowscount += $row['rowscount'] * count($_hostids);
				}
			}
		}

		return (string) $rowscount;
	}

	private function getObjects(array $options, array $templates): array {
		$host_dashboards = [];
		$template_dashboards = [];

		$td_result = DBselect($this->getTemplateDashboardSelectQuery($options, array_keys($templates)));

		while ($row = DBfetch($td_result)) {
			$template_dashboards[$row['dashboardid']] = $row;

			if (array_key_exists('hostids', $templates[$row['templateid']])) {
				foreach ($templates[$row['templateid']]['hostids'] as $hostid => $foo) {
					$host_dashboards[] =
						array_combine(self::SORT_COLUMNS, [(string) $hostid, $row['dashboardid'], $row['name']]);
				}
			}

			if (array_key_exists('templateids', $templates[$row['templateid']])) {
				foreach ($templates[$row['templateid']]['templateids'] as $_hostids) {
					foreach ($_hostids as $hostid => $foo) {
						$host_dashboards[] =
							array_combine(self::SORT_COLUMNS, [(string) $hostid, $row['dashboardid'], $row['name']]);
					}
				}
			}
		}

		self::sortObjects($host_dashboards, $options['sortfield'], $options['sortorder']);

		if ($options['limit'] !== null) {
			$host_dashboards = array_slice($host_dashboards, 0, $options['limit']);
			$template_dashboards =
				array_intersect_key($template_dashboards, array_column($host_dashboards, 'dashboardid', 'dashboardid'));

			self::unsetUnrelatedTemplates($templates, $host_dashboards);
		}

		$this->addRelatedPages($host_dashboards, $templates, $options);

		$output = $options['output'] === API_OUTPUT_EXTEND ? self::OUTPUT_FIELDS : $options['output'];

		foreach ($host_dashboards as &$host_dashboard) {
			$_host_dashboard = [];

			foreach ($output as $field_name) {
				$_host_dashboard[$field_name] = in_array($field_name, self::SORT_COLUMNS)
					? $host_dashboard[$field_name]
					: $template_dashboards[$host_dashboard['dashboardid']][$field_name];
			}

			if (array_key_exists('pages', $host_dashboard)) {
				$_host_dashboard['pages'] = $host_dashboard['pages'];
			}

			$host_dashboard = $_host_dashboard;
		}
		unset($host_dashboard);

		return $host_dashboards;
	}

	private function getTemplateDashboardSelectQuery(array $options, array $templateids): string {
		$sql_parts = [
			'select' => [],
			'from' => 'dashboard d',
			'where' => [dbConditionId('d.templateid', $templateids)],
			'group' => [],
			'order' => []
		];

		if ($options['dashboardids'] !== null) {
			$sql_parts['where'][] = dbConditionId('d.dashboardid', $options['dashboardids']);
		}

		if ($options['filter'] !== null) {
			$this->dbFilter('dashboard d', $options, $sql_parts);
		}

		if ($options['search'] !== null) {
			zbx_db_search('dashboard d', $options, $sql_parts);
		}

		if ($options['countOutput']) {
			$td_options = [
				'countOutput' => true,
				'groupCount' => true
			];
			$sql_parts['group']['templateid'] = 'd.templateid';
		}
		else {
			$common_fields = array_flip(['dashboardid', 'name', 'display_period', 'auto_start', 'templateid']);

			$td_output = $options['output'] === API_OUTPUT_EXTEND
				? $common_fields
				: array_intersect_key(array_flip($options['output']), $common_fields);

			$td_options = ['output' => array_keys($td_output + array_flip(['dashboardid', 'name', 'templateid']))];
		}

		$sql_parts = $this->applyQueryOutputOptions('dashboard', 'd', $td_options, $sql_parts);

		return self::createSelectQueryFromParts($sql_parts);
	}

	private static function sortObjects(array &$result, array $sortfield, $sortorder): void {
		if (!$sortfield) {
			return;
		}

		$fields = [];

		if (is_array($sortorder)) {
			foreach ($sortfield as $i => $field) {
				$fields[] =
					['field' => $field, 'order' => array_key_exists($i, $sortorder) ? $sortorder[$i] : ZBX_SORT_UP];
			}
		}
		else {
			foreach ($sortfield as $i => $field) {
				$fields[] = ['field' => $field, 'order' => $sortorder];
			}
		}

		CArrayHelper::sort($result, $fields);
	}

	private static function unsetUnrelatedTemplates(array &$templates, array $host_dashboards): void {
		$hostids = array_column($host_dashboards, 'hostid', 'hostid');

		$templateids = [];

		foreach ($templates as $templateid => $template) {
			if (array_key_exists('root', $template)) {
				$templateids[$templateid] = true;
			}
		}

		do {
			$_templateids = [];

			foreach ($templateids as $templateid => $foo) {
				if (!array_key_exists($templateid, $templates)) {
					continue;
				}

				if (array_key_exists('hostids', $templates[$templateid])) {
					$templates[$templateid]['hostids'] =
						array_intersect_key($templates[$templateid]['hostids'], $hostids);

					if (!$templates[$templateid]['hostids']) {
						unset($templates[$templateid]['hostids']);
					}
				}

				if (array_key_exists('templateids', $templates[$templateid])) {
					foreach ($templates[$templateid]['templateids'] as $child_templateid => &$_hostids) {
						$_hostids = array_intersect_key($_hostids, $hostids);

						if (!$_hostids) {
							unset($templates[$templateid]['templateids'][$child_templateid]);
						}

						$_templateids[$child_templateid] = true;
					}
					unset($_hostids);

					if (!$templates[$templateid]['templateids']) {
						unset($templates[$templateid]['templateids']);
					}
				}

				if (!array_key_exists('hostids', $templates[$templateid])
						&& !array_key_exists('templateids', $templates[$templateid])) {
					unset($templates[$templateid]);
				}
			}
		} while ($templateids = $_templateids);
	}

	private function addRelatedPages(array &$host_dashboards, array $templates, array $options): void {
		if ($options['selectPages'] === null) {
			return;
		}

		if ($options['selectPages'] === API_OUTPUT_EXTEND) {
			$options['selectPages'] = ['dashboard_pageid', 'name', 'display_period', 'widgets'];
		}

		$host_dashboard_indexes = [];

		foreach ($host_dashboards as $i => &$host_dashboard) {
			$host_dashboard['pages'] = [];

			$host_dashboard_indexes[$host_dashboard['dashboardid']][] = $i;
		}
		unset($host_dashboard);

		$_options = [
			'output' => $this->outputExtend(array_diff($options['selectPages'], ['widgets']),
				['dashboard_pageid', 'dashboardid']
			),
			'filter' => ['dashboardid' => array_keys($host_dashboard_indexes)],
			'sortfield' => ['sortorder']
		];
		$result = DBselect(DB::makeSql('dashboard_page', $_options));

		$extra_fields = in_array('dashboard_pageid', $options['selectPages'])
			? array_flip(['dashboardid'])
			: array_flip(['dashboard_pageid', 'dashboardid']);

		$pages = [];

		while ($page = DBfetch($result)) {
			$value = array_diff_key($page, $extra_fields);

			foreach ($host_dashboard_indexes[$page['dashboardid']] as $i) {
				$host_dashboards[$i]['pages'][] = $value;
				$pages[$page['dashboard_pageid']][$host_dashboards[$i]['hostid']] =
					&$host_dashboards[$i]['pages'][array_key_last($host_dashboards[$i]['pages'])];
			}
		}

		if ($pages && in_array('widgets', $options['selectPages'])) {
			self::addRelatedPageWidgets($pages, $templates);
		}
	}

	private static function addRelatedPageWidgets(array $pages, array $templates): void {
		foreach ($pages as &$host_pages) {
			foreach ($host_pages as &$page) {
				$page['widgets'] = [];
			}
			unset($page);
		}
		unset($host_pages);

		$options = [
			'output' => ['widgetid', 'type', 'name', 'x', 'y', 'width', 'height', 'view_mode', 'dashboard_pageid'],
			'filter' => ['dashboard_pageid' => array_keys($pages)]
		];
		$result = DBselect(DB::makeSql('widget', $options));

		$widgets = [];

		while ($widget = DBfetch($result)) {
			$value = array_diff_key($widget, array_flip(['dashboard_pageid']));

			foreach ($pages[$widget['dashboard_pageid']] as $hostid => &$page) {
				$page['widgets'][] = $value;
				$widgets[$widget['widgetid']][$hostid] = &$page['widgets'][array_key_last($page['widgets'])];
			}
			unset($page);
		}

		if ($widgets) {
			self::addRelatedPageWidgetFields($widgets, $templates);
		}
	}

	private static function addRelatedPageWidgetFields(array $widgets, array $templates): void {
		foreach ($widgets as &$host_widgets) {
			foreach ($host_widgets as &$widget) {
				$widget['fields'] = [];
			}
			unset($widget);
		}
		unset($host_widgets);

		$options = [
			'output' => array_merge(['widget_fieldid', 'widgetid', 'type', 'name'],
				array_unique(array_values(CDashboardGeneral::WIDGET_FIELD_TYPE_COLUMNS))
			),
			'filter' => ['widgetid' => array_keys($widgets)]
		];
		$result = DBselect(DB::makeSql('widget_field', $options));

		$value_itemids = [];
		$value_graphids = [];

		while ($widget_field = DBfetch($result)) {
			$value_field_name = CDashboardGeneral::WIDGET_FIELD_TYPE_COLUMNS[$widget_field['type']];

			$value = [
				'type' => $widget_field['type'],
				'name' => $widget_field['name'],
				'value' => $widget_field[$value_field_name]
			];

			foreach ($widgets[$widget_field['widgetid']] as $hostid => &$widget) {
				$widget['fields'][] = $value;

				if ($value_field_name === 'value_itemid') {
					$value_itemids[$widget_field[$value_field_name]][$hostid][] =
						&$widget['fields'][array_key_last($widget['fields'])]['value'];
				}

				if ($value_field_name === 'value_graphid') {
					$value_graphids[$widget_field[$value_field_name]][$hostid][] =
						&$widget['fields'][array_key_last($widget['fields'])]['value'];
				}
			}
			unset($widget);
		}

		if ($value_itemids) {
			self::resolveItemIds($value_itemids, $templates);
		}

		if ($value_graphids) {
			self::resolveGraphIds($value_graphids, $templates);
		}
	}

	private static function resolveItemIds(array $value_itemids, array $templates): void {
		$options = [
			'output' => ['itemid', 'hostid'],
			'itemids' => array_keys($value_itemids)
		];
		$result = DBselect(DB::makeSql('items', $options));

		$item_templateids = [];

		while ($row = DBfetch($result)) {
			$item_templateids[$row['itemid']] = $row['hostid'];
		}

		$item_links = [];
		$processed_itemids = [];
		$host_items = [];

		do {
			$hostids = ['templateids' => [], 'hostids' => []];

			foreach (array_unique($item_templateids) as $templateid) {
				if (array_key_exists('hostids', $templates[$templateid])) {
					$hostids['hostids'] += $templates[$templateid]['hostids'];
				}

				if (array_key_exists('templateids', $templates[$templateid])) {
					$hostids['templateids'] += $templates[$templateid]['templateids'];
				}
			}

			$options = [
				'output' => ['itemid', 'hostid', 'templateid'],
				'filter' => [
					'templateid' => array_keys($item_templateids),
					'hostid' => array_keys($hostids['templateids'] + $hostids['hostids'])
				]
			];
			$result = DBselect(DB::makeSql('items', $options));

			$processed_itemids += $item_templateids;
			$_item_templateids = [];

			while ($item = DBfetch($result)) {
				if (array_key_exists($item['hostid'], $hostids['templateids'])) {
					if (!array_key_exists($item['hostid'], $templates[$item_templateids[$item['templateid']]]['templateids'])) {
						continue;
					}

					$item_links[$item['itemid']] = $item['templateid'];

					if (!array_key_exists($item['itemid'], $processed_itemids)) {
						$_item_templateids[$item['itemid']] = $item['hostid'];
					}
				}
				else {
					if (!array_key_exists($item['hostid'], $templates[$item_templateids[$item['templateid']]]['hostids'])) {
						continue;
					}

					$host_items[] = $item;
				}
			}
		} while ($item_templateids = $_item_templateids);

		foreach ($host_items as $item) {
			$tpl_itemid = $item['templateid'];

			do {
				if (array_key_exists($tpl_itemid, $value_itemids)) {
					foreach ($value_itemids[$tpl_itemid][$item['hostid']] as &$value) {
						$value = $item['itemid'];
					}
					unset($value);
				}

				$tpl_itemid = array_key_exists($tpl_itemid, $item_links) ? $item_links[$tpl_itemid] : null;
			} while ($tpl_itemid !== null);
		}
	}

	private static function resolveGraphIds(array $value_graphids, array $templates): void {
		$result = DBselect(
			'SELECT DISTINCT gi.graphid,i.hostid'.
			' FROM graphs_items gi,items i'.
			' WHERE gi.itemid=i.itemid'.
				' AND '.dbConditionId('gi.graphid', array_keys($value_graphids))
		);

		$graph_templateids = [];

		while ($row = DBfetch($result)) {
			$graph_templateids[$row['graphid']] = $row['hostid'];
		}

		$graph_links = [];
		$processed_graphids = [];
		$host_graphs = [];

		do {
			$hostids = ['templateids' => [], 'hostids' => []];

			foreach (array_unique($graph_templateids) as $templateid) {
				if (array_key_exists('templateids', $templates[$templateid])) {
					$hostids['templateids'] += $templates[$templateid]['templateids'];
				}

				if (array_key_exists('hostids', $templates[$templateid])) {
					$hostids['hostids'] += $templates[$templateid]['hostids'];
				}
			}

			$result = DBselect(
				'SELECT DISTINCT g.graphid,g.templateid,i.hostid'.
				' FROM graphs g,graphs_items gi,items i'.
				' WHERE g.graphid=gi.graphid'.
					' AND gi.itemid=i.itemid'.
					' AND '.dbConditionId('g.templateid', array_keys($graph_templateids)).
					' AND '.dbConditionId('i.hostid', array_keys($hostids['templateids'] + $hostids['hostids']))
			);

			$processed_graphids += $graph_templateids;
			$_graph_templateids = [];

			while ($graph = DBfetch($result)) {
				if (array_key_exists($graph['hostid'], $hostids['templateids'])) {
					if (!array_key_exists($graph['hostid'], $templates[$graph_templateids[$graph['templateid']]]['templateids'])) {
						continue;
					}

					$graph_links[$graph['graphid']] = $graph['templateid'];

					if (!array_key_exists($graph['graphid'], $processed_graphids)) {
						$_graph_templateids[$graph['graphid']] = $graph['hostid'];
					}
				}
				else {
					if (!array_key_exists($graph['hostid'], $templates[$graph_templateids[$graph['templateid']]]['hostids'])) {
						continue;
					}

					$host_graphs[] = $graph;
				}
			}
		} while ($graph_templateids = $_graph_templateids);

		foreach ($host_graphs as $graph) {
			$tpl_graphid = $graph['templateid'];

			do {
				if (array_key_exists($tpl_graphid, $value_graphids)) {
					foreach ($value_graphids[$tpl_graphid][$graph['hostid']] as &$value) {
						$value = $graph['graphid'];
					}
					unset($value);
				}

				$tpl_graphid = array_key_exists($tpl_graphid, $graph_links) ? $graph_links[$tpl_graphid] : null;
			} while ($tpl_graphid !== null);
		}
	}
}
