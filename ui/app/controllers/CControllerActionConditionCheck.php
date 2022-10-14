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


class CControllerActionConditionCheck extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
		$this->disableSIDvalidation();
	}

	protected function checkInput(): bool {
		$condition_types = array_keys(condition_type2str());
		$condition_operators = array_keys(condition_operator2str());

		$fields = [
			'actionid' =>			'db actions.actionid',
			'type' =>				'required|in '.ZBX_POPUP_CONDITION_TYPE_ACTION,
			'source' =>				'required|in '.implode(',', [
										EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_DISCOVERY, EVENT_SOURCE_AUTOREGISTRATION,
										EVENT_SOURCE_INTERNAL, EVENT_SOURCE_SERVICE
									]),
			'condition_type' =>		'in '.implode(',', $condition_types),
			'trigger_context' =>	'in '.implode(',', ['host', 'template']),
			'operator' =>			'in '.implode(',', $condition_operators),
			'value' =>				'',
			'value2' =>				'not_empty',
			'row_index' =>			'int32'
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
		return $this->getUserType() >= USER_TYPE_ZABBIX_ADMIN;
	}

	protected function validateCondition(): bool {
		$validator = new CActionCondValidator();
		$is_valid = $validator->validate([
			'conditiontype' => $this->getInput('condition_type'),
			'value' => $this->hasInput('value') ? $this->getInput('value') : null,
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
			'value' => $this->hasInput('value') ? $this->getInput('value') : null,
			'value2' => $this->hasInput('value2') ? $this->getInput('value2') : null
		];
		$actionConditionStringValues = $this->conditionValueToString($condition);

		$data = [
			'title' => _('New condition'),
			'command' => '',
			'row_index' => $this->getInput('row_index'),
			'message' => '',
			'errors' => null,
			'action' => $this->getAction(),
			'type' => $this->getInput('type'),
			'conditiontype' => $this->getInput('condition_type'),
			'value' => $this->hasInput('value') ? $this->getInput('value') : null,
			'value2' => $this->hasInput('value2') ? $this->getInput('value2') : null,
			'operator' => $this->getInput('operator'),
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
		$groupids = [];
		$triggerids = [];
		$hostids = [];
		$templateids = [];
		$druleids = [];
		$dcheckids = [];
		$serviceids = [];
		$proxyids = 0;

		$result = _('Unknown');

		switch ($condition['condition_type']) {
			case CONDITION_TYPE_HOST_GROUP:
				$groupids = $condition['value'];
				break;

			case CONDITION_TYPE_TRIGGER:
				$triggerids = $condition['value'];
				break;

			case CONDITION_TYPE_HOST:
				$hostids = $condition['value'];
				break;

			case CONDITION_TYPE_TEMPLATE:
				$templateids = $condition['value'];
				break;

			case CONDITION_TYPE_PROXY:
				$proxyids = $condition['value'];
				break;

			case CONDITION_TYPE_SERVICE:
				$serviceids = $condition['value'];
				break;

			case CONDITION_TYPE_DRULE:
				$druleids = $condition['value'];
				break;

			// Return values as is for following condition types.
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
				$result = CSeverityHelper::getName((int)$condition['value']);
				break;

			case CONDITION_TYPE_DRULE:
				$druleids[$condition['value']] = $condition['value'];
				break;

			case CONDITION_TYPE_DCHECK:
				$dcheckids[$condition['value']] = $condition['value'];
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
		$drules = [];
		$dchecks = [];
		$services = [];

		if ($groupids) {
			$groups = API::HostGroup()->get([
				'output' => ['name'],
				'groupids' => $groupids,
				'preservekeys' => true
			]);
		}

		if ($triggerids) {
			$triggers = API::Trigger()->get([
				'output' => ['description'],
				'triggerids' => $triggerids,
				'expandDescription' => true,
				'selectHosts' => ['name'],
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

		if ($templateids) {
			$templates = API::Template()->get([
				'output' => ['name'],
				'templateids' => $templateids,
				'preservekeys' => true
			]);
		}

		if ($proxyids) {
			$proxies = API::Proxy()->get([
				'output' => ['host'],
				'proxyids' => $proxyids,
				'preservekeys' => true
			]);
		}

		if ($druleids) {
			$drules = API::DRule()->get([
				'output' => ['name'],
				'druleids' => $druleids,
				'preservekeys' => true
			]);
		}

		if ($dcheckids) {
			$dchecks = API::DCheck()->get([
				'output' => ['type', 'key_', 'ports'],
				'dcheckids' => $dcheckids,
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

		if ($groups || $triggers || $hosts || $templates || $proxies || $drules || $dchecks || $services) {
			$id = $condition['value'];


			switch ($condition['condition_type']) {
				case CONDITION_TYPE_HOST_GROUP:
					foreach ($id as $groupid) {
						if (array_key_exists($groupid, $groups)) {
							$result = $groups[$groupid]['name'];
						}
						$names[] = $result;
					}
					break;

				case CONDITION_TYPE_TRIGGER:
					foreach ($id as $triggerid) {
						if (array_key_exists($triggerid, $triggers)) {
							$host = reset($triggers[$triggerid]['hosts']);
							$result = $host['name'].NAME_DELIMITER.$triggers[$triggerid]['description'];
						}
						$names[] = $result;
					}
					break;

				case CONDITION_TYPE_HOST:
					foreach ($id as $hostid) {
						if (array_key_exists($hostid, $hosts)) {
							$result = $hosts[$hostid]['name'];
						}
						$names[] = $result;
					}
					break;

				case CONDITION_TYPE_TEMPLATE:
					foreach ($id as $templateid) {
						if (array_key_exists($templateid, $templates)) {
							$result = $templates[$templateid]['name'];
						}
						$names[] = $result;
					}
					break;

				case CONDITION_TYPE_PROXY:
					if (array_key_exists($proxyids, $proxies)) {
						$result = $proxies[$proxyids]['host'];
					}
					break;

				case CONDITION_TYPE_DRULE:
					foreach ($id as $druleid) {
						if (array_key_exists($druleid, $drules)) {
							$result = $drules[$druleid]['name'];
						}
						$names[] = $result;
					}
					break;

				case CONDITION_TYPE_DCHECK:
					if (array_key_exists($id, $dchecks)) {
						$drule = reset($dchecks[$id]['drules']);
						$type = $dchecks[$id]['type'];
						$key_ = $dchecks[$id]['key_'];
						$ports = $dchecks[$id]['ports'];

						$dcheck = discovery_check2str($type, $key_, $ports);

						$result = $drule['name'].NAME_DELIMITER.$dcheck;
					}
					break;

				case CONDITION_TYPE_SERVICE:
					foreach ($id as $serviceid) {
						if (array_key_exists($serviceid, $services)) {
							$result = $services[$serviceid]['name'];
						}
						$names[] = $result;
					}
					break;

			}
		}

		if ($groups || $triggers || $hosts || $templates || $services || $drules) {
			$all_results = $names;
		}
		else {
			$all_results[] = $result;
		}
		return $all_results;
	}
}
