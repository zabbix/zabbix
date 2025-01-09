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
 * Class containing operations with userrole update form.
 */
class CControllerUserroleUpdate extends CControllerUserroleEditGeneral {

	protected $role = [];

	protected function checkInput(): bool {
		$fields = [
			'roleid' => 									'fatal|required|db users.roleid',
			'name' => 										'required|db role.name|not_empty',
			'type' => 										'required|in '.implode(',', [USER_TYPE_ZABBIX_USER, USER_TYPE_ZABBIX_ADMIN, USER_TYPE_SUPER_ADMIN]),
			'ui_monitoring_dashboard' => 					'in 0,1',
			'ui_monitoring_problems' => 					'in 0,1',
			'ui_monitoring_hosts' => 						'in 0,1',
			'ui_monitoring_latest_data' => 					'in 0,1',
			'ui_monitoring_maps' => 						'in 0,1',
			'ui_monitoring_discovery' => 					'in 0,1',
			'ui_services_services' => 						'in 0,1',
			'ui_services_sla' => 							'in 0,1',
			'ui_services_sla_report' => 					'in 0,1',
			'ui_inventory_overview' => 						'in 0,1',
			'ui_inventory_hosts' => 						'in 0,1',
			'ui_reports_system_info' => 					'in 0,1',
			'ui_reports_scheduled_reports' => 				'in 0,1',
			'ui_reports_availability_report' => 			'in 0,1',
			'ui_reports_top_triggers' => 					'in 0,1',
			'ui_reports_audit' => 							'in 0,1',
			'ui_reports_action_log' => 						'in 0,1',
			'ui_reports_notifications' => 					'in 0,1',
			'ui_configuration_template_groups' => 			'in 0,1',
			'ui_configuration_host_groups' => 				'in 0,1',
			'ui_configuration_templates' => 				'in 0,1',
			'ui_configuration_hosts' => 					'in 0,1',
			'ui_configuration_maintenance' => 				'in 0,1',
			'ui_configuration_trigger_actions' => 			'in 0,1',
			'ui_configuration_service_actions' => 			'in 0,1',
			'ui_configuration_discovery_actions' => 		'in 0,1',
			'ui_configuration_autoregistration_actions' => 	'in 0,1',
			'ui_configuration_internal_actions' => 			'in 0,1',
			'ui_configuration_event_correlation' => 		'in 0,1',
			'ui_configuration_discovery' => 				'in 0,1',
			'ui_administration_general' => 					'in 0,1',
			'ui_administration_audit_log' => 				'in 0,1',
			'ui_administration_housekeeping' => 			'in 0,1',
			'ui_administration_proxy_groups' => 			'in 0,1',
			'ui_administration_proxies' => 					'in 0,1',
			'ui_administration_macros' => 					'in 0,1',
			'ui_administration_authentication' => 			'in 0,1',
			'ui_administration_user_groups' => 				'in 0,1',
			'ui_administration_user_roles' => 				'in 0,1',
			'ui_administration_users' => 					'in 0,1',
			'ui_administration_api_tokens' => 				'in 0,1',
			'ui_administration_media_types' => 				'in 0,1',
			'ui_administration_scripts' => 					'in 0,1',
			'ui_administration_queue' => 					'in 0,1',
			'actions_edit_dashboards' => 					'in 0,1',
			'actions_edit_maps' => 							'in 0,1',
			'actions_edit_maintenance' => 					'in 0,1',
			'actions_acknowledge_problems' => 				'in 0,1',
			'actions_close_problems' => 					'in 0,1',
			'actions_change_severity' => 					'in 0,1',
			'actions_suppress_problems' => 					'in 0,1',
			'actions_add_problem_comments' => 				'in 0,1',
			'actions_execute_scripts' => 					'in 0,1',
			'actions_manage_api_tokens' => 					'in 0,1',
			'actions_manage_scheduled_reports' => 			'in 0,1',
			'actions_manage_sla' => 						'in 0,1',
			'actions_invoke_execute_now' =>					'in 0,1',
			'actions_change_problem_ranking' =>				'in 0,1',
			'ui_default_access' => 							'in 0,1',
			'modules_default_access' => 					'in 0,1',
			'actions_default_access' => 					'in 0,1',
			'modules' => 									'array',
			'api_access' => 								'in 0,1',
			'api_mode' => 									'in '.implode(',', [ZBX_ROLE_RULE_API_MODE_DENY, ZBX_ROLE_RULE_API_MODE_ALLOW]),
			'api_methods' => 								'array',
			'service_read_access' => 						'in '.implode(',', [CRoleHelper::SERVICES_ACCESS_NONE, CRoleHelper::SERVICES_ACCESS_ALL, CRoleHelper::SERVICES_ACCESS_LIST]),
			'service_read_list' => 							'array_db services.serviceid',
			'service_read_tag_tag' => 						'string',
			'service_read_tag_value' => 					'string',
			'service_write_access' => 						'in '.implode(',', [CRoleHelper::SERVICES_ACCESS_NONE, CRoleHelper::SERVICES_ACCESS_ALL, CRoleHelper::SERVICES_ACCESS_LIST]),
			'service_write_list' => 						'array_db services.serviceid',
			'service_write_tag_tag' => 						'string',
			'service_write_tag_value' => 					'string',
			'form_refresh' => 								'int32'
		];

		$ret = $this->validateInput($fields);
		$error = $this->getValidationError();

		if (!$ret) {
			switch ($error) {
				case self::VALIDATION_ERROR:
					$response = new CControllerResponseRedirect('zabbix.php?action=userrole.edit');
					$response->setFormData($this->getInputAll());
					CMessageHelper::setErrorTitle(_('Cannot update user role'));
					$this->setResponse($response);
					break;

				case self::VALIDATION_FATAL_ERROR:
					$this->setResponse(new CControllerResponseFatal());
					break;
			}
		}

		return $ret;
	}

	/**
	 * @throws APIException
	 */
	protected function checkPermissions(): bool {
		if (!$this->checkAccess(CRoleHelper::UI_ADMINISTRATION_USER_ROLES)) {
			return false;
		}

		if ($this->getInput('roleid', 0) != 0) {
			$roles = API::Role()->get([
				'output' => ['roleid', 'name', 'type', 'readonly'],
				'roleids' => $this->getInput('roleid'),
				'filter' => [
					'readonly' => '0'
				],
				'editable' => true
			]);

			if (!$roles) {
				return false;
			}

			$this->role = $roles[0];
		}

		return true;
	}

	/**
	 * @throws APIException
	 */
	protected function doAction(): void {
		$role = [
			'roleid' => $this->getInput('roleid', '0'),
			'name' => trim($this->getInput('name')),
			'type' => $this->getInput('type', USER_TYPE_ZABBIX_USER)
		];

		$role['rules'] = $this->getRulesInput((int) $role['type']);

		$result = API::Role()->update($role);

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
				(new CUrl('zabbix.php'))
					->setArgument('action', 'userrole.edit')
					->setArgument('roleid', $this->getInput('roleid'))
			);
			CMessageHelper::setErrorTitle(_('Cannot update user role'));
			$response->setFormData($this->getInputAll());
		}

		$this->setResponse($response);
	}
}
