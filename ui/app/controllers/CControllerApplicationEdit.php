<?php declare(strict_types=1);
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


class CControllerApplicationEdit extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'name'          => 'db applications.name',
			'hostid'        => 'required|db hosts.hostid',
			'applicationid' => 'db applications.applicationid'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		if ($this->getUserType() < USER_TYPE_ZABBIX_ADMIN) {
			return false;
		}

		if ($this->getInput('applicationid', 0) != 0) {
			$db_applications = (bool) API::Application()->get([
				'output' => [],
				'applicationids' => $this->getInput('applicationid'),
				'editable' => true
			]);

			if (!$db_applications) {
				return false;
			}
		}

		return isWritableHostTemplates((array) $this->getInput('hostid'));
	}

	protected function doAction() {
		$data = [
			'hostid' => $this->getInput('hostid', 0),
			'applicationid' => $this->getInput('applicationid', 0),
			'name' => $this->getInput('name', '')
		];

		if ($data['applicationid'] != 0) {
			$db_applications = API::Application()->get([
				'output' => ['applicationid', 'name', 'hostid'],
				'applicationids' => $data['applicationid'],
				'editable' => true
			]);

			$data['name'] = $this->getInput('name', $db_applications[0]['name']);
			$data['hostid'] = $db_applications[0]['hostid'];
		}

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of applications'));
		$this->setResponse($response);
	}
}
