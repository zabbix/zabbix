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
 * Class containing operations with userrole update form.
 */
class CControllerUserroleUpdate extends CControllerUserroleEditGeneral {

	protected $role = [];

	protected function init(): void {
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	public static function getValidationRules(): array {
		$api_uniq = [
			['role.get', ['name' => '{name}'], 'roleid']
		];

		return ['object', 'api_uniq' => $api_uniq, 'fields' => [
			'roleid' => ['db role.roleid', 'required'],
			'name' => ['db role.name', 'required', 'not_empty'],
			'type' => ['db role.type', 'required', 'in' => [USER_TYPE_ZABBIX_USER, USER_TYPE_ZABBIX_ADMIN,
				USER_TYPE_SUPER_ADMIN
			]],
			'ui' => ['array', 'required', 'not_empty',
				'field' => ['string', 'in' => CRoleHelper::getUiElementsByUserType(USER_TYPE_SUPER_ADMIN)],
				'messages' => ['not_empty' => _('At least one UI element must be checked.')]
			],
			'ui_default_access' => ['boolean'],
			'modules' => ['array', 'required', 'field' => ['boolean']],
			'modules_default_access' => ['boolean'],
			'actions' => ['array', 'required',
				'field' => ['string', 'in' => CRoleHelper::getActionsByUserType(USER_TYPE_SUPER_ADMIN)]
			],
			'actions_default_access' => ['boolean'],
			'api_access' => ['boolean'],
			'api_mode' => ['integer', 'required', 'when' => ['api_access', 'in' => [1]],
				'in' => [ZBX_ROLE_RULE_API_MODE_DENY, ZBX_ROLE_RULE_API_MODE_ALLOW]
			],
			'api_methods' => ['array'],
			'service_read_access' => ['integer',
				'in' => [CRoleHelper::SERVICES_ACCESS_NONE, CRoleHelper::SERVICES_ACCESS_ALL,
					CRoleHelper::SERVICES_ACCESS_LIST
				]
			],
			'service_read_list' => ['array',
				'field' => ['db role_rule.value_serviceid'],
				'when' => ['service_read_access', 'in' => [CRoleHelper::SERVICES_ACCESS_LIST]]
			],
			'service_read_tag_value' => ['string',
				'when' => ['service_read_access', 'in' => [CRoleHelper::SERVICES_ACCESS_LIST]]
			],
			'service_read_tag_tag' => [
				['string', 'when' => ['service_read_access', 'in' => [CRoleHelper::SERVICES_ACCESS_LIST]]],
				['string', 'required', 'not_empty',
					'when' => [
						['service_read_access', 'in' => [CRoleHelper::SERVICES_ACCESS_LIST]],
						['service_read_tag_value', 'not_empty']
					]
				]
			],
			'service_write_access' => ['integer',
				'in' => [CRoleHelper::SERVICES_ACCESS_NONE, CRoleHelper::SERVICES_ACCESS_ALL,
					CRoleHelper::SERVICES_ACCESS_LIST
				]
			],
			'service_write_list' => ['array',
				'field' => ['db role_rule.value_serviceid'],
				'when' => ['service_write_access', 'in' => [CRoleHelper::SERVICES_ACCESS_LIST]]
			],
			'service_write_tag_value' => ['string',
				'when' => ['service_write_access', 'in' => [CRoleHelper::SERVICES_ACCESS_LIST]]
			],
			'service_write_tag_tag' => [
				['string', 'when' => ['service_write_access', 'in' => [CRoleHelper::SERVICES_ACCESS_LIST]]],
				['string', 'required', 'not_empty',
					'when' => [
						['service_write_access', 'in' => [CRoleHelper::SERVICES_ACCESS_LIST]],
						['service_write_tag_value', 'not_empty']
					]
				]
			],
			'form_refresh' => ['integer']
		]];
	}

	protected function checkInput(): bool {
		$ret = $this->validateInput(self::getValidationRules());

		if (!$ret) {
			$form_errors = $this->getValidationError();

			$response = $form_errors
				? ['form_errors' => $form_errors]
				: ['error' => [
					'title' => _('Cannot update user role'),
					'messages' => array_column(get_and_clear_messages(), 'message')
				]];

			$this->setResponse(new CControllerResponseData(['main_block' => json_encode($response)]));
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

		$output = [];

		if ($result) {
			$output['success']['title'] = _('User role updated');
			$output['success']['redirect'] = (new CUrl('zabbix.php'))
				->setArgument('action', 'userrole.list')
				->setArgument('page', CPagerHelper::loadPage('userrole.list', null))
				->getUrl();
		}
		else {
			$output['error'] = [
				'title' => _('Cannot update user role'),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
