<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


require_once dirname(__FILE__).'/include/config.inc.php';

$requestType = getRequest('type', PAGE_TYPE_JSON);
if ($requestType == PAGE_TYPE_JSON) {
	$http_request = new CHttpRequest();
	$json = new CJson();
	$data = $json->decode($http_request->body(), true);
}
else {
	$data = $_REQUEST;
}

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
	case 'host.get':
		$result = API::Host()->get([
			'startSearch' => true,
			'search' => $data['params']['search'],
			'output' => ['hostid', 'host', 'name'],
			'sortfield' => 'name',
			'limit' => 15
		]);
		break;

	case 'message.mute':
		$msgsettings = getMessageSettings();
		$msgsettings['sounds.mute'] = 1;
		updateMessageSettings($msgsettings);
		break;

	case 'message.unmute':
		$msgsettings = getMessageSettings();
		$msgsettings['sounds.mute'] = 0;
		updateMessageSettings($msgsettings);
		break;

	case 'message.settings':
		$msgsettings = getMessageSettings();
		$msgsettings['timeout'] = timeUnitToSeconds($msgsettings['timeout']);
		$result = $msgsettings;
		break;

	case 'message.get':
		$msgsettings = getMessageSettings();

		// if no severity is selected, show nothing
		if (empty($msgsettings['triggers.severities'])) {
			break;
		}

		// timeout
		$timeout = time() - timeUnitToSeconds($msgsettings['timeout']);
		$lastMsgTime = 0;
		if (isset($data['params']['messageLast']['events'])) {
			$lastMsgTime = $data['params']['messageLast']['events']['time'];
		}

		$options = [
			'monitored' => true,
			'lastChangeSince' => max([$lastMsgTime, $msgsettings['last.clock'], $timeout]),
			'value' => [TRIGGER_VALUE_TRUE, TRIGGER_VALUE_FALSE],
			'priority' => array_keys($msgsettings['triggers.severities']),
			'triggerLimit' => 15
		];
		if (!$msgsettings['triggers.recovery']) {
			$options['value'] = [TRIGGER_VALUE_TRUE];
		}
		$events = getLastEvents($options);

		$sortClock = [];
		$sortEvent = [];

		$usedTriggers = [];
		foreach ($events as $number => $event) {
			if (count($usedTriggers) < 15) {
				if (!isset($usedTriggers[$event['objectid']])) {
					$trigger = $event['trigger'];
					$host = $event['host'];

					if ($event['value'] == TRIGGER_VALUE_FALSE) {
						$priority = 0;
						$title = _('Resolved');
						$sound = $msgsettings['sounds.recovery'];
					}
					else {
						$priority = $trigger['priority'];
						$title = _('Problem on');
						$sound = $msgsettings['sounds.'.$trigger['priority']];
					}

					$url_tr_status = 'tr_status.php?hostid='.$host['hostid'];
					$url_events = (new CUrl('zabbix.php'))
						->setArgument('action', 'problem.view')
						->setArgument('filter_triggerids[]', $event['objectid'])
						->setArgument('filter_set', '1')
						->getUrl();
					$url_tr_events = 'tr_events.php?eventid='.$event['eventid'].'&triggerid='.$event['objectid'];

					$result[$number] = [
						'type' => 3,
						'caption' => 'events',
						'sourceid' => $event['eventid'],
						'time' => $event['clock'],
						'priority' => $priority,
						'sound' => $sound,
						'severity_style' => getSeverityStyle($trigger['priority'], $event['value'] == TRIGGER_VALUE_TRUE),
						'title' => $title.' [url='.$url_tr_status.']'.CHtml::encode($host['name']).'[/url]',
						'body' => [
							'[url='.$url_events.']'.CHtml::encode($trigger['description']).'[/url]',
							'[url='.$url_tr_events.']'.
								zbx_date2str(DATE_TIME_FORMAT_SECONDS, $event['clock']).'[/url]',
						],
						'timeout' => $msgsettings['timeout']
					];

					$sortClock[$number] = $event['clock'];
					$sortEvent[$number] = $event['eventid'];
					$usedTriggers[$event['objectid']] = true;
				}
			}
			else {
				break;
			}
		}
		array_multisort($sortClock, SORT_ASC, $sortEvent, SORT_ASC, $result);
		break;

	case 'message.closeAll':
		$msgsettings = getMessageSettings();
		switch (strtolower($data['params']['caption'])) {
			case 'events':
				$msgsettings['last.clock'] = (int) $data['params']['time'] + 1;
				updateMessageSettings($msgsettings);
				break;
		}
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
			'output' => ['triggerid', 'expression', 'priority'],
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
	 * Supported objects: "applications", "hosts", "hostGroup", "templates", "triggers"
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
				$hostGroups = API::HostGroup()->get([
					'editable' => array_key_exists('editable', $data) ? $data['editable'] : false,
					'output' => ['groupid', 'name'],
					'search' => array_key_exists('search', $data) ? ['name' => $data['search']] : null,
					'filter' => array_key_exists('filter', $data) ? $data['filter'] : null,
					'limit' => array_key_exists('limit', $data) ? $data['limit'] : null
				]);

				if ($hostGroups) {
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
					'hostids' => zbx_toArray($data['hostid']),
					'output' => ['applicationid', 'name'],
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
