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
?>
<?php
require_once('include/config.inc.php');

$page['title'] = "RPC";
$page['file'] = 'jsrpc.php';
$page['hist_arg'] = array();

$page['type'] = detect_page_type(PAGE_TYPE_JSON);

include_once('include/page_header.php');

//		VAR				TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields = array();
	check_fields($fields);
?>
<?php
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
			$search = $data['params']['search'];

			$options = array(
				'startSearch' => 1,
				'search' => $search,
				'output' => array('hostid', 'host'),
				'sortfield' => 'host',
				'limit' => 15
			);

			$result = CHost::get($options);
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
// Events
			$msgsettings = getMessageSettings();

			// if no severity is selected, show nothing
			if (empty($msgsettings['triggers.severities'])) {
				break;
			}

// timeout
			$timeOut = (time() - $msgsettings['timeout']);
			$lastMsgTime = 0;
			if(isset($params['messageLast']['events'])){
				$lastMsgTime = $params['messageLast']['events']['time'];
			}
//---

			$options = array(
				'nodeids' => get_current_nodeid(true),
				'lastChangeSince' => max(array($lastMsgTime, $msgsettings['last.clock'], $timeOut)),
				'value' => array(TRIGGER_VALUE_TRUE, TRIGGER_VALUE_FALSE),
				'priority' => array_keys($msgsettings['triggers.severities']),
				'limit' => 15
			);
			if(!$msgsettings['triggers.recovery']) $options['value'] = array(TRIGGER_VALUE_TRUE);

			$events = getLastEvents($options);

			$sortClock = array();
			$sortEvent = array();
			foreach($events as $enum => $event){
				$trigger = $event['trigger'];
				$host = $event['host'];

				if($event['value'] == TRIGGER_VALUE_FALSE){
					$priority = 0;
					$title = S_RESOLVED;
					$sound = $msgsettings['sounds.recovery'];
				}
				else{
					$priority = $trigger['priority'];
					$title = S_PROBLEM_ON;
					$sound = $msgsettings['sounds.'.$trigger['priority']];
				}

				$url_tr_status = 'tr_status.php?hostid='.$host['hostid'];
				$url_events = 'events.php?triggerid='.$event['objectid'];
				$url_tr_events = 'tr_events.php?eventid='.$event['eventid'].'&triggerid='.$event['objectid'];

				$result[$enum] = array(
					'type' => 3,
					'caption' => 'events',
					'sourceid' => $event['eventid'],
					'time' => $event['clock'],
					'priority' => $priority,
					'sound' => $sound,
					'color' => getEventColor($trigger['priority'], $event['value']),
					'title' => $title.' '.get_node_name_by_elid($host['hostid'],null,':').'[url='.$url_tr_status.']'.$host['host'].'[/url]',
					'body' => array(
						S_DETAILS.': '.' [url='.$url_events.']'.$trigger['description'].'[/url]',
						S_DATE.': [b][url='.$url_tr_events.']'.zbx_date2str(S_DATE_FORMAT_YMDHMS, $event['clock']).'[/url][/b]',
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

			$msgsettings = getMessageSettings();
			switch(strtolower($params['caption'])){
				case 'events':
					$msgsettings['last.clock'] = (int)$params['time']+1;
					updateMessageSettings($msgsettings);
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
