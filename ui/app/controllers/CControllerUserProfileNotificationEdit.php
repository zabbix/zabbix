<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
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
 * Class containing operations with user profile notification edit form.
 */
class CControllerUserProfileNotificationEdit extends CControllerUserEditGeneral {

	protected function checkInput(): bool {
		return true;
	}

	protected function checkPermissions(): bool {
		if (CWebUser::isGuest() || !CWebUser::isLoggedIn()) {
			return false;
		}

		$users = API::User()->get([
			'output' => ['provisioned'],
			'selectMedias' => ['mediaid', 'mediatypeid', 'period', 'sendto', 'severity', 'active', 'provisioned'],
			'userids' => CWebUser::$data['userid'],
			'editable' => true
		]);

		if (!$users) {
			return false;
		}

		$this->user = $users[0];

		return true;
	}

	/**
	 * Set user medias if user is at least admin and set messages in data.
	 */
	protected function doAction(): void {
		$data = [
			'medias' => $this->user['medias'],
			'userid' => CWebUser::$data['userid'],
			'messages' => getMessageSettings(),
			'internal_auth' => CWebUser::$data['auth_type'] == ZBX_AUTH_INTERNAL,
			'readonly' => $this->user['provisioned'] == CUser::PROVISION_STATUS_YES,
			'can_edit_media' => $this->checkAccess(CRoleHelper::ACTIONS_EDIT_OWN_MEDIA)
		];

		$data['js_validation_rules'] = (new CFormValidator(
				CControllerUserProfileNotificationUpdate::getValidationRules($data['can_edit_media'])
			))
			->getRules();

		$data = $this->setUserMedias($data);

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Notifications'));
		$this->setResponse($response);
	}
}
