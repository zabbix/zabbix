<?php declare(strict_types = 0);
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


require_once __DIR__.'/../../include/actions.inc.php';

class CControllerPopupActionConditionEdit extends CController {

	protected function init() {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'actionid' =>			'db actions.actionid',
			'type' =>				'required|in '.ZBX_POPUP_CONDITION_TYPE_ACTION,
			'source' =>				'required|in '.implode(',', [
										EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_DISCOVERY, EVENT_SOURCE_AUTOREGISTRATION,
										EVENT_SOURCE_INTERNAL, EVENT_SOURCE_SERVICE
									]),
			'condition_type' =>		'db conditions.conditiontype|in '.implode(',', array_keys(condition_type2str())),
			'operator' =>			'db conditions.operator|in '.implode(',', array_keys(condition_operator2str())),
			'trigger_context' =>	'in '.implode(',', ['host', 'template']),
			'row_index' =>			'required|int32'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		switch ($this->getInput('source')) {
			case EVENT_SOURCE_TRIGGERS:
				return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_TRIGGER_ACTIONS);

			case EVENT_SOURCE_DISCOVERY:
				return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_DISCOVERY_ACTIONS);

			case EVENT_SOURCE_AUTOREGISTRATION:
				return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_AUTOREGISTRATION_ACTIONS);

			case EVENT_SOURCE_INTERNAL:
				return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_INTERNAL_ACTIONS);

			case EVENT_SOURCE_SERVICE:
				return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_SERVICE_ACTIONS);
		}

		return false;
	}

	protected function getConditionLastType(): string {
		$default = [
			EVENT_SOURCE_TRIGGERS => ZBX_CONDITION_TYPE_EVENT_NAME,
			EVENT_SOURCE_DISCOVERY => ZBX_CONDITION_TYPE_DHOST_IP,
			EVENT_SOURCE_AUTOREGISTRATION => ZBX_CONDITION_TYPE_HOST_NAME,
			EVENT_SOURCE_INTERNAL => ZBX_CONDITION_TYPE_EVENT_TYPE,
			EVENT_SOURCE_SERVICE => ZBX_CONDITION_TYPE_SERVICE
		];

		$last_type = CProfile::get('popup.condition.actions_last_type', $default[$this->getInput('source')],
			$this->getInput('source')
		);

		if ($this->hasInput('condition_type') && $this->getInput('condition_type') != $last_type) {
			CProfile::update('popup.condition.actions_last_type', $this->getInput('condition_type'),
				PROFILE_TYPE_INT, $this->getInput('source')
			);
			$last_type = $this->getInput('condition_type');
		}

		return $last_type;
	}

	protected function doAction(): void {
		$data = [
			'title' => _('New condition'),
			'command' => '',
			'row_index' => $this->getInput('row_index'),
			'message' => '',
			'errors' => null,
			'action' => $this->getAction(),
			'type' => $this->getInput('type'),
			'last_type' => $this->getConditionLastType(),
			'eventsource' => $this->getInput('source'),
			'allowed_conditions' => get_conditions_by_eventsource($this->getInput('source')),
			'trigger_context' => $this->getInput('trigger_context', 'host'),
			'user' => ['debug_mode' => $this->getDebugMode()],
			'actionid' => $this->getInput('actionid', '')
		];

		$response = new CControllerResponseData($data);
		$this->setResponse($response);
	}
}
