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


class CControllerRegExUpdate extends CController {

	protected function init(): void {
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	public static function getValidationRules(): array {
		$api_uniq = [
			['regexp.get', ['name' => '{name}'], 'regexpid']
		];

		return ['object', 'api_uniq' => $api_uniq, 'fields' => [
			'regexpid' => ['db regexps.regexpid', 'required'],
			'name' => ['db regexps.name', 'required', 'not_empty'],
			'test_string' => ['db regexps.test_string'],
			'expressions' => ['objects', 'required', 'not_empty', 'uniq' => [['expression_type', 'expression']],
				'fields' => [
					'expression_type' => ['db expressions.expression_type', 'required',
						'in' => [EXPRESSION_TYPE_INCLUDED, EXPRESSION_TYPE_ANY_INCLUDED, EXPRESSION_TYPE_NOT_INCLUDED,
							EXPRESSION_TYPE_TRUE, EXPRESSION_TYPE_FALSE
						]
					],
					'expression' => [
						['db expressions.expression', 'required', 'not_empty', 'use' => [CRegexValidator::class, []],
							'when' => ['expression_type', 'in' => [EXPRESSION_TYPE_TRUE, EXPRESSION_TYPE_FALSE]]
						],
						['db expressions.expression', 'required', 'not_empty', 'when' => ['expression_type', 'in' => [
							EXPRESSION_TYPE_INCLUDED, EXPRESSION_TYPE_ANY_INCLUDED, EXPRESSION_TYPE_NOT_INCLUDED
						]]]
					],
					'exp_delimiter' => ['db expressions.exp_delimiter', 'in' => [',', '.', '/'], 'when' => [
						'expression_type', 'in' => [EXPRESSION_TYPE_ANY_INCLUDED]
					]],
					'case_sensitive' => ['db expressions.case_sensitive', 'in' => [0, 1]]
				],
				'messages' => [
					'uniq' => _('Expression type and expression combination is not unique.'),
					'not_empty' => _('At least one expression must be added.')
				]
			]
		]];
	}

	protected function checkInput(): bool {
		$ret = $this->validateInput(self::getValidationRules());

		if (!$ret) {
			$form_errors = $this->getValidationError();
			$response = $form_errors
				? ['form_errors' => $form_errors]
				: ['error' => [
					'title' => _('Cannot update regular expression'),
					'messages' => array_column(get_and_clear_messages(), 'message')
				]];

			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode($response)])
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL);
	}

	protected function doAction(): void {
		$expressions = $this->getInput('expressions');

		foreach ($expressions as &$expression) {
			if ($expression['expression_type'] != EXPRESSION_TYPE_ANY_INCLUDED) {
				$expression['exp_delimiter'] = '';
			}
		}
		unset($expression);

		$result = API::Regexp()->update([
			'regexpid' => $this->getInput('regexpid'),
			'name' => $this->getInput('name'),
			'test_string' => $this->getInput('test_string', ''),
			'expressions' => $expressions
		]);

		$output = [];

		if ($result) {
			$output['success']['title'] = _('Regular expression updated');

			if ($messages = get_and_clear_messages()) {
				$output['success']['messages'] = array_column($messages, 'message');
			}
		}
		else {
			$output['error'] = [
				'title' => _('Cannot update regular expression'),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
