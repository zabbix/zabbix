<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


class CControllerTokenCreate extends CController {

	protected function init() {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput() {
		$fields = [
			'name' 			=> 'db token.name|required|not_empty',
			'description'	=> 'db token.description',
			'userid' 		=> 'db users.userid|required',
			'expires_state' => 'in 0,1|required',
			'expires_at'	=> 'abs_time',
			'status' 		=> 'db token.status|required|in ' . ZBX_AUTH_TOKEN_ENABLED . ',' . ZBX_AUTH_TOKEN_DISABLED,
			'admin_mode'	=> 'required|in 0,1'
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
						'title' => _('Cannot add API token'),
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
		$this->getInputs($token, ['name', 'description', 'userid', 'expires_at', 'status']);

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

		$result = API::Token()->create($token);

		$output = [];

		if ($result) {
			['tokenids' => $tokenids] = $result;
			[['token' => $auth_token]] = API::Token()->generate($tokenids);

			[$user] = (CWebUser::$data['userid'] != $token['userid'])
				? API::User()->get([
					'output' => ['username', 'name', 'surname'],
					'userids' => $token['userid']
				])
				: [CWebUser::$data];

			$output['success']['title'] = _('API token added');

			if ($messages = get_and_clear_messages()) {
				$output['success']['messages'] = array_column($messages, 'message');
			}

			$output['data'] = [
				'name' => $token['name'],
				'user_name' => getUserFullname($user),
				'auth_token' => $auth_token,
				'expires_at' => $token['expires_at'],
				'description' => $token['description'],
				'status' => $token['status'],
				'message' => _('API token added'),
				'admin_mode' => $this->getInput('admin_mode')
			];
		}
		else {
			$output['error'] = [
				'title' => _('Cannot add API token'),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
