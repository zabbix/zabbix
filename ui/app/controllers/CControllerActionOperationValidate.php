<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


class CControllerActionOperationValidate extends CController {
	protected function init(): void
	{
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
		$this->disableSIDvalidation();
	}

	protected function checkInput() {
		$fields = [
			'operation' => 'array',
			'actionid'	=> 'db actions.actionid'
		];

		$ret = $this->validateInput($fields) && $this->validateOperation();

		if (!$ret) {
			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])])
			);
		}

		return $ret;
	}

	protected function validateOperation(): bool {
		$operation = $this->getInput('operation', []);
		$required_fields = ['eventsource', 'recovery', 'operationtype'];

		foreach ($required_fields as $field) {
			if (!array_key_exists($field, $operation)) {
				error(_s('Field "%1$s" is mandatory.', $field));

				return false;
			}
		}

		$eventsource = $operation['eventsource'];
		$recovery = $operation['recovery'];
		$optype = $operation['operationtype'];

		// todo : fix this so can remove regex
		$operationtype = preg_replace('[\D]', '', $optype);
		$allowed_operations = getAllowedOperations($eventsource);

		if (preg_match('/\bscriptid\b/', $optype)){
			$operationtype = OPERATION_TYPE_COMMAND;
		}

		// todo : add default operation object?? see defaultOperationObject()
		// todo : get data from ActionOperationGet ??


		if (!array_key_exists($recovery, $allowed_operations)
				|| !in_array($operationtype, $allowed_operations[$recovery])) {
			error(_s('Incorrect action operation type "%1$s" for event source "%2$s".', $operationtype, $eventsource));

			return false;
		}

		$default_msg_validator = new CLimitedSetValidator([
			'values' => [0, 1]
		]);

		if ($recovery == ACTION_OPERATION) {
			if ((array_key_exists('esc_step_from', $operation) || array_key_exists('esc_step_to', $operation))
					&& (!array_key_exists('esc_step_from', $operation)
						|| !array_key_exists('esc_step_to', $operation))) {
				error(_('Parameters "esc_step_from" and "esc_step_to" must be set together.'));

				return false;
			}

			if (array_key_exists('esc_step_from', $operation) && array_key_exists('esc_step_to', $operation)) {
				if ($operation['esc_step_from'] < 1 || $operation['esc_step_to'] < 0) {
					error(_('Incorrect action operation escalation step values.'));

					return false;
				}

				if ($operation['esc_step_from'] > $operation['esc_step_to'] && $operation['esc_step_to'] != 0) {
					error(_('Incorrect action operation escalation step values.'));

					return false;
				}
			}

			if (array_key_exists('esc_period', $operation)
					&& !validateTimeUnit($operation['esc_period'], SEC_PER_MIN, SEC_PER_WEEK, true, $error,
					['usermacros' => true])) {
				error(_s('Incorrect value for field "%1$s": %2$s.', _('Step duration'), $error));

				return false;
			}
		}

		switch ($operationtype) {
			case OPERATION_TYPE_MESSAGE:
				$has_users = array_key_exists('opmessage_usr', $operation) && $operation['opmessage_usr'];
				$has_usrgrps = array_key_exists('opmessage_grp', $operation) && $operation['opmessage_grp'];

				if (!$has_users && !$has_usrgrps) {
					error(_('No recipients specified for action operation message.'));

					return false;
				}
				// break; is not missing here

			case OPERATION_TYPE_UPDATE_MESSAGE:
				$message = array_key_exists('opmessage', $operation) ? $operation['opmessage'] : [];

				if (array_key_exists('default_msg', $message)
						&& (!$default_msg_validator->validate($message['default_msg']))) {
					error(_s('Incorrect value "%1$s" for "%2$s" field.', $message['default_msg'], _('Custom message')));

					return false;
				}
				break;

			case OPERATION_TYPE_COMMAND:
				// todo : find how to add this!!!
				// todo : remove. just for testing
				$operation['opcommand'] = ['scriptid' => 4];

				if (!array_key_exists('scriptid', $operation['opcommand']) || !$operation['opcommand']['scriptid']) {
					error(_('No script specified for action operation command.'));

					return false;
				}

				if ($eventsource == EVENT_SOURCE_SERVICE) {
					break;
				}

				$has_groups = array_key_exists('opcommand_grp', $operation) && $operation['opcommand_grp'];
				$has_hosts = array_key_exists('opcommand_hst', $operation) && $operation['opcommand_hst'];

				if (!$has_groups && !$has_hosts) {
					error(_('No targets specified for action operation global script.'));

					return false;
				}
				break;

			case OPERATION_TYPE_GROUP_ADD:
			case OPERATION_TYPE_GROUP_REMOVE:
				if (!array_key_exists('opgroup', $operation) || !$operation['opgroup']) {
					error(_('Operation has no group to operate.'));

					return false;
				}
				break;

			case OPERATION_TYPE_TEMPLATE_ADD:
			case OPERATION_TYPE_TEMPLATE_REMOVE:
				if (!array_key_exists('optemplate', $operation) || !$operation['optemplate']) {
					error(_('Operation has no template to operate.'));

					return false;
				}
				break;

			case OPERATION_TYPE_HOST_INVENTORY:
				if (!array_key_exists('opinventory', $operation)
						|| !array_key_exists('inventory_mode', $operation['opinventory'])) {
					error(_('No inventory mode specified for action operation.'));

					return false;
				}

				if ($operation['opinventory']['inventory_mode'] != HOST_INVENTORY_MANUAL
						&& $operation['opinventory']['inventory_mode'] != HOST_INVENTORY_AUTOMATIC) {
					error(_('Incorrect inventory mode in action operation.'));

					return false;
				}
				break;
		}

		return true;
	}

	protected function checkPermissions() {
		if ($this->getUserType() >= USER_TYPE_ZABBIX_ADMIN) {
			if (!$this->getInput('actionid', '0')) {
				return true;
			}

			return (bool) API::Action()->get([
				'output' => [],
				'actionids' => $this->getInput('actionid'),
				'editable' => true
			]);
		}

		return false;
	}

	protected function doAction() {
		$operation = $this->getInput('operation');

		$operationtype = preg_replace('[\D]', '', $operation['operationtype']);

		if (preg_match('/\bscriptid\b/', $operation['operationtype'])){
			$operationtype = OPERATION_TYPE_COMMAND;
		}
		// todo : check what is the same and remove unnecessary code
		// todo : fix - fields based on eventsource

		$data['operation'] = $operation;
		$data['operation']['operationtype'] = $operationtype;
		$data['operation']['details'] = $this->getActionOperationDescription($operation);

		if ($operation['recovery'] == ACTION_OPERATION) {
			$data['operation']['start_in'] = $this->createStartInColumn($operation);

			if ($operation['recovery'] == ACTION_OPERATION &&
					($operation['eventsource'] == EVENT_SOURCE_TRIGGERS
					|| $operation['eventsource'] == EVENT_SOURCE_INTERNAL
					|| $operation['eventsource'] == EVENT_SOURCE_SERVICE)) {
				$data['operation']['duration'] = $this->createDurationColumn($operation['esc_period']);
				$data['operation']['steps'] = $this->createStepsColumn($operation);
			}
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($data)]));
	}

	protected function createStartInColumn($operation):string {
		$delays = count_operations_delay([$operation], $operation['esc_period']);

		return ($delays[$operation['esc_step_from']] === null)
			? _('Unknown')
			: ($delays[$operation['esc_step_from']] != 0
				? convertUnits(['value' => $delays[$operation['esc_step_from']], 'units' => 'uptime'])
				: _('Immediately')
			);
	}

	protected function createStepsColumn($operation):string {
		$steps = '';
		$step_from = $operation['esc_step_from'];
		if ($operation['esc_step_from'] < 1) {
			$step_from = 1;
		}
		// todo : should add in increasing order (by steps) from js??
		if (($step_from === $operation['esc_step_to']) || $operation['esc_step_to'] == 0) {
			$steps = $step_from;
		}
		if ($step_from < $operation['esc_step_to']) {
			$steps = $step_from.' - '.$operation['esc_step_to'];
		}

		return $steps;
	}

	protected function createDurationColumn($step_duration):string {
		return $step_duration === '0'
			? 'Default'
			: $step_duration;
	}

	function getActionOperationDescription(array $operation): array {
		$result = [];

		$media_typeids = [];
		$userids = [];
		$usr_grpids = [];
		$hostids = [];
		$groupids = [];
		$templateids = [];
		$scriptids = [];

		$type = $operation['recovery'];
		$operationtype = preg_replace('[\D]', '', $operation['operationtype']);

		if (preg_match('/\bscriptid\b/', $operation['operationtype'])){
			$operationtype = OPERATION_TYPE_COMMAND;
		}

		if ($type == ACTION_OPERATION) {
			switch ($operationtype) {
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
					// todo : add opcommand_hst or opcommand_grp!!!

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

					$scriptids[$operation['opcommand']['scriptid']] = true;
					break;

				case OPERATION_TYPE_GROUP_ADD:
				case OPERATION_TYPE_GROUP_REMOVE:
					foreach ($operation['opgroup'] as $groupid) {
						$groupids[$groupid['groupid']] = true;
					}
					break;

				case OPERATION_TYPE_TEMPLATE_ADD:
				case OPERATION_TYPE_TEMPLATE_REMOVE:
					foreach ($operation['optemplate'] as $templateid) {
						$templateids[$templateid['templateid']] = true;
					}
					break;
			}
		}
		else {
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

					$scriptids[$operation['opcommand']['scriptid']] = true;
					break;
			}
		}

		$media_types = [];
		$user_groups = [];
		$hosts = [];
		$host_groups = [];
		$templates = [];
		$scripts = [];

		if ($media_typeids) {
			$media_types = API::Mediatype()->get([
				'output' => ['name'],
				'mediatypeids' => $media_typeids,
				'preservekeys' => true
			]);
		}

		if ($userids) {
			$fullnames = [];

			$users = API::User()->get([
				'output' => ['userid', 'username', 'name', 'surname'],
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

		if ($scriptids) {
			$scripts = API::Script()->get([
				'output' => ['name'],
				'scriptids' => array_keys($scriptids),
				'filter' => ['scope' => ZBX_SCRIPT_SCOPE_ACTION],
				'preservekeys' => true
			]);
		}

		// Format the output.
		if ($type == ACTION_OPERATION) {

			switch ($operationtype) {
				case OPERATION_TYPE_MESSAGE:
					$media_type = _('all media');
					$media_typeid = $operation['opmessage']['mediatypeid'];

					if ($media_typeid != 0 && isset($media_types[$media_typeid])) {
						$media_type = $media_types[$media_typeid]['name'];
					}

					if (array_key_exists('opmessage_usr', $operation) && $operation['opmessage_usr']) {
						$user_names_list = [];

						foreach ($operation['opmessage_usr'] as $user) {
							if (isset($fullnames[$user['userid']])) {
								$user_names_list[] = $fullnames[$user['userid']];
							}
						}

						order_result($user_names_list);

						$result['type'] = (_('Send message to users').': ');
						$result['data'] = [implode(', ', $user_names_list), ' ', _('via'), ' ', $media_type];
					}

					if (array_key_exists('opmessage_grp', $operation) && $operation['opmessage_grp']) {
						$user_groups_list = [];

						foreach ($operation['opmessage_grp'] as $userGroup) {
							if (isset($user_groups[$userGroup['usrgrpid']])) {
								$user_groups_list[] = $user_groups[$userGroup['usrgrpid']]['name'];
							}
						}

						order_result($user_groups_list);

						$result['type'] = _('Send message to user groups').': ';
						$result['data'] = [implode(', ', $user_groups_list), _('via'), $media_type];
					}
					break;

				case OPERATION_TYPE_COMMAND:
					$scriptid = $operation['opcommand']['scriptid'];

					if ($operation['eventsource'] == EVENT_SOURCE_SERVICE) {
						$result['type'] = [_s('Run script "%1$s" on Zabbix server', $scripts[$scriptid]['name'])];

						break;
					}

					if (array_key_exists('opcommand_hst', $operation) && $operation['opcommand_hst']) {
						$host_list = [];

						foreach ($operation['opcommand_hst'] as $host) {
							if ($host['hostid'] == 0) {
								$result['type'] = [_s('Run script "%1$s" on current host', $scripts[$scriptid]['name'])];
							}
							elseif (isset($hosts[$host['hostid']])) {
								$host_list[] = $hosts[$host['hostid']]['name'];
							}
						}

						if ($host_list) {
							order_result($host_list);

							$result['type'] = _s('Run script "%1$s" on hosts', $scripts[$scriptid]['name'].': ');
							$result['data'] = [implode(', ', $host_list)];
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

						$result['type'] = _s('Run script "%1$s" on host groups', $scripts[$scriptid]['name']).': ';
						$result['data'] = [implode(', ', $host_group_list)];
					}
					break;

				case OPERATION_TYPE_HOST_ADD:
					$result['type'] = (_('Add host'));
					break;

				case OPERATION_TYPE_HOST_REMOVE:
					$result['type'] = (_('Remove host'));
					break;

				case OPERATION_TYPE_HOST_ENABLE:
					$result['type'] = (_('Enable host'));
					break;

				case OPERATION_TYPE_HOST_DISABLE:
					$result['type'] = (_('Disable host'));
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

					if ($operationtype == OPERATION_TYPE_GROUP_ADD) {
						$result['type'] = _('Add to host groups').': ';
					}
					else {
						$result['type'] = _('Remove from host groups').': ';
					}

					$result['data'] = [implode(', ', $host_group_list)];
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

					if ($operationtype == OPERATION_TYPE_TEMPLATE_ADD) {
						$result['type'] = _('Link to templates').': ';
					}
					else {
						$result['type'] = _('Unlink from templates').': ';
					}

					$result['data'] = [implode(', ', $template_list)];
					break;

				case OPERATION_TYPE_HOST_INVENTORY:
					$host_inventory_modes = getHostInventoryModes();
					$result['type'] = operation_type2str(OPERATION_TYPE_HOST_INVENTORY).': ';
					$result['data'] = [$host_inventory_modes[$operation['opinventory']['inventory_mode']]];
					break;
			}
		}
		else {
			switch ($operationtype) {
				case OPERATION_TYPE_MESSAGE:
					$media_type = _('all media');
					$media_typeid = $operation['opmessage']['mediatypeid'];

					if ($media_typeid != 0 && isset($media_types[$media_typeid])) {
						$media_type = $media_types[$media_typeid]['name'];
					}

					if (array_key_exists('opmessage_usr', $operation) && $operation['opmessage_usr']) {
						$user_names_list = [];

						foreach ($operation['opmessage_usr'] as $user) {
							if (isset($fullnames[$user['userid']])) {
								$user_names_list[] = $fullnames[$user['userid']];
							}
						}

						order_result($user_names_list);

						$result['type'] = _('Send message to users').': ';
						$result['data'] = [implode(', ', $user_names_list), ' ', _('via'), ' ', $media_type];
					}

					if (array_key_exists('opmessage_grp', $operation) && $operation['opmessage_grp']) {
						$user_groups_list = [];

						foreach ($operation['opmessage_grp'] as $userGroup) {
							if (isset($user_groups[$userGroup['usrgrpid']])) {
								$user_groups_list[] = $user_groups[$userGroup['usrgrpid']]['name'];
							}
						}

						order_result($user_groups_list);

						$result['type'] = _('Send message to user groups').': ';
						$result['data'] = [implode(', ', $user_groups_list), ' ', _('via'), ' ', $media_type];
					}
					break;

				case OPERATION_TYPE_COMMAND:
					$scriptid = $operation['opcommand']['scriptid'];

					if ($operation['eventsource'] == EVENT_SOURCE_SERVICE) {
						$result['type'] = [_s('Run script "%1$s" on Zabbix server', $scripts[$scriptid]['name'])];

						break;
					}

					if (array_key_exists('opcommand_hst', $operation) && $operation['opcommand_hst']) {
						$host_list = [];

						foreach ($operation['opcommand_hst'] as $host) {
							if ($host['hostid'] == 0) {
								$result['type'] = [_s('Run script "%1$s" on current host', $scripts[$scriptid]['name'])];
							}
							elseif (isset($hosts[$host['hostid']])) {
								$host_list[] = $hosts[$host['hostid']]['name'];
							}
						}

						if ($host_list) {
							order_result($host_list);

							$result['type'] = _s('Run script "%1$s" on hosts', $scripts[$scriptid]['name']).': ';
							$result['data'] = [implode(', ', $host_list)];
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

						$result['type'] = _s('Run script "%1$s" on host groups', $scripts[$scriptid]['name']).': ';
						$result['data'] = [implode(', ', $host_group_list)];
					}
					break;

				case OPERATION_TYPE_RECOVERY_MESSAGE:
				case OPERATION_TYPE_UPDATE_MESSAGE:
					$result['type'] =_('Notify all involved');
					break;
			}
		}

		return $result;
	}
}
