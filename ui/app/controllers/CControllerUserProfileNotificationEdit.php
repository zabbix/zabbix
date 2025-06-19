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
 * Class containing operations with user profile notification edit form.
 */
class CControllerUserProfileNotificationEdit extends CControllerUserEditGeneral {

	protected function checkInput(): bool {
		$fields = [
			'messages' =>		'array',
			'form_refresh' =>	'int32'
		];

		if (CWebUser::$data['type'] > USER_TYPE_ZABBIX_USER) {
			$fields += [
				'medias' =>			'array'
			];
		}

		$ret = $this->validateInput($fields) && $this->validateMedias();

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function validateMedias(): bool {
		$validation_rules = [
			'mediaid' =>		'id',
			'mediatypeid' =>	'required|db media_type.mediatypeid',
			'sendto' =>			'required',
			'period' =>			'required|time_periods',
			'active' =>			'in '.implode(',', [MEDIA_STATUS_ACTIVE, MEDIA_STATUS_DISABLED]),
			'severity' =>		'int32|ge 0|le '.(pow(2, TRIGGER_SEVERITY_COUNT) - 1)
		];

		foreach ($this->getInput('medias', []) as $media) {
			$validator = new CNewValidator($media, $validation_rules);

			if ($validator->isError()) {
				return false;
			}
		}

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
			'form_refresh' => 0
		];

		// Overwrite with input variables.
		$this->getInputs($data, array_keys($data));

		$data['medias'] = $data['form_refresh'] != 0
			? $this->getInput('medias', [])
			: $this->user['medias'];

		$data = $this->setUserMedias($data);

		$data += [
			'userid' => CWebUser::$data['userid'],
			'messages' => $this->getInput('messages', []) + getMessageSettings(),
			'action' => $this->getAction(),
			'internal_auth' => CWebUser::$data['auth_type'] == ZBX_AUTH_INTERNAL,
			'readonly' => $this->user['provisioned'] == CUser::PROVISION_STATUS_YES
		];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Notifications'));
		$this->setResponse($response);
	}
}
