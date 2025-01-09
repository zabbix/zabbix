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


class CControllerTemplateGroupUpdate extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'groupid' => 	'fatal|required|db hstgrp.groupid',
			'name' => 		'db hstgrp.name',
			'subgroups' => 	'in 0,1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'title' => _('Cannot update template group'),
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])])
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		if (!$this->checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATE_GROUPS)) {
			return false;
		}

		return (bool) API::TemplateGroup()->get([
			'output' => [],
			'groupids' => $this->getInput('groupid'),
			'editable' => true
		]);
	}

	protected function doAction(): void {
		$groupid = $this->getInput('groupid');
		$name = $this->getInput('name');

		DBstart();
		$result = API::TemplateGroup()->update([
			'groupid' => $groupid,
			'name' => $name
		]);

		if ($result && $this->getInput('subgroups', 0)) {
			$result = API::TemplateGroup()->propagate([
				'groups' => [
					'groupid' => $groupid
				],
				'permissions' => true
			]);
		}
		$result = DBend($result);

		$output = [];

		if ($result) {
			$output['success']['title'] = _('Template group updated');

			if ($messages = get_and_clear_messages()) {
				$output['success']['messages'] = array_column($messages, 'message');
			}
		}
		else {
			$output['error'] = [
				'title' => _('Cannot update template group'),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		$this->setResponse((new CControllerResponseData(['main_block' => json_encode($output)]))->disableView());
	}
}
