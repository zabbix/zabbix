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
		$fields = [
			'eventsource' => 'in '.implode(',', [
					EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_DISCOVERY, EVENT_SOURCE_AUTOREGISTRATION,
					EVENT_SOURCE_INTERNAL, EVENT_SOURCE_SERVICE
				]),
			'g_actionid' => 'array_id',
			'actionid' => 'string',
			'filter_set' => 'string',
			'filter_rst' =>	'string',
			'form_refresh' => 'string',
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
		$eventsource = $this->getInput('eventsource');
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
				'formula' => $this->getInput('formula', ''),
				'allowedOperations' => getAllowedOperations($eventsource)
			];
			foreach ($data['action']['filter']['conditions'] as $condition) {
				$condition_name = $this->conditionValueToString($condition);
				$data['condition_name'][] = $condition_name;
			}
			$data['action']['filter']['conditions'] = CConditionHelper::sortConditionsByFormulaId(
				$data['action']['filter']['conditions']
			);
		}
		else {
			$data = [
				'eventsource' => $eventsource,
				'actionid' => $this->getInput('actionid', ''),
				'action' => [
					'name' => '',
					'esc_period' => DB::getDefault('actions', 'esc_period'),
					'eventsource' => $eventsource,
					'status' =>'',
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

		if ($data['actionid'] && $this->hasInput('form_refresh')) {
			if ($data['eventsource'] == EVENT_SOURCE_TRIGGERS) {
				$data['action']['pause_suppressed'] = $this->getInput('pause_suppressed', ACTION_PAUSE_SUPPRESSED_FALSE);
				$data['action']['notify_if_canceled'] = $this->getInput('notify_if_canceled',
					ACTION_NOTIFY_IF_CANCELED_FALSE
				);
			}
		}
//		else {
//			if ($data['eventsource'] == EVENT_SOURCE_TRIGGERS) {
//				$data['action']['pause_suppressed'] = $this->getInput('pause_suppressed',
//					$this->hasInput('form_refresh') ? ACTION_PAUSE_SUPPRESSED_FALSE : ACTION_PAUSE_SUPPRESSED_TRUE
//				);
//				$data['action']['notify_if_canceled'] = $this->getInput('notify_if_canceled',
//					$this->hasInput('form_refresh') ? ACTION_NOTIFY_IF_CANCELED_FALSE : ACTION_NOTIFY_IF_CANCELED_TRUE
//				);
//			}
//		}

		$response = new CControllerResponseData($data);
		$this->setResponse($response);
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

		switch ($condition['conditiontype']) {
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

			switch ($condition['conditiontype']) {
				case CONDITION_TYPE_HOST_GROUP:
					if (array_key_exists($id, $groups)) {
						$result = $groups[$id]['name'];
					}
					break;

				case CONDITION_TYPE_TRIGGER:
					if (array_key_exists($id, $triggers)) {
						$host = reset($triggers[$id]['hosts']);
						$result = $host['name'] . NAME_DELIMITER . $triggers[$id]['description'];
					}
					break;

				case CONDITION_TYPE_HOST:
					if (array_key_exists($id, $hosts)) {
						$result = $hosts[$id]['name'];
					}
					break;

				case CONDITION_TYPE_TEMPLATE:
					if (array_key_exists($id, $templates)) {
						$result = $templates[$id]['name'];
					}
					break;

				case CONDITION_TYPE_PROXY:
					if (array_key_exists($id, $proxies)) {
						$result = $proxies[$id]['host'];
					}
					break;

				case CONDITION_TYPE_DRULE:
					if (array_key_exists($id, $dRules)) {
						$result = $dRules[$id]['name'];
					}
					break;

				case CONDITION_TYPE_DCHECK:
					if (array_key_exists($id, $dChecks)) {
						$drule = reset($dChecks[$id]['drules']);
						$type = $dChecks[$id]['type'];
						$key_ = $dChecks[$id]['key_'];
						$ports = $dChecks[$id]['ports'];

						$dCheck = discovery_check2str($type, $key_, $ports);

						$result = $drule['name'] . NAME_DELIMITER . $dCheck;
					}
					break;

				case CONDITION_TYPE_SERVICE:
					if (array_key_exists($id, $services)) {
						$result = $services[$id]['name'];
					}
					break;
			}
		}

		$result_arr[] = $result;
		return $result_arr;
	}
}
