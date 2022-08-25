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


class CControllerActionUpdate extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		// TODO: check If additional fields are necessarry
		$fields = [
			'eventsource' => 'required|in '.implode(',', [
					EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_DISCOVERY,EVENT_SOURCE_AUTOREGISTRATION,
					EVENT_SOURCE_INTERNAL,EVENT_SOURCE_SERVICE
			]),
			'name' => 'string|required|not_empty',
			'actionid' => 'id',
			'status' => 'in '.ACTION_STATUS_ENABLED,
			'operations' => 'array',
			'recovery_operations' => 'array',
			'update_operations' => 'array',
			'esc_period' => 'string|not_empty',
			'filter' => 'array',
			'conditions' => 'array',
			'evaltype' => 'in '.implode(',', [
				CONDITION_EVAL_TYPE_AND_OR, CONDITION_EVAL_TYPE_AND, CONDITION_EVAL_TYPE_OR,
				CONDITION_EVAL_TYPE_EXPRESSION
			]),
			'formula' => 'string'
		];

		$ret = $this->validateInput($fields);

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

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_ACTIONS);
	}

	protected function doAction(): void {
		$eventsource = $this->getInput('eventsource');
		// todo : receive operations tables data from form

//		foreach (['operations', 'recovery_operations', 'update_operations'] as $operation_group) {
//			foreach ($action[$operation_group] as &$operation) {
//				if ($operation_group === 'operations') {
//					if ($eventsource == EVENT_SOURCE_TRIGGERS) {
//						if (array_key_exists('opconditions', $operation)) {
//							foreach ($operation['opconditions'] as &$opcondition) {
//								unset($opcondition['opconditionid'], $opcondition['operationid']);
//							}
//							unset($opcondition);
//						}
//						else {
//							$operation['opconditions'] = [];
//						}
//					}
//					elseif ($eventsource == EVENT_SOURCE_DISCOVERY || $eventsource == EVENT_SOURCE_AUTOREGISTRATION) {
//						unset($operation['esc_period'], $operation['esc_step_from'], $operation['esc_step_to'],
//							$operation['evaltype']
//						);
//					}
//					elseif ($eventsource == EVENT_SOURCE_INTERNAL || $eventsource == EVENT_SOURCE_SERVICE) {
//						unset($operation['evaltype']);
//					}
//				}
//				elseif ($operation_group === 'recovery_operations') {
//					if ($operation['operationtype'] != OPERATION_TYPE_MESSAGE) {
//						unset($operation['opmessage']['mediatypeid']);
//					}

//					if ($operation['operationtype'] == OPERATION_TYPE_COMMAND) {
//						unset($operation['opmessage']);
//					}
//				}

//				if (array_key_exists('opmessage', $operation)) {
//					if (!array_key_exists('default_msg', $operation['opmessage'])) {
//						$operation['opmessage']['default_msg'] = 1;
//					}

//					if ($operation['opmessage']['default_msg'] == 1) {
//						unset($operation['opmessage']['subject'], $operation['opmessage']['message']);
//					}
//				}

//				if (array_key_exists('opmessage_grp', $operation) || array_key_exists('opmessage_usr', $operation)) {
//					if (!array_key_exists('opmessage_grp', $operation)) {
//						$operation['opmessage_grp'] = [];
//					}

//					if (!array_key_exists('opmessage_usr', $operation)) {
//						$operation['opmessage_usr'] = [];
//					}
//				}

//				if (array_key_exists('opcommand_grp', $operation) || array_key_exists('opcommand_hst', $operation)) {
//					if (array_key_exists('opcommand_grp', $operation)) {
//						foreach ($operation['opcommand_grp'] as &$opcommand_grp) {
//							unset($opcommand_grp['opcommand_grpid']);
//						}
//						unset($opcommand_grp);
//					}
//					else {
//						$operation['opcommand_grp'] = [];
//					}

//					if (array_key_exists('opcommand_hst', $operation)) {
//						foreach ($operation['opcommand_hst'] as &$opcommand_hst) {
//							unset($opcommand_hst['opcommand_hstid']);
//						}
//						unset($opcommand_hst);
//					}
//					else {
//						$operation['opcommand_hst'] = [];
//					}
//				}

//				unset($operation['operationid'], $operation['actionid'], $operation['eventsource'], $operation['recovery'],
//					$operation['id']
//				);
//			}
//			unset($operation);
//		}

		$action = [
			'name' => $this->getInput('name'),
			'actionid' => $this->getInput('actionid'),
			'status' => $this->hasInput('status') ? ACTION_STATUS_ENABLED : ACTION_STATUS_DISABLED,
			'eventsource' => $eventsource,
			'operations' => [
				[
					"esc_step_from" => '1',
					'esc_step_to' => '1',
					'opmessage_grp' => [
						['usrgrpid' => '8']
					],
					'opmessage' => [
						'mediatypeid' => '0',
						'default_msg' => '1'
					],
					'evaltype' => '0',
					'operationtype' => '0',
					'opconditions' => [],
					'opmessage_usr' => []
				]
			],
			'recovery_operations' => [],
			'update_operations' => []
		];

		$filter = [
			'conditions' => $this->getInput('conditions', []),
			'evaltype' => $this->getInput('evaltype')
		];

		if ($filter['conditions']) {

			if ($filter['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
				if (count($filter['conditions']) > 1) {
					$filter['formula'] = $this->getInput('formula');
				}
				else {
					// If only one or no conditions are left, reset the evaltype to "and/or".
					$filter['evaltype'] = CONDITION_EVAL_TYPE_AND_OR;
				}
			}

			foreach ($filter['conditions'] as &$condition) {
				if ($filter['evaltype'] != CONDITION_EVAL_TYPE_EXPRESSION) {
					unset($condition['formulaid']);
				}

				if ($condition['conditiontype'] == CONDITION_TYPE_SUPPRESSED) {
					unset($condition['value']);
				}

				if ($condition['conditiontype'] != CONDITION_TYPE_EVENT_TAG_VALUE) {
					unset($condition['value2']);
				}
			}
			unset($condition);

			$action['filter'] = $filter;
		}

		if (in_array($eventsource, [EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_INTERNAL, EVENT_SOURCE_SERVICE])) {
			$action['esc_period'] = $this->getInput('esc_period', DB::getDefault('actions', 'esc_period'));
		}

		if ($eventsource == EVENT_SOURCE_TRIGGERS) {
			$action['pause_suppressed'] = $this->getInput('pause_suppressed', ACTION_PAUSE_SUPPRESSED_FALSE);
			$action['notify_if_canceled'] = $this->getInput('notify_if_canceled', ACTION_NOTIFY_IF_CANCELED_FALSE);
		}

		if ($eventsource == EVENT_SOURCE_AUTOREGISTRATION) {
			// todo : remove this. this is just for testing
			$action['operations'][0]['operationtype'] = '4';
			$action['operations'][0]['opgroup'] = [['groupid' => '2']];
			unset(
				$action['operations'][0]['opmessage'], $action['operations'][0]['opmessage_usr'],
				$action['operations'][0]['opmessage_grp']
			);
		}

		if ($eventsource == EVENT_SOURCE_INTERNAL || $eventsource == EVENT_SOURCE_SERVICE ||
				$eventsource == EVENT_SOURCE_DISCOVERY || $eventsource == EVENT_SOURCE_AUTOREGISTRATION) {
			// todo : remove this. this is just for testing
			unset ($action['operations'][0]['evaltype'], $action['operations'][0]['opconditions']);
		}

		if ($eventsource == EVENT_SOURCE_DISCOVERY) {
			// todo : remove this. this is just for testing
			unset ($action['operations'][0]['esc_step_from'], $action['operations'][0]['esc_step_to']);
		}

		if ($eventsource == EVENT_SOURCE_AUTOREGISTRATION) {
			// todo : remove this. this is just for testing
			unset ($action['operations'][0]['esc_step_from'], $action['operations'][0]['esc_step_to']);
		}

		switch ($eventsource) {
			case EVENT_SOURCE_DISCOVERY:
			case EVENT_SOURCE_AUTOREGISTRATION:
				unset($action['recovery_operations']);
			// break; is not missing here

			case EVENT_SOURCE_INTERNAL:
				unset($action['update_operations']);
				break;
		}

		DBstart();

		$result = API::Action()->update($action);

		$result = DBend($result);

		if ($result) {
			$output['success']['title'] =  _('Action updated');

			if ($messages = get_and_clear_messages()) {
				$output['success']['messages'] = array_column($messages, 'message');
			}
		}
		else {
			$output['error'] = [
				'title' => _('Cannot update action'),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
