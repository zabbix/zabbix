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


class CControllerActionDelete extends CController {

	protected function checkInput(): bool {
		$fields = [
			'eventsource'=> 'in '.implode(',', [
					EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_DISCOVERY, EVENT_SOURCE_AUTOREGISTRATION,
					EVENT_SOURCE_INTERNAL, EVENT_SOURCE_SERVICE
			]),
			'g_actionid' => 'array_id',
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
		if ($this->hasInput('g_actionid')) {
			$actionids = $this->getInput('g_actionid', []);

			$actions_count = count($actionids);

			$result = API::Action()->delete($actionids);

			if ($result) {
				$output['success']['title'] = (_n('Selected action deleted', 'Selected actions deleted', $actions_count));

				if ($messages = get_and_clear_messages()) {
					$output['success']['messages'] = array_column($messages, 'message');
				}
			}
			else {
				$output['error'] = [
					'title' => (_n('Cannot delete selected action', 'Cannot delete selected actions', $actions_count)),
					'messages' => array_column(get_and_clear_messages(), 'message')
				];
			}

			$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
		}
	}
}
