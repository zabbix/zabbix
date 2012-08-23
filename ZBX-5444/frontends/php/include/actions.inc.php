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


function condition_operator2str($operator) {
	$str_op[CONDITION_OPERATOR_EQUAL] = '=';
	$str_op[CONDITION_OPERATOR_NOT_EQUAL] = '<>';
	$str_op[CONDITION_OPERATOR_LIKE] = _('like');
	$str_op[CONDITION_OPERATOR_NOT_LIKE] = _('not like');
	$str_op[CONDITION_OPERATOR_IN] = _('in');
	$str_op[CONDITION_OPERATOR_MORE_EQUAL] = '>=';
	$str_op[CONDITION_OPERATOR_LESS_EQUAL] = '<=';
	$str_op[CONDITION_OPERATOR_NOT_IN] = _('not in');

	if (isset($str_op[$operator])) {
		return $str_op[$operator];
	}

	return _('Unknown');
}

function condition_type2str($conditiontype) {
	$str_type[CONDITION_TYPE_HOST_GROUP] = _('Host group');
	$str_type[CONDITION_TYPE_HOST_TEMPLATE] = _('Host template');
	$str_type[CONDITION_TYPE_TRIGGER] = _('Trigger');
	$str_type[CONDITION_TYPE_HOST] = _('Host');
	$str_type[CONDITION_TYPE_TRIGGER_NAME] = _('Trigger name');
	$str_type[CONDITION_TYPE_TRIGGER_VALUE] = _('Trigger value');
	$str_type[CONDITION_TYPE_TRIGGER_SEVERITY] = _('Trigger severity');
	$str_type[CONDITION_TYPE_TIME_PERIOD] = _('Time period');
	$str_type[CONDITION_TYPE_MAINTENANCE] = _('Maintenance status');
	$str_type[CONDITION_TYPE_NODE] = _('Node');
	$str_type[CONDITION_TYPE_DRULE] = _('Discovery rule');
	$str_type[CONDITION_TYPE_DCHECK] = _('Discovery check');
	$str_type[CONDITION_TYPE_DOBJECT] = _('Discovery object');
	$str_type[CONDITION_TYPE_DHOST_IP] = _('Host IP');
	$str_type[CONDITION_TYPE_DSERVICE_TYPE] = _('Service type');
	$str_type[CONDITION_TYPE_DSERVICE_PORT] = _('Service port');
	$str_type[CONDITION_TYPE_DSTATUS] = _('Discovery status');
	$str_type[CONDITION_TYPE_DUPTIME] = _('Uptime/Downtime');
	$str_type[CONDITION_TYPE_DVALUE] = _('Received value');
	$str_type[CONDITION_TYPE_EVENT_ACKNOWLEDGED] = _('Event acknowledged');
	$str_type[CONDITION_TYPE_APPLICATION] = _('Application');
	$str_type[CONDITION_TYPE_PROXY] = _('Proxy');
	$str_type[CONDITION_TYPE_HOST_NAME] = _('Host name');

	if (isset($str_type[$conditiontype])) {
		return $str_type[$conditiontype];
	}

	return _('Unknown');
}

function discovery_object2str($object) {
	$str_object[EVENT_OBJECT_DHOST] = _('Device');
	$str_object[EVENT_OBJECT_DSERVICE] = _('Service');

	if (isset($str_object[$object])) {
		return $str_object[$object];
	}

	return _('Unknown');
}

function condition_value2str($conditiontype, $value) {
	switch ($conditiontype) {
		case CONDITION_TYPE_HOST_GROUP:
			$groups = API::HostGroup()->get(array(
				'groupids' => $value,
				'output' => API_OUTPUT_EXTEND,
				'nodeids' => get_current_nodeid(true),
				'limit' => 1
			));

			if (!$group = reset($groups)) {
				error(_s('No host groups with groupid "%s".', $value));
			}

			$str_val = '';
			if (id2nodeid($value) != get_current_nodeid()) {
				$str_val = get_node_name_by_elid($value, true, ': ');
			}

			$str_val .= $group['name'];
			break;
		case CONDITION_TYPE_TRIGGER:
			$trigs = API::Trigger()->get(array(
				'triggerids' => $value,
				'expandDescription' => true,
				'output' => API_OUTPUT_EXTEND,
				'selectHosts' => array('name'),
				'nodeids' => get_current_nodeid(true),
				'limit' => 1
			));
			$trig = reset($trigs);
			$host = reset($trig['hosts']);
			$str_val = '';
			if (id2nodeid($value) != get_current_nodeid()) {
				$str_val = get_node_name_by_elid($value, true, ': ');
			}

			$str_val .= $host['name'].': '.$trig['description'];
			break;
		case CONDITION_TYPE_HOST:
		case CONDITION_TYPE_HOST_TEMPLATE:
			$host = get_host_by_hostid($value);
			$str_val = '';
			if (id2nodeid($value) != get_current_nodeid()) {
				$str_val = get_node_name_by_elid($value, true, ': ');
			}
			$str_val.= $host['name'];
			break;
		case CONDITION_TYPE_TRIGGER_NAME:
		case CONDITION_TYPE_HOST_NAME:
			$str_val = $value;
			break;
		case CONDITION_TYPE_TRIGGER_VALUE:
			$str_val = trigger_value2str($value);
			break;
		case CONDITION_TYPE_TRIGGER_SEVERITY:
			$str_val = getSeverityCaption($value);
			break;
		case CONDITION_TYPE_TIME_PERIOD:
			$str_val = $value;
			break;
		case CONDITION_TYPE_MAINTENANCE:
			$str_val = _('maintenance');
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
			$row = DBfetch(DBselect(
				'SELECT DISTINCT dr.name,c.dcheckid,c.type,c.key_,c.ports'.
				' FROM drules dr,dchecks c '.
				' WHERE dr.druleid=c.druleid '.
					' AND c.dcheckid='.$value
			));
			$str_val = $row['name'].': '.discovery_check2str($row['type'], $row['key_'], $row['ports']);
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
			$str_val = ($value) ? _('Ack') : _('Not Ack');
			break;
		case CONDITION_TYPE_APPLICATION:
			$str_val = $value;
			break;
		default:
			return _('Unknown');
			break;
	}

	return '"'.$str_val.'"';
}

function get_condition_desc($conditiontype, $operator, $value) {
	return condition_type2str($conditiontype).' '.condition_operator2str($operator).' '.condition_value2str($conditiontype, $value);
}

function get_operation_desc($type, $data) {
	$result = array();

	if ($type == SHORT_DESCRIPTION) {
		switch ($data['operationtype']) {
			case OPERATION_TYPE_MESSAGE:
				if (!isset($data['opmessage_usr'])) {
					$data['opmessage_usr'] = array();
				}
				if (!isset($data['opmessage_grp'])) {
					$data['opmessage_grp'] = array();
				}

				$users = API::User()->get(array(
					'userids' => zbx_objectValues($data['opmessage_usr'], 'userid'),
					'output' => array('userid', 'alias')
				));
				if (!empty($users)) {
					order_result($users, 'alias');

					$result[] = bold(array(_('Send message to users').':'.SPACE));
					$result[] = array(implode(', ', zbx_objectValues($users, 'alias')), BR());
				}

				$usrgrps = API::UserGroup()->get(array(
					'usrgrpids' => zbx_objectValues($data['opmessage_grp'], 'usrgrpid'),
					'output' => API_OUTPUT_EXTEND
				));
				if (!empty($usrgrps)) {
					order_result($usrgrps, 'name');

					$result[] = bold(array(_('Send message to user groups').':'.SPACE));
					$result[] = array(implode(', ', zbx_objectValues($usrgrps, 'name')), BR());
				}
				break;
			case OPERATION_TYPE_COMMAND:
				if (!isset($data['opcommand_grp'])) {
					$data['opcommand_grp'] = array();
				}
				if (!isset($data['opcommand_hst'])) {
					$data['opcommand_hst'] = array();
				}

				$hosts = API::Host()->get(array(
					'hostids' => zbx_objectValues($data['opcommand_hst'], 'hostid'),
					'output' => array('hostid', 'name')
				));

				foreach ($data['opcommand_hst'] as $cmd) {
					if ($cmd['hostid'] != 0) {
						continue;
					}

					$result[] = array(bold(_('Run remote commands on current host')), BR());
					break;
				}

				if (!empty($hosts)) {
					order_result($hosts, 'name');

					$result[] = bold(_('Run remote commands on hosts').':'.SPACE);
					$result[] = array(implode(', ', zbx_objectValues($hosts, 'name')), BR());
				}

				$groups = API::HostGroup()->get(array(
					'groupids' => zbx_objectValues($data['opcommand_grp'], 'groupid'),
					'output' => array('groupid', 'name')
				));

				if (!empty($groups)) {
					order_result($groups, 'name');

					$result[] = bold(_('Run remote commands on host groups').':'.SPACE);
					$result[] = array(implode(', ', zbx_objectValues($groups, 'name')), BR());
				}
				break;
			case OPERATION_TYPE_HOST_ADD:
				$result[] = array(bold(_('Add host')), BR());
				break;
			case OPERATION_TYPE_HOST_REMOVE:
				$result[] = array(bold(_('Remove host')), BR());
				break;
			case OPERATION_TYPE_HOST_ENABLE:
				$result[] = array(bold(_('Enable host')), BR());
				break;
			case OPERATION_TYPE_HOST_DISABLE:
				$result[] = array(bold(_('Disable host')), BR());
				break;
			case OPERATION_TYPE_GROUP_ADD:
			case OPERATION_TYPE_GROUP_REMOVE:
				if (!isset($data['opgroup'])) {
					$data['opgroup'] = array();
				}

				$groups = API::HostGroup()->get(array(
					'groupids' => zbx_objectValues($data['opgroup'], 'groupid'),
					'output' => array('groupid', 'name')
				));

				if (!empty($groups)) {
					order_result($groups, 'name');

					if (OPERATION_TYPE_GROUP_ADD == $data['operationtype']) {
						$result[] = bold(_('Add to host groups').':'.SPACE);
					}
					else {
						$result[] = bold(_('Remove from host groups').':'.SPACE);
					}

					$result[] = array(implode(', ', zbx_objectValues($groups, 'name')), BR());
				}
				break;
			case OPERATION_TYPE_TEMPLATE_ADD:
			case OPERATION_TYPE_TEMPLATE_REMOVE:
				if (!isset($data['optemplate'])) {
					$data['optemplate'] = array();
				}

				$templates = API::Template()->get(array(
					'templateids' => zbx_objectValues($data['optemplate'], 'templateid'),
					'output' => array('hostid', 'name')
				));

				if (!empty($templates)) {
					order_result($templates, 'name');

					if (OPERATION_TYPE_TEMPLATE_ADD == $data['operationtype']) {
						$result[] = bold(_('Link to templates').':'.SPACE);
					}
					else {
						$result[] = bold(_('Unlink from templates').':'.SPACE);
					}

					$result[] = array(implode(', ', zbx_objectValues($templates, 'name')), BR());
				}
				break;
			default:
		}
	}
	else {
		switch ($data['operationtype']) {
			case OPERATION_TYPE_MESSAGE:
				if (isset($data['opmessage']['default_msg']) && !empty($data['opmessage']['default_msg'])) {
					if (isset($_REQUEST['def_shortdata']) && isset($_REQUEST['def_longdata'])) {
						$result[] = array(bold(_('Subject').': '), BR(), zbx_nl2br($_REQUEST['def_shortdata']));
						$result[] = array(bold(_('Message').':'), BR(), zbx_nl2br($_REQUEST['def_longdata']));
					}
					elseif (isset($data['opmessage']['operationid'])) {
						$sql = 'SELECT a.def_shortdata,a.def_longdata '.
								' FROM actions a,operations o '.
								' WHERE a.actionid=o.actionid '.
									' AND o.operationid='.$data['operationid'];
						if ($rows = DBfetch(DBselect($sql, 1))) {
							$result[] = array(bold(_('Subject').': '), BR(), zbx_nl2br($rows['def_shortdata']));
							$result[] = array(bold(_('Message').': '), BR(), zbx_nl2br($rows['def_longdata']));
						}
					}
				}
				else {
					$result[] = array(bold(_('Subject').': '), BR(), zbx_nl2br($data['opmessage']['subject']));
					$result[] = array(bold(_('Message').': '), BR(), zbx_nl2br($data['opmessage']['message']));
				}

				break;
			case OPERATION_TYPE_COMMAND:
				switch ($data['opcommand']['type']) {
					case ZBX_SCRIPT_TYPE_IPMI:
						$result[] = array(bold(_('Run IPMI command').':'), BR(), italic(zbx_nl2br($data['opcommand']['command'])));
						break;
					case ZBX_SCRIPT_TYPE_SSH:
						$result[] = array(bold(_('Run SSH commands').':'), BR(), italic(zbx_nl2br($data['opcommand']['command'])));
						break;
					case ZBX_SCRIPT_TYPE_TELNET:
						$result[] = array(bold(_('Run TELNET commands').':'), BR(), italic(zbx_nl2br($data['opcommand']['command'])));
						break;
					case ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT:
						if ($data['opcommand']['execute_on'] == ZBX_SCRIPT_EXECUTE_ON_AGENT) {
							$result[] = array(bold(_('Run custom commands on Zabbix agent').':'), BR(), italic(zbx_nl2br($data['opcommand']['command'])));
						}
						else {
							$result[] = array(bold(_('Run custom commands on Zabbix server').':'), BR(), italic(zbx_nl2br($data['opcommand']['command'])));
						}
						break;
					case ZBX_SCRIPT_TYPE_GLOBAL_SCRIPT:
						$userScripts = API::Script()->get(array(
							'scriptids' => $data['opcommand']['scriptid'],
							'output' => API_OUTPUT_EXTEND
						));
						$userScript = reset($userScripts);

						$result[] = array(bold(_('Run global script').':'.SPACE), italic($userScript['name']));
						break;
					default:
						$result[] = array(bold(_('Run commands').':'), BR(), italic(zbx_nl2br($data['opcommand']['command'])));
				}
				break;
			default:
		}
	}

	return $result;
}

function get_conditions_by_eventsource($eventsource) {
	$conditions[EVENT_SOURCE_TRIGGERS] = array(
		CONDITION_TYPE_APPLICATION,
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

	if (ZBX_DISTRIBUTED) {
		array_push($conditions[EVENT_SOURCE_TRIGGERS], CONDITION_TYPE_NODE);
	}

	if (isset($conditions[$eventsource])) {
		return $conditions[$eventsource];
	}

	return $conditions[EVENT_SOURCE_TRIGGERS];
}

function get_opconditions_by_eventsource($eventsource) {
	$conditions = array(
		EVENT_SOURCE_TRIGGERS => array(CONDITION_TYPE_EVENT_ACKNOWLEDGED),
		EVENT_SOURCE_DISCOVERY => array(),
	);

	if (isset($conditions[$eventsource])) {
		return $conditions[$eventsource];
	}
}

function get_operations_by_eventsource($eventsource) {
	$operations[EVENT_SOURCE_TRIGGERS] = array(
		OPERATION_TYPE_MESSAGE,
		OPERATION_TYPE_COMMAND
	);
	$operations[EVENT_SOURCE_DISCOVERY] = array(
		OPERATION_TYPE_MESSAGE,
		OPERATION_TYPE_COMMAND,
		OPERATION_TYPE_HOST_ADD,
		OPERATION_TYPE_HOST_REMOVE,
		OPERATION_TYPE_GROUP_ADD,
		OPERATION_TYPE_GROUP_REMOVE,
		OPERATION_TYPE_TEMPLATE_ADD,
		OPERATION_TYPE_TEMPLATE_REMOVE,
		OPERATION_TYPE_HOST_ENABLE,
		OPERATION_TYPE_HOST_DISABLE
	);
	$operations[EVENT_SOURCE_AUTO_REGISTRATION] = array(
		OPERATION_TYPE_MESSAGE,
		OPERATION_TYPE_COMMAND,
		OPERATION_TYPE_HOST_ADD,
		OPERATION_TYPE_GROUP_ADD,
		OPERATION_TYPE_TEMPLATE_ADD,
		OPERATION_TYPE_HOST_DISABLE
	);

	if (isset($operations[$eventsource])) {
		return $operations[$eventsource];
	}

	return $operations[EVENT_SOURCE_TRIGGERS];
}

function operation_type2str($type = null) {
	$types = array(
		OPERATION_TYPE_MESSAGE => _('Send message'),
		OPERATION_TYPE_COMMAND => _('Remote command'),
		OPERATION_TYPE_HOST_ADD => _('Add host'),
		OPERATION_TYPE_HOST_REMOVE => _('Remove host'),
		OPERATION_TYPE_HOST_ENABLE => _('Enable host'),
		OPERATION_TYPE_HOST_DISABLE => _('Disable host'),
		OPERATION_TYPE_GROUP_ADD => _('Add to host group'),
		OPERATION_TYPE_GROUP_REMOVE => _('Remove from host group'),
		OPERATION_TYPE_TEMPLATE_ADD => _('Link to template'),
		OPERATION_TYPE_TEMPLATE_REMOVE => _('Unlink from template')
	);

	if (is_null($type)) {
		return order_result($types);
	}
	elseif (isset($types[$type])) {
		return $types[$type];
	}
	else {
		return _('Unknown');
	}
}

function sortOperations($eventsource, &$operations) {
	if ($eventsource == EVENT_SOURCE_TRIGGERS) {
		$esc_step_from = array();
		$esc_step_to = array();
		$esc_period = array();
		$operationTypes = array();

		foreach ($operations as $key => $operation) {
			$esc_step_from[$key] = $operation['esc_step_from'];
			$esc_step_to[$key] = $operation['esc_step_to'];
			$esc_period[$key] = $operation['esc_period'];
			$operationTypes[$key] = $operation['operationtype'];
		}
		array_multisort($esc_step_from, SORT_ASC, $esc_step_to, SORT_ASC, $esc_period, SORT_ASC, $operationTypes, SORT_ASC, $operations);
	}
	else {
		CArrayHelper::sort($operations, array('operationtype'));
	}
}

function get_operators_by_conditiontype($conditiontype) {
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

	if (isset($operators[$conditiontype])) {
		return $operators[$conditiontype];
	}

	return array();
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
		$delays[$i+1] = $delays[$i] + $esc_period;
	}

	return $delays;
}

function get_action_msgs_for_event($event) {
	$table = new CTableInfo(_('No actions found.'));
	$table->setHeader(array(
		is_show_all_nodes() ? _('Nodes') : null,
		_('Time'),
		_('Type'),
		_('Status'),
		_('Retries left'),
		_('Recipient(s)'),
		_('Message'),
		_('Error')
	));

	$alerts = $event['alerts'];
	foreach ($alerts as $alertid => $alert) {
		if ($alert['alerttype'] != ALERT_TYPE_MESSAGE) {
			continue;
		}

		$mediatype = array_pop($alert['mediatypes']);

		$time = zbx_date2str(EVENT_ACTION_MESSAGES_DATE_FORMAT, $alert['clock']);
		if ($alert['esc_step'] > 0) {
			$time = array(
				bold(_('Step').': '),
				$alert["esc_step"],
				br(),
				bold(_('Time').': '),
				br(),
				$time
			);
		}

		if ($alert['status'] == ALERT_STATUS_SENT) {
			$status = new CSpan(_('sent'), 'green');
			$retries = new CSpan(SPACE, 'green');
		}
		elseif ($alert['status'] == ALERT_STATUS_NOT_SENT) {
			$status = new CSpan(_('In progress'), 'orange');
			$retries = new CSpan(ALERT_MAX_RETRIES - $alert['retries'], 'orange');
		}
		else {
			$status = new CSpan(_('not sent'), 'red');
			$retries = new CSpan(0, 'red');
		}
		$sendto = $alert['sendto'];

		$message = array(
			bold(_('Subject').':'),
			br(),
			$alert['subject'],
			br(),
			br(),
			bold(_('Message').':')
		);
		array_push($message, BR(), zbx_nl2br($alert['message']));

		if (empty($alert['error'])) {
			$error = new CSpan(SPACE, 'off');
		}
		else {
			$error = new CSpan($alert['error'], 'on');
		}

		$table->addRow(array(
			get_node_name_by_elid($alert['alertid']),
			new CCol($time, 'top'),
			new CCol((!empty($mediatype['description']) ? $mediatype['description'] : ''), 'top'),
			new CCol($status, 'top'),
			new CCol($retries, 'top'),
			new CCol($sendto, 'top'),
			new CCol($message, 'wraptext top'),
			new CCol($error, 'wraptext top')
		));
	}

	return $table;
}

function get_action_cmds_for_event($event) {
	$table = new CTableInfo(_('No actions found.'));
	$table->setHeader(array(
		is_show_all_nodes() ? _('Nodes') : null,
		_('Time'),
		_('Status'),
		_('Command'),
		_('Error')
	));

	$alerts = $event['alerts'];
	foreach ($alerts as $alert) {
		if ($alert['alerttype'] != ALERT_TYPE_COMMAND) {
			continue;
		}

		$time = zbx_date2str(EVENT_ACTION_CMDS_DATE_FORMAT, $alert['clock']);
		if ($alert['esc_step'] > 0) {
			$time = array(
				bold(_('Step').': '),
				$alert['esc_step'],
				br(),
				bold(_('Time').': '),
				br(),
				$time
			);
		}

		switch ($alert['status']) {
			case ALERT_STATUS_SENT:
				$status = new CSpan(_('executed'), 'green');
				break;
			case ALERT_STATUS_NOT_SENT:
				$status = new CSpan(_('In progress'), 'orange');
				break;
			default:
				$status = new CSpan(_('not sent'), 'red');
				break;
		}

		$message = array(bold(_('Command').':'));
		array_push($message, BR(), zbx_nl2br($alert['message']));

		$error = empty($alert['error']) ? new CSpan(SPACE, 'off') : new CSpan($alert['error'], 'on');

		$table->addRow(array(
			get_node_name_by_elid($alert['alertid']),
			new CCol($time, 'top'),
			new CCol($status, 'top'),
			new CCol($message, 'wraptext top'),
			new CCol($error, 'wraptext top')
		));
	}

	return $table;
}

function get_actions_hint_by_eventid($eventid, $status = null) {
	$hostids = array();
	$sql = 'SELECT DISTINCT i.hostid'.
			' FROM events e,functions f,items i'.
			' WHERE e.eventid='.$eventid.
				' AND e.object='.EVENT_SOURCE_TRIGGERS.
				' AND f.triggerid=e.objectid '.
				' AND i.itemid=f.itemid';
	if ($host = DBfetch(DBselect($sql, 1))) {
		$hostids[$host['hostid']] = $host['hostid'];
	}
	$available_triggers = get_accessible_triggers(PERM_READ_ONLY, $hostids);

	$tab_hint = new CTableInfo(_('No actions found.'));
	$tab_hint->setAttribute('style', 'width: 300px;');
	$tab_hint->setHeader(array(
		is_show_all_nodes() ? _('Nodes') : null,
		_('User'),
		_('Details'),
		_('Status')
	));

	$sql = 'SELECT DISTINCT a.alertid,mt.description,u.alias,a.subject,a.message,a.sendto,a.status,a.retries,a.alerttype'.
			' FROM events e,alerts a'.
				' LEFT JOIN users u ON u.userid=a.userid'.
				' LEFT JOIN media_type mt ON mt.mediatypeid=a.mediatypeid'.
			' WHERE a.eventid='.$eventid.
				(is_null($status)?'':' AND a.status='.$status).
				' AND e.eventid=a.eventid'.
				' AND a.alerttype IN ('.ALERT_TYPE_MESSAGE.','.ALERT_TYPE_COMMAND.')'.
				' AND '.DBcondition('e.objectid',$available_triggers).
				' AND '.DBin_node('a.alertid').
			' ORDER BY a.alertid';
	$result = DBselect($sql, 30);

	while ($row = DBfetch($result)) {
		if ($row['status'] == ALERT_STATUS_SENT) {
			$status = new CSpan(_('Sent'), 'green');
		}
		elseif ($row['status'] == ALERT_STATUS_NOT_SENT) {
			$status = new CSpan(_('In progress'), 'orange');
		}
		else {
			$status = new CSpan(_('not sent'), 'red');
		}

		switch ($row['alerttype']) {
			case ALERT_TYPE_MESSAGE:
				$message = empty($row['description']) ? '-' : $row['description'];
				break;
			case ALERT_TYPE_COMMAND:
				$message = array(bold(_('Command').':'));
				$msg = explode("\n", $row['message']);
				foreach ($msg as $m) {
					array_push($message, BR(), $m);
				}
				break;
			default:
				$message = '-';
		}

		$tab_hint->addRow(array(
			get_node_name_by_elid($row['alertid']),
			empty($row['alias']) ? ' - ' : $row['alias'],
			$message,
			$status
		));
	}
	return $tab_hint;
}

function get_event_actions_status($eventid) {
	$actionTable = new CTable(' - ');

	$alerts = DBfetch(DBselect(
		'SELECT COUNT(a.alertid) AS cnt_all'.
		' FROM alerts a'.
		' WHERE a.eventid='.$eventid.
			' AND a.alerttype IN ('.ALERT_TYPE_MESSAGE.','.ALERT_TYPE_COMMAND.')'
	));

	if (isset($alerts['cnt_all']) && $alerts['cnt_all'] > 0) {
		$mixed = 0;

		// sent
		$tmp = DBfetch(DBselect(
			'SELECT COUNT(a.alertid) AS sent'.
			' FROM alerts a'.
			' WHERE a.eventid='.$eventid.
				' AND a.alerttype IN ('.ALERT_TYPE_MESSAGE.','.ALERT_TYPE_COMMAND.')'.
				' AND a.status='.ALERT_STATUS_SENT
		));
		$alerts['sent'] = $tmp['sent'];
		$mixed += $alerts['sent'] ? ALERT_STATUS_SENT : 0;

		// in progress
		$tmp = DBfetch(DBselect(
			'SELECT COUNT(a.alertid) AS inprogress'.
			' FROM alerts a'.
			' WHERE a.eventid='.$eventid.
				' AND a.alerttype IN ('.ALERT_TYPE_MESSAGE.','.ALERT_TYPE_COMMAND.')'.
				' AND a.status='.ALERT_STATUS_NOT_SENT
		));
		$alerts['inprogress'] = $tmp['inprogress'];

		// failed
		$tmp = DBfetch(DBselect(
			'SELECT COUNT(a.alertid) AS failed'.
			' FROM alerts a'.
			' WHERE a.eventid='.$eventid.
				' AND a.alerttype IN ('.ALERT_TYPE_MESSAGE.','.ALERT_TYPE_COMMAND.')'.
				' AND a.status='.ALERT_STATUS_FAILED
		));
		$alerts['failed'] = $tmp['failed'];
		$mixed += $alerts['failed'] ? ALERT_STATUS_FAILED : 0;

		if ($alerts['inprogress']) {
			$status = new CSpan(_('In progress'), 'orange');
		}
		elseif ($mixed == ALERT_STATUS_SENT) {
			$status = new CSpan(_('Ok'), 'green');
		}
		elseif ($mixed == ALERT_STATUS_FAILED) {
			$status = new CSpan(_('Failed'), 'red');
		}
		else {
			$tdl = new CCol($alerts['sent'] ? new CSpan($alerts['sent'], 'green') : SPACE);
			$tdl->setAttribute('width', '10');

			$tdr = new CCol($alerts['failed'] ? new CSpan($alerts['failed'], 'red') : SPACE);
			$tdr->setAttribute('width', '10');

			$status = new CRow(array($tdl, $tdr));
		}
		$actionTable->addRow($status);
	}
	return $actionTable;
}

function get_event_actions_stat_hints($eventid) {
	$actionCont = new CDiv(null, 'event-action-cont');

	$alerts = DBfetch(DBselect(
		'SELECT COUNT(a.alertid) AS cnt'.
		' FROM alerts a'.
		' WHERE a.eventid='.$eventid.
			' AND a.alerttype IN ('.ALERT_TYPE_MESSAGE.','.ALERT_TYPE_COMMAND.')'
	));

	if (isset($alerts['cnt']) && $alerts['cnt'] > 0) {
		// left
		$alerts = DBfetch(DBselect(
			'SELECT COUNT(a.alertid) AS sent'.
			' FROM alerts a'.
			' WHERE a.eventid='.$eventid.
				' AND a.alerttype IN ('.ALERT_TYPE_MESSAGE.','.ALERT_TYPE_COMMAND.')'.
				' AND a.status='.ALERT_STATUS_SENT
		));
		$alert_cnt = new CSpan($alerts['sent'], 'green');
		if ($alerts['sent']) {
			$alert_cnt->setHint(get_actions_hint_by_eventid($eventid, ALERT_STATUS_SENT));
		}
		$left = new CDiv($alerts['sent'] ? $alert_cnt : SPACE);

		// center
		$alerts = DBfetch(DBselect(
			'SELECT COUNT(a.alertid) AS inprogress'.
			' FROM alerts a'.
			' WHERE a.eventid='.$eventid.
				' AND a.alerttype IN ('.ALERT_TYPE_MESSAGE.','.ALERT_TYPE_COMMAND.')'.
				' AND a.status='.ALERT_STATUS_NOT_SENT
		));
		$alert_cnt = new CSpan($alerts['inprogress'], 'orange');
		if ($alerts['inprogress']) {
			$alert_cnt->setHint(get_actions_hint_by_eventid($eventid, ALERT_STATUS_NOT_SENT));
		}
		$center = new CDiv($alerts['inprogress'] ? $alert_cnt : SPACE);

		// right
		$alerts = DBfetch(DBselect(
			'SELECT COUNT(a.alertid) AS failed'.
			' FROM alerts a'.
			' WHERE a.eventid='.$eventid.
				' AND a.alerttype IN ('.ALERT_TYPE_MESSAGE.','.ALERT_TYPE_COMMAND.')'.
				' AND a.status='.ALERT_STATUS_FAILED
		));
		$alert_cnt = new CSpan($alerts['failed'], 'red');
		if ($alerts['failed']) {
			$alert_cnt->setHint(get_actions_hint_by_eventid($eventid, ALERT_STATUS_FAILED));
		}
		$right = new CDiv($alerts['failed'] ? $alert_cnt : SPACE);

		$actionCont->addItem(array($left, $center, $right));
	}
	else {
		$actionCont->addItem('-');
	}
	return $actionCont;
}
