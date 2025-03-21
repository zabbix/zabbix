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


class CControllerOauthEdit extends CController {

	protected function init() {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'mediatypeid' =>		'id',
			'redirection_url' =>	'string',
			'client_id' => 			'string',
			'client_secret' =>		'string',
			'authorization_url' =>	'string',
			'token_url' =>			'string',
			'update' =>				'in 0,1',
			'advanced_form' =>		'in 0,1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData([
					'main_block' => json_encode([
						'error' => [
							'title' => _('Invalid Oauth configuration'),
							'messages' => array_column(get_and_clear_messages(), 'message')
						]
					])
				]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_MEDIA_TYPES);
	}

	/**
	 * @throws Exception
	 */
	protected function doAction(): void {
		$oauth = [
			'redirection_url' => '',
			'client_id' => '',
			'client_secret' => '',
			'authorization_url' => '',
			'token_url' => '',
			'token_status' => 0
		];
		$this->getInputs($oauth, array_keys($oauth));

		if ($this->hasInput('mediatypeid')) {
			// TODO: add tokens data from db: token_status, access_token_updated, refresh_access_after
		}

		$data = [
			'update' => $this->hasInput('mediatypeid') ? 1 : 0,
			'advanced_form' => $this->getInput('advanced_form', 0),
			'oauth' => $oauth,
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		$this->setResponse(new CControllerResponseData($data));
	}
}
