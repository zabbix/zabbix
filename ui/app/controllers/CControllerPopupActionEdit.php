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

require_once dirname(__FILE__).'/../../include/actions.inc.php';
// todo: check if needed here

class CControllerPopupActionEdit extends CController {

	protected function checkInput(): bool {
		$fields = [
			'eventsource' => 'in '.implode(',', [
				// is service necessary here?
					EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_DISCOVERY,
					EVENT_SOURCE_AUTOREGISTRATION, EVENT_SOURCE_INTERNAL,
					EVENT_SOURCE_SERVICE
				]),
			'g_actionid' => 'array_id',
			'filter_set' => 'string',
			'filter_rst' =>	'string',
			'add_condition' => 'string',
			'filter_name' =>'string',
			'new_condition' => 'string',
			'filter_status' =>'in '.implode(',', [-1, ACTION_STATUS_ENABLED, ACTION_STATUS_DISABLED])
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		// is this enough?
		return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_ACTIONS);
	}

	protected function doAction(): void {

		// TODO: pass all the variables. E.g. $data: actionid, action [recovery_operations] allowedOperations
		// TODO : $operations: operationtype, opconditions, opmessage $operationid

		$eventsource = $this->getInput('eventsource');

		// if ($this->getInput('add_condition') && $this->getInput('new_condition')) {
		// $this->addCondition();
		// }

		$data = [
			'eventsource' => $eventsource,
			'actionid' => $this->getInput('g_actionid')
		];

		$response = new CControllerResponseData($data);

		$this->setResponse($response);
	}

	// TODO: fix this. check, what I need, and what I don't
	protected function addCondition() {

		$newCondition = $this->getInput('new_condition');

		if ($newCondition) {
			$conditions = $this->getInput('conditions', []);

			// When adding new condition, in order to check for an existing condition, it must have a not null value.
			if ($newCondition['conditiontype'] == CONDITION_TYPE_SUPPRESSED) {
				$newCondition['value'] = '';
			}

			// check existing conditions and remove duplicate condition values
			foreach ($conditions as $condition) {
				if ($newCondition['conditiontype'] == $condition['conditiontype']) {
					if (is_array($newCondition['value'])) {
						foreach ($newCondition['value'] as $key => $newValue) {
							if ($condition['value'] == $newValue) {
								unset($newCondition['value'][$key]);
							}
						}
					} else {
						if ($newCondition['value'] == $condition['value'] && (!array_key_exists('value2', $newCondition)
								|| $newCondition['value2'] === $condition['value2'])) {
							$newCondition['value'] = null;
						}
					}
				}
			}

			$usedFormulaIds = zbx_objectValues($conditions, 'formulaid');

			if (isset($newCondition['value'])) {
				$newConditionValues = zbx_toArray($newCondition['value']);
				foreach ($newConditionValues as $newValue) {
					$condition = $newCondition;
					$condition['value'] = $newValue;
					$condition['formulaid'] = CConditionHelper::getNextFormulaId($usedFormulaIds);
					$usedFormulaIds[] = $condition['formulaid'];
					$conditions[] = $condition;
				}
			}

			$_REQUEST['conditions'] = $conditions;
		}
	}
}
