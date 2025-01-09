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


class CControllerTokenEdit extends CController {

	/**
	 * @var mixed
	 */
	private $token;

	protected function init() {
		$this->disableCsrfValidation();
	}

	protected function checkInput() {
		$fields = [
			'tokenid'		=> 'db token.tokenid',
			'admin_mode'	=> 'required|in 0,1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions() {
		if (CWebUser::isGuest()) {
			return false;
		}

		if ($this->hasInput('tokenid')) {
			$this->token = API::Token()->get([
				'output' => ['tokenid', 'userid', 'name', 'description', 'expires_at', 'status'],
				'tokenids' => $this->getInput('tokenid')
			]);

			if (!$this->token) {
				return false;
			}
		}

		if ($this->getInput('admin_mode') === '1') {
			return ($this->checkAccess(CRoleHelper::ACTIONS_MANAGE_API_TOKENS)
				&& $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_API_TOKENS)
			);
		}

		return $this->checkAccess(CRoleHelper::ACTIONS_MANAGE_API_TOKENS);
	}

	protected function doAction() {
		if ($this->hasInput('tokenid')) {
			$data = $this->token[0];

			if ($data['expires_at'] != 0) {
				$data['expires_at'] = date(ZBX_FULL_DATE_TIME, (int) $data['expires_at']);
				$data['expires_state'] = '1';
			}
			else {
				$data['expires_at'] = null;
				$data['expires_state'] = '0';
			}
		}
		else {
			$data = [
				'tokenid' => 0,
				'userid' => 0,
				'name' => '',
				'description' => '',
				'expires_at' => null,
				'expires_state' => '1',
				'status' => ZBX_AUTH_TOKEN_ENABLED
			];
		}

		$data['ms_user'] = [];
		$this->getInputs($data, ['userid', 'name', 'description', 'expires_at', 'expires_state', 'status']);

		if ($data['userid'] != 0) {
			[$user] = (CWebUser::$data['userid'] != $data['userid'])
				? API::User()->get([
					'output' => ['username', 'name', 'surname'],
					'userids' => $data['userid']
				])
				: [CWebUser::$data];

			$data['ms_user'] = [['id' => $user['userid'], 'name' => getUserFullname($user)]];
		}

		$data['admin_mode'] = $this->getInput('admin_mode');

		$data['user'] = ['debug_mode' => $this->getDebugMode()];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('API tokens'));
		$this->setResponse($response);
	}
}
