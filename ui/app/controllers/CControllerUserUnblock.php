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


class CControllerUserUnblock extends CController {

	protected function checkInput(): bool {
		$fields = [
			'userids' =>	'required|array_db users.userid'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_USERS);
	}

	protected function doAction(): void {
		$userids = $this->getInput('userids');

		$result = API::User()->unblock($userids);
		$unblocked = count($userids);

		$response = new CControllerResponseRedirect(
			(new CUrl('zabbix.php'))
				->setArgument('action', 'user.list')
				->setArgument('page', CPagerHelper::loadPage('user.list', null))
		);

		if ($result) {
			$users = API::User()->get([
				'output' => ['username', 'name', 'surname'],
				'userids' => $userids
			]);

			foreach ($users as $user) {
				info(_s('User "%1$s" unblocked.', getUserFullname($user)));
			}

			$response->setFormData(['uncheck' => '1']);
			CMessageHelper::setSuccessTitle(_n('User unblocked', 'Users unblocked', $unblocked));
		}
		else {
			CMessageHelper::setErrorTitle(_n('Cannot unblock user', 'Cannot unblock users', $unblocked));
		}

		$this->setResponse($response);
	}
}
