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
 * Class containing operations with userrole edit form.
 */
class CControllerUserroleEdit extends CControllerUserroleEditGeneral {

	private $role;

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		global $ZBX_FEATURE_FLAGS;

		$fields = [
			'roleid' =>					'db users.roleid',
			'name' =>					'db role.name',
			'super_admin_role_clone' =>	'in 1'
		];

		if (!$ZBX_FEATURE_FLAGS['modules_config_enabled']) {
			unset($fields['fields']['modules'], $fields['fields']['modules_default_access']);
		}

		if (!CSettingsHelper::isMobileDevicesEnabled()) {
			unset($fields['fields']['ui_administration_linked_devices'], $fields['fields']['devices_actions'],
				$fields['fields']['devices_actions_default_access']
			);
		}

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
		global $ZBX_FEATURE_FLAGS;

		$db_defaults = DB::getDefaults('role');

		$data = match (true) {
			$this->hasInput('super_admin_role_clone') => [
				'roleid' => null,
				'name' => $this->getInput('name'),
				'type' => USER_TYPE_SUPER_ADMIN,
				'readonly' => (bool) $db_defaults['readonly'],
				'is_own_role' => false,
				'rules' => array_merge(
					$this->getRulesDefaults(USER_TYPE_SUPER_ADMIN),
					$this->getRulesByRoleid(USER_TYPE_SUPER_ADMIN)
				)
			],
			$this->role === null => [
				'roleid' => null,
				'name' => $db_defaults['name'],
				'type' => $db_defaults['type'],
				'readonly' => (bool) $db_defaults['readonly'],
				'is_own_role' => false,
				'rules' => $this->getRulesDefaults((int) $db_defaults['type'])
			],
			default => [
				'roleid' => $this->role['roleid'],
				'name' => $this->role['name'],
				'type' => $this->role['type'],
				'readonly' => (bool) $this->role['readonly'],
				'is_own_role' => bccomp($this->role['roleid'], CWebUser::$data['roleid']) == 0,
				'rules' => array_merge(
					$this->getRulesDefaults((int) $this->role['type']),
					$this->getRulesByRoleid($this->role['roleid'])
				)
			]
		};

		$data['rules']['modules_config_enabled'] = $ZBX_FEATURE_FLAGS['modules_config_enabled'];
		$data['rules']['service_read_list'] = $this->getServicesList($data['rules']['service_read_list']);
		$data['rules']['service_write_list'] = $this->getServicesList($data['rules']['service_write_list']);

		$db_modules = $data['rules']['modules_config_enabled']
			? API::Module()->get([
				'output' => ['moduleid', 'relative_path', 'status']
			])
			: [];

		$disabled_modules = array_filter($db_modules,
			static fn(array $db_module): bool => $db_module['status'] == MODULE_STATUS_DISABLED
		);

		$data['js_validation_rules_create'] = (new CFormValidator(CControllerUserroleCreate::getValidationRules()))
			->getRules();

		$data += [
			'disabled_moduleids' => array_column($disabled_modules, 'moduleid', 'moduleid'),
			'labels' => $this->getLabels($db_modules),
			'js_validation_rules' => $data['roleid'] === null
				? $data['js_validation_rules_create']
				: (new CFormValidator(CControllerUserroleUpdate::getValidationRules()))->getRules()
		];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of user roles'));
		$this->setResponse($response);
	}

	private function getServicesList(array $serviceids): array {
		return API::Service()->get([
			'output' => ['serviceid', 'name'],
			'serviceids' => array_column($serviceids, 'serviceid')
		]);
	}

	private function getLabels(array $db_modules): array {
		$labels = [
			'sections' => CRoleHelper::getUiSectionsLabels(USER_TYPE_SUPER_ADMIN),
			'actions' => CRoleHelper::getActionsLabels(USER_TYPE_SUPER_ADMIN),
			'devices_actions' => CRoleHelper::getDevicesActionsLabels(USER_TYPE_SUPER_ADMIN)
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
			'api.access' => false,
			'api.mode' => 'api.mode',
			'actions' => array_fill_keys(CRoleHelper::getActionsByUserType($user_type), true),
			'actions.default_access' => true,
			'devices.access' => ZBX_ROLE_RULE_DISABLED,
			'devices.actions' => array_fill_keys(CRoleHelper::getDeviceActionsByUserType($user_type), false),
			'devices.actions.default_access' => ZBX_ROLE_RULE_DISABLED
		];
	}

	/**
	 * @throws APIException
	 */
	private function getRulesByRoleid(string $roleid): array {
		global $ZBX_FEATURE_FLAGS;

		$select_rules = ['ui', 'ui.default_access', 'api', 'api.access', 'api.mode', 'actions',
			'actions.default_access', 'services.read.mode', 'services.read.list', 'services.read.tag',
			'services.write.mode', 'services.write.list', 'services.write.tag', 'devices.access', 'devices.actions',
			'devices.actions.default_access'
		];

		if ($ZBX_FEATURE_FLAGS['modules_config_enabled']) {
			$select_rules = array_merge($select_rules, ['modules', 'modules.default_access']);
		}

		$roles = API::Role()->get([
			'output' => ['roleid'],
			'selectRules' => $select_rules,
			'roleids' => $roleid
		]);

		return $this->getRules($roles[0]['rules']);
	}

	private function getRules(array $input): array {
		global $ZBX_FEATURE_FLAGS;

		$rules = [
			'ui' => [],
			'actions' => [],
			'service_read_access' => match (true) {
				$input['services.read.mode'] == ZBX_ROLE_RULE_SERVICES_ACCESS_ALL => CRoleHelper::SERVICES_ACCESS_ALL,
				$input['services.read.list'] || $input['services.read.tag']['tag'] !== '' =>
					CRoleHelper::SERVICES_ACCESS_LIST,
				default => CRoleHelper::SERVICES_ACCESS_NONE
			},
			'service_read_list' => $input['services.read.list'],
			'service_read_tag' => $input['services.read.tag'],
			'service_write_access' => match (true) {
				$input['services.write.mode'] == ZBX_ROLE_RULE_SERVICES_ACCESS_ALL => CRoleHelper::SERVICES_ACCESS_ALL,
				$input['services.write.list'] || $input['services.write.tag']['tag'] !== '' =>
					CRoleHelper::SERVICES_ACCESS_LIST,
				default => CRoleHelper::SERVICES_ACCESS_NONE
			},
			'service_write_list' => $input['services.write.list'],
			'service_write_tag' => $input['services.write.tag'],
			'ui.default_access' => $input['ui.default_access'],
			'api.access' => $input['api.access'],
			'api.mode' => $input['api.mode'],
			'actions.default_access' => $input['actions.default_access'],
			'devices.access' => $input['devices.access'],
			'devices.actions.default_access' => $input['devices.actions.default_access']
		];

		foreach ($input['ui'] as $rule) {
			$rules['ui']['ui.'.$rule['name']] = $rule['status'];
		}

		if ($ZBX_FEATURE_FLAGS['modules_config_enabled']) {
			$rules += [
				'modules' => [],
				'modules.default_access' => $input['modules.default_access']
			];
			foreach ($input['modules'] as $rule) {
				$rules['modules'][$rule['moduleid']] = $rule['status'];
			}
		}

		if ($input['api']) {
			$rules += [
				'api' => array_map(
					static fn(string $method): array => ['id' => $method, 'name' => $method],
					$input['api']
				)
			];
		}

		foreach ($input['actions'] as $rule) {
			$rules['actions']['actions.'.$rule['name']] = $rule['status'];
		}

		foreach ($input['devices.actions'] as $rule) {
			$rules['devices.actions']['devices.actions.'.$rule['name']] = $rule['status'];
		}

		return $rules;
	}
}
