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


function condition_operator2str($operator) {
	$operators = [
		CONDITION_OPERATOR_EQUAL  => '=',
		CONDITION_OPERATOR_NOT_EQUAL  => '<>',
		CONDITION_OPERATOR_LIKE  => _('like'),
		CONDITION_OPERATOR_NOT_LIKE  => _('not like'),
		CONDITION_OPERATOR_IN => _('in'),
		CONDITION_OPERATOR_MORE_EQUAL => '>=',
		CONDITION_OPERATOR_LESS_EQUAL => '<=',
		CONDITION_OPERATOR_NOT_IN => _('not in')
	];

	return $operators[$operator];
}

function condition_type2str($type) {
	$types = [
		CONDITION_TYPE_MAINTENANCE => _('Maintenance status'),
		CONDITION_TYPE_TRIGGER_NAME => _('Trigger name'),
		CONDITION_TYPE_TRIGGER_SEVERITY => _('Trigger severity'),
		CONDITION_TYPE_TRIGGER => _('Trigger'),
		CONDITION_TYPE_HOST_NAME => _('Host name'),
		CONDITION_TYPE_HOST_GROUP => _('Host group'),
		CONDITION_TYPE_TEMPLATE => _('Template'),
		CONDITION_TYPE_HOST => _('Host'),
		CONDITION_TYPE_TIME_PERIOD => _('Time period'),
		CONDITION_TYPE_DRULE => _('Discovery rule'),
		CONDITION_TYPE_DCHECK => _('Discovery check'),
		CONDITION_TYPE_DOBJECT => _('Discovery object'),
		CONDITION_TYPE_DHOST_IP => _('Host IP'),
		CONDITION_TYPE_DSERVICE_TYPE => _('Service type'),
		CONDITION_TYPE_DSERVICE_PORT => _('Service port'),
		CONDITION_TYPE_DSTATUS => _('Discovery status'),
		CONDITION_TYPE_DUPTIME => _('Uptime/Downtime'),
		CONDITION_TYPE_DVALUE => _('Received value'),
		CONDITION_TYPE_EVENT_ACKNOWLEDGED => _('Event acknowledged'),
		CONDITION_TYPE_APPLICATION => _('Application'),
		CONDITION_TYPE_PROXY => _('Proxy'),
		CONDITION_TYPE_EVENT_TYPE => _('Event type'),
		CONDITION_TYPE_HOST_METADATA => _('Host metadata'),
		CONDITION_TYPE_EVENT_TAG => _('Tag'),
		CONDITION_TYPE_EVENT_TAG_VALUE => _('Tag value')
	];

	return $types[$type];
}

function discovery_object2str($object = null) {
	$objects = [
		EVENT_OBJECT_DHOST => _('Device'),
		EVENT_OBJECT_DSERVICE => _('Service')
	];

	if ($object === null) {
		return $objects;
	}

	return $objects[$object];
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
 *													severity names
 *
 * @return array									returns an array of actions condition string values
 */
function actionConditionValueToString(array $actions, array $config) {
	$result = [];

	$groupIds = [];
	$triggerIds = [];
	$hostIds = [];
	$templateIds = [];
	$proxyIds = [];
	$dRuleIds = [];
	$dCheckIds = [];

	foreach ($actions as $i => $action) {
		$result[$i] = [];

		foreach ($action['filter']['conditions'] as $j => $condition) {
			// unknown types and all of the default values for other types are 'Unknown'
			$result[$i][$j] = _('Unknown');

			switch ($condition['conditiontype']) {
				case CONDITION_TYPE_HOST_GROUP:
					$groupIds[$condition['value']] = $condition['value'];
					break;

				case CONDITION_TYPE_TRIGGER:
					$triggerIds[$condition['value']] = $condition['value'];
					break;

				case CONDITION_TYPE_HOST:
					$hostIds[$condition['value']] = $condition['value'];
					break;

				case CONDITION_TYPE_TEMPLATE:
					$templateIds[$condition['value']] = $condition['value'];
					break;

				case CONDITION_TYPE_PROXY:
					$proxyIds[$condition['value']] = $condition['value'];
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
				case CONDITION_TYPE_EVENT_TAG:
				case CONDITION_TYPE_EVENT_TAG_VALUE:
					$result[$i][$j] = $condition['value'];
					break;

				case CONDITION_TYPE_EVENT_ACKNOWLEDGED:
					$result[$i][$j] = $condition['value'] ? _('Ack') : _('Not Ack');
					break;

				case CONDITION_TYPE_MAINTENANCE:
					$result[$i][$j] = _('maintenance');
					break;

				case CONDITION_TYPE_TRIGGER_SEVERITY:
					$result[$i][$j] = getSeverityName($condition['value'], $config);
					break;

				case CONDITION_TYPE_DRULE:
					$dRuleIds[$condition['value']] = $condition['value'];
					break;

				case CONDITION_TYPE_DCHECK:
					$dCheckIds[$condition['value']] = $condition['value'];
					break;

				case CONDITION_TYPE_DOBJECT:
					$result[$i][$j] = discovery_object2str($condition['value']);
					break;

				case CONDITION_TYPE_DSERVICE_TYPE:
					$result[$i][$j] = discovery_check_type2str($condition['value']);
					break;

				case CONDITION_TYPE_DSTATUS:
					$result[$i][$j] = discovery_object_status2str($condition['value']);
					break;

				case CONDITION_TYPE_EVENT_TYPE:
					$result[$i][$j] = eventType($condition['value']);
					break;
			}
		}
	}

	$groups = [];
	$triggers = [];
	$hosts = [];
	$templates = [];
	$proxies = [];
	$dRules = [];
	$dChecks = [];

	if ($groupIds) {
		$groups = API::HostGroup()->get([
			'output' => ['name'],
			'groupids' => $groupIds,
			'preservekeys' => true
		]);
	}

	if ($triggerIds) {
		$triggers = API::Trigger()->get([
			'output' => ['description'],
			'triggerids' => $triggerIds,
			'expandDescription' => true,
			'selectHosts' => ['name'],
			'preservekeys' => true
		]);
	}

	if ($hostIds) {
		$hosts = API::Host()->get([
			'output' => ['name'],
			'hostids' => $hostIds,
			'preservekeys' => true
		]);
	}

	if ($templateIds) {
		$templates = API::Template()->get([
			'output' => ['name'],
			'templateids' => $templateIds,
			'preservekeys' => true
		]);
	}

	if ($proxyIds) {
		$proxies = API::Proxy()->get([
			'output' => ['host'],
			'proxyids' => $proxyIds,
			'preservekeys' => true
		]);
	}

	if ($dRuleIds) {
		$dRules = API::DRule()->get([
			'output' => ['name'],
			'druleids' => $dRuleIds,
			'preservekeys' => true
		]);
	}

	if ($dCheckIds) {
		$dChecks = API::DCheck()->get([
			'output' => ['type', 'key_', 'ports'],
			'dcheckids' => $dCheckIds,
			'selectDRules' => ['name'],
			'preservekeys' => true
		]);
	}

	if ($groups || $triggers || $hosts || $templates || $proxies || $dRules || $dChecks) {
		foreach ($actions as $i => $action) {
			foreach ($action['filter']['conditions'] as $j => $condition) {
				$id = $condition['value'];

				switch ($condition['conditiontype']) {
					case CONDITION_TYPE_HOST_GROUP:
						if (isset($groups[$id])) {
							$result[$i][$j] = $groups[$id]['name'];
						}
						break;

					case CONDITION_TYPE_TRIGGER:
						if (isset($triggers[$id])) {
							$host = reset($triggers[$id]['hosts']);
							$result[$i][$j] = $host['name'].NAME_DELIMITER.$triggers[$id]['description'];
						}
						break;

					case CONDITION_TYPE_HOST:
						if (isset($hosts[$id])) {
							$result[$i][$j] = $hosts[$id]['name'];
						}
						break;

					case CONDITION_TYPE_TEMPLATE:
						if (isset($templates[$id])) {
							$result[$i][$j] = $templates[$id]['name'];
						}
						break;

					case CONDITION_TYPE_PROXY:
						if (isset($proxies[$id])) {
							$result[$i][$j] = $proxies[$id]['host'];
						}
						break;

					case CONDITION_TYPE_DRULE:
						if (isset($dRules[$id])) {
							$result[$i][$j] = $dRules[$id]['name'];
						}
						break;

					case CONDITION_TYPE_DCHECK:
						if (isset($dChecks[$id])) {
							$drule = reset($dChecks[$id]['drules']);
							$type = $dChecks[$id]['type'];
							$key_ = $dChecks[$id]['key_'];
							$ports = $dChecks[$id]['ports'];

							$dCheck = discovery_check2str($type, $key_, $ports);

							$result[$i][$j] = $drule['name'].NAME_DELIMITER.$dCheck;
						}
						break;
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
	$result = [];

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
 * @param string $value2
 *
 * @return array
 */
function getConditionDescription($conditionType, $operator, $value, $value2) {
	if ($conditionType == CONDITION_TYPE_EVENT_TAG_VALUE) {
		$description = [_('Tag')];
		$description[] = ' ';
		$description[] = italic(CHtml::encode($value2));
		$description[] = ' ';
	}
	else {
		$description = [condition_type2str($conditionType)];
		$description[] = ' ';
	}

	$description[] = condition_operator2str($operator);
	$description[] = ' ';
	$description[] = italic(CHtml::encode($value));

	return $description;
}

/**
 * Gathers media types, user groups, users, host groups, hosts and templates for actions and their operations, and
 * returns the HTML representation of action operation values according to action operation type.
 *
 * @param array $actions				Array of actions
 * @param int $type						Operations recovery type (ACTION_OPERATION or ACTION_RECOVERY_OPERATION)
 *
 * @return array						Returns an array of actions operation descriptions.
 */
function getActionOperationDescriptions(array $actions, $type) {
	$result = [];

	$media_typeids = [];
	$userids = [];
	$usr_grpids = [];
	$hostids = [];
	$groupids = [];
	$templateids = [];

	foreach ($actions as $i => $action) {
		$result[$i] = [];

		if ($type == ACTION_OPERATION) {
			foreach ($action['operations'] as $j => $operation) {
				$result[$i][$j] = [];

				switch ($operation['operationtype']) {
					case OPERATION_TYPE_MESSAGE:
						$media_typeid = $operation['opmessage']['mediatypeid'];

						if ($media_typeid != 0) {
							$media_typeids[$media_typeid] = $media_typeid;
						}

						if (array_key_exists('opmessage_usr', $operation) && $operation['opmessage_usr']) {
							foreach ($operation['opmessage_usr'] as $users) {
								$userids[$users['userid']] = $users['userid'];
							}
						}

						if (array_key_exists('opmessage_grp', $operation) && $operation['opmessage_grp']) {
							foreach ($operation['opmessage_grp'] as $user_groups) {
								$usr_grpids[$user_groups['usrgrpid']] = $user_groups['usrgrpid'];
							}
						}
						break;

					case OPERATION_TYPE_COMMAND:
						if (array_key_exists('opcommand_hst', $operation) && $operation['opcommand_hst']) {
							foreach ($operation['opcommand_hst'] as $host) {
								if ($host['hostid'] != 0) {
									$hostids[$host['hostid']] = $host['hostid'];
								}
							}
						}

						if (array_key_exists('opcommand_grp', $operation) && $operation['opcommand_grp']) {
							foreach ($operation['opcommand_grp'] as $host_group) {
								$groupids[$host_group['groupid']] = true;
							}
						}
						break;

					case OPERATION_TYPE_GROUP_ADD:
					case OPERATION_TYPE_GROUP_REMOVE:
						foreach ($operation['groupids'] as $groupid) {
							$groupids[$groupid] = true;
						}
						break;

					case OPERATION_TYPE_TEMPLATE_ADD:
					case OPERATION_TYPE_TEMPLATE_REMOVE:
						foreach ($operation['templateids'] as $templateid) {
							$templateids[$templateid] = true;
						}
						break;
				}
			}
		}
		else {
			foreach ($action['recovery_operations'] as $j => $operation) {
				$result[$i][$j] = [];

				switch ($operation['operationtype']) {
					case OPERATION_TYPE_MESSAGE:
						$media_typeid = $operation['opmessage']['mediatypeid'];

						if ($media_typeid != 0) {
							$media_typeids[$media_typeid] = $media_typeid;
						}

						if (array_key_exists('opmessage_usr', $operation) && $operation['opmessage_usr']) {
							foreach ($operation['opmessage_usr'] as $users) {
								$userids[$users['userid']] = $users['userid'];
							}
						}

						if (array_key_exists('opmessage_grp', $operation) && $operation['opmessage_grp']) {
							foreach ($operation['opmessage_grp'] as $user_groups) {
								$usr_grpids[$user_groups['usrgrpid']] = $user_groups['usrgrpid'];
							}
						}
						break;

					case OPERATION_TYPE_COMMAND:
						if (array_key_exists('opcommand_hst', $operation) && $operation['opcommand_hst']) {
							foreach ($operation['opcommand_hst'] as $host) {
								if ($host['hostid'] != 0) {
									$hostids[$host['hostid']] = $host['hostid'];
								}
							}
						}

						if (array_key_exists('opcommand_grp', $operation) && $operation['opcommand_grp']) {
							foreach ($operation['opcommand_grp'] as $host_group) {
								$groupids[$host_group['groupid']] = true;
							}
						}
						break;
				}
			}
		}
	}

	$media_types = [];
	$users = [];
	$user_groups = [];
	$hosts = [];
	$host_groups = [];
	$templates = [];

	if ($media_typeids) {
		$media_types = API::Mediatype()->get([
			'output' => ['description'],
			'mediatypeids' => $media_typeids,
			'preservekeys' => true
		]);
	}

	if ($userids) {
		$fullnames = [];

		$users = API::User()->get([
			'output' => ['userid', 'alias', 'name', 'surname'],
			'userids' => $userids
		]);

		foreach ($users as $user) {
			$fullnames[$user['userid']] = getUserFullname($user);
		}
	}

	if ($usr_grpids) {
		$user_groups = API::UserGroup()->get([
			'output' => ['name'],
			'usrgrpids' => $usr_grpids,
			'preservekeys' => true
		]);
	}

	if ($hostids) {
		$hosts = API::Host()->get([
			'output' => ['name'],
			'hostids' => $hostids,
			'preservekeys' => true
		]);
	}

	if ($groupids) {
		$host_groups = API::HostGroup()->get([
			'output' => ['name'],
			'groupids' => array_keys($groupids),
			'preservekeys' => true
		]);
	}

	if ($templateids) {
		$templates = API::Template()->get([
			'output' => ['name'],
			'templateids' => array_keys($templateids),
			'preservekeys' => true
		]);
	}

	// format the HTML ouput
	foreach ($actions as $i => $action) {
		if ($type == ACTION_OPERATION) {
			foreach ($action['operations'] as $j => $operation) {
				switch ($operation['operationtype']) {
					case OPERATION_TYPE_MESSAGE:
						$media_type = _('all media');
						$media_typeid = $operation['opmessage']['mediatypeid'];

						if ($media_typeid != 0 && isset($media_types[$media_typeid])) {
							$media_type = $media_types[$media_typeid]['description'];
						}

						if (array_key_exists('opmessage_usr', $operation) && $operation['opmessage_usr']) {
							$user_names_list = [];

							foreach ($operation['opmessage_usr'] as $user) {
								if (isset($fullnames[$user['userid']])) {
									$user_names_list[] = $fullnames[$user['userid']];
								}
							}

							order_result($user_names_list);

							$result[$i][$j][] = bold(_('Send message to users').': ');
							$result[$i][$j][] = [implode(', ', $user_names_list), SPACE, _('via'), SPACE,
								$media_type
							];
							$result[$i][$j][] = BR();
						}

						if (array_key_exists('opmessage_grp', $operation) && $operation['opmessage_grp']) {
							$user_groups_list = [];

							foreach ($operation['opmessage_grp'] as $userGroup) {
								if (isset($user_groups[$userGroup['usrgrpid']])) {
									$user_groups_list[] = $user_groups[$userGroup['usrgrpid']]['name'];
								}
							}

							order_result($user_groups_list);

							$result[$i][$j][] = bold(_('Send message to user groups').': ');
							$result[$i][$j][] = [implode(', ', $user_groups_list), SPACE, _('via'), SPACE,
								$media_type
							];
							$result[$i][$j][] = BR();
						}
						break;

					case OPERATION_TYPE_COMMAND:
						if (array_key_exists('opcommand_hst', $operation) && $operation['opcommand_hst']) {
							$host_list = [];

							foreach ($operation['opcommand_hst'] as $host) {
								if ($host['hostid'] == 0) {
									$result[$i][$j][] = [
										bold(_('Run remote commands on current host')),
										BR()
									];
								}
								elseif (isset($hosts[$host['hostid']])) {
									$host_list[] = $hosts[$host['hostid']]['name'];
								}
							}

							if ($host_list) {
								order_result($host_list);

								$result[$i][$j][] = bold(_('Run remote commands on hosts').': ');
								$result[$i][$j][] = [implode(', ', $host_list), BR()];
							}
						}

						if (array_key_exists('opcommand_grp', $operation) && $operation['opcommand_grp']) {
							$host_group_list = [];

							foreach ($operation['opcommand_grp'] as $host_group) {
								if (isset($host_groups[$host_group['groupid']])) {
									$host_group_list[] = $host_groups[$host_group['groupid']]['name'];
								}
							}

							order_result($host_group_list);

							$result[$i][$j][] = bold(_('Run remote commands on host groups').': ');
							$result[$i][$j][] = [implode(', ', $host_group_list), BR()];
						}
						break;

					case OPERATION_TYPE_HOST_ADD:
						$result[$i][$j][] = [bold(_('Add host')), BR()];
						break;

					case OPERATION_TYPE_HOST_REMOVE:
						$result[$i][$j][] = [bold(_('Remove host')), BR()];
						break;

					case OPERATION_TYPE_HOST_ENABLE:
						$result[$i][$j][] = [bold(_('Enable host')), BR()];
						break;

					case OPERATION_TYPE_HOST_DISABLE:
						$result[$i][$j][] = [bold(_('Disable host')), BR()];
						break;

					case OPERATION_TYPE_GROUP_ADD:
					case OPERATION_TYPE_GROUP_REMOVE:
						$host_group_list = [];

						foreach ($operation['groupids'] as $groupid) {
							if (array_key_exists($groupid, $host_groups)) {
								$host_group_list[] = $host_groups[$groupid]['name'];
							}
						}

						order_result($host_group_list);

						if ($operation['operationtype'] == OPERATION_TYPE_GROUP_ADD) {
							$result[$i][$j][] = bold(_('Add to host groups').': ');
						}
						else {
							$result[$i][$j][] = bold(_('Remove from host groups').': ');
						}

						$result[$i][$j][] = [implode(', ', $host_group_list), BR()];
						break;

					case OPERATION_TYPE_TEMPLATE_ADD:
					case OPERATION_TYPE_TEMPLATE_REMOVE:
						$template_list = [];

						foreach ($operation['templateids'] as $templateid) {
							if (array_key_exists($templateid, $templates)) {
								$template_list[] = $templates[$templateid]['name'];
							}
						}

						order_result($template_list);

						if ($operation['operationtype'] == OPERATION_TYPE_TEMPLATE_ADD) {
							$result[$i][$j][] = bold(_('Link to templates').': ');
						}
						else {
							$result[$i][$j][] = bold(_('Unlink from templates').': ');
						}

						$result[$i][$j][] = [implode(', ', $template_list), BR()];
						break;

					case OPERATION_TYPE_HOST_INVENTORY:
						$host_inventory_modes = getHostInventoryModes();
						$result[$i][$j][] = bold(operation_type2str(OPERATION_TYPE_HOST_INVENTORY).': ');
						$result[$i][$j][] = [
							$host_inventory_modes[$operation['opinventory']['inventory_mode']],
							BR()
						];
						break;
				}
			}
		}
		else {
			foreach ($action['recovery_operations'] as $j => $operation) {
				switch ($operation['operationtype']) {
					case OPERATION_TYPE_MESSAGE:
						$media_type = _('all media');
						$media_typeid = $operation['opmessage']['mediatypeid'];

						if ($media_typeid != 0 && isset($media_types[$media_typeid])) {
							$media_type = $media_types[$media_typeid]['description'];
						}

						if (array_key_exists('opmessage_usr', $operation) && $operation['opmessage_usr']) {
							$user_names_list = [];

							foreach ($operation['opmessage_usr'] as $user) {
								if (isset($fullnames[$user['userid']])) {
									$user_names_list[] = $fullnames[$user['userid']];
								}
							}

							order_result($user_names_list);

							$result[$i][$j][] = bold(_('Send message to users').': ');
							$result[$i][$j][] = [implode(', ', $user_names_list), SPACE, _('via'), SPACE,
								$media_type
							];
							$result[$i][$j][] = BR();
						}

						if (array_key_exists('opmessage_grp', $operation) && $operation['opmessage_grp']) {
							$user_groups_list = [];

							foreach ($operation['opmessage_grp'] as $userGroup) {
								if (isset($user_groups[$userGroup['usrgrpid']])) {
									$user_groups_list[] = $user_groups[$userGroup['usrgrpid']]['name'];
								}
							}

							order_result($user_groups_list);

							$result[$i][$j][] = bold(_('Send message to user groups').': ');
							$result[$i][$j][] = [implode(', ', $user_groups_list), SPACE, _('via'), SPACE,
								$media_type
							];
							$result[$i][$j][] = BR();
						}
						break;

					case OPERATION_TYPE_COMMAND:
						if (array_key_exists('opcommand_hst', $operation) && $operation['opcommand_hst']) {
							$host_list = [];

							foreach ($operation['opcommand_hst'] as $host) {
								if ($host['hostid'] == 0) {
									$result[$i][$j][] = [
										bold(_('Run remote commands on current host')),
										BR()
									];
								}
								elseif (isset($hosts[$host['hostid']])) {
									$host_list[] = $hosts[$host['hostid']]['name'];
								}
							}

							if ($host_list) {
								order_result($host_list);

								$result[$i][$j][] = bold(_('Run remote commands on hosts').': ');
								$result[$i][$j][] = [implode(', ', $host_list), BR()];
							}
						}

						if (array_key_exists('opcommand_grp', $operation) && $operation['opcommand_grp']) {
							$host_group_list = [];

							foreach ($operation['opcommand_grp'] as $host_group) {
								if (isset($host_groups[$host_group['groupid']])) {
									$host_group_list[] = $host_groups[$host_group['groupid']]['name'];
								}
							}

							order_result($host_group_list);

							$result[$i][$j][] = bold(_('Run remote commands on host groups').': ');
							$result[$i][$j][] = [implode(', ', $host_group_list), BR()];
						}
						break;

					case OPERATION_TYPE_RECOVERY_MESSAGE:
						$result[$i][$j][] = bold(
							_('Notify all who received any messages regarding the problem before')
						);
						break;
				}
			}
		}
	}

	return $result;
}

/**
 * Gathers action operation script details and returns the HTML items representing action operation with hint.
 *
 * @param array  $operations								Array of action operations or recovery operations.
 * @param string $operation['operationtype']				Action operation type.
 *															Possible values: OPERATION_TYPE_MESSAGE and OPERATION_TYPE_COMMAND
 * @param string $operation['opcommand']['type']			Action operation command type.
 *															Possible values: ZBX_SCRIPT_TYPE_IPMI, ZBX_SCRIPT_TYPE_SSH,
 *															ZBX_SCRIPT_TYPE_TELNET, ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT
 *															and ZBX_SCRIPT_TYPE_GLOBAL_SCRIPT
 * @param string $operation['opmessage']['default_msg']		'1' to show default message (optional)
 * @param array  $defaultMessage							Array containing default subject and message set via action.
 * @param string $defaultMessage['subject']					Default subject.
 * @param string $defaultMessage['message']					Default message text.
 *
 * @return array											Returns an array of action operation hints.
 */
function getActionOperationHints(array $operations, array $defaultMessage) {
	$result = [];
	$scriptids = [];
	$scripts = [];

	foreach ($operations as $operation) {
		if ($operation['operationtype'] == OPERATION_TYPE_COMMAND
				&& $operation['opcommand']['type'] == ZBX_SCRIPT_TYPE_GLOBAL_SCRIPT) {
			$scriptids[$operation['opcommand']['scriptid']] = true;
		}
	}

	if ($scriptids) {
		$scripts = API::Script()->get([
			'output' => ['name'],
			'scriptids' => array_keys($scriptids),
			'preservekeys' => true
		]);
	}

	foreach ($operations as $key => $operation) {
		$result[$key] = [];

		switch ($operation['operationtype']) {
			case OPERATION_TYPE_MESSAGE:
				// show default entered action subject and message or show custom subject and message for each operation
				$subject = (isset($operation['opmessage']['default_msg']) && $operation['opmessage']['default_msg'])
					? $defaultMessage['subject']
					: $operation['opmessage']['subject'];

				$message = (isset($operation['opmessage']['default_msg']) && $operation['opmessage']['default_msg'])
					? $defaultMessage['message']
					: $operation['opmessage']['message'];

				$result[$key][] = [bold($subject), BR(), BR(), zbx_nl2br($message)];
				break;

			case OPERATION_TYPE_COMMAND:
				switch ($operation['opcommand']['type']) {
					case ZBX_SCRIPT_TYPE_IPMI:
						$result[$key][] = [bold(_('Run IPMI command').': '), BR(),
							italic(zbx_nl2br($operation['opcommand']['command']))
						];
						break;

					case ZBX_SCRIPT_TYPE_SSH:
						$result[$key][] = [bold(_('Run SSH commands').': '), BR(),
							italic(zbx_nl2br($operation['opcommand']['command']))
						];
						break;

					case ZBX_SCRIPT_TYPE_TELNET:
						$result[$key][] = [bold(_('Run TELNET commands').': '), BR(),
							italic(zbx_nl2br($operation['opcommand']['command']))
						];
						break;

					case ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT:
						if ($operation['opcommand']['execute_on'] == ZBX_SCRIPT_EXECUTE_ON_AGENT) {
							$result[$key][] = [bold(_('Run custom commands on Zabbix agent').': '), BR(),
								italic(zbx_nl2br($operation['opcommand']['command']))
							];
						}
						else {
							$result[$key][] = [bold(_('Run custom commands on Zabbix server').': '), BR(),
								italic(zbx_nl2br($operation['opcommand']['command']))
							];
						}
						break;

					case ZBX_SCRIPT_TYPE_GLOBAL_SCRIPT:
						$scriptId = $operation['opcommand']['scriptid'];

						if (isset($scripts[$scriptId])) {
							$result[$key][] = [bold(_('Run global script').': '),
								italic($scripts[$scriptId]['name'])
							];
						}
						break;

					default:
						$result[$key][] = [bold(_('Run commands').': '), BR(),
							italic(zbx_nl2br($operation['opcommand']['command']))
						];
				}
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
	$conditions[EVENT_SOURCE_TRIGGERS] = [
		CONDITION_TYPE_APPLICATION,
		CONDITION_TYPE_HOST_GROUP,
		CONDITION_TYPE_TEMPLATE,
		CONDITION_TYPE_HOST,
		CONDITION_TYPE_TRIGGER,
		CONDITION_TYPE_TRIGGER_NAME,
		CONDITION_TYPE_TRIGGER_SEVERITY,
		CONDITION_TYPE_TIME_PERIOD,
		CONDITION_TYPE_MAINTENANCE,
		CONDITION_TYPE_EVENT_TAG,
		CONDITION_TYPE_EVENT_TAG_VALUE
	];
	$conditions[EVENT_SOURCE_DISCOVERY] = [
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
	];
	$conditions[EVENT_SOURCE_AUTO_REGISTRATION] = [
		CONDITION_TYPE_HOST_NAME,
		CONDITION_TYPE_PROXY,
		CONDITION_TYPE_HOST_METADATA
	];
	$conditions[EVENT_SOURCE_INTERNAL] = [
		CONDITION_TYPE_APPLICATION,
		CONDITION_TYPE_EVENT_TYPE,
		CONDITION_TYPE_HOST_GROUP,
		CONDITION_TYPE_TEMPLATE,
		CONDITION_TYPE_HOST
	];

	if (isset($conditions[$eventsource])) {
		return $conditions[$eventsource];
	}

	return $conditions[EVENT_SOURCE_TRIGGERS];
}

function get_opconditions_by_eventsource($eventsource) {
	$conditions = [
		EVENT_SOURCE_TRIGGERS => [CONDITION_TYPE_EVENT_ACKNOWLEDGED],
		EVENT_SOURCE_DISCOVERY => [],
	];

	if (isset($conditions[$eventsource])) {
		return $conditions[$eventsource];
	}
}

/**
 * Return allowed operations types.
 *
 * @param int $eventsource
 *
 * @return array
 */
function getAllowedOperations($eventsource) {
	if ($eventsource == EVENT_SOURCE_TRIGGERS) {
		$operations = [
			ACTION_OPERATION => [
				OPERATION_TYPE_MESSAGE,
				OPERATION_TYPE_COMMAND
			],
			ACTION_RECOVERY_OPERATION => [
				OPERATION_TYPE_MESSAGE,
				OPERATION_TYPE_COMMAND,
				OPERATION_TYPE_RECOVERY_MESSAGE
			]
		];
	}

	if ($eventsource == EVENT_SOURCE_DISCOVERY) {
		$operations[ACTION_OPERATION] = [
			OPERATION_TYPE_MESSAGE,
			OPERATION_TYPE_COMMAND,
			OPERATION_TYPE_HOST_ADD,
			OPERATION_TYPE_HOST_REMOVE,
			OPERATION_TYPE_GROUP_ADD,
			OPERATION_TYPE_GROUP_REMOVE,
			OPERATION_TYPE_TEMPLATE_ADD,
			OPERATION_TYPE_TEMPLATE_REMOVE,
			OPERATION_TYPE_HOST_ENABLE,
			OPERATION_TYPE_HOST_DISABLE,
			OPERATION_TYPE_HOST_INVENTORY
		];
	}

	if ($eventsource == EVENT_SOURCE_AUTO_REGISTRATION) {
		$operations[ACTION_OPERATION] = [
			OPERATION_TYPE_MESSAGE,
			OPERATION_TYPE_COMMAND,
			OPERATION_TYPE_HOST_ADD,
			OPERATION_TYPE_GROUP_ADD,
			OPERATION_TYPE_TEMPLATE_ADD,
			OPERATION_TYPE_HOST_DISABLE,
			OPERATION_TYPE_HOST_INVENTORY
		];
	}

	if ($eventsource == EVENT_SOURCE_INTERNAL) {
		$operations = [
			ACTION_OPERATION => [OPERATION_TYPE_MESSAGE],
			ACTION_RECOVERY_OPERATION => [
				OPERATION_TYPE_MESSAGE,
				OPERATION_TYPE_RECOVERY_MESSAGE
			]
		];
	}

	return $operations;
}

function operation_type2str($type = null) {
	$types = [
		OPERATION_TYPE_MESSAGE => _('Send message'),
		OPERATION_TYPE_COMMAND => _('Remote command'),
		OPERATION_TYPE_HOST_ADD => _('Add host'),
		OPERATION_TYPE_HOST_REMOVE => _('Remove host'),
		OPERATION_TYPE_HOST_ENABLE => _('Enable host'),
		OPERATION_TYPE_HOST_DISABLE => _('Disable host'),
		OPERATION_TYPE_GROUP_ADD => _('Add to host group'),
		OPERATION_TYPE_GROUP_REMOVE => _('Remove from host group'),
		OPERATION_TYPE_TEMPLATE_ADD => _('Link to template'),
		OPERATION_TYPE_TEMPLATE_REMOVE => _('Unlink from template'),
		OPERATION_TYPE_HOST_INVENTORY => _('Set host inventory mode'),
		OPERATION_TYPE_RECOVERY_MESSAGE => _('Send recovery message')
	];

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
		$esc_step_from = [];
		$esc_step_to = [];
		$esc_period = [];
		$operationTypes = [];

		foreach ($operations as $key => $operation) {
			$esc_step_from[$key] = $operation['esc_step_from'];
			$esc_step_to[$key] = $operation['esc_step_to'];
			$esc_period[$key] = $operation['esc_period'];
			$operationTypes[$key] = $operation['operationtype'];
		}
		array_multisort($esc_step_from, SORT_ASC, $esc_step_to, SORT_ASC, $esc_period, SORT_ASC, $operationTypes, SORT_ASC, $operations);
	}
	else {
		CArrayHelper::sort($operations, ['operationtype']);
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
	$operators[CONDITION_TYPE_HOST_GROUP] = [
		CONDITION_OPERATOR_EQUAL,
		CONDITION_OPERATOR_NOT_EQUAL
	];
	$operators[CONDITION_TYPE_TEMPLATE] = [
		CONDITION_OPERATOR_EQUAL,
		CONDITION_OPERATOR_NOT_EQUAL
	];
	$operators[CONDITION_TYPE_HOST] = [
		CONDITION_OPERATOR_EQUAL,
		CONDITION_OPERATOR_NOT_EQUAL
	];
	$operators[CONDITION_TYPE_TRIGGER] = [
		CONDITION_OPERATOR_EQUAL,
		CONDITION_OPERATOR_NOT_EQUAL
	];
	$operators[CONDITION_TYPE_TRIGGER_NAME] = [
		CONDITION_OPERATOR_LIKE,
		CONDITION_OPERATOR_NOT_LIKE
	];
	$operators[CONDITION_TYPE_TRIGGER_SEVERITY] = [
		CONDITION_OPERATOR_EQUAL,
		CONDITION_OPERATOR_NOT_EQUAL,
		CONDITION_OPERATOR_MORE_EQUAL,
		CONDITION_OPERATOR_LESS_EQUAL
	];
	$operators[CONDITION_TYPE_TIME_PERIOD] = [
		CONDITION_OPERATOR_IN,
		CONDITION_OPERATOR_NOT_IN
	];
	$operators[CONDITION_TYPE_MAINTENANCE] = [
		CONDITION_OPERATOR_IN,
		CONDITION_OPERATOR_NOT_IN
	];
	$operators[CONDITION_TYPE_DRULE] = [
		CONDITION_OPERATOR_EQUAL,
		CONDITION_OPERATOR_NOT_EQUAL
	];
	$operators[CONDITION_TYPE_DCHECK] = [
		CONDITION_OPERATOR_EQUAL,
		CONDITION_OPERATOR_NOT_EQUAL
	];
	$operators[CONDITION_TYPE_DOBJECT] = [
		CONDITION_OPERATOR_EQUAL,
	];
	$operators[CONDITION_TYPE_PROXY] = [
		CONDITION_OPERATOR_EQUAL,
		CONDITION_OPERATOR_NOT_EQUAL
	];
	$operators[CONDITION_TYPE_DHOST_IP] = [
		CONDITION_OPERATOR_EQUAL,
		CONDITION_OPERATOR_NOT_EQUAL
	];
	$operators[CONDITION_TYPE_DSERVICE_TYPE] = [
		CONDITION_OPERATOR_EQUAL,
		CONDITION_OPERATOR_NOT_EQUAL
	];
	$operators[CONDITION_TYPE_DSERVICE_PORT] = [
		CONDITION_OPERATOR_EQUAL,
		CONDITION_OPERATOR_NOT_EQUAL
	];
	$operators[CONDITION_TYPE_DSTATUS] = [
		CONDITION_OPERATOR_EQUAL,
	];
	$operators[CONDITION_TYPE_DUPTIME] = [
		CONDITION_OPERATOR_MORE_EQUAL,
		CONDITION_OPERATOR_LESS_EQUAL
	];
	$operators[CONDITION_TYPE_DVALUE] = [
		CONDITION_OPERATOR_EQUAL,
		CONDITION_OPERATOR_NOT_EQUAL,
		CONDITION_OPERATOR_MORE_EQUAL,
		CONDITION_OPERATOR_LESS_EQUAL,
		CONDITION_OPERATOR_LIKE,
		CONDITION_OPERATOR_NOT_LIKE
	];
	$operators[CONDITION_TYPE_EVENT_ACKNOWLEDGED] = [
		CONDITION_OPERATOR_EQUAL
	];
	$operators[CONDITION_TYPE_APPLICATION] = [
		CONDITION_OPERATOR_EQUAL,
		CONDITION_OPERATOR_LIKE,
		CONDITION_OPERATOR_NOT_LIKE
	];
	$operators[CONDITION_TYPE_HOST_NAME] = [
		CONDITION_OPERATOR_LIKE,
		CONDITION_OPERATOR_NOT_LIKE
	];
	$operators[CONDITION_TYPE_EVENT_TYPE] = [
		CONDITION_OPERATOR_EQUAL
	];
	$operators[CONDITION_TYPE_HOST_METADATA] = [
		CONDITION_OPERATOR_LIKE,
		CONDITION_OPERATOR_NOT_LIKE
	];
	$operators[CONDITION_TYPE_EVENT_TAG] = [
		CONDITION_OPERATOR_EQUAL,
		CONDITION_OPERATOR_NOT_EQUAL,
		CONDITION_OPERATOR_LIKE,
		CONDITION_OPERATOR_NOT_LIKE
	];
	$operators[CONDITION_TYPE_EVENT_TAG_VALUE] = [
		CONDITION_OPERATOR_EQUAL,
		CONDITION_OPERATOR_NOT_EQUAL,
		CONDITION_OPERATOR_LIKE,
		CONDITION_OPERATOR_NOT_LIKE
	];

	if (isset($operators[$conditiontype])) {
		return $operators[$conditiontype];
	}

	return [];
}

function count_operations_delay($operations, $def_period = 0) {
	$delays = [1 => 0];
	$periods = [];
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
	$dbUsers = API::User()->get([
		'output' => ['userid', 'alias', 'name', 'surname'],
		'userids' => zbx_objectValues($alerts, 'userid'),
		'preservekeys' => true
	]);

	$table = (new CTableInfo())->setHeader([
		_('Step'), _('Time'), _('Type'), _('Status'), _('Retries left'), _('Recipient'), _('Message'), _('Info')
	]);

	foreach ($alerts as $alert) {
		if ($alert['alerttype'] != ALERT_TYPE_MESSAGE) {
			continue;
		}

		$mediaType = array_pop($alert['mediatypes']);

		if ($alert['status'] == ALERT_STATUS_SENT) {
			$status = (new CSpan(_('Sent')))->addClass(ZBX_STYLE_GREEN);
			$retries = '';
		}
		elseif ($alert['status'] == ALERT_STATUS_NOT_SENT) {
			$status = (new CSpan(_('In progress')))->addClass(ZBX_STYLE_YELLOW);
			$retries = (new CSpan(ALERT_MAX_RETRIES - $alert['retries']))->addClass(ZBX_STYLE_YELLOW);
		}
		else {
			$status = (new CSpan(_('Not sent')))->addClass(ZBX_STYLE_RED);
			$retries = (new CSpan('0'))->addClass(ZBX_STYLE_RED);
		}

		$recipient = $alert['userid']
			? [bold(getUserFullname($dbUsers[$alert['userid']])), BR(), $alert['sendto']]
			: $alert['sendto'];

		$table->addRow([
			$alert['esc_step'],
			zbx_date2str(DATE_TIME_FORMAT_SECONDS, $alert['clock']),
			isset($mediaType['description']) ? $mediaType['description'] : '',
			$status,
			$retries,
			$recipient,
			[bold($alert['subject']), BR(), BR(), zbx_nl2br($alert['message'])],
			$alert['error'] === '' ? '' : makeErrorIcon($alert['error'])
		]);
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
	$table = (new CTableInfo())->setHeader([_('Step'), _('Time'), _('Status'), _('Command'), _('Error')]);

	foreach ($alerts as $alert) {
		if ($alert['alerttype'] != ALERT_TYPE_COMMAND) {
			continue;
		}

		switch ($alert['status']) {
			case ALERT_STATUS_SENT:
				$status = (new CSpan(_('Executed')))->addClass(ZBX_STYLE_GREEN);
				break;

			case ALERT_STATUS_NOT_SENT:
				$status = (new CSpan(_('In progress')))->addClass(ZBX_STYLE_YELLOW);
				break;

			default:
				$status = (new CSpan(_('Not sent')))->addClass(ZBX_STYLE_RED);
				break;
		}

		$table->addRow([
			$alert['esc_step'],
			zbx_date2str(DATE_TIME_FORMAT_SECONDS, $alert['clock']),
			$status,
			zbx_nl2br($alert['message']),
			$alert['error'] ? (new CSpan($alert['error']))->addClass(ZBX_STYLE_RED) : ''
		]);
	}

	return $table;
}

function makeActionHints($alerts, $mediatypes, $users, $status) {
	$table = (new CTableInfo())->setHeader([_('Time'), _('User'), _('Details'), _('Status'), _('Info')]);

	$popup_rows = 0;

	foreach ($alerts as $alert) {
		switch ($status) {
			case ALERT_STATUS_NOT_SENT:
				$status_str = (new CSpan(_('In progress')))->addClass(ZBX_STYLE_YELLOW);
				break;

			case ALERT_STATUS_SENT:
				$status_str = (new CSpan($alert['alerttype'] == ALERT_TYPE_COMMAND ? _('Executed') : _('Sent')))
					->addClass(ZBX_STYLE_GREEN);
				break;

			default:
				$status_str = (new CSpan(_('Not sent')))->addClass(ZBX_STYLE_RED);
		}

		switch ($alert['alerttype']) {
			case ALERT_TYPE_MESSAGE:
				$user = array_key_exists($alert['userid'], $users) ? getUserFullname($users[$alert['userid']]) : '';
				$message = array_key_exists($alert['mediatypeid'], $mediatypes)
					? $mediatypes[$alert['mediatypeid']]['description']
					: '';
				break;
			case ALERT_TYPE_COMMAND:
				$user = '';
				$message = [bold(_('Command').NAME_DELIMITER), BR(), zbx_nl2br($alert['message'])];
				break;
			default:
				$user = '';
				$message = '';
		}

		$table->addRow([
			zbx_date2str(DATE_TIME_FORMAT_SECONDS, $alert['clock']),
			$user,
			$message,
			$status_str,
			$alert['error'] === '' ? '' : makeErrorIcon($alert['error'])
		]);

		if (++$popup_rows == ZBX_WIDGET_ROWS) {
			break;
		}
	}

	return $table;
}

function makeEventsActions($eventids) {
	if (!$eventids) {
		return [];
	}

	$result = DBselect(
		'SELECT a.eventid,a.mediatypeid,a.userid,a.clock,a.message,a.status,a.alerttype,a.error'.
		' FROM alerts a'.
		' WHERE '.dbConditionInt('a.eventid', $eventids).
			' AND a.alerttype IN ('.ALERT_TYPE_MESSAGE.','.ALERT_TYPE_COMMAND.')'.
		' ORDER BY a.alertid DESC'
	);

	$events = [];
	$userids = [];
	$users = [];
	$mediatypeids = [];
	$mediatypes = [];

	while ($row = DBfetch($result)) {
		if (!array_key_exists($row['eventid'], $events)) {
			$events[$row['eventid']] = [
				ALERT_STATUS_NOT_SENT => [],
				ALERT_STATUS_SENT => [],
				ALERT_STATUS_FAILED => []
			];
		}

		$event = [
			'clock' => $row['clock'],
			'alerttype' => $row['alerttype'],
			'error' => $row['error']
		];

		switch ($event['alerttype']) {
			case ALERT_TYPE_COMMAND:
				$event['message'] = $row['message'];
				break;

			case ALERT_TYPE_MESSAGE:
				$event['mediatypeid'] = $row['mediatypeid'];
				$event['userid'] = $row['userid'];

				if ($event['mediatypeid'] != 0) {
					$mediatypeids[$row['mediatypeid']] = true;
				}

				if ($event['userid'] != 0) {
					$userids[$row['userid']] = true;
				}
				break;
		}

		$events[$row['eventid']][$row['status']][] = $event;
	}

	if ($mediatypeids) {
		$mediatypes = API::Mediatype()->get([
			'output' => ['description'],
			'mediatypeids' => array_keys($mediatypeids),
			'preservekeys' => true
		]);
	}

	if ($userids) {
		$users = API::User()->get([
			'output' => ['alias', 'name', 'surname'],
			'userids' => array_keys($userids),
			'preservekeys' => true
		]);
	}

	foreach ($events as $eventid => &$event) {
		$event = (new CList([
			$event[ALERT_STATUS_SENT]
				? (new CSpan(count($event[ALERT_STATUS_SENT])))
					->addClass(ZBX_STYLE_LINK_ACTION)
					->addClass(ZBX_STYLE_GREEN)
					->setHint(makeActionHints($event[ALERT_STATUS_SENT], $mediatypes, $users, ALERT_STATUS_SENT))
				: '',
			$event[ALERT_STATUS_NOT_SENT]
				? (new CSpan(count($event[ALERT_STATUS_NOT_SENT])))
					->addClass(ZBX_STYLE_LINK_ACTION)
					->addClass(ZBX_STYLE_YELLOW)
					->setHint(
						makeActionHints($event[ALERT_STATUS_NOT_SENT], $mediatypes, $users, ALERT_STATUS_NOT_SENT)
					)
				: '',
			$event[ALERT_STATUS_FAILED]
				? (new CSpan(count($event[ALERT_STATUS_FAILED])))
					->addClass(ZBX_STYLE_LINK_ACTION)
					->addClass(ZBX_STYLE_RED)
					->setHint(makeActionHints($event[ALERT_STATUS_FAILED], $mediatypes, $users, ALERT_STATUS_FAILED))
				: ''
		]))->addClass(ZBX_STYLE_LIST_HOR_MIN_WIDTH);
	}
	unset($event);

	return $events;
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
	$types = [
		EVENT_TYPE_ITEM_NOTSUPPORTED => _('Item in "not supported" state'),
		EVENT_TYPE_LLDRULE_NOTSUPPORTED => _('Low-level discovery rule in "not supported" state'),
		EVENT_TYPE_TRIGGER_UNKNOWN => _('Trigger in "unknown" state')
	];

	if (is_null($type)) {
		return $types;
	}

	return $types[$type];
}
