<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
		$result = getMessageSettings();
		break;

	case 'message.get':
		$msgsettings = getMessageSettings();

		// if no severity is selected, show nothing
		if (empty($msgsettings['triggers.severities'])) {
			break;
		}

		// timeout
		$timeout = time() - $msgsettings['timeout'];
		$lastMsgTime = 0;
		if (isset($data['params']['messageLast']['events'])) {
			$lastMsgTime = $data['params']['messageLast']['events']['time'];
		}

		$options = [
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
			CSession::setValue('serverCheckResult', $zabbixServer->isRunning());
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
					'editable' => isset($data['editable']) ? $data['editable'] : null,
					'output' => ['groupid', 'name'],
					'search' => isset($data['search']) ? ['name' => $data['search']] : null,
					'filter' => isset($data['filter']) ? $data['filter'] : null,
					'limit' => isset($data['limit']) ? $data['limit'] : null
				]);

				if ($hostGroups) {
					CArrayHelper::sort($hostGroups, [
						['field' => 'name', 'order' => ZBX_SORT_UP]
					]);

					if (isset($data['limit'])) {
						$hostGroups = array_slice($hostGroups, 0, $data['limit']);
					}

					foreach ($hostGroups as $hostGroup) {
						$result[] = [
							'id' => $hostGroup['groupid'],
							'name' => $hostGroup['name']
						];
					}
				}
				break;

			case 'hosts':
				$hosts = API::Host()->get([
					'editable' => isset($data['editable']) ? $data['editable'] : null,
					'output' => ['hostid', 'name'],
					'templated_hosts' => isset($data['templated_hosts']) ? $data['templated_hosts'] : null,
					'search' => isset($data['search']) ? ['name' => $data['search']] : null,
					'limit' => $config['search_limit']
				]);

				if ($hosts) {
					CArrayHelper::sort($hosts, [
						['field' => 'name', 'order' => ZBX_SORT_UP]
					]);

					if (isset($data['limit'])) {
						$hosts = array_slice($hosts, 0, $data['limit']);
					}

					foreach ($hosts as $host) {
						$result[] = [
							'id' => $host['hostid'],
							'name' => $host['name']
						];
					}
				}
				break;

			case 'templates':
				$templates = API::Template()->get([
					'editable' => isset($data['editable']) ? $data['editable'] : null,
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

					foreach ($templates as $template) {
						$result[] = [
							'id' => $template['templateid'],
							'name' => $template['name']
						];
					}
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

					foreach ($applications as $application) {
						$result[] = [
							'id' => $application['applicationid'],
							'name' => $application['name']
						];
					}
				}
				break;

			case 'triggers':
				$triggers = API::Trigger()->get([
					'output' => ['triggerid', 'description'],
					'selectHosts' => ['name'],
					'editable' => isset($data['editable']) ? $data['editable'] : null,
					'monitored' => isset($data['monitored']) ? $data['monitored'] : null,
					'search' => isset($data['search']) ? ['description' => $data['search']] : null,
					'limit' => $config['search_limit']
				]);

				if ($triggers) {
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
					'editable' => array_key_exists('editable', $data) ? $data['editable'] : null,
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
