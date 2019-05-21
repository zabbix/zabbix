<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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


class CControllerUsergroupCreate extends CController {

	protected function checkInput() {
		$fields = [
			'name' => 'db usrgrp.name|not_empty'
		];

		$ret = $this->validateInput($fields);
		$error = $this->GetValidationError();

		if (!$ret) {
			switch ($error) {
				case self::VALIDATION_ERROR:
					$response = new CControllerResponseRedirect('zabbix.php?action=usergroup.edit');
					$response->setFormData($this->getInputAll());
					$response->setMessageError(_('Cannot ...'));
					$this->setResponse($response);
					break;

				case self::VALIDATION_FATAL_ERROR:
					$this->setResponse(new CControllerResponseFatal());
					break;
			}
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() == USER_TYPE_SUPER_ADMIN);
	}

	protected function doAction() {
		$result = true;

		if ($result) {
			$response = new CControllerResponseRedirect('zabbix.php?action=usergroup.list');
			$response->setMessageOk(_('.. added'));
		}
		else {
			$response = new CControllerResponseRedirect('zabbix.php?action=usergroup.edit');
			$response->setFormData($this->getInputAll());
			$response->setMessageError(_('Cannot ...'));
		}
		$this->setResponse($response);
	}
}
