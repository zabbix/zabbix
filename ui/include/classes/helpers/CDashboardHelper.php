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
	 * @param string $userid
	 *
	 * @return string
	 */
	public static function getOwnerName($userid) {
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
	 * @param array $dashboards  An associative array of the dashboards.
	 */
	public static function updateEditableFlag(array &$dashboards) {
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
					$rf_rate = CProfile::get('web.dashbrd.widget.rf_rate', null, $widgetid);

					if ($rf_rate === null) {
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
	 * @param array $fields  fields as received from API
	 *
	 * @static
	 *
	 * @return array
	 */
	public static function convertWidgetFields($fields) {
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
	 * @param array $widgets
	 *
	 * @static
	 *
	 * @return bool
	 */
	public static function hasTimeSelector(array $widgets) {
		foreach ($widgets as $widget) {
			if (CWidgetConfig::usesTimeSelector($widget)) {
				return true;
			}
		}
		return false;
	}
}
