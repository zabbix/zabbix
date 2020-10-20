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

class CControllerRegExList extends CController {

	protected function init() {
		$this->disableSIDValidation();
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
			'regexes'  => [],
			'db_exps'  => [],
			'uncheck'  => $this->hasInput('uncheck')
		];

		$db_regex = DBselect('SELECT re.* FROM regexps re');

		while ($regex = DBfetch($db_regex)) {
			$regex['expressions'] = [];
			$data['regexes'][$regex['regexpid']] = $regex;
		}

		order_result($data['regexes'], 'name');

		$db_expressions = DBselect(
			'SELECT e.*'.
			' FROM expressions e'.
			' WHERE '.dbConditionInt('e.regexpid', array_keys($data['regexes'])).
			' ORDER BY e.expression_type'
		);

		while ($expr = DBfetch($db_expressions)) {
			$data['db_exps'][] = [
				'regexid' => $expr['regexpid'],
				'expression' => $expr['expression'],
				'expression_type' => $expr['expression_type']
			];
		}

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of regular expressions'));
		$this->setResponse($response);
	}
}
