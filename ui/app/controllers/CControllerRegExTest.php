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

	protected function init(): void {
		$this->disableCsrfValidation();
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'expressions' => 'array',
			'test_string' => 'string'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL);
	}

	protected function doAction(): void {
		$result = [
			'expressions' => $this->getInput('expressions', []),
			'errors' => [],
			'final' => true
		];

		foreach ($this->getInput('expressions', []) as $id => $expression) {
			try {
				self::validateRegex($expression);
				$is_match = CGlobalRegexp::matchExpression($expression, $this->getInput('test_string', ''));
				$result['expressions'][$id] = $is_match;
				$result['final'] = $result['final'] && $result['expressions'][$id];
			}
			catch (Exception $e) {
				$result['expressions'][$id] = null;
				$result['errors'][$id] = $e->getMessage();
				$result['final'] = false;
			}
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($result)]));
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
