<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


class CControllerRegExList extends CController {

	protected function init() {
		$this->disableCsrfValidation();
	}

	protected function checkInput() {
		$fields = [
			'uncheck' => 'in 1'
		];

		return $this->validateInput($fields);
	}

	protected function checkPermissions() {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL);
	}

	protected function doAction() {
		$data = [
			'regexs' => API::Regexp()->get([
				'output' => ['regexpid', 'name'],
				'selectExpressions' => ['expression', 'expression_type'],
				'preservekeys' => true
			]),
			'uncheck' => $this->hasInput('uncheck')
		];

		order_result($data['regexs'], 'name');

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of regular expressions'));
		$this->setResponse($response);
	}
}
