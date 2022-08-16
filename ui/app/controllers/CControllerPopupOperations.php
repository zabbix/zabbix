<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


/**
 * Actions new condition popup.
 */
class CControllerPopupOperations extends CController {

	protected function checkInput()
	{
		// TODO: Implement checkInput() method.
		return true;
	}

	protected function checkPermissions()
	{
		// TODO: Implement checkPermissions() method.
		return true;
	}

	protected function doAction()
	{
		$data = [
			'eventsource' => '0',
			'actionid' => $this->getInput('actionid', ''),
			'action' => [
				'name' => '',
				'esc_period' => '',
				'eventsource' => '0',
				'status' =>'',
				'operations' => [],
				'filter' => [
					'conditions' => []
				]
			]
		];

		$response = new CControllerResponseData($data);
		$this->setResponse($response);
	}

}
