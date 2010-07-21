<?php
/*
** ZABBIX
** Copyright (C) 2000-2010 SIA Zabbix
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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

require_once('include/config.inc.php');

$page['title'] = "RPC";
$page['file'] = 'jsrpc.php';
$page['hist_arg'] = array();

$page['type'] = detect_page_type(PAGE_TYPE_JSON);

include_once('include/page_header.php');

//		VAR				TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields = array();
	check_fields($fields);

// ACTION /////////////////////////////////////////////////////////////////////////////
	$http_request = new CHTTP_request();
	$data = $http_request->body();

	$json = new CJSON();
	$data = $json->decode($data, true);

	if(!is_array($data)) fatal_error('Wrong RPC call to JS RPC');
	if(!isset($data['method']) || !isset($data['params'])) fatal_error('Wrong RPC call to JS RPC');
	if(!is_array($data['params'])) fatal_error('Wrong RPC call to JS RPC');

	$result = array();
	switch($data['method']){
		case 'host.get':
			$pattern = $data['params']['pattern'];

			$options = array(
				'startPattern' => 1,
				'pattern' => $pattern,
				'output' => array('hostid', 'host'),
				'sortfield' => 'host',
				'limit' => 15
			);

			$result = CHost::get($options);
			break;
		case 'message.mute':
			$msgsettings = getMessageSettings();
			$msgsettings['sounds']['mute'] = 1;
			updateMessageSettings($msgsettings);
			break;
		case 'message.unmute':
			$msgsettings = getMessageSettings();
			$msgsettings['sounds']['mute'] = 0;
			updateMessageSettings($msgsettings);
			break;
		case 'message.settings':
			$result = getMessageSettings();
			break;
		case 'message.get':
			$params = $data['params'];
// Events
			$msgsettings = getMessageSettings();

			$lastEventId = CProfile::get('web.messages.last.eventid', 0);
//SDI($lastEventId);
			if(isset($params['messageLast']['events'])){
				if(bccomp($params['messageLast']['events'], $lastEventId) > 0)
						$lastEventId = $params['messageLast']['events'];
				$lastEventId = bcadd($lastEventId, 1);
			}

			$triggerOptions = array(
				'lastChangeSince' => (time() - $msgsettings['timeout']), // 15 min
				'filter' => array(
					'priority' => array_keys($msgsettings['triggers']['severities']),
				),
				'select_hosts' => array('hostid', 'host'),
				'output' => API_OUTPUT_EXTEND,
				'expandDescription' => 1,
				'sortfield' => 'lastchange',
				'sortorder' => ZBX_SORT_DOWN,
				'limit' => 15
			);

			if(!$msgsettings['triggers']['recovery']){
				$triggerOptions['filter']['value'] = array(TRIGGER_VALUE_TRUE);
			}

			$triggers = CTrigger::get($triggerOptions);
			$triggers = zbx_toHash($triggers, 'triggerid');

			$options = array(
				'object' => EVENT_OBJECT_TRIGGER,
				'triggerids' => zbx_objectValues($triggers, 'triggerid'),
				'time_from' => (time() - $msgsettings['timeout']), // 15 min
				'output' => API_OUTPUT_EXTEND,
				'sortfield' => 'clock',
				'sortorder' => ZBX_SORT_DOWN,
				'limit' => 15
			);

			if($lastEventId > 0){
				$options['eventid_from'] = $lastEventId;
			}

			if(!$msgsettings['triggers']['recovery']){
				$options['value'] = TRIGGER_VALUE_TRUE;
			}

			$events = CEvent::get($options);

			$sortClock = array();
			$sortEvent = array();
			foreach($events as $enum => $event){
				if(!isset($triggers[$event['objectid']])) continue;

				$trigger = $triggers[$event['objectid']];
				$host = reset($trigger['hosts']);

				if($event['value'] == TRIGGER_VALUE_FALSE){
					$priority = 0;
					$title = S_RESOLVED;
					$sound = $msgsettings['sounds']['recovery'];
				}
				else{
					$priority = $trigger['priority'];
					$title = S_PROBLEM_ON;
					$sound = $msgsettings['sounds'][$trigger['priority']];
				}

				$url = 'tr_status.php?hostid='.$host['hostid'];

				$result[$enum] = array(
					'type' => 3,
					'caption' => 'events',
					'sourceid' => $event['eventid'],
					'time' => $event['clock'],
					'priority' => $priority,
					'sound' => $sound,
					'color' => getEventColor($trigger['priority'], $event['value']),
					'title' => $title.' [url='.$url.']'.$host['host'].'[/url]',
					'body' => array(
						S_DETAILS.': '.$trigger['description'],
						S_DATE.': [b]'.zbx_date2str(S_DATE_FORMAT_YMDHMS, $event['clock']).'[/b]',
//						S_AGE.': '.zbx_date2age($event['clock'], time()),
//						S_SEVERITY.': '.get_severity_style($trigger['priority'])
//						S_SOURCE.': '.$event['eventid'].' : '.$event['clock']
					),
					'timeout' => $msgsettings['timeout']
				);

				$sortClock[$enum] = $event['clock'];
				$sortEvent[$enum] = $event['eventid'];
			}

			array_multisort($sortClock, SORT_ASC, $sortEvent, SORT_ASC, $result);
		break;
		case 'message.closeAll':
			$params = $data['params'];

			switch(strtolower($params['caption'])){
				case 'events':
					$params['sourceid'] = bcadd($params['sourceid'], '1', 0);
					CProfile::update('web.messages.last.eventid', $params['sourceid'], PROFILE_TYPE_ID);
					break;
			}

		break;
		default:
			fatal_error('Wrong RPC call to JS RPC');
	}

	if(isset($data['id'])){
		$rpcResp = array(
			'jsonrpc' => '2.0',
			'result' => $result,
			'id' => $data['id']
		);
		
		print($json->encode($rpcResp));
	}
?>
<?php

include_once('include/page_footer.php');

?>