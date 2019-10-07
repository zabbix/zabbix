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
class CControllerRegExList extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'demo' => ''
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() == USER_TYPE_SUPER_ADMIN);
	}

	protected function doAction() {
		$data = [
			'regexps' => [],
			'regexpids' => []
		];

		$dbRegExp = DBselect('SELECT re.* FROM regexps re');

		while ($regExp = DBfetch($dbRegExp)) {
			$regExp['expressions'] = [];

			$data['regexps'][$regExp['regexpid']] = $regExp;
			$data['regexpids'][$regExp['regexpid']] = $regExp['regexpid'];
		}

		order_result($data['regexps'], 'name');

		$data['db_exps'] = DBfetchArray(DBselect(
			'SELECT e.*'.
			' FROM expressions e'.
			' WHERE '.dbConditionInt('e.regexpid', $data['regexpids']).
			' ORDER BY e.expression_type'
		));

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of regular expressions'));
		$this->setResponse($response);
	}
}
