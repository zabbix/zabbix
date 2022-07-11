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


class CControllerTokenView extends CController {

	protected function checkInput() {
		$fields = [
			'name'        => 'required',
			'user'        => 'required',
			'auth_token'  => 'required',
			'description' => 'required',
			'expires_at'  => 'required',
			'status'      => 'db token.status|in '.ZBX_AUTH_TOKEN_ENABLED.','.ZBX_AUTH_TOKEN_DISABLED.'|required'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		if (CWebUser::isGuest()) {
			return false;
		}

		return ($this->checkAccess(CRoleHelper::ACTIONS_MANAGE_API_TOKENS)
			&& $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL)
		);
	}

	protected function doAction() {
		$data = $this->getInputAll();
		$response = new CControllerResponseData($data);
		$response->setTitle(_('API tokens'));
		$this->setResponse($response);
	}
}
