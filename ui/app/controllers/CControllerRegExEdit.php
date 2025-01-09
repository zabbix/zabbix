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


class CControllerRegExEdit extends CController {

	protected $db_regex = [];

	protected function init() {
		$this->disableCsrfValidation();
	}

	protected function checkInput() {
		$fields = [
			'name'         => 'db regexps.name',
			'test_string'  => 'db regexps.test_string',
			'regexid'      => 'db regexps.regexpid',
			'expressions'  => 'array',
			'form_refresh' => 'int32'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		if (!$this->checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL)) {
			return false;
		}

		if ($this->hasInput('regexid')) {
			$db_regexs = API::Regexp()->get([
				'output' => ['name', 'test_string'],
				'selectExpressions' => ['expression_type', 'expression', 'exp_delimiter', 'case_sensitive'],
				'regexpids' => [$this->getInput('regexid')]
			]);

			if (!$db_regexs) {
				return false;
			}

			$this->db_regex = $db_regexs[0];
		}

		return true;
	}

	protected function doAction() {
		$data = [
			'regexid' => $this->getInput('regexid', 0),
			'name' => $this->hasInput('regexid')
				? $this->getInput('name', $this->db_regex['name'])
				: $this->getInput('name', ''),
			'test_string'  => $this->hasInput('regexid')
				? $this->getInput('test_string', $this->db_regex['test_string'])
				: $this->getInput('test_string', ''),
			'expressions'  => [],
			'form_refresh' => $this->getInput('form_refresh', 0)
		];

		if ($data['form_refresh'] == 0) {
			if ($data['regexid'] == 0) {
				$data['expressions'] = [[
					'expression_type' => EXPRESSION_TYPE_INCLUDED,
					'expression' => '',
					'exp_delimiter' => ',',
					'case_sensitive' => 0
				]];
			}
			else {
				$data['expressions'] = $this->db_regex['expressions'];
			}
		}
		else {
			$data['expressions'] = $this->getInput('expressions', [[
				'expression_type' => EXPRESSION_TYPE_INCLUDED,
				'expression' => '',
				'exp_delimiter' => ',',
				'case_sensitive' => 0
			]]);

			foreach ($data['expressions'] as &$expression) {
				$expression += ['exp_delimiter' => ',', 'case_sensitive' => 0];
			}
			unset($expression);
		}

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of regular expressions'));
		$this->setResponse($response);
	}
}
