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


class CControllerUserResetTotp extends CController {

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
		$resetids = API::User()->resetTotp($this->getInput('userids'));

		$response = new CControllerResponseRedirect(
			(new CUrl('zabbix.php'))
				->setArgument('action', 'user.list')
				->setArgument('page', CPagerHelper::loadPage('user.list', null))
		);

		if ($resetids) {
			$users = API::User()->get([
				'output' => ['username', 'name', 'surname'],
				'userids' => $resetids['userids']
			]);

			foreach ($users as $user) {
				info(_s('TOTP secret for user "%1$s" has been reset.', getUserFullname($user)));
			}

			$response->setFormData(['uncheck' => '1']);
			CMessageHelper::setSuccessTitle(_('TOTP secret reset successful.'));
		}
		else {
			CMessageHelper::setErrorTitle(
				_n('Cannot reset TOTP secret', 'Cannot reset TOTP secrets', count($resetids['userids']))
			);
		}

		$this->setResponse($response);
	}
}
