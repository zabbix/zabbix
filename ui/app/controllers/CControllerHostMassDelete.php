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


class CControllerHostMassDelete extends CController {

	protected function checkInput(): bool {
		$fields = [
			'hostids' => 'required|array_db hosts.hostid'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'title' => CMessageHelper::getTitle(),
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])])
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS);
	}

	protected function doAction(): void {
		$output = [];
		$hostids = $this->getInput('hostids');
		$hosts_count = count($hostids);
		$result = API::Host()->delete($hostids);

		if (!$result) {
			$hostids = API::Host()->get([
				'output' => [],
				'hostids' => $hostids,
				'editable' => true
			]);

			$output['keepids'] = array_column($hostids, 'hostid');
		}

		if ($result) {
			$success = ['title' => _n('Host deleted', 'Hosts deleted', $hosts_count)];

			if ($messages = get_and_clear_messages()) {
				$success['messages'] = array_column($messages, 'message');
			}

			$success['action'] = 'delete';
			$output['success'] = $success;
		}
		else {
			CMessageHelper::setErrorTitle(_n('Cannot delete host', 'Cannot delete hosts', $hosts_count));

			$output['error'] = [
				'title' => CMessageHelper::getTitle(),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
