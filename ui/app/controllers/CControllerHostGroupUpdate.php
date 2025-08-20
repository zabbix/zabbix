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


class CControllerHostGroupUpdate extends CController {

	private $group;

	protected function init(): void {
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	public static function getValidationRules(): array {
		$api_uniq = [
			'hostgroup.get', ['name' => '{name}'], 'groupid'
		];

		return ['object', 'api_uniq' => $api_uniq, 'fields' => [
			'groupid' => ['db hstgrp.groupid', 'required'],
			'name' => ['db hstgrp.name', 'required', 'not_empty', 'use' => [CHostGroupNameParser::class, []],
				'messages' => ['use' => _('Invalid host group name.')]
			],
			'subgroups' => ['boolean']
		]];
	}

	protected function checkInput(): bool {
		$ret = $this->validateInput(self::getValidationRules());

		if (!$ret) {
			$form_errors = $this->getValidationError();
			$response = $form_errors
				? ['form_errors' => $form_errors]
				: ['error' => [
					'title' => _('Cannot update host group'),
					'messages' => array_column(get_and_clear_messages(), 'message')
				]];

			$this->setResponse(new CControllerResponseData(['main_block' => json_encode($response)]));
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		if (!$this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOST_GROUPS)) {
			return false;
		}

		$db_groups = API::HostGroup()->get([
			'output' => ['flags'],
			'groupids' => $this->getInput('groupid'),
			'editable' => true
		]);

		if ($db_groups) {
			$this->group = $db_groups[0];
		}

		return (bool) $db_groups;
	}

	protected function doAction(): void {
		$groupid = $this->getInput('groupid');
		$name = $this->getInput('name');

		DBstart();
		$result = $this->group['flags'] == ZBX_FLAG_DISCOVERY_NORMAL
			? API::HostGroup()->update([
				'groupid' => $groupid,
				'name' => $name
			])
			: true;

		if ($result && $this->getInput('subgroups', 0)) {
			$result = API::HostGroup()->propagate([
				'groups' => [
					'groupid' => $groupid
				],
				'permissions' => true,
				'tag_filters' => true
			]);
		}
		$result = DBend($result);

		$output = [];

		if ($result) {
			$output['success']['title'] = _('Host group updated');

			if ($messages = get_and_clear_messages()) {
				$output['success']['messages'] = array_column($messages, 'message');
			}
		}
		else {
			$output['error'] = [
				'title' => _('Cannot update host group'),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		$this->setResponse((new CControllerResponseData(['main_block' => json_encode($output)]))->disableView());
	}
}
