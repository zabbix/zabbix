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


class CControllerActionOperationCheck extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
		$this->disableSIDvalidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'operation' =>	'array',
			'actionid' =>	'db actions.actionid'
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
		$operationtype = preg_replace('[\D]', '', $operation['operationtype']);
		$allowed_operations = getAllowedOperations($eventsource);

		if (preg_match('/\bscriptid\b/', $operation['operationtype'])){
			$operationtype = OPERATION_TYPE_COMMAND;
		}

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
				$scriptid = preg_replace('[\D]', '', $operation['operationtype']);
				$operation['opcommand'] = ['scriptid' => $scriptid];

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

	protected function checkPermissions(): bool {
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

	protected function doAction(): void {
		$operation = $this->getInput('operation');
		$eventsource = $operation['eventsource'];

		if (preg_match('/\bscriptid\b/', $operation['operationtype'])){
			$operation['opcommand']['scriptid'] = preg_replace('[\D]', '', $operation['operationtype']);
			$operationtype = OPERATION_TYPE_COMMAND;

			if (array_key_exists('opcommand_hst', $operation)) {
				if (array_key_exists('current_host', $operation['opcommand_hst'][0]['hostid'])) {
					$operation['opcommand_hst'][0]['hostid'] = 0;
				}
			}
		}
		else {
			$operationtype = preg_replace('[\D]', '', $operation['operationtype']);
		}

		if (array_key_exists('opmessage', $operation)) {
			if (!array_key_exists('default_msg', $operation['opmessage'])
				&& ($operationtype === OPERATION_TYPE_MESSAGE
					|| $operationtype === OPERATION_TYPE_RECOVERY_MESSAGE
					|| $operationtype === OPERATION_TYPE_UPDATE_MESSAGE)) {
				$operation['opmessage']['default_msg'] = '1';

				unset($operation['opmessage']['subject'], $operation['opmessage']['message']);
			}
		}
		$operation['operationtype'] = $operationtype;

		$action = [
			'name' => '',
			'esc_period' => DB::getDefault('actions', 'esc_period'),
			'eventsource' => $eventsource,
			'status' => 0,
			'operations' => $operation['recovery'] == ACTION_OPERATION? [$operation] : [],
			'recovery_operations' => $operation['recovery'] == ACTION_RECOVERY_OPERATION ? [$operation] : [],
			'update_operations' => $operation['recovery'] == ACTION_UPDATE_OPERATION ? [$operation] : [],
			'filter' => [
				'conditions' => [],
				'evaltype' => ''
			],
			'pause_suppressed' => ACTION_PAUSE_SUPPRESSED_TRUE,
			'notify_if_canceled' =>  ACTION_NOTIFY_IF_CANCELED_TRUE
		];

		$data['operation'] = $operation;

		if (in_array($eventsource, [EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_SERVICE, EVENT_SOURCE_INTERNAL])
				&& $operation['recovery'] == ACTION_OPERATION) {
			$data['operation']['start_in'] = $this->createStartInColumn($operation);

			if ($operation['recovery'] == ACTION_OPERATION &&
					($eventsource == EVENT_SOURCE_TRIGGERS
					|| $eventsource == EVENT_SOURCE_INTERNAL
					|| $eventsource == EVENT_SOURCE_SERVICE)) {
				$data['operation']['duration'] = $this->createDurationColumn($operation['esc_period']);
				$data['operation']['steps'] = $this->createStepsColumn($operation);
			}
		}

		$data['operation']['details'] = $this->getData($operationtype, [$action], $operation['recovery']);

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($data)]));
	}

	protected function createStartInColumn($operation): string {
		$operation_type = $this->getInput('operation')['recovery'];
		$previous_operations = [];

		if ($operation_type == ACTION_OPERATION && $this->getInput('actionid')) {
			$allOperations = API::Action()->get([
				'selectOperations' => ['operationtype', 'esc_period', 'esc_step_from', 'esc_step_to', 'evaltype'],
				'actionids' => $this->getInput('actionid')
			]);
			$previous_operations = $allOperations[0]['operations'];
		}

		$delays = count_operations_delay($previous_operations, $operation['esc_period']);

		return ($delays[$operation['esc_step_from']] === null)
			? _('Unknown')
			: ($delays[$operation['esc_step_from']] != 0
				? convertUnits(['value' => $delays[$operation['esc_step_from']], 'units' => 'uptime'])
				: _('Immediately')
			);
	}

	protected function createStepsColumn($operation): string {
		$steps = '';
		$step_from = $operation['esc_step_from'];
		if ($operation['esc_step_from'] < 1) {
			$step_from = 1;
		}
		if (($step_from === $operation['esc_step_to']) || $operation['esc_step_to'] == 0) {
			$steps = $step_from;
		}
		if ($step_from < $operation['esc_step_to']) {
			$steps = $step_from.' - '.$operation['esc_step_to'];
		}

		return $steps;
	}

	protected function createDurationColumn($step_duration): string {
		return $step_duration === '0'
			? 'Default'
			: $step_duration;
	}

	protected function getData(int $operationtype, array $action, int $type): array {
		$data = getActionOperationData($action, $type);
		$operation_values = getOperationDataValues($data);
		$result = [];

		$media_types = array_key_exists('media_types', $operation_values) ? $operation_values['media_types'] : [];
		$user_groups = array_key_exists('user_groups', $operation_values) ? $operation_values['user_groups'] : [];
		$hosts = array_key_exists('hosts', $operation_values) ? $operation_values['hosts'] : [];
		$host_groups = array_key_exists('host_groups', $operation_values) ? $operation_values['host_groups'] : [];
		$templates = array_key_exists('templates', $operation_values) ? $operation_values['templates'] : [];
		$scripts = array_key_exists('scripts', $operation_values) ? $operation_values['scripts'] : [];
		$fullnames = array_key_exists('users', $operation_values) ? $operation_values['users']['fullnames'] : [];

		switch ($type) {
			case ACTION_OPERATION:
				$operation = $action[0]['operations'][0];
				break;

			case ACTION_RECOVERY_OPERATION:
				$operation = $action[0]['recovery_operations'][0];
				break;

			case ACTION_UPDATE_OPERATION:
				$operation =  $action[0]['update_operations'][0];
				break;
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

						$result['type'][] = (_('Send message to users').': ');
						$result['data'][] = [implode(', ', $user_names_list), ' ', _('via'), ' ', $media_type];
					}

					if (array_key_exists('opmessage_grp', $operation) && $operation['opmessage_grp']) {
						$user_groups_list = [];

						foreach ($operation['opmessage_grp'] as $userGroup) {
							if (isset($user_groups[$userGroup['usrgrpid']])) {
								$user_groups_list[] = $user_groups[$userGroup['usrgrpid']]['name'];
							}
						}

						order_result($user_groups_list);

						$result['type'][] = _('Send message to user groups').': ';
						$result['data'][] = [implode(', ', $user_groups_list), _('via'), $media_type];
					}
				break;

				case OPERATION_TYPE_COMMAND:
					if ($operation['eventsource'] == EVENT_SOURCE_SERVICE) {
						$result['type'][] = _s('Run script "%1$s" on Zabbix server', $scripts[$operation['opcommand']['scriptid']]['name']);
						break;
					}

					if (array_key_exists('opcommand_hst', $operation) && $operation['opcommand_hst']) {
						$host_list = [];

						foreach ($operation['opcommand_hst'] as $host) {
							if ($host['hostid'] == 0) {
								$result['type'][] = (_s('Run script "%1$s" on current host', $scripts[$operation['opcommand']['scriptid']]['name']));
							}
							elseif (isset($hosts[$host['hostid']])) {
								$host_list[] = $hosts[$host['hostid']]['name'];
							}
						}

						if ($host_list) {
							order_result($host_list);

							$result['type'][] = _s('Run script "%1$s" on hosts: ', $scripts[$operation['opcommand']['scriptid']]['name']);
							$result['data'][] = [implode(', ', $host_list)];
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

						$result['type'][] = (_s('Run script "%1$s" on host groups: ', $scripts[$operation['opcommand']['scriptid']]['name']));
						$result['data'][] = [implode(', ', $host_group_list)];
					}
					break;

				case OPERATION_TYPE_HOST_ADD:
					$result['type'][] = (_('Add host'));
					break;

				case OPERATION_TYPE_HOST_REMOVE:
					$result['type'][] = (_('Remove host'));
					break;

				case OPERATION_TYPE_HOST_ENABLE:
					$result['type'][] = (_('Enable host'));
					break;

				case OPERATION_TYPE_HOST_DISABLE:
					$result['type'][] = (_('Disable host'));
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
						$result['type'][] = _('Add to host groups').': ';
					}
					else {
						$result['type'][] = _('Remove from host groups').': ';
					}

					$result['data'][] = [implode(', ', $host_group_list)];

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
						$result['type'][] = _('Link to templates').': ';
					}
					else {
						$result['type'][] = _('Unlink from templates').': ';
					}

					$result['data'][] = [implode(', ', $template_list)];
					break;

				case OPERATION_TYPE_HOST_INVENTORY:
					$host_inventory_modes = getHostInventoryModes();
					$result['type'][] = operation_type2str(OPERATION_TYPE_HOST_INVENTORY).': ';
					$result['data'][] = [$host_inventory_modes[$operation['opinventory']['inventory_mode']]];
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

						$result['type'][] = _('Send message to users').': ';
						$result['data'][] = [implode(', ', $user_names_list), ' ', _('via'), ' ', $media_type];
					}

					if (array_key_exists('opmessage_grp', $operation) && $operation['opmessage_grp']) {
						$user_groups_list = [];

						foreach ($operation['opmessage_grp'] as $userGroup) {
							if (isset($user_groups[$userGroup['usrgrpid']])) {
								$user_groups_list[] = $user_groups[$userGroup['usrgrpid']]['name'];
							}
						}

						order_result($user_groups_list);

						$result['type'][] = _('Send message to user groups').': ';
						$result['data'][] = [implode(', ', $user_groups_list), ' ', _('via'), ' ', $media_type];
					}
					break;

				case OPERATION_TYPE_COMMAND:
					if ($operation['eventsource'] == EVENT_SOURCE_SERVICE) {
						$result['type'][] = _s('Run script "%1$s" on Zabbix server', $data['scripts'][$operation['opcommand']['scriptid']]['name']);
						break;
					}

					if (array_key_exists('opcommand_hst', $operation) && $operation['opcommand_hst']) {
						$host_list = [];

						foreach ($operation['opcommand_hst'] as $host) {
							if ($host['hostid'] == 0) {
								$result['type'][] = (_s('Run script "%1$s" on current host', $scripts[$operation['opcommand']['scriptid']]['name']));
							}
							elseif (isset($hosts[$host['hostid']])) {
								$host_list[] = $hosts[$host['hostid']]['name'];
							}
						}

						if ($host_list) {
							order_result($host_list);

							$result['type'][] = _s('Run script "%1$s" on hosts: ', $scripts[$operation['opcommand']['scriptid']]['name']);
							$result['data'][] = [implode(', ', $host_list)];
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

						$result['type'][] = (_s('Run script "%1$s" on host groups: ', $scripts[$operation['opcommand']['scriptid']]['name']));
						$result['data'][] = [implode(', ', $host_group_list)];
					}
					break;


				case OPERATION_TYPE_RECOVERY_MESSAGE:
				case OPERATION_TYPE_UPDATE_MESSAGE:
					$result['type'][] =_('Notify all involved');
					$result['data'][] = [];
					break;
			}
		}

		return $result;
	}
}
