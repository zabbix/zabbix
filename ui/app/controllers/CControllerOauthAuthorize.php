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
			error($curl_status['error'], true);
		}

		return $result;
	}

	protected function validateState(string $state): bool {
		$state = json_decode(base64_decode($state), true);

		if (!is_array($state)) {
			error(_('Invalid request.'), true);

			return false;
		}

		$mandatory_all = array_flip(['client_id', 'redirection_url', 'token_url', 'sign']);
		$result = !array_diff_key($mandatory_all, $state);

		$mandatory_one = array_flip(['mediatypeid', 'client_secret']);
		$result = $result && array_intersect_key($mandatory_one, $state);

		if (!$result) {
			error(_('Invalid request.'), true);

			return false;
		}

		$state_sign = $state['sign'];
		unset($state['sign']);
		$sign = CEncryptHelper::sign(json_encode($state));

		if (!CEncryptHelper::checkSign($state_sign, $sign)) {
			error(_('Invalid request.'), true);

			return false;
		}

		return true;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_MEDIA_TYPES);
	}

	public function doAction() {
		$state = json_decode(base64_decode($this->getInput('state')), true);
		$data = [
			'code' => $this->getInput('code'),
			'redirect_uri' => $state['redirection_url']
		];
		$data += array_intersect_key($state, array_flip(['client_id', 'client_secret']));

		if (!array_key_exists('client_secret', $data)) {
			$mediatype = API::MediaType()->get([
				'output' => ['client_secret'],
				'mediatypeids' => [$state['mediatypeid']]
			]);
			$data['client_secret'] = $mediatype ? $mediatype[0]['client_secret'] : '';
		}

		$token_url = $state['token_url'];
		$i = strpos($token_url, '?');

		if ($i !== false) {
			$url_arguments = [];
			parse_str(substr($token_url, $i + 1), $url_arguments);
			$data += $url_arguments;
			$token_url = substr($token_url, 0, $i);
		}

		$this->setResponse(new CControllerResponseData([
			'tokens' => $this->exchangeCodeToTokens($token_url, $data),
			'user' => ['debug_mode' => $this->getDebugMode()]
		]));
	}

	/**
	 * Get access and refresh tokens from OAuth service.
	 *
	 * @param array $oauth  OAuth data to be sent.
	 *
	 * @return array of 'tokens_status', 'access_token', 'access_expires_in' and 'refresh_token' or empty array on error.
	 */
	protected function exchangeCodeToTokens(string $token_url, array $data): array {
		$handle = curl_init();
		$curl_options = [
			CURLOPT_URL => $token_url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => http_build_query($data),
			CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
			CURLOPT_TIMEOUT => 30,
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2
		];

		if (defined('CURLOPT_PROTOCOLS_STR')) {
			$curl_options[CURLOPT_PROTOCOLS_STR] = 'https,http';
			$curl_options[CURLOPT_REDIR_PROTOCOLS_STR] = 'https,http';
		}
		else {
			$curl_options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS | CURLPROTO_HTTP;
			$curl_options[CURLOPT_REDIR_PROTOCOLS] = CURLPROTO_HTTPS | CURLPROTO_HTTP;
		}

		curl_setopt_array($handle, $curl_options);

		$raw_response = curl_exec($handle);

		if (curl_errno($handle)) {
			error(curl_error($handle), true);
			curl_close($handle);

			return [];
		}

		$http_code = curl_getinfo($handle, CURLINFO_HTTP_CODE);
		curl_close($handle);

		if ($http_code != 200) {
			error(_('Unexpected HTTP response status code.'), true);
			error(_('Response')."\n".$raw_response, true);

			return [];
		}

		$response = json_decode($raw_response, true);
		$mandatory_all = array_flip(['access_token', 'refresh_token', 'expires_in']);

		if (!is_array($response) || array_diff_key($mandatory_all, $response)) {
			error(_('OAuth response missing mandatory fields.'), true);
			error(_('Response')."\n".$raw_response, true);

			return [];
		}

		return [
			'tokens_status' => OAUTH_ACCESS_TOKEN_VALID | OAUTH_REFRESH_TOKEN_VALID,
			'access_token' => $response['access_token'],
			'access_token_updated' => time(),
			'access_expires_in' => $response['expires_in'],
			'refresh_token' => $response['refresh_token'],
			'message' => _s('Configured %1$s ago', zbx_date2age(time() - 1))
		];
	}
}
