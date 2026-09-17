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


class CControllerApmDbTest extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_DATA_SOURCE);
	}

	protected function checkInput(): bool {
		$ret = $this->validateInput(CControllerApmDbUpdate::getValidationRules());

		if (!$ret) {
			$form_errors = $this->getValidationError();
			$response = $form_errors
				? ['form_errors' => $form_errors]
				: ['error' => [
					'title' => _('Cannot test configuration'),
					'messages' => array_column(get_and_clear_messages(), 'message')
				]];

			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode($response)])
			);
		}

		return $ret;
	}

	protected function doAction(): void {
		$settings = API::Settings()->get(['output' => ['apm_global_db']]);

		$apm = $this->getInputAll();
		if ($apm['authentication_type'] == APM_GLOBAL_DB_AUTHTYPE_PASSWORD) {
			$apm = array_merge(['password' => $settings['apm_global_db']['password']], $this->getInputAll());
		}

		$output = [];

		try {
			ApmDbClickHouse::getInstance($apm)->fetch('SELECT 1')->current();

			$output['success'] = [
				'title' => _('Successfully connected to APM data source.')
			];
		} catch (Exception) {
			$output['error'] = [
				'title' => _('Could not connect to APM data source.')
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
