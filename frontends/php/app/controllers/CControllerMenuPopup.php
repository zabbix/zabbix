<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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


class CControllerMenuPopup extends CController {

	protected function checkInput() {
		$fields = [
			'type' => 'required|in dashboard,history,host,item,item_prototype,map_element,refresh,trigger,trigger_macro',
			'data' => 'array'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$output = [];
			if (($messages = getMessages()) !== null) {
				$output['errors'] = $messages->toString();
			}

			$this->setResponse(new CControllerResponseData(['main_block' => CJs::encodeJson($output)]));
		}

		return $ret;
	}

	protected function checkPermissions() {
		return true;
	}

	/**
	 * Prepare data for dashboard context menu popup.
	 *
	 * @param array  $data
	 * @param string $data['dashboardid']
	 *
	 * @return mixed
	 */
	private static function getMenuDataDashboard(array $data) {
		$db_dashboards = API::Dashboard()->get([
			'output' => [],
			'dashboardids' => $data['dashboardid'],
		]);

		if ($db_dashboards) {
			$db_dashboard = $db_dashboards[0];

			return [
				'type' => 'dashboard',
				'dashboardid' => $data['dashboardid'],
				'editable' => (bool) API::Dashboard()->get([
					'output' => [],
					'dashboardids' => $data['dashboardid'],
					'editable' => true
				])
			];
		}

		error(_('No permissions to referred object or it does not exist!'));

		return null;
	}

	/**
	 * Prepare data for history context menu popup.
	 *
	 * @param array  $data
	 * @param string $data['itemid']
	 *
	 * @return mixed
	 */
	private static function getMenuDataHistory(array $data) {
		$db_items = API::Item()->get([
			'output' => ['value_type'],
			'itemids' => $data['itemid'],
			'webitems' => true
		]);

		if ($db_items) {
			$db_item = $db_items[0];

			return [
				'type' => 'history',
				'itemid' => $data['itemid'],
				'hasLatestGraphs' => in_array($db_item['value_type'], [ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_FLOAT])
			];
		}

		error(_('No permissions to referred object or it does not exist!'));

		return null;
	}

	/**
	 * Prepare data for host context menu popup.
	 *
	 * @param array  $data
	 * @param string $data['hostid']
	 * @param bool   $data['has_goto']           (optional) Can be used to hide "GO TO" menu section. Default: true.
	 * @param int    $data['severity_min']       (optional)
	 * @param bool   $data['show_suppressed']    (optional)
	 * @param array  $data['urls']               (optional)
	 * @param string $data['urls']['label']
	 * @param string $data['urls']['url']
	 * @param string $data['filter_application'] (optional) Application name for filter by application.
	 *
	 * @return mixed
	 */
	private static function getMenuDataHost(array $data) {
		$has_goto = !array_key_exists('has_goto', $data) || $data['has_goto'];

		$db_hosts = $has_goto
			? API::Host()->get([
				'output' => ['status'],
				'selectGraphs' => API_OUTPUT_COUNT,
				'selectScreens' => API_OUTPUT_COUNT,
				'hostids' => $data['hostid']
			])
			: API::Host()->get([
				'output' => [],
				'hostids' => $data['hostid']
			]);

		if ($db_hosts) {
			$db_host = $db_hosts[0];

			$scripts = API::Script()->getScriptsByHosts([$data['hostid']])[$data['hostid']];

			foreach ($scripts as &$script) {
				$script['name'] = trimPath($script['name']);
			}
			unset($script);

			CArrayHelper::sort($scripts, ['name']);

			$menu_data = [
				'type' => 'host',
				'hostid' => $data['hostid'],
				'hasGoTo' => (bool) $has_goto
			];

			if ($has_goto) {
				$menu_data['showGraphs'] = (bool) $db_host['graphs'];
				$menu_data['showScreens'] = (bool) $db_host['screens'];
				$menu_data['showTriggers'] = ($db_host['status'] == HOST_STATUS_MONITORED);
				if (array_key_exists('severity_min', $data)) {
					$menu_data['severity_min'] = $data['severity_min'];
				}
				if (array_key_exists('show_suppressed', $data)) {
					$menu_data['show_suppressed'] = $data['show_suppressed'];
				}
			}

			foreach (array_values($scripts) as $script) {
				$menu_data['scripts'][] = [
					'name' => $script['name'],
					'scriptid' => $script['scriptid'],
					'confirmation' => $script['confirmation']
				];
			}

			if (array_key_exists('urls', $data)) {
				$menu_data['urls'] = $data['urls'];
			}

			if (array_key_exists('filter_application', $data)) {
				$menu_data['filter_application'] = $data['filter_application'];
			}

			return $menu_data;
		}

		error(_('No permissions to referred object or it does not exist!'));

		return null;
	}

	/**
	 * Prepare data for item context menu popup.
	 *
	 * @param array  $data
	 * @param string $data['itemid']
	 *
	 * @return mixed
	 */
	private static function getMenuDataItem(array $data) {
		$db_items = API::Item()->get([
			'output' => ['hostid', 'name', 'value_type', 'flags'],
			'itemids' => $data['itemid'],
			'webitems' => true
		]);

		if ($db_items) {
			$db_item = $db_items[0];
			$menu_data = [
				'type' => 'item',
				'itemid' => $data['itemid'],
				'hostid' => $db_item['hostid'],
				'name' => $db_item['name'],
				'create_dependent_item' => ($db_item['flags'] != ZBX_FLAG_DISCOVERY_CREATED),
				'create_dependent_discovery' => ($db_item['flags'] != ZBX_FLAG_DISCOVERY_CREATED)
			];

			if (in_array($db_item['value_type'], [ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_TEXT])) {
				$db_triggers = API::Trigger()->get([
					'output' => ['triggerid', 'description', 'recovery_mode'],
					'selectFunctions' => API_OUTPUT_EXTEND,
					'itemids' => $data['itemid']
				]);

				$menu_data['show_triggers'] = true;
				$menu_data['triggers'] = [];

				foreach ($db_triggers as $db_trigger) {
					if ($db_trigger['recovery_mode'] == ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION) {
						continue;
					}

					foreach ($db_trigger['functions'] as $function) {
						if (!str_in_array($function['function'], ['regexp', 'iregexp'])) {
							continue 2;
						}
					}

					$menu_data['triggers'][] = [
						'triggerid' => $db_trigger['triggerid'],
						'name' => $db_trigger['description']
					];
				}
			}

			return $menu_data;
		}

		error(_('No permissions to referred object or it does not exist!'));

		return null;
	}

	/**
	 * Prepare data for item prototype context menu popup.
	 *
	 * @param array  $data
	 * @param string $data['itemid']
	 *
	 * @return mixed
	 */
	private static function getMenuDataItemPrototype(array $data) {
		$db_item_prototypes = API::ItemPrototype()->get([
			'output' => ['name'],
			'selectDiscoveryRule' => ['itemid'],
			'itemids' => $data['itemid']
		]);

		if ($db_item_prototypes) {
			$db_item_prototype = $db_item_prototypes[0];

			return [
				'type' => 'item_prototype',
				'itemid' => $data['itemid'],
				'name' => $db_item_prototype['name'],
				'parent_discoveryid' => $db_item_prototype['discoveryRule']['itemid']
			];
		}

		error(_('No permissions to referred object or it does not exist!'));

		return null;
	}

	/**
	 * Combines map URLs with element's URLs and performs other modifications with the URLs.
	 *
	 * @param array  $selement
	 * @param array  $map_urls
	 *
	 * @return array
	 */
	private static function prepareMapElementUrls(array $selement, array $map_urls) {
		// Remove unused selement url data.
		foreach ($selement['urls'] as &$url) {
			unset($url['sysmapelementurlid'], $url['selementid']);
		}
		unset($url);

		// Add map urls to element's urls based on their type.
		foreach ($map_urls as $map_url) {
			if ($selement['elementtype'] == $map_url['elementtype']) {
				unset($map_url['elementtype']);
				$selement['urls'][] = $map_url;
			}
		}

		$selement = CMacrosResolverHelper::resolveMacrosInMapElements([$selement], ['resolve_element_urls' => true])[0];

		// Unset URLs with empty name or value.
		foreach ($selement['urls'] as $url_nr => $url) {
			if ($url['name'] === '' || $url['url'] === '') {
				unset($selement['urls'][$url_nr]);
			}
			elseif (CHtmlUrlValidator::validate($url['url']) === false) {
				$selement['urls'][$url_nr]['url'] = 'javascript: alert(\''._s('Provided URL "%1$s" is invalid.',
					zbx_jsvalue($url['url'], false, false)).'\');';
			}
		}

		CArrayHelper::sort($selement['urls'], ['name']);
		// Prepare urls for processing in menupopup.js.
		$selement['urls'] = CArrayHelper::renameObjectsKeys($selement['urls'], ['name' => 'label']);

		return $selement;
	}

	/**
	 * Prepare data for map element context menu popup.
	 *
	 * @param array  $data
	 * @param string $data['sysmapid']
	 * @param string $data['selementid']
	 * @param array  $data['options']        (optional)
	 * @param int    $data['severity_min']   (optional)
	 * @param string $data['widget_uniqueid] (optional)
	 * @param string $data['hostid']         (optional)
	 *
	 * @return mixed
	 */
	private static function getMenuDataMapElement(array $data) {
		$db_maps = API::Map()->get([
			'output' => ['show_suppressed'],
			'selectSelements' => ['selementid', 'elementtype', 'elementsubtype', 'elements', 'urls', 'application'],
			'selectUrls' => ['name', 'url', 'elementtype'],
			'sysmapids' => $data['sysmapid']
		]);

		if ($db_maps) {
			$db_map = $db_maps[0];
			$selement = null;

			foreach ($db_map['selements'] as $db_selement) {
				if (bccomp($db_selement['selementid'], $data['selementid']) == 0) {
					$selement = $db_selement;
					break;
				}
			}

			if ($selement !== null) {
				if ($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_HOST_GROUP
						&& $selement['elementsubtype'] == SYSMAP_ELEMENT_SUBTYPE_HOST_GROUP_ELEMENTS
						&& array_key_exists('hostid', $data) && $data['hostid'] != 0) {
					$selement['elementtype'] = SYSMAP_ELEMENT_TYPE_HOST;
					$selement['elementsubtype'] = SYSMAP_ELEMENT_SUBTYPE_HOST_GROUP;
					$selement['elements'][0]['hostid'] = $data['hostid'];
				}

				$selement = self::prepareMapElementUrls($selement, $db_map['urls']);

				switch ($selement['elementtype']) {
					case SYSMAP_ELEMENT_TYPE_MAP:
						$menu_data = [
							'type' => 'map_element_submap',
							'sysmapid' => $selement['elements'][0]['sysmapid']
						];
						if (array_key_exists('severity_min', $data)) {
							$menu_data['severity_min'] = $data['severity_min'];
						}
						if (array_key_exists('widget_uniqueid', $data)) {
							$menu_data['widget_uniqueid'] = $data['widget_uniqueid'];
						}
						if ($selement['urls']) {
							$menu_data['urls'] = $selement['urls'];
						}
						return $menu_data;

					case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
						$menu_data = [
							'type' => 'map_element_group',
							'groupid' => $selement['elements'][0]['groupid']
						];
						if (array_key_exists('severity_min', $data)) {
							$menu_data['severity_min'] = $data['severity_min'];
						}
						if ($db_map['show_suppressed']) {
							$menu_data['show_suppressed'] = true;
						}
						if ($selement['urls']) {
							$menu_data['urls'] = $selement['urls'];
						}
						if ($selement['application'] !== '') {
							$menu_data['filter_application'] = $selement['application'];
						}
						return $menu_data;

					case SYSMAP_ELEMENT_TYPE_HOST:
						$host_data = [
							'hostid' => $selement['elements'][0]['hostid']
						];
						if (array_key_exists('severity_min', $data)) {
							$host_data['severity_min'] = $data['severity_min'];
						}
						if ($db_map['show_suppressed']) {
							$host_data['show_suppressed'] = true;
						}
						if ($selement['urls']) {
							$host_data['urls'] = $selement['urls'];
						}
						if ($selement['application'] !== '') {
							$host_data['filter_application'] = $selement['application'];
						}
						return self::getMenuDataHost($host_data);

					case SYSMAP_ELEMENT_TYPE_TRIGGER:
						$menu_data = [
							'type' => 'map_element_trigger',
							'triggerids' => zbx_objectValues($selement['elements'], 'triggerid')
						];
						if (array_key_exists('severity_min', $data)) {
							$menu_data['severity_min'] = $data['severity_min'];
						}
						if ($db_map['show_suppressed']) {
							$menu_data['show_suppressed'] = true;
						}
						if ($selement['urls']) {
							$menu_data['urls'] = $selement['urls'];
						}
						return $menu_data;

					case SYSMAP_ELEMENT_TYPE_IMAGE:
						$menu_data = [
							'type' => 'map_element_image',
						];
						if ($selement['urls']) {
							$menu_data['urls'] = $selement['urls'];
						}
						return $menu_data;
				}
			}
		}

		error(_('No permissions to referred object or it does not exist!'));

		return null;
	}

	/**
	 * Prepare data for refresh menu popup.
	 *
	 * @param array  $data
	 * @param string $data['widgetName']
	 * @param string $data['currentRate']
	 * @param bool   $data['multiplier']   Multiplier or time mode.
	 * @param array  $data['params']       (optional) URL parameters.
	 *
	 * @return mixed
	 */
	private static function getMenuDataRefresh(array $data) {
		$menu_data = [
			'type' => 'refresh',
			'widgetName' => $data['widgetName'],
			'currentRate' => $data['currentRate'],
			'multiplier' => (bool) $data['multiplier']
		];

		if (array_key_exists('params', $data)) {
			$menu_data['params'] = $data['params'];
		}

		return $menu_data;
	}

	/**
	 * Prepare data for trigger context menu popup.
	 *
	 * @param array  $data
	 * @param string $data['triggerid']
	 * @param string $data['eventid']                 (optional) Mandatory for Acknowledge and Description menus.
	 * @param array  $data['acknowledge']             (optional) Acknowledge link parameters.
	 * @param string $data['acknowledge']['backurl']
	 * @param int    $data['severity_min']            (optional)
	 * @param bool   $data['show_suppressed']         (optional)
	 * @param array  $data['urls']                    (optional)
	 * @param string $data['urls']['name']
	 * @param string $data['urls']['url']
	 * @param bool   $data['show_description']        (optional) default: true
	 *
	 * @return mixed
	 */
	private static function getMenuDataTrigger(array $data) {
		$db_triggers = API::Trigger()->get([
			'output' => ['expression', 'url', 'comments'],
			'selectHosts' => ['hostid', 'name', 'status'],
			'selectItems' => ['itemid', 'hostid', 'name', 'key_', 'value_type'],
			'triggerids' => $data['triggerid'],
			'preservekeys' => true
		]);

		if ($db_triggers) {
			$db_triggers = CMacrosResolverHelper::resolveTriggerUrls($db_triggers);

			$db_trigger = reset($db_triggers);
			$db_trigger['items'] = CMacrosResolverHelper::resolveItemNames($db_trigger['items']);

			$hosts = [];
			$show_events = true;

			foreach ($db_trigger['hosts'] as $host) {
				$hosts[$host['hostid']] = $host['name'];

				if ($host['status'] != HOST_STATUS_MONITORED) {
					$show_events = false;
				}
			}
			unset($db_trigger['hosts']);

			foreach ($db_trigger['items'] as &$item) {
				$item['hostname'] = $hosts[$item['hostid']];
			}
			unset($item);

			CArrayHelper::sort($db_trigger['items'], ['name', 'hostname', 'itemid']);

			$with_hostname = count($hosts) > 1;
			$items = [];

			foreach ($db_trigger['items'] as $item) {
				$items[] = [
					'name' => $with_hostname
						? $item['hostname'].NAME_DELIMITER.$item['name_expanded']
						: $item['name_expanded'],
					'params' => [
						'itemid' => $item['itemid'],
						'action' => in_array($item['value_type'], [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64])
							? HISTORY_GRAPH
							: HISTORY_VALUES
					]
				];
			}

			$options = [
				'show_description' => !array_key_exists('show_description', $data) || $data['show_description']
			];

			if ($options['show_description']) {
				$rw_triggers = API::Trigger()->get([
					'output' => ['flags'],
					'triggerids' => $data['triggerid'],
					'editable' => true
				]);

				$options['description_enabled'] = ($db_trigger['comments'] !== ''
					|| ($rw_triggers && $rw_triggers[0]['flags'] == ZBX_FLAG_DISCOVERY_NORMAL));
			}

			$menu_data = [
				'type' => 'trigger',
				'triggerid' => $data['triggerid'],
				'items' => $items,
				'showEvents' => $show_events,
				'configuration' =>
					in_array(CWebUser::$data['type'], [USER_TYPE_ZABBIX_ADMIN, USER_TYPE_SUPER_ADMIN])
			];

			if (!$options['show_description']) {
				$menu_data['show_description'] = false;
			}
			else if (!$options['description_enabled']) {
				$menu_data['description_enabled'] = false;
			}

			if (array_key_exists('eventid', $data)) {
				$menu_data['eventid'] = $data['eventid'];
			}

			if (array_key_exists('acknowledge', $data)) {
				$menu_data['acknowledge'] = $data['acknowledge'];
			}

			if ($db_trigger['url'] !== '') {
				$menu_data['url'] = CHtmlUrlValidator::validate($db_trigger['url'])
					? $db_trigger['url']
					: 'javascript: alert(\''._s('Provided URL "%1$s" is invalid.', zbx_jsvalue($db_trigger['url'],
							false, false)).'\');';
			}

			return $menu_data;
		}

		error(_('No permissions to referred object or it does not exist!'));

		return null;
	}

	/**
	 * Prepare data for trigger macro context menu popup.
	 *
	 * @return array
	 */
	private static function getMenuDataTriggerMacro() {
		return ['type' => 'trigger_macro'];
	}

	protected function doAction() {
		$data = $this->hasInput('data') ? $this->getInput('data') : [];

		switch ($this->getInput('type')) {
			case 'dashboard':
				$menu_data = self::getMenuDataDashboard($data);
				break;

			case 'history':
				$menu_data = self::getMenuDataHistory($data);
				break;

			case 'host':
				$menu_data = self::getMenuDataHost($data);
				break;

			case 'item':
				$menu_data = self::getMenuDataItem($data);
				break;

			case 'item_prototype':
				$menu_data = self::getMenuDataItemPrototype($data);
				break;

			case 'map_element':
				$menu_data = self::getMenuDataMapElement($data);
				break;

			case 'refresh':
				$menu_data = self::getMenuDataRefresh($data);
				break;

			case 'trigger':
				$menu_data = self::getMenuDataTrigger($data);
				break;

			case 'trigger_macro':
				$menu_data = self::getMenuDataTriggerMacro();
				break;
		}

		$output = [];

		if ($menu_data !== null) {
			$output['data'] = $menu_data;
		}

		if (($messages = getMessages()) !== null) {
			$output['errors'] = $messages->toString();
		}

		$this->setResponse(new CControllerResponseData(['main_block' => CJs::encodeJson($output)]));
	}
}
