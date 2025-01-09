<?php
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


class CControllerIconMapCreate extends CController {

	protected function checkInput() {
		$fields = [
			'iconmap'   => 'required|array'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL);
	}

	protected function doAction() {
		$result = (bool) API::IconMap()->create($this->getInput('iconmap'));

		if ($result) {
			$response = new CControllerResponseRedirect(
				(new CUrl('zabbix.php'))->setArgument('action', 'iconmap.list')
			);
			CMessageHelper::setSuccessTitle(_('Icon map created'));
		}
		else {
			$response = new CControllerResponseRedirect(
				(new CUrl('zabbix.php'))->setArgument('action', 'iconmap.edit')
			);
			$response->setFormData($this->getInputAll());
			CMessageHelper::setErrorTitle(_('Cannot create icon map'));
		}

		$this->setResponse($response);
	}
}
