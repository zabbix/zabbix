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


class CControllerTokenUpdate extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput() {
		$fields = [
			'tokenid'       => 'db token.tokenid|required|fatal',
			'name'          => 'db token.name|required|not_empty',
			'description'   => 'db token.description',
			'expires_state' => 'in 0,1|required',
			'expires_at'    => 'abs_time',
			'status'        => 'db token.status|required|in '.ZBX_AUTH_TOKEN_ENABLED.','.ZBX_AUTH_TOKEN_DISABLED,
			'admin_mode'    => 'required|in 0,1',
			'regenerate'    => 'in 1'
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			$fields = [];

			if ($this->getInput('expires_state') == 1) {
				$fields['expires_at'] = 'required';
			}

			if ($fields) {
				$validator = new CNewValidator($this->getInputAll(), $fields);

				foreach ($validator->getAllErrors() as $error) {
					info($error);
				}

				if ($validator->isErrorFatal() || $validator->isError()) {
					$ret = false;
				}
			}
		}

		if (!$ret) {
			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'title' => _('Cannot update API token'),
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])])
			);
		}

		return $ret;
	}

	protected function checkPermissions() {
		if (CWebUser::isGuest()) {
			return false;
		}

		return $this->checkAccess(CRoleHelper::ACTIONS_MANAGE_API_TOKENS);
	}

	/**
	 * @throws Exception
	 */
	protected function doAction() {
		$this->getInputs($token, ['tokenid', 'name', 'description', 'expires_at', 'status']);

		if ($this->getInput('expires_state')) {
			$parser = new CAbsoluteTimeParser();
			$parser->parse($token['expires_at']);

			$token['expires_at'] = $parser
				->getDateTime(true)
				->getTimestamp();
		}
		else {
			$token['expires_at'] = 0;
		}

		$result = API::Token()->update($token);

		$output = [];

		if ($result) {
			$output['success']['title'] = _('API token updated');

			if ($messages = get_and_clear_messages()) {
				$output['success']['messages'] = array_column($messages, 'message');
			}

			if ($this->hasInput('regenerate')) {
				['tokenids' => $tokenids] = $result;
				[['userid' => $userid]] = API::Token()->get([
					'output' => ['userid'],
					'tokenids' => $tokenids
				]);
				[['token' => $auth_token]] = API::Token()->generate($tokenids);

				[$user] = (CWebUser::$data['userid'] != $userid)
					? API::User()->get([
						'output' => ['username', 'name', 'surname'],
						'userids' => $userid
					])
					: [CWebUser::$data];

				$output['data'] = [
					'name' => $token['name'],
					'user_name' => getUserFullname($user),
					'auth_token' => $auth_token,
					'expires_at' => $token['expires_at'],
					'description' => $token['description'],
					'status' => $token['status'],
					'message' => _('API token updated'),
					'admin_mode' => $this->getInput('admin_mode')
				];
			}
		}
		else {
			$output['error'] = [
				'title' => _('Cannot update API token'),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
