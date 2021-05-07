<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
 * Class containing operations with userrole edit form.
 */
class CControllerUserroleEdit extends CControllerUserroleEditGeneral {

	protected $role = [];

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'roleid' => 'db users.roleid',
			'name' => 'db role.name',
			'type' => 'in '.implode(',', [USER_TYPE_ZABBIX_USER, USER_TYPE_ZABBIX_ADMIN, USER_TYPE_SUPER_ADMIN]),
			'ui_monitoring_dashboard' => 'in 0,1',
			'ui_monitoring_problems' => 'in 0,1',
			'ui_monitoring_hosts' => 'in 0,1',
			'ui_monitoring_overview' => 'in 0,1',
			'ui_monitoring_latest_data' => 'in 0,1',
			'ui_monitoring_maps' => 'in 0,1',
			'ui_monitoring_discovery' => 'in 0,1',
			'ui_monitoring_services' => 'in 0,1',
			'ui_inventory_overview' => 'in 0,1',
			'ui_inventory_hosts' => 'in 0,1',
			'ui_reports_system_info' => 'in 0,1',
			'ui_reports_scheduled_reports' => 'in 0,1',
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
			'actions_manage_api_tokens' => 'in 0,1',
			'actions_manage_scheduled_reports' => 'in 0,1',
			'ui_default_access' => 'in 0,1',
			'modules_default_access' => 'in 0,1',
			'actions_default_access' => 'in 0,1',
			'modules' => 'array',
			'api_access' => 'in 0,1',
			'api_mode' => 'in 0,1',
			'api_methods' => 'array'
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
		$rules_labels = $this->getUiLabels($section_labels);
		$actions_labels = CRoleHelper::getActionsLabels(USER_TYPE_SUPER_ADMIN);
		$module_labels = $this->getModulesLabels();

		$data = [
			'roleid' => 0,
			'name' => $db_defaults['name'],
			'type' => $db_defaults['type'],
			'readonly' => $db_defaults['readonly'],
			'labels' => [
				'sections' => $section_labels,
				'rules' => $rules_labels,
				'modules' => $module_labels,
				'actions' => $actions_labels
			]
		];

		if ($this->getInput('roleid', 0) != 0) {
			$data['roleid'] = $this->role['roleid'];
			$data['name'] = $this->role['name'];
			$data['type'] = $this->role['type'];
			$data['readonly'] = $this->role['readonly'];
		}
		else {
			// The input value will be set in case of read-only role cloning.
			$data['type'] = $this->getInput('type', $data['type']);
		}

		$data['rules'] = [
			CRoleHelper::UI_DEFAULT_ACCESS => true,
			CRoleHelper::ACTIONS_DEFAULT_ACCESS => true,
			CRoleHelper::MODULES_DEFAULT_ACCESS => true,
			CRoleHelper::SECTION_UI => array_fill_keys(CRoleHelper::getAllUiElements((int) $data['type']), true),
			CRoleHelper::SECTION_ACTIONS => array_fill_keys(CRoleHelper::getAllActions((int) $data['type']), true),
			CRoleHelper::SECTION_MODULES => [],
			CRoleHelper::API_ACCESS => true,
			CRoleHelper::API_MODE => CRoleHelper::API_MODE_DENY,
			CRoleHelper::SECTION_API => []
		];

		if ($this->getInput('roleid', 0) != 0) {
			$data['rules'] = array_merge($data['rules'], $this->getRulesValue((int) $this->role['roleid']));
		}

		$data['is_own_role'] = ($data['roleid'] == CWebUser::$data['roleid']);
		$data = $this->overwriteInputs($data);

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of user roles'));
		$this->setResponse($response);
	}
}
