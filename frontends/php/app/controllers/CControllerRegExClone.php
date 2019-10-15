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


class CControllerRegExClone extends CController {

	protected function checkInput() {
		$fields = [
			'name'         => 'string | db regexps.name',
			'test_string'  => 'string | db regexps.test_string',
			'regexid'      => 'required | db regexps.regexpid',
			'expressions'  => 'array',
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		if ($this->getUserType() != USER_TYPE_SUPER_ADMIN) {
			return false;
		}

		$db_regex = DBfetch(DBSelect('SELECT * FROM regexps'.
			' WHERE '.dbConditionInt('regexpid', (array) $this->getInput('regexid'))
		));

		if (!$db_regex) {
			return false;
		}

		return true;
	}

	protected function doAction() {
		$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))->setArgument('action', 'regex.edit'));

		$form_data = $this->getInputAll();
		unset($form_data['regexid']);
		$form_data['form_refresh'] = 1;

		$response->setFormData($form_data);
		$this->setResponse($response);
	}
}
