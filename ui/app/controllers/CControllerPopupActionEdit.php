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


class CControllerPopupActionEdit extends CController {

	protected function checkInput(): bool {
		$eventsource = [
			EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_DISCOVERY, EVENT_SOURCE_AUTOREGISTRATION,
			EVENT_SOURCE_INTERNAL, EVENT_SOURCE_SERVICE
		];

		$fields = [
			'eventsource' =>	'required|db actions.eventsource|in '.implode(',', $eventsource),
			'actionid' =>		'db actions.actionid'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		switch ($this->getInput('eventsource')) {
			case EVENT_SOURCE_TRIGGERS:
				$has_permission = $this->checkAccess(CRoleHelper::UI_CONFIGURATION_TRIGGER_ACTIONS);
				break;

			case EVENT_SOURCE_DISCOVERY:
				$has_permission =  $this->checkAccess(CRoleHelper::UI_CONFIGURATION_DISCOVERY_ACTIONS);
				break;

			case EVENT_SOURCE_AUTOREGISTRATION:
				$has_permission =  $this->checkAccess(CRoleHelper::UI_CONFIGURATION_AUTOREGISTRATION_ACTIONS);
				break;

			case EVENT_SOURCE_INTERNAL:
				$has_permission =  $this->checkAccess(CRoleHelper::UI_CONFIGURATION_INTERNAL_ACTIONS);
				break;

			case EVENT_SOURCE_SERVICE:
				$has_permission =  $this->checkAccess(CRoleHelper::UI_CONFIGURATION_SERVICE_ACTIONS);
				break;
		}

		if (!$has_permission) {
			return false;
		}

		if ($this->hasInput('actionid')) {
			$this->action = API::Action()->get([
				'output' => [
					'actionid', 'name', 'esc_period', 'eventsource', 'status', 'pause_suppressed', 'notify_if_canceled'
				],
				'actionids' => $this->getInput('actionid'),
				'selectOperations' => 'extend',
				'selectRecoveryOperations' => 'extend',
				'selectUpdateOperations' => 'extend',
				'selectFilter' => 'extend'
			]);

			if (!$this->action) {
				return false;
			}
			$this->action = $this->action[0];
		}
		else {
			$this->action = null;
		}

		return true;
	}

	protected function doAction(): void {
		$eventsource = $this->getInput('eventsource', EVENT_SOURCE_TRIGGERS);

		if ($this->action !== null) {
			$formula = array_key_exists('formula', $this->action['filter'])
				? $this->action['filter']['formula']
				: '';

			sortOperations($eventsource, $this->action['operations']);
			CArrayHelper::sort($this->action['recovery_operations'], ['operationtype']);
			CArrayHelper::sort($this->action['update_operations'], ['operationtype']);

			$data = [
				'eventsource' => $eventsource,
				'actionid' => $this->action['actionid'],
				'action' => [
					'name' => $this->action['name'],
					'esc_period' => $this->action['esc_period'],
					'eventsource' => $eventsource,
					'status' => $this->action['status'],
					'operations' => $this->action['operations'],
					'recovery_operations' => $this->action['recovery_operations'],
					'update_operations' => $this->action['update_operations'],
					'filter' => $this->action['filter'],
					'pause_suppressed' => $this->action['pause_suppressed'],
					'notify_if_canceled' =>  $this->action['notify_if_canceled']
				],
				'formula' => $formula,
				'allowedOperations' => getAllowedOperations($eventsource)
			];

			foreach ($data['action']['filter']['conditions'] as  $row_index => &$condition) {
				$condition_names = actionConditionValueToString([$data['action']]);
				$data['condition_name'][] = $condition_names[0][$row_index];
				$condition += [
					'row_index' => $row_index,
					'name' => $condition_names[0][$row_index]
				];
			}
			unset ($condition);

			$data['action']['filter']['conditions'] = CConditionHelper::sortConditionsByFormulaId(
				$data['action']['filter']['conditions']
			);

			foreach ($data['action']['operations'] as &$operation) {
				$action = [
					'esc_period' => $this->action['esc_period'],
					'eventsource' => $eventsource,
					'operations' => [$operation],
					'recovery_operations' => [],
					'update_operations' => [],
				];

				$operation['details'] = $this->getData($operation['operationtype'], [$action], ACTION_OPERATION);
				$operation['recovery'] = ACTION_OPERATION;
			}

			unset($operation);
			foreach ($data['action']['recovery_operations'] as &$operation) {
				$action = [
					'esc_period' => $this->action['esc_period'],
					'eventsource' => $eventsource,
					'operations' => [],
					'recovery_operations' => [$operation],
					'update_operations' => [],
				];

				$operation['details'] = $this->getData($operation['operationtype'], [$action], ACTION_RECOVERY_OPERATION);
				$operation['recovery'] = ACTION_RECOVERY_OPERATION;
			}

			unset($operation);
			foreach ($data['action']['update_operations'] as &$operation) {
				$action = [
					'esc_period' => $this->action['esc_period'],
					'eventsource' => $eventsource,
					'operations' => [],
					'recovery_operations' => [],
					'update_operations' => [$operation],
				];

				$operation['details'] = $this->getData($operation['operationtype'], [$action], ACTION_UPDATE_OPERATION);
				$operation['recovery'] = ACTION_UPDATE_OPERATION;
			}
			unset($operation);

		}
		else {
			$data = [
				'eventsource' => $eventsource,
				'actionid' => $this->getInput('actionid', ''),
				'action' => [
					'name' => '',
					'esc_period' => DB::getDefault('actions', 'esc_period'),
					'eventsource' => $eventsource,
					'status' => '',
					'operations' => [],
					'recovery_operations' => [],
					'update_operations' => [],
					'filter' => [
						'conditions' => [],
						'evaltype' => ''
					],
					'pause_suppressed' => ACTION_PAUSE_SUPPRESSED_TRUE,
					'notify_if_canceled' =>  ACTION_NOTIFY_IF_CANCELED_TRUE
				],
				'formula' => $this->getInput('formula', ''),
				'allowedOperations' => getAllowedOperations($eventsource)
			];
		}

		$response = new CControllerResponseData($data);
		$this->setResponse($response);
	}

	protected function getData(int $operationtype, array $action, int $type): array {
		$data = getActionOperationData($action, $type);
		$eventsource = $action[0]['eventsource'];

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
						$result['data'][] = [implode(', ', $user_names_list), _('via'), $media_type];
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
					if ($eventsource == EVENT_SOURCE_SERVICE) {
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
						$result['data'][] = [implode(', ', $user_names_list), _('via'), $media_type];
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
					if ($eventsource == EVENT_SOURCE_SERVICE) {
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
