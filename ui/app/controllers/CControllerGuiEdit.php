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


class CControllerGuiEdit extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'default_theme'           => 'db config.default_theme',
			'search_limit'            => 'db config.search_limit',
			'max_in_table'            => 'db config.max_in_table',
			'server_check_interval'   => 'db config.server_check_interval'
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
			'default_theme'           => $this->getInput('default_theme',           $config['default_theme']),
			'search_limit'            => $this->getInput('search_limit',            $config['search_limit']),
			'max_in_table'            => $this->getInput('max_in_table',            $config['max_in_table']),
			'server_check_interval'   => $this->getInput('server_check_interval',   $config['server_check_interval'])
		];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of GUI'));
		$this->setResponse($response);
	}
}
