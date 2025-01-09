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


class CControllerUserProvision extends CController {

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

		$result = API::User()->provision($userids);
		$provisionedids = $result ? $result['userids'] : [];

		$response = new CControllerResponseRedirect(
			(new CUrl('zabbix.php'))
				->setArgument('action', 'user.list')
				->setArgument('page', CPagerHelper::loadPage('user.list', null))
		);

		if ($provisionedids) {
			$users = API::User()->get([
				'output' => ['username', 'name', 'surname'],
				'userids' => $provisionedids
			]);

			foreach ($users as $user) {
				info(_s('User "%1$s" provisioned.', getUserFullname($user)));
			}

			$response->setFormData(['uncheck' => '1']);
			CMessageHelper::setSuccessTitle(_('Provisioning successful.'));
		}
		else {
			CMessageHelper::setErrorTitle(
				_n('Cannot provision user', 'Cannot provision users', count($provisionedids))
			);
		}

		$this->setResponse($response);
	}
}
