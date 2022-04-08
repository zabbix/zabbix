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


require_once dirname(__FILE__).'/include/func.inc.php';
require_once dirname(__FILE__).'/include/defines.inc.php';
require_once dirname(__FILE__).'/include/classes/user/CWebUser.php';
require_once dirname(__FILE__).'/include/classes/core/CHttpRequest.php';

$requestType = getRequest('type', PAGE_TYPE_JSON);
if ($requestType == PAGE_TYPE_JSON) {
	$http_request = new CHttpRequest();
	$data = json_decode($http_request->body(), true);
}
else {
	$data = $_REQUEST;
}

if (is_array($data) && array_key_exists('method', $data) && $data['method'] === 'zabbix.status') {
	CWebUser::disableSessionExtension();
}

require_once dirname(__FILE__).'/include/config.inc.php';

$page['title'] = 'RPC';
$page['file'] = 'jsrpc.php';
$page['type'] = detect_page_type($requestType);

require_once dirname(__FILE__).'/include/page_header.php';

if (!is_array($data) || !isset($data['method'])
		|| ($requestType == PAGE_TYPE_JSON && (!isset($data['params']) || !is_array($data['params'])))) {
	fatal_error('Wrong RPC call to JS RPC!');
}

$result = [];
$limit = CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT);

switch ($data['method']) {
	case 'search':
		$result = API::Host()->get([
			'output' => ['hostid', 'name'],
			'search' => ['name' => $data['params']['search'], 'host' => $data['params']['search']],
			'searchByAny' => true,
			'sortfield' => 'name',
			'limit' => 15
		]);
		break;

	case 'zabbix.status':
		if (!CSessionHelper::has('serverCheckResult')
				|| (CSessionHelper::get('serverCheckTime') + SERVER_CHECK_INTERVAL) <= time()) {

			if ($ZBX_SERVER === null && $ZBX_SERVER_PORT === null) {
				$is_running = false;
			}
			else {
				$zabbix_server = new CZabbixServer($ZBX_SERVER, $ZBX_SERVER_PORT,
					timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::CONNECT_TIMEOUT)),
					timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::SOCKET_TIMEOUT)), 0
				);

				$is_running = $zabbix_server->isRunning(CSessionHelper::getId());
			}

			CSessionHelper::set('serverCheckResult', $is_running);
			CSessionHelper::set('serverCheckTime', time());
		}

		$result = [
			'result' => (bool) CSessionHelper::get('serverCheckResult'),
			'message' => CSessionHelper::get('serverCheckResult')
				? ''
				: _('Zabbix server is not running: the information displayed may not be current.')
		];
		break;

	case 'screen.get':
		$result = '';
		$screenBase = CScreenBuilder::getScreen($data);
		if ($screenBase !== null) {
			$screen = $screenBase->get();

			if ($data['mode'] == SCREEN_MODE_JS) {
				$result = $screen;
			}
			elseif (is_object($screen)) {
				$result = $screen->toString();
			}
		}
		break;

	case 'trigger.get':
		$result = [];

		$triggers = API::Trigger()->get([
			'output' => ['triggerid', 'priority'],
			'triggerids' => $data['triggerids'],
			'limit' => $limit
		]);

		if ($triggers) {
			CArrayHelper::sort($triggers, [
				['field' => 'priority', 'order' => ZBX_SORT_DOWN]
			]);

			foreach ($triggers as $trigger) {
				$trigger['class_name'] = CSeverityHelper::getStyle((int) $trigger['priority']);
				$result[] = $trigger;
			}
		}
		break;

	/**
	 * Create multi select data.
	 * Supported objects: "hosts", "hostGroup", "templates", "triggers"
	 *
	 * @param string $data['object_name']
	 * @param string $data['search']
	 * @param int    $data['limit']
	 *
	 * @return array(int => array('value' => int, 'text' => string))
	 */
	case 'multiselect.get':
		switch ($data['object_name']) {
			case 'hostGroup':
				$options = [
					'output' => ['groupid', 'name'],
					'search' => array_key_exists('search', $data) ? ['name' => $data['search']] : null,
					'filter' => array_key_exists('filter', $data) ? $data['filter'] : null,
					'real_hosts' => array_key_exists('real_hosts', $data) ? $data['real_hosts'] : null,
					'with_items' => array_key_exists('with_items', $data) ? $data['with_items'] : null,
					'with_httptests' => array_key_exists('with_httptests', $data) ? $data['with_httptests'] : null,
					'with_hosts_and_templates' => array_key_exists('with_hosts_and_templates', $data)
						? $data['with_hosts_and_templates']
						: null,
					'with_monitored_triggers' => array_key_exists('with_monitored_triggers', $data)
						? $data['with_monitored_triggers']
						: null,
					'with_triggers' => array_key_exists('with_triggers', $data) ? $data['with_triggers'] : null,
					'templated_hosts' => array_key_exists('templated_hosts', $data) ? $data['templated_hosts'] : null,
					'editable' => array_key_exists('editable', $data) ? $data['editable'] : false,
					'limit' => array_key_exists('limit', $data) ? $data['limit'] : null,
					'preservekeys' => true
				];
				$hostGroups = API::HostGroup()->get($options);

				if ($hostGroups) {
					if (array_key_exists('enrich_parent_groups', $data)) {
						$hostGroups = enrichParentGroups($hostGroups);
					}

					CArrayHelper::sort($hostGroups, [
						['field' => 'name', 'order' => ZBX_SORT_UP]
					]);

					if (isset($data['limit'])) {
						$hostGroups = array_slice($hostGroups, 0, $data['limit']);
					}

					$result = CArrayHelper::renameObjectsKeys($hostGroups, ['groupid' => 'id']);
				}
				break;

			case 'host_templates':
			case 'hosts':
				$options = [
					'output' => ['hostid', 'name'],
					'templated_hosts' => array_key_exists('templated_hosts', $data) ? $data['templated_hosts'] : null,
					'with_items' => array_key_exists('with_items', $data) ? $data['with_items'] : null,
					'with_httptests' => array_key_exists('with_httptests', $data) ? $data['with_httptests'] : null,
					'with_triggers' => array_key_exists('with_triggers', $data) ? $data['with_triggers'] : null,
					'search' => array_key_exists('search', $data) ? ['name' => $data['search']] : null,
					'editable' => array_key_exists('editable', $data) ? $data['editable'] : false,
					'limit' => $limit
				];

				if ($data['object_name'] === 'host_templates') {
					$options['templated_hosts'] = true;
				}

				if (array_key_exists('with_monitored_triggers', $data)) {
					$options += [
						'with_monitored_triggers' => true
					];
				}

				if (array_key_exists('with_monitored_items', $data)) {
					$options += [
						'with_monitored_items' => true,
						'monitored_hosts' => true
					];
				}

				$hosts = API::Host()->get($options);

				if ($hosts) {
					CArrayHelper::sort($hosts, [
						['field' => 'name', 'order' => ZBX_SORT_UP]
					]);

					if (isset($data['limit'])) {
						$hosts = array_slice($hosts, 0, $data['limit']);
					}

					$result = CArrayHelper::renameObjectsKeys($hosts, ['hostid' => 'id']);
				}
				break;

			case 'items':
			case 'item_prototypes':
				$options = [
					'output' => ['itemid', 'name'],
					'selectHosts' => ['name'],
					'hostids' => array_key_exists('hostid', $data) ? $data['hostid'] : null,
					'templated' => array_key_exists('real_hosts', $data) ? false : null,
					'search' => array_key_exists('search', $data) ? ['name' => $data['search']] : null,
					'filter' => array_key_exists('filter', $data) ? $data['filter'] : null,
					'limit' => $limit
				];

				if ($data['object_name'] === 'item_prototypes') {
					$records = API::ItemPrototype()->get($options);
				}
				else {
					if (array_key_exists('webitems', $data)) {
						$options['webitems'] = $data['webitems'];
					}

					$records = API::Item()->get($options);
				}

				if ($records) {
					CArrayHelper::sort($records, ['name']);

					if (array_key_exists('limit', $data)) {
						$records = array_slice($records, 0, $data['limit']);
					}

					foreach ($records as $record) {
						$result[] = [
							'id' => $record['itemid'],
							'name' => $record['name'],
							'prefix' => $record['hosts'][0]['name'].NAME_DELIMITER
						];
					}
				}
				break;

			case 'graphs':
			case 'graph_prototypes':
				$options = [
					'output' => ['graphid', 'name'],
					'selectHosts' => ['hostid', 'name'],
					'hostids' => array_key_exists('hostid', $data) ? $data['hostid'] : null,
					'templated' => array_key_exists('real_hosts', $data) ? false : null,
					'search' => array_key_exists('search', $data) ? ['name' => $data['search']] : null,
					'filter' => array_key_exists('filter', $data) ? $data['filter'] : null,
					'limit' => $limit
				];

				if ($data['object_name'] === 'graph_prototypes') {
					$options['selectDiscoveryRule'] = ['hostid'];

					$records = API::GraphPrototype()->get($options);
				}
				else {
					$records = API::Graph()->get($options);
				}

				CArrayHelper::sort($records, ['name']);

				if (array_key_exists('limit', $data)) {
					$records = array_slice($records, 0, $data['limit']);
				}

				foreach ($records as $record) {
					if ($data['object_name'] === 'graphs') {
						$host_name = $record['hosts'][0]['name'];
					}
					else {
						$host_names = array_column($record['hosts'], 'name', 'hostid');
						$host_name = $host_names[$record['discoveryRule']['hostid']];
					}

					$result[] = [
						'id' => $record['graphid'],
						'name' => $record['name'],
						'prefix' => $host_name.NAME_DELIMITER
					];
				}
				break;

			case 'templates':
				$templates = API::Template()->get([
					'editable' => isset($data['editable']) ? $data['editable'] : false,
					'output' => ['templateid', 'name'],
					'search' => isset($data['search']) ? ['name' => $data['search']] : null,
					'limit' => $limit
				]);

				if ($templates) {
					CArrayHelper::sort($templates, [
						['field' => 'name', 'order' => ZBX_SORT_UP]
					]);

					if (isset($data['limit'])) {
						$templates = array_slice($templates, 0, $data['limit']);
					}

					$result = CArrayHelper::renameObjectsKeys($templates, ['templateid' => 'id']);
				}
				break;

			case 'proxies':
				$proxies = API::Proxy()->get([
					'output' => ['proxyid', 'host'],
					'search' => array_key_exists('search', $data) ? ['host' => $data['search']] : null,
					'limit' => $limit
				]);

				if ($proxies) {
					CArrayHelper::sort($proxies, ['host']);

					if (isset($data['limit'])) {
						$proxies = array_slice($proxies, 0, $data['limit']);
					}

					$result = CArrayHelper::renameObjectsKeys($proxies, ['proxyid' => 'id', 'host' => 'name']);
				}
				break;

			case 'triggers':
				$host_fields = ['name'];
				if (array_key_exists('real_hosts', $data) && $data['real_hosts']) {
					$host_fields[] = 'status';
				}

				$triggers = API::Trigger()->get([
					'output' => ['triggerid', 'description'],
					'selectHosts' => $host_fields,
					'editable' => isset($data['editable']) ? $data['editable'] : false,
					'monitored' => isset($data['monitored']) ? $data['monitored'] : null,
					'search' => isset($data['search']) ? ['description' => $data['search']] : null,
					'limit' => $limit
				]);

				if ($triggers) {
					if (array_key_exists('real_hosts', $data) && $data['real_hosts']) {
						foreach ($triggers as $key => $trigger) {
							foreach ($triggers[$key]['hosts'] as $host) {
								if ($host['status'] != HOST_STATUS_MONITORED
										&& $host['status'] != HOST_STATUS_NOT_MONITORED) {
									unset($triggers[$key]);
									break;
								}
							}
						}
					}

					CArrayHelper::sort($triggers, [
						['field' => 'description', 'order' => ZBX_SORT_UP]
					]);

					if (isset($data['limit'])) {
						$triggers = array_slice($triggers, 0, $data['limit']);
					}

					foreach ($triggers as $trigger) {
						$hostName = '';

						if ($trigger['hosts']) {
							$trigger['hosts'] = reset($trigger['hosts']);

							$hostName = $trigger['hosts']['name'].NAME_DELIMITER;
						}

						$result[] = [
							'id' => $trigger['triggerid'],
							'name' => $trigger['description'],
							'prefix' => $hostName
						];
					}
				}
				break;

			case 'users':
				$users = API::User()->get([
					'output' => ['userid', 'username', 'name', 'surname'],
					'search' => array_key_exists('search', $data)
						? [
							'username' => $data['search'],
							'name' => $data['search'],
							'surname' => $data['search']
						]
						: null,
					'searchByAny' => true,
					'limit' => $limit
				]);

				if ($users) {
					CArrayHelper::sort($users, [
						['field' => 'username', 'order' => ZBX_SORT_UP]
					]);

					if (array_key_exists('limit', $data)) {
						$users = array_slice($users, 0, $data['limit']);
					}

					foreach ($users as $user) {
						$result[] = [
							'id' => $user['userid'],
							'name' => getUserFullname($user)
						];
					}
				}
				break;

			case 'usersGroups':
				$groups = API::UserGroup()->get([
					'output' => ['usrgrpid', 'name'],
					'search' => array_key_exists('search', $data) ? ['name' => $data['search']] : null,
					'limit' => $limit
				]);

				if ($groups) {
					CArrayHelper::sort($groups, [
						['field' => 'name', 'order' => ZBX_SORT_UP]
					]);

					if (array_key_exists('limit', $data)) {
						$groups = array_slice($groups, 0, $data['limit']);
					}

					$result = CArrayHelper::renameObjectsKeys($groups, ['usrgrpid' => 'id']);
				}
				break;

			case 'drules':
				$drules = API::DRule()->get([
					'output' => ['druleid', 'name'],
					'search' => array_key_exists('search', $data) ? ['name' => $data['search']] : null,
					'filter' => ['status' => DRULE_STATUS_ACTIVE],
					'limit' => $limit
				]);

				if ($drules) {
					CArrayHelper::sort($drules, [
						['field' => 'name', 'order' => ZBX_SORT_UP]
					]);

					$result = CArrayHelper::renameObjectsKeys($drules, ['druleid' => 'id']);
				}
				break;

			case 'roles':
				$roles = API::Role()->get([
					'output' => ['roleid', 'name'],
					'search' => array_key_exists('search', $data) ? ['name' => $data['search']] : null,
					'limit' => $limit
				]);

				if ($roles) {
					CArrayHelper::sort($roles, [
						['field' => 'name', 'order' => ZBX_SORT_UP]
					]);

					if (array_key_exists('limit', $data)) {
						$roles = array_slice($roles, 0, $data['limit']);
					}

					$result = CArrayHelper::renameObjectsKeys($roles, ['roleid' => 'id']);
				}
				break;

			case 'api_methods':
				$result = [];
				$user_type = array_key_exists('user_type', $data) ? $data['user_type'] : USER_TYPE_ZABBIX_USER;
				$search = array_key_exists('search', $data) ? $data['search'] : '';

				$api_methods = array_slice(
					preg_grep('/'.preg_quote($search).'/',
						array_merge(CRoleHelper::getApiMethodMasks($user_type), CRoleHelper::getApiMethods($user_type))
					),
					0, $limit
				);

				foreach ($api_methods as $api_method) {
					$result[] = ['id' => $api_method, 'name' => $api_method];
				}
				break;

			case 'valuemap_names':
				if (!array_key_exists('hostids', $data) || !array_key_exists('context', $data)) {
					break;
				}

				$hostids = $data['hostids'];

				if (array_key_exists('with_inherited', $data)) {
					$hostids = CTemplateHelper::getParentTemplatesRecursive($hostids, $data['context']);
				}

				$result = API::ValueMap()->get([
					'output' => ['valuemapid', 'name'],
					'hostids' => $hostids,
					'search' => ['name' => $data['search'] ? $data['search'] : null],
					'limit' => $limit
				]);
				$result = array_column($result, null, 'name');
				$result = CArrayHelper::renameObjectsKeys($result, ['valuemapid' => 'id']);
				CArrayHelper::sort($result, ['name']);
				break;

			case 'valuemaps':
				if (!array_key_exists('hostids', $data) || !array_key_exists('context', $data)) {
					break;
				}

				if ($data['context'] === 'host') {
					$hosts = API::Host()->get([
						'output' => ['name'],
						'hostids' => $data['hostids'],
						'preservekeys' => true
					]);
				}
				else {
					$hosts = API::Template()->get([
						'output' => ['name'],
						'templateids' => $data['hostids'],
						'preservekeys' => true
					]);
				}

				$valuemaps = API::ValueMap()->get([
					'output' => ['valuemapid', 'name', 'hostid'],
					'hostids' => $data['hostids'],
					'search' => ['name' => $data['search'] ? $data['search'] : null],
					'limit' => $limit
				]);

				foreach ($valuemaps as &$valuemap) {
					$valuemap['prefix'] = $hosts[$valuemap['hostid']]['name'].NAME_DELIMITER;
					unset($valuemap['hostid']);
				}
				unset($valuemap);

				$result = CArrayHelper::renameObjectsKeys($valuemaps, ['valuemapid' => 'id']);
				CArrayHelper::sort($result, ['name']);
				break;

			case 'dashboard':
				$dashboards = API::Dashboard()->get([
					'output' => ['dashboardid', 'name'],
					'search' => array_key_exists('search', $data) ? ['name' => $data['search']] : null,
					'limit' => $limit
				]);

				if ($dashboards) {
					CArrayHelper::sort($dashboards, [['field' => 'name', 'order' => ZBX_SORT_UP]]);

					if (array_key_exists('limit', $data)) {
						$dashboards = array_slice($dashboards, 0, $data['limit']);
					}

					$result = CArrayHelper::renameObjectsKeys($dashboards, ['dashboardid' => 'id']);
				}
				break;

			case 'services':
				$services = API::Service()->get([
					'output' => ['serviceid', 'name'],
					'search' => array_key_exists('search', $data) ? ['name' => $data['search']] : null,
					'limit' => $limit
				]);

				if ($services) {
					CArrayHelper::sort($services, [['field' => 'name', 'order' => ZBX_SORT_UP]]);

					if (array_key_exists('limit', $data)) {
						$services = array_slice($services, 0, $data['limit']);
					}

					$result = CArrayHelper::renameObjectsKeys($services, ['serviceid' => 'id']);
				}
				break;

			case 'sla':
				$slas = API::Sla()->get([
					'output' => ['slaid', 'name'],
					'filter' => [
						'status' => array_key_exists('enabled_only', $data) ? ZBX_SLA_STATUS_ENABLED : null
					],
					'search' => array_key_exists('search', $data) ? ['name' => $data['search']] : null,
					'limit' => $limit
				]);

				if ($slas) {
					CArrayHelper::sort($slas, [['field' => 'name', 'order' => ZBX_SORT_UP]]);

					if (array_key_exists('limit', $data)) {
						$slas = array_slice($slas, 0, $data['limit']);
					}

					$result = CArrayHelper::renameObjectsKeys($slas, ['slaid' => 'id']);
				}
				break;
		}
		break;

	case 'patternselect.get':
		$search = (array_key_exists('search', $data) && $data['search'] !== '') ? $data['search'] : null;
		$wildcard_enabled = array_key_exists('wildcard_allowed', $data) && strpos($search, '*') !== false;

		switch ($data['object_name']) {
			case 'hosts':
				$options = [
					'output' => ['name'],
					'search' => ['name' => $search.($wildcard_enabled ? '*' : '')],
					'searchWildcardsEnabled' => $wildcard_enabled,
					'preservekeys' => true,
					'limit' => $limit
				];

				$db_result = API::Host()->get($options);
				break;

			case 'items':
				$options = [
					'output' => ['name'],
					'search' => ['name' => $search.($wildcard_enabled ? '*' : '')],
					'searchWildcardsEnabled' => $wildcard_enabled,
					'filter' => array_key_exists('filter', $data) ? $data['filter'] : null,
					'templated' => array_key_exists('real_hosts', $data) ? false : null,
					'webitems' => array_key_exists('webitems', $data) ? $data['webitems'] : null,
					'limit' => $limit
				];

				$db_result = API::Item()->get($options);
				break;

			case 'graphs':
				$options = [
					'output' => ['name'],
					'search' => ['name' => $search.($wildcard_enabled ? '*' : '')],
					'hostids' => array_key_exists('hostid', $data) ? $data['hostid'] : null,
					'templated' => array_key_exists('real_hosts', $data) ? false : null,
					'searchWildcardsEnabled' => $wildcard_enabled,
					'limit' => $limit
				];

				$db_result = API::Graph()->get($options);
				break;
		}

		$result[] = [
			'name' => $search,
			'id' => $search
		];

		if ($db_result) {
			$db_result = array_flip(zbx_objectValues($db_result, 'name'));

			if (array_key_exists($search, $db_result)) {
				unset($db_result[$search]);
			}

			if (array_key_exists('limit', $data)) {
				$db_result = array_slice($db_result, 0, $data['limit']);
			}

			foreach ($db_result as $name => $id) {
				$result[] = [
					'name' => $name,
					'id' => $name
				];
			}
		}
		break;

	default:
		fatal_error('Wrong RPC call to JS RPC!');
}

if ($requestType == PAGE_TYPE_JSON) {
	if (isset($data['id'])) {
		echo json_encode([
			'jsonrpc' => '2.0',
			'result' => $result,
			'id' => $data['id']
		]);

		session_write_close();
		exit();
	}
}
elseif ($requestType == PAGE_TYPE_TEXT_RETURN_JSON) {
	echo json_encode([
		'jsonrpc' => '2.0',
		'result' => $result
	]);

	session_write_close();
	exit();
}
elseif ($requestType == PAGE_TYPE_TEXT || $requestType == PAGE_TYPE_JS) {
	echo $result;
}

require_once dirname(__FILE__).'/include/page_footer.php';
