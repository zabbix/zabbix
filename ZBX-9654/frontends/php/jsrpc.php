<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
					$url_events = 'events.php?filter_set=1&triggerid='.$event['objectid'].'&source='.EVENT_SOURCE_TRIGGERS;
					$url_tr_events = 'tr_events.php?eventid='.$event['eventid'].'&triggerid='.$event['objectid'];

					$result[$number] = [
						'type' => 3,
						'caption' => 'events',
						'sourceid' => $event['eventid'],
						'time' => $event['clock'],
						'priority' => $priority,
						'sound' => $sound,
						'color' => getSeverityColor($trigger['priority'], $event['value']),
						'title' => $title.' [url='.$url_tr_status.']'.$host['name'].'[/url]',
						'body' => [
							_('Details').': [url='.$url_events.']'.$trigger['description'].'[/url]',
							_('Date').': [b][url='.$url_tr_events.']'.
								zbx_date2str(DATE_TIME_FORMAT_SECONDS, $event['clock']).'[/url][/b]',
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
		$session = Z::getInstance()->getSession();
		if (!isset($session['serverCheckResult']) || ($session['serverCheckTime'] + SERVER_CHECK_INTERVAL) <= time()) {
			$zabbixServer = new CZabbixServer($ZBX_SERVER, $ZBX_SERVER_PORT, ZBX_SOCKET_TIMEOUT, 0);
			$session['serverCheckResult'] = $zabbixServer->isRunning();
			$session['serverCheckTime'] = time();
		}

		$result = [
			'result' => (bool) $session['serverCheckResult'],
			'message' => $session['serverCheckResult'] ? '' : _('Zabbix server is not running: the information displayed may not be current.')
		];
		break;

	case 'screen.get':
		$options = [
			'pageFile' => !empty($data['pageFile']) ? $data['pageFile'] : null,
			'mode' => !empty($data['mode']) ? $data['mode'] : null,
			'timestamp' => !empty($data['timestamp']) ? $data['timestamp'] : time(),
			'resourcetype' => !empty($data['resourcetype']) ? $data['resourcetype'] : null,
			'screenid' => (isset($data['screenid']) && $data['screenid'] != 0) ? $data['screenid'] : null,
			'screenitemid' => !empty($data['screenitemid']) ? $data['screenitemid'] : null,
			'groupid' => !empty($data['groupid']) ? $data['groupid'] : null,
			'hostid' => !empty($data['hostid']) ? $data['hostid'] : null,
			'period' => !empty($data['period']) ? $data['period'] : null,
			'stime' => !empty($data['stime']) ? $data['stime'] : null,
			'profileIdx' => !empty($data['profileIdx']) ? $data['profileIdx'] : null,
			'profileIdx2' => !empty($data['profileIdx2']) ? $data['profileIdx2'] : null,
			'updateProfile' => isset($data['updateProfile']) ? $data['updateProfile'] : null
		];
		if ($options['resourcetype'] == SCREEN_RESOURCE_HISTORY) {
			$options['itemids'] = !empty($data['itemids']) ? $data['itemids'] : null;
			$options['action'] = !empty($data['action']) ? $data['action'] : null;
			$options['filter'] = !empty($data['filter']) ? $data['filter'] : null;
			$options['filter_task'] = !empty($data['filter_task']) ? $data['filter_task'] : null;
			$options['mark_color'] = !empty($data['mark_color']) ? $data['mark_color'] : null;
		}
		elseif ($options['resourcetype'] == SCREEN_RESOURCE_CHART) {
			$options['graphid'] = !empty($data['graphid']) ? $data['graphid'] : null;
			$options['profileIdx2'] = $options['graphid'];
		}

		$screenBase = CScreenBuilder::getScreen($options);
		if (!empty($screenBase)) {
			$screen = $screenBase->get();
		}

		if (!empty($screen)) {
			if ($options['mode'] == SCREEN_MODE_JS) {
				$result = $screen;
			}
			else {
				if (is_object($screen)) {
					$result = $screen->toString();
				}
			}
		}
		else {
			$result = '';
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
					'editable' => isset($data['editable']) ? $data['editable'] : null,
					'output' => ['triggerid', 'description'],
					'selectHosts' => ['name'],
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
