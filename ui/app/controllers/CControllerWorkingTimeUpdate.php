<?php
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


class CControllerWorkingTimeUpdate extends CController {

	protected function checkInput() {
		$fields = [
			'work_period' => 'required | time_periods'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))
				->setArgument('action', 'workingtime.edit')
				->getUrl()
			);
			$response->setFormData($this->getInputAll());
			$response->setMessageError(_('Cannot update configuration'));
			$this->setResponse($response);
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() == USER_TYPE_SUPER_ADMIN);
	}

	protected function doAction() {
		$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))
			->setArgument('action', 'workingtime.edit')
		);

		DBstart();
		$result = update_config(['work_period' => $this->getInput('work_period')]);
		$result = DBend($result);

		if ($result) {
			$response->setMessageOk(_('Configuration updated'));
		}
		else {
			$response->setFormData($this->getInputAll());
			$response->setMessageError(_('Cannot update configuration'));
		}

		$this->setResponse($response);
	}
}
