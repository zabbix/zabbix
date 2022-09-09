<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


class CControllerActionEnable extends CController {
	protected function checkInput(): bool {
		$fields = [
			'eventsource'=> 'in '.implode(',', [
					EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_DISCOVERY, EVENT_SOURCE_AUTOREGISTRATION,
					EVENT_SOURCE_INTERNAL, EVENT_SOURCE_SERVICE
			]),
			'g_actionid' => 'array_id|required|not_empty'
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
		$eventsource = $this->getInput('eventsource');
		$has_permission = false;

		switch ($eventsource) {
			case EVENT_SOURCE_TRIGGERS:
				$has_permission = $this->checkAccess(CRoleHelper::UI_CONFIGURATION_TRIGGER_ACTIONS);
				break;

			case EVENT_SOURCE_DISCOVERY:
				$has_permission =  $this->checkAccess(CRoleHelper::UI_CONFIGURATION_DISCOVERY_ACTIONS);
				break;

			case EVENT_SOURCE_AUTOREGISTRATION:
				$has_permission =  $this->checkAccess(CRoleHelper::UI_CONFIGURATION_AUTOREGISTRATION_ACTIONS);
				break;

			case EVENT_SOURCE_INTERNAL:
				$has_permission =  $this->checkAccess(CRoleHelper::UI_CONFIGURATION_INTERNAL_ACTIONS);
				break;

			case EVENT_SOURCE_SERVICE:
				$has_permission =  $this->checkAccess(CRoleHelper::UI_CONFIGURATION_SERVICE_ACTIONS);
				break;
		}

		return $has_permission;
	}

	protected function doAction(): void {
		$eventsource = $this->getInput('eventsource');

		if ($this->hasInput('g_actionid')) {
			$actionids = $this->getInput('g_actionid', []);
			$actions_count = count($actionids);
			$actions = [];

			foreach ($actionids as $actionid) {
				$actions[] = ['actionid' => $actionid, 'status' => ACTION_STATUS_ENABLED];
			}

			$result = API::Action()->update($actions);

			if ($result && array_key_exists('actionids', $result)) {
				CMessageHelper::setSuccessTitle(_n('Action enabled', 'Actions enabled', $actions_count));
			}
			else {
				CMessageHelper::setErrorTitle(_n('Cannot enable action ', 'Cannot enable actions', $actions_count));
			}

			uncheckTableRows('g_actionid');

			$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))
				->setArgument('action', 'action.list')
				->setArgument('eventsource', $eventsource)
			);

			$this->setResponse($response);
		}
	}
}
