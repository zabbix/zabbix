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

$requestType = get_request('type', PAGE_TYPE_JSON);
if ($requestType == PAGE_TYPE_JSON) {
	$http_request = new CHTTP_request();
	$json = new CJSON();
	$data = $json->decode($http_request->body(), true);
}
else {
	$data = $_REQUEST;
}

$page['title'] = 'RPC';
$page['file'] = 'jsrpc.php';
$page['hist_arg'] = array();
$page['type'] = detect_page_type($requestType);

require_once dirname(__FILE__).'/include/page_header.php';

if (!is_array($data) || !isset($data['method'])
		|| ($requestType == PAGE_TYPE_JSON && (!isset($data['params']) || !is_array($data['params'])))) {
	fatal_error('Wrong RPC call to JS RPC!');
}

$result = array();
switch ($data['method']) {
	case 'host.get':
		$result = API::Host()->get(array(
			'startSearch' => 1,
			'search' => $data['params']['search'],
			'output' => array('hostid', 'host', 'name'),
			'sortfield' => 'name',
			'limit' => 15
		));
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

		// On a full page refresh $lastMsgTime can be 0, if no cookie exists or a number if cookie exists.
		$lastMsgTime = 0;
		if (isset($data['params']['messageLast']['events'])) {
			$lastMsgTime = $data['params']['messageLast']['events']['time'];
		}

		/*
		 * If cookie exists, collect the events that are still required to display. In the mean time query will also
		 * collect the new events depeding on the last event time ($lastMsgTime).
		 */
		$eventids = array();
		if (array_key_exists('eventids', $data['params'])) {
			$eventids = array_filter(explode(',', $data['params']['eventids']));
		}

		/*
		 * On a full page refresh 'lastupdate' is 0, otherwise it is the last RPC call time.
		 * 'last.clock' is the last event time + 1 second and updates only when 'message.closeAll' is called.
		 */
		if ($data['params']['lastupdate'] == 0) {
			/*
			 * Events with short timeouts can happen in between full page refresh. Since RPC calls are made each
			 * 60 seconds, get events during the last minute or get messages if not yet timed out.
			 */
			$timeout = ($msgsettings['timeout'] < 60) ? 60 : $msgsettings['timeout'];
			$lastChangeSince = max(array($lastMsgTime, $msgsettings['last.clock'], time() - $timeout));
		}
		else {
			$lastChangeSince = max(array($lastMsgTime, $msgsettings['last.clock'],
				$data['params']['lastupdate'] - $msgsettings['timeout']
			));
		}

		$options = array(
			'nodeids' => get_current_nodeid(true),
			'lastChangeSince' => $lastChangeSince,
			'value' => array(TRIGGER_VALUE_TRUE, TRIGGER_VALUE_FALSE),
			'priority' => array_keys($msgsettings['triggers.severities']),
			'triggerLimit' => 15,
			'eventids' => $eventids
		);
		if (!$msgsettings['triggers.recovery']) {
			$options['value'] = array(TRIGGER_VALUE_TRUE);
		}
		$events = getLastEvents($options);

		$sortClock = array();
		$sortEvent = array();

		$usedTriggers = array();
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
					$url_events = 'events.php?triggerid='.$event['objectid'];
					$url_tr_events = 'tr_events.php?eventid='.$event['eventid'].'&triggerid='.$event['objectid'];

					$result[$number] = array(
						'type' => 3,
						'caption' => 'events',
						'sourceid' => $event['eventid'],
						'time' => $event['clock'],
						'priority' => $priority,
						'sound' => $sound,
						'color' => getSeverityColor($trigger['priority'], $event['value']),
						'title' => $title.' '.get_node_name_by_elid($host['hostid'], null, ':').'[url='.$url_tr_status.']'.$host['host'].'[/url]',
						'body' => array(
							_('Details').': [url='.$url_events.']'.$trigger['description'].'[/url]',
							_('Date').': [b][url='.$url_tr_events.']'.zbx_date2str(_('d M Y H:i:s'), $event['clock']).'[/url][/b]',
						),
						'timeout' => $msgsettings['timeout']
					);

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
			$session['serverCheckResult'] = zabbixIsRunning();
			$session['serverCheckTime'] = time();
		}

		$result = array(
			'result' => (bool) $session['serverCheckResult'],
			'message' => $session['serverCheckResult'] ? '' : _('Zabbix server is not running: the information displayed may not be current.')
		);
		break;

	case 'screen.get':
		$options = array(
			'pageFile' => !empty($data['pageFile']) ? $data['pageFile'] : null,
			'mode' => !empty($data['mode']) ? $data['mode'] : null,
			'timestamp' => !empty($data['timestamp']) ? $data['timestamp'] : time(),
			'resourcetype' => !empty($data['resourcetype']) ? $data['resourcetype'] : null,
			'screenitemid' => !empty($data['screenitemid']) ? $data['screenitemid'] : null,
			'groupid' => !empty($data['groupid']) ? $data['groupid'] : null,
			'hostid' => !empty($data['hostid']) ? $data['hostid'] : null,
			'period' => !empty($data['period']) ? $data['period'] : null,
			'stime' => !empty($data['stime']) ? $data['stime'] : null,
			'profileIdx' => !empty($data['profileIdx']) ? $data['profileIdx'] : null,
			'profileIdx2' => !empty($data['profileIdx2']) ? $data['profileIdx2'] : null,
			'updateProfile' => isset($data['updateProfile']) ? $data['updateProfile'] : null
		);
		if ($options['resourcetype'] == SCREEN_RESOURCE_HISTORY) {
			$options['itemid'] = !empty($data['itemid']) ? $data['itemid'] : null;
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

	default:
		fatal_error('Wrong RPC call to JS RPC!');
}

if ($requestType == PAGE_TYPE_JSON) {
	if (isset($data['id'])) {
		$rpcResp = array(
			'jsonrpc' => '2.0',
			'result' => $result,
			'id' => $data['id']
		);
		echo $json->encode($rpcResp);
	}
}
elseif ($requestType == PAGE_TYPE_TEXT || $requestType == PAGE_TYPE_JS) {
	echo $result;
}

require_once dirname(__FILE__).'/include/page_footer.php';
