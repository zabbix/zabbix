<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
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


class CControllerValidateUse extends CController {

	protected function init() {
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
		$this->disableCsrfValidation();
	}

	public static function getValidationRules(): array {
		return ['object', 'fields' => [
			'validations' => ['objects', 'required', 'not_empty',
				'fields' => [
					'field' => ['string', 'required'],
					'value' => ['string'],
					'class' => ['string', 'required', 'not_empty'],
					'options' => ['array'],
					'error_msg' => ['string']
				],
				'count_values' => ['field_rules' => ['field'], 'max' => 500]
			]
		]];
	}

	protected function checkInput() {
		$ret = $this->validateInput($this->getValidationRules());

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => $this->getValidationError()
					]
				])]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions() {
		return true;
	}

	protected function doAction() {
		$response = [];
		$errors = [];

		try {
			foreach ($this->getInput('validations') as $validation) {
				$error = null;
				$use_rule = [
					$validation['class'],
					array_key_exists('options', $validation) ? $validation['options'] : []
				];

				if (!CFormValidator::validateUse(['use' => $use_rule], $validation['value'], $error)) {
					$errors[] = [
						'field' => $validation['field'],
						'message' => array_key_exists('error_msg', $validation)
							? $validation['error_msg']
							: $error
					];
				}
			}

			if ($errors) {
				$response += [
					'result' => false,
					'errors' => $errors
				];
			}
			else {
				$response += ['result' => true];
			}
		}
		catch (Exception $e) {
			$response += [
				'error' => [
					'code' => $e->getCode(),
					'message' => $e->getMessage()
				]
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($response)]));
	}
}
