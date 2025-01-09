<?php declare(strict_types = 0);
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


class CControllerMenuPopup extends CController {

	protected function init() {
		$this->disableCsrfValidation();
	}

	protected function checkInput() {
		$fields = [
			'type' => 'required|in history,host,item,item_prototype,map_element,trigger,trigger_macro,drule',
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
				$rules = [
					'itemid' => 'required|db items.itemid'
				];
				break;

			case 'item':
			case 'item_prototype':
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
					'backurl' => 'required|string',
					'eventid' => 'db events.eventid',
					'show_update_problem' => 'in 0,1',
					'show_rank_change_cause' => 'in 0,1',
					'show_rank_change_symptom' => 'in 0,1',
					'ids' => 'array_db events.eventid'
				];
				break;

			case 'trigger_macro':
				$rules = [];
				break;

			case 'drule':
				$rules = [
					'druleid' => 'required|db drules.druleid'
				];
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

			$all_scripts = CWebUser::checkAccess(CRoleHelper::ACTIONS_EXECUTE_SCRIPTS)
				? API::Script()->getScriptsByHosts(['hostid' => $data['hostid']])
				: [];

			if ($all_scripts) {
				$all_scripts = array_key_exists($data['hostid'], $all_scripts) ? $all_scripts[$data['hostid']] : [];
			}

			$scripts = [];
			$urls = [];

			if (array_key_exists('urls', $data)) {
				foreach ($data['urls'] as &$url) {
					$url['confirmation'] = '';
					$url['menu_path'] = '';
					$url['name'] = $url['label'];

					unset($url['label']);
				}
				unset($url);

				$urls = $data['urls'];
			}

			if ($all_scripts) {
				foreach ($all_scripts as $num => $script) {
					// Filter only host scope scripts, get rid of excess spaces and unify slashes in menu path.
					if ($script['scope'] != ZBX_SCRIPT_SCOPE_HOST) {
						unset($all_scripts[$num]);
						continue;
					}

					// Split scripts and URLs.
					if ($script['type'] == ZBX_SCRIPT_TYPE_URL) {
						$urls[] = $script;
					}
					else {
						$scripts[] = $script;
					}
				}

				$scripts = self::sortEntitiesByMenuPath($scripts);
				$urls = self::sortEntitiesByMenuPath($urls);
			}

			$menu_data = [
				'type' => 'host',
				'hostid' => $data['hostid'],
				'hasGoTo' => (bool) $has_goto,
				'allowed_ui_inventory' => CWebUser::checkAccess(CRoleHelper::UI_INVENTORY_HOSTS),
				'allowed_ui_latest_data' => CWebUser::checkAccess(CRoleHelper::UI_MONITORING_LATEST_DATA),
				'allowed_ui_problems' => CWebUser::checkAccess(CRoleHelper::UI_MONITORING_PROBLEMS),
				'allowed_ui_hosts' => CWebUser::checkAccess(CRoleHelper::UI_MONITORING_HOSTS),
				'allowed_ui_conf_hosts' => CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS),
				'csrf_token' => CCsrfTokenHelper::get('scriptexec')
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

			$menu_data = self::addScripts($menu_data, $scripts);
			$menu_data = self::addUrls($menu_data, $urls);

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
			'output' => ['hostid', 'key_', 'name_resolved', 'flags', 'type', 'value_type', 'history', 'trends'],
			'selectHosts' => ['host'],
			'selectTriggers' => ['triggerid', 'description'],
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
						'output' => ['hostid'],
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
				'backurl' => $data['backurl'],
				'itemid' => $data['itemid'],
				'name' => $db_item['name_resolved'],
				'key' => $db_item['key_'],
				'hostid' => $db_item['hostid'],
				'host' => $db_item['hosts'][0]['host'],
				'triggers' => $db_item['triggers'],
				'showGraph' => ($db_item['value_type'] == ITEM_VALUE_TYPE_FLOAT
					|| $db_item['value_type'] == ITEM_VALUE_TYPE_UINT64
				),
				'history' => $db_item['history'] != 0,
				'trends' => $db_item['trends'] != 0,
				'isDiscovery' => $db_item['flags'] == ZBX_FLAG_DISCOVERY_CREATED,
				'isExecutable' => $is_executable,
				'isWriteable' => $is_writable,
				'allowed_ui_latest_data' => CWebUser::checkAccess(CRoleHelper::UI_MONITORING_LATEST_DATA),
				'allowed_ui_conf_hosts' => CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS),
				'binary_value_type' => $db_item['value_type'] == ITEM_VALUE_TYPE_BINARY
			];
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
	private static function getMenuDataItemPrototype(array $data) {
		$db_item_prototypes = API::ItemPrototype()->get([
			'output' => ['name', 'key_'],
			'selectDiscoveryRule' => ['itemid'],
			'selectHosts' => ['host'],
			'selectTriggers' => ['triggerid', 'description'],
			'itemids' => $data['itemid']
		]);

		if ($db_item_prototypes) {
			$db_item_prototype = $db_item_prototypes[0];

			$menu_data = [
				'type' => 'item_prototype',
				'backurl' => $data['backurl'],
				'itemid' => $data['itemid'],
				'name' => $db_item_prototype['name'],
				'key' => $db_item_prototype['key_'],
				'hostid' => $db_item_prototype['hosts'][0]['hostid'],
				'host' => $db_item_prototype['hosts'][0]['host'],
				'parent_discoveryid' => $db_item_prototype['discoveryRule']['itemid'],
				'trigger_prototypes' => $db_item_prototype['triggers']
			];

			return $menu_data;
		}

		error(_('No permissions to referred object or it does not exist!'));

		return null;
	}

	/**
	 * Validates URLs for supported schemes.
	 *
	 * @param array  $urls
	 * @param array  $urls[]['url']
	 *
	 * @return array
	 */
	private static function sanitizeMapElementUrls(array $urls): array {
		foreach ($urls as &$url) {
			if (CHtmlUrlValidator::validate($url['url'], ['allow_user_macro' => false]) === false) {
				$url['url'] = 'javascript: alert('.json_encode(_s('Provided URL "%1$s" is invalid.', $url['url'])).');';
			}
		}
		unset($url);

		return $urls;
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
							$menu_data['urls'] = self::sanitizeMapElementUrls($selement['urls']);
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
							$menu_data['urls'] = self::sanitizeMapElementUrls($selement['urls']);
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
						$items = [];
						$triggers = [];
						$hosts = [];
						$show_events = true;
						$unique_itemids = [];

						$db_triggers = API::Trigger()->get([
							'output' => ['triggerid', 'description'],
							'selectHosts' => ['hostid', 'name', 'status'],
							'selectItems' => ['itemid', 'hostid', 'name_resolved', 'value_type', 'type'],
							'triggerids' => array_column($selement['elements'], 'triggerid'),
							'preservekeys' => true
						]);

						foreach ($db_triggers as $db_trigger) {
							foreach ($db_trigger['hosts'] as $host) {
								$hosts[$host['hostid']] = $host['name'];
							}

							foreach ($db_trigger['items'] as &$item) {
								$item['hostname'] = $hosts[$item['hostid']];

								if ($host['status'] != HOST_STATUS_MONITORED) {
									$show_events = false;
								}
							}
							unset($item);

							CArrayHelper::sort($db_trigger['items'], ['name_resolved', 'hostname', 'itemid']);

							$with_hostname = count($hosts) > 1;

							foreach ($db_trigger['items'] as $item) {
								if (in_array($item['itemid'], $unique_itemids)) {
									continue;
								}

								$items[] = [
									'name' => $with_hostname
										? $item['hostname'].NAME_DELIMITER.$item['name_resolved']
										: $item['name_resolved'],
									'params' => [
										'itemid' => $item['itemid'],
										'action' => in_array(
											$item['value_type'], [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64]
										)
											? HISTORY_GRAPH
											: HISTORY_VALUES,
										'is_webitem' => $item['type'] == ITEM_TYPE_HTTPTEST
									]
								];

								$unique_itemids[] = $item['itemid'];
							}

							$triggers[] = [
								'triggerid' => $db_trigger['triggerid'],
								'description' => $db_trigger['description']
							];
						}

						$menu_data = [
							'type' => 'map_element_trigger',
							'triggers' => $triggers,
							'items' => $items,
							'show_events' => $show_events,
							'allowed_ui_problems' => CWebUser::checkAccess(CRoleHelper::UI_MONITORING_PROBLEMS),
							'allowed_ui_conf_hosts' => CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS),
							'allowed_ui_latest_data' => CWebUser::checkAccess(CRoleHelper::UI_MONITORING_LATEST_DATA)
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
							$menu_data['urls'] = self::sanitizeMapElementUrls($selement['urls']);
						}

						return $menu_data;

					case SYSMAP_ELEMENT_TYPE_IMAGE:
						$menu_data = [
							'type' => 'map_element_image'
						];
						if ($selement['urls']) {
							$menu_data['urls'] = self::sanitizeMapElementUrls($selement['urls']);
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
	 *        string $data['triggerid']                 Trigger ID.
	 *        string $data['backurl']                   URL from where the menu popup was called.
	 *        string $data['eventid']                   (optional) Mandatory for "Update problem", "Mark as cause"
	 *                                                  and "Mark selected as symptoms" context menus.
	 *        array  $data['ids']                       (optional) Event IDs that are used in event rank change to
	 *                                                  symptom.
	 *        bool   $data['show_update_problem']       (optional) Whether to show "Update problem".
	 *        bool   $data['show_rank_change_cause']    (optional) Whether to show "Mark as cause".
	 *        bool   $data['show_rank_change_symptom']  (optional) Whether to show "Mark selected as symptoms".
	 *
	 * @return array|null
	 */
	private static function getMenuDataTrigger(array $data): ?array {
		$db_triggers = API::Trigger()->get([
			'output' => ['expression', 'url_name', 'url', 'comments', 'manual_close'],
			'selectHosts' => ['hostid', 'name', 'status'],
			'selectItems' => ['itemid', 'hostid', 'name_resolved', 'value_type', 'type'],
			'triggerids' => $data['triggerid'],
			'preservekeys' => true
		]);

		if ($db_triggers) {
			$db_trigger = reset($db_triggers);

			if (array_key_exists('eventid', $data)) {
				$db_trigger['eventid'] = $data['eventid'];
			}

			$db_trigger['url'] = CMacrosResolverHelper::resolveTriggerUrl($db_trigger, $url) ? $url : '';

			if ($db_trigger['url'] !== '') {
				$db_trigger['url_name'] = CMacrosResolverHelper::resolveTriggerUrlName($db_trigger, $url_name)
					? $url_name
					: '';
			}

			$hosts = [];
			$show_events = true;

			foreach ($db_trigger['hosts'] as $host) {
				$hosts[$host['hostid']] = $host['name'];

				if ($host['status'] != HOST_STATUS_MONITORED) {
					$show_events = false;
				}
			}

			foreach ($db_trigger['items'] as &$item) {
				$item['hostname'] = $hosts[$item['hostid']];
			}
			unset($item);

			CArrayHelper::sort($db_trigger['items'], ['name_resolved', 'hostname', 'itemid']);

			$with_hostname = count($hosts) > 1;
			$items = [];

			foreach ($db_trigger['items'] as $item) {
				$items[] = [
					'name' => $with_hostname
						? $item['hostname'].NAME_DELIMITER.$item['name_resolved']
						: $item['name_resolved'],
					'params' => [
						'itemid' => $item['itemid'],
						'action' => in_array($item['value_type'], [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64])
							? HISTORY_GRAPH
							: HISTORY_VALUES,
						'is_webitem' => $item['type'] == ITEM_TYPE_HTTPTEST
					]
				];
			}

			$menu_data = [
				'type' => 'trigger',
				'triggerid' => $data['triggerid'],
				'backurl' => $data['backurl'],
				'items' => $items,
				'show_events' => $show_events,
				'allowed_ui_problems' => CWebUser::checkAccess(CRoleHelper::UI_MONITORING_PROBLEMS),
				'allowed_ui_conf_hosts' => CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS),
				'allowed_ui_latest_data' => CWebUser::checkAccess(CRoleHelper::UI_MONITORING_LATEST_DATA),
				'allowed_actions_change_problem_ranking' =>
					CWebUser::checkAccess(CRoleHelper::ACTIONS_CHANGE_PROBLEM_RANKING)
			];

			$can_be_closed = ($db_trigger['manual_close'] == ZBX_TRIGGER_MANUAL_CLOSE_ALLOWED
				&& CWebUser::checkAccess(CRoleHelper::ACTIONS_CLOSE_PROBLEMS)
			);
			$event = [];

			if (array_key_exists('eventid', $data)) {
				$menu_data['eventid'] = $data['eventid'];

				$events = API::Event()->get([
					'output' => ['eventid', 'r_eventid', 'urls', 'cause_eventid'],
					'selectAcknowledges' => ['action'],
					'eventids' => $data['eventid']
				]);

				if ($events) {
					$event = $events[0];

					if ($can_be_closed) {
						$can_be_closed = !isEventClosed($event);
					}

					if (CWebUser::checkAccess(CRoleHelper::ACTIONS_CHANGE_PROBLEM_RANKING)) {
						// Can change rank to cause if event is not already cause.
						$menu_data['mark_as_cause'] = ($event['cause_eventid'] != 0);

						// Check if selected events can change rank to symptom for given cause.
						$menu_data['mark_selected_as_symptoms'] = array_key_exists('ids', $data) && $data['ids']
							? (bool) validateEventRankChangeToSymptom($data['ids'], $data['eventid'])
							: false;

						$menu_data['eventids'] = array_key_exists('ids', $data) ? $data['ids'] : [];

						// Show individual menus depending on location.
						$menu_data['show_rank_change_cause'] = array_key_exists('show_rank_change_cause', $data)
							&& $data['show_rank_change_cause'];
						$menu_data['show_rank_change_symptom'] = array_key_exists('show_rank_change_symptom', $data)
							&& $data['show_rank_change_symptom'];
						$menu_data['csrf_tokens']['acknowledge'] = CCsrfTokenHelper::get('acknowledge');
					}
				}
			}

			if (array_key_exists('show_update_problem', $data)) {
				$menu_data['show_update_problem'] = $data['show_update_problem']
					&& (CWebUser::checkAccess(CRoleHelper::ACTIONS_ADD_PROBLEM_COMMENTS)
						|| CWebUser::checkAccess(CRoleHelper::ACTIONS_CHANGE_SEVERITY)
						|| CWebUser::checkAccess(CRoleHelper::ACTIONS_ACKNOWLEDGE_PROBLEMS)
						|| $can_be_closed
						|| CWebUser::checkAccess(CRoleHelper::ACTIONS_SUPPRESS_PROBLEMS)
					);
			}

			$scripts_by_events = [];

			if (CWebUser::checkAccess(CRoleHelper::ACTIONS_EXECUTE_SCRIPTS) && $event) {
				$scripts_by_events = API::Script()->getScriptsByEvents(['eventid' => $event['eventid']]);
			}

			// Filter only event scope scripts and get rid of excess spaces and create full name with menu path included.
			$scripts = [];
			$urls = [];

			foreach ($scripts_by_events as &$event_scripts) {
				foreach ($event_scripts as $num => &$event_script) {
					if ($event_script['scope'] != ZBX_SCRIPT_SCOPE_EVENT) {
						unset($event_script[$num]);
						continue;
					}

					$scriptid = $event_script['scriptid'];

					// Split scripts and URLs.
					if ($event_script['type'] == ZBX_SCRIPT_TYPE_URL) {
						if (!array_key_exists($scriptid, $urls)) {
							$urls[$scriptid] = $event_script;
						}
					}
					else {
						if (!array_key_exists($scriptid, $scripts)) {
							$scripts[$scriptid] = $event_script;
						}
					}
				}
				unset($event_script);
			}
			unset($event_scripts);

			if ($event) {
				foreach ($event['urls'] as &$url) {
					$url['new_window'] = ZBX_SCRIPT_URL_NEW_WINDOW_YES;
					$url['confirmation'] = '';
					$url['menu_path'] = '';
				}
				unset($url);

				$urls = array_merge($urls, $event['urls']);
			}

			if ($db_trigger['url'] !== '') {
				$urls = array_merge($urls, [[
					'name' => $db_trigger['url_name'] !== '' ? $db_trigger['url_name'] : _('Trigger URL'),
					'url' => $db_trigger['url'],
					'menu_path' => '',
					'new_window' => ZBX_SCRIPT_URL_NEW_WINDOW_NO,
					'confirmation' => ''
				]]);
			}

			$scripts = self::sortEntitiesByMenuPath($scripts);
			$urls = self::sortEntitiesByMenuPath($urls);

			$menu_data = self::addScripts($menu_data, $scripts);
			$menu_data = self::addUrls($menu_data, $urls);

			if ($scripts) {
				$menu_data['csrf_tokens']['scriptexec'] = CCsrfTokenHelper::get('scriptexec');
			}

			return $menu_data;
		}

		error(_('No permissions to referred object or it does not exist!'));

		return null;
	}

	/**
	 * Process menu path and sort scripts or URLs according to it.
	 *
	 * @param array  $entities                 Scripts and URLs.
	 * @param string $entities[]['name']       Name of the script or URL.
	 * @param string $entities[]['menu_path']  Menu path of the script or URL.
	 *
	 * @return array
	 */
	private static function sortEntitiesByMenuPath(array $entities): array {
		if ($entities) {
			foreach ($entities as &$entity) {
				$entity['menu_path'] = trimPath($entity['menu_path']);
				$entity['sort'] = '';

				if (strlen($entity['menu_path']) > 0) {
					// First or only slash from beginning is trimmed.
					if (substr($entity['menu_path'], 0, 1) === '/') {
						$entity['menu_path'] = substr($entity['menu_path'], 1);
					}

					$entity['sort'] = $entity['menu_path'];

					// If there is something more, check if last slash is present.
					if (strlen($entity['menu_path']) > 0) {
						if (substr($entity['menu_path'], -1) !== '/'
								&& substr($entity['menu_path'], -2) === '\\/') {
							$entity['sort'] = $entity['menu_path'].'/';
						}
						else {
							$entity['sort'] = $entity['menu_path'];
						}

						if (substr($entity['menu_path'], -1) === '/'
								&& substr($entity['menu_path'], -2) !== '\\/') {
							$entity['menu_path'] = substr($entity['menu_path'], 0, -1);
						}
					}
				}

				$entity['sort'] = $entity['sort'].$entity['name'];
			}
			unset($entity);

			CArrayHelper::sort($entities, ['sort']);
		}

		return $entities;
	}

	/**
	 * Prepare data for trigger macro context menu popup.
	 *
	 * @return array
	 */
	private static function getMenuDataTriggerMacro() {
		return ['type' => 'trigger_macro'];
	}

	private static function addScripts(array $menu_data, array $scripts): array {
		$fields = ['name', 'menu_path', 'scriptid', 'confirmation', 'manualinput', 'manualinput_prompt',
			'manualinput_validator_type', 'manualinput_validator', 'manualinput_default_value'
		];

		foreach ($scripts as $script) {
			$menu_data['scripts'][] = array_intersect_key($script, array_flip($fields));
		}

		return $menu_data;
	}

	private static function addUrls(array $menu_data, array $urls): array {
		$fields = ['scriptid', 'manualinput', 'manualinput_prompt', 'manualinput_validator_type',
			'manualinput_validator', 'manualinput_default_value'
		];

		foreach ($urls as $url) {
			$menu_data_parameters = [
				'label' => $url['name'],
				'menu_path' => $url['menu_path'],
				'confirmation' => $url['confirmation']
			];

			$target = array_key_exists('new_window', $url) && $url['new_window'] == ZBX_SCRIPT_URL_NEW_WINDOW_YES
				? '_blank'
				: '';

			if (CHtmlUrlValidator::validate($url['url'], ['allow_user_macro' => false])) {
				$menu_data_parameters += [
					'url' => $url['url'],
					'target' => $target
				];
			}
			else {
				$menu_data_parameters += [
					'url' => 'javascript: alert('.json_encode(_s('Provided URL "%1$s" is invalid.', $url['url'])).');'
				];
			}

			if (array_key_exists('scriptid', $url)) {
				$menu_data_parameters += array_intersect_key($url, array_flip($fields));
			}

			$menu_data['urls'][] = $menu_data_parameters;
		}

		return $menu_data;
	}

	private static function getMenuDataDRule(array $data): ?array {
		$db_drules_count = API::DRule()->get([
			'output' => [],
			'druleids' => $data['druleid'],
			'countOutput' => true
		]);

		if ($db_drules_count > 0) {
			$menu_data = [
				'type' => 'drule',
				'druleid' => $data['druleid'],
				'allowed_ui_conf_drules' => CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_DISCOVERY)
			];

			return $menu_data;
		}

		error(_('No permissions to referred object or it does not exist!'));

		return null;
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

			case 'item_prototype':
				$menu_data = self::getMenuDataItemPrototype($data);
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

			case 'drule':
				$menu_data = self::getMenuDataDRule($data);
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
