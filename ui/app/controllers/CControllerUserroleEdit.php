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
 * Class containing operations with user edit form.
 */
class CControllerUserroleEdit extends CController {

	protected $role = [];

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'roleid' => 'db users.roleid'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		if (!$this->checkAccess(CRoleHelper::UI_ADMINISTRATION_USER_ROLES)) {
			return false;
		}

		if ($this->getInput('roleid', 0) != 0) {
			$roles = API::Role()->get([
				'output' => ['roleid', 'name', 'type', 'readonly'],
				'roleids' => $this->getInput('roleid'),
				'editable' => true
			]);

			if (!$roles) {
				return false;
			}

			$this->role = $roles[0];
		}

		return true;
	}

	protected function doAction() {
		$db_defaults = DB::getDefaults('role');

		$section_labels = CRoleHelper::getUiSectionsLabels(USER_TYPE_SUPER_ADMIN);
		$rules_labels = $this->getRulesLabels($section_labels);
		$actions_labels = CRoleHelper::getActionsLabels(USER_TYPE_SUPER_ADMIN);
		$module_labels = $this->getModuleLabels();

		$data = [
			'roleid' => 0,
			'name' => $db_defaults['name'],
			'type' => $db_defaults['type'],
			'readonly' => $db_defaults['readonly'],
			'labels' => [
				'sections' => $section_labels,
				'rules' => $rules_labels,
				'actions' => $actions_labels,
				'modules' => $module_labels
			],
			'rules' => [
				CRoleHelper::UI_DEFAULT_ACCESS => true,
				CRoleHelper::MODULES_DEFAULT_ACCESS => true,
				CRoleHelper::ACTIONS_DEFAULT_ACCESS => true,
				CRoleHelper::API_ACCESS => true,
				CRoleHelper::API_MODE => CRoleHelper::API_MODE_DENY,
				CRoleHelper::UI_MONITORING_DASHBOARD => true,
				CRoleHelper::UI_MONITORING_PROBLEMS => true,
				CRoleHelper::UI_MONITORING_HOSTS => true,
				CRoleHelper::UI_MONITORING_OVERVIEW => true,
				CRoleHelper::UI_MONITORING_LATEST_DATA => true,
				CRoleHelper::UI_MONITORING_SCREENS => true,
				CRoleHelper::UI_MONITORING_MAPS => true,
				CRoleHelper::UI_MONITORING_SERVICES => true,
				CRoleHelper::UI_INVENTORY_OVERVIEW => true,
				CRoleHelper::UI_INVENTORY_HOSTS => true,
				CRoleHelper::UI_REPORTS_AVAILABILITY_REPORT => true,
				CRoleHelper::UI_REPORTS_TOP_TRIGGERS => true,
				CRoleHelper::ACTIONS_EDIT_DASHBOARDS => true,
				CRoleHelper::ACTIONS_EDIT_MAPS => true,
				CRoleHelper::ACTIONS_ACKNOWLEDGE_PROBLEMS => true,
				CRoleHelper::ACTIONS_CLOSE_PROBLEMS => true,
				CRoleHelper::ACTIONS_CHANGE_SEVERITY => true,
				CRoleHelper::ACTIONS_ADD_PROBLEM_COMMENTS => true,
				CRoleHelper::ACTIONS_EXECUTE_SCRIPTS => true
			]
		];

		if ($this->getInput('roleid', 0) != 0) {
			$data['roleid'] = $this->role['roleid'];
			$data['name'] = $this->role['name'];
			$data['type'] = $this->role['type'];
			$data['readonly'] = $this->role['readonly'];
			$data['rules'] = $this->getRulesValue((int) $this->role['roleid']);
		}

		// TODO: Overwrite with input variables.
		// $this->getInputs($data, ['name', 'gui_access', 'users_status', 'debug_mode', 'form_refresh']);

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of user roles'));
		$this->setResponse($response);
	}

	private function getRulesLabels(array $sections): array {
		$rules_labels = [];
		foreach (array_keys($sections) as $section) {
			$rules_labels[$section] = CRoleHelper::getUiSectionRulesLabels($section, USER_TYPE_SUPER_ADMIN);
		}

		return $rules_labels;
	}

	private function getModuleLabels(): array {
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

	private function getRulesValue(int $roleid) {
		$result = [];

		$response = API::Role()->get([
			'output' => ['roleid'],
			'selectRules' => ['ui', 'ui.default_access', 'modules', 'modules.default_access', 'api.access', 'api.mode',
				'api', 'actions', 'actions.default_access'
			],
			'roleids' => $roleid
		]);
		$response = $response[0];

		if (count($response['rules'][CRoleHelper::SECTION_UI])) {
			foreach ($response['rules'][CRoleHelper::SECTION_UI] as $ui_rule) {
				$result[CRoleHelper::SECTION_UI.'.'.$ui_rule['name']] = $ui_rule['status'];
			}
		}

		if (count($response['rules'][CRoleHelper::SECTION_ACTIONS])) {
			foreach ($response['rules'][CRoleHelper::SECTION_ACTIONS] as $action_rule) {
				$result[CRoleHelper::SECTION_ACTIONS.'.'.$action_rule['name']] = $action_rule['status'];
			}
		}

		if (count($response['rules'][CRoleHelper::SECTION_MODULES])) {
			foreach ($response['rules'][CRoleHelper::SECTION_MODULES] as $module_rule) {
				$result[CRoleHelper::SECTION_MODULES][$module_rule['moduleid']] = $module_rule['status'];
			}
		}

		// TODO: add mapping for api methods.

		$result[CRoleHelper::UI_DEFAULT_ACCESS] = $response['rules']['ui.default_access'];
		$result[CRoleHelper::MODULES_DEFAULT_ACCESS] = $response['rules']['modules.default_access'];
		$result[CRoleHelper::ACTIONS_DEFAULT_ACCESS] = $response['rules']['actions.default_access'];
		$result[CRoleHelper::API_ACCESS] = $response['rules']['api.access'];
		$result[CRoleHelper::API_MODE] = $response['rules']['api.mode'];

		return $result;
	}
}
