<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
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


/**
 * Class containing operations with userrole.
 */
abstract class CControllerUserroleEditGeneral extends CController {

	/**
	 * @throws APIException
	 */
	protected function getRulesInput(int $user_type): array {
		global $ZBX_FEATURE_FLAGS;

		return array_merge(
			$this->getUiSectionRules($user_type),
			$this->getServiceSectionRules(),
			$ZBX_FEATURE_FLAGS['modules_config_enabled'] ? $this->getModuleSectionRules() : [],
			$this->getApiSectionRules(),
			$this->getActionSectionRules($user_type),
			$this->getDevicesActionSectionRules($user_type)
		);
	}

	private function getUiSectionRules(int $user_type): array {
		$ui_rules = array_flip($this->getInput('ui', []));

		return [
			'ui' => array_map(
				static fn(string $rule): array => [
					'name' => str_replace('ui.', '', $rule),
					'status' => array_key_exists($rule, $ui_rules) ? ZBX_ROLE_RULE_ENABLED : ZBX_ROLE_RULE_DISABLED
				],
				CRoleHelper::getUiElementsByUserType($user_type)
			),
			'ui.default_access' => $this->getInput('ui_default_access', ZBX_ROLE_RULE_ENABLED)
		];
	}

	private function getServiceSectionRules(): array {
		$read_access = $this->getInput('service_read_access', CRoleHelper::SERVICES_ACCESS_NONE);
		$write_access = $this->getInput('service_write_access', CRoleHelper::SERVICES_ACCESS_NONE);

		return [
			'services.read.mode' => $read_access == CRoleHelper::SERVICES_ACCESS_ALL
				? ZBX_ROLE_RULE_SERVICES_ACCESS_ALL
				: ZBX_ROLE_RULE_SERVICES_ACCESS_CUSTOM,
			'services.read.list' => $read_access == CRoleHelper::SERVICES_ACCESS_LIST
				? array_map(
					static fn(string $serviceid): array => ['serviceid' => $serviceid],
					$this->getInput('service_read_list', []))
				: [],
			'services.read.tag' => $read_access == CRoleHelper::SERVICES_ACCESS_LIST
				? [
					'tag' => trim($this->getInput('service_read_tag_tag', '')),
					'value' => trim($this->getInput('service_read_tag_value', ''))
				]
				: ['tag' => '', 'value' => ''],
			'services.write.mode' => $write_access == CRoleHelper::SERVICES_ACCESS_ALL
				? ZBX_ROLE_RULE_SERVICES_ACCESS_ALL
				: ZBX_ROLE_RULE_SERVICES_ACCESS_CUSTOM,
			'services.write.list' => $write_access == CRoleHelper::SERVICES_ACCESS_LIST
				? array_map(
					static fn(string $serviceid): array => ['serviceid' => $serviceid],
					$this->getInput('service_write_list', []))
				: [],
			'services.write.tag' => $write_access == CRoleHelper::SERVICES_ACCESS_LIST
				? [
					'tag' => trim($this->getInput('service_write_tag_tag', '')),
					'value' => trim($this->getInput('service_write_tag_value', ''))
				]
				: ['tag' => '', 'value' => '']
		];
	}

	/**
	 * @throws APIException
	 */
	private function getModuleSectionRules(): array {
		$fields = [
			'modules' => [],
			'modules.default_access' => $this->getInput('modules_default_access', ZBX_ROLE_RULE_ENABLED)
		];

		if ($this->hasInput('modules')) {
			$db_modules = API::Module()->get([
				'output' => [],
				'preservekeys' => true
			]);

			$modules = $this->getInput('modules', []);

			foreach (array_keys($db_modules) as $moduleid) {
				$fields['modules'][] = [
					'moduleid' => $moduleid,
					'status' => array_key_exists($moduleid, $modules) ? $modules[$moduleid] : ZBX_ROLE_RULE_ENABLED
				];
			}
		}

		return $fields;
	}

	private function getApiSectionRules() : array {
		return  [
			'api' => $this->getInput('api_methods', []),
			'api.access' => $this->getInput('api_access', ZBX_ROLE_RULE_ENABLED),
			'api.mode' => $this->getInput('api_mode', ZBX_ROLE_RULE_API_MODE_DENY)
		];
	}

	private function getActionSectionRules(int $user_type): array {
		$action_rules = array_flip($this->getInput('actions'));

		return [
			'actions' => array_map(
				static fn(string $rule): array => [
					'name' => str_replace('actions.', '', $rule),
					'status' => array_key_exists($rule, $action_rules) ? ZBX_ROLE_RULE_ENABLED : ZBX_ROLE_RULE_DISABLED
				],
				CRoleHelper::getActionsByUserType($user_type)
			),
			'actions.default_access' => $this->getInput('actions_default_access', ZBX_ROLE_RULE_ENABLED)
		];
	}

	private function getDevicesActionSectionRules(int $user_type): array {
		if (!CSettingsHelper::isMobileDevicesEnabled()) {
			return [];
		}

		$device_action_rules = array_flip($this->getInput('devices_actions', []));

		return [
			'devices.access' => $this->getInput('devices_access') ? ZBX_ROLE_RULE_ENABLED : ZBX_ROLE_RULE_DISABLED,
			'devices.actions' => array_map(
				static fn(string $rule): array => [
					'name' => str_replace('devices.actions.', '', $rule),
					'status' => array_key_exists($rule, $device_action_rules)
						? ZBX_ROLE_RULE_ENABLED
						: ZBX_ROLE_RULE_DISABLED
				],
				CRoleHelper::getDeviceActionsByUserType($user_type)
			),
			'devices.actions.default_access' => $this->getInput('devices_actions_default_access',
				ZBX_ROLE_RULE_DISABLED
			)
		];
	}
}
