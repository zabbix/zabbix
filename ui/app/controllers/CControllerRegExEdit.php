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

require_once dirname(__FILE__).'/../../include/regexp.inc.php';

class CControllerRegExEdit extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'name'         => 'db regexps.name',
			'test_string'  => 'db regexps.test_string',
			'regexid'      => 'db regexps.regexpid',
			'expressions'  => 'array',
			'form_refresh' => ''
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
			$db_regex = DBfetch(DBSelect('SELECT * FROM regexps'.
				' WHERE '.dbConditionInt('regexpid', (array) $this->getInput('regexid'))
			));

			if (!$db_regex) {
				return false;
			}

			$this->regex = [
				'name' => $this->getInput('name', $db_regex['name']),
				'test_string' => $this->getInput('test_string', $db_regex['test_string']),
				'regexid' => $this->getInput('regexid', $db_regex['regexpid'])
			];
		}
		else {
			$this->regex = [
				'name' => $this->getInput('name', ''),
				'test_string' =>  $this->getInput('test_string', ''),
				'regexid' => 0
			];
		}

		return true;
	}

	protected function doAction() {
		$data = [
			'regexid'      => $this->regex['regexid'],
			'expressions'  => [],
			'name'         => $this->regex['name'],
			'test_string'  => $this->regex['test_string'],
			'form_refresh' => $this->getInput('form_refresh', 0)
		];

		if ($data['form_refresh'] == 0) {
			if ($this->regex['regexid'] == 0) {
				$data['expressions'][] = [
					'expression' => '',
					'expression_type' => EXPRESSION_TYPE_INCLUDED,
					'exp_delimiter' => ',',
					'case_sensitive' => 0
				];
			}
			else {
				$data['expressions'] = DBfetchArray(DBselect(
					'SELECT e.expressionid,e.expression,e.expression_type,e.exp_delimiter,e.case_sensitive'.
					' FROM expressions e'.
					' WHERE '.dbConditionInt('e.regexpid', (array) $this->regex['regexid']).
					' ORDER BY e.expression_type'
				));
			}
		}
		else {
			$data['expressions'] = $this->getInput('expressions', [[
				'expression' => '',
				'expression_type' => EXPRESSION_TYPE_INCLUDED,
				'exp_delimiter' => ',',
				'case_sensitive' => 0
			]]);

			foreach ($data['expressions'] as &$expression) {
				if (!array_key_exists('case_sensitive', $expression)) {
					$expression['case_sensitive'] = 0;
				}
			}
			unset($expression);
		}

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of regular expressions'));
		$this->setResponse($response);
	}
}
