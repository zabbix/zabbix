<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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


class CControllerScriptUserInputCheck extends CController {

	protected function init() {
		$this->disableCsrfValidation();
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'manual_input' =>		'required|string',
			'default_input' =>		'db scripts.manualinput_default_value|string',
			'input_type' =>			'db scripts.manualinput_validator_type|in '.implode(',', [SCRIPT_MANUALINPUT_TYPE_LIST, SCRIPT_MANUALINPUT_TYPE_STRING]),
			'input_validation' =>	'db scripts.manualinput_validator',
			'test' =>				'in 1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_SCRIPTS);
	}

	protected function doAction(): void {
		$input_type = $this->getInput('input_type');
		$manual_input = $this->getInput('manual_input');
		$output = [];
		$result = false;
		$test = $this->hasInput('test');

		if ($test) {
			if ($input_type == SCRIPT_MANUALINPUT_TYPE_LIST) {
				$dropdown_values = explode(",", $this->getInput('input_validation'));

				if (in_array($manual_input, $dropdown_values)) {
					$result = true;
				}
				else {
					error(
						_s('Incorrect value for field "%1$s": %2$s.', 'manual_input',
							_s('value must be one of: %1$s', implode(', ', $dropdown_values))
						)
					);
				}
			}
			else {
				$validate_user_input = $this->getInput('input_validation');

				if (preg_match('/'.str_replace('/', '\/', $validate_user_input).'/', $manual_input) == false){
					error(
						_s('Incorrect value for field "%1$s": %2$s.', 'manual_input',
							_s('input does not match the provided pattern: %1$s', $validate_user_input)
						)
					);
				}
				else {
					$result = true;
				}
			}

			if ($result) {
				$output['success']['messages'] = ['User input has been successfully tested.'];
			}
			else {
				if ($messages = get_and_clear_messages()) {
					$output['error']['messages'] = array_column($messages, 'message');
				}
			}
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output, JSON_THROW_ON_ERROR)]));
	}
}
