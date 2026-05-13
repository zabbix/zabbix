<?php
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


class CControllerTrigDisplayUpdate extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
	}

	public static function getValidationRules(): array {
		return ['object', 'fields' => [
			'custom_color' => ['integer', 'required',
				'in' => [EVENT_CUSTOM_COLOR_DISABLED, EVENT_CUSTOM_COLOR_ENABLED]
			],
			'problem_unack_color' => ['setting problem_unack_color', 'required', 'rgb',
				'when' => ['custom_color', 'in' => [EVENT_CUSTOM_COLOR_ENABLED]]
			],
			'problem_ack_color' => ['setting problem_ack_color', 'required', 'rgb',
				'when' => ['custom_color', 'in' => [EVENT_CUSTOM_COLOR_ENABLED]]
			],
			'ok_unack_color' => ['setting ok_unack_color', 'required', 'rgb',
				'when' => ['custom_color', 'in' => [EVENT_CUSTOM_COLOR_ENABLED]]
			],
			'ok_ack_color' => ['setting ok_ack_color', 'required', 'rgb',
				'when' => ['custom_color', 'in' => [EVENT_CUSTOM_COLOR_ENABLED]]
			],
			'problem_unack_style' => ['boolean', 'required'],
			'problem_ack_style' => ['boolean', 'required'],
			'ok_unack_style' => ['boolean', 'required'],
			'ok_ack_style' => ['boolean', 'required'],
			'ok_period' => ['setting ok_period', 'required', 'not_empty',
				'use' => [CTimeUnitValidator::class, ['min' => 0, 'max' => SEC_PER_DAY]]
			],
			'blink_period' => ['setting blink_period', 'required', 'not_empty',
				'use' => [CTimeUnitValidator::class, ['min' => 0, 'max' => SEC_PER_DAY]]
			],
			'severity_name_0' => ['setting severity_name_0', 'required', 'not_empty'],
			'severity_color_0' => ['setting severity_color_0', 'required', 'rgb'],
			'severity_name_1' => ['setting severity_name_1', 'required', 'not_empty'],
			'severity_color_1' => ['setting severity_color_1', 'required', 'rgb'],
			'severity_name_2' => ['setting severity_name_2', 'required', 'not_empty'],
			'severity_color_2' => ['setting severity_color_2', 'required', 'rgb'],
			'severity_name_3' => ['setting severity_name_3', 'required', 'not_empty'],
			'severity_color_3' => ['setting severity_color_3', 'required', 'rgb'],
			'severity_name_4' => ['setting severity_name_4', 'required', 'not_empty'],
			'severity_color_4' => ['setting severity_color_4', 'required', 'rgb'],
			'severity_name_5' => ['setting severity_name_5', 'required', 'not_empty'],
			'severity_color_5' => ['setting severity_color_5', 'required', 'rgb']
		]];
	}

	protected function checkInput(): bool {
		$ret = $this->validateInput(self::getValidationRules());

		if (!$ret) {
			$form_errors = $this->getValidationError();
			$response = $form_errors
				? ['form_errors' => $form_errors]
				: ['error' => [
					'title' => _('Cannot update configuration'),
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
		$result = API::Settings()->update($this->getInputAll());

		$output = [];

		if ($result) {
			$output['success'] = [
				'title' => _('Configuration updated')
			];
		}
		else {
			$output['error'] = [
				'title' => _('Cannot update configuration'),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
