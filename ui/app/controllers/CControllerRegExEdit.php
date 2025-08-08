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

	protected array $db_regexp = [];

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'regexpid' =>	'db regexps.regexpid',
			'regexp' =>		'array'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		if (!$this->checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL)) {
			return false;
		}

		if ($this->hasInput('regexpid')) {
			$db_regexps = API::Regexp()->get([
				'output' => ['regexpid', 'name', 'test_string'],
				'selectExpressions' => ['expression_type', 'expression', 'exp_delimiter', 'case_sensitive'],
				'regexpids' => $this->getInput('regexpid')
			]);

			if (!$db_regexps) {
				return false;
			}

			$this->db_regexp = $db_regexps[0];
		}

		return true;
	}

	protected function doAction(): void {
		$regexp_default = [
			'regexpid' => DB::getDefault('regexps', 'regexpid'),
			'name' => DB::getDefault('regexps', 'name'),
			'test_string' => DB::getDefault('regexps', 'test_string'),
			'expressions' => [[
				'expression_type' => DB::getDefault('expressions', 'expression_type'),
				'expression' => DB::getDefault('expressions', 'expression'),
				'exp_delimiter' => ',',
				'case_sensitive' => DB::getDefault('expressions', 'case_sensitive')
			]]
		];

		$regexp = array_replace($regexp_default, $this->getInput('regexp', []), $this->db_regexp);
		$js_validation_rules = $this->hasInput('regexpid')
			? CControllerRegExUpdate::getValidationRules()
			: CControllerRegExCreate::getValidationRules();

		$data = [
			'regexp' => $regexp,
			'js_validation_rules' => (new CFormValidator($js_validation_rules))->getRules()
		];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of regular expressions'));
		$this->setResponse($response);
	}
}
