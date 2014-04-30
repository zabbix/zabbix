<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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


function condition_operator2str($operator) {
	switch ($operator) {
		case CONDITION_OPERATOR_EQUAL:
			return '=';
		case CONDITION_OPERATOR_NOT_EQUAL:
			return '<>';
		case CONDITION_OPERATOR_LIKE:
			return _('like');
		case CONDITION_OPERATOR_NOT_LIKE:
			return _('not like');
		case CONDITION_OPERATOR_IN:
			return _('in');
		case CONDITION_OPERATOR_MORE_EQUAL:
			return '>=';
		case CONDITION_OPERATOR_LESS_EQUAL:
			return '<=';
		case CONDITION_OPERATOR_NOT_IN:
			return _('not in');
		default:
			return _('Unknown');
	}
}

function condition_type2str($conditionType) {
	switch ($conditionType) {
		case CONDITION_TYPE_TRIGGER_VALUE:
			return _('Trigger value');
		case CONDITION_TYPE_MAINTENANCE:
			return _('Maintenance status');
		case CONDITION_TYPE_TRIGGER_NAME:
			return _('Trigger name');
		case CONDITION_TYPE_TRIGGER_SEVERITY:
			return _('Trigger severity');
		case CONDITION_TYPE_TRIGGER:
			return _('Trigger');
		case CONDITION_TYPE_HOST_NAME:
			return _('Host name');
		case CONDITION_TYPE_HOST_GROUP:
			return _('Host group');
		case CONDITION_TYPE_TEMPLATE:
			return _('Template');
		case CONDITION_TYPE_HOST:
			return _('Host');
		case CONDITION_TYPE_TIME_PERIOD:
			return _('Time period');
		case CONDITION_TYPE_DRULE:
			return _('Discovery rule');
		case CONDITION_TYPE_DCHECK:
			return _('Discovery check');
		case CONDITION_TYPE_DOBJECT:
			return _('Discovery object');
		case CONDITION_TYPE_DHOST_IP:
			return _('Host IP');
		case CONDITION_TYPE_DSERVICE_TYPE:
			return _('Service type');
		case CONDITION_TYPE_DSERVICE_PORT:
			return _('Service port');
		case CONDITION_TYPE_DSTATUS:
			return _('Discovery status');
		case CONDITION_TYPE_DUPTIME:
			return _('Uptime/Downtime');
		case CONDITION_TYPE_DVALUE:
			return _('Received value');
		case CONDITION_TYPE_EVENT_ACKNOWLEDGED:
			return _('Event acknowledged');
		case CONDITION_TYPE_APPLICATION:
			return _('Application');
		case CONDITION_TYPE_PROXY:
			return _('Proxy');
		case CONDITION_TYPE_EVENT_TYPE:
			return _('Event type');
		case CONDITION_TYPE_HOST_METADATA:
			return _('Host metadata');
		default:
			return _('Unknown');
	}
}

function discovery_object2str($object = null) {
	$discoveryObjects = array(
		EVENT_OBJECT_DHOST => _('Device'),
		EVENT_OBJECT_DSERVICE => _('Service')
	);

	if ($object === null) {
		return $discoveryObjects;
	}
	elseif (isset($discoveryObjects[$object])) {
		return $discoveryObjects[$object];
	}
	else {
		return _('Unknown');
	}
}

function condition_value2str($conditiontype, $value) {
	switch ($conditiontype) {
		case CONDITION_TYPE_HOST_GROUP:
			$groups = API::HostGroup()->get(array(
				'groupids' => $value,
				'output' => array('name'),
				'limit' => 1
			));

			if ($groups) {
				$group = reset($groups);

				$str_val = $group['name'];
			}
			else {
				return _('Unknown');
			}
			break;
		case CONDITION_TYPE_TRIGGER:
			$trigs = API::Trigger()->get(array(
				'triggerids' => $value,
				'expandDescription' => true,
				'output' => array('description'),
				'selectHosts' => array('name'),
				'limit' => 1
			));

			if ($trigs) {
				$trig = reset($trigs);
				$host = reset($trig['hosts']);

				$str_val = $host['name'].NAME_DELIMITER.$trig['description'];
			}
			else {
				return _('Unknown');
			}
			break;
		case CONDITION_TYPE_HOST:
		case CONDITION_TYPE_TEMPLATE:
			if ($host = get_host_by_hostid($value)) {
				$str_val = $host['name'];
			}
			else {
				return _('Unknown');
			}
			break;
		case CONDITION_TYPE_TRIGGER_NAME:
		case CONDITION_TYPE_HOST_METADATA:
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
		case CONDITION_TYPE_DRULE:
			if ($drule = get_discovery_rule_by_druleid($value)) {
				$str_val = $drule['name'];
			}
			else {
				return _('Unknown');
			}
			break;
		case CONDITION_TYPE_DCHECK:
			$row = DBfetch(DBselect(
					'SELECT dr.name,c.dcheckid,c.type,c.key_,c.ports'.
					' FROM drules dr,dchecks c'.
					' WHERE dr.druleid=c.druleid'.
						' AND c.dcheckid='.zbx_dbstr($value)
			));
			if ($row) {
				$str_val = $row['name'].NAME_DELIMITER.discovery_check2str($row['type'], $row['key_'], $row['ports']);
			}
			else {
				return _('Unknown');
			}
			break;
		case CONDITION_TYPE_DOBJECT:
			$str_val = discovery_object2str($value);
			break;
		case CONDITION_TYPE_PROXY:
			if ($host = get_host_by_hostid($value)) {
				$str_val = $host['host'];
			}
			else {
				return _('Unknown');
			}
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
		case CONDITION_TYPE_EVENT_TYPE:
			$str_val = eventType($value);
			break;
		default:
			return _('Unknown');
	}

	return $str_val;
}

/**
 * Returns the HTML representation of an action condition.
 *
 * @param $conditiontype
 * @param $operator
 * @param $value
 *
 * @return array
 */
function get_condition_desc($conditiontype, $operator, $value) {
	return array(
		condition_type2str($conditiontype),
		SPACE,
		condition_operator2str($operator),
		SPACE,
		italic(CHtml::encode(condition_value2str($conditiontype, $value)))
	);
}

/**
 * Generates array with HTML items representing operation with description
 *
 * @param int $type short or long description, use const. SHORT_DESCRIPTION and LONG_DESCRIPTION
 * @param array $data
 * @param int $data['operationtype'] type of operation: OPERATION_TYPE_MESSAGE, OPERATION_TYPE_COMMAND, ...
 * @param int $data['opmessage']['mediatypeid'] type id of message media
 * @param bool $data['opmessage']['default_msg'] should default message be used
 * @param bool $data['opmessage']['operationid'] if true $data['operationid'] will be used to retrieve default messages from DB
 * @param string $data['opmessage']['subject'] subject of message
 * @param string $data['opmessage']['message'] message it self
 * @param array $data['opmessage_usr'] list of user ids if OPERATION_TYPE_MESSAGE
 * @param array $data['opmessage_grp'] list of group ids if OPERATION_TYPE_MESSAGE
 * @param array $data['opcommand_grp'] list of group ids if OPERATION_TYPE_COMMAND
 * @param array $data['opcommand_hst'] list of host ids if OPERATION_TYPE_COMMAND
 * @param array $data['opgroup'] list of group ids if OPERATION_TYPE_GROUP_ADD or OPERATION_TYPE_GROUP_REMOVE
 * @param array $data['optemplate'] list of template ids if OPERATION_TYPE_TEMPLATE_ADD or OPERATION_TYPE_TEMPLATE_REMOVE
 * @param int $data['operationid'] id of operation
 * @param int $data['opcommand']['type'] type of command: ZBX_SCRIPT_TYPE_IPMI, ZBX_SCRIPT_TYPE_SSH, ...
 * @param string $data['opcommand']['command'] actual command
 * @param int $data['opcommand']['scriptid'] script id used if $data['opcommand']['type'] is ZBX_SCRIPT_TYPE_GLOBAL_SCRIPT
 *
 * @return array
 */
function get_operation_descr($type, $data) {
	$result = array();

	if ($type == SHORT_DESCRIPTION) {
		switch ($data['operationtype']) {
			case OPERATION_TYPE_MESSAGE:
				$mediaTypes = API::Mediatype()->get(array(
					'mediatypeids' => $data['opmessage']['mediatypeid'],
					'output' => array('description')
				));
				if (empty($mediaTypes)) {
					$mediatype = _('all media');
				}
				else {
					$mediatype = reset($mediaTypes);
					$mediatype = $mediatype['description'];
				}


				if (!empty($data['opmessage_usr'])) {
					$users = API::User()->get(array(
						'userids' => zbx_objectValues($data['opmessage_usr'], 'userid'),
						'output' => array('userid', 'alias', 'name', 'surname')
					));
					order_result($users, 'alias');

					foreach ($users as $user) {
						$fullnames[] = getUserFullname($user);
					}

					$result[] = bold(_('Send message to users').NAME_DELIMITER);
					$result[] = array(implode(', ', $fullnames), SPACE, _('via'), SPACE, $mediatype);
					$result[] = BR();
				}

				if (!empty($data['opmessage_grp'])) {
					$usrgrps = API::UserGroup()->get(array(
						'usrgrpids' => zbx_objectValues($data['opmessage_grp'], 'usrgrpid'),
						'output' => API_OUTPUT_EXTEND
					));
					order_result($usrgrps, 'name');

					$result[] = bold(_('Send message to user groups').NAME_DELIMITER);
					$result[] = array(implode(', ', zbx_objectValues($usrgrps, 'name')), SPACE, _('via'), SPACE, $mediatype);
					$result[] = BR();
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

					$result[] = bold(_('Run remote commands on hosts').NAME_DELIMITER);
					$result[] = array(implode(', ', zbx_objectValues($hosts, 'name')), BR());
				}

				$groups = API::HostGroup()->get(array(
					'groupids' => zbx_objectValues($data['opcommand_grp'], 'groupid'),
					'output' => array('groupid', 'name')
				));

				if (!empty($groups)) {
					order_result($groups, 'name');

					$result[] = bold(_('Run remote commands on host groups').NAME_DELIMITER);
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
						$result[] = bold(_('Add to host groups').NAME_DELIMITER);
					}
					else {
						$result[] = bold(_('Remove from host groups').NAME_DELIMITER);
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
						$result[] = bold(_('Link to templates').NAME_DELIMITER);
					}
					else {
						$result[] = bold(_('Unlink from templates').NAME_DELIMITER);
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
						$result[] = array(bold(_('Subject').NAME_DELIMITER), BR(), zbx_nl2br($_REQUEST['def_shortdata']));
						$result[] = array(bold(_('Message').NAME_DELIMITER), BR(), zbx_nl2br($_REQUEST['def_longdata']));
					}
					elseif (isset($data['opmessage']['operationid'])) {
						$sql = 'SELECT a.def_shortdata,a.def_longdata '.
								' FROM actions a,operations o '.
								' WHERE a.actionid=o.actionid '.
									' AND o.operationid='.zbx_dbstr($data['operationid']);
						if ($rows = DBfetch(DBselect($sql, 1))) {
							$result[] = array(bold(_('Subject').NAME_DELIMITER), BR(), zbx_nl2br($rows['def_shortdata']));
							$result[] = array(bold(_('Message').NAME_DELIMITER), BR(), zbx_nl2br($rows['def_longdata']));
						}
					}
				}
				else {
					$result[] = array(bold(_('Subject').NAME_DELIMITER), BR(), zbx_nl2br($data['opmessage']['subject']));
					$result[] = array(bold(_('Message').NAME_DELIMITER), BR(), zbx_nl2br($data['opmessage']['message']));
				}

				break;
			case OPERATION_TYPE_COMMAND:
				switch ($data['opcommand']['type']) {
					case ZBX_SCRIPT_TYPE_IPMI:
						$result[] = array(bold(_('Run IPMI command').NAME_DELIMITER), BR(), italic(zbx_nl2br($data['opcommand']['command'])));
						break;
					case ZBX_SCRIPT_TYPE_SSH:
						$result[] = array(bold(_('Run SSH commands').NAME_DELIMITER), BR(), italic(zbx_nl2br($data['opcommand']['command'])));
						break;
					case ZBX_SCRIPT_TYPE_TELNET:
						$result[] = array(bold(_('Run TELNET commands').NAME_DELIMITER), BR(), italic(zbx_nl2br($data['opcommand']['command'])));
						break;
					case ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT:
						if ($data['opcommand']['execute_on'] == ZBX_SCRIPT_EXECUTE_ON_AGENT) {
							$result[] = array(bold(_('Run custom commands on Zabbix agent').NAME_DELIMITER), BR(), italic(zbx_nl2br($data['opcommand']['command'])));
						}
						else {
							$result[] = array(bold(_('Run custom commands on Zabbix server').NAME_DELIMITER), BR(), italic(zbx_nl2br($data['opcommand']['command'])));
						}
						break;
					case ZBX_SCRIPT_TYPE_GLOBAL_SCRIPT:
						$userScripts = API::Script()->get(array(
							'scriptids' => $data['opcommand']['scriptid'],
							'output' => API_OUTPUT_EXTEND
						));
						$userScript = reset($userScripts);

						$result[] = array(bold(_('Run global script').NAME_DELIMITER), italic($userScript['name']));
						break;
					default:
						$result[] = array(bold(_('Run commands').NAME_DELIMITER), BR(), italic(zbx_nl2br($data['opcommand']['command'])));
				}
				break;
			default:
		}
	}

	return $result;
}

/**
 * Return an array of action conditions supported by the given event source.
 *
 * @param int $eventsource
 *
 * @return mixed
 */
function get_conditions_by_eventsource($eventsource) {
	$conditions[EVENT_SOURCE_TRIGGERS] = array(
		CONDITION_TYPE_APPLICATION,
		CONDITION_TYPE_HOST_GROUP,
		CONDITION_TYPE_TEMPLATE,
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
		CONDITION_TYPE_PROXY,
		CONDITION_TYPE_HOST_METADATA
	);
	$conditions[EVENT_SOURCE_INTERNAL] = array(
		CONDITION_TYPE_APPLICATION,
		CONDITION_TYPE_EVENT_TYPE,
		CONDITION_TYPE_HOST_GROUP,
		CONDITION_TYPE_TEMPLATE,
		CONDITION_TYPE_HOST
	);

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
	$operations[EVENT_SOURCE_INTERNAL] = array(
		OPERATION_TYPE_MESSAGE
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
	if ($eventsource == EVENT_SOURCE_TRIGGERS || $eventsource == EVENT_SOURCE_INTERNAL) {
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

/**
 * Return an array of operators supported by the given action condition.
 *
 * @param int $conditiontype
 *
 * @return array
 */
function get_operators_by_conditiontype($conditiontype) {
	$operators[CONDITION_TYPE_HOST_GROUP] = array(
		CONDITION_OPERATOR_EQUAL,
		CONDITION_OPERATOR_NOT_EQUAL
	);
	$operators[CONDITION_TYPE_TEMPLATE] = array(
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
	$operators[CONDITION_TYPE_EVENT_TYPE] = array(
		CONDITION_OPERATOR_EQUAL
	);
	$operators[CONDITION_TYPE_HOST_METADATA] = array(
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

/**
 * Get action messages.
 *
 * @param array  $alerts
 * @param string $alerts[n]['alertid']
 * @param string $alerts[n]['userid']
 * @param int    $alerts[n]['alerttype']
 * @param array  $alerts[n]['mediatypes']
 * @param string $alerts[n]['clock']
 * @param int    $alerts[n]['esc_step']
 * @param int    $alerts[n]['status']
 * @param int    $alerts[n]['retries']
 * @param string $alerts[n]['subject']
 * @param string $alerts[n]['sendto']
 * @param string $alerts[n]['message']
 * @param string $alerts[n]['error']
 *
 * @return CTableInfo
 */
function getActionMessages(array $alerts) {
	$dbUsers = API::User()->get(array(
		'output' => array('userid', 'alias', 'name', 'surname'),
		'userids' => zbx_objectValues($alerts, 'userid'),
		'preservekeys' => true
	));

	$table = new CTableInfo(_('No actions found.'));
	$table->setHeader(array(
		_('Time'),
		_('Type'),
		_('Status'),
		_('Retries left'),
		_('Recipient(s)'),
		_('Message'),
		_('Info')
	));

	foreach ($alerts as $alert) {
		if ($alert['alerttype'] != ALERT_TYPE_MESSAGE) {
			continue;
		}

		$mediaType = array_pop($alert['mediatypes']);

		$time = zbx_date2str(DATE_TIME_FORMAT_SECONDS, $alert['clock']);

		if ($alert['esc_step'] > 0) {
			$time = array(
				bold(_('Step').NAME_DELIMITER),
				$alert['esc_step'],
				br(),
				bold(_('Time').NAME_DELIMITER),
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

		$recipient = $alert['userid']
			? array(bold(getUserFullname($dbUsers[$alert['userid']])), BR(), $alert['sendto'])
			: $alert['sendto'];

		$message = array(
			bold(_('Subject').NAME_DELIMITER),
			br(),
			$alert['subject'],
			br(),
			br(),
			bold(_('Message').NAME_DELIMITER)
		);

		array_push($message, BR(), zbx_nl2br($alert['message']));

		if (zbx_empty($alert['error'])) {
			$info = '';
		}
		else {
			$info = new CDiv(SPACE, 'status_icon iconerror');
			$info->setHint($alert['error'], '', 'on');
		}

		$table->addRow(array(
			new CCol($time, 'top'),
			new CCol((isset($mediaType['description']) ? $mediaType['description'] : ''), 'top'),
			new CCol($status, 'top'),
			new CCol($retries, 'top'),
			new CCol($recipient, 'top'),
			new CCol($message, 'wraptext top'),
			new CCol($info, 'wraptext top')
		));
	}

	return $table;
}

/**
 * Get action remote commands.
 *
 * @param array  $alerts
 * @param string $alerts[n]['alertid']
 * @param int    $alerts[n]['alerttype']
 * @param string $alerts[n]['clock']
 * @param int    $alerts[n]['esc_step']
 * @param int    $alerts[n]['status']
 * @param string $alerts[n]['message']
 * @param string $alerts[n]['error']
 *
 * @return CTableInfo
 */
function getActionCommands(array $alerts) {
	$table = new CTableInfo(_('No actions found.'));
	$table->setHeader(array(
		_('Time'),
		_('Status'),
		_('Command'),
		_('Error')
	));

	foreach ($alerts as $alert) {
		if ($alert['alerttype'] != ALERT_TYPE_COMMAND) {
			continue;
		}

		$time = zbx_date2str(DATE_TIME_FORMAT_SECONDS, $alert['clock']);

		if ($alert['esc_step'] > 0) {
			$time = array(
				bold(_('Step').NAME_DELIMITER),
				$alert['esc_step'],
				br(),
				bold(_('Time').NAME_DELIMITER),
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

		$error = $alert['error'] ? new CSpan($alert['error'], 'on') : new CSpan(SPACE, 'off');

		$table->addRow(array(
			new CCol($time, 'top'),
			new CCol($status, 'top'),
			new CCol(array(bold(_('Command').NAME_DELIMITER), BR(), zbx_nl2br($alert['message'])), 'wraptext top'),
			new CCol($error, 'wraptext top')
		));
	}

	return $table;
}

function get_actions_hint_by_eventid($eventid, $status = null) {
	$tab_hint = new CTableInfo(_('No actions found.'));
	$tab_hint->setAttribute('style', 'width: 300px;');
	$tab_hint->setHeader(array(
		_('User'),
		_('Details'),
		_('Status')
	));

	$sql = 'SELECT a.alertid,mt.description,u.alias,u.name,u.surname,a.subject,a.message,a.sendto,a.status,a.retries,a.alerttype'.
			' FROM events e,alerts a'.
				' LEFT JOIN users u ON u.userid=a.userid'.
				' LEFT JOIN media_type mt ON mt.mediatypeid=a.mediatypeid'.
			' WHERE a.eventid='.zbx_dbstr($eventid).
				(is_null($status)?'':' AND a.status='.$status).
				' AND e.eventid=a.eventid'.
				' AND a.alerttype IN ('.ALERT_TYPE_MESSAGE.','.ALERT_TYPE_COMMAND.')'.
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
				$message = array(bold(_('Command').NAME_DELIMITER));
				$msg = explode("\n", $row['message']);
				foreach ($msg as $m) {
					array_push($message, BR(), $m);
				}
				break;
			default:
				$message = '-';
		}

		if (!$row['alias']) {
			$row['alias'] = ' - ';
		}
		else {
			$fullname = '';
			if ($row['name']) {
				$fullname = $row['name'];
			}
			if ($row['surname']) {
				$fullname .= $fullname ? ' '.$row['surname'] : $row['surname'];
			}
			if ($fullname) {
				$row['alias'] .= ' ('.$fullname.')';
			}
		}

		$tab_hint->addRow(array(
			$row['alias'],
			$message,
			$status
		));
	}

	return $tab_hint;
}

function getEventActionsStatus($eventIds) {
	if (empty($eventIds)) {
		return array();
	}

	$actions = array();

	$alerts = DBselect(
		'SELECT a.eventid,a.status,COUNT(a.alertid) AS cnt'.
		' FROM alerts a'.
		' WHERE a.alerttype IN ('.ALERT_TYPE_MESSAGE.','.ALERT_TYPE_COMMAND.')'.
			' AND '.dbConditionInt('a.eventid', $eventIds).
		' GROUP BY eventid,status'
	);

	while ($alert = DBfetch($alerts)) {
		$actions[$alert['eventid']][$alert['status']] = $alert['cnt'];
	}

	foreach ($actions as $eventId => $action) {
		$sendCount = isset($action[ALERT_STATUS_SENT]) ? $action[ALERT_STATUS_SENT] : 0;
		$notSendCount = isset($action[ALERT_STATUS_NOT_SENT]) ? $action[ALERT_STATUS_NOT_SENT] : 0;
		$failedCount = isset($action[ALERT_STATUS_FAILED]) ? $action[ALERT_STATUS_FAILED] : 0;

		// calculate total
		$mixed = 0;
		if ($sendCount > 0) {
			$mixed += ALERT_STATUS_SENT;
		}
		if ($failedCount > 0) {
			$mixed += ALERT_STATUS_FAILED;
		}

		// display
		if ($notSendCount > 0) {
			$status = new CSpan(_('In progress'), 'orange');
		}
		elseif ($mixed == ALERT_STATUS_SENT) {
			$status = new CSpan(_('Ok'), 'green');
		}
		elseif ($mixed == ALERT_STATUS_FAILED) {
			$status = new CSpan(_('Failed'), 'red');
		}
		else {
			$columnLeft = new CCol(($sendCount > 0) ? new CSpan($sendCount, 'green') : SPACE);
			$columnLeft->setAttribute('width', '10');

			$columnRight = new CCol(($failedCount > 0) ? new CSpan($failedCount, 'red') : SPACE);
			$columnRight->setAttribute('width', '10');

			$status = new CRow(array($columnLeft, $columnRight));
		}

		$actions[$eventId] = new CTable(' - ');
		$actions[$eventId]->addRow($status);
	}

	return $actions;
}

function getEventActionsStatHints($eventIds) {
	if (empty($eventIds)) {
		return array();
	}

	$actions = array();

	$alerts = DBselect(
		'SELECT a.eventid,a.status,COUNT(a.alertid) AS cnt'.
		' FROM alerts a'.
		' WHERE a.alerttype IN ('.ALERT_TYPE_MESSAGE.','.ALERT_TYPE_COMMAND.')'.
			' AND '.dbConditionInt('a.eventid', $eventIds).
		' GROUP BY eventid,status'
	);

	while ($alert = DBfetch($alerts)) {
		if ($alert['cnt'] > 0) {
			if ($alert['status'] == ALERT_STATUS_SENT) {
				$color = 'green';
			}
			elseif ($alert['status'] == ALERT_STATUS_NOT_SENT) {
				$color = 'orange';
			}
			else {
				$color = 'red';
			}

			$hint = new CSpan($alert['cnt'], $color);
			$hint->setHint(get_actions_hint_by_eventid($alert['eventid'], $alert['status']));

			$actions[$alert['eventid']][$alert['status']] = $hint;
		}
	}

	foreach ($actions as $eventId => $action) {
		$actions[$eventId] = new CDiv(null, 'event-action-cont');
		$actions[$eventId]->addItem(array(
			new CDiv(isset($action[ALERT_STATUS_SENT]) ? $action[ALERT_STATUS_SENT] : SPACE),
			new CDiv(isset($action[ALERT_STATUS_NOT_SENT]) ? $action[ALERT_STATUS_NOT_SENT] : SPACE),
			new CDiv(isset($action[ALERT_STATUS_FAILED]) ? $action[ALERT_STATUS_FAILED] : SPACE)
		));
	}

	return $actions;
}

/**
 * Returns the names of the "Event type" action condition values.
 *
 * If the $type parameter is passed, returns the name of the specific value, otherwise - returns an array of all
 * supported values.
 *
 * @param string $type
 *
 * @return array|string
 */
function eventType($type = null) {
	$types = array(
		EVENT_TYPE_ITEM_NOTSUPPORTED => _('Item in "not supported" state'),
		EVENT_TYPE_ITEM_NORMAL => _('Item in "normal" state'),
		EVENT_TYPE_LLDRULE_NOTSUPPORTED => _('Low-level discovery rule in "not supported" state'),
		EVENT_TYPE_LLDRULE_NORMAL => _('Low-level discovery rule in "normal" state'),
		EVENT_TYPE_TRIGGER_UNKNOWN => _('Trigger in "unknown" state'),
		EVENT_TYPE_TRIGGER_NORMAL => _('Trigger in "normal" state')
	);

	if (is_null($type)) {
		return $types;
	}
	elseif (isset($types[$type])) {
		return $types[$type];
	}
	else {
		return _('Unknown');
	}
}
