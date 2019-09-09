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


require_once dirname(__FILE__).'/include/func.inc.php';
require_once dirname(__FILE__).'/include/defines.inc.php';
require_once dirname(__FILE__).'/include/classes/json/CJson.php';
require_once dirname(__FILE__).'/include/classes/user/CWebUser.php';
require_once dirname(__FILE__).'/include/classes/core/CHttpRequest.php';

$requestType = getRequest('type', PAGE_TYPE_JSON);
if ($requestType == PAGE_TYPE_JSON) {
	$http_request = new CHttpRequest();
	$json = new CJson();
	$data = $json->decode($http_request->body(), true);
}
else {
	$data = $_REQUEST;
}

if (is_array($data) && array_key_exists('method', $data)
		&& in_array($data['method'], ['message.settings', 'message.get', 'zabbix.status'])) {
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
		CSession::start();
		if (!CSession::keyExists('serverCheckResult')
				|| (CSession::getValue('serverCheckTime') + SERVER_CHECK_INTERVAL) <= time()) {
			$zabbixServer = new CZabbixServer($ZBX_SERVER, $ZBX_SERVER_PORT, ZBX_SOCKET_TIMEOUT, 0);
			CSession::setValue('serverCheckResult', $zabbixServer->isRunning(CWebUser::getSessionCookie()));
			CSession::setValue('serverCheckTime', time());
		}

		$result = [
			'result' => (bool) CSession::getValue('serverCheckResult'),
			'message' => CSession::getValue('serverCheckResult')
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
		$config = select_config();
		$result = [];

		$triggers = API::Trigger()->get([
			'output' => ['triggerid', 'priority'],
			'triggerids' => $data['triggerids'],
			'limit' => $config['search_limit']
		]);

		if ($triggers) {
			CArrayHelper::sort($triggers, [
				['field' => 'priority', 'order' => ZBX_SORT_DOWN]
			]);

			foreach ($triggers as $trigger) {
				$trigger['class_name'] = getSeverityStyle($trigger['priority']);
				$result[] = $trigger;
			}
		}
		break;

	/**
	 * Create multi select data.
	 * Supported objects: "applications", "hosts", "hostGroup", "templates", "triggers", "application_prototypes"
	 *
	 * @param string $data['objectName']
	 * @param string $data['search']
	 * @param int    $data['limit']
	 *
	 * @return array(int => array('value' => int, 'text' => string))
	 */
	case 'multiselect.get':
		$config = select_config();

		switch ($data['objectName']) {
			case 'hostGroup':
				$options = [
					'editable' => array_key_exists('editable', $data) ? $data['editable'] : false,
					'output' => ['groupid', 'name'],
					'search' => array_key_exists('search', $data) ? ['name' => $data['search']] : null,
					'filter' => array_key_exists('filter', $data) ? $data['filter'] : null,
					'limit' => array_key_exists('limit', $data) ? $data['limit'] : null,
					'real_hosts' => array_key_exists('real_hosts', $data) ? $data['real_hosts'] : null
				];
				$hostGroups = API::HostGroup()->get($options);

				if ($hostGroups) {
					if (array_key_exists('enrich_parent_groups', $data)) {
						$hostGroups = enrichParentGroups($hostGroups, [
							'real_hosts' => null
						] + $options);
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

			case 'hosts':
				$hosts = API::Host()->get([
					'editable' => array_key_exists('editable', $data) ? $data['editable'] : false,
					'output' => ['hostid', 'name'],
					'templated_hosts' => array_key_exists('templated_hosts', $data) ? $data['templated_hosts'] : null,
					'search' => array_key_exists('search', $data) ? ['name' => $data['search']] : null,
					'limit' => $config['search_limit']
				]);


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
				$items = API::Item()->get([
					'output' => ['itemid', 'hostid', 'name', 'key_'],
					'selectHosts' => ['name'],
					'hostids' => array_key_exists('hostid', $data) ? $data['hostid'] : null,
					'templated' => array_key_exists('real_hosts', $data) ? false : null,
					'webitems' => array_key_exists('webitems', $data) ? $data['webitems'] : null,
					'search' => array_key_exists('search', $data) ? ['name' => $data['search']] : null,
					'filter' => array_key_exists('filter', $data) ? $data['filter'] : null,
					'limit' => $config['search_limit']
				]);

				if ($items) {
					$items = CMacrosResolverHelper::resolveItemNames($items);
					CArrayHelper::sort($items, [
						['field' => 'name_expanded', 'order' => ZBX_SORT_UP]
					]);

					if (array_key_exists('limit', $data)) {
						$items = array_slice($items, 0, $data['limit']);
					}

					foreach ($items as $item) {
						$result[] = [
							'id' => $item['itemid'],
							'name' => $item['name_expanded'],
							'prefix' => $item['hosts'][0]['name'].NAME_DELIMITER
						];
					}
				}
				break;

			case 'templates':
				$templates = API::Template()->get([
					'editable' => isset($data['editable']) ? $data['editable'] : false,
					'output' => ['templateid', 'name'],
					'search' => isset($data['search']) ? ['name' => $data['search']] : null,
					'limit' => $config['search_limit']
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
					'limit' => $config['search_limit']
				]);

				if ($proxies) {
					CArrayHelper::sort($proxies, ['host']);

					if (isset($data['limit'])) {
						$proxies = array_slice($proxies, 0, $data['limit']);
					}

					$result = CArrayHelper::renameObjectsKeys($proxies, ['proxyid' => 'id', 'host' => 'name']);
				}
				break;

			case 'applications':
				$applications = API::Application()->get([
					'output' => ['applicationid', 'name'],
					'hostids' => zbx_toArray($data['hostid']),
					'search' => isset($data['search']) ? ['name' => $data['search']] : null,
					'limit' => $config['search_limit']
				]);

				if ($applications) {
					CArrayHelper::sort($applications, [
						['field' => 'name', 'order' => ZBX_SORT_UP]
					]);

					if (isset($data['limit'])) {
						$applications = array_slice($applications, 0, $data['limit']);
					}

					$result = CArrayHelper::renameObjectsKeys($applications, ['applicationid' => 'id']);
				}
				break;

			case 'application_prototypes':
				$discovery_rules = API::DiscoveryRule()->get([
					'output' => [],
					'selectApplicationPrototypes' => ['application_prototypeid', 'name'],
					'itemids' => [$data['parent_discoveryid']],
					'limitSelects' => $config['search_limit']
				]);

				if ($discovery_rules) {
					$discovery_rule = $discovery_rules[0];

					if ($discovery_rule['applicationPrototypes']) {
						foreach ($discovery_rule['applicationPrototypes'] as $application_prototype) {
							if (array_key_exists('search', $data)
									&& stripos($application_prototype['name'], $data['search']) !== false) {
								$result[] = [
									'id' => $application_prototype['application_prototypeid'],
									'name' => $application_prototype['name']
								];
							}
						}

						CArrayHelper::sort($result, [['field' => 'name', 'order' => ZBX_SORT_UP]]);

						if (array_key_exists('limit', $data)) {
							$result = array_slice($result, 0, $data['limit']);
						}
					}
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
					'limit' => $config['search_limit']
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
					'output' => ['userid', 'alias', 'name', 'surname'],
					'search' => array_key_exists('search', $data)
						? [
							'alias' => $data['search'],
							'name' => $data['search'],
							'surname' => $data['search']
						]
						: null,
					'searchByAny' => true,
					'limit' => $config['search_limit']
				]);

				if ($users) {
					CArrayHelper::sort($users, [
						['field' => 'alias', 'order' => ZBX_SORT_UP]
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
					'limit' => $config['search_limit']
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
					'limit' => $config['search_limit']
				]);

				if ($drules) {
					CArrayHelper::sort($drules, [
						['field' => 'name', 'order' => ZBX_SORT_UP]
					]);

					if (array_key_exists('limit', $data)) {
						$applications = array_slice($drules, 0, $data['limit']);
					}

					$result = CArrayHelper::renameObjectsKeys($drules, ['druleid' => 'id']);
				}
				break;

		}
		break;

	case 'patternselect.get':
		$config = select_config();
		$search = (array_key_exists('search', $data) && $data['search'] !== '') ? $data['search'] : null;
		$wildcard_enabled = (strpos($search, '*') !== false);
		$result = [];

		switch ($data['objectName']) {
			case 'hosts':
				$options = [
					'output' => ['name'],
					'search' => ['name' => $search.($wildcard_enabled ? '*' : '')],
					'searchWildcardsEnabled' => $wildcard_enabled,
					'preservekeys' => true,
					'limit' => $config['search_limit']
				];

				$db_result = API::Host()->get($options);
				break;

			case 'items':
				$options = [
					'output' => ['name'],
					'search' => ['name' => $search.($wildcard_enabled ? '*' : '')],
					'searchWildcardsEnabled' => $wildcard_enabled,
					'filter' => [
						'value_type' => [ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_FLOAT],
						'flags' => ZBX_FLAG_DISCOVERY_NORMAL
					],
					'templated' => array_key_exists('real_hosts', $data) ? false : null,
					'webitems' => array_key_exists('webitems', $data) ? $data['webitems'] : null,
					'limit' => $config['search_limit']
				];

				$db_result = API::Item()->get($options);
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
		echo $json->encode([
			'jsonrpc' => '2.0',
			'result' => $result,
			'id' => $data['id']
		]);
	}
}
elseif ($requestType == PAGE_TYPE_TEXT_RETURN_JSON) {
	$json = new CJson();

	echo $json->encode([
		'jsonrpc' => '2.0',
		'result' => $result
	]);
}
elseif ($requestType == PAGE_TYPE_TEXT || $requestType == PAGE_TYPE_JS) {
	echo $result;
}

require_once dirname(__FILE__).'/include/page_footer.php';
