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


class CControllerRegExUpdate extends CController {

	protected function checkInput() {
		$fields = [
			'regexid'      => 'fatal|required|db regexps.regexpid',
			'name'         => 'required|string|not_empty|db regexps.name',
			'test_string'  => 'string|db regexps.test_string',
			'expressions'  => 'required|array',
			'form_refresh' => ''
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			switch ($this->getValidationError()) {
				case self::VALIDATION_ERROR:
					$response = new CControllerResponseRedirect(
						(new CUrl('zabbix.php'))
							->setArgument('action', 'regex.edit')
							->setArgument('regexid', $this->getInput('regexid'))
					);
					$response->setFormData($this->getInputAll());
					CMessageHelper::setErrorTitle(_('Cannot update regular expression'));
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
		$expressions = $this->getInput('expressions');

		foreach ($expressions as &$expression) {
			if (!array_key_exists('case_sensitive', $expression)) {
				$expression['case_sensitive'] = 0;
			}
		}
		unset($expression);

		$result = API::Regexp()->update([
			'regexpid' => $this->getInput('regexid'),
			'name' => $this->getInput('name'),
			'test_string' => $this->getInput('test_string', ''),
			'expressions' => $expressions
		]);

		if ($result) {
			$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))->setArgument('action', 'regex.list'));
			CMessageHelper::setSuccessTitle(_('Regular expression updated'));
		}
		else {
			$response = new CControllerResponseRedirect(
				(new CUrl('zabbix.php'))
					->setArgument('action', 'regex.edit')
					->setArgument('regexid', $this->getInput('regexid'))
			);
			$response->setFormData($this->getInputAll());
			CMessageHelper::setErrorTitle(_('Cannot update regular expression'));
		}

		$this->setResponse($response);
	}
}
