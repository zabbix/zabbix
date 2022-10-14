<?php
declare(strict_types=0);
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

require_once __DIR__ . '/../../include/actions.inc.php';

class CControllerPopupActionConditionEdit extends CController {
	protected function checkInput(): bool {
		$fields = [
			'actionid' => 'string',
			'type' => 'required|in '.ZBX_POPUP_CONDITION_TYPE_ACTION,
			'source' => 'required|in '.implode(',', [
					EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_DISCOVERY, EVENT_SOURCE_AUTOREGISTRATION, EVENT_SOURCE_INTERNAL,
					EVENT_SOURCE_SERVICE
				]),
			'condition_type' => 'in '.implode(',', [
					CONDITION_TYPE_HOST_GROUP, CONDITION_TYPE_TEMPLATE, CONDITION_TYPE_HOST, CONDITION_TYPE_TRIGGER,
					CONDITION_TYPE_TRIGGER_NAME, CONDITION_TYPE_TRIGGER_SEVERITY, CONDITION_TYPE_TIME_PERIOD,
					CONDITION_TYPE_SUPPRESSED, CONDITION_TYPE_DRULE, CONDITION_TYPE_DCHECK, CONDITION_TYPE_DOBJECT,
					CONDITION_TYPE_PROXY, CONDITION_TYPE_DHOST_IP, CONDITION_TYPE_DSERVICE_TYPE,
					CONDITION_TYPE_DSERVICE_PORT, CONDITION_TYPE_DSTATUS, CONDITION_TYPE_DUPTIME, CONDITION_TYPE_DVALUE,
					CONDITION_TYPE_EVENT_ACKNOWLEDGED, CONDITION_TYPE_HOST_NAME, CONDITION_TYPE_EVENT_TYPE,
					CONDITION_TYPE_HOST_METADATA, CONDITION_TYPE_EVENT_TAG, CONDITION_TYPE_EVENT_TAG_VALUE,
					CONDITION_TYPE_SERVICE, CONDITION_TYPE_SERVICE_NAME
				]),
			'row_index' => 'required|int32',
			'trigger_context' => 'in '.implode(',', ['host', 'template']),
			'operator' => 'in '.implode(',', [
					CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL, CONDITION_OPERATOR_LIKE,
					CONDITION_OPERATOR_NOT_LIKE, CONDITION_OPERATOR_IN, CONDITION_OPERATOR_MORE_EQUAL,
					CONDITION_OPERATOR_LESS_EQUAL, CONDITION_OPERATOR_NOT_IN, CONDITION_OPERATOR_YES,
					CONDITION_OPERATOR_NO, CONDITION_OPERATOR_REGEXP, CONDITION_OPERATOR_NOT_REGEXP
				])
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		$eventsource = $this->getInput('source');
		$has_permission = false;

		switch ($eventsource) {
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

		return $has_permission;
	}

	protected function getConditionLastType(): string {
		$default = [
			EVENT_SOURCE_TRIGGERS => CONDITION_TYPE_TRIGGER_NAME,
			EVENT_SOURCE_DISCOVERY => CONDITION_TYPE_DHOST_IP,
			EVENT_SOURCE_AUTOREGISTRATION => CONDITION_TYPE_HOST_NAME,
			EVENT_SOURCE_INTERNAL => CONDITION_TYPE_EVENT_TYPE,
			EVENT_SOURCE_SERVICE => CONDITION_TYPE_SERVICE
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
			'actionid' => $this->hasInput('actionid') ? $this->getInput('actionid') : ''
		];

		$response = new CControllerResponseData($data);
		$this->setResponse($response);
	}
}
