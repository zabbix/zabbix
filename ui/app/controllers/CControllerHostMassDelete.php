<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS);
	}

	protected function doAction(): void {

		$result = API::Host()->delete($this->getInput('hostids'));

		if ($result) {
			$output = ['title' => _('Host deleted')];

			if ($messages = CMessageHelper::getMessages()) {
				$output['messages'] = array_column($messages, 'message');
			}
		}
		else {
			$output = [
				'errors' => makeMessageBox(ZBX_STYLE_MSG_BAD, filter_messages(), _('Cannot delete host'))->toString()
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
