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


class CControllerGuiUpdate extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
	}

	public static function getValidationRules(): array {
		$themes = array_keys(APP::getThemes());
		$timezones = array_keys(CTimezoneHelper::getList());
		$timezones[] = ZBX_DEFAULT_TIMEZONE;

		return ['object', 'fields' => [
			'default_lang' => ['string', 'required', 'in' => array_keys(getLocales())],
			'default_timezone' => ['string', 'required', 'in' => $timezones],
			'default_theme' => ['string', 'required', 'in' => $themes],
			'search_limit' => ['integer', 'required', 'min' => 1, 'max' => 999999],
			'max_overview_table_size' => ['integer', 'required', 'min' => 5, 'max' => 999999],
			'max_in_table' => ['integer', 'required', 'min' => 1, 'max' => 99999],
			'server_check_interval' => ['integer', 'required', 'in' => [0, SERVER_CHECK_INTERVAL]],
			'work_period' => ['string', 'required', 'not_empty',
				'length' => CSettingsSchema::getFieldLength('work_period'),
				'use' => [CTimePeriodsParser::class, ['usermacros' => true]]
			],
			'show_technical_errors' => ['integer', 'required', 'in' => [0, 1]],
			'history_period' => ['string', 'required', 'not_empty',
				'use' => [CTimeUnitValidator::class, ['min' => SEC_PER_DAY, 'max' => 7 * SEC_PER_DAY]]
			],
			'period_default' => ['string', 'required', 'not_empty',
				'use' => [CTimeUnitValidator::class,
					['min' => SEC_PER_MIN, 'max' => 10 * SEC_PER_YEAR, 'with_year' => true]
				]
			],
			'max_period' => ['string', 'required', 'not_empty',
				'use' => [CTimeUnitValidator::class,
					['min' => SEC_PER_YEAR, 'max' => 10 * SEC_PER_YEAR, 'with_year' => true]
				]
			]
		]];
	}

	protected function checkInput() {
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

	protected function checkPermissions() {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL);
	}

	protected function doAction() {
		$result = API::Settings()->update($this->getInputAll());

		$output = [];

		if ($result) {
			$output['success'] = [
				'title' => _('Configuration updated'),
				'redirect' => (new CUrl('zabbix.php'))->setArgument('action', 'gui.edit')->getUrl()
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
