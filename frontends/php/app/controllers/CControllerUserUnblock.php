<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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

	protected function checkInput() {
		$fields = [
			'userids' =>	'required|array_db users.userid'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() == USER_TYPE_SUPER_ADMIN);
	}

	protected function doAction() {
		$userids = $this->getInput('userids');

		DBstart();

		$users = API::User()->get([
			'output' => ['alias', 'name', 'surname'],
			'userids' => $userids,
			'editable' => true
		]);

		$result = (count($users) == count($userids) && unblock_user_login($userids));

		if ($result) {
			foreach ($users as $user) {
				info('User '.$user['alias'].' unblocked');
				add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_USER,
					'Unblocked user alias ['.$user['alias'].'] name ['.$user['name'].'] surname ['.$user['surname'].']'
				);
			}
		}

		$result = DBend($result);

		$unblocked = count($userids);

		$url = (new CUrl('zabbix.php'))
			->setArgument('action', 'user.list')
			->setArgument('uncheck', '1');

		$response = new CControllerResponseRedirect($url->getUrl());

		if ($result) {
			$response->setMessageOk(_n('User unblocked', 'Users unblocked', $unblocked));
		}
		else {
			$response->setMessageError(_n('Cannot unblock user', 'Cannot unblock users', $unblocked));
		}

		$this->setResponse($response);
	}
}
