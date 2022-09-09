<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
			'type' => 'required|in history,host,item,item_configuration,item_prototype_configuration,map_element,trigger,trigger_macro',
			'data' => 'array'
		];

		$ret = $this->validateInput($fields) && $this->validateInputData();

		if (!$ret) {
			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])])
			);
		}

		return $ret;
	}

	protected function validateInputData(): bool {
		$type = $this->getInput('type');

		switch ($type) {
			case 'host':
				$rules = [
					'hostid' => 'required|db hosts.hostid',
					'has_goto' => 'in 0'
				];
				break;

			case 'history':
			case 'item':
				$rules = [
					'itemid' => 'required|db items.itemid'
				];
				break;

			case 'item_configuration':
			case 'item_prototype_configuration':
				$rules = [
					'itemid' => 'required|db items.itemid',
					'backurl' => 'required|string'
				];
				break;

			case 'map_element':
				$rules = [
					'sysmapid' => 'required|db sysmaps.sysmapid',
					'selementid' => 'required|db sysmaps_elements.selementid',
					'unique_id' => 'string',
					'severity_min' => 'in '.implode(',', [TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_INFORMATION, TRIGGER_SEVERITY_WARNING, TRIGGER_SEVERITY_AVERAGE, TRIGGER_SEVERITY_HIGH, TRIGGER_SEVERITY_DISASTER]),
					'hostid' => 'db hosts.hostid'
				];
				break;

			case 'trigger':
				$rules = [
					'triggerid' => 'required|db triggers.triggerid',
					'eventid' => 'db events.eventid',
					'acknowledge' => 'in 0,1'
				];
				break;

			case 'trigger_macro':
				$rules = [];
				break;
		}

		$this->input['data'] = array_intersect_key($this->getInput('data', []), $rules);

		$validator = new CNewValidator($this->input['data'], $rules);
		array_map('error', $validator->getAllErrors());

		return !$validator->isError() && !$validator->isErrorFatal();
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
				'hasLatestGraphs' => in_array($db_item['value_type'], [ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_FLOAT]),
				'allowed_ui_latest_data' => CWebUser::checkAccess(CRoleHelper::UI_MONITORING_LATEST_DATA)
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
	 * @param array  $data['tags']               (optional)
	 * @param array  $data['evaltype']           (optional)
	 * @param array  $data['urls']               (optional)
	 * @param string $data['urls']['label']
	 * @param string $data['urls']['url']
	 *
	 * @return mixed
	 */
	private static function getMenuDataHost(array $data) {
		$has_goto = !array_key_exists('has_goto', $data) || $data['has_goto'];

		$db_hosts = $has_goto
			? API::Host()->get([
				'output' => ['hostid', 'status'],
				'selectGraphs' => API_OUTPUT_COUNT,
				'selectHttpTests' => API_OUTPUT_COUNT,
				'hostids' => $data['hostid']
			])
			: API::Host()->get([
				'output' => ['hostid'],
				'hostids' => $data['hostid']
			]);

		if ($db_hosts) {
			$db_host = $db_hosts[0];
			$rw_hosts = false;

			if ($has_goto && CWebUser::getType() > USER_TYPE_ZABBIX_USER) {
				$rw_hosts = (bool) API::Host()->get([
					'output' => [],
					'hostids' => $db_host['hostid'],
					'editable' => true
				]);
			}

			$scripts = CWebUser::checkAccess(CRoleHelper::ACTIONS_EXECUTE_SCRIPTS)
				? API::Script()->getScriptsByHosts([$data['hostid']])[$data['hostid']]
				: [];

			// Filter only host scope scripts, get rid of excess spaces and unify slashes in menu path.
			if ($scripts) {
				foreach ($scripts as $num => &$script) {
					if ($script['scope'] != ZBX_SCRIPT_SCOPE_HOST) {
						unset($scripts[$num]);
						continue;
					}

					$script['menu_path'] = trimPath($script['menu_path']);
					$script['sort'] = '';

					if (strlen($script['menu_path']) > 0) {
						// First or only slash from beginning is trimmed.
						if (substr($script['menu_path'], 0, 1) === '/') {
							$script['menu_path'] = substr($script['menu_path'], 1);
						}

						$script['sort'] = $script['menu_path'];

						// If there is something more, check if last slash is present.
						if (strlen($script['menu_path']) > 0) {
							if (substr($script['menu_path'], -1) !== '/'
									&& substr($script['menu_path'], -2) === '\\/') {
								$script['sort'] = $script['menu_path'].'/';
							}
							else {
								$script['sort'] = $script['menu_path'];
							}

							if (substr($script['menu_path'], -1) === '/'
									&& substr($script['menu_path'], -2) !== '\\/') {
								$script['menu_path'] = substr($script['menu_path'], 0, -1);
							}
						}
					}

					$script['sort'] = $script['sort'].$script['name'];
				}
				unset($script);
			}

			if ($scripts) {
				CArrayHelper::sort($scripts, ['sort']);
			}

			$menu_data = [
				'type' => 'host',
				'hostid' => $data['hostid'],
				'hasGoTo' => (bool) $has_goto,
				'allowed_ui_inventory' => CWebUser::checkAccess(CRoleHelper::UI_INVENTORY_HOSTS),
				'allowed_ui_latest_data' => CWebUser::checkAccess(CRoleHelper::UI_MONITORING_LATEST_DATA),
				'allowed_ui_problems' => CWebUser::checkAccess(CRoleHelper::UI_MONITORING_PROBLEMS),
				'allowed_ui_hosts' => CWebUser::checkAccess(CRoleHelper::UI_MONITORING_HOSTS),
				'allowed_ui_conf_hosts' => CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS)
			];

			if ($has_goto) {
				$menu_data['showGraphs'] = (bool) $db_host['graphs'];
				$menu_data['showDashboards'] = (bool) getHostDashboards($data['hostid']);
				$menu_data['showWeb'] = (bool) $db_host['httpTests'];
				$menu_data['isWriteable'] = $rw_hosts;
				$menu_data['showTriggers'] = ($db_host['status'] == HOST_STATUS_MONITORED);
				if (array_key_exists('severity_min', $data)) {
					$menu_data['severities'] = array_column(
						CSeverityHelper::getSeverities((int) $data['severity_min']),
						'value'
					);
				}
				if (array_key_exists('show_suppressed', $data)) {
					$menu_data['show_suppressed'] = $data['show_suppressed'];
				}
			}

			foreach (array_values($scripts) as $script) {
				$menu_data['scripts'][] = [
					'name' => $script['name'],
					'menu_path' => $script['menu_path'],
					'scriptid' => $script['scriptid'],
					'confirmation' => $script['confirmation']
				];
			}

			if (array_key_exists('urls', $data)) {
				$menu_data['urls'] = $data['urls'];
			}

			if (array_key_exists('tags', $data)) {
				$menu_data['tags'] = $data['tags'];
				$menu_data['evaltype'] = $data['evaltype'];
			}

			return $menu_data;
		}

		error(_('No permissions to referred object or it does not exist!'));

		return null;
	}

	/**
	 * Prepare data for item latest data context menu popup.
	 *
	 * @param array  $data
	 * @param string $data['itemid']
	 *
	 * @return mixed
	 */
	private static function getMenuDataItem(array $data) {
		$db_items = API::Item()->get([
			'output' => ['hostid', 'type', 'value_type', 'history', 'trends'],
			'itemids' => $data['itemid'],
			'webitems' => true
		]);

		if ($db_items) {
			$db_item = $db_items[0];
			$is_writable = false;
			$is_executable = false;

			if ($db_item['type'] != ITEM_TYPE_HTTPTEST) {
				if (CWebUser::getType() == USER_TYPE_SUPER_ADMIN) {
					$is_writable = true;
				}
				elseif (CWebUser::getType() == USER_TYPE_ZABBIX_ADMIN) {
					$is_writable = (bool) API::Host()->get([
						'output' => [],
						'hostids' => $db_item['hostid'],
						'editable' => true
					]);
				}
			}

			if (in_array($db_item['type'], checkNowAllowedTypes())) {
				$is_executable = $is_writable ? true : CWebUser::checkAccess(CRoleHelper::ACTIONS_INVOKE_EXECUTE_NOW);
			}

			return [
				'type' => 'item',
				'itemid' => $data['itemid'],
				'hostid' => $db_item['hostid'],
				'showGraph' => ($db_item['value_type'] == ITEM_VALUE_TYPE_FLOAT
					|| $db_item['value_type'] == ITEM_VALUE_TYPE_UINT64
				),
				'history' => $db_item['history'] != 0,
				'trends' => $db_item['trends'] != 0,
				'isWriteable' => $is_writable,
				'isExecutable' => $is_executable,
				'allowed_ui_conf_hosts' => CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS)
			];
		}

		error(_('No permissions to referred object or it does not exist!'));

		return null;
	}

	/**
	 * Prepare data for item configuration context menu popup.
	 *
	 * @param array  $data
	 * @param string $data['itemid']
	 * @param string $data['backurl']
	 *
	 * @return mixed
	 */
	private static function getMenuDataItemConfiguration(array $data) {
		$db_items = API::Item()->get([
			'output' => ['hostid', 'key_', 'name', 'flags'],
			'selectHosts' => ['host'],
			'itemids' => $data['itemid'],
			'webitems' => true
		]);

		if ($db_items) {
			$db_item = $db_items[0];
			$menu_data = [
				'type' => 'item_configuration',
				'backurl' => $data['backurl'],
				'itemid' => $data['itemid'],
				'hostid' => $db_item['hostid'],
				'host' => $db_item['hosts'][0]['host'],
				'name' => $db_item['name'],
				'key' => $db_item['key_'],
				'create_dependent_item' => ($db_item['flags'] != ZBX_FLAG_DISCOVERY_CREATED),
				'create_dependent_discovery' => ($db_item['flags'] != ZBX_FLAG_DISCOVERY_CREATED),
				'allowed_ui_latest_data' => CWebUser::checkAccess(CRoleHelper::UI_MONITORING_LATEST_DATA),
				'triggers' => []
			];

			$db_triggers = API::Trigger()->get([
				'output' => ['triggerid', 'description'],
				'itemids' => $data['itemid']
			]);

			foreach ($db_triggers as $db_trigger) {
				$menu_data['triggers'][] = [
					'triggerid' => $db_trigger['triggerid'],
					'name' => $db_trigger['description']
				];
			}

			return $menu_data;
		}

		error(_('No permissions to referred object or it does not exist!'));

		return null;
	}

	/**
	 * Prepare data for item prototype configuration context menu popup.
	 *
	 * @param array  $data
	 * @param string $data['itemid']
	 * @param string $data['backurl']
	 *
	 * @return mixed
	 */
	private static function getMenuDataItemPrototypeConfiguration(array $data) {
		$db_item_prototypes = API::ItemPrototype()->get([
			'output' => ['name', 'key_'],
			'selectDiscoveryRule' => ['itemid'],
			'selectHosts' => ['host'],
			'itemids' => $data['itemid']
		]);

		if ($db_item_prototypes) {
			$db_item_prototype = $db_item_prototypes[0];

			$menu_data = [
				'type' => 'item_prototype_configuration',
				'backurl' => $data['backurl'],
				'itemid' => $data['itemid'],
				'name' => $db_item_prototype['name'],
				'key' => $db_item_prototype['key_'],
				'host' => $db_item_prototype['hosts'][0]['host'],
				'parent_discoveryid' => $db_item_prototype['discoveryRule']['itemid'],
				'trigger_prototypes' => []
			];

			$db_trigger_prototypes = API::TriggerPrototype()->get([
				'output' => ['triggerid', 'description'],
				'itemids' => $data['itemid']
			]);

			foreach ($db_trigger_prototypes as $db_trigger_prototype) {
				$menu_data['trigger_prototypes'][] = [
					'triggerid' => $db_trigger_prototype['triggerid'],
					'name' => $db_trigger_prototype['description']
				];
			}
			return $menu_data;
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
			elseif (CHtmlUrlValidator::validate($url['url'], ['allow_user_macro' => false]) === false) {
				$selement['urls'][$url_nr]['url'] = 'javascript: alert(\''._s('Provided URL "%1$s" is invalid.',
					zbx_jsvalue($url['url'], false, false)).'\');';
			}
		}

		CArrayHelper::sort($selement['urls'], ['name']);
		$selement['urls'] = array_values($selement['urls']);

		// Prepare urls for processing in menupopup.js.
		$selement['urls'] = CArrayHelper::renameObjectsKeys($selement['urls'], ['name' => 'label']);

		return $selement;
	}

	/**
	 * Prepare data for map element context menu popup.
	 *
	 * @param array   $data
	 * @param string  $data['sysmapid']
	 * @param string  $data['selementid']
	 * @param int     $data['severity_min']  (optional)
	 * @param int     $data['unique_id']     (optional)
	 * @param string  $data['hostid']        (optional)
	 *
	 * @return mixed
	 */
	private static function getMenuDataMapElement(array $data) {
		$db_maps = API::Map()->get([
			'output' => ['show_suppressed'],
			'selectSelements' => ['selementid', 'elementtype', 'elementsubtype', 'elements', 'urls', 'tags',
				'evaltype'
			],
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
							'sysmapid' => $selement['elements'][0]['sysmapid'],
							'allowed_ui_maps' => CWebUser::checkAccess(CRoleHelper::UI_MONITORING_MAPS)
						];
						if (array_key_exists('severity_min', $data)) {
							$menu_data['severity_min'] = $data['severity_min'];
						}
						if (array_key_exists('unique_id', $data)) {
							$menu_data['unique_id'] = $data['unique_id'];
						}

						if ($selement['urls']) {
							$menu_data['urls'] = $selement['urls'];
						}
						return $menu_data;

					case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
						$menu_data = [
							'type' => 'map_element_group',
							'groupid' => $selement['elements'][0]['groupid'],
							'allowed_ui_problems' => CWebUser::checkAccess(CRoleHelper::UI_MONITORING_PROBLEMS)
						];
						if (array_key_exists('severity_min', $data)) {
							$menu_data['severities'] = array_column(
								CSeverityHelper::getSeverities((int) $data['severity_min']),
								'value'
							);
						}
						if ($db_map['show_suppressed']) {
							$menu_data['show_suppressed'] = true;
						}
						if ($selement['urls']) {
							$menu_data['urls'] = $selement['urls'];
						}
						if ($selement['tags']) {
							$menu_data['evaltype'] = $selement['evaltype'];
							$menu_data['tags'] = $selement['tags'];
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
						if ($selement['tags']) {
							$host_data['evaltype'] = $selement['evaltype'];
							$host_data['tags'] = $selement['tags'];
						}
						return self::getMenuDataHost($host_data);

					case SYSMAP_ELEMENT_TYPE_TRIGGER:
						$menu_data = [
							'type' => 'map_element_trigger',
							'triggerids' => zbx_objectValues($selement['elements'], 'triggerid'),
							'allowed_ui_problems' => CWebUser::checkAccess(CRoleHelper::UI_MONITORING_PROBLEMS)
						];
						if (array_key_exists('severity_min', $data)) {
							$menu_data['severities'] = array_column(
								CSeverityHelper::getSeverities((int) $data['severity_min']),
								'value'
							);
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
							'type' => 'map_element_image'
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
	 * Prepare data for trigger context menu popup.
	 *
	 * @param array  $data
	 * @param string $data['triggerid']
	 * @param string $data['eventid']      (optional) Mandatory for Acknowledge menu.
	 * @param bool   $data['acknowledge']  (optional) Whether to show Acknowledge menu.
	 *
	 * @return mixed
	 */
	private static function getMenuDataTrigger(array $data) {
		$db_triggers = API::Trigger()->get([
			'output' => ['expression', 'url', 'comments', 'manual_close'],
			'selectHosts' => ['hostid', 'name', 'status'],
			'selectItems' => ['itemid', 'hostid', 'name', 'value_type'],
			'triggerids' => $data['triggerid'],
			'preservekeys' => true
		]);

		if ($db_triggers) {
			$db_trigger = reset($db_triggers);

			if (array_key_exists('eventid', $data)) {
				$db_trigger['eventid'] = $data['eventid'];
			}

			$db_trigger['url'] = CMacrosResolverHelper::resolveTriggerUrl($db_trigger, $url) ? $url : '';

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
						? $item['hostname'].NAME_DELIMITER.$item['name']
						: $item['name'],
					'params' => [
						'itemid' => $item['itemid'],
						'action' => in_array($item['value_type'], [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64])
							? HISTORY_GRAPH
							: HISTORY_VALUES
					]
				];
			}

			$menu_data = [
				'type' => 'trigger',
				'triggerid' => $data['triggerid'],
				'items' => $items,
				'showEvents' => $show_events,
				'allowed_ui_problems' => CWebUser::checkAccess(CRoleHelper::UI_MONITORING_PROBLEMS),
				'allowed_ui_conf_hosts' => CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS),
				'allowed_ui_latest_data' => CWebUser::checkAccess(CRoleHelper::UI_MONITORING_LATEST_DATA)
			];

			if ($db_trigger['url'] !== '') {
				$menu_data['urls'][] = [
					'label' => _('Trigger URL'),
					'url' => $db_trigger['url']
				];
			}

			$can_be_closed = ($db_trigger['manual_close'] == ZBX_TRIGGER_MANUAL_CLOSE_ALLOWED
					&& CWebUser::checkAccess(CRoleHelper::ACTIONS_CLOSE_PROBLEMS)
			);
			$event = [];

			if (array_key_exists('eventid', $data)) {
				$menu_data['eventid'] = $data['eventid'];

				$events = API::Event()->get([
					'output' => ['r_eventid', 'urls'],
					'select_acknowledges' => ['action'],
					'eventids' => $data['eventid']
				]);

				if ($events) {
					$event = $events[0];

					if ($can_be_closed) {
						$can_be_closed = !isEventClosed($event);
					}

					foreach ($event['urls'] as $url) {
						$menu_data['urls'][] = [
							'label' => $url['name'],
							'url' => $url['url'],
							'target' => '_blank',
							'rel' => 'noopener'.(ZBX_NOREFERER ? ' noreferrer' : '')
						];
					}
				}
			}

			if (array_key_exists('urls', $menu_data)) {
				foreach ($menu_data['urls'] as &$url) {
					if (!CHtmlUrlValidator::validate($url['url'], ['allow_user_macro' => false])) {
						$url['url'] = 'javascript: alert(\''.
							_s('Provided URL "%1$s" is invalid.', zbx_jsvalue($url['url'], false, false)).
						'\');';
						unset($url['target'], $url['rel']);
					}
				}
				unset($url);
			}

			if (array_key_exists('acknowledge', $data)) {
				$menu_data['acknowledge'] = ((bool) $data['acknowledge']
						&& (CWebUser::checkAccess(CRoleHelper::ACTIONS_ADD_PROBLEM_COMMENTS)
							|| CWebUser::checkAccess(CRoleHelper::ACTIONS_CHANGE_SEVERITY)
							|| CWebUser::checkAccess(CRoleHelper::ACTIONS_ACKNOWLEDGE_PROBLEMS)
							|| $can_be_closed
							|| CWebUser::checkAccess(CRoleHelper::ACTIONS_SUPPRESS_PROBLEMS)
						)
				);
			}

			$scripts_by_hosts = CWebUser::checkAccess(CRoleHelper::ACTIONS_EXECUTE_SCRIPTS)
				? $event ? API::Script()->getScriptsByHosts(array_keys($hosts)) : []
				: [];

			// Filter only event scope scripts and get rid of excess spaces and create full name with menu path included.
			$scripts = [];
			foreach ($scripts_by_hosts as &$host_scripts) {
				foreach ($host_scripts as &$host_script) {
					if (!array_key_exists($host_script['scriptid'], $scripts)
							&& $host_script['scope'] == ZBX_SCRIPT_SCOPE_EVENT) {
						$host_script['menu_path'] = trimPath($host_script['menu_path']);
						$host_script['sort'] = '';

						if (strlen($host_script['menu_path']) > 0) {
							// First or only slash from beginning is trimmed.
							if (substr($host_script['menu_path'], 0, 1) === '/') {
								$host_script['menu_path'] = substr($host_script['menu_path'], 1);
							}

							$host_script['sort'] = $host_script['menu_path'];

							// If there is something more, check if last slash is present.
							if (strlen($host_script['menu_path']) > 0) {
								if (substr($host_script['menu_path'], -1) !== '/'
										&& substr($host_script['menu_path'], -2) === '\\/') {
									$host_script['sort'] = $host_script['menu_path'].'/';
								}
								else {
									$host_script['sort'] = $host_script['menu_path'];
								}

								if (substr($host_script['menu_path'], -1) === '/'
										&& substr($host_script['menu_path'], -2) !== '\\/') {
									$host_script['menu_path'] = substr($host_script['menu_path'], 0, -1);
								}
							}
						}

						$host_script['sort'] = $host_script['sort'].$host_script['name'];

						$scripts[$host_script['scriptid']] = $host_script;
					}
				}
				unset($host_script);
			}
			unset($host_scripts);

			CArrayHelper::sort($scripts, ['sort']);

			foreach (array_values($scripts) as $script) {
				$menu_data['scripts'][] = [
					'name' => $script['name'],
					'menu_path' => $script['menu_path'],
					'scriptid' => $script['scriptid'],
					'confirmation' => $script['confirmation']
				];
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
			case 'history':
				$menu_data = self::getMenuDataHistory($data);
				break;

			case 'host':
				$menu_data = self::getMenuDataHost($data);
				break;

			case 'item':
				$menu_data = self::getMenuDataItem($data);
				break;

			case 'item_configuration':
				$menu_data = self::getMenuDataItemConfiguration($data);
				break;

			case 'item_prototype_configuration':
				$menu_data = self::getMenuDataItemPrototypeConfiguration($data);
				break;

			case 'map_element':
				$menu_data = self::getMenuDataMapElement($data);
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

		if ($messages = get_and_clear_messages()) {
			$output['error']['messages'] = array_column($messages, 'message');
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
