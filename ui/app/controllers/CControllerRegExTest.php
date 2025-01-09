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


class CControllerRegExTest extends CController {

	protected function init() {
		$this->disableCsrfValidation();
	}

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
