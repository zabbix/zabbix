<?php declare(strict_types=0);
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


class CControllerHostDisable extends CControllerHostUpdateGeneral {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS);
	}

	protected function checkInput(): bool {
		$fields = [
			'hostids'	=> 'required|array'
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

	public function doAction() {
		$output = [];
		$hosts = [];

		foreach ($this->getInput('hostids') as $hostid) {
			$hosts[] = ['hostid' => $hostid, 'status' => HOST_STATUS_NOT_MONITORED];
		}

		$result = API::Host()->update($hosts);
		$messages = array_column(get_and_clear_messages(), 'message');
		$count = count($hosts);

		if ($result) {
			$output['success']['title'] = _n('Host disabled', 'Hosts disabled', $count);

			if ($messages) {
				$output['success']['messages'] = $messages;
			}
		}
		else {
			$output['error'] = [
				'title' => _n('Cannot disable host', 'Cannot disable hosts', $count),
				'messages' => $messages
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
