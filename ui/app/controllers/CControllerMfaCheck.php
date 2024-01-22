<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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


class CControllerMfaCheck extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
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
			'client_secret' => ''
		];

		$this->getInputs($data, array_keys($data));

		if ($this->hasInput('mfaid')) {
			$data['mfaid'] = $this->getInput('mfaid');
		}

		$data['type_name'] = ($data['type'] == MFA_TYPE_TOTP) ? _('TOTP') : _('DUO Universal Prompt');

		switch ($data['type']) {
			case MFA_TYPE_TOTP:
				unset($data['api_hostname'], $data['clientid'], $data['client_secret']);
				break;

			case MFA_TYPE_DUO:
				unset($data['hash_function'], $data['code_length']);
				break;
		}

		if ($this->getDebugMode() == GROUP_DEBUG_MODE_ENABLED) {
			CProfiler::getInstance()->stop();
			$data['debug'] = CProfiler::getInstance()->make()->toString();
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($data)]));
	}
}
