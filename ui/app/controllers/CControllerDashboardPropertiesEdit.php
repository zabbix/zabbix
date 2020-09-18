<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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


class CControllerDashboardPropertiesEdit extends CControllerDashboardAbstract {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'template' => 'in 1',
			'userid' => 'db users.userid',
			'name' => 'db dashboard.name'
		];

		$ret = $this->validateInput($fields);

		if (!$this->hasInput('template') && !$this->hasInput('userid')) {
			error(_s('Field "%1$s" is mandatory.', 'userid'));

			$ret = false;
		}

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData([
					'main_block' => json_encode(['errors' => getMessages()->toString()])
				]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions() {
		return true;
	}

	protected function doAction() {
		$data = [
			'dashboard' => [
				'template' => $this->hasInput('template'),
				'name' => $this->getInput('name')
			],
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		if (!$this->hasInput('template')) {
			$userid = $this->getInput('userid');

			$data['dashboard']['owner'] = [
				'id' => $userid,
				'name' => self::getOwnerName($userid)
			];
		}

		$this->setResponse(new CControllerResponseData($data));
	}
}
