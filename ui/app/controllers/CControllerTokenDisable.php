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


class CControllerTokenDisable extends CController {

	protected function checkInput() {
		$fields = [
			'tokenids'   => 'required|array_db token.tokenid',
			'action_src' => 'required|in token.list,user.token.list'
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

		return $this->checkAccess(CRoleHelper::ACTIONS_MANAGE_API_TOKENS);
	}

	protected function doAction() {
		$tokens = [];

		foreach ($this->getInput('tokenids') as $tokenid) {
			$tokens[] = ['tokenid' => $tokenid, 'status' => ZBX_AUTH_TOKEN_DISABLED];
		}

		$result = API::Token()->update($tokens);
		$updated = count($tokens);
		$response = new CControllerResponseRedirect(
			(new CUrl('zabbix.php'))
				->setArgument('action', $this->getInput('action_src'))
				->setArgument('page', CPagerHelper::loadPage($this->getInput('action_src'), null))
		);

		if ($result) {
			$response->setFormData(['uncheck' => '1']);
			CMessageHelper::setSuccessTitle(_n('API token disabled', 'API tokens disabled', $updated));
		}
		else {
			CMessageHelper::setErrorTitle(_n('Cannot disable API token', 'Cannot disable API tokens', $updated));
		}

		$this->setResponse($response);
	}
}
