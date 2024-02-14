<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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
		$userids = $this->getInput('userids');

		$users_with_totp_secret = CUser::getUseridsWithMfaTotpSecrets($userids);

		$users_to_update = [];
		foreach ($userids as $userid) {
			if (in_array($userid, $users_with_totp_secret)) {
				$users_to_update[] = [
					'userid' => $userid,
					'mfa_totp_secrets' => []
				];
			}
		}

		$result = API::User()->update($users_to_update);
		$resetids = $result ? $result['userids'] : [];

		unset($resetids[CWebUser::$data['userid']]);

		CUser::terminateActiveSessions($resetids);

		$response = new CControllerResponseRedirect(
			(new CUrl('zabbix.php'))
				->setArgument('action', 'user.list')
				->setArgument('page', CPagerHelper::loadPage('user.list', null))
		);

		if ($resetids) {
			$users = API::User()->get([
				'output' => ['username', 'name', 'surname'],
				'userids' => $resetids
			]);

			foreach ($users as $user) {
				info(_s('TOTP secret for user "%1$s" has been reset.', getUserFullname($user)));
			}

			$response->setFormData(['uncheck' => '1']);
			CMessageHelper::setSuccessTitle(_('TOTP secret reset successful.'));
		}
		else {
			CMessageHelper::setErrorTitle(
				_n('Cannot reset TOTP secret', 'Cannot reset TOTP secrets', count($resetids))
			);
		}

		$this->setResponse($response);
	}
}
