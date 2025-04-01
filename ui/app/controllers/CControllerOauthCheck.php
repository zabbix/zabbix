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


class CControllerOauthCheck extends CController {

	public function init() {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'mediatypeid' =>					'id',
			'redirection_url' =>				'db media_type_oauth.redirection_url|required',
			'client_id' => 						'db media_type_oauth.client_id|required',
			'client_secret' =>					'db media_type_oauth.client_secret',
			'authorization_url' =>				'string',
			'authorization_url_parameters' =>	'array',
			'token_url' =>						'string',
			'token_url_parameters' =>			'array',
			'authorization_mode' =>				'string|in auto,manual',
			'code' =>							'string',
			'update' =>							'in 0,1',
			'advanced_form' =>					'in 0,1'
		];

		$ret = $this->validateInput($fields);

		if ($ret && $this->hasInput('client_secret') && $this->getInput('client_secret') === '') {
			$ret = false;
			error(_s('Incorrect value for field "%1$s": %2$s.', 'client_secret', _('cannot be empty')));
		}

		if ($ret && $this->getInput('advanced_form', 0)) {
			$ret = $this->validateAdvancedForm();
		}

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

	protected function validateAdvancedForm(): bool {
		$ret = true;

		if (trim($this->getInput('authorization_url', '')) === ''
				|| parse_url($this->getInput('authorization_url'), PHP_URL_SCHEME) === null) {
			$ret = false;
			error(_s('Incorrect value for field "%1$s": %2$s.', 'authorization_url', _('unacceptable URL')));
		}

		if (trim($this->getInput('token_url', '')) === ''
				|| parse_url($this->getInput('token_url'), PHP_URL_SCHEME) === null) {
			$ret = false;
			error(_s('Incorrect value for field "%1$s": %2$s.', 'token_url', _('unacceptable URL')));
		}

		if ($this->getInput('authorization_mode', 'auto') === 'manual' && trim($this->getInput('code', '')) === '') {
			$ret = false;
			error(_s('Incorrect value for field "%1$s": %2$s.', 'code', _('cannot be empty')));
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_MEDIA_TYPES);
	}

	public function doAction() {
		$oauth = [
			'redirection_url' => '',
			'client_id' => '',
			'authorization_url' => '',
			'token_url' => ''
		];
		$this->getInputs($oauth, ['redirection_url', 'client_id', 'authorization_url', 'token_url', 'client_secret',
			'mediatypeid'
		]);

		$authorization_url = new CUrl($oauth['authorization_url']);
		foreach ($this->getInput('authorization_url_parameters', []) as $parameter) {
			if (array_key_exists('name', $parameter) && array_key_exists('value', $parameter)) {
				$authorization_url->setArgument($parameter['name'], $parameter['value']);
			}
		}

		$oauth['authorization_url'] = $authorization_url->getUrl();

		$token_url = new CUrl($oauth['token_url']);
		foreach ($this->getInput('token_url_parameters', []) as $parameter) {
			if (array_key_exists('name', $parameter) && array_key_exists('value', $parameter)) {
				$token_url->setArgument($parameter['name'], $parameter['value']);
			}
		}

		$oauth['token_url'] = $token_url->getUrl();

		if ($this->getInput('authorization_mode', 'auto') === 'auto') {
			$url = $authorization_url;
			$url->setArgument('redirect_uri', $oauth['redirection_url']);
			$url->setArgument('client_id', $oauth['client_id']);
		}
		else {
			$url = new CUrl();
			$url->setArgument('action', 'oauth.authorize');
			$url->setArgument('code', $this->getInput('code'));
		}

		$url->setArgument('state', base64_encode(json_encode($oauth)));

		$data = [
			'oauth_popup_url' => $url->getUrl(),
			'oauth' => $oauth
		];

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($data)]));
	}
}
