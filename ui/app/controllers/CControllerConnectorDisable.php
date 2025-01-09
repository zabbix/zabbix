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


class CControllerConnectorDisable extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'connectorids' =>	'required|array_db connector.connectorid'
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
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL);
	}

	protected function doAction(): void {
		$connectorids = $this->getInput('connectorids');

		$update = [];

		foreach ($connectorids as $connectorid) {
			$update[] = [
				'connectorid' => $connectorid,
				'status' => ZBX_CONNECTOR_STATUS_DISABLED
			];
		}

		$result = API::Connector()->update($update);

		$output = [];

		if ($result) {
			$output['success']['title'] = _n('Connector disabled', 'Connectors disabled', count($connectorids));

			if ($messages = get_and_clear_messages()) {
				$output['success']['messages'] = array_column($messages, 'message');
			}
		}
		else {
			$output['error'] = [
				'title' => _n('Cannot disable connector', 'Cannot disable connectors', count($connectorids)),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];

			$connectors = API::Connector()->get([
				'output' => [],
				'connectorids' => $connectorids,
				'preservekeys' => true
			]);

			$output['keepids'] = array_keys($connectors);
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
