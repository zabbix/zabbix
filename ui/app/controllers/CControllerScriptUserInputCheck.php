<?php declare(strict_types = 0);
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


class CControllerScriptUserInputCheck extends CController {

	protected function init() {
		$this->disableCsrfValidation();
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'manualinput' =>				'required|string',
			'manualinput_validator_type' =>	'db scripts.manualinput_validator_type|in '.implode(',', [ZBX_SCRIPT_MANUALINPUT_TYPE_STRING, ZBX_SCRIPT_MANUALINPUT_TYPE_LIST]),
			'manualinput_validator' =>		'db scripts.manualinput_validator|required|string',
			'test' =>						'in 1'
		];

		$ret = $this->validateInput($fields) && $this->validateManualinputFields();

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'title' => _('Invalid input'),
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				], JSON_THROW_ON_ERROR)]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return true;
	}

	protected function doAction(): void {
		$output = [];

		if ($this->hasInput('test')) {
			$output['success']['messages'] = [_('User input has been successfully tested.')];
			$output['success']['test'] = true;
		}
		else {
			$output = ['data' => ['manualinput' => $this->getInput('manualinput')]];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output, JSON_THROW_ON_ERROR)]));
	}

	private function validateManualinputFields(): bool {
		$manualinput = $this->getInput('manualinput');
		$manualinput_validator = $this->getInput('manualinput_validator');

		if ($this->getInput('manualinput_validator_type') == ZBX_SCRIPT_MANUALINPUT_TYPE_LIST) {
			$user_input_values = array_map('trim', explode(',', $manualinput_validator));
			$manualinput_validator = implode(', ', $user_input_values);

			// Check if provided manualinput value is one of dropdown values when executing the script.
			if (!in_array($manualinput, $user_input_values)) {
				error(_s('Incorrect value for field "%1$s": %2$s.', 'manualinput',
					_s('value must be one of %1$s', $manualinput_validator)
				));

				return false;
			}
		}
		else {
			$regular_expression = '/'.str_replace('/', '\/', $manualinput_validator).'/';
			$regex_validator = new CRegexValidator([
				'messageInvalid' => _('Regular expression must be a string'),
				'messageRegex' => _('Incorrect regular expression "%1$s": "%2$s"')
			]);

			if (!$regex_validator->validate($manualinput_validator)) {
				error(_s('Incorrect value for field "%1$s": %2$s.','manualinput_validator',
					$regex_validator->getError()
				));

				return false;
			}
			if ($manualinput_validator === '') {
				error(_s('Incorrect value for field "%1$s": %2$s.', 'manualinput_validator',
					_('Expression cannot be empty')
				));

				return false;
			}
			if (!preg_match($regular_expression, trim($manualinput))) {
				error(_s('Incorrect value for field "%1$s": %2$s.', 'manualinput',
					_s('input does not match the provided pattern: %1$s', $manualinput_validator)
				));

				return false;
			}
		}

		return true;
	}
}
