<?php
/*
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
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
function check_permission_for_action_conditions($conditions) {
	global $USER_DETAILS;

	if (USER_TYPE_SUPER_ADMIN == $USER_DETAILS['type']) {
		return true;
	}

	$groupids = array();
	$hostids = array();
	$triggerids = array();

	foreach ($conditions as $ac_data) {
		if ($ac_data['operator'] != 0) {
			continue;
		}

		switch ($ac_data['conditiontype']) {
			case CONDITION_TYPE_HOST_GROUP:
				$groupids[$ac_data['value']] = $ac_data['value'];
				break;
			case CONDITION_TYPE_HOST:
			case CONDITION_TYPE_HOST_TEMPLATE:
				$hostids[$ac_data['value']] = $ac_data['value'];
				break;
			case CONDITION_TYPE_TRIGGER:
				$triggerids[$ac_data['value']] = $ac_data['value'];
				break;
		}
	}

	$options = array(
		'groupids' => $groupids,
		'editable' => 1
	);

	try {
		$groups = CHostgroup::get($options);
		$groups = zbx_toHash($groups, 'groupid');
		foreach ($groupids as $hgnum => $groupid) {
			if (!isset($groups[$groupid])) {
				throw new Exception(S_INCORRECT_GROUP);
			}
		}

		$options = array(
			'hostids' => $hostids,
			'editable' => 1
		);
		$hosts = CHost::get($options);
		$hosts = zbx_toHash($hosts, 'hostid');
		foreach ($hostids as $hnum => $hostid) {
			if (!isset($hosts[$hostid])) {
				throw new Exception(S_INCORRECT_HOST);
			}
		}

		$options = array(
			'triggerids' => $triggerids,
			'editable' => 1
		);
		$triggers = CTrigger::get($options);
		$triggers = zbx_toHash($triggers, 'triggerid');
		foreach ($triggerids as $hnum => $triggerid) {
			if (!isset($triggers[$triggerid])) {
				throw new Exception(S_INCORRECT_TRIGGER);
			}
		}
	}
	catch (Exception $e) {
		return false;
	}

	return true;
}

function get_action_by_actionid($actionid){
	$sql='select * from actions where actionid='.$actionid;
	$result=DBselect($sql);

	if($row=DBfetch($result)){
		return	$row;
	}
	else{
		error('No action with actionid=['.$actionid.']');
	}
return	$result;
}

function condition_operator2str($operator){
	$str_op[CONDITION_OPERATOR_EQUAL] 	= '=';
	$str_op[CONDITION_OPERATOR_NOT_EQUAL]	= '<>';
	$str_op[CONDITION_OPERATOR_LIKE]	= S_LIKE_SMALL;
	$str_op[CONDITION_OPERATOR_NOT_LIKE]	= S_NOT_LIKE_SMALL;
	$str_op[CONDITION_OPERATOR_IN]		= S_IN_SMALL;
	$str_op[CONDITION_OPERATOR_MORE_EQUAL]	= '>=';
	$str_op[CONDITION_OPERATOR_LESS_EQUAL]	= '<=';
	$str_op[CONDITION_OPERATOR_NOT_IN]	= S_NOT_IN_SMALL;

	if(isset($str_op[$operator]))
		return $str_op[$operator];

	return S_UNKNOWN;
}

function condition_type2str($conditiontype){
	$str_type[CONDITION_TYPE_HOST_GROUP]		= S_HOST_GROUP;
	$str_type[CONDITION_TYPE_HOST_TEMPLATE]		= S_HOST_TEMPLATE;
	$str_type[CONDITION_TYPE_TRIGGER]		= S_TRIGGER;
	$str_type[CONDITION_TYPE_HOST]			= S_HOST;
	$str_type[CONDITION_TYPE_TRIGGER_NAME]		= S_TRIGGER_DESCRIPTION;
	$str_type[CONDITION_TYPE_TRIGGER_VALUE]		= S_TRIGGER_VALUE;
	$str_type[CONDITION_TYPE_TRIGGER_SEVERITY]	= S_TRIGGER_SEVERITY;
	$str_type[CONDITION_TYPE_TIME_PERIOD]		= S_TIME_PERIOD;
	$str_type[CONDITION_TYPE_MAINTENANCE]		= S_MAINTENANCE_STATUS;
	$str_type[CONDITION_TYPE_NODE]			= S_NODE;
	$str_type[CONDITION_TYPE_DRULE]			= S_DISCOVERY_RULE;
	$str_type[CONDITION_TYPE_DCHECK]		= S_DISCOVERY_CHECK;
	$str_type[CONDITION_TYPE_DOBJECT]		= S_DISCOVERED_OBJECT;
	$str_type[CONDITION_TYPE_DHOST_IP]		= S_HOST_IP;
	$str_type[CONDITION_TYPE_DSERVICE_TYPE]		= S_SERVICE_TYPE;
	$str_type[CONDITION_TYPE_DSERVICE_PORT]		= S_SERVICE_PORT;
	$str_type[CONDITION_TYPE_DSTATUS]		= S_DISCOVERY_STATUS;
	$str_type[CONDITION_TYPE_DUPTIME]		= S_UPTIME_DOWNTIME;
	$str_type[CONDITION_TYPE_DVALUE]		= S_RECEIVED_VALUE;
	$str_type[CONDITION_TYPE_EVENT_ACKNOWLEDGED]	= S_EVENT_ACKNOWLEDGED;
	$str_type[CONDITION_TYPE_APPLICATION]		= S_APPLICATION;
	$str_type[CONDITION_TYPE_PROXY]			= S_PROXY;
	$str_type[CONDITION_TYPE_HOST_NAME]		= S_HOST_NAME;

	if(isset($str_type[$conditiontype]))
		return $str_type[$conditiontype];

return S_UNKNOWN;
}

function discovery_object2str($object){
	$str_object[EVENT_OBJECT_DHOST]		= S_DEVICE;
	$str_object[EVENT_OBJECT_DSERVICE]	= S_SERVICE;

	if(isset($str_object[$object]))
		return $str_object[$object];

return S_UNKNOWN;
}

function condition_value2str($conditiontype, $value){
	switch($conditiontype){
		case CONDITION_TYPE_HOST_GROUP:
			$group = get_hostgroup_by_groupid($value);

			$str_val = '';
			if(id2nodeid($value) != get_current_nodeid()) $str_val = get_node_name_by_elid($value, true, ': ');
			$str_val.= $group['name'];
			break;
		case CONDITION_TYPE_TRIGGER:
			$trig = CTrigger::get(array(
				'triggerids' => $value,
				'expandTriggerDescriptions' => true,
				'output' => API_OUTPUT_EXTEND,
				'select_hosts' => API_OUTPUT_EXTEND,
				'nodeids' => get_current_nodeid(true),
			));
			$trig = reset($trig);
			$host = reset($trig['hosts']);
			$str_val = '';
			if(id2nodeid($value) != get_current_nodeid()) $str_val = get_node_name_by_elid($value, true, ': ');
			$str_val .= $host['host'].':'.$trig['description'];
			break;
		case CONDITION_TYPE_HOST:
		case CONDITION_TYPE_HOST_TEMPLATE:
			$host = get_host_by_hostid($value);
			$str_val = '';
			if(id2nodeid($value) != get_current_nodeid()) $str_val = get_node_name_by_elid($value, true, ': ');
			$str_val.= $host['host'];
			break;
		case CONDITION_TYPE_TRIGGER_NAME:
		case CONDITION_TYPE_HOST_NAME:
			$str_val = $value;
			break;
		case CONDITION_TYPE_TRIGGER_VALUE:
			$str_val = trigger_value2str($value);
			break;
		case CONDITION_TYPE_TRIGGER_SEVERITY:
			$str_val = get_severity_description($value);
			break;
		case CONDITION_TYPE_TIME_PERIOD:
			$str_val = $value;
			break;
		case CONDITION_TYPE_MAINTENANCE:
			$str_val = S_MAINTENANCE_SMALL;
			break;
		case CONDITION_TYPE_NODE:
			$node = get_node_by_nodeid($value);
			$str_val = $node['name'];
			break;
		case CONDITION_TYPE_DRULE:
			$drule = get_discovery_rule_by_druleid($value);
			$str_val = $drule['name'];
			break;
		case CONDITION_TYPE_DCHECK:
			$row = DBfetch(DBselect('SELECT DISTINCT r.name,c.dcheckid,c.type,c.key_,c.snmp_community,c.ports'.
					' FROM drules r,dchecks c WHERE r.druleid=c.druleid AND c.dcheckid='.$value));
			$str_val = $row['name'].':'.discovery_check2str($row['type'],
					$row['snmp_community'], $row['key_'], $row['ports']);
			break;
		case CONDITION_TYPE_DOBJECT:
			$str_val = discovery_object2str($value);
			break;
		case CONDITION_TYPE_PROXY:
			$host = get_host_by_hostid($value);
			$str_val = $host['host'];
			break;
		case CONDITION_TYPE_DHOST_IP:
			$str_val = $value;
			break;
		case CONDITION_TYPE_DSERVICE_TYPE:
			$str_val = discovery_check_type2str($value);
			break;
		case CONDITION_TYPE_DSERVICE_PORT:
			$str_val = $value;
			break;
		case CONDITION_TYPE_DSTATUS:
			$str_val = discovery_object_status2str($value);
			break;
		case CONDITION_TYPE_DUPTIME:
			$str_val = $value;
			break;
		case CONDITION_TYPE_DVALUE:
			$str_val = $value;
			break;
		case CONDITION_TYPE_EVENT_ACKNOWLEDGED:
			$str_val = ($value)?S_ACK:S_NOT_ACK;
			break;
		case CONDITION_TYPE_APPLICATION:
			$str_val = $value;
			break;
		default:
			return S_UNKNOWN;
			break;
	}
	return '"'.$str_val.'"';
}

function get_condition_desc($conditiontype, $operator, $value){
	return condition_type2str($conditiontype).' '.
		condition_operator2str($operator).' '.
		condition_value2str($conditiontype, $value);
}

define('LONG_DESCRITION', 0);
define('SHORT_DESCRITION', 1);

function get_operation_desc($type=SHORT_DESCRITION, $data){
	$result = null;

	switch($type){
		case SHORT_DESCRITION:
			switch($data['operationtype']){
				case OPERATION_TYPE_MESSAGE:
					switch($data['object']){
						case OPERATION_OBJECT_USER:
							$obj_data = CUser::get(array('userids' => $data['objectid'],  'output' => API_OUTPUT_EXTEND));
							$obj_data = reset($obj_data);

							$obj_data = S_USER.' "'.$obj_data['alias'].'"';
							break;
						case OPERATION_OBJECT_GROUP:
							$obj_data = CUserGroup::get(array('usrgrpids' => $data['objectid'],  'output' => API_OUTPUT_EXTEND));
							$obj_data = reset($obj_data);

							$obj_data = S_GROUP.' "'.$obj_data['name'].'"';
							break;
					}
					$result = S_SEND_MESSAGE_TO.' '.$obj_data;
					break;
				case OPERATION_TYPE_COMMAND:
					$result = S_RUN_REMOTE_COMMANDS;
					break;
				case OPERATION_TYPE_HOST_ADD:
					$result = S_ADD_HOST;
					break;
				case OPERATION_TYPE_HOST_REMOVE:
					$result = S_REMOVE_HOST;
					break;
				case OPERATION_TYPE_HOST_ENABLE:
					$result = S_ENABLE_HOST;
					break;
				case OPERATION_TYPE_HOST_DISABLE:
					$result = S_DISABLE_HOST;
					break;
				case OPERATION_TYPE_GROUP_ADD:
					$obj_data = get_hostgroup_by_groupid($data['objectid']);
					$result = S_ADD_TO_GROUP.' "'.$obj_data['name'].'"';
					break;
				case OPERATION_TYPE_GROUP_REMOVE:
					$obj_data = get_hostgroup_by_groupid($data['objectid']);
					$result = S_DELETE_FROM_GROUP.' "'.$obj_data['name'].'"';
					break;
				case OPERATION_TYPE_TEMPLATE_ADD:
					$obj_data = get_host_by_hostid($data['objectid']);
					$result = S_LINK_TO_TEMPLATE.' "'.$obj_data['host'].'"';
					break;
				case OPERATION_TYPE_TEMPLATE_REMOVE:
					$obj_data = get_host_by_hostid($data['objectid']);
					$result = S_UNLINK_FROM_TEMPLATE.' "'.$obj_data['host'].'"';
					break;
				default: break;
			}
			break;
		case LONG_DESCRITION:
			switch($data['operationtype']){
				case OPERATION_TYPE_MESSAGE:
					if(isset($data['default_msg']) && !empty($data['default_msg'])){
						if(isset($_REQUEST['def_shortdata']) && isset($_REQUEST['def_longdata'])){
							$temp = bold(S_SUBJECT.': ');
							$result = $temp->ToString()."\n".$_REQUEST['def_shortdata']."\n";
							$temp = bold(S_MESSAGE.':');
							$result .= $temp->ToString()."\n".$_REQUEST['def_longdata'];
						}
						else if(isset($data['operationid'])){
							$sql = 'SELECT a.def_shortdata,a.def_longdata '.
									' FROM actions a, operations o '.
									' WHERE a.actionid=o.actionid '.
										' AND o.operationid='.$data['operationid'];
							if($rows = DBfetch(DBselect($sql,1))){
								$temp = bold(S_SUBJECT.': ');
								$result = $temp->ToString()."\n".$rows['def_shortdata']."\n";
								$temp = bold(S_MESSAGE.':');
								$result .= $temp->ToString()."\n".$rows['def_longdata'];
							}
						}
					}
					else{
						$temp = bold(S_SUBJECT.': ');
						$result = $temp->ToString().$data['shortdata']."\n";
						$temp = bold(S_MESSAGE.':');
						$result .= $temp->ToString().$data['longdata'];
					}

					break;
				case OPERATION_TYPE_COMMAND:
					$temp = bold(S_REMOTE_COMMANDS.': ');
					$result = $temp->ToString().$data['longdata'];
					break;
				default: break;
			}
			break;
		default:
			break;
	}

	return $result;
}

function get_conditions_by_eventsource($eventsource){
	$conditions[EVENT_SOURCE_TRIGGERS] = array(
			CONDITION_TYPE_APPLICATION,
//			CONDITION_TYPE_EVENT_ACKNOWLEDGED,
			CONDITION_TYPE_HOST_GROUP,
			CONDITION_TYPE_HOST_TEMPLATE,
			CONDITION_TYPE_HOST,
			CONDITION_TYPE_TRIGGER,
			CONDITION_TYPE_TRIGGER_NAME,
			CONDITION_TYPE_TRIGGER_SEVERITY,
			CONDITION_TYPE_TRIGGER_VALUE,
			CONDITION_TYPE_TIME_PERIOD,
			CONDITION_TYPE_MAINTENANCE
		);
	$conditions[EVENT_SOURCE_DISCOVERY] = array(
			CONDITION_TYPE_DHOST_IP,
			CONDITION_TYPE_DSERVICE_TYPE,
			CONDITION_TYPE_DSERVICE_PORT,
			CONDITION_TYPE_DRULE,
			CONDITION_TYPE_DCHECK,
			CONDITION_TYPE_DOBJECT,
			CONDITION_TYPE_DSTATUS,
			CONDITION_TYPE_DUPTIME,
			CONDITION_TYPE_DVALUE,
			CONDITION_TYPE_PROXY
		);
	$conditions[EVENT_SOURCE_AUTO_REGISTRATION] = array(
			CONDITION_TYPE_HOST_NAME,
			CONDITION_TYPE_PROXY
		);

	if (ZBX_DISTRIBUTED)
		array_push($conditions[EVENT_SOURCE_TRIGGERS], CONDITION_TYPE_NODE);

	if(isset($conditions[$eventsource]))
		return $conditions[$eventsource];

	return $conditions[EVENT_SOURCE_TRIGGERS];
}

function get_opconditions_by_eventsource($eventsource){
	$conditions = array(
		EVENT_SOURCE_TRIGGERS => array(
			CONDITION_TYPE_EVENT_ACKNOWLEDGED
		),
		EVENT_SOURCE_DISCOVERY => array(),
		);

	if(isset($conditions[$eventsource]))
		return $conditions[$eventsource];

}

function get_operations_by_eventsource($eventsource){
	$operations[EVENT_SOURCE_TRIGGERS] = array(
			OPERATION_TYPE_MESSAGE,
			OPERATION_TYPE_COMMAND
		);
	$operations[EVENT_SOURCE_DISCOVERY] = array(
			OPERATION_TYPE_MESSAGE,
			OPERATION_TYPE_COMMAND,
			OPERATION_TYPE_HOST_ADD,
			OPERATION_TYPE_HOST_REMOVE,
			OPERATION_TYPE_HOST_ENABLE,
			OPERATION_TYPE_HOST_DISABLE,
			OPERATION_TYPE_GROUP_ADD,
			OPERATION_TYPE_GROUP_REMOVE,
			OPERATION_TYPE_TEMPLATE_ADD,
			OPERATION_TYPE_TEMPLATE_REMOVE
		);
	$operations[EVENT_SOURCE_AUTO_REGISTRATION] = array(
			OPERATION_TYPE_MESSAGE,
			OPERATION_TYPE_COMMAND,
			OPERATION_TYPE_HOST_ADD,
			OPERATION_TYPE_HOST_DISABLE,
			OPERATION_TYPE_GROUP_ADD,
			OPERATION_TYPE_TEMPLATE_ADD
		);

	if(isset($operations[$eventsource]))
		return $operations[$eventsource];

	return $operations[EVENT_SOURCE_TRIGGERS];
}

function	operation_type2str($type)
{
	$str_type[OPERATION_TYPE_MESSAGE]		= S_SEND_MESSAGE;
	$str_type[OPERATION_TYPE_COMMAND]		= S_REMOTE_COMMAND;
	$str_type[OPERATION_TYPE_HOST_ADD]		= S_ADD_HOST;
	$str_type[OPERATION_TYPE_HOST_REMOVE]		= S_REMOVE_HOST;
	$str_type[OPERATION_TYPE_HOST_ENABLE]		= S_ENABLE_HOST;
	$str_type[OPERATION_TYPE_HOST_DISABLE]		= S_DISABLE_HOST;
	$str_type[OPERATION_TYPE_GROUP_ADD]		= S_ADD_TO_GROUP;
	$str_type[OPERATION_TYPE_GROUP_REMOVE]		= S_DELETE_FROM_GROUP;
	$str_type[OPERATION_TYPE_TEMPLATE_ADD]		= S_LINK_TO_TEMPLATE;
	$str_type[OPERATION_TYPE_TEMPLATE_REMOVE]	= S_UNLINK_FROM_TEMPLATE;

	if(isset($str_type[$type]))
		return $str_type[$type];

	return S_UNKNOWN;
}

function	get_operators_by_conditiontype($conditiontype)
{
	$operators[CONDITION_TYPE_HOST_GROUP] = array(
			CONDITION_OPERATOR_EQUAL,
			CONDITION_OPERATOR_NOT_EQUAL
		);
	$operators[CONDITION_TYPE_HOST_TEMPLATE] = array(
			CONDITION_OPERATOR_EQUAL,
			CONDITION_OPERATOR_NOT_EQUAL
		);
	$operators[CONDITION_TYPE_HOST] = array(
			CONDITION_OPERATOR_EQUAL,
			CONDITION_OPERATOR_NOT_EQUAL
		);
	$operators[CONDITION_TYPE_TRIGGER] = array(
			CONDITION_OPERATOR_EQUAL,
			CONDITION_OPERATOR_NOT_EQUAL
		);
	$operators[CONDITION_TYPE_TRIGGER_NAME] = array(
			CONDITION_OPERATOR_LIKE,
			CONDITION_OPERATOR_NOT_LIKE
		);
	$operators[CONDITION_TYPE_TRIGGER_SEVERITY] = array(
			CONDITION_OPERATOR_EQUAL,
			CONDITION_OPERATOR_NOT_EQUAL,
			CONDITION_OPERATOR_MORE_EQUAL,
			CONDITION_OPERATOR_LESS_EQUAL
		);
	$operators[CONDITION_TYPE_TRIGGER_VALUE] = array(
			CONDITION_OPERATOR_EQUAL
		);
	$operators[CONDITION_TYPE_TIME_PERIOD] = array(
			CONDITION_OPERATOR_IN,
			CONDITION_OPERATOR_NOT_IN
		);
	$operators[CONDITION_TYPE_MAINTENANCE] = array(
			CONDITION_OPERATOR_IN,
			CONDITION_OPERATOR_NOT_IN
		);
	$operators[CONDITION_TYPE_NODE] = array(
			CONDITION_OPERATOR_EQUAL,
			CONDITION_OPERATOR_NOT_EQUAL
		);
	$operators[CONDITION_TYPE_DRULE] = array(
			CONDITION_OPERATOR_EQUAL,
			CONDITION_OPERATOR_NOT_EQUAL
		);
	$operators[CONDITION_TYPE_DCHECK] = array(
			CONDITION_OPERATOR_EQUAL,
			CONDITION_OPERATOR_NOT_EQUAL
		);
	$operators[CONDITION_TYPE_DOBJECT] = array(
			CONDITION_OPERATOR_EQUAL,
		);
	$operators[CONDITION_TYPE_PROXY] = array(
			CONDITION_OPERATOR_EQUAL,
			CONDITION_OPERATOR_NOT_EQUAL
		);
	$operators[CONDITION_TYPE_DHOST_IP] = array(
			CONDITION_OPERATOR_EQUAL,
			CONDITION_OPERATOR_NOT_EQUAL
		);
	$operators[CONDITION_TYPE_DSERVICE_TYPE] = array(
			CONDITION_OPERATOR_EQUAL,
			CONDITION_OPERATOR_NOT_EQUAL
		);
	$operators[CONDITION_TYPE_DSERVICE_PORT] = array(
			CONDITION_OPERATOR_EQUAL,
			CONDITION_OPERATOR_NOT_EQUAL
		);
	$operators[CONDITION_TYPE_DSTATUS] = array(
			CONDITION_OPERATOR_EQUAL,
		);
	$operators[CONDITION_TYPE_DUPTIME] = array(
			CONDITION_OPERATOR_MORE_EQUAL,
			CONDITION_OPERATOR_LESS_EQUAL
		);
	$operators[CONDITION_TYPE_DVALUE] = array(
			CONDITION_OPERATOR_EQUAL,
			CONDITION_OPERATOR_NOT_EQUAL,
			CONDITION_OPERATOR_MORE_EQUAL,
			CONDITION_OPERATOR_LESS_EQUAL,
			CONDITION_OPERATOR_LIKE,
			CONDITION_OPERATOR_NOT_LIKE
		);
	$operators[CONDITION_TYPE_EVENT_ACKNOWLEDGED] = array(
			CONDITION_OPERATOR_EQUAL
		);
	$operators[CONDITION_TYPE_APPLICATION] = array(
			CONDITION_OPERATOR_EQUAL,
			CONDITION_OPERATOR_LIKE,
			CONDITION_OPERATOR_NOT_LIKE
		);
	$operators[CONDITION_TYPE_HOST_NAME] = array(
			CONDITION_OPERATOR_LIKE,
			CONDITION_OPERATOR_NOT_LIKE
		);

	if(isset($operators[$conditiontype]))
		return $operators[$conditiontype];

	return array();
}

function	update_action_status($actionid, $status)
{
	return DBexecute("update actions set status=$status where actionid=$actionid");
}

function validate_condition($conditiontype, $value){
	global $USER_DETAILS;

	switch($conditiontype){
		case CONDITION_TYPE_HOST_GROUP:
			$groups = CHostGroup::get(array(
				'groupids' => $value,
				'output' => API_OUTPUT_SHORTEN,
				'nodeids' => get_current_nodeid(true),
			));
			if(empty($groups)){
				error(S_INCORRECT_GROUP);
				return false;
			}
			break;
		case CONDITION_TYPE_HOST_TEMPLATE:
			$templates = CTemplate::get(array(
				'templateids' => $value,
				'output' => API_OUTPUT_SHORTEN,
				'nodeids' => get_current_nodeid(true),
			));
			if(empty($templates)){
				error(S_INCORRECT_HOST);
				return false;
			}
			break;
		case CONDITION_TYPE_TRIGGER:
			$triggers = CTrigger::get(array(
				'triggerids' => $value,
				'output' => API_OUTPUT_SHORTEN,
				'nodeids' => get_current_nodeid(true),
			));
			if(empty($triggers)){
				error(S_INCORRECT_TRIGGER);
				return false;
			}
			break;
		case CONDITION_TYPE_HOST:
			$hosts = CHost::get(array(
				'hostids' => $value,
				'output' => API_OUTPUT_SHORTEN,
				'nodeids' => get_current_nodeid(true),
			));
			if(empty($hosts)){
				error(S_INCORRECT_HOST);
				return false;
			}
			break;
		case CONDITION_TYPE_TIME_PERIOD:
			if( !validate_period($value) ){
				error(S_INCORRECT_PERIOD.' ['.$value.']');
				return false;
			}
			break;
		case CONDITION_TYPE_DHOST_IP:
			if( !validate_ip_range($value) ){
				error(S_INCORRECT_IP.' ['.$value.']');
				return false;
			}
			break;
		case CONDITION_TYPE_DSERVICE_TYPE:
			if( S_UNKNOWN == discovery_check_type2str($value) ){
				error(S_INCORRECT_DISCOVERY_CHECK);
				return false;
			}
			break;
		case CONDITION_TYPE_DSERVICE_PORT:
			if( !validate_port_list($value) ){
				error(S_INCORRECT_PORT.' ['.$value.']');
				return false;
			}
			break;
		case CONDITION_TYPE_DSTATUS:
			if( S_UNKNOWN == discovery_object_status2str($value) ){
				error(S_INCORRECT_DISCOVERY_STATUS);
				return false;
			}
			break;
		case CONDITION_TYPE_EVENT_ACKNOWLEDGED:
			if(S_UNKNOWN == condition_value2str($conditiontype,$value)){
				error(S_INCORRECT_DISCOVERY_STATUS);
				return false;
			}
			break;

		case CONDITION_TYPE_TRIGGER_NAME:
		case CONDITION_TYPE_TRIGGER_VALUE:
		case CONDITION_TYPE_TRIGGER_SEVERITY:
		case CONDITION_TYPE_MAINTENANCE:
		case CONDITION_TYPE_NODE:
		case CONDITION_TYPE_DRULE:
		case CONDITION_TYPE_DCHECK:
		case CONDITION_TYPE_DOBJECT:
		case CONDITION_TYPE_PROXY:
		case CONDITION_TYPE_DUPTIME:
		case CONDITION_TYPE_DVALUE:
		case CONDITION_TYPE_APPLICATION:
		case CONDITION_TYPE_HOST_NAME:
			break;
		default:
			error(S_INCORRECT_CONDITION_TYPE);
			return false;
			break;
	}
	return true;
}

function validate_operation($operation){
	if(isset($operation['esc_period']) && (($operation['esc_period'] > 0) && ($operation['esc_period'] < 60))){
		error(S_INCORRECT_ESCALATION_PERIOD);
		return false;
	}

	switch($operation['operationtype']){
		case OPERATION_TYPE_MESSAGE:
			switch($operation['object']){
				case OPERATION_OBJECT_USER:
					$users = CUser::get(array('userids' => $operation['objectid'],  'output' => API_OUTPUT_EXTEND));
					if(empty($users)){
						error(S_INCORRECT_USER);
						return false;
					}
					break;
				case OPERATION_OBJECT_GROUP:
					$usrgrps = CUserGroup::get(array('usrgrpids' => $operation['objectid'],  'output' => API_OUTPUT_EXTEND));
					if(empty($usrgrps)){
						error(S_INCORRECT_GROUP);
						return false;
					}
					break;
				default:
					error(S_INCORRECT_OBJECT_TYPE);
					return false;
			}
			break;
		case OPERATION_TYPE_COMMAND:
			return validate_commands($operation['longdata']);
		case OPERATION_TYPE_HOST_ADD:
		case OPERATION_TYPE_HOST_REMOVE:
		case OPERATION_TYPE_HOST_ENABLE:
		case OPERATION_TYPE_HOST_DISABLE:
			break;
		case OPERATION_TYPE_GROUP_ADD:
		case OPERATION_TYPE_GROUP_REMOVE:
			$groups = CHostGroup::get(array(
				'groupids' => $operation['objectid'],
				'output' => API_OUTPUT_SHORTEN,
				'editable' => 1,
			));
			if(empty($groups)){
				error(S_INCORRECT_GROUP);
				return false;
			}
			break;
		case OPERATION_TYPE_TEMPLATE_ADD:
		case OPERATION_TYPE_TEMPLATE_REMOVE:
			$tpls = CTemplate::get(array(
				'templateids' => $operation['objectid'],
				'output' => API_OUTPUT_SHORTEN,
				'editable' => 1,
			));
			if(empty($tpls)){
				error(S_INCORRECT_HOST);
				return false;
			}
			break;
		default:
			error(S_INCORRECT_OPERATION_TYPE);
			return false;
	}
return true;
}

function validate_commands($commands){
	$cmd_list = explode("\n",$commands);
	foreach($cmd_list as $cmd){
		$cmd = trim($cmd, "\x00..\x1F");
//		if(!ereg("^(({HOSTNAME})|".ZBX_EREG_INTERNAL_NAMES.")(:|#)[[:print:]]*$",$cmd,$cmd_items)){
		if(!preg_match("/^(({HOSTNAME})|".ZBX_PREG_INTERNAL_NAMES.")(:|#)[".ZBX_PREG_PRINT."]*$/", $cmd, $cmd_items)){
			error(S_INCORRECT_COMMAND.": '$cmd'");
			return FALSE;
		}

		if($cmd_items[4] == '#'){ // group
			if(!DBfetch(DBselect('select groupid from groups where name='.zbx_dbstr($cmd_items[1])))){
				error(S_UNKNOWN_GROUP_NAME.": '".$cmd_items[1]."' ".S_IN_COMMAND_SMALL." '".$cmd."'");
				return FALSE;
			}
		}
		else if($cmd_items[4] == ':'){ // host
			if(($cmd_items[1] != '{HOSTNAME}') && !DBfetch(DBselect('select hostid from hosts where host='.zbx_dbstr($cmd_items[1])))){
				error(S_UNKNOWN_HOST_NAME.": '".$cmd_items[1]."' ".S_IN_COMMAND_SMALL." '".$cmd."'");
				return FALSE;
			}
		}
	}
	return TRUE;
}

function count_operations_delay($operations, $def_period = 0) {
	$delays = array(1 => 0);
	$periods = array();
	$max_step = 0;

	foreach ($operations as $operation) {
		$step_to = $operation['esc_step_to'] ? $operation['esc_step_to'] : 9999;
		$esc_period = $operation['esc_period'] ? $operation['esc_period'] : $def_period;

		if ($max_step < $operation['esc_step_from']) {
			$max_step = $operation['esc_step_from'];
		}

		for ($i = $operation['esc_step_from']; $i <= $step_to; $i++) {
			if (!isset($periods[$i]) || $periods[$i] > $esc_period) {
				$periods[$i] = $esc_period;
			}
		}
	}

	for ($i = 1; $i <= $max_step; $i++) {
		$esc_period = isset($periods[$i]) ? $periods[$i] : $def_period;
		$delays[$i + 1] = $delays[$i] + $esc_period;
	}

	return $delays;
}

function get_history_of_actions($limit,&$last_clock=null,$sql_cond=''){
	validate_sort_and_sortorder('clock', ZBX_SORT_DOWN);
	$available_triggers = get_accessible_triggers(PERM_READ_ONLY, array());

	$alerts = array();
	$clock = array();
	$table = new CTableInfo(S_NO_ACTIONS_FOUND);
	$table->setHeader(array(
			is_show_all_nodes() ? make_sorting_header(S_NODES,'a.alertid') : null,
			make_sorting_header(S_TIME,'clock'),
			make_sorting_header(S_TYPE,'description'),
			make_sorting_header(S_STATUS,'status'),
			make_sorting_header(S_RETRIES_LEFT,'retries'),
			make_sorting_header(S_RECIPIENTS,'sendto'),
			S_MESSAGE,
			S_ERROR
			));

	$sql = 'SELECT a.alertid,a.clock,mt.description,a.sendto,a.subject,a.message,a.status,a.retries,a.error '.
			' FROM events e, alerts a '.
				' LEFT JOIN media_type mt ON mt.mediatypeid=a.mediatypeid '.
			' WHERE e.eventid = a.eventid '.
				' AND alerttype IN ('.ALERT_TYPE_MESSAGE.') '.
				$sql_cond.
				' AND '.DBcondition('e.objectid',$available_triggers).
				' AND '.DBin_node('a.alertid').
			' ORDER BY a.clock DESC';
	$result = DBselect($sql,$limit);
	while($row=DBfetch($result)){
		$alerts[] = $row;
		$clock[] = $row['clock'];
	}

	$last_clock = !empty($clock)?min($clock):null;

	$sortfield = getPageSortField('clock');
	$sortorder = getPageSortOrder();

	order_result($alerts, $sortfield, $sortorder);

	foreach($alerts as $num => $row){
		$time=zbx_date2str(S_HISTORY_OF_ACTIONS_DATE_FORMAT,$row['clock']);

		if($row['status'] == ALERT_STATUS_SENT){
			$status=new CSpan(S_SENT,'green');
			$retries=new CSpan(SPACE,'green');
		}
		else if($row['status'] == ALERT_STATUS_NOT_SENT){
			$status=new CSpan(S_IN_PROGRESS,'orange');
			$retries=new CSpan(ALERT_MAX_RETRIES - $row['retries'],'orange');
		}
		else{
			$status=new CSpan(S_NOT_SENT,'red');
			$retries=new CSpan(0,'red');
		}
		$sendto=$row['sendto'];

		$message = array(bold(S_SUBJECT.': '),br(),$row['subject'],br(),br(),bold(S_MESSAGE.': '),br(),$row['message']);

		if(empty($row['error'])){
			$error=new CSpan(SPACE,'off');
		}
		else{
			$error=new CSpan($row['error'],'on');
		}

		$table->addRow(array(
			get_node_name_by_elid($row['alertid']),
			new CCol($time, 'top'),
			new CCol($row['description'], 'top'),
			new CCol($status, 'top'),
			new CCol($retries, 'top'),
			new CCol($sendto, 'top'),
			new CCol($message, 'top'),
			new CCol($error, 'wraptext top')));
	}
return $table;
}

// Author: Aly
function get_action_msgs_for_event($eventid){

	$table = new CTableInfo(S_NO_ACTIONS_FOUND);
	$table->setHeader(array(
		is_show_all_nodes() ? S_NODES:null,
		S_TIME,
		S_TYPE,
		S_STATUS,
		S_RETRIES_LEFT,
		S_RECIPIENTS,
		S_MESSAGE,
		S_ERROR
	));


	$alerts = CAlert::get(array(
		'eventids' => $eventid,
		'filter' => array(
			'alerttype' => ALERT_TYPE_MESSAGE,
		),
		'output' => API_OUTPUT_EXTEND,
		'select_mediatypes' => API_OUTPUT_EXTEND,
		'sortfield' => 'clock',
		'sortorder' => ZBX_SORT_DOWN
	));

	foreach($alerts as $alertid => $row){
// mediatypes
		$mediatype = array_pop($row['mediatypes']);

		$time=zbx_date2str(S_EVENT_ACTION_MESSAGES_DATE_FORMAT,$row["clock"]);
		if($row['esc_step'] > 0){
			$time = array(bold(S_STEP.': '),$row["esc_step"],br(),bold(S_TIME.': '),br(),$time);
		}

		if($row["status"] == ALERT_STATUS_SENT){
			$status=new CSpan(S_SENT,"green");
			$retries=new CSpan(SPACE,"green");
		}
		else if($row["status"] == ALERT_STATUS_NOT_SENT){
			$status=new CSpan(S_IN_PROGRESS,"orange");
			$retries=new CSpan(ALERT_MAX_RETRIES - $row["retries"],"orange");
		}
		else{
			$status=new CSpan(S_NOT_SENT,"red");
			$retries=new CSpan(0,"red");
		}
		$sendto=$row["sendto"];

		$message = array(bold(S_SUBJECT.':'),br(),$row["subject"],br(),br(),bold(S_MESSAGE.':'));
		$msg = explode("\n",$row['message']);

		foreach($msg as $m){
			array_push($message, BR(), $m);
		}

		if(empty($row["error"])){
			$error=new CSpan(SPACE,"off");
		}
		else{
			$error=new CSpan($row["error"],"on");
		}

		$table->addRow(array(
			get_node_name_by_elid($row['alertid']),
			new CCol($time, 'top'),
			new CCol((!empty($mediatype['description']) ? $mediatype['description'] : ''), 'top'),
			new CCol($status, 'top'),
			new CCol($retries, 'top'),
			new CCol($sendto, 'top'),
			new CCol($message, 'wraptext top'),
			new CCol($error, 'wraptext top')));
	}

return $table;
}

// Author: Aly
function get_action_cmds_for_event($eventid){

	$table = new CTableInfo(S_NO_ACTIONS_FOUND);
	$table->setHeader(array(
		is_show_all_nodes()?S_NODES:null,
		S_TIME,
		S_STATUS,
		S_COMMAND,
		S_ERROR
	));


	$alerts = CAlert::get(array(
		'eventids' => $eventid,
		'filter' => array(
			'alerttype' => ALERT_TYPE_COMMAND
		),
		'output' => API_OUTPUT_EXTEND,
		'sortfield' => 'clock',
		'sortorder' => ZBX_SORT_DOWN
	));

	foreach($alerts as $alertid => $row){
		$time = zbx_date2str(S_EVENT_ACTION_CMDS_DATE_FORMAT, $row['clock']);
		if($row['esc_step'] > 0){
			$time = array(bold(S_STEP.': '), $row['esc_step'], br(), bold(S_TIME.': '), br(), $time);
		}

		switch($row['status']){
			case ALERT_STATUS_SENT:
				$status = new CSpan(S_EXECUTED, 'green');
			break;
			case ALERT_STATUS_NOT_SENT:
				$status = new CSpan(S_IN_PROGRESS, 'orange');
			break;
			default:
				$status = new CSpan(S_NOT_SENT, 'red');
			break;
		}

		$message = array(bold(S_COMMAND.':'));
		$msg = explode('\n', $row['message']);
		foreach($msg as $m){
			array_push($message, BR(), $m);
		}

		$error = empty($row['error']) ? new CSpan(SPACE, 'off') : new CSpan($row['error'], 'on');


		$table->addRow(array(
			get_node_name_by_elid($row['alertid']),
			new CCol($time, 'top'),
			new CCol($status, 'top'),
			new CCol($message, 'wraptext top'),
			new CCol($error, 'wraptext top')
		));
	}

return $table;
}

// Author: Aly
function get_actions_hint_by_eventid($eventid,$status=NULL){
	$hostids = array();
	$sql = 'SELECT DISTINCT i.hostid '.
			' FROM events e, functions f, items i '.
			' WHERE e.eventid='.$eventid.
				' AND e.object='.EVENT_SOURCE_TRIGGERS.
				' AND f.triggerid=e.objectid '.
				' AND i.itemid=f.itemid';
	if($host = DBfetch(DBselect($sql,1))){
		$hostids[$host['hostid']] = $host['hostid'];
	}
	$available_triggers = get_accessible_triggers(PERM_READ_ONLY, $hostids);

	$tab_hint = new CTableInfo(S_NO_ACTIONS_FOUND);
	$tab_hint->setAttribute('style', 'width: 300px;');
	$tab_hint->SetHeader(array(
			is_show_all_nodes() ? S_NODES : null,
			S_USER,
			S_DETAILS,
			S_STATUS
			));
/*
	$sql = 'SELECT DISTINCT a.alertid,mt.description,a.sendto,a.status,u.alias,a.retries '.
			' FROM events e,users u,alerts a'.
			' left join media_type mt on mt.mediatypeid=a.mediatypeid'.
			' WHERE a.eventid='.$eventid.
				(is_null($status)?'':' AND a.status='.$status).
				' AND e.eventid = a.eventid'.
				' AND a.alerttype IN ('.ALERT_TYPE_MESSAGE.','.ALERT_TYPE_COMMAND.')'.
				' AND '.DBcondition('e.objectid',$available_triggers).
				' AND '.DBin_node('a.alertid').
				' AND u.userid=a.userid '.
			' ORDER BY mt.description';
//*/
	$sql = 'SELECT DISTINCT a.alertid,mt.description,u.alias,a.subject,a.message,a.sendto,a.status,a.retries,a.alerttype '.
			' FROM events e,alerts a '.
				' LEFT JOIN users u ON u.userid=a.userid '.
				' LEFT JOIN media_type mt ON mt.mediatypeid=a.mediatypeid'.
			' WHERE a.eventid='.$eventid.
				(is_null($status)?'':' AND a.status='.$status).
				' AND e.eventid = a.eventid'.
				' AND a.alerttype IN ('.ALERT_TYPE_MESSAGE.','.ALERT_TYPE_COMMAND.')'.
				' AND '.DBcondition('e.objectid',$available_triggers).
				' AND '.DBin_node('a.alertid').
			' ORDER BY a.alertid';
	$result=DBselect($sql,30);

	while($row=DBfetch($result)){

		if($row["status"] == ALERT_STATUS_SENT){
			$status=new CSpan(S_SENT,"green");
			$retries=new CSpan(SPACE,"green");
		}
		else if($row["status"] == ALERT_STATUS_NOT_SENT){
			$status=new CSpan(S_IN_PROGRESS,"orange");
			$retries=new CSpan(ALERT_MAX_RETRIES - $row["retries"],"orange");
		}
		else{
			$status=new CSpan(S_NOT_SENT,"red");
			$retries=new CSpan(0,"red");
		}

		switch($row['alerttype']){
			case ALERT_TYPE_MESSAGE:
				$message = empty($row['description'])?'-':$row['description'];
				break;
			case ALERT_TYPE_COMMAND:
				$message = array(bold(S_COMMAND.':'));
				$msg = explode("\n",$row['message']);
				foreach($msg as $m){
					array_push($message, BR(), $m);
				}
				break;
			default:
				$message = '-';
		}

		$tab_hint->addRow(array(
			get_node_name_by_elid($row['alertid']),
			empty($row['alias'])?' - ':$row['alias'],
			$message,
			$status
		));
	}

return $tab_hint;
}

function get_event_actions_status($eventid){
// Actions
	$actions= new CTable(' - ');

	$sql='SELECT COUNT(a.alertid) as cnt_all'.
			' FROM alerts a '.
			' WHERE a.eventid='.$eventid.
				' AND a.alerttype in ('.ALERT_TYPE_MESSAGE.','.ALERT_TYPE_COMMAND.')';

	$alerts=DBfetch(DBselect($sql));

	if(isset($alerts['cnt_all']) && ($alerts['cnt_all'] > 0)){
		$mixed = 0;
// Sent
		$sql='SELECT COUNT(a.alertid) as sent '.
				' FROM alerts a '.
				' WHERE a.eventid='.$eventid.
					' AND a.alerttype in ('.ALERT_TYPE_MESSAGE.','.ALERT_TYPE_COMMAND.')'.
					' AND a.status='.ALERT_STATUS_SENT;

		$tmp=DBfetch(DBselect($sql));
		$alerts['sent'] = $tmp['sent'];
		$mixed+=($alerts['sent'])?ALERT_STATUS_SENT:0;
// In progress
		$sql='SELECT COUNT(a.alertid) as inprogress '.
				' FROM alerts a '.
				' WHERE a.eventid='.$eventid.
					' AND a.alerttype in ('.ALERT_TYPE_MESSAGE.','.ALERT_TYPE_COMMAND.')'.
					' AND a.status='.ALERT_STATUS_NOT_SENT;

		$tmp=DBfetch(DBselect($sql));
		$alerts['inprogress'] = $tmp['inprogress'];
// Failed
		$sql='SELECT COUNT(a.alertid) as failed '.
				' FROM alerts a '.
				' WHERE a.eventid='.$eventid.
					' AND a.alerttype in ('.ALERT_TYPE_MESSAGE.','.ALERT_TYPE_COMMAND.')'.
					' AND a.status='.ALERT_STATUS_FAILED;

		$tmp=DBfetch(DBselect($sql));
		$alerts['failed'] = $tmp['failed'];
		$mixed+=($alerts['failed'])?ALERT_STATUS_FAILED:0;

		if($alerts['inprogress']){
			$status = new CSpan(S_IN_PROGRESS,'orange');
		}
		else if(ALERT_STATUS_SENT == $mixed){
			$status = new CSpan(S_OK,'green');
		}
		else if(ALERT_STATUS_FAILED == $mixed){
			$status = new CSpan(S_FAILED,'red');
		}
		else{
			$tdl = new CCol(($alerts['sent'])?(new CSpan($alerts['sent'],'green')):SPACE);
			$tdl->setAttribute('width','10');

			$tdr = new CCol(($alerts['failed'])?(new CSpan($alerts['failed'],'red')):SPACE);
			$tdr->setAttribute('width','10');

			$status = new CRow(array($tdl,$tdr));
		}

		$actions->addRow($status);
	}

return $actions;
}

function get_event_actions_stat_hints($eventid){
	$actions= new CTable(' - ');

	$sql='SELECT COUNT(a.alertid) as cnt '.
			' FROM alerts a '.
			' WHERE a.eventid='.$eventid.
				' AND a.alerttype in ('.ALERT_TYPE_MESSAGE.','.ALERT_TYPE_COMMAND.')';


	$alerts=DBfetch(DBselect($sql));

	if(isset($alerts['cnt']) && ($alerts['cnt'] > 0)){
		$sql='SELECT COUNT(a.alertid) as sent '.
				' FROM alerts a '.
				' WHERE a.eventid='.$eventid.
					' AND a.alerttype in ('.ALERT_TYPE_MESSAGE.','.ALERT_TYPE_COMMAND.')'.
					' AND a.status='.ALERT_STATUS_SENT;

		$alerts=DBfetch(DBselect($sql));

		$alert_cnt = new CSpan($alerts['sent'],'green');
		if($alerts['sent']){
			$hint=get_actions_hint_by_eventid($eventid,ALERT_STATUS_SENT);
			$alert_cnt->SetHint($hint);
		}
		$tdl = new CCol(($alerts['sent'])?$alert_cnt:SPACE);
		$tdl->setAttribute('width','10');

		$sql='SELECT COUNT(a.alertid) as inprogress '.
				' FROM alerts a '.
				' WHERE a.eventid='.$eventid.
					' AND a.alerttype in ('.ALERT_TYPE_MESSAGE.','.ALERT_TYPE_COMMAND.')'.
					' AND a.status='.ALERT_STATUS_NOT_SENT;

		$alerts=DBfetch(DBselect($sql));

		$alert_cnt = new CSpan($alerts['inprogress'],'orange');
		if($alerts['inprogress']){
			$hint=get_actions_hint_by_eventid($eventid,ALERT_STATUS_NOT_SENT);
			$alert_cnt->setHint($hint);
		}
		$tdc = new CCol(($alerts['inprogress'])?$alert_cnt:SPACE);
		$tdc->setAttribute('width','10');

		$sql='SELECT COUNT(a.alertid) as failed '.
				' FROM alerts a '.
				' WHERE a.eventid='.$eventid.
					' AND a.alerttype in ('.ALERT_TYPE_MESSAGE.','.ALERT_TYPE_COMMAND.')'.
					' AND a.status='.ALERT_STATUS_FAILED;

		$alerts=DBfetch(DBselect($sql));

		$alert_cnt = new CSpan($alerts['failed'],'red');
		if($alerts['failed']){
			$hint=get_actions_hint_by_eventid($eventid,ALERT_STATUS_FAILED);
			$alert_cnt->setHint($hint);
		}

		$tdr = new CCol(($alerts['failed'])?$alert_cnt:SPACE);
		$tdr->setAttribute('width','10');

		$actions->addRow(array($tdl,$tdc,$tdr));
	}
return $actions;
}
?>
