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


class CControllerUserroleCreate extends CController {

	protected function checkInput() {
		$fields = [
			'name' => 'required|not_empty|db role.name',
			'type' => 'required|in '.implode(',', [USER_TYPE_ZABBIX_USER, USER_TYPE_ZABBIX_ADMIN, USER_TYPE_SUPER_ADMIN]),
			'ui_default_access' => 'required|in 0,1',
			'modules_default_access' => 'required|in 0,1',
			'actions_default_access' => 'required|in 0,1',
			'api_access' => 'required|in 0,1',
			'api_mode' => 'required|in 0,1',
			'ui_monitoring_dashboard' => 'in 0,1',
			'ui_monitoring_problems' => 'in 0,1',
			'ui_monitoring_hosts' => 'in 0,1',
			'ui_monitoring_overview' => 'in 0,1',
			'ui_monitoring_latest_data' => 'in 0,1',
			'ui_monitoring_screens' => 'in 0,1',
			'ui_monitoring_maps' => 'in 0,1',
			'ui_monitoring_discovery' => 'in 0,1',
			'ui_monitoring_services' => 'in 0,1',
			'ui_inventory_overview' => 'in 0,1',
			'ui_inventory_hosts' => 'in 0,1',
			'ui_reports_system_info' => 'in 0,1',
			'ui_reports_availability_report' => 'in 0,1',
			'ui_reports_top_triggers' => 'in 0,1',
			'ui_reports_audit' => 'in 0,1',
			'ui_reports_action_log' => 'in 0,1',
			'ui_reports_notifications' => 'in 0,1',
			'ui_configuration_host_groups' => 'in 0,1',
			'ui_configuration_templates' => 'in 0,1',
			'ui_configuration_hosts' => 'in 0,1',
			'ui_configuration_maintenance' => 'in 0,1',
			'ui_configuration_actions' => 'in 0,1',
			'ui_configuration_event_correlation' => 'in 0,1',
			'ui_configuration_discovery' => 'in 0,1',
			'ui_configuration_services' => 'in 0,1',
			'ui_administration_general' => 'in 0,1',
			'ui_administration_proxies' => 'in 0,1',
			'ui_administration_authentication' => 'in 0,1',
			'ui_administration_user_groups' => 'in 0,1',
			'ui_administration_user_roles' => 'in 0,1',
			'ui_administration_users' => 'in 0,1',
			'ui_administration_media_types' => 'in 0,1',
			'ui_administration_scripts' => 'in 0,1',
			'ui_administration_queue' => 'in 0,1',
			'actions_edit_dashboards' => 'in 0,1',
			'actions_edit_maps' => 'in 0,1',
			'actions_edit_maintenance' => 'in 0,1',
			'actions_acknowledge_problems' => 'in 0,1',
			'actions_close_problems' => 'in 0,1',
			'actions_change_severity' => 'in 0,1',
			'actions_add_problem_comments' => 'in 0,1',
			'actions_execute_scripts' => 'in 0,1',
			'modules' => 'array'
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

		return true;
	}

	protected function doAction() {
		$role = [
			'name' => $this->getInput('name'),
			'type' => $this->getInput('type')
		];

		$rules = [
			CRoleHelper::UI_DEFAULT_ACCESS => $this->getInput('ui_default_access'),
			CRoleHelper::ACTIONS_DEFAULT_ACCESS => $this->getInput('actions_default_access'),
			CRoleHelper::MODULES_DEFAULT_ACCESS => $this->getInput('modules_default_access'),
		];

		$rules[CRoleHelper::SECTION_UI] = array_map(function (string $rule): array {
			return [
				'name' => str_replace(CRoleHelper::SECTION_UI.'.', '', $rule),
				'status' => $this->getInput(str_replace('.', '_', $rule))
			];
		}, CRoleHelper::getAllUiElements((int) $role['type']));

		$rules[CRoleHelper::SECTION_ACTIONS] = array_map(function (string $rule): array {
			return [
				'name' => str_replace(CRoleHelper::SECTION_ACTIONS.'.', '', $rule),
				'status' => $this->getInput(str_replace('.', '_', $rule))
			];
		}, CRoleHelper::getAllActions((int) $role['type']));

		$moduelids = $this->getModuleIds();
		if ($moduelids) {
			$modules = $this->getInput(CRoleHelper::SECTION_MODULES);
			$rules[CRoleHelper::SECTION_MODULES] = array_map(function (string $moduleid) use ($modules): array {
				return [
					'moduleid' => $moduleid,
					'status' => $modules[$moduleid]
				];
			}, $moduelids);
		}

		$role['rules'] = $rules;

		$result = API::Role()->create($role);

		if ($result) {
			$response = new CControllerResponseRedirect(
				(new CUrl('zabbix.php'))
					->setArgument('action', 'userrole.list')
					->setArgument('page', CPagerHelper::loadPage('userrole.list', null))
			);
			$response->setFormData(['uncheck' => '1']);
			CMessageHelper::setSuccessTitle(_('User role updated'));
		}
		else {
			$response = new CControllerResponseRedirect(
				(new CUrl('zabbix.php'))->setArgument('action', 'userrole.edit')
			);
			CMessageHelper::setErrorTitle(_('Cannot update user role'));
			$response->setFormData($this->getInputAll());
		}

		$this->setResponse($response);
	}

	private function getModuleIds(): array {
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
}
