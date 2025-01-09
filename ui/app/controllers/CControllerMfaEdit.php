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


class CControllerMfaEdit extends CController {

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'mfaid' =>			'db mfa.mfaid',
			'type' =>			'in '.MFA_TYPE_TOTP.','.MFA_TYPE_DUO,
			'name' =>			'db mfa.name',
			'hash_function' =>	'in '.TOTP_HASH_SHA1.','.TOTP_HASH_SHA256.','.TOTP_HASH_SHA512,
			'code_length' =>	'in '.TOTP_CODE_LENGTH_6.','.TOTP_CODE_LENGTH_8,
			'api_hostname' =>	'db mfa.api_hostname',
			'clientid' =>		'db mfa.clientid',
			'client_secret' =>	'db mfa.client_secret',
			'add_mfa_method' =>	'in 0,1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData([
					'main_block' => json_encode([
						'error' => [
							'title' => _('Invalid MFA configuration'),
							'messages' => array_column(get_and_clear_messages(), 'message')
						]
					])
				]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_AUTHENTICATION);
	}

	protected function doAction(): void {
		$data = [
			'type' => MFA_TYPE_TOTP,
			'name' => '',
			'hash_function' => TOTP_HASH_SHA1,
			'code_length' => TOTP_CODE_LENGTH_6,
			'api_hostname' => '',
			'clientid' => '',
			'user' => [
				'debug_mode' => $this->getDebugMode()
			],
			'add_mfa_method' => 1
		];

		$this->getInputs($data, array_keys($data));

		if ($this->hasInput('client_secret')) {
			$data['client_secret'] = $this->getInput('client_secret');
		}

		if ($this->hasInput('mfaid')) {
			$data['mfaid'] = $this->getInput('mfaid');
		}

		$curl_status = (new CFrontendSetup())->checkPhpCurlModule();
		$data['curl_error'] = ($curl_status['result'] == CFrontendSetup::CHECK_WARNING);

		$this->setResponse(new CControllerResponseData($data));
	}
}
