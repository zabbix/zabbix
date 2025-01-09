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


/**
 * Class containing operations with userrole.
 */
abstract class CControllerUserroleEditGeneral extends CController {

	/**
	 * @throws APIException
	 */
	protected function getRulesInput(int $user_type): array {
		return array_merge(
			$this->getUiSectionRules($user_type),
			$this->getServiceSectionRules(),
			$this->getModuleSectionRules(),
			$this->getApiSectionRules(),
			$this->getActionSectionRules($user_type)
		);
	}

	private function getUiSectionRules(int $user_type): array {
		return [
			'ui' => array_map(
				function (string $rule): array {
					return [
						'name' => str_replace('ui.', '', $rule),
						'status' => $this->getInput(str_replace('.', '_', $rule))
					];
				},
				CRoleHelper::getUiElementsByUserType($user_type)
			),
			'ui.default_access' => $this->getInput('ui_default_access')
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
					static function (string $serviceid): array {
						return ['serviceid' => $serviceid];
					},
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
					static function (string $serviceid): array {
						return ['serviceid' => $serviceid];
					},
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
		$db_modules = API::Module()->get([
			'output' => [],
			'preservekeys' => true
		]);

		$modules = $this->getInput('modules', []);

		return [
			'modules' => array_map(
				static function (string $moduleid) use ($modules): array {
					return [
						'moduleid' => $moduleid,
						'status' => $modules[$moduleid]
					];
				},
				array_keys($db_modules)
			),
			'modules.default_access' => $this->getInput('modules_default_access')
		];
	}

	private function getApiSectionRules() : array {
		return  [
			'api' => $this->getInput('api_methods', []),
			'api.access' => $this->getInput('api_access'),
			'api.mode' => $this->getInput('api_mode', ZBX_ROLE_RULE_API_MODE_DENY)
		];
	}

	private function getActionSectionRules(int $user_type): array {
		return [
			'actions' => array_map(
				function (string $rule): array {
					return [
						'name' => str_replace('actions.', '', $rule),
						'status' => $this->getInput(str_replace('.', '_', $rule))
					];
				},
				CRoleHelper::getActionsByUserType($user_type)
			),
			'actions.default_access' => $this->getInput('actions_default_access')
		];
	}
}
