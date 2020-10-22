<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
 * Class containing operations with userrole.
 */
abstract class CControllerUserroleEditGeneral extends CController {

	protected function getRules(int $user_type): array {
		$rules = [
			CRoleHelper::UI_DEFAULT_ACCESS => $this->getInput('ui_default_access'),
			CRoleHelper::ACTIONS_DEFAULT_ACCESS => $this->getInput('actions_default_access'),
			CRoleHelper::MODULES_DEFAULT_ACCESS => $this->getInput('modules_default_access'),
		];

		$rules += $this->getUiSectionRules($user_type);
		$rules += $this->getActionSectionRules($user_type);
		$rules += $this->getModuleSectionRules();
		$rules += $this->getApiSectionRules();

		return $rules;
	}

	protected function getUiSectionRules(int $user_type): array {
		return [
			CRoleHelper::SECTION_UI => array_map(function (string $rule): array {
				return [
					'name' => str_replace(CRoleHelper::SECTION_UI.'.', '', $rule),
					'status' => $this->getInput(str_replace('.', '_', $rule))
				];
			}, CRoleHelper::getAllUiElements($user_type))
		];
	}

	protected function getActionSectionRules(int $user_type): array {
		return [
			CRoleHelper::SECTION_ACTIONS => array_map(function (string $rule): array {
				return [
					'name' => str_replace(CRoleHelper::SECTION_ACTIONS.'.', '', $rule),
					'status' => $this->getInput(str_replace('.', '_', $rule))
				];
			}, CRoleHelper::getAllActions($user_type))
		];
	}

	protected function getModuleSectionRules(): array {
		$moduelids = $this->getModuleIds();
		if (!$moduelids) {
			return [];
		}

		$modules = $this->getInput(CRoleHelper::SECTION_MODULES);
		return [
			CRoleHelper::SECTION_MODULES => array_map(function (string $moduleid) use ($modules): array {
				return [
					'moduleid' => $moduleid,
					'status' => $modules[$moduleid]
				];
			}, $moduelids)
		];
	}

	protected function getApiSectionRules(): array {
		$rules = [];

		$rules[CRoleHelper::API_ACCESS] = $this->getInput('api_access');
		if ($rules[CRoleHelper::API_ACCESS]) {
			$rules[CRoleHelper::API_MODE] = $this->getInput('api_mode');

			if ($this->hasInput('api_methods')) {
				$rules[CRoleHelper::SECTION_API] = $this->getInput('api_methods');
			}
		}

		return $rules;
	}

	protected function getModuleIds(): array {
		$response = API::Module()->get([
			'output' => ['moduleid'],
			'filter' => [
				'status' => MODULE_STATUS_ENABLED
			]
		]);

		if (!$response) {
			return [];
		}

		return array_column($response, 'moduleid');
	}

	protected function getUiLabels(array $sections): array {
		$rules_labels = [];
		foreach (array_keys($sections) as $section) {
			$rules_labels[$section] = CRoleHelper::getUiSectionRulesLabels($section, USER_TYPE_SUPER_ADMIN);
		}

		return $rules_labels;
	}

	protected function getModulesLabels(): array {
		$response = API::Module()->get([
			'output' => ['moduleid', 'relative_path'],
			'filter' => [
				'status' => MODULE_STATUS_ENABLED
			]
		]);

		if (!$response) {
			return [];
		}

		$modules = [];
		$module_manager = new CModuleManager(APP::ModuleManager()->getModulesDir());

		foreach ($response as $module) {
			$manifest = $module_manager->addModule($module['relative_path']);
			$modules[$module['moduleid']] = $manifest['name'];
		}

		return $modules;
	}

	protected function getRulesValue(int $roleid) {
		$result = [];

		$response = API::Role()->get([
			'output' => ['roleid'],
			'selectRules' => ['ui', 'ui.default_access', 'modules', 'modules.default_access', 'api', 'api.access',
				'api.mode', 'actions', 'actions.default_access'
			],
			'roleids' => $roleid
		]);
		$response = $response[0];

		$result[CRoleHelper::UI_DEFAULT_ACCESS] = $response['rules'][CRoleHelper::UI_DEFAULT_ACCESS];
		$result[CRoleHelper::ACTIONS_DEFAULT_ACCESS] = $response['rules'][CRoleHelper::ACTIONS_DEFAULT_ACCESS];
		$result[CRoleHelper::MODULES_DEFAULT_ACCESS] = $response['rules'][CRoleHelper::MODULES_DEFAULT_ACCESS];

		if (count($response['rules'][CRoleHelper::SECTION_UI])) {
			foreach ($response['rules'][CRoleHelper::SECTION_UI] as $ui_rule) {
				$result[CRoleHelper::SECTION_UI][CRoleHelper::SECTION_UI.'.'.$ui_rule['name']] = $ui_rule['status'];
			}
		}

		if (count($response['rules'][CRoleHelper::SECTION_ACTIONS])) {
			foreach ($response['rules'][CRoleHelper::SECTION_ACTIONS] as $action_rule) {
				$result[CRoleHelper::SECTION_ACTIONS][CRoleHelper::SECTION_ACTIONS.'.'.$action_rule['name']] = $action_rule['status'];
			}
		}

		if (count($response['rules'][CRoleHelper::SECTION_MODULES])) {
			foreach ($response['rules'][CRoleHelper::SECTION_MODULES] as $module_rule) {
				$result[CRoleHelper::SECTION_MODULES][$module_rule['moduleid']] = $module_rule['status'];
			}
		}

		$result[CRoleHelper::API_ACCESS] = $response['rules'][CRoleHelper::API_ACCESS];
		$result[CRoleHelper::API_MODE] = $response['rules'][CRoleHelper::API_MODE];

		if (count($response['rules'][CRoleHelper::SECTION_API])) {
			$result[CRoleHelper::SECTION_API] = array_map(function (string $method): array {
				return [
					'id' => $method,
					'name' => $method
				];
			}, $response['rules'][CRoleHelper::SECTION_API]);
		}

		return $result;
	}

	protected function overwriteInputs(array $data): array {
		$this->getInputs($data, ['name', 'type']);

		// Overwrite default access inputs.
		foreach ([CRoleHelper::UI_DEFAULT_ACCESS, CRoleHelper::MODULES_DEFAULT_ACCESS,
				CRoleHelper::ACTIONS_DEFAULT_ACCESS] as $label) {
			$input_name = str_replace('.', '_', $label);
			if ($this->hasInput($input_name)) {
				$data['rules'][$label] = $this->getInput($input_name);
			}
		}

		// Overwrite UI section.
		foreach (CRoleHelper::getAllUiElements((int) $data['type']) as $label) {
			$input_name = str_replace('.', '_', $label);
			if ($this->hasInput($input_name)) {
				$data['rules'][CRoleHelper::SECTION_UI][$label] = $this->getInput($input_name);
			}
		}

		// Overwrite actions section.
		foreach (CRoleHelper::getAllActions((int) $data['type']) as $label) {
			$input_name = str_replace('.', '_', $label);
			if ($this->hasInput($input_name)) {
				$data['rules'][CRoleHelper::SECTION_ACTIONS][$label] = $this->getInput($input_name);
			}
		}

		// Overwrite modules section.
		if ($this->hasInput(CRoleHelper::SECTION_MODULES)) {
			$data['rules'][CRoleHelper::SECTION_MODULES] = $this->getInput('modules');
		}

		// Overwrite API section.
		if ($this->hasInput('api_access')) {
			$data['rules'][CRoleHelper::API_ACCESS] = $this->getInput('api_access');
		}
		if ($this->hasInput('api_mode')) {
			$data['rules'][CRoleHelper::API_MODE] = $this->getInput('api_mode');
		}
		if ($this->hasInput('api_methods')) {
			foreach ($this->getInput('api_methods') as $method) {
				$data['rules'][CRoleHelper::SECTION_API][] = ['id' => $method, 'name' => $method];
			}
		}

		return $data;
	}
}
