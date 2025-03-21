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


class CControllerOauthAuthorize extends CController {

	public function init() {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'code' =>	'string|required',
			'scope' =>	'string',
			'state' =>	'string|required'
		];

		$ret = $this->validateCurl()
			&& $this->validateInput($fields)
			&& $this->validateState($this->getInput('state'));

		if (!$ret) {
			$this->setResponse((new CControllerResponseData([])));
		}

		return $ret;
	}

	protected function validateCurl(): bool {
		$curl_status = (new CFrontendSetup())->checkPhpCurlModule();
		$result = $curl_status['result'] == CFrontendSetup::CHECK_OK;

		if (!$result) {
			error($curl_status['error']);
		}

		return $result;
	}

	protected function validateState(string $state): bool {
		$state = json_decode(base64_decode($state), true);

		if (!is_array($state)) {
			error(_('Invalid request.'));

			return false;
		}

		$mandatory_all = array_flip(['client_id', 'redirection_url', 'token_url']);
		$result = !array_diff_key($mandatory_all, $state);

		$mandatory_one = array_flip(['mediaid', 'client_secret']);
		$result = $result && array_intersect_key($mandatory_one, $state);

		if ($result && array_key_exists('token_url_parameters', $state)) {
			$mandatory_all = array_flip(['name', 'value']);
			$invalid_entries = array_filter(
				$state['token_url_parameters'],
				fn ($parameter) => (bool) array_diff_key($mandatory_all, $parameter)
			);
			$result = !$invalid_entries;
		}

		if (!$result) {
			error(_('Invalid request.'));
		}

		return $result;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_MEDIA_TYPES);
	}

	public function doAction() {
		$state = json_decode(base64_decode($this->getInput('state')), true);
		$oauth = [
			'code' => $this->getInput('code'),
			'redirect_uri' => $state['redirection_url']
		];
		$oauth += array_intersect_key($state, array_flip(['client_id', 'client_secret']));

		if (!array_key_exists('client_secret', $oauth)) {
			$mediatype = API::MediaType()->get([
				'output' => ['client_secret'],
				'mediatypeids' => [$state['mediatypeid']]
			]);
			$oauth['client_secret'] = $mediatype ? $mediatype[0]['client_secret'] : '';
		}

		if (array_key_exists('token_url_parameters', $state)) {
			foreach ($state['token_url_parameters'] as $parameter) {
				$oauth += [$parameter['name'] => $parameter['value']];
			}
		}

		$this->setResponse(new CControllerResponseData([
			'tokens' => $this->exchangeCodeToTokens($state['token_url'], $oauth)
		]));
	}

	/**
	 * Get access and refresh tokens from OAuth service.
	 *
	 * @param array $oauth  OAuth data to be sent.
	 *
	 * @return array of 'access_token', 'access_expires_in' and 'refresh_token'.
	 */
	protected function exchangeCodeToTokens(string $token_url, array $data): array {
		$result = [];
		$handle = curl_init();
		curl_setopt_array($handle, [
			CURLOPT_URL => $token_url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => http_build_query($data),
			CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
			CURLOPT_TIMEOUT => 30,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2,
			// CURLOPT_SSL_VERIFYSTATUS => true // this option is very tricky to setup apache2 and php correctly
		]);

		$raw_response = curl_exec($handle);

		if (curl_errno($handle)) {
			CMessageHelper::setErrorTitle(_('CURL Error'));
			CMessageHelper::addError(curl_error($handle));
			curl_close($handle);

			return $result;
		}

		$http_code = curl_getinfo($handle, CURLINFO_HTTP_CODE);
		curl_close($handle);

		if ($http_code != 200) {
			CMessageHelper::setErrorTitle(_('Unexpected HTTP response status code.'));

			return $result + ['raw_response' => $raw_response];
		}

		$response = json_decode($raw_response, true);
		$mandatory_all = ['access_token', 'refresh_token', 'expires_in'];

		if (!is_array($response) || array_diff_key(array_flip($mandatory_all), $response)) {
			CMessageHelper::setErrorTitle(_('OAuth response missing mandatory fields.'));

			return $result + ['raw_response' => $raw_response];
		}

		return [
			'access_token' => $response['access_token'],
			'access_expires_in' => $response['expires_in'],
			'refresh_token' => $response['refresh_token']
		];
	}
}
