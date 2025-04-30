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
 * Class containing operations with userrole edit form.
 */
class CControllerUserroleEdit extends CControllerUserroleEditGeneral {

	private $role;

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'roleid' => 									'db users.roleid',
			'name' => 										'db role.name',
			'type' => 										'in '.implode(',', [USER_TYPE_ZABBIX_USER, USER_TYPE_ZABBIX_ADMIN, USER_TYPE_SUPER_ADMIN]),
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
			'ui_administration_audit_log' =>				'in 0,1',
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
			'actions_suppress_problems' =>					'in 0,1',
			'actions_change_severity' => 					'in 0,1',
			'actions_add_problem_comments' => 				'in 0,1',
			'actions_execute_scripts' => 					'in 0,1',
			'actions_manage_api_tokens' => 					'in 0,1',
			'actions_manage_scheduled_reports' => 			'in 0,1',
			'actions_manage_sla' => 						'in 0,1',
			'actions_invoke_execute_now' =>					'in 0,1',
			'actions_change_problem_ranking' =>				'in 0,1',
			'actions_edit_own_media' =>						'in 0,1',
			'actions_edit_user_media' =>					'in 0,1',
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
			'form_refresh' => 								'int32',
			'super_admin_role_clone' =>						'in 1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
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

		if ($this->hasInput('roleid')) {
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

	/**
	 * @throws APIException
	 */
	protected function doAction(): void {
		$db_defaults = DB::getDefaults('role');

		if ($this->hasInput('super_admin_role_clone')) {
			$data = [
				'roleid' => null,
				'name' => $this->getInput('name', ''),
				'type' => $this->getInput('type', USER_TYPE_SUPER_ADMIN),
				'readonly' => (bool) $db_defaults['readonly'],
				'is_own_role' => false,
				'rules' => array_merge(
					$this->getRulesDefaults((int) $this->getInput('type', USER_TYPE_SUPER_ADMIN)),
					$this->getRulesByRoleid(USER_TYPE_SUPER_ADMIN)
				)
			];
		}
		elseif ($this->role === null) {
			$data = [
				'roleid' => null,
				'readonly' => (bool) $db_defaults['readonly'],
				'is_own_role' => false
			];

			if (!$this->hasInput('form_refresh')) {
				$data += [
					'name' => $db_defaults['name'],
					'type' => $db_defaults['type'],
					'rules' => $this->getRulesDefaults((int) $db_defaults['type'])
				];
			}
			else {
				$data += [
					'name' => $this->getInput('name', $db_defaults['name']),
					'type' => $this->getInput('type', USER_TYPE_ZABBIX_USER),
					'rules' => array_merge(
						$this->getRulesDefaults((int) $this->getInput('type', USER_TYPE_ZABBIX_USER)),
						$this->getRules($this->getRulesInput($this->getInput('type', USER_TYPE_ZABBIX_USER)))
					)
				];
			}
		}
		else {
			$data = [
				'roleid' => $this->role['roleid'],
				'readonly' => (bool) $this->role['readonly'],
				'is_own_role' => $this->role['roleid'] == CWebUser::$data['roleid']
			];

			if (!$this->hasInput('form_refresh')) {
				$data += [
					'name' => $this->role['name'],
					'type' => $this->role['type'],
					'rules' => array_merge(
						$this->getRulesDefaults((int) $this->role['type']),
						$this->getRulesByRoleid($this->role['roleid'])
					)
				];
			}
			else {
				$data += [
					'name' => $this->getInput('name', $db_defaults['name']),
					'type' => $this->getInput('type', $this->role['type']),
					'rules' => array_merge(
						$this->getRulesDefaults((int) $this->getInput('type', $this->role['type'])),
						$this->getRules($this->getRulesInput($this->getInput('type', $this->role['type'])))
					)
				];
			}
		}

		$db_modules = API::Module()->get([
			'output' => ['moduleid', 'relative_path', 'status']
		]);

		$disabled_modules = array_filter($db_modules,
			static function(array $db_module): bool {
				return $db_module['status'] == MODULE_STATUS_DISABLED;
			}
		);

		$data['disabled_moduleids'] = array_column($disabled_modules, 'moduleid', 'moduleid');

		$data['labels'] = $this->getLabels($db_modules);

		$data['rules']['service_read_list'] = API::Service()->get([
			'output' => ['serviceid', 'name'],
			'serviceids' => array_column($data['rules']['service_read_list'], 'serviceid')
		]);

		$data['rules']['service_write_list'] = API::Service()->get([
			'output' => ['serviceid', 'name'],
			'serviceids' => array_column($data['rules']['service_write_list'], 'serviceid')
		]);

		$data['form_refresh'] = $this->getInput('form_refresh', 0);

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of user roles'));
		$this->setResponse($response);
	}

	private function getFormData(array $data): array {
		$this->getInputs($data, ['name', 'type']);

		// Overwrite default access inputs.
		if ($this->hasInput('ui_default_access')) {
			$data['rules']['ui.default_access'] = $this->getInput('ui_default_access');
		}

		if ($this->hasInput('modules_default_access')) {
			$data['rules']['modules.default_access'] = $this->getInput('modules_default_access');
		}

		if ($this->hasInput('actions_default_access')) {
			$data['rules']['actions.default_access'] = $this->getInput('actions_default_access');
		}

		// UI section.
		foreach (CRoleHelper::getUiElementsByUserType((int) $data['type']) as $label) {
			$input_name = str_replace('.', '_', $label);

			if ($this->hasInput($input_name)) {
				$data['rules']['ui'][$label] = $this->getInput($input_name);
			}
		}

		// Services section.
		if ($this->hasInput('service_read_access')) {
			$data['rules']['service_read_access'] = $this->getInput('service_read_access');
		}

		if ($data['rules']['service_read_access'] == CRoleHelper::SERVICES_ACCESS_LIST) {
			if ($this->hasInput('service_read_list')) {
				foreach ($this->getInput('service_read_list') as $serviceid) {
					$data['rules']['service_read_list'][] = ['serviceid' => $serviceid];
				}
			}

			if ($this->hasInput('service_read_tag_tag')) {
				$data['rules']['service_read_tag']['tag'] = trim($this->getInput('service_read_tag_tag'));
			}

			if ($this->hasInput('service_read_tag_value')) {
				$data['rules']['service_read_tag']['value'] = trim($this->getInput('service_read_tag_value'));
			}
		}

		if ($this->hasInput('service_write_access')) {
			$data['rules']['service_write_access'] = $this->getInput('service_write_access');
		}

		if ($data['rules']['service_write_access'] == CRoleHelper::SERVICES_ACCESS_LIST) {
			if ($this->hasInput('service_write_list')) {
				foreach ($this->getInput('service_write_list') as $serviceid) {
					$data['rules']['service_write_list'][] = ['serviceid' => $serviceid];
				}
			}

			if ($this->hasInput('service_write_tag_tag')) {
				$data['rules']['service_write_tag']['tag'] = trim($this->getInput('service_write_tag_tag'));
			}

			if ($this->hasInput('service_write_tag_value')) {
				$data['rules']['service_write_tag']['value'] = trim($this->getInput('service_write_tag_value'));
			}
		}

		// Modules section.
		if ($this->hasInput('modules')) {
			$data['rules']['modules'] = $this->getInput('modules');
		}

		// API section.
		if ($this->hasInput('api_access')) {
			$data['rules']['api.access'] = $this->getInput('api_access');
		}
		if ($this->hasInput('api_mode')) {
			$data['rules']['api.mode'] = $this->getInput('api_mode');
		}
		foreach ($this->getInput('api_methods', []) as $method) {
			$data['rules']['api'][] = ['id' => $method, 'name' => $method];
		}

		// Actions section.
		foreach (CRoleHelper::getActionsByUserType((int) $data['type']) as $label) {
			$input_name = str_replace('.', '_', $label);

			if ($this->hasInput($input_name)) {
				$data['rules']['actions'][$label] = $this->getInput($input_name);
			}
		}

		return $data;
	}

	private function getLabels(array $db_modules): array {
		$labels = [
			'sections' => CRoleHelper::getUiSectionsLabels(USER_TYPE_SUPER_ADMIN),
			'actions' => CRoleHelper::getActionsLabels(USER_TYPE_SUPER_ADMIN)
		];

		foreach (array_keys(CRoleHelper::getUiSectionsLabels(USER_TYPE_SUPER_ADMIN)) as $section) {
			$labels['rules'][$section] = CRoleHelper::getUiSectionRulesLabels($section, USER_TYPE_SUPER_ADMIN);
		}

		$labels['modules'] = [];

		if ($db_modules) {
			$module_manager = new CModuleManager(APP::getRootDir());

			foreach ($db_modules as $db_module) {
				$manifest = $module_manager->addModule($db_module['relative_path']);

				if ($manifest !== null) {
					$labels['modules'][$db_module['moduleid']] = $manifest['name'];
				}
			}
		}

		natcasesort($labels['modules']);

		return $labels;
	}

	private function getRulesDefaults(int $user_type): array {
		return [
			'ui' => array_fill_keys(CRoleHelper::getUiElementsByUserType($user_type), true),
			'ui.default_access' => true,
			'service_read_access' => CRoleHelper::SERVICES_ACCESS_ALL,
			'service_read_list' => [],
			'service_read_tag' => ['tag' => '', 'value' => ''],
			'service_write_access' => CRoleHelper::SERVICES_ACCESS_NONE,
			'service_write_list' => [],
			'service_write_tag' => ['tag' => '', 'value' => ''],
			'modules' => [],
			'modules.default_access' => true,
			'api' => [],
			'api.access' => true,
			'api.mode' => 'api.mode',
			'actions' => array_fill_keys(CRoleHelper::getActionsByUserType($user_type), true),
			'actions.default_access' => true
		];
	}

	/**
	 * @throws APIException
	 */
	private function getRulesByRoleid(string $roleid): array {
		$roles = API::Role()->get([
			'output' => ['roleid'],
			'selectRules' => ['ui', 'ui.default_access', 'modules', 'modules.default_access', 'api', 'api.access',
				'api.mode', 'actions', 'actions.default_access', 'services.read.mode', 'services.read.list',
				'services.read.tag', 'services.write.mode', 'services.write.list', 'services.write.tag'
			],
			'roleids' => $roleid
		]);

		return $this->getRules($roles[0]['rules']);
	}

	private function getRules(array $input): array {
		$rules = [];

		foreach ($input['ui'] as $rule) {
			$rules['ui']['ui.'.$rule['name']] = $rule['status'];
		}

		if ($input['services.read.mode'] == ZBX_ROLE_RULE_SERVICES_ACCESS_ALL) {
			$rules['service_read_access'] = CRoleHelper::SERVICES_ACCESS_ALL;
		}
		elseif ($input['services.read.list'] || $input['services.read.tag']['tag'] !== '') {
			$rules['service_read_access'] = CRoleHelper::SERVICES_ACCESS_LIST;
		}
		else {
			$rules['service_read_access'] = CRoleHelper::SERVICES_ACCESS_NONE;
		}

		$rules['service_read_list'] = $input['services.read.list'];
		$rules['service_read_tag'] = $input['services.read.tag'];

		if ($input['services.write.mode'] == ZBX_ROLE_RULE_SERVICES_ACCESS_ALL) {
			$rules['service_write_access'] = CRoleHelper::SERVICES_ACCESS_ALL;
		}
		elseif ($input['services.write.list'] || $input['services.write.tag']['tag'] !== '') {
			$rules['service_write_access'] = CRoleHelper::SERVICES_ACCESS_LIST;
		}
		else {
			$rules['service_write_access'] = CRoleHelper::SERVICES_ACCESS_NONE;
		}

		$rules['service_write_list'] = $input['services.write.list'];
		$rules['service_write_tag'] = $input['services.write.tag'];

		foreach ($input['modules'] as $rule) {
			$rules['modules'][$rule['moduleid']] = $rule['status'];
		}

		if ($input['api']) {
			$rules['api'] = array_map(
				static function (string $method): array {
					return [
						'id' => $method,
						'name' => $method
					];
				},
				$input['api']
			);
		}

		foreach ($input['actions'] as $rule) {
			$rules['actions']['actions.'.$rule['name']] = $rule['status'];
		}

		$rules['ui.default_access'] = $input['ui.default_access'];
		$rules['modules.default_access'] = $input['modules.default_access'];
		$rules['api.access'] = $input['api.access'];
		$rules['api.mode'] = $input['api.mode'];
		$rules['actions.default_access'] = $input['actions.default_access'];

		return $rules;
	}
}
