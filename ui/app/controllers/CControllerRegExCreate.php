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


class CControllerRegExCreate extends CController {

	protected function checkInput() {
		$fields = [
			'name'         => 'required|string|not_empty|db regexps.name',
			'test_string'  => 'string|db regexps.test_string',
			'expressions'  => 'required|array',
			'form_refresh' => ''
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			switch ($this->getValidationError()) {
				case self::VALIDATION_ERROR:
					$url = (new CUrl('zabbix.php'))->setArgument('action', 'regex.edit');

					$response = new CControllerResponseRedirect($url);
					$response->setFormData($this->getInputAll());
					CMessageHelper::setErrorTitle(_('Cannot add regular expression'));
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
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL);
	}

	protected function doAction() {
		$result = API::Regexp()->create([
			'name' => $this->getInput('name'),
			'test_string' => $this->getInput('test_string', ''),
			'expressions' => $this->getInput('expressions')
		]);

		if ($result) {
			$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))->setArgument('action', 'regex.list'));
			CMessageHelper::setSuccessTitle(_('Regular expression added'));
		}
		else {
			$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))->setArgument('action', 'regex.edit'));
			$response->setFormData($this->getInputAll());
			CMessageHelper::setErrorTitle(_('Cannot add regular expression'));
		}

		$this->setResponse($response);
	}
}
