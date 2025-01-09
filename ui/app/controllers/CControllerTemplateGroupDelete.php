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


class CControllerTemplateGroupDelete extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'groupids' => 'required|array_db hstgrp.groupid'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'title' => _('Cannot delete host groups'),
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])])
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATE_GROUPS);
	}

	protected function doAction(): void {
		$groupids = $this->getInput('groupids');
		$result = API::TemplateGroup()->delete($groupids);
		$deleted = count($groupids);
		$output = [];

		if ($result) {
			$output['success']['title'] = _n('Template group deleted', 'Template groups deleted', $deleted);

			if ($messages = get_and_clear_messages()) {
				$output['success']['messages'] = array_column($messages, 'message');
			}
		}
		else {
			$groups = API::TemplateGroup()->get([
				'output' => [],
				'groupids' => $groupids,
				'editable' => true,
				'preservekeys' => true
			]);

			$output['error'] = [
				'title' => _n('Cannot delete template group', 'Cannot delete template groups', $deleted),
				'messages' => array_column(get_and_clear_messages(), 'message'),
				'keepids' => array_keys($groups)
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
