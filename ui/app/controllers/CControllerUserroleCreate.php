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
 * Class containing operations with userrole create form.
 */
class CControllerUserroleCreate extends CControllerUserroleEditGeneral {

	protected function checkInput() {
		$fields = [
			'name' => 'required|not_empty|db role.name',
			'type' => 'required|in '.implode(',', [USER_TYPE_ZABBIX_USER, USER_TYPE_ZABBIX_ADMIN, USER_TYPE_SUPER_ADMIN]),
			'ui_default_access' => 'required|in 0,1',
			'modules_default_access' => 'required|in 0,1',
			'actions_default_access' => 'required|in 0,1',
			'api_access' => 'required|in 0,1',
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
			'modules' => 'array',
			'api_mode' => 'in 0,1',
			'api_methods' => 'array'
		];

		$ret = $this->validateInput($fields);
		$error = $this->getValidationError();

		if (!$ret) {
			switch ($error) {
				case self::VALIDATION_ERROR:
					$response = new CControllerResponseRedirect('zabbix.php?action=userrole.edit');
					$response->setFormData($this->getInputAll());
					CMessageHelper::setErrorTitle(_('Cannot create user role'));
					$this->setResponse($response);
					break;

				case self::VALIDATION_FATAL_ERROR:
					$this->setResponse(new CControllerResponseFatal());
					break;
			}
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
			'name' => trim($this->getInput('name')),
			'type' => $this->getInput('type')
		];

		$role['rules'] = $this->getRules((int) $role['type']);

		$result = API::Role()->create($role);

		if ($result) {
			$response = new CControllerResponseRedirect(
				(new CUrl('zabbix.php'))
					->setArgument('action', 'userrole.list')
					->setArgument('page', CPagerHelper::loadPage('userrole.list', null))
			);
			$response->setFormData(['uncheck' => '1']);
			CMessageHelper::setSuccessTitle(_('User role created'));
		}
		else {
			$response = new CControllerResponseRedirect(
				(new CUrl('zabbix.php'))->setArgument('action', 'userrole.edit')
			);
			CMessageHelper::setErrorTitle(_('Cannot create user role'));
			$response->setFormData($this->getInputAll());
		}

		$this->setResponse($response);
	}
}
