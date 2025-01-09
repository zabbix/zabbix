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


class CControllerPopupTokenView extends CController {

	protected function init() {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
		$this->disableCsrfValidation();
	}

	protected function checkInput() {
		$fields = [
			'name'        => 'required',
			'user_name'   => 'required',
			'auth_token'  => 'required',
			'description' => 'required',
			'expires_at'  => 'required',
			'status'      => 'db token.status|in '.ZBX_AUTH_TOKEN_ENABLED.','.ZBX_AUTH_TOKEN_DISABLED.'|required',
			'message'     => 'required',
			'admin_mode'  => 'required|in 0,1'
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

		if ($this->getInput('admin_mode') === '0') {
			return $this->checkAccess(CRoleHelper::ACTIONS_MANAGE_API_TOKENS);
		}

		return ($this->checkAccess(CRoleHelper::ACTIONS_MANAGE_API_TOKENS)
			&& $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_API_TOKENS)
		);
	}

	protected function doAction() {
		$data = $this->getInputAll();
		$data['user'] = ['debug_mode' => $this->getDebugMode()];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('API tokens'));
		$this->setResponse($response);
	}
}
