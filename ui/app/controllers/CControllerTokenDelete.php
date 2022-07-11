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


class CControllerTokenDelete extends CController {

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
		$tokenids = $this->getInput('tokenids');

		$result = API::Token()->delete($tokenids);

		$deleted = count($tokenids);

		$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))
			->setArgument('action', $this->getInput('action_src'))
			->setArgument('page', CPagerHelper::loadPage($this->getInput('action_src'), null))
		);

		if ($result) {
			$response->setFormData(['uncheck' => '1']);
			CMessageHelper::setSuccessTitle(_n('API token deleted', 'API tokens deleted', $deleted));
		}
		else {
			CMessageHelper::setErrorTitle(_n('Cannot delete API token', 'Cannot delete API tokens', $deleted));
		}

		$this->setResponse($response);
	}
}
