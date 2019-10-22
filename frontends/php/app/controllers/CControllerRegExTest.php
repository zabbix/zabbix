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


class CControllerRegExTest extends CController {

	protected function checkInput() {
		$fields = [
			'ajaxdata' => 'array'
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
		$response = new CAjaxResponse();
		$data = $this->getInput('ajaxdata', []);

		$result = [
			'expressions' => $this->getInput('expressions', []),
			'errors' => [],
			'final' => true
		];

		$validator = new CRegexValidator([
			'messageInvalid' => _('Regular expression must be a string'),
			'messageRegex' => _('Incorrect regular expression "%1$s": "%2$s"')
		]);

		foreach ($data['expressions'] as $id => $expression) {
			if (!in_array($expression['expression_type'], [EXPRESSION_TYPE_FALSE, EXPRESSION_TYPE_TRUE]) ||
				$validator->validate($expression['expression'])
			) {
				$match = CGlobalRegexp::matchExpression($expression, $data['testString']);

				$result['expressions'][$id] = $match;
			} else {
				$match = false;
				$result['errors'][$id] = $validator->getError();
			}

			$result['final'] = ($result['final'] && $match);
		}

		$response->success($result);
		$response->send();
	}
}
