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


class CControllerMiscConfigUpdate extends CController {

	protected function checkInput() {
		$fields = [
			'refresh_unsupported'    => 'string',
			'discovery_groupid'      => 'db hstgrp.groupid',
			'default_inventory_mode' => 'in '.HOST_INVENTORY_DISABLED.','.HOST_INVENTORY_MANUAL.','.HOST_INVENTORY_AUTOMATIC,
			'alert_usrgrpid'         => 'db usrgrp.usrgrpid',
			'snmptrap_logging'       => 'in 0,1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			switch ($this->getValidationError()) {
				case self::VALIDATION_ERROR:
					$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))
						->setArgument('action', 'miscconfig.edit')
					);

					$response->setFormData($this->getInputAll());
					$response->setMessageError(_('Cannot update configuration'));

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
		$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))
			->setArgument('action', 'miscconfig.edit')
		);

		DBstart();
		$result = update_config([
			'refresh_unsupported'    => trim($this->getInput('refresh_unsupported')),
			'alert_usrgrpid'         => $this->getInput('alert_usrgrpid'),
			'discovery_groupid'      => $this->getInput('discovery_groupid'),
			'default_inventory_mode' => $this->getInput('default_inventory_mode'),
			'snmptrap_logging'       => $this->getInput('snmptrap_logging')
		]);
		$result = DBend($result);

		if ($result) {
			$response->setMessageOk(_('Configuration updated'));
		}
		else {
			$response->setMessageError(_('Cannot update configuration'));
			$response->setFormData($this->getInputAll());
		}

		$this->setResponse($response);
	}
}
