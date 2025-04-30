<?php
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


/**
 * Class containing operations for updating user profile notification.
 */
class CControllerUserProfileNotificationUpdate extends CControllerUserUpdateGeneral {

	protected function checkInput(): bool {
		$fields = [
			'userid' =>				'fatal|required|db users.userid',
			'messages' =>			'array',
			'medias' =>				'array',
			'form_refresh' =>		'int32'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			switch ($this->getValidationError()) {
				case self::VALIDATION_ERROR:
					$response = (new CControllerResponseRedirect((new CUrl('zabbix.php'))
						->setArgument('action', 'userprofile.notification.edit')
					));
					$response->setFormData($this->getInputAll());
					CMessageHelper::setErrorTitle(_('Cannot update user'));
					$this->setResponse($response);
					break;

				case self::VALIDATION_FATAL_ERROR:
					$this->setResponse(new CControllerResponseFatal());
					break;
			}
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return (bool) API::User()->get([
			'output' => [],
			'userids' => $this->getInput('userid'),
			'editable' => true
		]);
	}

	protected function doAction(): void {
		$user = [
			'userid' => CWebUser::$data['userid']
		];

		if ($this->checkAccess(CRoleHelper::ACTIONS_EDIT_OWN_MEDIA)) {
			$user['medias'] = $this->getInputUserMedia();
		}

		DBstart();
		$result = updateMessageSettings($this->getInput('messages', []));
		$result = $result && (bool) API::User()->update($user);
		$result = DBend($result);

		if ($result) {
			$response = new CControllerResponseRedirect(new CUrl(CMenuHelper::getFirstUrl()));
			CMessageHelper::setSuccessTitle(_('User updated'));
		}
		else {
			$response = (new CControllerResponseRedirect((new CUrl('zabbix.php'))
				->setArgument('action', 'userprofile.notification.edit')
			));
			$response->setFormData($this->getInputAll());
			CMessageHelper::setErrorTitle(_('Cannot update user'));
		}

		$this->setResponse($response);
	}
}
