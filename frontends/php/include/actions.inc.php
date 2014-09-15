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

/**
 * Converts numerical action condition values to their corresponding string values according to action condition type.
 *
 * For action condition types such as: hosts, host groups, templates, proxies, triggers, discovery rules
 * and discovery checks, action condition values contain IDs. All unique IDs are first collected and then queried.
 * For other action condition types values are returned as they are or converted using simple string convertion
 * functions according to action condition type.
 *
 * @param array $actions							array of actions
 * @param array $action['filter']					array containing arrays of action conditions and other data
 * @param array $action['filter']['conditions']		array of action conditions
 * @param array $config								array containing configuration parameters for getting trigger
 *													severity captions
 *
 * @return array									returns an array of actions condition string values
 */
function actionConditionValueToString(array $actions, array $config) {
	$result = array();

	if (!$actions) {
		return $result;
	}

	$hostGroupsToSelect = array();
	$triggersToSelect = array();
	$hostsToSelect = array();
	$templatesToSelect = array();
	$proxiesToSelect = array();
	$dRulesToSelect = array();
	$dChecksToSelect = array();

	foreach ($actions as $aIdx => $action) {
		$result[$aIdx] = array();

		foreach ($action['filter']['conditions'] as $cIdx => $condition) {
			// unknown types and all of the default values for other types are 'Unknown'
			$result[$aIdx][$cIdx] = _('Unknown');

			switch ($condition['conditiontype']) {
				// gather hostgroup IDs for later querying
				case CONDITION_TYPE_HOST_GROUP:
					$hostGroupsToSelect[$condition['value']] = $condition['value'];
					break;

				// gather trigger IDs for later querying
				case CONDITION_TYPE_TRIGGER:
					$triggersToSelect[$condition['value']] = $condition['value'];
					break;

				// gether hosts IDs for later querying
				case CONDITION_TYPE_HOST:
					$hostsToSelect[$condition['value']] = $condition['value'];
					break;

				// gether template IDs for later querying
				case CONDITION_TYPE_TEMPLATE:
					$templatesToSelect[$condition['value']] = $condition['value'];
					break;

				// gether proxy IDs for later querying
				case CONDITION_TYPE_PROXY:
					$proxiesToSelect[$condition['value']] = $condition['value'];
					break;

				// return values as is for following condition types
				case CONDITION_TYPE_TRIGGER_NAME:
				case CONDITION_TYPE_HOST_METADATA:
				case CONDITION_TYPE_HOST_NAME:
				case CONDITION_TYPE_TIME_PERIOD:
				case CONDITION_TYPE_DHOST_IP:
				case CONDITION_TYPE_DSERVICE_PORT:
				case CONDITION_TYPE_DUPTIME:
				case CONDITION_TYPE_DVALUE:
				case CONDITION_TYPE_APPLICATION:
					$result[$aIdx][$cIdx] = $condition['value'];
					break;

				case CONDITION_TYPE_EVENT_ACKNOWLEDGED:
					$result[$aIdx][$cIdx] = $condition['value'] ? _('Ack') : _('Not Ack');
					break;

				case CONDITION_TYPE_MAINTENANCE:
					$result[$aIdx][$cIdx] = _('maintenance');
					break;

				case CONDITION_TYPE_TRIGGER_VALUE:
					$result[$aIdx][$cIdx] = trigger_value2str($condition['value']);
					break;

				case CONDITION_TYPE_TRIGGER_SEVERITY:
					$result[$aIdx][$cIdx] = getSeverityCaption($condition['value'], $config);
					break;

				// gather dicovery rule IDs for later querying
				case CONDITION_TYPE_DRULE:
					$dRulesToSelect[$condition['value']] = $condition['value'];
					break;

				// gather dicovery check IDs for later querying
				case CONDITION_TYPE_DCHECK:
					$dChecksToSelect[$condition['value']] = $condition['value'];
					break;

				case CONDITION_TYPE_DOBJECT:
					$result[$aIdx][$cIdx] = discovery_object2str($condition['value']);
					break;

				case CONDITION_TYPE_DSERVICE_TYPE:
					$result[$aIdx][$cIdx] = discovery_check_type2str($condition['value']);
					break;

				case CONDITION_TYPE_DSTATUS:
					$result[$aIdx][$cIdx] = discovery_object_status2str($condition['value']);
					break;

				case CONDITION_TYPE_EVENT_TYPE:
					$result[$aIdx][$cIdx] = eventType($condition['value']);
			}
		}
	}

	$groups = null;
	$triggers = null;
	$hosts = null;
	$templates = null;
	$proxies = null;
	$dRules = null;
	$dChecks = null;

	if ($hostGroupsToSelect) {
		$groups = API::HostGroup()->get(array(
			'output' => array('name'),
			'groupids' => $hostGroupsToSelect,
			'preservekeys' => true
		));
	}

	if ($triggersToSelect) {
		$triggers = API::Trigger()->get(array(
			'output' => array('description'),
			'triggerids' => $triggersToSelect,
			'expandDescription' => true,
			'selectHosts' => array('name'),
			'preservekeys' => true
		));
	}

	if ($hostsToSelect) {
		$hosts = API::Host()->get(array(
			'output' => array('name'),
			'hostids' => $hostsToSelect,
			'preservekeys' => true
		));
	}

	if ($templatesToSelect) {
		$templates = API::Template()->get(array(
			'output' => array('name'),
			'templateids' => $templatesToSelect,
			'preservekeys' => true
		));
	}

	if ($proxiesToSelect) {
		$proxies = API::Proxy()->get(array(
			'output' => array('host'),
			'proxyids' => $proxiesToSelect,
			'preservekeys' => true
		));
	}

	if ($dRulesToSelect) {
		$dRules = API::DRule()->get(array(
			'output' => array('name'),
			'druleids' => $dRulesToSelect,
			'preservekeys' => true
		));
	}

	if ($dChecksToSelect) {
		$dChecks = API::DCheck()->get(array(
			'output' => array('type', 'key_', 'ports'),
			'dcheckids' => $dChecksToSelect,
			'selectDRules' => array('name'),
			'preservekeys' => true
		));
	}

	if ($groups || $triggers || $hosts || $templates || $proxies || $dRules || $dChecks) {
		foreach ($actions as $aIdx => $action) {
			foreach ($action['filter']['conditions'] as $cIdx => $condition) {
				$id = $condition['value'];

				switch ($condition['conditiontype']) {
					case CONDITION_TYPE_HOST_GROUP:
						if (isset($groups[$id])) {
							$result[$aIdx][$cIdx] = $groups[$id]['name'];
						}
						break;

					case CONDITION_TYPE_TRIGGER:
						if (isset($triggers[$id])) {
							$host = reset($triggers[$id]['hosts']);
							$result[$aIdx][$cIdx] = $host['name'].NAME_DELIMITER.$triggers[$id]['description'];
						}
						break;

					case CONDITION_TYPE_HOST:
						if (isset($hosts[$id])) {
							$result[$aIdx][$cIdx] = $hosts[$id]['name'];
						}
						break;

					case CONDITION_TYPE_TEMPLATE:
						if (isset($templates[$id])) {
							$result[$aIdx][$cIdx] = $templates[$id]['name'];
						}
						break;

					case CONDITION_TYPE_PROXY:
						if (isset($proxies[$id])) {
							$result[$aIdx][$cIdx] = $proxies[$id]['host'];
						}
						break;

					case CONDITION_TYPE_DRULE:
						if (isset($dRules[$id])) {
							$result[$aIdx][$cIdx] = $dRules[$id]['name'];
						}
						break;

					case CONDITION_TYPE_DCHECK:
						if (isset($dChecks[$id])) {
							$drule = reset($dChecks[$id]['drules']);
							$type = $dChecks[$id]['type'];
							$key = $dChecks[$id]['key_'];
							$ports = $dChecks[$id]['ports'];

							$dCheck = discovery_check2str($type, $key, $ports);

							$result[$aIdx][$cIdx] = $drule['name'].NAME_DELIMITER.$dCheck;
						}
				}
			}
		}
	}

	return $result;
}

/**
 * Converts numerical action operation condition values to their corresponding string values according to
 * action operation condition type. Since action list does not display operation conditions,
 * so there is only an array of operation conditions for single action which is displayed in operation details.
 *
 * @param array  $conditions					array of actions operation conditions
 * @param string $condition['conditiontype']	operation condition type
 * @param string $condition['value']			operation condition value
 *
 * @return array								returns an array of action operation condition string values
 */
function actionOperationConditionValueToString(array $conditions) {
	$result = array();

	foreach ($conditions as $condition) {
		if ($condition['conditiontype'] == CONDITION_TYPE_EVENT_ACKNOWLEDGED) {
			$result[] = $condition['value'] ? _('Ack') : _('Not Ack');
		}
	}

	return $result;
}

/**
 * Returns the HTML representation of an action condition and action operation condition.
 *
 * @param string $conditionType
 * @param string $operator
 * @param string $value
 *
 * @return array
 */
function getConditionDescription($conditionType, $operator, $value) {
	return array(
		condition_type2str($conditionType),
		SPACE,
		condition_operator2str($operator),
		SPACE,
		italic(CHtml::encode($value))
	);
}

/**
 * Gathers media types, user groups, users, host groups, hosts and templates for actions and their operations, and
 * returns the HTML representation of action operation values according to action operation type.
 *
 * @param array $actions				array of actions
 * @param array $action['operations']	array of action operations
 *
 * @return array						returns an array of actions operation descriptions
 */
function getActionOperationDescriptions(array $actions) {
	$result = array();

	if (!$actions) {
		return $result;
	}

	$mediaTypesToSelect = array();
	$usersToSelect = array();
	$userGroupsToSelect = array();
	$hostsToSelect = array();
	$hostGroupsToSelect = array();
	$templatesToSelect = array();

	foreach ($actions as $aIdx => $action) {
		$result[$aIdx] = array();

		foreach ($action['operations'] as $oIdx => $operation) {
			$result[$aIdx][$oIdx] = array();

			switch ($operation['operationtype']) {
				// gather media type IDs, user IDs and user group IDs for later querying
				case OPERATION_TYPE_MESSAGE:
					$mediaTypeId = $operation['opmessage']['mediatypeid'];

					if ($mediaTypeId != 0) {
						$mediaTypesToSelect[$mediaTypeId] = $mediaTypeId;
					}

					if (isset($operation['opmessage_usr']) && $operation['opmessage_usr']) {
						foreach ($operation['opmessage_usr'] as $users) {
							$usersToSelect[$users['userid']] = $users['userid'];
						}
					}

					if (isset($operation['opmessage_grp']) && $operation['opmessage_grp']) {
						foreach ($operation['opmessage_grp'] as $userGroups) {
							$userGroupsToSelect[$userGroups['usrgrpid']] = $userGroups['usrgrpid'];
						}
					}
					break;

				// gather host IDs and host group IDs for later querying
				case OPERATION_TYPE_COMMAND:
					if (isset($operation['opcommand_hst']) && $operation['opcommand_hst']) {
						foreach ($operation['opcommand_hst'] as $host) {
							if ($host['hostid'] != 0) {
								$hostsToSelect[$host['hostid']] = $host['hostid'];
							}
						}
					}

					if (isset($operation['opcommand_grp']) && $operation['opcommand_grp']) {
						foreach ($operation['opcommand_grp'] as $hostGroup) {
							$hostGroupsToSelect[$hostGroup['groupid']] = $hostGroup['groupid'];
						}
					}
					break;

				// gather host group IDs for later querying
				case OPERATION_TYPE_GROUP_ADD:
				case OPERATION_TYPE_GROUP_REMOVE:
					foreach ($operation['opgroup'] as $hostGroup) {
						$hostGroupsToSelect[$hostGroup['groupid']] = $hostGroup['groupid'];
					}
					break;

				// gather template IDs for later querying
				case OPERATION_TYPE_TEMPLATE_ADD:
				case OPERATION_TYPE_TEMPLATE_REMOVE:
					foreach ($operation['optemplate'] as $template) {
						$templatesToSelect[$template['templateid']] = $template['templateid'];
					}
					break;

				default:
			}
		}
	}

	$mediaTypes = null;
	$users = null;
	$userGroups = null;
	$hosts = null;
	$hostGroups = null;
	$templates = null;

	if ($mediaTypesToSelect) {
		$mediaTypes = API::Mediatype()->get(array(
			'output' => array('description'),
			'mediatypeids' => $mediaTypesToSelect,
			'preservekeys' => true
		));
	}

	if ($usersToSelect) {
		$fullnames = array();

		$users = API::User()->get(array(
			'output' => array('userid', 'alias', 'name', 'surname'),
			'userids' => $usersToSelect
		));

		foreach ($users as $user) {
			$fullnames[$user['userid']] = getUserFullname($user);
		}
	}

	if ($userGroupsToSelect) {
		$userGroups = API::UserGroup()->get(array(
			'output' => array('name'),
			'usrgrpids' => $userGroupsToSelect,
			'preservekeys' => true
		));
	}

	if ($hostsToSelect) {
		$hosts = API::Host()->get(array(
			'output' => array('name'),
			'hostids' => $hostsToSelect,
			'preservekeys' => true
		));
	}

	if ($hostGroupsToSelect) {
		$hostGroups = API::HostGroup()->get(array(
			'output' => array('name'),
			'groupids' => $hostGroupsToSelect,
			'preservekeys' => true
		));
	}

	if ($templatesToSelect) {
		$templates = API::Template()->get(array(
			'output' => array('name'),
			'templateids' => $templatesToSelect,
			'preservekeys' => true
		));
	}

	// format the HTML ouput
	foreach ($actions as $aIdx => $action) {
		foreach ($action['operations'] as $oIdx => $operation) {
			switch ($operation['operationtype']) {
				case OPERATION_TYPE_MESSAGE:
					$mediaType = _('all media');

					$mediaTypeId = $operation['opmessage']['mediatypeid'];

					if ($mediaTypeId != 0 && isset($mediaTypes[$mediaTypeId])) {
						$mediaType = $mediaTypes[$mediaTypeId]['description'];
					}

					if (isset($operation['opmessage_usr']) && $operation['opmessage_usr']) {
						$userNamesList = array();

						foreach ($operation['opmessage_usr'] as $user) {
							if (isset($fullnames[$user['userid']])) {
								$userNamesList[] = $fullnames[$user['userid']];
							}
						}

						order_result($userNamesList);

						$result[$aIdx][$oIdx][] = bold(_('Send message to users').': ');
						$result[$aIdx][$oIdx][] = array(implode(', ', $userNamesList), SPACE, _('via'), SPACE,
							$mediaType
						);
						$result[$aIdx][$oIdx][] = BR();
					}

					if (isset($operation['opmessage_grp']) && $operation['opmessage_grp']) {
						$userGroupsList = array();

						foreach ($operation['opmessage_grp'] as $userGroup) {
							if (isset($userGroups[$userGroup['usrgrpid']])) {
								$userGroupsList[] = $userGroups[$userGroup['usrgrpid']]['name'];
							}
						}

						order_result($userGroupsList);

						$result[$aIdx][$oIdx][] = bold(_('Send message to user groups').': ');
						$result[$aIdx][$oIdx][] = array(implode(', ', $userGroupsList), SPACE, _('via'), SPACE,
							$mediaType
						);
						$result[$aIdx][$oIdx][] = BR();
					}
					break;

				case OPERATION_TYPE_COMMAND:
					if (isset($operation['opcommand_hst']) && $operation['opcommand_hst']) {
						$hostList = array();

						foreach ($operation['opcommand_hst'] as $host) {
							if ($host['hostid'] == 0) {
								$result[$aIdx][$oIdx][] = array(
									bold(_('Run remote commands on current host')),
									BR()
								);
							}
							elseif (isset($hosts[$host['hostid']])) {
								$hostList[] = $hosts[$host['hostid']]['name'];
							}
						}

						if ($hostList) {
							order_result($hostList);

							$result[$aIdx][$oIdx][] = bold(_('Run remote commands on hosts').': ');
							$result[$aIdx][$oIdx][] = array(implode(', ', $hostList), BR());
						}
					}

					if (isset($operation['opcommand_grp']) && $operation['opcommand_grp']) {
						$hostGroupList = array();

						foreach ($operation['opcommand_grp'] as $hostGroup) {
							if (isset($hostGroups[$hostGroup['groupid']])) {
								$hostGroupList[] = $hostGroups[$hostGroup['groupid']]['name'];
							}
						}

						order_result($hostGroupList);

						$result[$aIdx][$oIdx][] = bold(_('Run remote commands on host groups').': ');
						$result[$aIdx][$oIdx][] = array(implode(', ', $hostGroupList), BR());
					}
					break;

				case OPERATION_TYPE_HOST_ADD:
					$result[$aIdx][$oIdx][] = array(bold(_('Add host')), BR());
					break;

				case OPERATION_TYPE_HOST_REMOVE:
					$result[$aIdx][$oIdx][] = array(bold(_('Remove host')), BR());
					break;

				case OPERATION_TYPE_HOST_ENABLE:
					$result[$aIdx][$oIdx][] = array(bold(_('Enable host')), BR());
					break;

				case OPERATION_TYPE_HOST_DISABLE:
					$result[$aIdx][$oIdx][] = array(bold(_('Disable host')), BR());
					break;

				case OPERATION_TYPE_GROUP_ADD:
				case OPERATION_TYPE_GROUP_REMOVE:
					$hostGroupList = array();

					foreach ($operation['opgroup'] as $hostGroup) {
						if (isset($hostGroups[$hostGroup['groupid']])) {
							$hostGroupList[] = $hostGroups[$hostGroup['groupid']]['name'];
						}
					}

					order_result($hostGroupList);

					if ($operation['operationtype'] == OPERATION_TYPE_GROUP_ADD) {
						$result[$aIdx][$oIdx][] = bold(_('Add to host groups').': ');
					}
					else {
						$result[$aIdx][$oIdx][] = bold(_('Remove from host groups').': ');
					}

					$result[$aIdx][$oIdx][] = array(implode(', ', $hostGroupList), BR());
					break;

				case OPERATION_TYPE_TEMPLATE_ADD:
				case OPERATION_TYPE_TEMPLATE_REMOVE:
					$templateList = array();

					foreach ($operation['optemplate'] as $template) {
						if (isset($templates[$template['templateid']])) {
							$templateList[] = $templates[$template['templateid']]['name'];
						}
					}

					order_result($templateList);

					if ($operation['operationtype'] == OPERATION_TYPE_TEMPLATE_ADD) {
						$result[$aIdx][$oIdx][] = bold(_('Link to templates').': ');
					}
					else {
						$result[$aIdx][$oIdx][] = bold(_('Unlink from templates').': ');
					}

					$result[$aIdx][$oIdx][] = array(implode(', ', $templateList), BR());
			}
		}
	}

	return $result;
}

/**
 * Gathers action operation script details and returns the HTML items representing action operation with hint.
 *
 * @param array  $operations						array of action operations
 * @param string $operation['operationtype']		action operation type.
 *													Possible values: OPERATION_TYPE_MESSAGE and OPERATION_TYPE_COMMAND
 * @param string $operation['opcommand']['type']	action operation command type.
 *													Possible values: ZBX_SCRIPT_TYPE_IPMI, ZBX_SCRIPT_TYPE_SSH,
 *													ZBX_SCRIPT_TYPE_TELNET, ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT
 *													and ZBX_SCRIPT_TYPE_GLOBAL_SCRIPT
 *
 * @return array									returns an array of action operation hints
 */
function getActionOperationHints(array $operations) {
	$result = array();

	$scriptsToSelect = array();

	foreach ($operations as $oIdx => $operation) {
		if ($operation['operationtype'] == OPERATION_TYPE_COMMAND
				&& $operation['opcommand']['type'] == ZBX_SCRIPT_TYPE_GLOBAL_SCRIPT) {
			$scriptId = $operation['opcommand']['scriptid'];
			$scriptsToSelect[$scriptId] = $scriptId;
		}
	}

	$scripts = null;
	if ($scriptsToSelect) {
		$scripts = API::Script()->get(array(
			'output' => array('name'),
			'scriptids' => $scriptsToSelect,
			'preservekeys' => true
		));
	}

	foreach ($operations as $oIdx => $operation) {
		$result[$oIdx] = array();

		switch ($operation['operationtype']) {
			case OPERATION_TYPE_MESSAGE:
				if (hasRequest('def_shortdata') && hasRequest('def_longdata')) {
					$result[$oIdx][] = array(bold(_('Subject').': '), BR(), zbx_nl2br(getRequest('def_shortdata')));
					$result[$oIdx][] = array(bold(_('Message').': '), BR(), zbx_nl2br(getRequest('def_longdata')));
				}
				else {
					$result[$oIdx][] = array(bold(_('Subject').': '), BR(),
						zbx_nl2br($operation['opmessage']['subject'])
					);
					$result[$oIdx][] = array(bold(_('Message').': '), BR(),
						zbx_nl2br($operation['opmessage']['message'])
					);
				}
				break;

			case OPERATION_TYPE_COMMAND:
				switch ($operation['opcommand']['type']) {
					case ZBX_SCRIPT_TYPE_IPMI:
						$result[$oIdx][] = array(bold(_('Run IPMI command').': '), BR(),
							italic(zbx_nl2br($operation['opcommand']['command']))
						);
						break;

					case ZBX_SCRIPT_TYPE_SSH:
						$result[$oIdx][] = array(bold(_('Run SSH commands').': '), BR(),
							italic(zbx_nl2br($operation['opcommand']['command']))
						);
						break;

					case ZBX_SCRIPT_TYPE_TELNET:
						$result[$oIdx][] = array(bold(_('Run TELNET commands').': '), BR(),
							italic(zbx_nl2br($operation['opcommand']['command']))
						);
						break;

					case ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT:
						if ($operation['opcommand']['execute_on'] == ZBX_SCRIPT_EXECUTE_ON_AGENT) {
							$result[$oIdx][] = array(bold(_('Run custom commands on Zabbix agent').': '), BR(),
								italic(zbx_nl2br($operation['opcommand']['command']))
							);
						}
						else {
							$result[$oIdx][] = array(bold(_('Run custom commands on Zabbix server').': '), BR(),
								italic(zbx_nl2br($operation['opcommand']['command']))
							);
						}
						break;

					case ZBX_SCRIPT_TYPE_GLOBAL_SCRIPT:
						$scriptId = $operation['opcommand']['scriptid'];

						if (isset($scripts[$scriptId])) {
							$result[$oIdx][] = array(bold(_('Run global script').': '),
								italic($scripts[$scriptId]['name'])
							);
						}
						break;

					default:
						$result[$oIdx][] = array(bold(_('Run commands').': '), BR(),
							italic(zbx_nl2br($operation['opcommand']['command']))
						);
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
			$info->setHint($alert['error'], 'on');
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
				(is_null($status)?'':' AND a.status='.zbx_dbstr($status)).
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
