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


class CControllerMaintenanceDelete extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'maintenanceids' =>	'required|array_id'
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
		return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_MAINTENANCE)
			&& $this->checkAccess(CRoleHelper::ACTIONS_EDIT_MAINTENANCE);
	}

	protected function doAction(): void {
		$maintenanceids = $this->getInput('maintenanceids');

		$result = API::Maintenance()->delete($maintenanceids);

		$output = [];

		if ($result) {
			$output['success']['title'] = _n('Maintenance period deleted', 'Maintenance periods deleted',
				count($maintenanceids)
			);

			if ($messages = get_and_clear_messages()) {
				$output['success']['messages'] = array_column($messages, 'message');
			}
		}
		else {
			$output['error'] = [
				'title' => _n('Cannot delete maintenance period', 'Cannot delete maintenance periods',
					count($maintenanceids)
				),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];

			$maintenances = API::Maintenance()->get([
				'output' => [],
				'maintenanceids' => $maintenanceids,
				'editable' => true,
				'preservekeys' => true
			]);

			$output['keepids'] = array_keys($maintenances);
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
