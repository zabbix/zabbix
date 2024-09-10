<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


function condition_operator2str($operator = null) {
	$operators = [
		CONDITION_OPERATOR_EQUAL  => _('equals'),
		CONDITION_OPERATOR_NOT_EQUAL  => _('does not equal'),
		CONDITION_OPERATOR_LIKE  => _('contains'),
		CONDITION_OPERATOR_NOT_LIKE  => _('does not contain'),
		CONDITION_OPERATOR_IN => _('in'),
		CONDITION_OPERATOR_MORE_EQUAL => _('is greater than or equals'),
		CONDITION_OPERATOR_LESS_EQUAL => _('is less than or equals'),
		CONDITION_OPERATOR_NOT_IN => _('not in'),
		CONDITION_OPERATOR_YES => _('Yes'),
		CONDITION_OPERATOR_NO => _('No'),
		CONDITION_OPERATOR_REGEXP => _('matches'),
		CONDITION_OPERATOR_NOT_REGEXP => _('does not match')
	];

	return $operator !== null
		? $operators[$operator]
		: $operators;
}

function condition_type2str($type = null) {
	$types = [
		ZBX_CONDITION_TYPE_SUPPRESSED => _('Problem is suppressed'),
		ZBX_CONDITION_TYPE_EVENT_NAME => _('Event name'),
		ZBX_CONDITION_TYPE_TRIGGER_SEVERITY => _('Trigger severity'),
		ZBX_CONDITION_TYPE_TRIGGER => _('Trigger'),
		ZBX_CONDITION_TYPE_HOST_NAME => _('Host name'),
		ZBX_CONDITION_TYPE_HOST_GROUP => _('Host group'),
		ZBX_CONDITION_TYPE_TEMPLATE => _('Template'),
		ZBX_CONDITION_TYPE_HOST => _('Host'),
		ZBX_CONDITION_TYPE_TIME_PERIOD => _('Time period'),
		ZBX_CONDITION_TYPE_DRULE => _('Discovery rule'),
		ZBX_CONDITION_TYPE_DCHECK => _('Discovery check'),
		ZBX_CONDITION_TYPE_DOBJECT => _('Discovery object'),
		ZBX_CONDITION_TYPE_DHOST_IP => _('Host IP'),
		ZBX_CONDITION_TYPE_DSERVICE_TYPE => _('Service type'),
		ZBX_CONDITION_TYPE_DSERVICE_PORT => _('Service port'),
		ZBX_CONDITION_TYPE_DSTATUS => _('Discovery status'),
		ZBX_CONDITION_TYPE_DUPTIME => _('Uptime/Downtime'),
		ZBX_CONDITION_TYPE_DVALUE => _('Received value'),
		ZBX_CONDITION_TYPE_EVENT_ACKNOWLEDGED => _('Event acknowledged'),
		ZBX_CONDITION_TYPE_PROXY => _('Proxy'),
		ZBX_CONDITION_TYPE_EVENT_TYPE => _('Event type'),
		ZBX_CONDITION_TYPE_HOST_METADATA => _('Host metadata'),
		ZBX_CONDITION_TYPE_EVENT_TAG => _('Tag name'),
		ZBX_CONDITION_TYPE_EVENT_TAG_VALUE => _('Tag value'),
		ZBX_CONDITION_TYPE_SERVICE => _('Service'),
		ZBX_CONDITION_TYPE_SERVICE_NAME => _('Service name')
	];

	return $type !== null
		? $types[$type]
		: $types;
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
 * For action condition types such as: host groups, hosts, templates, triggers, discovery rules, discovery checks,
 * proxies and services, action condition values contain IDs. All unique IDs are first collected and then queried.
 * For other action condition types values are returned as they are or converted using simple string conversion
 * functions according to action condition type.
 *
 * @param array $actions                         Array of actions.
 *        array $action['filter']                Array containing arrays of action conditions and other data.
 *        array $action['filter']['conditions']  Array of action conditions.
 *
 * @return array  Returns an array of actions condition string values.
 */
function actionConditionValueToString(array $actions): array {
	$result = [];

	$groupids = [];
	$triggerids = [];
	$hostids = [];
	$templateids = [];
	$proxyids = [];
	$druleids = [];
	$dcheckids = [];
	$serviceids = [];

	foreach ($actions as $i => $action) {
		$result[$i] = [];

		foreach ($action['filter']['conditions'] as $j => $condition) {
			// Unknown types and all the default values for other types are 'Unknown'.
			$result[$i][$j] = _('Unknown');

			switch ($condition['conditiontype']) {
				case ZBX_CONDITION_TYPE_HOST_GROUP:
					if ($condition['value'] != 0) {
						$groupids[$condition['value']] = $condition['value'];
					}
					else {
						$result[$i][$j] = _('Deleted host group');
					}
					break;

				case ZBX_CONDITION_TYPE_TRIGGER:
					if ($condition['value'] != 0) {
						$triggerids[$condition['value']] = $condition['value'];
					}
					else {
						$result[$i][$j] = _('Deleted trigger');
					}
					break;

				case ZBX_CONDITION_TYPE_HOST:
					if ($condition['value'] != 0) {
						$hostids[$condition['value']] = $condition['value'];
					}
					else {
						$result[$i][$j] = _('Deleted host');
					}
					break;

				case ZBX_CONDITION_TYPE_TEMPLATE:
					$templateids[$condition['value']] = $condition['value'];
					break;

				case ZBX_CONDITION_TYPE_PROXY:
					$proxyids[$condition['value']] = $condition['value'];
					break;

				case ZBX_CONDITION_TYPE_SERVICE:
					$serviceids[$condition['value']] = $condition['value'];
					break;

				// return values as is for following condition types
				case ZBX_CONDITION_TYPE_EVENT_NAME:
				case ZBX_CONDITION_TYPE_HOST_METADATA:
				case ZBX_CONDITION_TYPE_HOST_NAME:
				case ZBX_CONDITION_TYPE_TIME_PERIOD:
				case ZBX_CONDITION_TYPE_DHOST_IP:
				case ZBX_CONDITION_TYPE_DSERVICE_PORT:
				case ZBX_CONDITION_TYPE_DUPTIME:
				case ZBX_CONDITION_TYPE_DVALUE:
				case ZBX_CONDITION_TYPE_EVENT_TAG:
				case ZBX_CONDITION_TYPE_EVENT_TAG_VALUE:
				case ZBX_CONDITION_TYPE_SERVICE_NAME:
					$result[$i][$j] = $condition['value'];
					break;

				case ZBX_CONDITION_TYPE_EVENT_ACKNOWLEDGED:
					$result[$i][$j] = $condition['value'] ? _('Ack') : _('Not Ack');
					break;

				case ZBX_CONDITION_TYPE_TRIGGER_SEVERITY:
					$result[$i][$j] = CSeverityHelper::getName((int) $condition['value']);
					break;

				case ZBX_CONDITION_TYPE_DRULE:
					$druleids[$condition['value']] = $condition['value'];
					break;

				case ZBX_CONDITION_TYPE_DCHECK:
					$dcheckids[$condition['value']] = $condition['value'];
					break;

				case ZBX_CONDITION_TYPE_DOBJECT:
					$result[$i][$j] = discovery_object2str($condition['value']);
					break;

				case ZBX_CONDITION_TYPE_DSERVICE_TYPE:
					$result[$i][$j] = discovery_check_type2str($condition['value']);
					break;

				case ZBX_CONDITION_TYPE_DSTATUS:
					$result[$i][$j] = discovery_object_status2str($condition['value']);
					break;

				case ZBX_CONDITION_TYPE_EVENT_TYPE:
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
	$drules = [];
	$dchecks = [];
	$services = [];

	if ($groupids) {
		$groups = API::HostGroup()->get([
			'output' => ['name'],
			'groupids' => $groupids,
			'preservekeys' => true
		]);
	}

	if ($triggerids) {
		$triggers = API::Trigger()->get([
			'output' => ['description'],
			'triggerids' => $triggerids,
			'expandDescription' => true,
			'selectHosts' => ['name'],
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

	if ($templateids) {
		$templates = API::Template()->get([
			'output' => ['name'],
			'templateids' => $templateids,
			'preservekeys' => true
		]);
	}

	if ($proxyids) {
		$proxies = API::Proxy()->get([
			'output' => ['name'],
			'proxyids' => $proxyids,
			'preservekeys' => true
		]);
	}

	if ($druleids) {
		$drules = API::DRule()->get([
			'output' => ['name'],
			'druleids' => $druleids,
			'preservekeys' => true
		]);
	}

	if ($dcheckids) {
		$dchecks = API::DCheck()->get([
			'output' => ['type', 'key_', 'ports', 'allow_redirect'],
			'dcheckids' => $dcheckids,
			'selectDRules' => ['name'],
			'preservekeys' => true
		]);
	}

	if ($serviceids) {
		$services = API::Service()->get([
			'output' => ['name'],
			'serviceids' => $serviceids,
			'preservekeys' => true
		]);
	}

	if ($groups || $triggers || $hosts || $templates || $proxies || $drules || $dchecks || $services) {
		foreach ($actions as $i => $action) {
			foreach ($action['filter']['conditions'] as $j => $condition) {
				$id = $condition['value'];

				switch ($condition['conditiontype']) {
					case ZBX_CONDITION_TYPE_HOST_GROUP:
						if (array_key_exists($id, $groups)) {
							$result[$i][$j] = $groups[$id]['name'];
						}
						break;

					case ZBX_CONDITION_TYPE_TRIGGER:
						if (array_key_exists($id, $triggers)) {
							$host = reset($triggers[$id]['hosts']);
							$result[$i][$j] = $host['name'].NAME_DELIMITER.$triggers[$id]['description'];
						}
						break;

					case ZBX_CONDITION_TYPE_HOST:
						if (array_key_exists($id, $hosts)) {
							$result[$i][$j] = $hosts[$id]['name'];
						}
						break;

					case ZBX_CONDITION_TYPE_TEMPLATE:
						if (array_key_exists($id, $templates)) {
							$result[$i][$j] = $templates[$id]['name'];
						}
						break;

					case ZBX_CONDITION_TYPE_PROXY:
						if (array_key_exists($id, $proxies)) {
							$result[$i][$j] = $proxies[$id]['name'];
						}
						break;

					case ZBX_CONDITION_TYPE_DRULE:
						if (array_key_exists($id, $drules)) {
							$result[$i][$j] = $drules[$id]['name'];
						}
						break;

					case ZBX_CONDITION_TYPE_DCHECK:
						if (array_key_exists($id, $dchecks)) {
							$drule = reset($dchecks[$id]['drules']);
							$type = $dchecks[$id]['type'];
							$key_ = $dchecks[$id]['key_'];
							$ports = $dchecks[$id]['ports'];
							$allow_redirect = $dchecks[$id]['allow_redirect'];

							$dcheck = discovery_check2str($type, $key_, $ports, $allow_redirect);

							$result[$i][$j] = $drule['name'].NAME_DELIMITER.$dcheck;
						}
						break;

					case ZBX_CONDITION_TYPE_SERVICE:
						if (array_key_exists($id, $services)) {
							$result[$i][$j] = $services[$id]['name'];
						}
						break;
				}
			}
		}
	}

	return $result;
}

/**
 * Returns the HTML representation of an action condition and action operation condition.
 *
 * @param string $condition_type
 * @param string $operator
 * @param string $value
 * @param string $value2
 *
 * @return array|string
 */
function getConditionDescription($condition_type, $operator, $value, $value2) {
	if ($condition_type == ZBX_CONDITION_TYPE_EVENT_TAG_VALUE) {
		$description = [_('Value of tag')];
		$description[] = ' ';
		$description[] = italic($value2);
		$description[] = ' ';
	}
	elseif ($condition_type == ZBX_CONDITION_TYPE_SUPPRESSED) {
		return ($operator == CONDITION_OPERATOR_YES)
			? [_('Problem is suppressed')]
			: [_('Problem is not suppressed')];
	}
	elseif ($condition_type == ZBX_CONDITION_TYPE_EVENT_ACKNOWLEDGED) {
		return $value ? _('Event is acknowledged') : _('Event is not acknowledged');
	}
	else {
		$description = [condition_type2str($condition_type)];
		$description[] = ' ';
	}

	$description[] = condition_operator2str($operator);
	$description[] = ' ';
	$description[] = italic($value);

	return $description;
}

/**
 * Gathers operation data and processes it based on operation type.
 *
 * @param array $operations  Array of operations.
 *
 * @return array  Returns an array of processed data.
 */
function getActionOperationData(array $operations): array {
	$result = [];
	$data = [];

	foreach ($operations as $operation) {
		switch ($operation['operationtype']) {
			case OPERATION_TYPE_MESSAGE:
				if ($operation['opmessage']['mediatypeid'] != 0) {
					$data['mediatypeids'][$operation['opmessage']['mediatypeid']] = true;
				}

				if (array_key_exists('opmessage_usr', $operation) && $operation['opmessage_usr']) {
					foreach ($operation['opmessage_usr'] as $users) {
						$data['userids'][$users['userid']] = true;
					}
				}

				if (array_key_exists('opmessage_grp', $operation) && $operation['opmessage_grp']) {
					foreach ($operation['opmessage_grp'] as $user_groups) {
						$data['usrgrpids'][$user_groups['usrgrpid']] = true;
					}
				}
				break;

			case OPERATION_TYPE_COMMAND:
				if (array_key_exists('opcommand_hst', $operation) && $operation['opcommand_hst']) {
					foreach ($operation['opcommand_hst'] as $host) {
						if ($host['hostid'] != 0) {
							$data['hostids'][$host['hostid']] = true;
						}
					}
				}

				if (array_key_exists('opcommand_grp', $operation) && $operation['opcommand_grp']) {
					foreach ($operation['opcommand_grp'] as $host_group) {
						$data['groupids'][$host_group['groupid']] = true;
					}
				}

				$data['scriptids'][$operation['opcommand']['scriptid']] = true;
				break;

			case OPERATION_TYPE_GROUP_ADD:
			case OPERATION_TYPE_GROUP_REMOVE:
				foreach ($operation['opgroup'] as $groupid) {
					$data['groupids'][$groupid['groupid']] = true;
				}
				break;

			case OPERATION_TYPE_TEMPLATE_ADD:
			case OPERATION_TYPE_TEMPLATE_REMOVE:
				foreach ($operation['optemplate'] as $templateid) {
					$data['templateids'][$templateid['templateid']] = true;
				}
				break;
		}
	}

	if (array_key_exists('mediatypeids', $data)) {
		$result['mediatypes'] = API::Mediatype()->get([
			'output' => ['name'],
			'mediatypeids' => array_keys($data['mediatypeids']),
			'preservekeys' => true
		]);
	}

	if (array_key_exists('userids', $data)) {
		$users = API::User()->get([
			'output' => ['userid', 'username', 'name', 'surname'],
			'userids' => array_keys($data['userids'])
		]);

		foreach ($users as $user) {
			$result['users'][$user['userid']]['name'] = getUserFullname($user);
		}
	}

	if (array_key_exists('usrgrpids', $data)) {
		$result['user_groups'] = API::UserGroup()->get([
			'output' => ['name'],
			'usrgrpids' => array_keys($data['usrgrpids']),
			'preservekeys' => true
		]);
	}

	if (array_key_exists('hostids', $data)) {
		$result['hosts'] = API::Host()->get([
			'output' => ['name'],
			'hostids' => array_keys($data['hostids']),
			'preservekeys' => true
		]);
	}

	if (array_key_exists('groupids', $data)) {
		$result['host_groups'] = API::HostGroup()->get([
			'output' => ['name'],
			'groupids' => array_keys($data['groupids']),
			'preservekeys' => true
		]);
	}

	if (array_key_exists('templateids', $data)) {
		$result['templates'] = API::Template()->get([
			'output' => ['name'],
			'templateids' => array_keys($data['templateids']),
			'preservekeys' => true
		]);
	}

	if (array_key_exists('scriptids', $data)) {
		$result['scripts'] = API::Script()->get([
			'output' => ['name'],
			'scriptids' => array_keys($data['scriptids']),
			'filter' => ['scope' => ZBX_SCRIPT_SCOPE_ACTION],
			'preservekeys' => true
		]);
	}

	return $result;
}

/**
 *  Formats the HTML representation of action operation values according to action operation type.
 *
 * @param array $operations        Array of operations.
 * @param int   $eventsource       Actions eventsource.
 * @param array $operation_values  All processed data of operation values.
 *
 * @return array  Returns an array of actions operation descriptions.
 */
function getActionOperationDescriptions(array $operations, int $eventsource, array $operation_values): array {
	$result = [];

	$mediatypes = array_key_exists('mediatypes', $operation_values) ? $operation_values['mediatypes'] : [];
	$users = array_key_exists('users', $operation_values) ? $operation_values['users'] : [];
	$user_groups = array_key_exists('user_groups', $operation_values) ? $operation_values['user_groups'] : [];
	$hosts = array_key_exists('hosts', $operation_values) ? $operation_values['hosts'] : [];
	$host_groups = array_key_exists('host_groups', $operation_values) ? $operation_values['host_groups'] : [];
	$templates = array_key_exists('templates', $operation_values) ? $operation_values['templates'] : [];
	$scripts = array_key_exists('scripts', $operation_values) ? $operation_values['scripts'] : [];

	foreach ($operations as $i => $operation) {
		switch ($operation['operationtype']) {
			case OPERATION_TYPE_MESSAGE:
				$mediatype = _('all media');
				$mediatypeid = $operation['opmessage']['mediatypeid'];

				if ($mediatypeid != 0 && array_key_exists($mediatypeid, $mediatypes)) {
					$mediatype = $mediatypes[$mediatypeid]['name'];
				}

				if (array_key_exists('opmessage_usr', $operation) && $operation['opmessage_usr']) {
					$user_names_list = [];

					foreach ($operation['opmessage_usr'] as $user) {
						if (array_key_exists($user['userid'], $users)){
							$user_names_list[] = $users[$user['userid']]['name'];
						}
					}

					order_result($user_names_list);

					$result[$i][] = bold(_('Send message to users').': ');
					$result[$i][] = [implode(', ', $user_names_list), ' ', _('via'), ' ', $mediatype];
					$result[$i][] = BR();
				}

				if (array_key_exists('opmessage_grp', $operation) && $operation['opmessage_grp']) {
					$user_groups_list = [];

					foreach ($operation['opmessage_grp'] as $user_group) {
						if (array_key_exists($user_group['usrgrpid'], $user_groups)) {
							$user_groups_list[] = $user_groups[$user_group['usrgrpid']]['name'];
						}
					}

					order_result($user_groups_list);

					$result[$i][] = bold(_('Send message to user groups').': ');
					$result[$i][] = [implode(', ', $user_groups_list), ' ', _('via'), ' ', $mediatype];
					$result[$i][] = BR();
				}
				break;

			case OPERATION_TYPE_COMMAND:
				$scriptid = $operation['opcommand']['scriptid'];

				if ($eventsource == EVENT_SOURCE_SERVICE) {
					$result[$i][] = [
						bold(_s('Run script "%1$s" on Zabbix server', $scripts[$scriptid]['name'])),
						BR()
					];

					break;
				}

				$operation += ['opcommand_hst' => [], 'opcommand_grp' => []];

				if (!$operation['opcommand_hst'] && !$operation['opcommand_grp']) {
					$result[$i][] = [
						bold(_s('Run script "%1$s" on deleted object(s)', $scripts[$scriptid]['name'])),
						BR()
					];

					break;
				}

				if ($operation['opcommand_hst']) {
					$host_list = [];

					foreach ($operation['opcommand_hst'] as $host) {
						if ($host['hostid'] == 0) {
							$result[$i][] = [
								bold(_s('Run script "%1$s" on current host', $scripts[$scriptid]['name'])),
								BR()
							];
						}
						elseif (array_key_exists($host['hostid'], $hosts)) {
							$host_list[] = $hosts[$host['hostid']]['name'];
						}
					}

					if ($host_list) {
						order_result($host_list);

						$result[$i][] = bold(
							_s('Run script "%1$s" on hosts', $scripts[$scriptid]['name']).': '
						);
						$result[$i][] = [implode(', ', $host_list), BR()];
					}
				}

				if ($operation['opcommand_grp']) {
					$host_group_list = [];

					foreach ($operation['opcommand_grp'] as $host_group) {
						if (array_key_exists($host_group['groupid'], $host_groups)) {
							$host_group_list[] = $host_groups[$host_group['groupid']]['name'];
						}
					}

					order_result($host_group_list);

					$result[$i][] = bold(
						_s('Run script "%1$s" on host groups', $scripts[$scriptid]['name']).': '
					);
					$result[$i][] = [implode(', ', $host_group_list), BR()];
				}
				break;

			case OPERATION_TYPE_HOST_ADD:
				$result[$i][] = [bold(_('Add host')), BR()];
				break;

			case OPERATION_TYPE_HOST_REMOVE:
				$result[$i][] = [bold(_('Remove host')), BR()];
				break;

			case OPERATION_TYPE_HOST_TAGS_ADD:
			case OPERATION_TYPE_HOST_TAGS_REMOVE:
				$tags = [];
				if (array_key_exists('optag', $operation) && $operation['optag']) {
					CArrayHelper::sort($operation['optag'], ['tag', 'value']);

					foreach ($operation['optag'] as $tag) {
						$value = getTagString($tag);

						if ($value !== '') {
							$tags[] = (new CSpan($value))
								->addClass(ZBX_STYLE_TAG)
								->setHint(getTagString($tag));
						}
					}

					if ($operation['operationtype'] == OPERATION_TYPE_HOST_TAGS_ADD) {
						$result[$i][] = bold(_('Add host tags').': ');
					}
					else {
						$result[$i][] = bold(_('Remove host tags').': ');
					}
				}

				$result[$i][] = [$tags, BR()];
				break;

			case OPERATION_TYPE_HOST_ENABLE:
				$result[$i][] = [bold(_('Enable host')), BR()];
				break;

			case OPERATION_TYPE_HOST_DISABLE:
				$result[$i][] = [bold(_('Disable host')), BR()];
				break;

			case OPERATION_TYPE_GROUP_ADD:
			case OPERATION_TYPE_GROUP_REMOVE:
				$host_group_list = [];

				foreach ($operation['opgroup'] as $groupid) {
					if (array_key_exists($groupid['groupid'], $host_groups)) {
						$host_group_list[] = $host_groups[$groupid['groupid']]['name'];
					}
				}

				order_result($host_group_list);

				$host_group_list = $host_group_list
					? implode(', ', $host_group_list)
					: italic(_('Deleted host group(s)'));

				if ($operation['operationtype'] == OPERATION_TYPE_GROUP_ADD) {
					$result[$i][] = bold(_('Add to host groups').': ');
				}
				else {
					$result[$i][] = bold(_('Remove from host groups').': ');
				}

				$result[$i][] = [$host_group_list, BR()];
				break;

			case OPERATION_TYPE_TEMPLATE_ADD:
			case OPERATION_TYPE_TEMPLATE_REMOVE:
				$template_list = [];

				foreach ($operation['optemplate'] as $templateid) {
					if (array_key_exists($templateid['templateid'], $templates)) {
						$template_list[] = $templates[$templateid['templateid']]['name'];
					}
				}

				order_result($template_list);

				if ($operation['operationtype'] == OPERATION_TYPE_TEMPLATE_ADD) {
					$result[$i][] = bold(_('Link templates').': ');
				}
				else {
					$result[$i][] = bold(_('Unlink templates').': ');
				}

				$result[$i][] = [implode(', ', $template_list), BR()];
				break;

			case OPERATION_TYPE_HOST_INVENTORY:
				$host_inventory_modes = getHostInventoryModes();
				$result[$i][] = bold(operation_type2str(OPERATION_TYPE_HOST_INVENTORY).': ');
				$result[$i][] = [$host_inventory_modes[$operation['opinventory']['inventory_mode']], BR()];
				break;

			case OPERATION_TYPE_RECOVERY_MESSAGE:
			case OPERATION_TYPE_UPDATE_MESSAGE:
				$result[$i][] = bold(_('Notify all involved'));
				break;
		}
	}

	return $result;
}

/**
 * Return an array of action conditions supported by the given event source.
 *
 * @param int|string $eventsource
 */
function get_conditions_by_eventsource($eventsource): array {
	$conditions[EVENT_SOURCE_TRIGGERS] = [
		ZBX_CONDITION_TYPE_EVENT_NAME,
		ZBX_CONDITION_TYPE_TRIGGER,
		ZBX_CONDITION_TYPE_TRIGGER_SEVERITY,
		ZBX_CONDITION_TYPE_HOST,
		ZBX_CONDITION_TYPE_HOST_GROUP,
		ZBX_CONDITION_TYPE_SUPPRESSED,
		ZBX_CONDITION_TYPE_EVENT_TAG,
		ZBX_CONDITION_TYPE_EVENT_TAG_VALUE,
		ZBX_CONDITION_TYPE_TEMPLATE,
		ZBX_CONDITION_TYPE_TIME_PERIOD
	];
	$conditions[EVENT_SOURCE_DISCOVERY] = [
		ZBX_CONDITION_TYPE_DHOST_IP,
		ZBX_CONDITION_TYPE_DCHECK,
		ZBX_CONDITION_TYPE_DOBJECT,
		ZBX_CONDITION_TYPE_DRULE,
		ZBX_CONDITION_TYPE_DSTATUS,
		ZBX_CONDITION_TYPE_PROXY,
		ZBX_CONDITION_TYPE_DVALUE,
		ZBX_CONDITION_TYPE_DSERVICE_PORT,
		ZBX_CONDITION_TYPE_DSERVICE_TYPE,
		ZBX_CONDITION_TYPE_DUPTIME
	];
	$conditions[EVENT_SOURCE_AUTOREGISTRATION] = [
		ZBX_CONDITION_TYPE_HOST_NAME,
		ZBX_CONDITION_TYPE_HOST_METADATA,
		ZBX_CONDITION_TYPE_PROXY
	];
	$conditions[EVENT_SOURCE_INTERNAL] = [
		ZBX_CONDITION_TYPE_EVENT_TYPE,
		ZBX_CONDITION_TYPE_HOST,
		ZBX_CONDITION_TYPE_HOST_GROUP,
		ZBX_CONDITION_TYPE_EVENT_TAG,
		ZBX_CONDITION_TYPE_EVENT_TAG_VALUE,
		ZBX_CONDITION_TYPE_TEMPLATE
	];
	$conditions[EVENT_SOURCE_SERVICE] = [
		ZBX_CONDITION_TYPE_SERVICE,
		ZBX_CONDITION_TYPE_SERVICE_NAME,
		ZBX_CONDITION_TYPE_EVENT_TAG,
		ZBX_CONDITION_TYPE_EVENT_TAG_VALUE
	];

	if (array_key_exists($eventsource, $conditions)) {
		return $conditions[$eventsource];
	}

	return $conditions[EVENT_SOURCE_TRIGGERS];
}

/**
 * Return allowed operations types.
 *
 * @param int $eventsource
 */
function getAllowedOperations($eventsource): array {
	switch ($eventsource) {
		case EVENT_SOURCE_TRIGGERS:
		case EVENT_SOURCE_SERVICE:
			return [
				ACTION_OPERATION => [
					OPERATION_TYPE_MESSAGE,
					OPERATION_TYPE_COMMAND
				],
				ACTION_RECOVERY_OPERATION => [
					OPERATION_TYPE_MESSAGE,
					OPERATION_TYPE_COMMAND,
					OPERATION_TYPE_RECOVERY_MESSAGE
				],
				ACTION_UPDATE_OPERATION => [
					OPERATION_TYPE_MESSAGE,
					OPERATION_TYPE_COMMAND,
					OPERATION_TYPE_UPDATE_MESSAGE
				]
			];

		case EVENT_SOURCE_DISCOVERY:
		case EVENT_SOURCE_AUTOREGISTRATION:
			return [
				ACTION_OPERATION => [
					OPERATION_TYPE_MESSAGE,
					OPERATION_TYPE_COMMAND,
					OPERATION_TYPE_HOST_ADD,
					OPERATION_TYPE_HOST_REMOVE,
					OPERATION_TYPE_GROUP_ADD,
					OPERATION_TYPE_GROUP_REMOVE,
					OPERATION_TYPE_TEMPLATE_ADD,
					OPERATION_TYPE_TEMPLATE_REMOVE,
					OPERATION_TYPE_HOST_TAGS_ADD,
					OPERATION_TYPE_HOST_TAGS_REMOVE,
					OPERATION_TYPE_HOST_ENABLE,
					OPERATION_TYPE_HOST_DISABLE,
					OPERATION_TYPE_HOST_INVENTORY
				]
			];

		case EVENT_SOURCE_INTERNAL:
			return [
				ACTION_OPERATION => [OPERATION_TYPE_MESSAGE],
				ACTION_RECOVERY_OPERATION => [
					OPERATION_TYPE_MESSAGE,
					OPERATION_TYPE_RECOVERY_MESSAGE
				]
			];

		default:
			return [];
	}
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
		OPERATION_TYPE_TEMPLATE_ADD => _('Link template'),
		OPERATION_TYPE_TEMPLATE_REMOVE => _('Unlink template'),
		OPERATION_TYPE_HOST_INVENTORY => _('Set host inventory mode'),
		OPERATION_TYPE_RECOVERY_MESSAGE => _('Notify all involved'),
		OPERATION_TYPE_UPDATE_MESSAGE => _('Notify all involved'),
		OPERATION_TYPE_HOST_TAGS_ADD => _('Add host tags'),
		OPERATION_TYPE_HOST_TAGS_REMOVE => _('Remove host tags')
	];

	if (is_null($type)) {
		return order_result($types);
	}
	elseif (array_key_exists($type, $types)) {
		return $types[$type];
	}
	else {
		return _('Unknown');
	}
}

function sortOperations($eventsource, &$operations): void {
	if (in_array($eventsource, [EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_INTERNAL, EVENT_SOURCE_SERVICE])) {
		$esc_step_from = [];
		$esc_step_to = [];
		$esc_period = [];
		$operationTypes = [];

		$simple_interval_parser = new CSimpleIntervalParser();

		foreach ($operations as $key => $operation) {
			$esc_step_from[$key] = $operation['esc_step_from'];
			$esc_step_to[$key] = $operation['esc_step_to'];
			/*
			 * Try to sort by "esc_period" in seconds, otherwise sort as string in case it's a macro or something
			 * invalid.
			 */
			$esc_period[$key] = ($simple_interval_parser->parse($operation['esc_period']) == CParser::PARSE_SUCCESS)
				? timeUnitToSeconds($operation['esc_period'])
				: $operation['esc_period'];

			$operationTypes[$key] = $operation['operationtype'];
		}
		array_multisort($esc_step_from, SORT_ASC, $esc_step_to, SORT_ASC, $esc_period, SORT_ASC, $operationTypes,
			SORT_ASC, $operations
		);
	}
	else {
		$order = getAllowedOperations($eventsource)[ACTION_OPERATION];

		usort($operations,
			static fn($a, $b) => array_search($a['operationtype'], $order) - array_search($b['operationtype'], $order)
		);
	}
}

/**
 * Return an array of operators supported by the given action condition.
 *
 * @param int $conditiontype
 */
function get_operators_by_conditiontype($conditiontype): array {
	switch ($conditiontype) {
		case ZBX_CONDITION_TYPE_DCHECK:
		case ZBX_CONDITION_TYPE_DHOST_IP:
		case ZBX_CONDITION_TYPE_DRULE:
		case ZBX_CONDITION_TYPE_DSERVICE_PORT:
		case ZBX_CONDITION_TYPE_DSERVICE_TYPE:
		case ZBX_CONDITION_TYPE_HOST:
		case ZBX_CONDITION_TYPE_HOST_GROUP:
		case ZBX_CONDITION_TYPE_PROXY:
		case ZBX_CONDITION_TYPE_SERVICE:
		case ZBX_CONDITION_TYPE_TEMPLATE:
		case ZBX_CONDITION_TYPE_TRIGGER:
			return [
				CONDITION_OPERATOR_EQUAL,
				CONDITION_OPERATOR_NOT_EQUAL
			];

		case ZBX_CONDITION_TYPE_SERVICE_NAME:
		case ZBX_CONDITION_TYPE_EVENT_NAME:
			return [
				CONDITION_OPERATOR_LIKE,
				CONDITION_OPERATOR_NOT_LIKE
			];

		case ZBX_CONDITION_TYPE_TRIGGER_SEVERITY:
			return [
				CONDITION_OPERATOR_EQUAL,
				CONDITION_OPERATOR_NOT_EQUAL,
				CONDITION_OPERATOR_MORE_EQUAL,
				CONDITION_OPERATOR_LESS_EQUAL
			];

		case ZBX_CONDITION_TYPE_TIME_PERIOD:
			return [
				CONDITION_OPERATOR_IN,
				CONDITION_OPERATOR_NOT_IN
			];

		case ZBX_CONDITION_TYPE_SUPPRESSED:
			return [
				CONDITION_OPERATOR_NO,
				CONDITION_OPERATOR_YES
			];

		case ZBX_CONDITION_TYPE_DOBJECT:
		case ZBX_CONDITION_TYPE_DSTATUS:
		case ZBX_CONDITION_TYPE_EVENT_ACKNOWLEDGED:
		case ZBX_CONDITION_TYPE_EVENT_TYPE:
			return [
				CONDITION_OPERATOR_EQUAL
			];

		case ZBX_CONDITION_TYPE_DUPTIME:
			return [
				CONDITION_OPERATOR_MORE_EQUAL,
				CONDITION_OPERATOR_LESS_EQUAL
			];

		case ZBX_CONDITION_TYPE_DVALUE:
			return [
				CONDITION_OPERATOR_EQUAL,
				CONDITION_OPERATOR_NOT_EQUAL,
				CONDITION_OPERATOR_MORE_EQUAL,
				CONDITION_OPERATOR_LESS_EQUAL,
				CONDITION_OPERATOR_LIKE,
				CONDITION_OPERATOR_NOT_LIKE
			];

		case ZBX_CONDITION_TYPE_HOST_METADATA:
		case ZBX_CONDITION_TYPE_HOST_NAME:
			return [
				CONDITION_OPERATOR_LIKE,
				CONDITION_OPERATOR_NOT_LIKE,
				CONDITION_OPERATOR_REGEXP,
				CONDITION_OPERATOR_NOT_REGEXP
			];

		case ZBX_CONDITION_TYPE_EVENT_TAG:
		case ZBX_CONDITION_TYPE_EVENT_TAG_VALUE:
			return [
				CONDITION_OPERATOR_EQUAL,
				CONDITION_OPERATOR_NOT_EQUAL,
				CONDITION_OPERATOR_LIKE,
				CONDITION_OPERATOR_NOT_LIKE
			];

		default:
			return [];
	}
}

function count_operations_delay($operations, $def_period): array {
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

/**
 * Get data required to create messages, severity changes, actions icon with popup with event actions.
 *
 * @param array $events    Array with event objects with acknowledges.
 * @param array $triggers  Array of triggers.
 */
function getEventsActionsIconsData(array $events, array $triggers): array {
	$suppressions = getEventsSuppressions($events);
	$messages = getEventsMessages($events);
	$severities = getEventsSeverityChanges($events, $triggers);
	$actions = getEventsAlertsOverview($events);

	return [
		'data' => [
			'suppressions' => $suppressions['data'],
			'messages' => $messages['data'],
			'severities' => $severities['data'],
			'actions' => $actions
		],
		'userids' => $messages['userids'] + $severities['userids'] + $suppressions['userids']
	];
}

/**
 * Get data, required to create suppressed problem icon with popup with event suppression data.
 *
 * @param array  $events                                        Array with event objects with acknowledges.
 *        string $events[]['eventid']                           Problem event ID.
 *        array  $events[]['acknowledges']                      Array with manual updates to problem.
 *        string $events[]['acknowledges'][]['action']          Action that was performed by problem update.
 *        string $events[]['acknowledges'][]['suppress_until']  Time until problem suppressed.
 *        string $events[]['acknowledges'][]['clock']           Time when manual suppression was made.
 *        string $events[]['acknowledges'][]['userid']          Author's userid.
 */
function getEventsSuppressions(array $events): array {
	$suppressions = [];
	$userids = [];

	// Create array of suppressions for each event.
	foreach ($events as $event) {
		$event_suppressions = [];

		foreach ($event['acknowledges'] as $ack) {
			if (($ack['action'] & ZBX_PROBLEM_UPDATE_SUPPRESS) == ZBX_PROBLEM_UPDATE_SUPPRESS) {
				$event_suppressions[] = [
					'suppress_until' => $ack['suppress_until'],
					'userid' => $ack['userid'],
					'clock' => $ack['clock']
				];

				$userids[$ack['userid']] = true;
			}
			elseif (($ack['action'] & ZBX_PROBLEM_UPDATE_UNSUPPRESS) == ZBX_PROBLEM_UPDATE_UNSUPPRESS) {
				$event_suppressions[] = [
					'userid' => $ack['userid'],
					'clock' => $ack['clock']
				];

				$userids[$ack['userid']] = true;
			}
		}

		CArrayHelper::sort($event_suppressions, [['field' => 'clock', 'order' => ZBX_SORT_DOWN]]);

		$suppressions[$event['eventid']] = [
			'suppress_until' => array_values($event_suppressions),
			'count' => count($event_suppressions)
		];
	}

	return [
		'data' => $suppressions,
		'userids' => $userids
	];
}

/**
 * Get data, required to create messages icon with popup with event messages.
 *
 * @param array  $events                                 Array with event objects with acknowledges.
 *        string $events[]['eventid']                    Problem event ID.
 *        array  $events[]['acknowledges']               Array with manual updates to problem.
 *        string $events[]['acknowledges'][]['action']   Action that was performed by problem update.
 *        string $events[]['acknowledges'][]['message']  Message text.
 *        string $events[]['acknowledges'][]['clock']    Time when message was added.
 *        string $events[]['acknowledges'][]['userid']   Author's userid.
 */
function getEventsMessages(array $events): array {
	$messages = [];
	$userids = [];

	// Create array of messages for each event
	foreach ($events as $event) {
		$event_messages = [];

		foreach ($event['acknowledges'] as $ack) {
			if (($ack['action'] & ZBX_PROBLEM_UPDATE_MESSAGE) == ZBX_PROBLEM_UPDATE_MESSAGE) {
				$event_messages[] = [
					'message' => $ack['message'],
					'userid' => $ack['userid'],
					'clock' => $ack['clock']
				];

				$userids[$ack['userid']] = true;
			}
		}

		CArrayHelper::sort($event_messages, [['field' => 'clock', 'order' => ZBX_SORT_DOWN]]);

		$messages[$event['eventid']] = [
			'messages' => array_values($event_messages),
			'count' => count($event_messages)
		];
	}

	return [
		'data' => $messages,
		'userids' => $userids
	];
}

/**
 * Get data, required to create severity changes icon with popup with event severity changes.
 *
 * @param array  $events                                      Array with event objects with acknowledges.
 *        string $events[]['eventid']                         Problem event ID.
 *        string $events[]['severity']                        Current event severity.
 *        string $events[]['objectid']                        Related trigger ID.
 *        array  $events[]['acknowledges']                    Array with manual updates to problem.
 *        string $events[]['acknowledges'][]['action']        Action that was performed by problem update.
 *        string $events[]['acknowledges'][]['clock']         Time when severity was changed.
 *        string $events[]['acknowledges'][]['old_severity']  Severity before the change.
 *        string $events[]['acknowledges'][]['new_severity']  Severity after the change.
 *        string $events[]['acknowledges'][]['userid']        Responsible user's userid.
 * @param array  $triggers                                    Related trigger data.
 *        string $triggers[]['priority']                      Severity of trigger.
 */
function getEventsSeverityChanges(array $events, array $triggers): array {
	$severities = [];
	$userids = [];

	// Create array of messages for each event.
	foreach ($events as $event) {
		$event_severities = [];

		foreach ($event['acknowledges'] as $ack) {
			if (($ack['action'] & ZBX_PROBLEM_UPDATE_SEVERITY) == ZBX_PROBLEM_UPDATE_SEVERITY) {
				$event_severities[] = [
					'old_severity' => $ack['old_severity'],
					'new_severity' => $ack['new_severity'],
					'userid' => $ack['userid'],
					'clock' => $ack['clock']
				];

				$userids[$ack['userid']] = true;
			}
		}

		CArrayHelper::sort($event_severities, [['field' => 'clock', 'order' => ZBX_SORT_DOWN]]);

		$severities[$event['eventid']] = [
			'severities' => array_values($event_severities),
			'count' => count($event_severities),
			'original_severity' => $triggers[$event['objectid']]['priority'],
			'current_severity' => $event['severity']
		];
	}

	return [
		'data' => $severities,
		'userids' => $userids
	];
}

/**
 * Get data, required to create actions icon.
 *
 * @param array  $events                 Array with event objects with acknowledges.
 *        string $events[]['eventid']    Problem event ID.
 *        string $events[]['r_eventid']  OK event ID.
 *
 * @return array  List indexed by eventid containing overview on event alerts.
 */
function getEventsAlertsOverview(array $events): array {
	$alert_eventids = [];
	$actions = [];
	$event_alert_state = [];

	foreach ($events as $event) {
		// Get alerts for event.
		$alert_eventids[$event['eventid']] = true;

		// Get alerts for related recovery events.
		if ($event['r_eventid'] != 0) {
			$alert_eventids[$event['r_eventid']] = true;
		}
	}

	if ($alert_eventids) {
		$event_alert_state = array_combine(array_keys($alert_eventids), array_fill(0, count($alert_eventids), [
			'failed_cnt' => 0,
			'in_progress_cnt' => 0,
			'total_cnt' => 0
		]));

		$alerts = API::Alert()->get([
			'groupCount' => true,
			'countOutput' => true,
			'filter' => ['status' => ALERT_STATUS_FAILED],
			'eventids' => array_keys($alert_eventids)
		]);

		foreach ($alerts as $alert) {
			$event_alert_state[$alert['eventid']]['failed_cnt'] = (int) $alert['rowscount'];
		}

		$alerts = API::Alert()->get([
			'groupCount' => true,
			'countOutput' => true,
			'filter' => ['status' => [ALERT_STATUS_NEW, ALERT_STATUS_NOT_SENT]],
			'eventids' => array_keys($alert_eventids)
		]);

		foreach ($alerts as $alert) {
			$event_alert_state[$alert['eventid']]['in_progress_cnt'] = (int) $alert['rowscount'];
		}

		$alerts = API::Alert()->get([
			'groupCount' => true,
			'countOutput' => true,
			'eventids' => array_keys($alert_eventids)
		]);

		foreach ($alerts as $alert) {
			$event_alert_state[$alert['eventid']]['total_cnt'] = (int) $alert['rowscount'];
		}
	}

	// Create array of actions for each event.
	foreach ($events as $event) {
		$event_actions = $event_alert_state[$event['eventid']];
		if ($event['r_eventid']) {
			$r_event_actions = $event_alert_state[$event['r_eventid']];
			$event_actions['failed_cnt'] += $r_event_actions['failed_cnt'];
			$event_actions['total_cnt'] += $r_event_actions['total_cnt'];
			$event_actions['in_progress_cnt'] += $r_event_actions['in_progress_cnt'];
		}

		$actions[$event['eventid']] = [
			'count' => $event_actions['total_cnt'] + count($event['acknowledges']),
			'has_uncomplete_action' => (bool) $event_actions['in_progress_cnt'],
			'has_failed_action' => (bool) $event_actions['failed_cnt']
		];
	}

	return $actions;
}

/**
 * Get data, required to create table with all (automatic and manual) actions for Event details page.
 *
 * @param array  $event               Event object with acknowledges.
 *        string $event['eventid']    Problem event ID.
 *        string $event['r_eventid']  OK event ID.
 */
function getEventDetailsActions(array $event): array {
	$r_events = [];

	// Select eventids for alert retrieval.
	$alert_eventids = [$event['eventid']];

	if ($event['r_eventid'] != 0) {
		$alert_eventids[] = $event['r_eventid'];

		$r_events = API::Event()->get([
			'output' => ['clock'],
			'eventids' => $event['r_eventid'],
			'preservekeys' => true
		]);
	}

	$search_limit = CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT);

	// Get automatic actions (alerts).
	$alerts = API::Alert()->get([
		'output' => ['alerttype', 'clock', 'error', 'eventid', 'esc_step', 'mediatypeid', 'message', 'retries',
			'sendto', 'status', 'subject', 'userid', 'p_eventid', 'acknowledgeid'
		],
		'eventids' => $alert_eventids,
		'limit' => $search_limit
	]);

	$actions = getSingleEventActions($event, $r_events, $alerts);

	return [
		'actions' => $actions['actions'],
		'mediatypeids' => $actions['mediatypeids'],
		'userids' => $actions['userids'],
		'count' => $actions['count']
	];
}

/**
 * Get array with all actions for single event.
 *
 * @param array  $event                              Event objects with acknowledges.
 *        string $event['eventid']                   Problem event ID.
 *        string $event['r_eventid']                 OK event ID.
 *        string $event['clock']                     Time when event occurred.
 *        array  $event['acknowledges']              Array with manual updates to problem.
 *        string $event['acknowledges'][]['userid']  User ID.
 * @param array  $r_events                           Recovery event data for all requested events.
 *        string $r_events[]['clock']                Recovery event creation time.
 * @param array  $alerts                             Alert data for all requested alerts.
 *        string $alerts[]['eventid']                If of problem event for which this alert was generated.
 *        string $alerts[]['mediatypeid']            ID for mediatype used for alert.
 *        string $alerts[]['alerttype']              Type of alert.
 *        string $alerts[]['status']                 Alert status.
 *        string $alerts[]['userid']                 ID of alert recipient.
 */
function getSingleEventActions(array $event, array $r_events, array $alerts): array {
	$action_count = 0;
	$has_uncomplete_action = false;
	$has_failed_action = false;
	$mediatypeids = [];
	$userids = [];

	// Create array of automatically and manually performed actions combined.
	$actions = [];

	// Add row for problem generation event.
	$actions[] = [
		'action_type' => ZBX_EVENT_HISTORY_PROBLEM_EVENT,
		'clock' => $event['clock']
	];

	// Add row for problem recovery event.
	if (array_key_exists($event['r_eventid'], $r_events)) {
		$actions[] = [
			'action_type' => ZBX_EVENT_HISTORY_RECOVERY_EVENT,
			'clock' => $r_events[$event['r_eventid']]['clock']
		];
	}

	// Add manual operations.
	foreach ($event['acknowledges'] as $ack) {
		$ack['action_type'] = ZBX_EVENT_HISTORY_MANUAL_UPDATE;
		$actions[] = $ack;

		$action_count++;
		$userids[$ack['userid']] = true;
	}

	// Add alerts.
	foreach ($alerts as $alert) {
		// Add only alerts, related to current event.
		if (bccomp($alert['eventid'], $event['eventid']) == 0
				|| bccomp($alert['eventid'], $event['r_eventid']) == 0) {
			$alert['action_type'] = ZBX_EVENT_HISTORY_ALERT;
			$actions[] = $alert;

			$action_count++;

			if ($alert['alerttype'] == ALERT_TYPE_MESSAGE) {
				if ($alert['mediatypeid'] != 0) {
					$mediatypeids[$alert['mediatypeid']] = true;
				}

				if ($alert['userid'] != 0) {
					$userids[$alert['userid']] = true;
				}
			}

			if ($alert['status'] == ALERT_STATUS_NEW || $alert['status'] == ALERT_STATUS_NOT_SENT) {
				$has_uncomplete_action = true;
			}
			elseif ($alert['status'] == ALERT_STATUS_FAILED) {
				$has_failed_action = true;
			}
		}
	}

	// Sort by action_type is done to put Recovery event before actions, resulted from it. Same for other action_type.
	CArrayHelper::sort($actions, [
		['field' => 'clock', 'order' => ZBX_SORT_DOWN],
		['field' => 'action_type', 'order' => ZBX_SORT_DOWN],
		['field' => 'alertid', 'order' => ZBX_SORT_DOWN]
	]);

	return [
		'actions' => array_values($actions),
		'count' => $action_count,
		'has_uncomplete_action' => $has_uncomplete_action,
		'has_failed_action' => $has_failed_action,
		'mediatypeids' => $mediatypeids,
		'userids' => $userids
	];
}

/**
 * Get data required to create history list in problem update page.
 *
 * @param array  $event                               Array with event objects with acknowledges.
 *        array  $event['acknowledges']               Array with manual updates to problem.
 *        string $event['acknowledges'][]['clock']    Time when severity was changed.
 *        string $event['acknowledges'][]['userid']   Responsible user's userid.
 */
function getEventUpdates(array $event): array {
	$userids = [];

	foreach ($event['acknowledges'] as $ack) {
		$userids[$ack['userid']] = true;
	}

	CArrayHelper::sort($event['acknowledges'], [['field' => 'clock', 'order' => ZBX_SORT_DOWN]]);

	return [
		'data' => array_values($event['acknowledges']),
		'userids' => $userids
	];
}

/**
 * Make icons (suppressions, messages, severity changes, actions) for actions column.
 *
 * @param string $eventid                  ID for event, for which icons are created.
 * @param array  $actions                  Array of actions data.
 *        array  $actions['suppressions']  Suppression icon data.
 *        array  $actions['messages']      Messages icon data.
 *        array  $actions['severities']    Severity change icon data.
 *        array  $actions['actions']       Actions icon data.
 * @param array  $users                    User name, surname and username.
 * @param bool   $is_acknowledged          Is the event currently acknowledged. If true, display icon.
 *
 * @throws Exception
 *
 * @return CCol|string
 */
function makeEventActionsIcons($eventid, array $actions, array $users, bool $is_acknowledged) {
	$suppression_icon = makeEventSuppressionsProblemIcon($actions['suppressions'][$eventid], $users);
	$messages_icon = makeEventMessagesIcon($actions['messages'][$eventid], $users);
	$severities_icon = makeEventSeverityChangesIcon($actions['severities'][$eventid], $users);
	$actions_icon = makeEventActionsIcon($actions['actions'][$eventid], $eventid);

	$action_icons = [];

	if ($is_acknowledged) {
		$action_icons[] = (new CIcon(ZBX_ICON_CHECK, _('Acknowledged')))->addClass(ZBX_STYLE_COLOR_POSITIVE);
	}

	if ($suppression_icon !== null) {
		$action_icons[] = $suppression_icon;
	}

	if ($messages_icon !== null) {
		$action_icons[] = $messages_icon;
	}

	if ($severities_icon !== null) {
		$action_icons[] = $severities_icon;
	}

	if ($actions_icon !== null) {
		$action_icons[] = $actions_icon;
	}

	return $action_icons ? (new CCol($action_icons))->addClass(ZBX_STYLE_NOWRAP) : '';
}

/**
 * Create icon with hintbox for event suppressions.
 * Records must be passed in the order starting from the latest by field 'clock'.
 *
 * @param array $data
 *        array  $data['suppress_until'][]['suppress_until']  Time until problem is suppressed by user.
 *        string $data['suppress_until'][]['clock']           Suppression creation time.
 * @param array $users                                        User name, surname and username.
 *
 * @throws Exception
 */
function makeEventSuppressionsProblemIcon(array $data, array $users): ?CButtonIcon {
	if ($data['count'] == 0) {
		return null;
	}

	$table = (new CTableInfo())->setHeader([_('Time'), _('User'), _('Action'), _('Suppress until')]);

	for ($i = 0; $i < $data['count'] && $i < ZBX_WIDGET_ROWS; $i++) {
		$suppression = $data['suppress_until'][$i];

		// Added in order to reuse makeActionTableUser().
		$suppression['action_type'] = ZBX_EVENT_HISTORY_MANUAL_UPDATE;

		if (array_key_exists('suppress_until', $suppression)) {
			$icon = new CIcon(ZBX_ICON_EYE_OFF, _('Suppressed'));

			if ($suppression['suppress_until'] == ZBX_PROBLEM_SUPPRESS_TIME_INDEFINITE) {
				$suppress_until = _s('Indefinitely');
			}
			else {
				$suppress_until = $suppression['suppress_until'] < strtotime('tomorrow')
						&& $suppression['suppress_until'] > strtotime('today')
					? zbx_date2str(TIME_FORMAT, $suppression['suppress_until'])
					: zbx_date2str(DATE_TIME_FORMAT, $suppression['suppress_until']);
			}
		}
		else {
			$icon = new CIcon(ZBX_ICON_EYE, _('Unsuppressed'));
			$suppress_until = '';
		}

		$table->addRow([
			zbx_date2str(DATE_TIME_FORMAT_SECONDS, $suppression['clock']),
			makeActionTableUser($suppression, $users),
			$icon,
			$suppress_until
		]);
	}

	if ($data['count'] > ZBX_WIDGET_ROWS) {
		$table->setFooter(
			(new CCol(_s('Displaying %1$s of %2$s found', ZBX_WIDGET_ROWS, $data['count'])))
				->addClass(ZBX_STYLE_LIST_TABLE_FOOTER)
		);
	}

	return (new CButtonIcon(array_key_exists('suppress_until', $data['suppress_until'][0])
		? ZBX_ICON_EYE_OFF
		: ZBX_ICON_EYE
	))
		->addClass(ZBX_STYLE_COLOR_ICON)
		->setHint($table, ZBX_STYLE_HINTBOX_WRAP_HORIZONTAL);
}

/**
 * Create icon with hintbox for event messages.
 *
 * @param array $data
 *        int    $data['count']                  Number of messages.
 *        array  $data['messages']               Array of messages.
 *        string $data['messages'][]['message']  Message text.
 *        string $data['messages'][]['clock']    Message creation time.
 * @param array $users                           User name, surname and username.
 *
 * @throws Exception
 */
function makeEventMessagesIcon(array $data, array $users): ?CButtonIcon {
	if ($data['count'] == 0) {
		return null;
	}

	$table = (new CTableInfo())->setHeader([_('Time'), _('User'), _('Message')]);

	for ($i = 0; $i < $data['count'] && $i < ZBX_WIDGET_ROWS; $i++) {
		$message = $data['messages'][$i];

		// Added in order to reuse makeActionTableUser().
		$message['action_type'] = ZBX_EVENT_HISTORY_MANUAL_UPDATE;

		$table->addRow([
			zbx_date2str(DATE_TIME_FORMAT_SECONDS, $message['clock']),
			makeActionTableUser($message, $users),
			zbx_nl2br($message['message'])
		]);
	}

	if ($data['count'] > ZBX_WIDGET_ROWS) {
		$table->setFooter(
			(new CCol(_s('Displaying %1$s of %2$s found', ZBX_WIDGET_ROWS, $data['count'])))
				->addClass(ZBX_STYLE_LIST_TABLE_FOOTER)
		);
	}

	return (new CButtonIcon(ZBX_ICON_ALERT_WITH_CONTENT))
		->setAttribute('data-content', $data['count'])
		->setAttribute('aria-label',
			_xn('%1$s message', '%1$s messages', $data['count'], 'screen reader', $data['count'])
		)
		->setHint($table, ZBX_STYLE_HINTBOX_WRAP_HORIZONTAL);
}

/**
 * Create icon with hintbox for event severity changes.
 *
 * @param array  $data
 *        int    $data['count']                         Total number of severity changes.
 *        array  $data['severities']                    Array of severities.
 *        string $data['severities'][]['old_severity']  Event severity before change.
 *        string $data['severities'][]['new_severity']  Event severity after change.
 *        string $data['severities'][]['clock']         Severity change time.
 *        string $data['original_severity']             Severity before change.
 *        string $data['current_severity']              Current severity.
 * @param array  $users                                 User name, surname and username.
 *
 * @throws Exception
 */
function makeEventSeverityChangesIcon(array $data, array $users): ?CButtonIcon {
	if ($data['count'] == 0) {
		return null;
	}

	$table = (new CTableInfo())->setHeader([_('Time'), _('User'), _('Severity changes')]);

	for ($i = 0; $i < $data['count'] && $i < ZBX_WIDGET_ROWS; $i++) {
		$severity = $data['severities'][$i];

		// Added in order to reuse makeActionTableUser().
		$severity['action_type'] = ZBX_EVENT_HISTORY_MANUAL_UPDATE;

		// severity changes
		$old_severity_name = CSeverityHelper::getName((int) $severity['old_severity']);
		$new_severity_name = CSeverityHelper::getName((int) $severity['new_severity']);

		$table->addRow([
			zbx_date2str(DATE_TIME_FORMAT_SECONDS, $severity['clock']),
			makeActionTableUser($severity, $users),
			[$old_severity_name, NBSP(), RARR(), NBSP(), $new_severity_name]
		]);
	}

	if ($data['count'] > ZBX_WIDGET_ROWS) {
		$table->setFooter(
			(new CCol(_s('Displaying %1$s of %2$s found', ZBX_WIDGET_ROWS, $data['count'])))
				->addClass(ZBX_STYLE_LIST_TABLE_FOOTER)
		);
	}

	if ($data['original_severity'] > $data['current_severity']) {
		$button = (new CButtonIcon(ZBX_ICON_ARROW_DOWN_SMALL))
			->addClass(ZBX_STYLE_COLOR_POSITIVE)
			->setAttribute('aria-label', _x('Severity decreased', 'screen reader'));
	}
	elseif ($data['original_severity'] < $data['current_severity']) {
		$button = (new CButtonIcon(ZBX_ICON_ARROW_UP_SMALL))
			->addClass(ZBX_STYLE_COLOR_NEGATIVE)
			->setAttribute('aria-label', _x('Severity increased', 'screen reader'));
	}
	else {
		$button = (new CButtonIcon(ZBX_ICON_ARROWS_TOP_BOTTOM))
			->addClass(ZBX_STYLE_COLOR_ICON)
			->setAttribute('aria-label', _x('Severity changed', 'screen reader'));
	}

	return $button->setHint($table, ZBX_STYLE_HINTBOX_WRAP_HORIZONTAL);
}

/**
 * @param array  $actions                      Array with all actions sorted by clock.
 *        int    $actions[]['action_type']     Type of action table entry (ZBX_EVENT_HISTORY_*).
 *        string $actions[]['clock']           Time, when action was performed.
 *        string $actions[]['message']         Message sent by alert, or written by manual update, or remote command text.
 *        string $actions[]['action']          Flag with problem update operation performed (only for ZBX_EVENT_HISTORY_MANUAL_UPDATE).
 *        string $actions[]['alerttype']       Type of alert (only for ZBX_EVENT_HISTORY_ALERT).
 *        string $actions[]['mediatypeid']     Id for mediatype, where alert message was sent (only for ZBX_EVENT_HISTORY_ALERT).
 *        string $actions[]['error']           Error message in case of failed alert (only for ZBX_EVENT_HISTORY_ALERT).
 * @param array  $users                        User name, surname and username.
 * @param array  $mediatypes                   Mediatypes with maxattempts value and name.
 *        string $mediatypes[]['name']         Mediatype name.
 *        string $mediatypes[]['maxattempts']  Maximum attempts for this mediatype.
 *
 * @throws Exception
 */
function makeEventActionsTable(array $actions, array $users, array $mediatypes): CTableInfo {
	$action_count = count($actions);

	$table = (new CTableInfo())->setHeader([
		_('Time'), _('User/Recipient'), _('Action'), _('Message/Command'), _('Status'), _('Info')
	]);

	for ($i = 0; $i < $action_count && $i < ZBX_WIDGET_ROWS; $i++) {
		$action = $actions[$i];

		$message = '';
		if ($action['action_type'] == ZBX_EVENT_HISTORY_MANUAL_UPDATE
				&& ($action['action'] & ZBX_PROBLEM_UPDATE_MESSAGE) == ZBX_PROBLEM_UPDATE_MESSAGE) {
			$message = zbx_nl2br($action['message']);
		}
		elseif ($action['action_type'] == ZBX_EVENT_HISTORY_ALERT) {
			if ($action['alerttype'] == ALERT_TYPE_COMMAND) {
				$message = _('Remote command');
			}
			elseif ($action['alerttype'] == ALERT_TYPE_MESSAGE) {
				$message = array_key_exists($action['mediatypeid'], $mediatypes)
					? $mediatypes[$action['mediatypeid']]['name']
					: '';
			}
		}

		$table->addRow([
			zbx_date2str(DATE_TIME_FORMAT_SECONDS, $action['clock']),
			makeActionTableUser($action, $users),
			makeActionTableIcon($action),
			$message,
			makeActionTableStatus($action),
			makeActionTableInfo($action, $mediatypes)
		]);
	}

	return $table;
}

/**
 * Create icon with hintbox for event actions.
 *
 * @param array  $data
 *        int    $data['count']                  Number of actions.
 *        bool   $data['has_uncomplete_action']  Does the event have at least one uncompleted alert action.
 *        bool   $data['has_failed_action']      Does the event have at least one failed alert action.
 * @param string $eventid
 */
function makeEventActionsIcon(array $data, $eventid): ?CButtonIcon {
	if ($data['count'] == 0) {
		return null;
	}

	$button = new CButtonIcon(ZBX_ICON_BULLET_RIGHT_WITH_CONTENT);

	if ($data['has_failed_action']) {
		$button->addClass(ZBX_STYLE_COLOR_NEGATIVE);
	}
	elseif ($data['has_uncomplete_action']) {
		$button->addClass(ZBX_STYLE_COLOR_WARNING);
	}

	return $button
		->setAttribute('data-content', $data['count'])
		->setAttribute('aria-label',
			_xn('%1$s action', '%1$s actions', $data['count'], 'screen reader', $data['count'])
		)
		->setAjaxHint([
			'type' => 'eventactions',
			'data' => ['eventid' => $eventid]
		], ZBX_STYLE_HINTBOX_WRAP_HORIZONTAL);
}

/**
 * Get table with list of event actions for event details page.
 *
 * @param array  $data
 *        array  $data['actions']                     Array with all actions sorted by clock.
 *        int    $data['actions'][]['action_type']    Type of action table entry (ZBX_EVENT_HISTORY_*).
 *        string $data['actions'][]['clock']          Time, when action was performed.
 *        string $data['actions'][]['message']        Message sent by alert, or written by manual update, or remote command text.
 *        string $data['actions'][]['alerttype']      Type of alert (only for ZBX_EVENT_HISTORY_ALERT).
 *        string $data['actions'][]['esc_step']       Alert escalation step (only for ZBX_EVENT_HISTORY_ALERT).
 *        string $data['actions'][]['subject']        Message alert subject (only for ZBX_EVENT_HISTORY_ALERT).
 *        string $data['actions'][]['p_eventid']      Problem eventid that was reason for alert (only for ZBX_EVENT_HISTORY_ALERT).
 *        string $data['actions'][]['acknowledgeid']  Problem update action that was reason for alert (only for ZBX_EVENT_HISTORY_ALERT).
 * @param array  $users                               User name, surname and username.
 * @param array  $mediatypes                          Mediatypes with maxattempts value.
 */
function makeEventDetailsActionsTable(array $data, array $users, array $mediatypes): CTableInfo {
	$table = (new CTableInfo())->setHeader([
		_('Step'), _('Time'), _('User/Recipient'), _('Action'), _('Message/Command'), _('Status'), _('Info')
	]);

	foreach ($data['actions'] as $action) {
		$esc_step = '';

		if ($action['action_type'] == ZBX_EVENT_HISTORY_ALERT && $action['p_eventid'] == 0
				&& $action['acknowledgeid'] == 0) {
			/*
			 * Escalation step should be displayed, only if alert is caused by problem event.
			 * Escalation step should not be displayed, if alert is caused by resolve event, or by problem update.
			 */
			$esc_step = $action['esc_step'];
		}

		$message = '';

		switch ($action['action_type']) {
			case ZBX_EVENT_HISTORY_ALERT:
				switch ($action['alerttype']) {
					case ALERT_TYPE_MESSAGE:
						$message = [bold($action['subject']), BR(), BR(), zbx_nl2br($action['message'])];
						break;

					case ALERT_TYPE_COMMAND:
						$message = [bold(_('Command').':'), BR(), zbx_nl2br($action['message'])];
						break;
				}
				break;

			case ZBX_EVENT_HISTORY_MANUAL_UPDATE:
				$message = zbx_nl2br($action['message']);
				break;
		}

		$table->addRow([
			$esc_step,
			zbx_date2str(DATE_TIME_FORMAT_SECONDS, $action['clock']),
			makeEventDetailsTableUser($action, $users),
			makeActionTableIcon($action),
			$message,
			makeActionTableStatus($action),
			makeActionTableInfo($action, $mediatypes)
		]);
	}

	return $table;
}

/**
 * Get table with list of event updates for update event page.
 *
 * @param array  $actions               Array with all actions sorted by clock.
 *        string $actions[]['clock']    Time, when action was performed.
 *        string $actions[]['message']  Message sent by alert, or written by manual update, or remote command text.
 * @param array  $users                 User name, surname and username.
 */
function makeEventHistoryTable(array $actions, array $users): CTable {
	$table = (new CTable())
		->addStyle('width: 100%;')
		->setHeader([_('Time'), _('User'), _('User action'), _('Message')]);

	foreach ($actions as $action) {
		// Added in order to reuse makeActionTableUser() and makeActionTableIcon()
		$action['action_type'] = ZBX_EVENT_HISTORY_MANUAL_UPDATE;

		$table->addRow([
			zbx_date2str(DATE_TIME_FORMAT_SECONDS, $action['clock']),
			makeActionTableUser($action, $users),
			makeActionTableIcon($action),
			(new CCol(zbx_nl2br($action['message'])))->addClass(ZBX_STYLE_TABLE_FORMS_OVERFLOW_BREAK)
		]);
	}

	return $table;
}

/**
 * Creates username for message author or alert receiver.
 *
 * @param array  $action                 Array of action data.
 *        int    $action['action_type']  Type of event table action (ZBX_EVENT_HISTORY_*).
 *        string $action['alerttype']    Type of alert.
 *        string $action['userid']       ID of message author, or alert receiver.
 * @param array  $users                  Array with user data - username, name, surname.
 */
function makeActionTableUser(array $action, array $users): string {
	if (($action['action_type'] == ZBX_EVENT_HISTORY_ALERT && $action['alerttype'] == ALERT_TYPE_MESSAGE)
			|| $action['action_type'] == ZBX_EVENT_HISTORY_MANUAL_UPDATE) {
		return array_key_exists($action['userid'], $users)
			? getUserFullname($users[$action['userid']])
			: _('Inaccessible user');
	}
	else {
		return '';
	}
}

/**
 * Creates username for message author or alert receiver. Also contains 'sendto' for message actions.
 *
 * @param array  $action
 *        int    $action['action_type']  Type of event table action (ZBX_EVENT_HISTORY_*).
 *        string $action['alerttype']    Type of alert.
 *        array  $action['userid']       ID of message author, or alert receiver.
 *        array  $action['sendto']       Receiver media address for automatic action.
 * @param array  $users                  Array with user data - username, name, surname.
 *
 * @return array|string
 */
function makeEventDetailsTableUser(array $action, array $users) {
	if ($action['action_type'] == ZBX_EVENT_HISTORY_ALERT && $action['alerttype'] == ALERT_TYPE_MESSAGE) {
		return array_key_exists($action['userid'], $users)
			? [getUserFullname($users[$action['userid']]), BR(), italic(zbx_nl2br($action['sendto']))]
			: _('Inaccessible user');
	}
	elseif ($action['action_type'] == ZBX_EVENT_HISTORY_MANUAL_UPDATE) {
		return array_key_exists($action['userid'], $users)
			? getUserFullname($users[$action['userid']])
			: _('Inaccessible user');
	}
	else {
		return '';
	}
}

/**
 * Make appropriate icon for event action.
 *
 * @param array $action                  Array with actions table data.
 *        int   $action['action_type']   Type of action table entry (ZBX_EVENT_HISTORY_*).
 *        int   $action['action']        Flag with problem update operation performed (only for ZBX_EVENT_HISTORY_MANUAL_UPDATE).
 *        int   $action['old_severity']  Severity before problem update (only for ZBX_EVENT_HISTORY_MANUAL_UPDATE).
 *        int   $action['new_severity']  Severity after problem update (only for ZBX_EVENT_HISTORY_MANUAL_UPDATE).
 *        int   $action['alerttype']     Type of alert (only for ZBX_EVENT_HISTORY_ALERT).
 */
function makeActionTableIcon(array $action): ?CTag {
	switch ($action['action_type']) {
		case ZBX_EVENT_HISTORY_PROBLEM_EVENT:
			return new CIcon(ZBX_ICON_CALENDAR_WARNING, _('Problem created'));

		case ZBX_EVENT_HISTORY_RECOVERY_EVENT:
			return new CIcon(ZBX_ICON_CALENDAR_CHECK, _('Problem resolved'));

		case ZBX_EVENT_HISTORY_MANUAL_UPDATE:
			$action_icons = [];

			if (($action['action'] & ZBX_PROBLEM_UPDATE_CLOSE) == ZBX_PROBLEM_UPDATE_CLOSE) {
				$action_icons[] = new CIcon(ZBX_ICON_CHECKBOX, _('Manually closed'));
			}

			if (($action['action'] & ZBX_PROBLEM_UPDATE_RANK_TO_CAUSE) == ZBX_PROBLEM_UPDATE_RANK_TO_CAUSE) {
				$action_icons[] = new CIcon(ZBX_ICON_ARROW_RIGHT_TOP, _('Cause'));
			}

			if (($action['action'] & ZBX_PROBLEM_UPDATE_RANK_TO_SYMPTOM) == ZBX_PROBLEM_UPDATE_RANK_TO_SYMPTOM) {
				$action_icons[] = new CIcon(ZBX_ICON_ARROW_TOP_RIGHT, _('Symptom'));
			}

			if (($action['action'] & ZBX_PROBLEM_UPDATE_ACKNOWLEDGE) == ZBX_PROBLEM_UPDATE_ACKNOWLEDGE) {
				$action_icons[] = new CIcon(ZBX_ICON_CHECK, _('Acknowledged'));
			}

			if (($action['action'] & ZBX_PROBLEM_UPDATE_UNACKNOWLEDGE) == ZBX_PROBLEM_UPDATE_UNACKNOWLEDGE) {
				$action_icons[] = new CIcon(ZBX_ICON_UNCHECK, _('Unacknowledged'));
			}

			if (($action['action'] & ZBX_PROBLEM_UPDATE_SUPPRESS) == ZBX_PROBLEM_UPDATE_SUPPRESS) {
				if ($action['suppress_until'] == ZBX_PROBLEM_SUPPRESS_TIME_INDEFINITE) {
					$suppress_until = _s('Indefinitely');
				}
				else {
					$suppress_until = $action['suppress_until'] < strtotime('tomorrow')
							&& $action['suppress_until'] > strtotime('today')
						? zbx_date2str(TIME_FORMAT, $action['suppress_until'])
						: zbx_date2str(DATE_TIME_FORMAT, $action['suppress_until']);
				}

				$action_icons[] = (new CButtonIcon(ZBX_ICON_EYE_OFF))
					->addClass(ZBX_STYLE_COLOR_ICON)
					->setHint(_s('Suppressed till: %1$s', $suppress_until), ZBX_STYLE_HINTBOX_WRAP_HORIZONTAL);
			}

			if (($action['action'] & ZBX_PROBLEM_UPDATE_UNSUPPRESS) == ZBX_PROBLEM_UPDATE_UNSUPPRESS) {
				$action_icons[] = new CIcon(ZBX_ICON_EYE, _('Unsuppressed'));
			}

			if (($action['action'] & ZBX_PROBLEM_UPDATE_MESSAGE) == ZBX_PROBLEM_UPDATE_MESSAGE) {
				$action_icons[] = new CIcon(ZBX_ICON_ALERT_MORE, _('Message'));
			}

			if (($action['action'] & ZBX_PROBLEM_UPDATE_SEVERITY) == ZBX_PROBLEM_UPDATE_SEVERITY) {
				$button = $action['new_severity'] > $action['old_severity']
					? (new CButtonIcon(ZBX_ICON_ARROW_UP_SMALL))->addClass(ZBX_STYLE_COLOR_NEGATIVE)
					: (new CButtonIcon(ZBX_ICON_ARROW_DOWN_SMALL))->addClass(ZBX_STYLE_COLOR_POSITIVE);

				$old_severity_name = CSeverityHelper::getName((int) $action['old_severity']);
				$new_severity_name = CSeverityHelper::getName((int) $action['new_severity']);

				$action_icons[] = $button->setHint(
					[$old_severity_name, NBSP(), RARR(), NBSP(), $new_severity_name], ZBX_STYLE_HINTBOX_WRAP_HORIZONTAL
				);
			}

			return (new CCol($action_icons))->addClass(ZBX_STYLE_NOWRAP);

		case ZBX_EVENT_HISTORY_ALERT:
			return $action['alerttype'] == ALERT_TYPE_COMMAND
				? new CIcon(ZBX_ICON_COMMAND, _('Remote command'))
				: new CIcon(ZBX_ICON_ENVELOPE_FILLED, _('Alert message'));

		default:
			return null;
	}
}

/**
 * Creates span with alert status text.
 *
 * @param array  $action
 *        int    $action['action_type']  Type of event table action (ZBX_EVENT_HISTORY_*).
 *        string $action['status']       Alert status.
 *        string $action['alerttype']    Type of alert.
 *
 * @return CSpan|string
 */
function makeActionTableStatus(array $action) {
	if ($action['action_type'] != ZBX_EVENT_HISTORY_ALERT) {
		return '';
	}

	switch ($action['status']) {
		case ALERT_STATUS_SENT:
			$status_label = ($action['alerttype'] == ALERT_TYPE_COMMAND)
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

	return (new CSpan($status_label))->addClass($status_color);
}

/**
 * Creates div with alert info icons.
 *
 * @param array  $action
 *        int    $action['action_type']        Type of event table action (ZBX_EVENT_HISTORY_*).
 *        string $action['status']             Alert status.
 *        string $action['alerttype']          Type of alert.
 *        string $action['mediatypeid']        ID for mediatype, where alert message was sent.
 *        string $action['retries']            How many retries were done for pending alert message.
 * @param array  $mediatypes                   Array of media type data.
 *        array  $mediatypes[]['maxattempts']  Maximum attempts for this mediatype.
 *
 * @return CDiv|string
 */
function makeActionTableInfo(array $action, array $mediatypes) {
	if ($action['action_type'] == ZBX_EVENT_HISTORY_ALERT) {
		$info_icons = [];

		if ($action['alerttype'] == ALERT_TYPE_MESSAGE
				&& ($action['status'] == ALERT_STATUS_NEW || $action['status'] == ALERT_STATUS_NOT_SENT)) {
			$info_icons[] = makeWarningIcon(array_key_exists($action['mediatypeid'], $mediatypes)
				? _n('%1$s retry left', '%1$s retries left',
					$mediatypes[$action['mediatypeid']]['maxattempts'] - $action['retries']
				)
				: ''
			);
		}
		elseif ($action['error'] !== '') {
			$info_icons[] = makeErrorIcon($action['error']);
		}

		return makeInformationList($info_icons);
	}
	else {
		return '';
	}
}
