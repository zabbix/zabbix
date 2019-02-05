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
			'type' => 'required|in history,host,item,item_prototype,map_element,trigger,trigger_macro',
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
	 * Prepare data for history context menu popup.
	 *
	 * @param array  $data
	 * @param string $data['itemid']
	 *
	 * @return mixed
	 */
	private static function getHistoryMenuData(array $data) {
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
	 * @param bool   $data['has_goto']        (optional) Can be used to hide "GO TO" menu section. Default: true.
	 * @param int    $data['severity_min']    (optional)
	 * @param bool   $data['show_suppressed'] (optional)
	 * @param array  $data['urls']            (optional)
	 * @param string $data['urls']['label']
	 * @param string $data['urls']['url']
	 *
	 * @return mixed
	 */
	private static function getHostMenuData(array $data) {
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
	private static function getItemMenuData(array $data) {
		$db_items = API::Item()->get([
			'output' => ['hostid', 'name', 'value_type'],
			'itemids' => $data['itemid'],
			'webitems' => true
		]);

		if ($db_items) {
			$db_item = $db_items[0];
			$triggers = [];

			if (in_array($db_item['value_type'], [ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_TEXT])) {
				$db_triggers = API::Trigger()->get([
					'output' => ['triggerid', 'description', 'recovery_mode'],
					'selectFunctions' => API_OUTPUT_EXTEND,
					'itemids' => $data['itemid']
				]);

				foreach ($db_triggers as $db_trigger) {
					if ($db_trigger['recovery_mode'] == ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION) {
						continue;
					}

					foreach ($db_trigger['functions'] as $function) {
						if (!str_in_array($function['function'], ['regexp', 'iregexp'])) {
							continue 2;
						}
					}

					$triggers[] = [
						'triggerid' => $db_trigger['triggerid'],
						'name' => $db_trigger['description']
					];
				}
			}

			return [
				'type' => 'item',
				'itemid' => $data['itemid'],
				'hostid' => $db_item['hostid'],
				'name' => $db_item['name'],
				'triggers' => $triggers
			];
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
	private static function getItemPrototypeMenuData(array $data) {
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
	 * Prepare data for map element context menu popup.
	 *
	 * @param array  $data
	 * @param string $data['sysmapid']
	 * @param string $data['selementid']
	 * @param array  $data['options']       (optional)
	 * @param int    $data['severity_min']  (optional)
	 * @param string $data['hostid']        (optional)
	 *
	 * @return mixed
	 */
	private static function getMapElementMenuData(array $data) {
		$db_maps = API::Map()->get([
			'output' => ['show_suppressed'],
			'selectSelements' => ['selementid', 'elementtype', 'elementsubtype', 'elements', 'urls'],
			'sysmapids' => $data['sysmapid'],
			'expandUrls' => true
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
				CArrayHelper::sort($selement['urls'], ['name']);
				$selement['urls'] = CArrayHelper::renameObjectsKeys($selement['urls'], ['name' => 'label']);

				if ($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_HOST_GROUP
						&& $selement['elementsubtype'] == SYSMAP_ELEMENT_SUBTYPE_HOST_GROUP_ELEMENTS
						&& array_key_exists('hostid', $data) && $data['hostid'] != 0) {
					$selement['elementtype'] = SYSMAP_ELEMENT_TYPE_HOST;
					$selement['elementsubtype'] = SYSMAP_ELEMENT_SUBTYPE_HOST_GROUP;
					$selement['elements'][0]['hostid'] = $data['hostid'];

					/*
					 * Expanding host URL macros again as some hosts were added from hostgroup areas and automatic
					 * expanding only happens for elements that are defined for map in DB.
					 */
					foreach ($selement['urls'] as &$url) {
						$url['url'] = str_replace('{HOST.ID}', $data['hostid'], $url['url']);
					}
					unset($url);
				}

				switch ($selement['elementtype']) {
					case SYSMAP_ELEMENT_TYPE_MAP:
						$menu_data = [
							'type' => 'map_element_submap',
							'sysmapid' => $selement['elements'][0]['sysmapid']
						];
						if (array_key_exists('severity_min', $data)) {
							$menu_data['severity_min'] = $data['severity_min'];
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
						return self::getHostMenuData($host_data);

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
				}
			}
		}

		error(_('No permissions to referred object or it does not exist!'));

		return null;
	}

	/**
	 * Prepare data for trigger context menu popup.
	 *
	 * @param array  $data
	 * @param string $data['triggerid']
	 * @param array  $data['acknowledge']             (optional) Acknowledge link parameters.
	 * @param string $data['acknowledge']['eventid']
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
	private static function getTriggerMenuData(array $data) {
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
						? $$item['hostname'].NAME_DELIMITER.$item['name_expanded']
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

				$editable = (bool) $rw_triggers;
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
	private static function getTriggerMacroMenuData() {
		return ['type' => 'trigger_macro'];
	}

	protected function doAction() {
		$data = $this->hasInput('data') ? $this->getInput('data') : [];

		switch ($this->getInput('type')) {
			case 'history':
				$menu_data = self::getHistoryMenuData($data);
				break;

			case 'host':
				$menu_data = self::getHostMenuData($data);
				break;

			case 'item':
				$menu_data = self::getItemMenuData($data);
				break;

			case 'item_prototype':
				$menu_data = self::getItemPrototypeMenuData($data);
				break;

			case 'map_element':
				$menu_data = self::getMapElementMenuData($data);
				break;

			case 'trigger':
				$menu_data = self::getTriggerMenuData($data);
				break;

			case 'trigger_macro':
				$menu_data = self::getTriggerMacroMenuData();
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
