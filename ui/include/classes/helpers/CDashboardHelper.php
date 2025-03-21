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


use Zabbix\Core\{
	CModule,
	CWidget
};

use Zabbix\Widgets\CWidgetField;

class CDashboardHelper {

	/**
	 * Get dashboard owner name.
	 */
	public static function getOwnerName(string $userid): string {
		$users = API::User()->get([
			'output' => ['name', 'surname', 'username'],
			'userids' => $userid
		]);

		return $users ? getUserFullname($users[0]) : _('Inaccessible user');
	}

	/**
	 * Update editable flag.
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
	 * Prepare dashboard pages and widgets for the presentation.
	 *
	 * @param array       $pages         Dashboard pages with widgets as returned by the dashboard API.
	 * @param string|null $templateid    Template ID, if used.
	 * @param bool        $with_rf_rate  Supply refresh rates for widgets, for the current user.
	 *
	 * @return array
	 */
	public static function preparePages(array $pages, ?string $templateid, bool $with_rf_rate): array {
		$prepared_pages = [];

		foreach ($pages as $page) {
			$prepared_widgets = [];

			CArrayHelper::sort($page['widgets'], ['y', 'x']);

			foreach ($page['widgets'] as $widget_data) {
				$prepared_widget = [
					'widgetid' => $widget_data['widgetid'],
					'type' => $widget_data['type'],
					'name' => $widget_data['name'],
					'view_mode' => (int) $widget_data['view_mode'],
					'pos' => [
						'x' => (int) $widget_data['x'],
						'y' => (int) $widget_data['y'],
						'width' => (int) $widget_data['width'],
						'height' => (int) $widget_data['height']
					],
					'rf_rate' => 0,
					'fields' => [],
					'messages' => []
				];

				/** @var CWidget $widget */
				$widget = APP::ModuleManager()->getModule($widget_data['type']);

				if ($widget !== null && $widget->getType() === CModule::TYPE_WIDGET) {
					$form = $widget->getForm(self::constructWidgetFields($widget_data['fields']), $templateid);

					$prepared_widget['messages'] = $form->validate();
					$prepared_widget['fields'] = $form->getFieldsValues();

					if ($with_rf_rate) {
						$rf_rate = (int) CProfile::get('web.dashboard.widget.rf_rate', -1, $widget_data['widgetid']);

						if ($rf_rate == -1) {
							$rf_rate = $prepared_widget['fields']['rf_rate'] == -1
								? $widget->getDefaultRefreshRate()
								: $prepared_widget['fields']['rf_rate'];
						}

						$prepared_widget['rf_rate'] = $rf_rate;
					}
				}

				$prepared_widgets[] = $prepared_widget;
			}

			$prepared_pages[] = [
				'dashboard_pageid' => $page['dashboard_pageid'],
				'name' => $page['name'],
				'display_period' => $page['display_period'],
				'widgets' => $prepared_widgets
			];
		}

		return $prepared_pages;
	}

	/**
	 * Get dashboard data source requirements.
	 *
	 * @param array $prepared_pages  Dashboard pages, prepared and validated using preparePages method.
	 *
	 * @return array
	 */
	public static function getBroadcastRequirements(array $prepared_pages): array {
		$requirements = [];

		foreach ($prepared_pages as $prepared_page) {
			foreach ($prepared_page['widgets'] as $widget_data) {
				foreach ($widget_data['fields'] as $field) {
					if (!is_array($field)) {
						continue;
					}

					$objects = [$field];

					while ($objects) {
						$objects_next = [];

						foreach ($objects as $object) {
							if (array_key_exists(CWidgetField::FOREIGN_REFERENCE_KEY, $object)) {
								[
									'reference' => $reference,
									'type' => $type
								] = CWidgetField::parseTypedReference($object[CWidgetField::FOREIGN_REFERENCE_KEY]);

								if ($reference === CWidgetField::REFERENCE_DASHBOARD) {
									$requirements[$type] = true;
								}
							}
							else {
								foreach ($object as $object_next) {
									if (is_array($object_next)) {
										$objects_next[] = $object_next;
									}
								}
							}

							$objects = $objects_next;
						}
					}
				}
			}
		}

		return $requirements;
	}

	/**
	 * Unset widget fields referring to inaccessible objects.
	 *
	 * @param array $pages  Dashboard pages with widgets, as returned by the dashboard API.
	 *        array $pages[]['widgets']
	 *        array $pages[]['widgets'][]['fields']
	 *        array $pages[]['widgets'][]['fields'][]['type']
	 *        array $pages[]['widgets'][]['fields'][]['value']
	 *
	 * @return array
	 */
	public static function unsetInaccessibleFields(array $pages): array {
		$ids = [
			ZBX_WIDGET_FIELD_TYPE_GROUP => [],
			ZBX_WIDGET_FIELD_TYPE_HOST => [],
			ZBX_WIDGET_FIELD_TYPE_ITEM => [],
			ZBX_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE => [],
			ZBX_WIDGET_FIELD_TYPE_GRAPH => [],
			ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE => [],
			ZBX_WIDGET_FIELD_TYPE_MAP => [],
			ZBX_WIDGET_FIELD_TYPE_SERVICE => [],
			ZBX_WIDGET_FIELD_TYPE_SLA => [],
			ZBX_WIDGET_FIELD_TYPE_USER => [],
			ZBX_WIDGET_FIELD_TYPE_ACTION => [],
			ZBX_WIDGET_FIELD_TYPE_MEDIA_TYPE => []
		];

		foreach ($pages as $p_index => $page) {
			foreach ($page['widgets'] as $w_index => $widget) {
				foreach ($widget['fields'] as $f_index => $field) {
					if (array_key_exists($field['type'], $ids)) {
						$ids[$field['type']][$field['value']][] = ['p' => $p_index, 'w' => $w_index, 'f' => $f_index];
					}
				}
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

		if ($ids[ZBX_WIDGET_FIELD_TYPE_SERVICE]) {
			$db_services = API::Service()->get([
				'output' => [],
				'serviceids' => array_keys($ids[ZBX_WIDGET_FIELD_TYPE_SERVICE]),
				'preservekeys' => true
			]);

			foreach ($ids[ZBX_WIDGET_FIELD_TYPE_SERVICE] as $serviceid => $indexes) {
				if (!array_key_exists($serviceid, $db_services)) {
					$inaccessible_indexes = array_merge($inaccessible_indexes, $indexes);
				}
			}
		}

		if ($ids[ZBX_WIDGET_FIELD_TYPE_SLA]) {
			$db_slas = API::Sla()->get([
				'output' => [],
				'slaids' => array_keys($ids[ZBX_WIDGET_FIELD_TYPE_SLA]),
				'preservekeys' => true
			]);

			foreach ($ids[ZBX_WIDGET_FIELD_TYPE_SLA] as $slaid => $indexes) {
				if (!array_key_exists($slaid, $db_slas)) {
					$inaccessible_indexes = array_merge($inaccessible_indexes, $indexes);
				}
			}
		}

		if ($ids[ZBX_WIDGET_FIELD_TYPE_USER]) {
			$db_users = API::User()->get([
				'output' => [],
				'userids' => array_keys($ids[ZBX_WIDGET_FIELD_TYPE_USER]),
				'preservekeys' => true
			]);

			foreach ($ids[ZBX_WIDGET_FIELD_TYPE_USER] as $userid => $indexes) {
				if (!array_key_exists($userid, $db_users)) {
					$inaccessible_indexes = array_merge($inaccessible_indexes, $indexes);
				}
			}
		}

		if ($ids[ZBX_WIDGET_FIELD_TYPE_ACTION]) {
			$db_actions = API::Action()->get([
				'output' => [],
				'actionids' => array_keys($ids[ZBX_WIDGET_FIELD_TYPE_ACTION]),
				'preservekeys' => true
			]);

			foreach ($ids[ZBX_WIDGET_FIELD_TYPE_ACTION] as $actionid => $indexes) {
				if (!array_key_exists($actionid, $db_actions)) {
					$inaccessible_indexes = array_merge($inaccessible_indexes, $indexes);
				}
			}
		}

		if ($ids[ZBX_WIDGET_FIELD_TYPE_MEDIA_TYPE]) {
			$db_media_types = API::MediaType()->get([
				'output' => [],
				'mediatypeids' => array_keys($ids[ZBX_WIDGET_FIELD_TYPE_MEDIA_TYPE]),
				'preservekeys' => true
			]);

			foreach ($ids[ZBX_WIDGET_FIELD_TYPE_MEDIA_TYPE] as $mediatypeid => $indexes) {
				if (!array_key_exists($mediatypeid, $db_media_types)) {
					$inaccessible_indexes = array_merge($inaccessible_indexes, $indexes);
				}
			}
		}

		foreach ($inaccessible_indexes as $index) {
			unset($pages[$index['p']]['widgets'][$index['w']]['fields'][$index['f']]);
		}

		return $pages;
	}

	/**
	 * Construct widget fields from widget field data returned by the dashboards API.
	 *
	 * @param array $fields
	 *
	 * @return array
	 */
	public static function constructWidgetFields(array $fields): array {
		$fields_new = [];

		foreach ($fields as $field) {
			if (array_key_exists($field['name'], $fields_new)) {
				$fields_new[$field['name']] = (array) $fields_new[$field['name']];
				$fields_new[$field['name']][] = $field['value'];
			}
			else {
				$fields_new[$field['name']] = $field['value'];
			}
		}

		return self::constructWidgetFieldsIntoObjects($fields_new);
	}

	/**
	 * Construct widget fields from destructured objects back into objects.
	 *
	 * Example:
	 *     In: [
	 *         'a.0'       => 'value_1',
	 *         'a.1'       => 'value_2',
	 *         'b.0.c.0.d' => 'value_3'
	 *     ]
	 *
	 *     Out: [
	 *         'a' => ['value_1', 'value_2'],
	 *         'b' => [0 => ['c' => [0 => ['d' => 'value_3']]]]
	 *     ]
	 *
	 * @param array $fields
	 *
	 * @return array
	 */
	private static function constructWidgetFieldsIntoObjects(array $fields): array {
		$fields_new = [];

		uksort($fields,
			static fn(string $key_1, string $key_2): int => strnatcmp($key_1, $key_2)
		);

		foreach ($fields as $key => $value) {
			if (preg_match('/^([a-z_]+)((\\.([a-z_]+|[0-9]+))+)$/', $key, $matches) === 0) {
				$fields_new[$key] = $value;

				continue;
			}

			$field_name = $matches[1];
			$field_path = $matches[2];

			preg_match_all('/\\.([a-z_]+|[0-9]+)/', $field_path, $matches);

			$field_path_keys = array_merge([$field_name], $matches[1]);

			$field_ptr = &$fields_new;

			for ($i = 0, $count = count($field_path_keys); $i < $count; $i++) {
				$field_path_key = $field_path_keys[$i];

				if ($i < $count - 1) {
					if (!array_key_exists($field_path_key, $field_ptr)) {
						$field_ptr[$field_path_key] = [];
					}

					$field_ptr = &$field_ptr[$field_path_key];
				}
				else {
					$field_ptr[$field_path_key] = $value;
				}
			}

			unset($field_ptr);
		}

		return $fields_new;
	}

	/**
	 * Validate input parameters of dashboard pages.
	 *
	 * @var array  $dashboard_pages
	 * @var array  $dashboard_pages[]['widgets']
	 * @var string $dashboard_pages[]['widgets'][]['widgetid']       (optional)
	 * @var array  $dashboard_pages[]['widgets'][]['pos']
	 * @var int    $dashboard_pages[]['widgets'][]['pos']['x']
	 * @var int    $dashboard_pages[]['widgets'][]['pos']['y']
	 * @var int    $dashboard_pages[]['widgets'][]['pos']['width']
	 * @var int    $dashboard_pages[]['widgets'][]['pos']['height']
	 * @var string $dashboard_pages[]['widgets'][]['type']
	 * @var string $dashboard_pages[]['widgets'][]['name']
	 * @var string $dashboard_pages[]['widgets'][]['fields']         (optional) JSON object
	 *
	 * @return array  Widgets and/or errors.
	 */
	public static function validateDashboardPages(array $dashboard_pages, ?string $templateid = null): array {
		$errors = [];

		foreach ($dashboard_pages as $dashboard_page_index => &$dashboard_page) {
			$dashboard_page_errors = [];

			foreach (['name', 'display_period'] as $field) {
				if (!array_key_exists($field, $dashboard_page)) {
					$dashboard_page_errors[] = _s('Invalid parameter "%1$s": %2$s.', 'pages['.$dashboard_page_index.']',
						_s('the parameter "%1$s" is missing', $field)
					);
				}
			}

			if ($dashboard_page_errors) {
				$errors = array_merge($errors, $dashboard_page_errors);

				break;
			}

			if (!array_key_exists('widgets', $dashboard_page)) {
				$dashboard_page['widgets'] = [];
			}

			foreach ($dashboard_page['widgets'] as $widget_index => &$widget_data) {
				$widget_errors = [];

				if (!array_key_exists('pos', $widget_data)) {
					$widget_errors[] = _s('Invalid parameter "%1$s": %2$s.',
						'pages['.$dashboard_page_index.'][widgets]['.$widget_index.']',
						_s('the parameter "%1$s" is missing', 'pos')
					);
				}
				else {
					foreach (['x', 'y', 'width', 'height'] as $field) {
						if (!is_array($widget_data['pos']) || !array_key_exists($field, $widget_data['pos'])) {
							$widget_errors[] = _s('Invalid parameter "%1$s": %2$s.',
								'pages['.$dashboard_page_index.'][widgets]['.$widget_index.'][pos]',
								_s('the parameter "%1$s" is missing', $field)
							);
						}
					}
				}

				foreach (['type', 'name', 'view_mode'] as $field) {
					if (!array_key_exists($field, $widget_data)) {
						$widget_errors[] = _s('Invalid parameter "%1$s": %2$s.',
							'pages['.$dashboard_page_index.'][widgets]['.$widget_index.']',
							_s('the parameter "%1$s" is missing', $field)
						);
					}
				}

				if ($widget_errors) {
					$errors = array_merge($errors, $widget_errors);

					break 2;
				}

				$widget_fields = array_key_exists('fields', $widget_data) ? $widget_data['fields'] : [];
				unset($widget_data['fields']);

				if ($widget_data['type'] === ZBX_WIDGET_INACCESSIBLE) {
					continue;
				}

				$widget = APP::ModuleManager()->getModule($widget_data['type']);

				if ($widget === null || $widget->getType() !== CModule::TYPE_WIDGET) {
					if ($widget_data['name'] !== '') {
						$widget_name = $widget_data['name'];
					}
					else {
						$widget_name = 'pages['.$dashboard_page_index.'][widgets]['.$widget_index.']';
					}

					$errors[] = _s('Cannot save widget "%1$s".', $widget_name).' '._('Inaccessible widget type.');

					continue;
				}

				$widget_name = $widget_data['name'] !== '' ? $widget_data['name'] : $widget->getDefaultName();

				$widget_data['form'] = $widget->getForm($widget_fields, $templateid);

				if ($widget_errors = $widget_data['form']->validate()) {
					foreach ($widget_errors as $error) {
						$errors[] = _s('Cannot save widget "%1$s".', $widget_name).' '.$error;
					}
				}
			}
			unset($widget_data);
		}
		unset($dashboard_page);

		return [
			'dashboard_pages' => $dashboard_pages,
			'errors' => $errors
		];
	}

	/**
	 * Prepare data for cloning template dashboards.
	 * Replace item and graph ids to new ids.
	 *
	 * @param array  $dashboards  Dashboards array.
	 * @param string $templateid  New template id.
	 *
	 * @return array
	 */
	public static function prepareForClone(array $dashboards, $templateid): array {
		foreach ($dashboards as &$dashboard) {
			unset($dashboard['dashboardid'], $dashboard['uuid']);

			$dashboard['templateid'] = $templateid;

			foreach ($dashboard['pages'] as &$dashboard_page) {
				unset($dashboard_page['dashboard_pageid']);

				foreach ($dashboard_page['widgets'] as &$widget) {
					unset($widget['widgetid']);

					$items = [];
					$graphs = [];

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
			unset($dashboard_page);
		}
		unset($dashboard);

		return $dashboards;
	}

	public static function getWidgetLastType(): ?string {
		$known_widgets = APP::ModuleManager()->getWidgets();

		$widget_last_type = CProfile::get('web.dashboard.last_widget_type');

		if (!array_key_exists($widget_last_type, $known_widgets)) {
			$current_types = [];
			$deprecated_types = [];

			/** @var CWidget $widget */
			foreach ($known_widgets as $widget) {
				if (!$widget->isDeprecated()) {
					$current_types[$widget->getId()] = $widget->getDefaultName();
				}
				else {
					$deprecated_types[$widget->getId()] = $widget->getDefaultName();
				}
			}

			natcasesort($current_types);
			natcasesort($deprecated_types);

			if ($current_types) {
				$widget_last_type = array_key_first($current_types);
			}
			elseif ($deprecated_types) {
				$widget_last_type = array_key_first($deprecated_types);
			}
			else {
				$widget_last_type = null;
			}
		}

		return $widget_last_type;
	}

	public static function getConfigurationHash(array $dashboard, array $widget_defaults): string {
		ksort($widget_defaults);

		return md5(json_encode([
			array_intersect_key($dashboard, array_flip(['name', 'display_period', 'auto_start', 'pages'])),
			$widget_defaults
		], JSON_THROW_ON_ERROR));
	}
}
