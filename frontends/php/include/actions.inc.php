<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
			$operations_key = ($type == ACTION_RECOVERY_OPERATION)
				? 'recovery_operations'
				: 'ack_operations';

			foreach ($action[$operations_key] as $j => $operation) {
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
			$operations_key = ($type == ACTION_RECOVERY_OPERATION)
				? 'recovery_operations'
				: 'ack_operations';

			foreach ($action[$operations_key] as $j => $operation) {
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
					case OPERATION_TYPE_ACK_MESSAGE:
						$result[$i][$j][] = bold(_('Notify all involved'));
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
 *															Possible values: OPERATION_TYPE_MESSAGE, OPERATION_TYPE_COMMAND,
 *															OPERATION_TYPE_ACK_MESSAGE and OPERATION_TYPE_RECOVERY_MESSAGE
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

				$result_hint = [];

				if (trim($subject)) {
					$result_hint = [bold($subject), BR(), BR()];
				}

				if (trim($message)) {
					$result_hint[] = zbx_nl2br($message);
				}

				if ($result_hint) {
					$result[$key][] = $result_hint;
				}
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
							$result[$key][] = [bold(_s('Run custom commands on %1$s', _('Zabbix agent')).': '),
								BR(), italic(zbx_nl2br($operation['opcommand']['command']))
							];
						}
						elseif ($operation['opcommand']['execute_on'] == ZBX_SCRIPT_EXECUTE_ON_PROXY) {
							$result[$key][] = [bold(_s('Run custom commands on %1$s', _('Zabbix server (proxy)')).': '),
								BR(), italic(zbx_nl2br($operation['opcommand']['command']))
							];
						}
						else {
							$result[$key][] = [bold(_s('Run custom commands on %1$s', _('Zabbix server')).': '),
								BR(), italic(zbx_nl2br($operation['opcommand']['command']))
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
				break;

			case OPERATION_TYPE_ACK_MESSAGE:
			case OPERATION_TYPE_RECOVERY_MESSAGE:
				$result_hint = [];
				$message = (array_key_exists('default_msg', $operation['opmessage'])
					&& $operation['opmessage']['default_msg'])
					? $defaultMessage
					: $operation['opmessage'];

				if (trim($message['subject'])) {
					$result_hint = [bold($message['subject']), BR(), BR()];
				}

				if (trim($message['message'])) {
					$result_hint[] = zbx_nl2br($message['message']);
				}

				if ($result_hint) {
					$result[$key][] = $result_hint;
				}
				break;
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
			],
			ACTION_ACKNOWLEDGE_OPERATION => [
				OPERATION_TYPE_MESSAGE,
				OPERATION_TYPE_COMMAND,
				OPERATION_TYPE_ACK_MESSAGE
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

/**
 * Get operation type text label according $type value. If $type is equal null array of all available operation types
 * will be returned.
 *
 * @param int|null $type  Operation type, one of OPERATION_TYPE_* constant or null.
 *
 * @return string|array
 */
function operation_type2str($type) {
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
		OPERATION_TYPE_RECOVERY_MESSAGE => _('Notify all involved'),
		OPERATION_TYPE_ACK_MESSAGE => _('Notify all involved')
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

		$simple_interval_parser = new CSimpleIntervalParser();

		foreach ($operations as $key => $operation) {
			$esc_step_from[$key] = $operation['esc_step_from'];
			$esc_step_to[$key] = $operation['esc_step_to'];
			// Try to sort by "esc_period" in seconds, otherwise sort as string in case it's a macro or something invalid.
			$esc_period[$key] = ($simple_interval_parser->parse($operation['esc_period']) == CParser::PARSE_SUCCESS)
				? timeUnitToSeconds($operation['esc_period'])
				: $operation['esc_period'];

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

function count_operations_delay($operations, $def_period) {
	$delays = [1 => 0];
	$periods = [];
	$max_step = 0;

	$simple_interval_parser = new CSimpleIntervalParser();

	$def_period = CMacrosResolverHelper::resolveTimeUnitMacros(
		[['def_period' => $def_period]], ['def_period']
	)[0]['def_period'];

	$def_period = ($simple_interval_parser->parse($def_period) == CParser::PARSE_SUCCESS)
		? timeUnitToSeconds($def_period)
		: null;

	$operations = CMacrosResolverHelper::resolveTimeUnitMacros($operations, ['esc_period']);

	foreach ($operations as $operation) {
		$esc_period = ($simple_interval_parser->parse($operation['esc_period']) == CParser::PARSE_SUCCESS)
			? timeUnitToSeconds($operation['esc_period'])
			: null;

		$esc_period = ($esc_period === null || $esc_period != 0) ? $esc_period : $def_period;
		$step_to = ($operation['esc_step_to'] != 0) ? $operation['esc_step_to'] : 9999;

		if ($max_step < $operation['esc_step_from']) {
			$max_step = $operation['esc_step_from'];
		}

		for ($i = $operation['esc_step_from']; $i <= $step_to; $i++) {
			if (!array_key_exists($i, $periods) || $esc_period === null || $periods[$i] > $esc_period) {
				$periods[$i] = $esc_period;
			}
		}
	}

	for ($i = 1; $i <= $max_step; $i++) {
		$esc_period = array_key_exists($i, $periods) ? $periods[$i] : $def_period;
		$delays[$i + 1] = ($esc_period !== null && $delays[$i] !== null) ? $delays[$i] + $esc_period : null;
	}

	return $delays;
}

function makeActionHints($alerts, $r_alerts, $mediatypes, $users, $display_recovery_alerts) {
	$table = (new CTableInfo())->setHeader([_('Step'), _('Time'), _('User'), _('Details'), _('Status'), _('Info')]);

	$popup_rows = 0;
	$recovery = true;

	foreach ([$r_alerts, $alerts] as $alerts_data) {
		$show_header = $display_recovery_alerts;

		foreach ($alerts_data as $alert) {
			switch ($alert['status']) {
				case ALERT_STATUS_SENT:
					$status = (new CSpan(($alert['alerttype'] == ALERT_TYPE_COMMAND) ? _('Executed') : _('Sent')))
						->addClass(ZBX_STYLE_GREEN);
					break;
				case ALERT_STATUS_NEW:
				case ALERT_STATUS_NOT_SENT:
					$status = (new CSpan(_('In progress')))->addClass(ZBX_STYLE_YELLOW);
					break;
				default:
					$status = (new CSpan(_('Failed')))->addClass(ZBX_STYLE_RED);
			}

			switch ($alert['alerttype']) {
				case ALERT_TYPE_MESSAGE:
					$user = array_key_exists($alert['userid'], $users)
						? getUserFullname($users[$alert['userid']])
						: _('Inaccessible user');

					$message = array_key_exists($alert['mediatypeid'], $mediatypes)
						? $mediatypes[$alert['mediatypeid']]['description']
						: '';
					break;

				case ALERT_TYPE_COMMAND:
					$user = '';
					$message = _('Remote command');
					break;

				default:
					$user = '';
					$message = '';
			}

			$info_icons = [];
			if ($alert['error'] !== ''
					&& !($alert['status'] == ALERT_STATUS_NEW || $alert['status'] == ALERT_STATUS_NOT_SENT)) {
				$info_icons[] = makeErrorIcon($alert['error']);
			}

			if ($show_header) {
				$table->addRow(
					(new CRow(
						(new CCol(
							new CTag('h4', true, $recovery ? _('Recovery') : _('Problem'))
						))->setColSpan($table->getNumCols())
					))->addClass(ZBX_STYLE_HOVER_NOBG)
				);
				$show_header = false;
			}

			$table->addRow([
				$alert['esc_step'],
				zbx_date2str(DATE_TIME_FORMAT_SECONDS, $alert['clock']),
				$user,
				$message,
				$status,
				makeInformationList($info_icons)
			]);

			if (++$popup_rows == ZBX_WIDGET_ROWS) {
				break 2;
			}
		}
		$recovery = false;
	}

	$total = count($alerts) + count($r_alerts);

	return [
		$table,
		($total > ZBX_WIDGET_ROWS)
			? (new CDiv(
				(new CDiv(
					(new CDiv(_s('Displaying %1$s of %2$s found', ZBX_WIDGET_ROWS, $total)))
						->addClass(ZBX_STYLE_TABLE_STATS)
				))->addClass(ZBX_STYLE_PAGING_BTN_CONTAINER)
			))->addClass(ZBX_STYLE_TABLE_PAGING)
			: null
	];
}

/**
 * Function returns default action options for makeEventsActionsTable, used to generate action icons and tables in
 * problem and event lists.
 */
function getDefaultActionOptions() {
	return [[
		'key' => 'messages',
		'operations' => ZBX_PROBLEM_UPDATE_MESSAGE,
		'columns' => ['time', 'user', 'action', 'message'],
		'message_max_length' => 30,
		'style' => 'CTableInfo'
	], [
		'key' => 'severity_changes',
		'operations' => ZBX_PROBLEM_UPDATE_SEVERITY,
		'columns' => ['time', 'user', 'severity_changes'],
		'style' => 'CTableInfo'
	], [
		'key' => 'action_list',
		'operations' => ZBX_PROBLEM_UPDATE_CLOSE | ZBX_PROBLEM_UPDATE_ACKNOWLEDGE |
			ZBX_PROBLEM_UPDATE_MESSAGE | ZBX_PROBLEM_UPDATE_SEVERITY,
		'columns' => ['time', 'user_recipient', 'action', 'message', 'status', 'info'],
		'message_max_length' => 30,
		'actions' => true,
		'style' => 'CTableInfo',
		'show_problem' => true
	]];
}

/**
 * Get list of event actions.
 *
 * @param array    $events
 * @param string   $events[]['eventid']
 * @param string   $events[]['r_eventid']      Recovery event ID (optional).
 * @param string   $events[]['acknowledges']   Event update operations.
 * @param array    $options
 * @param int      $options[]['key']           Action list name, just to identify it when asking multiple tables.
 * @param int      $options[]['operations']    Flag of included manual actions.
 * @param boolean  $options[]['actions']       Contains automatic operations.
 * @param boolean  $options[]['columns']       Table columns.
 * @param boolean  $options[]['show_problem']  Include the problem causing and recovery events.
 * @param string   $options[]['style']         Table style. Possible options: CTableInfo or CTable (default).
 * @param bool     $html                       If true, display action status with hint box in HTML format.
 *
 * @return array
 */
function makeEventsActionsTables(array $events, array $options, $html = true) {
	if (!$events) {
		return [];
	}

	$data = makeEventsActionsData($events, $options);
	$mediatypes = $data['mediatypes'];
	$users = $data['users'];
	$return = [];

	// List of possible table header column names. Used for each given key in $options[]['columns'].
	$header_labels = [
		'step' => _('Step'),
		'time' => _('Time'),
		'user' => _('User'),
		'user_action' => _('User action'),
		'user_recipient' => _('User/Recipient'),
		'message' => _('Message'),
		'message_command' => _('Message/Command'),
		'severity_changes' => _('Severity changes'),
		'action' => _('Action'),
		'status' => _('Status'),
		'info' => _('Info')
	];

	foreach ($users as &$user) {
		$user = getUserFullname($user);
	}
	unset($user);

	foreach ($events as $event) {
		$actions = $data['events'][$event['eventid']];

		foreach ($options as $opt) {
			$table_header = [];
			foreach ($opt['columns'] as $col) {
				$table_header[] = $header_labels[$col];
			}

			if ($html) {
				$table = (array_key_exists('style', $opt) && $opt['style'] === 'CTableInfo')
					? (new CTableInfo())->setHeader($table_header)
					: (new CTable())->setHeader($table_header);
			}
			else {
				$table = [];
			}


			$actions_count = 0;
			$uncomplete = false;
			$fail = false;

			if (array_key_exists($opt['key'], $actions)) {
				$message_max_length = array_key_exists('message_max_length', $opt) ? $opt['message_max_length'] : null;

				foreach ($actions[$opt['key']] as $action) {
					/**
					 * There can be 3 types of records: manually performed actions (acknowledges), automatically made
					 * actions (alerts) and problem/recovery events.
					 *
					 * Only manual actions and alerts are counted to be displayed as tiny numbers over icon.
					 */
					$is_manual = array_key_exists('user', $action);
					$is_alert = array_key_exists('alerttype', $action);
					$row = [];

					if ($is_manual || $is_alert) {
						$actions_count++;
					}

					if ($is_alert) {
						if ($action['status'] == ALERT_STATUS_NEW || $action['status'] == ALERT_STATUS_NOT_SENT) {
							if ($action['alerttype'] == ALERT_TYPE_MESSAGE) {
								$action['retries_left']
									= $mediatypes[$action['mediatypeid']]['maxattempts'] - $action['retries'];
							}

							$uncomplete = true;
						}
						elseif ($action['status'] == ALERT_STATUS_FAILED) {
							$fail = true;
						}
					}

					foreach ($opt['columns'] as $col) {
						switch ($col) {
							case 'step':
								$row[] = $is_alert ? $action['esc_step'] : '';
								break;

							case 'time':
								$row[] = zbx_date2str(DATE_TIME_FORMAT_SECONDS, $action['time']);
								break;

							case 'user_recipient':
							case 'user':
								if ($is_manual) {
									$row[] = array_key_exists($action['user'], $users)
										? $users[$action['user']]
										: _('Inaccessible user');
								}
								elseif ($is_alert && $action['alerttype'] == ALERT_TYPE_MESSAGE) {
									$row[] = array_key_exists($action['userid'], $users)
										? $users[$action['userid']]
										: _('Inaccessible user');
								}
								else {
									$row[] = '';
								}
								break;

							case 'severity_changes':
								if ($is_manual && array_key_exists('old_severity', $action)) {
									$row[] = $action['old_severity'].SPACE.'&rArr;'.SPACE.$action['new_severity'];
								}
								else {
									$row[] = '';
								}
								break;

							case 'message_command':
							case 'message':
								if ($is_manual) {
									if ($message_max_length) {
										$row[] = (strlen($action['message']) > $message_max_length)
											? mb_substr($action['message'], 0, $message_max_length) . '...'
											: $action['message'];
									}
									else {
										$row[] = $action['message'];
									}
								}
								elseif ($is_alert && $action['alerttype'] == ALERT_TYPE_MESSAGE) {
									$row[] = array_key_exists($action['mediatypeid'], $mediatypes)
										? $mediatypes[$action['mediatypeid']]['description']
										: '';
								}
								elseif ($is_alert && $action['alerttype'] == ALERT_TYPE_COMMAND) {
									$row[] = $action['message'];
								}
								else {
									$row[] = '';
								}
								break;

							case 'user_action':
							case 'action':
								if (!$is_alert && $action['actions']) {
									$action_icons = [];
									foreach ($action['actions'] as $act_icon) {
										if ($html) {
											$action_icons[] = makeActionIcon($act_icon);
										}
										else {
											switch ($act_icon['icon']) {
												case ZBX_STYLE_ACTION_ICON_CLOSE:
													$action_icons[] = _('Close problem');
													break;
												case ZBX_STYLE_ACTION_ICON_ACK:
													$action_icons[] = _('Acknowledged');
													break;
												case ZBX_STYLE_ACTION_ICON_MSG:
													$action_icons[] = _('Messages');
													break;
												case ZBX_PROBLEM_UPDATE_SEVERITY:
													$action_icons[] = _('Changed severity');
													break;
											}
										}
									}

									$row[] = $html
										? (new CCol($action_icons))->addClass(ZBX_STYLE_NOWRAP)
										: implode(', ', $action_icons);
								}
								elseif ($is_alert && $action['alerttype'] == ALERT_TYPE_COMMAND) {
									$row[] = $html
										? makeActionIcon(['icon' => ZBX_STYLE_ACTION_COMMAND])
										: _('Remote command');
								}
								elseif ($is_alert && $action['alerttype'] == ALERT_TYPE_MESSAGE) {
									$row[] = $html
										? makeActionIcon(['icon' => ZBX_STYLE_ACTION_MESSAGE])
										: _('Messages');
								}
								elseif ($is_alert && $action['alerttype'] == ALERT_TYPE_COMMAND) {
									$row[] = $html
										? makeActionIcon(ZBX_STYLE_ACTION_COMMAND)
										: _('Messages');
								}
								else {
									$row[] = '';
								}
								break;

							case 'status':
								if ($is_alert) {
									switch ($action['status']) {
										case ALERT_STATUS_SENT:
											$status_label = $action['alerttype'] == ALERT_TYPE_COMMAND
												? _('Executed')
												: _('Sent');
											$status_color = ZBX_STYLE_GREEN;
											break;

										case ALERT_STATUS_NEW:
										case ALERT_STATUS_NOT_SENT:
											$status_label = _('In progress');
											$status_color = ZBX_STYLE_YELLOW;
											break;

										default:
											$status_label = _('Failed');
											$status_color = ZBX_STYLE_RED;
											break;
									}

									$row[] = $html
										? (new CSpan($status_label))->addClass($status_color)
										: $status_label;
								}
								else {
									$row[] = '';
								}
								break;

							case 'info':
								if ($is_alert) {
									$info_icons = [];
									if ($action['error'] !== ''
											&& !($action['status'] == ALERT_STATUS_NEW
												|| $action['status'] == ALERT_STATUS_NOT_SENT)) {
										$info_icons[] = $html
											? makeErrorIcon($action['error'])
											: $action['error'];
									}
									elseif (array_key_exists('retries_left', $action)) {
										$msg = _xn(_('%1$s retry left'), _('%1$s retries left'),
											$action['retries_left'], '', $action['retries_left']
										);
										$info_icons[] = $html ? makeWarningIcon($msg) : $msg;
									}

									$row[] = $html ? makeInformationList($info_icons) : '';
								}
								else {
									$row[] = '';
								}
								break;

							default:
								$row[] = '';
								break;
						}
					}

					$html ? $table->addRow($row) : $table[] = $row;
				}
			}

			$return[$event['eventid']][$opt['key']]['table'] = $table;
			$return[$event['eventid']][$opt['key']]['count'] = $actions_count;
			$return[$event['eventid']][$opt['key']]['has_fail_action'] = $fail;
			$return[$event['eventid']][$opt['key']]['has_uncomplete_action'] = $uncomplete;
		}
	}

	return $return;
}

/**
 * Function prepares data for makeEventsActionsTable.
 *
 * @return array
 */
function makeEventsActionsData(array $events, array $options) {
	$config = select_config();
	$severities = [];
	$mediatypeids = [];
	$return = [];
	$alerts = [];
	$userids = [];

	for ($severity = TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity < TRIGGER_SEVERITY_COUNT; $severity++) {
		$severities[$severity] = getSeverityName($severity, $config);
	}

	// Select all eventids and objectids.
	$eventids = [];
	$objectids = [];
	$recovery_eventids = [];
	foreach ($events as $event) {
		$return[$event['eventid']] = [];
		$objectids[$event['objectid']] = true;

		$eventids[$event['eventid']] = true;
		if (array_key_exists('r_eventid', $event) && $event['r_eventid'] != 0) {
			$recovery_eventids[$event['r_eventid']] = true;
		}
	}

	// Find what's requested.
	$request_alerts = false;
	$show_problem = false;
	foreach ($options as $opt) {
		if (array_key_exists('actions', $opt) && $opt['actions'] === true) {
			$request_alerts = true;
		}
		if (array_key_exists('show_problem', $opt) && $opt['show_problem'] === true) {
			$show_problem = true;
		}
	}

	if ($show_problem) {
		$r_events = API::Event()->get([
			'output' => ['clock'],
			'eventids' => array_keys($recovery_eventids),
			'preservekeys' => true
		]);
	}

	// Get automatic actions.
	if ($request_alerts) {
		// Get alerts.
		$db_alerts = API::Alert()->get([
			'output' => ['alerttype','clock','error','esc_step','eventid','mediatypeid','message','p_eventid','retries',
				'status','userid'],
			'eventids' => array_keys($eventids + $recovery_eventids),
			'filter' => ['alerttype' => [ALERT_TYPE_MESSAGE, ALERT_TYPE_COMMAND], 'acknowledgeid' => 0],
			'sortorder' => ['alertid' => ZBX_SORT_DOWN]
		]);

		foreach ($db_alerts as $db_alert) {
			$alert = [
				'esc_step' => $db_alert['esc_step'],
				'time' => $db_alert['clock'],
				'status' => $db_alert['status'],
				'alerttype' => $db_alert['alerttype'],
				'error' => $db_alert['error'],
				'p_eventid' => $db_alert['p_eventid'],
				'retries' => $db_alert['retries']
			];

			if ($alert['alerttype'] == ALERT_TYPE_MESSAGE) {
				$alert['mediatypeid'] = $db_alert['mediatypeid'];
				$alert['userid'] = $db_alert['userid'];

				if ($alert['mediatypeid'] != 0) {
					$mediatypeids[$db_alert['mediatypeid']] = true;
				}

				if ($alert['userid'] != 0) {
					$userids[$db_alert['userid']] = true;
				}
			}
			elseif ($alert['alerttype'] == ALERT_TYPE_COMMAND) {
				$alert['message'] = $db_alert['message'];
			}

			$alerts[$db_alert['eventid']][$db_alert['p_eventid']][] = $alert;
		}
	}

	// Create array of automatica and manually performed actions combined.
	foreach ($events as $event) {
		foreach ($options as $opt) {
			$return[$event['eventid']][$opt['key']] = [];

			// Check what automatic actions should be included.
			if (array_key_exists('actions', $opt) && $opt['actions']) {
				$return[$event['eventid']][$opt['key']] = array_key_exists($event['eventid'], $alerts)
					? $alerts[$event['eventid']][0]
					: [];

				if (array_key_exists('r_eventid', $event) && $event['r_eventid'] != 0
						&& array_key_exists($event['r_eventid'], $alerts)
						&& array_key_exists($event['eventid'], $alerts[$event['r_eventid']])) {
					$return[$event['eventid']][$opt['key']] = array_merge($return[$event['eventid']][$opt['key']],
						$alerts[$event['r_eventid']][$event['eventid']]
					);
				}
			}

			// Add row for problem generation and recovery events.
			if (array_key_exists('show_problem', $opt) && $opt['show_problem'] === true) {
				$return[$event['eventid']][$opt['key']][] = [
					'time' => $event['clock'],
					'actions' => [
						['icon' => ZBX_STYLE_PROBLEM_GENERATED]
					]
				];

				if (array_key_exists('r_eventid', $event) && array_key_exists($event['r_eventid'], $r_events)) {
					$return[$event['eventid']][$opt['key']][] = [
						'time' => $r_events[$event['r_eventid']]['clock'],
						'actions' => [
							['icon' => ZBX_STYLE_PROBLEM_RECOVERY]
						]
					];
				}
			}

			// Check what manual operations should be included.
			if ($opt['operations'] != ZBX_PROBLEM_UPDATE_NONE && $event['acknowledges']) {
				foreach ($event['acknowledges'] as $ack) {
					$userids[$ack['userid']] = true;

					$row = [
						'step' => null,
						'time' => $ack['clock'],
						'user' => $ack['userid'],
						'actions' => [],
						'message' => '',
						'status' => null,
						'info' => null
					];

					if (($opt['operations'] & ZBX_PROBLEM_UPDATE_CLOSE) == ZBX_PROBLEM_UPDATE_CLOSE
							&& ($ack['action'] & ZBX_PROBLEM_UPDATE_CLOSE) == ZBX_PROBLEM_UPDATE_CLOSE) {
						$row['actions'][] = ['icon' => ZBX_STYLE_ACTION_ICON_CLOSE];
					}

					if (($opt['operations'] & ZBX_PROBLEM_UPDATE_ACKNOWLEDGE) == ZBX_PROBLEM_UPDATE_ACKNOWLEDGE
							&& ($ack['action'] & ZBX_PROBLEM_UPDATE_ACKNOWLEDGE) == ZBX_PROBLEM_UPDATE_ACKNOWLEDGE) {
						$row['actions'][] = ['icon' => ZBX_STYLE_ACTION_ICON_ACK];
					}

					if (($opt['operations'] & ZBX_PROBLEM_UPDATE_MESSAGE) == ZBX_PROBLEM_UPDATE_MESSAGE
							&& ($ack['action'] & ZBX_PROBLEM_UPDATE_MESSAGE) == ZBX_PROBLEM_UPDATE_MESSAGE) {
						$row['actions'][] = ['icon' => ZBX_STYLE_ACTION_ICON_MSG];
						$row['message'] = $ack['message'];
					}

					if (($opt['operations'] & ZBX_PROBLEM_UPDATE_SEVERITY) == ZBX_PROBLEM_UPDATE_SEVERITY
							&& ($ack['action'] & ZBX_PROBLEM_UPDATE_SEVERITY) == ZBX_PROBLEM_UPDATE_SEVERITY) {
						$row['old_severity'] = $severities[$ack['old_severity']];
						$row['new_severity'] = $severities[$ack['new_severity']];

						$message = $row['old_severity'].SPACE.'&rArr;'.SPACE.$row['new_severity'];

						$action_type = ($ack['new_severity'] > $ack['old_severity'])
							? ZBX_STYLE_ACTION_ICON_SEV_UP
							: ZBX_STYLE_ACTION_ICON_SEV_DOWN;

						$row['actions'][] = ['icon' => $action_type, 'hint' => $message];
					}

					if ($row['actions']) {
						$return[$event['eventid']][$opt['key']][] = $row;
					}
				}
			}

			CArrayHelper::sort($return[$event['eventid']][$opt['key']],
				[['field' => 'time', 'order' => ZBX_SORT_DOWN]]
			);
		}
	}

	$users = $userids
		? API::User()->get([
			'output' => ['alias', 'name', 'surname'],
			'userids' => array_keys($userids),
			'preservekeys' => true
		])
		: [];

	$mediatypes = $mediatypeids
		? API::Mediatype()->get([
			'output' => ['description', 'maxattempts'],
			'mediatypeids' => array_keys($mediatypeids),
			'preservekeys' => true
		])
		: [];

	return [
		'events' => $return,
		'mediatypes' => $mediatypes,
		'users' => $users
	];
}

/**
 * Get list of event actions.
 *
 * @param array  $problems
 * @param string $problems[]['eventid']
 * @param string $problems[]['r_eventid']  Recovery event ID (optional).
 * @param bool   $display_recovery_alerts  Include recovery events.
 * @param bool   $html                     If true, display action status with hint box in HTML format.
 *
 * @return array
 */
function makeEventsActions(array $problems, $display_recovery_alerts = false, $html = true) {
	if (!$problems) {
		return [];
	}

	$eventids = [];
	foreach ($problems as $problem) {
		$eventids[$problem['eventid']] = true;
		if (array_key_exists('r_eventid', $problem) && $problem['r_eventid'] != 0) {
			$eventids[$problem['r_eventid']] = true;
		}
	}

	$db_alerts = API::Alert()->get([
		'output' => ['eventid', 'p_eventid', 'mediatypeid', 'userid', 'esc_step', 'clock', 'status', 'alerttype',
			'error'
		],
		'eventids' => array_keys($eventids),
		'filter' => ['alerttype' => [ALERT_TYPE_MESSAGE, ALERT_TYPE_COMMAND], 'acknowledgeid' => 0],
		'sortorder' => ['alertid' => ZBX_SORT_DOWN]
	]);

	$alerts = [];
	$userids = [];
	$users = [];
	$mediatypeids = [];
	$mediatypes = [];

	foreach ($db_alerts as $db_alert) {
		$alert = [
			'esc_step' => $db_alert['esc_step'],
			'clock' => $db_alert['clock'],
			'status' => $db_alert['status'],
			'alerttype' => $db_alert['alerttype'],
			'error' => $db_alert['error'],
			'p_eventid' => $db_alert['p_eventid']
		];

		if ($alert['alerttype'] == ALERT_TYPE_MESSAGE) {
			$alert['mediatypeid'] = $db_alert['mediatypeid'];
			$alert['userid'] = $db_alert['userid'];

			if ($alert['mediatypeid'] != 0) {
				$mediatypeids[$db_alert['mediatypeid']] = true;
			}

			if ($alert['userid'] != 0) {
				$userids[$db_alert['userid']] = true;
			}
		}

		$alerts[$db_alert['eventid']][$db_alert['p_eventid']][] = $alert;
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

	foreach ($problems as $index => $problem) {
		$event_alerts = array_key_exists($problem['eventid'], $alerts) ? $alerts[$problem['eventid']][0] : [];
		$r_event_alerts = [];
		if (array_key_exists('r_eventid', $problem) && $problem['r_eventid'] != 0
				&& array_key_exists($problem['r_eventid'], $alerts)
				&& array_key_exists($problem['eventid'], $alerts[$problem['r_eventid']])) {
			$r_event_alerts = $alerts[$problem['r_eventid']][$problem['eventid']];
		}

		if ($event_alerts || $r_event_alerts) {
			$status = ALERT_STATUS_SENT;
			foreach ([$event_alerts, $r_event_alerts] as $alerts_data) {
				foreach ($alerts_data as $alert) {
					if ($alert['status'] == ALERT_STATUS_NOT_SENT || $alert['status'] == ALERT_STATUS_NEW) {
						$status = ALERT_STATUS_NOT_SENT;
					}
					elseif ($alert['status'] == ALERT_STATUS_FAILED && $status != ALERT_STATUS_NOT_SENT) {
						$status = ALERT_STATUS_FAILED;
					}
				}
			}

			switch ($status) {
				case ALERT_STATUS_SENT:
					$status_str = $html ? (new CLinkAction(_('Done')))->addClass(ZBX_STYLE_GREEN) : _('Done');
					break;

				case ALERT_STATUS_NOT_SENT:
					$status_str = $html
						? (new CLinkAction(_('In progress')))->addClass(ZBX_STYLE_YELLOW)
						: _('In progress');
					break;

				default:
					$status_str = $html ? (new CLinkAction(_('Failures')))->addClass(ZBX_STYLE_RED) : _('Failures');
			}

			if ($html) {
				$problems[$index] = [
					$status_str
						->setHint(
							makeActionHints($event_alerts, $r_event_alerts, $mediatypes, $users, $display_recovery_alerts)
						),
					CViewHelper::showNum(count($event_alerts) + count($r_event_alerts))
				];
			}
			else {
				$problems[$index] = $status_str;
			}
		}
		else {
			unset($problems[$index]);
		}
	}

	return $problems;
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
