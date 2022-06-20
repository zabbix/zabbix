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


class CControllerPopupTokenEdit extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'tokenid'       => 'db token.tokenid',
			'admin_mode'    => 'required|in 0,1'
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

		if ($this->getInput('admin_mode') === '1') {
			return ($this->checkAccess(CRoleHelper::ACTIONS_MANAGE_API_TOKENS)
				&& $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL)
			);
		}

		return $this->checkAccess(CRoleHelper::ACTIONS_MANAGE_API_TOKENS);
	}

	protected function doAction() {
		if ($this->hasInput('tokenid')) {
			$tokens = API::Token()->get([
				'output' => ['tokenid', 'userid', 'name', 'description', 'expires_at', 'status'],
				'tokenids' => $this->getInput('tokenid')
			]);

			if (!$tokens) {
				access_deny(ACCESS_DENY_PAGE);
			}

			$data = $tokens[0];

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
