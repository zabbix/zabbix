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
	protected function getRulesInput(int $user_type, ?array $rules = null): array {
		return array_merge(
			$this->getUiSectionRules($user_type, $rules),
			$this->getServiceSectionRules($rules),
			$this->getModuleSectionRules($rules),
			$this->getApiSectionRules($rules),
			$this->getActionSectionRules($user_type, $rules)
		);
	}

	private function getUiSectionRules(int $user_type, ?array $rules = null): array {
		return [
			'ui' => array_map(
				function (string $rule) use ($rules): array {
					$field = str_replace('.', '_', $rule);

					return [
						'name' => str_replace('ui.', '', $rule),
						'status' => $this->getInput($field, $rules === null
							? ZBX_ROLE_RULE_ENABLED
							: $rules['ui'][$rule]
						)
					];
				},
				CRoleHelper::getUiElementsByUserType($user_type)
			),
			'ui.default_access' => $this->getInput('ui_default_access', $rules === null
				? ZBX_ROLE_RULE_ENABLED
				: $rules['ui.default_access']
			)
		];
	}

	private function getServiceSectionRules(?array $rules = null): array {
		$read_access = $this->getInput('service_read_access', $rules === null
			? CRoleHelper::SERVICES_ACCESS_NONE
			: $rules['service_read_access']
		);
		$write_access = $this->getInput('service_write_access', $rules === null
			? CRoleHelper::SERVICES_ACCESS_NONE
			: $rules['service_write_access']
		);

		return [
			'services.read.mode' => $read_access == CRoleHelper::SERVICES_ACCESS_ALL
				? ZBX_ROLE_RULE_SERVICES_ACCESS_ALL
				: ZBX_ROLE_RULE_SERVICES_ACCESS_CUSTOM,
			'services.read.list' => $read_access == CRoleHelper::SERVICES_ACCESS_LIST
				? array_map(
					static fn(string $serviceid): array => ['serviceid' => $serviceid],
					$this->getInput('service_read_list', $rules === null
						? []
						: array_column($rules['service_read_list'], 'serviceid')
					)
				)
				: [],
			'services.read.tag' => $read_access == CRoleHelper::SERVICES_ACCESS_LIST
				? [
					'tag' => trim($this->getInput('service_read_tag_tag', $rules === null
						? ''
						: $rules['service_read_tag']['tag'])
					),
					'value' => trim($this->getInput('service_read_tag_value', $rules === null
						? ''
						: $rules['service_read_tag']['value'])
					)
				]
				: ['tag' => '', 'value' => ''],
			'services.write.mode' => $write_access == CRoleHelper::SERVICES_ACCESS_ALL
				? ZBX_ROLE_RULE_SERVICES_ACCESS_ALL
				: ZBX_ROLE_RULE_SERVICES_ACCESS_CUSTOM,
			'services.write.list' => $write_access == CRoleHelper::SERVICES_ACCESS_LIST
				? array_map(
					static fn(string $serviceid): array => ['serviceid' => $serviceid],
					$this->getInput('service_write_list', $rules === null
						? []
						: array_column($rules['service_write_list'], 'serviceid')
					)
				)
				: [],
			'services.write.tag' => $write_access == CRoleHelper::SERVICES_ACCESS_LIST
				? [
					'tag' => trim($this->getInput('service_write_tag_tag', $rules === null
						? ''
						: $rules['service_write_tag']['tag'])
					),
					'value' => trim($this->getInput('service_write_tag_value', $rules === null
						? ''
						: $rules['service_write_tag']['value'])
					)
				]
				: ['tag' => '', 'value' => '']
		];
	}

	/**
	 * @throws APIException
	 */
	private function getModuleSectionRules(?array $rules = null): array {
		$db_modules = API::Module()->get([
			'output' => [],
			'preservekeys' => true
		]);

		$modules = $this->getInput('modules', $rules === null ? [] : $rules['modules']);

		return [
			'modules' => array_map(
				static fn(string $moduleid): array => [
					'moduleid' => $moduleid,
					'status' => array_key_exists($moduleid, $modules) ? $modules[$moduleid] : ZBX_ROLE_RULE_ENABLED
				],
				array_keys($db_modules)
			),
			'modules.default_access' => $this->getInput('modules_default_access', $rules === null
				? ZBX_ROLE_RULE_ENABLED
				: $rules['modules.default_access']
			)
		];
	}

	private function getApiSectionRules(?array $rules = null): array {
		return [
			'api' => $this->getInput('api_methods', $rules === null ? [] : array_column($rules['api'], 'name')),
			'api.access' => $this->getInput('api_access', $rules === null
				? ZBX_ROLE_RULE_ENABLED
				: $rules['api.access']
			),
			'api.mode' => $this->getInput('api_mode', $rules === null
				? ZBX_ROLE_RULE_API_MODE_DENY
				: $rules['api.mode']
			)
		];
	}

	private function getActionSectionRules(int $user_type, ?array $rules = null): array {
		return [
			'actions' => array_map(
				function (string $rule) use ($rules): array {
					$field = str_replace('.', '_', $rule);

					return [
						'name' => str_replace('actions.', '', $rule),
						'status' => $this->getInput($field, $rules === null
							? ZBX_ROLE_RULE_ENABLED
							: $rules['actions'][$rule]
						)
					];
				},
				CRoleHelper::getActionsByUserType($user_type)
			),
			'actions.default_access' => $this->getInput('actions_default_access', $rules === null
				? ZBX_ROLE_RULE_ENABLED
				: $rules['actions.default_access']
			)
		];
	}
}
