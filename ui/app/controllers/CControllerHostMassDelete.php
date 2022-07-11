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
			$success = ['title' => _('Host deleted')];

			if ($messages = get_and_clear_messages()) {
				$success['messages'] = array_column($messages, 'message');
			}

			$output['success'] = $success;
		}
		else {
			CMessageHelper::setErrorTitle(_('Cannot delete host'));

			$output['error'] = [
				'title' => CMessageHelper::getTitle(),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
