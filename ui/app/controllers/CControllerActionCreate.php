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


class CControllerActionCreate extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'eventsource' => 'in '.implode(',', [
				EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_DISCOVERY,EVENT_SOURCE_AUTOREGISTRATION,
				EVENT_SOURCE_INTERNAL,EVENT_SOURCE_SERVICE
			]),
			'name' => 'string|required',
			'status' => 'string',
			'operations' => 'int',
			'recovery_operations' => '',
			'update_operations' => ''
		];

		$ret = $this->validateInput($fields);

//		if (!$ret) {
//			$this->setResponse(new CControllerResponseFatal());
//		}

		return true;
	}

	protected function checkPermissions(): bool {
		// todo: add permission check
		return true;
	}

	protected function doAction(): void {

		$eventsource = 0;

		// pass fake data to test if create functionality works
		$action = [
			'name' => 'testname4',
			'status' => '0',
			'eventsource' => '0',
			'operations' => [
				[
					"operationtype" => '0',
					"esc_step_from" => '1',
					"esc_period" => '0',
					"esc_step_to" => '1',
					"evaltype" => '0',
					"opmessage" => [
						"default_msg" => "1",
						"mediatypeid" => "0"
					],
					"opmessage_grp" => [
						["usrgrpid" => '7']
					]
				]
			],
			'recovery_operations' => [],
			'update_operations' => []
		];

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

//		$filter = [
//			'conditions' => [],
//			'evaltype' => '0'
//		];

//		if ($filter['conditions'] || hasRequest('update')) {
//			if ($filter['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
//				if (count($filter['conditions']) > 1) {
//					$filter['formula'] = getRequest('formula');
//				}
//				else {
//					// If only one or no conditions are left, reset the evaltype to "and/or".
//					$filter['evaltype'] = CONDITION_EVAL_TYPE_AND_OR;
//				}
//			}

//			foreach ($filter['conditions'] as &$condition) {
//				if ($filter['evaltype'] != CONDITION_EVAL_TYPE_EXPRESSION) {
//					unset($condition['formulaid']);
//				}

//				if ($condition['conditiontype'] == CONDITION_TYPE_SUPPRESSED) {
//					unset($condition['value']);
//				}

//				if ($condition['conditiontype'] != CONDITION_TYPE_EVENT_TAG_VALUE) {
//					unset($condition['value2']);
//				}
//			}
//			unset($condition);

//			$action['filter'] = $filter;
//		}

//		if (in_array($eventsource, [EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_INTERNAL, EVENT_SOURCE_SERVICE])) {
//			$action['esc_period'] = getRequest('esc_period', DB::getDefault('actions', 'esc_period'));
//		}

//		if ($eventsource == EVENT_SOURCE_TRIGGERS) {
//			$action['pause_suppressed'] = getRequest('pause_suppressed', ACTION_PAUSE_SUPPRESSED_FALSE);
//			$action['notify_if_canceled'] = getRequest('notify_if_canceled', ACTION_NOTIFY_IF_CANCELED_FALSE);
//		}

//		switch ($eventsource) {
//			case EVENT_SOURCE_DISCOVERY:
//			case EVENT_SOURCE_AUTOREGISTRATION:
//				unset($action['recovery_operations']);
//			// break; is not missing here

//			case EVENT_SOURCE_INTERNAL:
//				unset($action['update_operations']);
//				break;
//		}

		DBstart();

		$result = API::Action()->create($action);

		$messageSuccess = _('Action added');
		$messageFailed = _('Cannot add action');

//		if ($result) {
//			unset($_REQUEST['form']);
//		}

		$result = DBend($result);

		if ($result) {
			uncheckTableRows($eventsource);
		}
		show_messages($result, $messageSuccess, $messageFailed);
	}
}
