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


class CControllerActionDelete extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'actionids' =>	'required|array_db actions.actionid'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])])
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		$permissions = array_filter([
			EVENT_SOURCE_TRIGGERS => $this->checkAccess(CRoleHelper::UI_CONFIGURATION_TRIGGER_ACTIONS),
			EVENT_SOURCE_DISCOVERY => $this->checkAccess(CRoleHelper::UI_CONFIGURATION_DISCOVERY_ACTIONS),
			EVENT_SOURCE_AUTOREGISTRATION => $this->checkAccess(CRoleHelper::UI_CONFIGURATION_AUTOREGISTRATION_ACTIONS),
			EVENT_SOURCE_INTERNAL => $this->checkAccess(CRoleHelper::UI_CONFIGURATION_INTERNAL_ACTIONS),
			EVENT_SOURCE_SERVICE => $this->checkAccess(CRoleHelper::UI_CONFIGURATION_SERVICE_ACTIONS)
		]);

		if (!$permissions) {
			return false;
		}

		$actionids = $this->getInput('actionids');

		return count($actionids) == API::Action()->get([
				'countOutput' => true,
				'actionids' => $actionids,
				'filter' => ['eventsource' => array_keys($permissions)]
			]);
	}

	protected function doAction(): void {
		$actionids = $this->getInput('actionids');
		$actions_count = count($actionids);

		$result = API::Action()->delete($actionids);

		$output = [];

		if ($result) {
			$output['success']['title'] = _n('Action deleted', 'Actions deleted', $actions_count);

			if ($messages = get_and_clear_messages()) {
				$output['success']['messages'] = array_column($messages, 'message');
			}
		}
		else {
			$output['error'] = [
				'title' => _n('Cannot delete action', 'Cannot delete actions', $actions_count),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];

			$actions = API::Action()->get([
				'output' => [],
				'actionids' => $actionids,
				'preservekeys' => true
			]);

			$output['keepids'] = array_keys($actions);
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
