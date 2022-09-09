<?php
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


/**
 * Actions new condition popup.
 */
class CControllerActionConditionCheck extends CController {
	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
		$this->disableSIDvalidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'actionid' => 'db actions.actionid',
			'type' => 'required|in ' . ZBX_POPUP_CONDITION_TYPE_ACTION,
			'source' => 'required|in ' . implode(',', [
					EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_DISCOVERY, EVENT_SOURCE_AUTOREGISTRATION,
					EVENT_SOURCE_INTERNAL, EVENT_SOURCE_SERVICE
			]),
			'condition_type' => 'in ' . implode(',', [
					CONDITION_TYPE_HOST_GROUP, CONDITION_TYPE_TEMPLATE, CONDITION_TYPE_HOST, CONDITION_TYPE_TRIGGER,
					CONDITION_TYPE_TRIGGER_NAME, CONDITION_TYPE_TRIGGER_SEVERITY, CONDITION_TYPE_TIME_PERIOD,
					CONDITION_TYPE_SUPPRESSED, CONDITION_TYPE_DRULE, CONDITION_TYPE_DCHECK, CONDITION_TYPE_DOBJECT,
					CONDITION_TYPE_PROXY, CONDITION_TYPE_DHOST_IP, CONDITION_TYPE_DSERVICE_TYPE,
					CONDITION_TYPE_DSERVICE_PORT, CONDITION_TYPE_DSTATUS, CONDITION_TYPE_DUPTIME, CONDITION_TYPE_DVALUE,
					CONDITION_TYPE_EVENT_ACKNOWLEDGED, CONDITION_TYPE_HOST_NAME, CONDITION_TYPE_EVENT_TYPE,
					CONDITION_TYPE_HOST_METADATA, CONDITION_TYPE_EVENT_TAG, CONDITION_TYPE_EVENT_TAG_VALUE,
					CONDITION_TYPE_SERVICE, CONDITION_TYPE_SERVICE_NAME
			]),
			'trigger_context' => 'in ' . implode(',', ['host', 'template']),
			'operator' => 'in ' . implode(',', [
					CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL, CONDITION_OPERATOR_LIKE,
					CONDITION_OPERATOR_NOT_LIKE, CONDITION_OPERATOR_IN, CONDITION_OPERATOR_MORE_EQUAL,
					CONDITION_OPERATOR_LESS_EQUAL, CONDITION_OPERATOR_NOT_IN, CONDITION_OPERATOR_YES, CONDITION_OPERATOR_NO,
					CONDITION_OPERATOR_REGEXP, CONDITION_OPERATOR_NOT_REGEXP
			]),
			'value' => 'required|not_empty',
			'value2' => 'not_empty'
		];

		$ret = $this->validateInput($fields) && $this->validateCondition();

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
		return ($this->getUserType() >= USER_TYPE_ZABBIX_ADMIN);
	}

	protected function validateCondition(): bool {
		$validator = new CActionCondValidator();
		$is_valid = $validator->validate([
			'conditiontype' => $this->getInput('condition_type'),
			'value' => $this->getInput('value'),
			'value2' => $this->hasInput('value2') ? $this->getInput('value2') : null,
			'operator' => $this->getInput('operator')
		]);

		if (!$is_valid) {
			error($validator->getError());
		}

		return $is_valid;
	}

	/**
	 * @throws JsonException
	 */
	protected function doAction(): void {
		$condition = [
			'condition_type' => $this->getInput('condition_type'),
			'operator' => $this->getInput('operator'),
			'value' => $this->getInput('value'),
			'value2' => $this->getInput('value2')
		];
		$actionConditionStringValues = $this->conditionValueToString($condition);

		$data = [
			'title' => _('New condition'),
			'command' => '',
			'message' => '',
			'errors' => null,
			'action' => $this->getAction(),
			'type' => $this->getInput('type'),
			'conditiontype' => $this->getInput('condition_type'),
			'value' => $this->getInput('value'),
			'value2' => $this->hasInput('value2') ? $this->getInput('value2') : null,
			'operator' => $this->getInput('operator'),
			//'last_type' => $this->getConditionLastType(),
			'eventsource' => $this->getInput('source'),
			'allowed_conditions' => get_conditions_by_eventsource($this->getInput('source')),
			'trigger_context' => $this->getInput('trigger_context', 'host'),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			],
			'name' => $actionConditionStringValues
		];

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($data, JSON_THROW_ON_ERROR)]));
	}

	protected function conditionValueToString($condition): array {
		$groupIds = [];
		$triggerIds = [];
		$hostIds = [];
		$templateIds = [];
		$proxyIds = [];
		$dRuleIds = [];
		$dCheckIds = [];
		$serviceids = [];

		$result = _('Unknown');

		switch ($condition['condition_type']) {
			case CONDITION_TYPE_HOST_GROUP:
				$groupIds = $condition['value'];
				break;

			case CONDITION_TYPE_TRIGGER:
				$triggerIds = $condition['value'];
				break;

			case CONDITION_TYPE_HOST:
				$hostIds = $condition['value'];
				break;

			case CONDITION_TYPE_TEMPLATE:
				$templateIds = $condition['value'];
				break;

			case CONDITION_TYPE_PROXY:
				$proxyIds = $condition['value'];
				break;

			case CONDITION_TYPE_SERVICE:
				$serviceids = $condition['value'];
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
			case CONDITION_TYPE_EVENT_TAG:
			case CONDITION_TYPE_EVENT_TAG_VALUE:
			case CONDITION_TYPE_SERVICE_NAME:
				$result = $condition['value'];
				break;

			case CONDITION_TYPE_EVENT_ACKNOWLEDGED:
				$result = $condition['value'] ? _('Ack') : _('Not Ack');
				break;

			case CONDITION_TYPE_TRIGGER_SEVERITY:
				$result =CSeverityHelper::getName((int)$condition['value']);
				break;

			case CONDITION_TYPE_DRULE:
				$dRuleIds[$condition['value']] = $condition['value'];
				break;

			case CONDITION_TYPE_DCHECK:
				$dCheckIds[$condition['value']] = $condition['value'];
				break;

			case CONDITION_TYPE_DOBJECT:
				$result = discovery_object2str($condition['value']);
				break;

			case CONDITION_TYPE_DSERVICE_TYPE:
				$result = discovery_check_type2str($condition['value']);
				break;

			case CONDITION_TYPE_DSTATUS:
				$result = discovery_object_status2str($condition['value']);
				break;

			case CONDITION_TYPE_EVENT_TYPE:
				$result = eventType($condition['value']);
				break;
		}

		$groups = [];
		$triggers = [];
		$hosts = [];
		$templates = [];
		$proxies = [];
		$dRules = [];
		$dChecks = [];
		$services = [];

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

		if ($serviceids) {
			$services = API::Service()->get([
				'output' => ['name'],
				'serviceids' => $serviceids,
				'preservekeys' => true
			]);
		}

		if ($groups || $triggers || $hosts || $templates || $proxies || $dRules || $dChecks || $services) {
			$id = $condition['value'];

			switch ($condition['condition_type']) {
				case CONDITION_TYPE_HOST_GROUP:
					if (isset($groups[$groupIds[0]])) {
						$result = $groups[$groupIds[0]]['name'];
					}
					break;

				case CONDITION_TYPE_TRIGGER:
					if (isset($triggers[$triggerIds[0]])) {
						$host = reset($triggers[$triggerIds[0]]['hosts']);
						$result = $host['name'] . NAME_DELIMITER . $triggers[$triggerIds[0]]['description'];
					}
					break;

				case CONDITION_TYPE_HOST:
					if (isset($hosts[$hostIds[0]])) {
						$result = $hosts[$hostIds[0]]['name'];
					}
					break;

				case CONDITION_TYPE_TEMPLATE:
					if (isset($templates[$templateIds[0]])) {
						$result = $templates[$templateIds[0]]['name'];
					}
					break;

				case CONDITION_TYPE_PROXY:
					if (isset($proxies[$proxyIds[0]])) {
						$result = $proxies[$proxyIds[0]]['host'];
					}
					break;

				case CONDITION_TYPE_DRULE:
					if (isset($dRules[$id])) {
						$result = $dRules[$id]['name'];
					}
					break;

				case CONDITION_TYPE_DCHECK:
					if (isset($dChecks[$id])) {
						$drule = reset($dChecks[$id]['drules']);
						$type = $dChecks[$id]['type'];
						$key_ = $dChecks[$id]['key_'];
						$ports = $dChecks[$id]['ports'];

						$dCheck = discovery_check2str($type, $key_, $ports);

						$result = $drule['name'] . NAME_DELIMITER . $dCheck;
					}
					break;

				case CONDITION_TYPE_SERVICE:
					if (isset($services[$serviceids[0]])) {
						$result = $services[$serviceids[0]]['name'];
					}
					break;
			}
		}

		$result_arr[] = $result;
		return $result_arr;
	}
}
