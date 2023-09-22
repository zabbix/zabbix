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


class CControllerScriptUserInputEdit extends CController {

	protected function init() {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'input_prompt' =>		'db scripts.manualinput_prompt|required|not_empty',
			'default_input' =>		'db scripts.manualinput_default_value|string',
			'input_type' =>			'db scripts.manualinput_validator_type|in '.implode(',', [SCRIPT_MANUALINPUT_TYPE_LIST, SCRIPT_MANUALINPUT_TYPE_STRING]),
			'input_validation' =>	'db scripts.manualinput_validator',
			'test' =>				'in 1',
			'confirmation' =>		'db scripts.confirmation'
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
		$data = [
			'input_prompt' => $this->getInput('input_prompt', ''),
			'default_input' => $this->getInput('default_input', ''),
			'input_validation' => $this->getInput('input_validation', ''),
			'user' => ['debug_mode' => $this->getDebugMode()],
			'test' => $this->hasInput('test'),
			'input_type' => $this->getInput('input_type'),
			'confirmation' => $this->hasInput('confirmation') && strlen($this->getInput('confirmation')) > 0
		];

		if ($data['input_type'] == SCRIPT_MANUALINPUT_TYPE_LIST) {
			$dropdown_values = explode(",", $this->getInput('input_validation'));

			foreach ($dropdown_values as $value) {
				$data['dropdown_options'][$value] = $value;
			}
		}

		$response = new CControllerResponseData($data);
		$this->setResponse($response);
	}
}
