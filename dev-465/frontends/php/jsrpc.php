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

			$hosts = CHost::get($options);

			$rpcResp = array(
				'jsonrpc' => '2.0',
				'result' => $hosts,
				'id' => $data['id']
			);
			break;
		case 'message.get':
			$params = $data['params'];
// Events
			$lastEventId = CProfile::get('web.messages.last.eventid', 0);

			if(isset($params['messageLast']['events'])){
				$lastEventId = max(array($params['messageLast']['events'], $lastEventId)) + 1;
			}

			$options = array(
				'object' => EVENT_OBJECT_TRIGGER,
				'time_from' => (time() - 18000), // 15 min
				'output' => API_OUTPUT_EXTEND,
				'sortfield' => 'eventid',
				'sortorder' => 'DESC',
				'limit' => 15
			);

			if($lastEventId > 0){
				$options['eventid_from'] = $lastEventId;
			}

			$events = CEvent::get($options);
			order_result($events, 'eventid', ZBX_SORT_UP);
			order_result($events, 'clock', ZBX_SORT_UP);
			
			$triggerOptions = array(
				'triggerids' => zbx_objectValues($events, 'objectid'),
				'select_hosts' => array('hostid', 'host'),
				'output' => API_OUTPUT_EXTEND,
				'expandDescription' => 1
			);
			$triggers = CTrigger::get($triggerOptions);
			$triggers = zbx_toHash($triggers, 'triggerid');

			$messages = array();
			foreach($events as $enum => $event){
				$trigger = $triggers[$event['objectid']];
				$host = reset($trigger['hosts']);
				
				$title = ($event['value'] == TRIGGER_VALUE_FALSE)?S_RESOLVED:S_PROBLEM.' '.S_ON_SMALL;

				$messages[] = array(
					'type' => 3,
					'caption' => 'events',
					'sourceid' => $event['eventid'],
					'time' => $event['clock'],
					'priority' => $trigger['priority'],
					'color' => getEventColor($trigger['priority'], $event['value']),
					'title' => $title.' '.$host['host'],
					'body' => array(
						S_DETAILS.': '.$trigger['description'],
						S_AGE.': '.zbx_date2age($event['clock'], time()),
//						S_SEVERITY.': '.get_severity_style($trigger['priority'])
					),
					'timeout' => 60,
'options' => $options

				);
			}

			$rpcResp = array(
				'jsonrpc' => '2.0',
				'result' => $messages,
				'id' => $data['id']
			);
		break;
		case 'message.close':
			$closeMessage = $data['params'];
			
			$rpcResp = array(
				'jsonrpc' => '2.0',
				'result' => $closeMessage,
				'id' => $data['id']
			);
		break;
		default:
			fatal_error('Wrong RPC call to JS RPC');
	}

	if(isset($data['id'])) print($json->encode($rpcResp));
?>
<?php

include_once('include/page_footer.php');

?>