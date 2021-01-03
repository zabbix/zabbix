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


class CControllerTrigSeverityUpdate extends CController {

	protected function checkInput() {
		$fields = [
			'severity_name_0'  => 'required | string | not_empty',
			'severity_name_1'  => 'required | string | not_empty',
			'severity_name_2'  => 'required | string | not_empty',
			'severity_name_3'  => 'required | string | not_empty',
			'severity_name_4'  => 'required | string | not_empty',
			'severity_name_5'  => 'required | string | not_empty',
			'severity_color_0' => 'required | rgb',
			'severity_color_1' => 'required | rgb',
			'severity_color_2' => 'required | rgb',
			'severity_color_3' => 'required | rgb',
			'severity_color_4' => 'required | rgb',
			'severity_color_5' => 'required | rgb'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))
				->setArgument('action', 'trigseverity.edit')
			);
			$response->setMessageError(_('Cannot update configuration'));
			$response->setFormData($this->getInputAll());
			$this->setResponse($response);
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() == USER_TYPE_SUPER_ADMIN);
	}

	protected function doAction() {
		$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))
			->setArgument('action', 'trigseverity.edit')
		);

		$result = update_config([
			'severity_name_0'  => $this->getInput('severity_name_0'),
			'severity_color_0' => $this->getInput('severity_color_0'),
			'severity_name_1'  => $this->getInput('severity_name_1'),
			'severity_color_1' => $this->getInput('severity_color_1'),
			'severity_name_2'  => $this->getInput('severity_name_2'),
			'severity_color_2' => $this->getInput('severity_color_2'),
			'severity_name_3'  => $this->getInput('severity_name_3'),
			'severity_color_3' => $this->getInput('severity_color_3'),
			'severity_name_4'  => $this->getInput('severity_name_4'),
			'severity_color_4' => $this->getInput('severity_color_4'),
			'severity_name_5'  => $this->getInput('severity_name_5'),
			'severity_color_5' => $this->getInput('severity_color_5')
		]);

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
