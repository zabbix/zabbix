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


require_once dirname(__FILE__).'/../../include/regexp.inc.php';

class CControllerRegExUpdate extends CController {

	protected function checkInput() {
		$fields = [
			'name'         => 'required | string | not_empty | db regexps.name',
			'test_string'  => 'string | db regexps.test_string',
			'regexid'      => 'fatal | required | db regexps.regexpid',
			'expressions'  => 'required | array'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			switch ($this->getValidationError()) {
				case self::VALIDATION_ERROR:
					$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))
						->setArgument('action', 'regex.edit')
						->setArgument('regexid', $this->getInput('regexid'))
					);
					$response->setFormData($this->getInputAll());
					$response->setMessageError(_('Cannot update regular expression'));
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
		/** @var array $expressions */
		$expressions = $this->getInput('expressions', []);

		foreach ($expressions as &$expression) {
			if (!array_key_exists('case_sensitive', $expression)) {
				$expression['case_sensitive'] = 0;
			}
		}
		unset($expression);

		$result = updateRegexp([
			'regexpid'    => $this->getInput('regexid'),
			'name'        => $this->getInput('name'),
			'test_string' => $this->getInput('test_string')
		], $expressions);

		if ($result) {
			$url = (new CUrl('zabbix.php'))->setArgument('action', 'regex.list');

			$response = new CControllerResponseRedirect($url);
			$response->setMessageOk(_('Regular expression updated'));

			add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_REGEXP, _('Name').NAME_DELIMITER.$this->getInput('name'));
		}
		else {
			$url = (new CUrl('zabbix.php'))
				->setArgument('action', 'regex.edit')
				->setArgument('regexid', $this->getInput('regexid'));

			$response = new CControllerResponseRedirect($url);
			$response->setFormData($this->getInputAll());
			$response->setMessageError(_('Cannot update regular expression'));
		}

		$this->setResponse($response);
	}
}
