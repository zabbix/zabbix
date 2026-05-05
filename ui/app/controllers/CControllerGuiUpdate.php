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


class CControllerGuiUpdate extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
	}

	public static function getValidationRules(): array {
		$timezones = array_keys(CTimezoneHelper::getList());
		$timezones[] = ZBX_DEFAULT_TIMEZONE;

		return ['object', 'fields' => [
			'default_lang' => ['setting default_lang', 'in' => array_keys(getLocales())],
			'default_timezone' => ['setting default_timezone', 'required', 'in' => $timezones],
			'default_theme' => ['setting default_theme', 'required', 'in' => array_keys(APP::getThemes())],
			'search_limit' => ['setting search_limit', 'required', 'min' => 1, 'max' => 999999],
			'max_overview_table_size' => ['setting max_overview_table_size', 'required', 'min' => 5, 'max' => 999999],
			'max_in_table' => ['setting max_in_table', 'required', 'min' => 1, 'max' => 99999],
			'server_check_interval' => ['setting server_check_interval', 'required',
				'in' => [0, SERVER_CHECK_INTERVAL]
			],
			'work_period' => ['setting work_period', 'required', 'not_empty',
				'use' => [CTimePeriodsParser::class, ['usermacros' => true]],
				'messages' => ['use' => _('Invalid time period.')]
			],
			'show_technical_errors' => ['boolean', 'required'],
			'history_period' => ['setting history_period', 'required', 'not_empty',
				'use' => [CTimeUnitValidator::class, ['min' => SEC_PER_DAY, 'max' => 7 * SEC_PER_DAY]]
			],
			'period_default' => ['setting period_default', 'required', 'not_empty',
				'use' => [CTimeUnitValidator::class,
					['min' => SEC_PER_MIN, 'max' => 10 * SEC_PER_YEAR, 'with_year' => true]
				]
			],
			'max_period' => ['setting max_period', 'required', 'not_empty',
				'use' => [CTimeUnitValidator::class,
					['min' => SEC_PER_YEAR, 'max' => 10 * SEC_PER_YEAR, 'with_year' => true]
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
