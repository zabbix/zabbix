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


class CDashboardHelper {

	/**
	 * Get dashboard owner name.
	 *
	 * @static
	 *
	 * @param string $userid
	 *
	 * @return string
	 */
	public static function getOwnerName($userid): string {
		$users = API::User()->get([
			'output' => ['name', 'surname', 'alias'],
			'userids' => $userid
		]);

		$name = $users ? getUserFullname($users[0]) : _('Inaccessible user');

		return $name;
	}

	/**
	 * Update editable flag.
	 *
	 * @static
	 *
	 * @param array $dashboards  An associative array of the dashboards.
	 */
	public static function updateEditableFlag(array &$dashboards): void {
		$dashboards_rw = API::Dashboard()->get([
			'output' => [],
			'dashboardids' => array_keys($dashboards),
			'editable' => true,
			'preservekeys' => true
		]);

		foreach ($dashboards as $dashboardid => &$dashboard) {
			$dashboard['editable'] = array_key_exists($dashboardid, $dashboards_rw);
		}
		unset($dashboard);
	}

	/**
	 * Prepare widgets for dashboard grid.
	 *
	 * @static
	 *
	 * @param array  $widgets
	 * @param string $templateid
	 * @param bool   $with_rf_rate
	 *
	 * @return array
	 */
	public static function prepareWidgetsForGrid(array $widgets, ?string $templateid, bool $with_rf_rate): array {
		$grid_widgets = [];

		if ($widgets) {
			CArrayHelper::sort($widgets, ['y', 'x']);

			$context = ($templateid === null)
				? CWidgetConfig::CONTEXT_DASHBOARD
				: CWidgetConfig::CONTEXT_TEMPLATE_DASHBOARD;

			$known_widget_types = array_keys(CWidgetConfig::getKnownWidgetTypes($context));

			foreach ($widgets as $widget) {
				if (!in_array($widget['type'], $known_widget_types)) {
					continue;
				}

				$widgetid = $widget['widgetid'];
				$fields_orig = self::convertWidgetFields($widget['fields']);

				// Transforms corrupted data to default values.
				$widget_form = CWidgetConfig::getForm($widget['type'], json_encode($fields_orig), $templateid);
				$widget_form->validate();
				$fields = $widget_form->getFieldsData();

				if ($with_rf_rate) {
					$rf_rate = (int) CProfile::get('web.dashbrd.widget.rf_rate', -1, $widgetid);

					if ($rf_rate == -1) {
						if ($context === CWidgetConfig::CONTEXT_DASHBOARD) {
							$rf_rate = ($fields['rf_rate'] == -1)
								? CWidgetConfig::getDefaultRfRate($widget['type'])
								: $fields['rf_rate'];
						}
						else {
							$rf_rate = CWidgetConfig::getDefaultRfRate($widget['type']);
						}
					}
				}
				else {
					$rf_rate = 0;
				}

				$grid_widgets[] = [
					'widgetid' => $widgetid,
					'type' => $widget['type'],
					'header' => $widget['name'],
					'view_mode' => $widget['view_mode'],
					'pos' => [
						'x' => (int) $widget['x'],
						'y' => (int) $widget['y'],
						'width' => (int) $widget['width'],
						'height' => (int) $widget['height']
					],
					'rf_rate' => $rf_rate,
					'fields' => $fields_orig,
					'configuration' => CWidgetConfig::getConfiguration($widget['type'], $fields, $widget['view_mode'])
				];
			}
		}

		return $grid_widgets;
	}

	/**
	 * Returns array of widgets without inaccessible fields.
	 *
	 * @static
	 *
	 * @param array $widgets
	 * @param array $widgets[]['fields']
	 * @param array $widgets[]['fields'][]['type']
	 * @param array $widgets[]['fields'][]['value']
	 *
	 * @return array
	 */
	public static function unsetInaccessibleFields(array $widgets): array {
		$ids = [
			ZBX_WIDGET_FIELD_TYPE_GROUP => [],
			ZBX_WIDGET_FIELD_TYPE_HOST => [],
			ZBX_WIDGET_FIELD_TYPE_ITEM => [],
			ZBX_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE => [],
			ZBX_WIDGET_FIELD_TYPE_GRAPH => [],
			ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE => [],
			ZBX_WIDGET_FIELD_TYPE_MAP => []
		];

		foreach ($widgets as $w_index => $widget) {
			foreach ($widget['fields'] as $f_index => $field) {
				$ids[$field['type']][$field['value']][] = ['w' => $w_index, 'f' => $f_index];
			}
		}

		$inaccessible_indexes = [];

		if ($ids[ZBX_WIDGET_FIELD_TYPE_GROUP]) {
			$db_groups = API::HostGroup()->get([
				'output' => [],
				'groupids' => array_keys($ids[ZBX_WIDGET_FIELD_TYPE_GROUP]),
				'preservekeys' => true
			]);

			foreach ($ids[ZBX_WIDGET_FIELD_TYPE_GROUP] as $groupid => $indexes) {
				if (!array_key_exists($groupid, $db_groups)) {
					$inaccessible_indexes = array_merge($inaccessible_indexes, $indexes);
				}
			}
		}

		if ($ids[ZBX_WIDGET_FIELD_TYPE_HOST]) {
			$db_hosts = API::Host()->get([
				'output' => [],
				'hostids' => array_keys($ids[ZBX_WIDGET_FIELD_TYPE_HOST]),
				'preservekeys' => true
			]);

			foreach ($ids[ZBX_WIDGET_FIELD_TYPE_HOST] as $hostid => $indexes) {
				if (!array_key_exists($hostid, $db_hosts)) {
					$inaccessible_indexes = array_merge($inaccessible_indexes, $indexes);
				}
			}
		}

		if ($ids[ZBX_WIDGET_FIELD_TYPE_ITEM]) {
			$db_items = API::Item()->get([
				'output' => [],
				'itemids' => array_keys($ids[ZBX_WIDGET_FIELD_TYPE_ITEM]),
				'webitems' => true,
				'preservekeys' => true
			]);

			foreach ($ids[ZBX_WIDGET_FIELD_TYPE_ITEM] as $itemid => $indexes) {
				if (!array_key_exists($itemid, $db_items)) {
					$inaccessible_indexes = array_merge($inaccessible_indexes, $indexes);
				}
			}
		}

		if ($ids[ZBX_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE]) {
			$db_item_prototypes = API::ItemPrototype()->get([
				'output' => [],
				'itemids' => array_keys($ids[ZBX_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE]),
				'preservekeys' => true
			]);

			foreach ($ids[ZBX_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE] as $item_prototypeid => $indexes) {
				if (!array_key_exists($item_prototypeid, $db_item_prototypes)) {
					$inaccessible_indexes = array_merge($inaccessible_indexes, $indexes);
				}
			}
		}

		if ($ids[ZBX_WIDGET_FIELD_TYPE_GRAPH]) {
			$db_graphs = API::Graph()->get([
				'output' => [],
				'graphids' => array_keys($ids[ZBX_WIDGET_FIELD_TYPE_GRAPH]),
				'preservekeys' => true
			]);

			foreach ($ids[ZBX_WIDGET_FIELD_TYPE_GRAPH] as $graphid => $indexes) {
				if (!array_key_exists($graphid, $db_graphs)) {
					$inaccessible_indexes = array_merge($inaccessible_indexes, $indexes);
				}
			}
		}

		if ($ids[ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE]) {
			$db_graph_prototypes = API::GraphPrototype()->get([
				'output' => [],
				'graphids' => array_keys($ids[ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE]),
				'preservekeys' => true
			]);

			foreach ($ids[ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE] as $graph_prototypeid => $indexes) {
				if (!array_key_exists($graph_prototypeid, $db_graph_prototypes)) {
					$inaccessible_indexes = array_merge($inaccessible_indexes, $indexes);
				}
			}
		}

		if ($ids[ZBX_WIDGET_FIELD_TYPE_MAP]) {
			$db_sysmaps = API::Map()->get([
				'output' => [],
				'sysmapids' => array_keys($ids[ZBX_WIDGET_FIELD_TYPE_MAP]),
				'preservekeys' => true
			]);

			foreach ($ids[ZBX_WIDGET_FIELD_TYPE_MAP] as $sysmapid => $indexes) {
				if (!array_key_exists($sysmapid, $db_sysmaps)) {
					$inaccessible_indexes = array_merge($inaccessible_indexes, $indexes);
				}
			}
		}

		foreach ($inaccessible_indexes as $index) {
			unset($widgets[$index['w']]['fields'][$index['f']]);
		}

		return $widgets;
	}

	/**
	 * Converts fields, received from API to key/value format.
	 *
	 * @static
	 *
	 * @param array $fields  fields as received from API
	 *
	 * @return array
	 */
	public static function convertWidgetFields(array $fields): array {
		$ret = [];

		foreach ($fields as $field) {
			if (array_key_exists($field['name'], $ret)) {
				$ret[$field['name']] = (array) $ret[$field['name']];
				$ret[$field['name']][] = $field['value'];
			}
			else {
				$ret[$field['name']] = $field['value'];
			}
		}

		return $ret;
	}

	/**
	 * Checks, if any of widgets needs time selector.
	 *
	 * @static
	 *
	 * @param array $widgets
	 *
	 * @return bool
	 */
	public static function hasTimeSelector(array $widgets): bool {
		foreach ($widgets as $widget) {
			if (CWidgetConfig::usesTimeSelector($widget)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Validate widget input parameters.
	 *
	 * @static
	 *
	 * @var array  $widgets
	 * @var string $widget[]['widgetid']       (optional)
	 * @var array  $widget[]['pos']
	 * @var int    $widget[]['pos']['x']
	 * @var int    $widget[]['pos']['y']
	 * @var int    $widget[]['pos']['width']
	 * @var int    $widget[]['pos']['height']
	 * @var string $widget[]['type']
	 * @var string $widget[]['name']
	 * @var string $widget[]['fields']         (optional) JSON object
	 *
	 * @return array  Widgets and/or errors.
	 */
	public static function validateWidgets(array $widgets, string $templateid = null): array {
		$errors = [];

		foreach ($widgets as $index => &$widget) {
			$widget_errors = [];

			if (!array_key_exists('pos', $widget)) {
				$widget_errors[] = _s('Invalid parameter "%1$s": %2$s.', 'widgets['.$index.']',
					_s('the parameter "%1$s" is missing', 'pos')
				);
			}
			else {
				foreach (['x', 'y', 'width', 'height'] as $field) {
					if (!is_array($widget['pos']) || !array_key_exists($field, $widget['pos'])) {
						$widget_errors[] = _s('Invalid parameter "%1$s": %2$s.', 'widgets['.$index.'][pos]',
							_s('the parameter "%1$s" is missing', $field)
						);
					}
				}
			}

			if (!array_key_exists('type', $widget)) {
				$widget_errors[] = _s('Invalid parameter "%1$s": %2$s.', 'widgets['.$index.']',
					_s('the parameter "%1$s" is missing', 'type')
				);
			}

			if (!array_key_exists('name', $widget)) {
				$widget_errors[] = _s('Invalid parameter "%1$s": %2$s.', 'widgets['.$index.']',
					_s('the parameter "%1$s" is missing', 'name')
				);
			}

			if (!array_key_exists('view_mode', $widget)) {
				$widget_errors[] = _s('Invalid parameter "%1$s": %2$s.', 'widgets['.$index.']',
					_s('the parameter "%1$s" is missing', 'view_mode')
				);
			}

			if ($widget_errors) {
				$errors = array_merge($errors, $widget_errors);

				break;
			}

			$widget_fields = array_key_exists('fields', $widget) ? $widget['fields'] : '{}';
			$widget['form'] = CWidgetConfig::getForm($widget['type'], $widget_fields, $templateid);
			unset($widget['fields']);

			if ($widget_errors = $widget['form']->validate()) {
				if ($widget['name'] === '') {
					$context = $this->hasInput('templateid')
						? CWidgetConfig::CONTEXT_TEMPLATE_DASHBOARD
						: CWidgetConfig::CONTEXT_DASHBOARD;

					$widget_name = CWidgetConfig::getKnownWidgetTypes($context)[$widget['type']];
				}
				else {
					$widget_name = $widget['name'];
				}

				foreach ($widget_errors as $error) {
					$errors[] = _s('Cannot save widget "%1$s".', $widget_name).' '.$error;
				}
			}
		}
		unset($widget);

		return [
			'widgets' => $widgets,
			'errors' => $errors
		];
	}

	/**
	 * Prepare data for cloning template dashboards.
	 * Replace item and graph ids to new ids.
	 *
	 * @static
	 *
	 * @param array  $dashboards  Dashboards array.
	 * @param string $templateid  New template id.
	 *
	 * @return array
	 */
	public static function prepareForClone(array $dashboards, $templateid): array {
		foreach ($dashboards as &$dashboard) {
			// Remove dashboard id.
			unset($dashboard['dashboardid']);
			// Replace template id.
			$dashboard['templateid'] = $templateid;

			if ($dashboard['widgets']) {
				foreach ($dashboard['widgets'] as &$widget) {
					$items = [];
					$graphs = [];

					// Remove widget id from array.
					unset($widget['widgetid']);

					foreach ($widget['fields'] as $field) {
						switch ($field['type']) {
							case ZBX_WIDGET_FIELD_TYPE_ITEM:
							case ZBX_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE:
								$items[$field['value']] = true;
								break;

							case ZBX_WIDGET_FIELD_TYPE_GRAPH:
							case ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE:
								$graphs[$field['value']] = true;
								break;
						}
					}

					if ($items) {
						$db_items = DBselect(
							'SELECT src.itemid AS srcid,dest.itemid as destid'.
							' FROM items dest,items src'.
							' WHERE dest.key_=src.key_'.
								' AND dest.hostid='.zbx_dbstr($templateid).
								' AND '.dbConditionInt('src.itemid', array_keys($items))
						);
						while ($db_item = DBfetch($db_items)) {
							$items[$db_item['srcid']] = $db_item['destid'];
						}
					}

					if ($graphs) {
						$db_graphs = DBselect(
							'SELECT src.graphid AS srcid,dest.graphid as destid'.
							' FROM graphs dest,graphs src,graphs_items destgi,items desti'.
							' WHERE dest.name=src.name'.
								' AND destgi.graphid=dest.graphid'.
								' AND destgi.itemid=desti.itemid'.
								' AND desti.hostid='.zbx_dbstr($templateid).
								' AND '.dbConditionInt('src.graphid', array_keys($graphs))
						);
						while ($db_graph = DBfetch($db_graphs)) {
							$graphs[$db_graph['srcid']] = $db_graph['destid'];
						}
					}

					foreach ($widget['fields'] as &$field) {
						switch ($field['type']) {
							case ZBX_WIDGET_FIELD_TYPE_ITEM:
							case ZBX_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE:
								$field['value'] = $items[$field['value']];
								break;

							case ZBX_WIDGET_FIELD_TYPE_GRAPH:
							case ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE:
								$field['value'] = $graphs[$field['value']];
								break;
						}
					}
					unset($field);
				}
				unset ($widget);
			}
		}
		unset($dashboard);

		return $dashboards;
	}
}
