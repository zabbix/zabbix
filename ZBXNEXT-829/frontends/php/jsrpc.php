<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


require_once dirname(__FILE__).'/include/config.inc.php';

$page['title'] = 'RPC';
$page['file'] = 'jsrpc.php';
$page['hist_arg'] = array();
$page['type'] = detect_page_type(PAGE_TYPE_JSON);

require_once dirname(__FILE__).'/include/page_header.php';

$http_request = new CHTTP_request();
$data = $http_request->body();

$json = new CJSON();
$data = $json->decode($data, true);

if (!is_array($data)) {
	fatal_error('Wrong RPC call to JS RPC');
}
if (!isset($data['method']) || !isset($data['params'])) {
	fatal_error('Wrong RPC call to JS RPC');
}
if (!is_array($data['params'])) {
	fatal_error('Wrong RPC call to JS RPC');
}

$result = array();
switch ($data['method']) {
	case 'host.get':
		$search = $data['params']['search'];

		$result = API::Host()->get(array(
			'startSearch' => 1,
			'search' => $search,
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
		$params = $data['params'];
		$msgsettings = getMessageSettings();

		// if no severity is selected, show nothing
		if (empty($msgsettings['triggers.severities'])) {
			break;
		}

		// timeout
		$timeOut = (time() - $msgsettings['timeout']);
		$lastMsgTime = 0;
		if (isset($params['messageLast']['events'])) {
			$lastMsgTime = $params['messageLast']['events']['time'];
		}

		$options = array(
			'nodeids' => get_current_nodeid(true),
			'lastChangeSince' => max(array($lastMsgTime, $msgsettings['last.clock'], $timeOut)),
			'value' => array(TRIGGER_VALUE_TRUE, TRIGGER_VALUE_FALSE),
			'priority' => array_keys($msgsettings['triggers.severities']),
			'limit' => 15
		);
		if (!$msgsettings['triggers.recovery']) {
			$options['value'] = array(TRIGGER_VALUE_TRUE);
		}
		$events = getLastEvents($options);

		$sortClock = array();
		$sortEvent = array();
		foreach ($events as $number => $event) {
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
		}
		array_multisort($sortClock, SORT_ASC, $sortEvent, SORT_ASC, $result);
		break;
	case 'message.closeAll':
		$params = $data['params'];
		$msgsettings = getMessageSettings();
		switch (strtolower($params['caption'])) {
			case 'events':
				$msgsettings['last.clock'] = (int)$params['time'] + 1;
				updateMessageSettings($msgsettings);
				break;
		}
		break;
	case 'zabbix.status':
		$config = select_config();
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
	default:
		fatal_error('Wrong RPC call to JS RPC');
}

if (isset($data['id'])) {
	$rpcResp = array(
		'jsonrpc' => '2.0',
		'result' => $result,
		'id' => $data['id']
	);
	echo $json->encode($rpcResp);
}

require_once dirname(__FILE__).'/include/page_footer.php';
