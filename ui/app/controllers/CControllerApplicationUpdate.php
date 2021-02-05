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


class CControllerApplicationUpdate extends CController {

	protected function checkInput() {
		$fields = [
			'name'          => 'db applications.name',
			'applicationid' => 'required|db applications.applicationid',
			'hostid'        => 'required|db hosts.hostid'
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

		$result = (bool) API::Application()->get([
			'output' => [],
			'applicationids' => $this->getInput('applicationid'),
			'editable' => true
		]);

		return $result && isWritableHostTemplates((array) $this->getInput('hostid'));
	}

	protected function doAction() {
		$result = (bool) API::Application()->update([
			'applicationid' => $this->getInput('applicationid'),
			'name' => $this->getInput('name')
		]);

		if ($result) {
			$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))
				->setArgument('action', 'application.list')
				->setArgument('page', CPagerHelper::loadPage('application.list', null))
			);
			$response->setFormData(['uncheck' => '1']);
			CMessageHelper::setSuccessTitle(_('Application updated'));
		}
		else {
			$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))
				->setArgument('action', 'application.edit')
				->setArgument('applicationid', $this->getInput('applicationid'))
				->setArgument('hostid', $this->getInput('hostid'))
			);
			$response->setFormData($this->getInputAll());
			CMessageHelper::setErrorTitle(_('Cannot update application'));
		}

		$this->setResponse($response);
	}
}
