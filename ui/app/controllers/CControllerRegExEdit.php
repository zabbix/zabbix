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

	protected array $db_regex = [];

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'regexid' => 'db regexps.regexpid',
			'regex' => 'array'
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
				'output' => ['regexpid', 'name', 'test_string'],
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
		$regex_default = [
			'regexpid' => '0',
			'name' => '',
			'test_string' => '',
			'expressions' => [[
				'expression_type' => EXPRESSION_TYPE_INCLUDED,
				'expression' => ''
			]]
		];

		$regex = array_replace($regex_default, $this->getInput('regex', []), $this->db_regex);

		foreach ($regex['expressions'] as &$expression) {
			$expression += ['exp_delimiter' => ',', 'case_sensitive' => 0];
		}
		unset($expression);

		$js_validation_rules = $this->hasInput('regexid')
			? CControllerRegExUpdate::getValidationRules()
			: CControllerRegExCreate::getValidationRules();

		$data = [
			'regex' => $regex,
			'js_validation_rules' => (new CFormValidator($js_validation_rules))->getRules()
		];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of regular expressions'));
		$this->setResponse($response);
	}
}
