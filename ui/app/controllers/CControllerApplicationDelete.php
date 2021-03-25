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


class CControllerApplicationDelete extends CController {

	protected function checkInput() {
		$fields = [
			'applicationids' => 'required|array_db applications.applicationid'
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

		return count($this->getInput('applicationids')) == API::Application()->get([
			'countOutput' => true,
			'applicationids' => $this->getInput('applicationids'),
			'editable' => true
		]);
	}

	protected function doAction() {
		$applicationids = $this->getInput('applicationids');

		$result = API::Application()->delete($applicationids);

		$deleted = count($applicationids);

		$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))
			->setArgument('action', 'application.list')
			->setArgument('page', CPagerHelper::loadPage('application.list', null))
		);

		if ($result) {
			$response->setFormData(['uncheck' => '1']);
			CMessageHelper::setSuccessTitle(_n('Application deleted', 'Applications deleted', $deleted));
		}
		else {
			CMessageHelper::setErrorTitle(_n('Cannot delete application', 'Cannot delete applications', $deleted));
		}

		$this->setResponse($response);
	}
}
