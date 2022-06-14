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
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL);
	}

	protected function doAction() {
		$response = new CAjaxResponse();
		$data = $this->getInput('ajaxdata', []);

		$result = [
			'expressions' => $this->getInput('expressions', []),
			'errors' => [],
			'final' => true
		];

		if (array_key_exists('expressions', $data)) {
			foreach ($data['expressions'] as $id => $expression) {
				try {
					self::validateRegex($expression);
					$result['expressions'][$id] = CGlobalRegexp::matchExpression($expression, $data['testString']);
					$result['final'] = $result['final'] && $result['expressions'][$id];
				}
				catch (Exception $e) {
					$result['errors'][$id] = $e->getMessage();
					$result['final'] = false;
				}
			}
		}

		$response->success($result);
		$response->send();
	}

	private static function validateRegex(array $expression): void {
		$validator = new CRegexValidator([
			'messageInvalid' => _('Regular expression must be a string'),
			'messageRegex' => _('Incorrect regular expression "%1$s": "%2$s"')
		]);

		switch ($expression['expression_type']) {
			case EXPRESSION_TYPE_TRUE:
			case EXPRESSION_TYPE_FALSE:
				if (!$validator->validate($expression['expression'])) {
					throw new Exception($validator->getError());
				}
				break;

			default:
				if ($expression['expression'] === '') {
					throw new Exception(_('Expression cannot be empty'));
				}
		}
	}
}
