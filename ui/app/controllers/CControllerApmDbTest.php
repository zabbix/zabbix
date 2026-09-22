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
		$apm = $this->getInputAll() + [
			'username' => '',
			'password' => '',
			'ssl_verify_peer' => APM_GLOBAL_DB_VERIFY_PEER_DISABLED,
			'ssl_verify_host' => APM_GLOBAL_DB_VERIFY_HOST_DISABLED,
			'ssl_cert_file' => '',
			'ssl_key_file' => '',
			'ssl_key_password' => '',
			'ssl_ca_file' => '',
			'ssl_ca_location' => ''
		];

		$output = [];

		try {
			if (!array_key_exists('password', $apm)) {
				$apm_global_db = CSettingsHelper::getApmGlobalDb();

				$apm['password'] = $apm_global_db['password'];
			}

			ApmDbClickHouse::getInstance($apm)->fetch('SELECT 1')->current();

			$output['success'] = [
				'title' => _('Successfully connected to APM data source.')
			];
		} catch (Exception $e) {
			$output['error'] = [
				'title' => _('Could not connect to APM data source.'),
				'messages' => [$e->getMessage()]
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
