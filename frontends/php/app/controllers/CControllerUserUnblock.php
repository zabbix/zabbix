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
			'group_userid' =>	'required|array_db users.userid'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		if ($this->getUserType() != USER_TYPE_SUPER_ADMIN) {
			return false;
		}

		$users = API::User()->get([
			'countOutput' => true,
			'userids' => $this->getInput('group_userid'),
			'editable' => true
		]);

		return ($users == count($this->getInput('group_userid')));
	}

	protected function doAction() {
		$group_userid = $this->getInput('group_userid');

		DBstart();

		$result = unblock_user_login($group_userid);

		if ($result) {
			$users = API::User()->get([
				'output' => ['alias', 'name', 'surname'],
				'userids' => $group_userid

			]);

			foreach ($users as $user) {
				info('User '.$user['alias'].' unblocked');
				add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_USER,
					'Unblocked user alias ['.$user['alias'].'] name ['.$user['name'].'] surname ['.$user['surname'].']'
				);
			}
		}

		$result = DBend($result);

		$unblocked = count($group_userid);

		$response = new CControllerResponseRedirect('zabbix.php?action=user.list&uncheck=1');

		if ($result) {
			$response->setMessageOk(_n('User unblocked', 'Users unblocked', $unblocked));
		}
		else {
			$response->setMessageError(_n('Cannot unblock user', 'Cannot unblock users', $unblocked));
		}

		$this->setResponse($response);
	}
}
