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


class CControllerTrigSeverityEdit extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'severity_name_0'  => 'string',
			'severity_color_0' => 'string',
			'severity_name_1'  => 'string',
			'severity_color_1' => 'string',
			'severity_name_2'  => 'string',
			'severity_color_2' => 'string',
			'severity_name_3'  => 'string',
			'severity_color_3' => 'string',
			'severity_name_4'  => 'string',
			'severity_color_4' => 'string',
			'severity_name_5'  => 'string',
			'severity_color_5' => 'string'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() == USER_TYPE_SUPER_ADMIN);
	}

	protected function doAction() {
		$config = select_config();

		$data = [
			'severity_name_0'  => $this->getInput('severity_name_0',  $config['severity_name_0']),
			'severity_color_0' => $this->getInput('severity_color_0', $config['severity_color_0']),
			'severity_name_1'  => $this->getInput('severity_name_1',  $config['severity_name_1']),
			'severity_color_1' => $this->getInput('severity_color_1', $config['severity_color_1']),
			'severity_name_2'  => $this->getInput('severity_name_2',  $config['severity_name_2']),
			'severity_color_2' => $this->getInput('severity_color_2', $config['severity_color_2']),
			'severity_name_3'  => $this->getInput('severity_name_3',  $config['severity_name_3']),
			'severity_color_3' => $this->getInput('severity_color_3', $config['severity_color_3']),
			'severity_name_4'  => $this->getInput('severity_name_4',  $config['severity_name_4']),
			'severity_color_4' => $this->getInput('severity_color_4', $config['severity_color_4']),
			'severity_name_5'  => $this->getInput('severity_name_5',  $config['severity_name_5']),
			'severity_color_5' => $this->getInput('severity_color_5', $config['severity_color_5'])
		];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of trigger severities'));
		$this->setResponse($response);
	}
}
