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
 * Class containing operations for updating a user.
 */
abstract class CControllerUserUpdateGeneral extends CController {

	/**
	 * Allow empty password.
	 *
	 * @var bool
	 */
	protected $allow_empty_password;


	/**
	 * @var array
	 */
	protected $timezones;

	protected function init() {
		parent::init();

		$this->timezones = array_keys(CTimezoneHelper::getList());
		$this->timezones[] = TIMEZONE_DEFAULT;
	}

	/**
	 * Get groups gui access.
	 *
	 * @param array  $usrgrps
	 * @param string $usrgrps[]['gui_access']
	 *
	 * @return int
	 */
	private static function hasInternalAuth($usrgrps) {
		$system_gui_access =
			(CAuthenticationHelper::get(CAuthenticationHelper::AUTHENTICATION_TYPE) == ZBX_AUTH_INTERNAL)
				? GROUP_GUI_ACCESS_INTERNAL
				: GROUP_GUI_ACCESS_LDAP;

		if (!$usrgrps) {
			return $system_gui_access == GROUP_GUI_ACCESS_INTERNAL;
		}

		foreach($usrgrps as $usrgrp) {
			$gui_access = ($usrgrp['gui_access'] == GROUP_GUI_ACCESS_SYSTEM)
				? $system_gui_access
				: $usrgrp['gui_access'];

			if ($gui_access == GROUP_GUI_ACCESS_INTERNAL) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Validate current password directly from input when updating user.
	 *
	 * @return bool
	 */
	protected function validateCurrentPassword(): bool {
		$this->allow_empty_password = !self::hasInternalAuth($this->getUserGroups());

		$current_password = $this->hasInput('current_password') ? $this->getInput('current_password') : null;

		if ($current_password === '' && !$this->allow_empty_password) {
			error(_s('Incorrect value for field "%1$s": %2$s.', _('Current password'), _('cannot be empty')));
			return false;
		}

		return true;
	}

	/**
	 * Validate password directly from input when updating user.
	 *
	 * @return bool
	 */
	protected function validatePassword(): bool {
		$this->allow_empty_password = !self::hasInternalAuth($this->getUserGroups());

		$password1 = $this->hasInput('password1') ? $this->getInput('password1') : null;
		$password2 = $this->hasInput('password2') ? $this->getInput('password2') : null;

		if ($password1 !== null && $password2 !== null) {
			if ($password1 !== $password2) {
				error(_('Both passwords must be equal.'));
				return false;
			}

			if ($password1 === '' && !$this->allow_empty_password) {
				error(_s('Incorrect value for field "%1$s": %2$s.', _('Password'), _('cannot be empty')));
				return false;
			}
		}

		return true;
	}

	/**
	 * Validate user role from user input.
	 *
	 * @return bool
	 */
	protected function validateUserRole(): bool {
		if ($this->hasInput('roleid')) {
			$role = API::Role()->get(['output' => [], 'roleids' => [$this->getInput('roleid')]]);

			if (!$role) {
				error(_('No permissions to referred object or it does not exist!'));

				return false;
			}
		}
		else {
			[$db_user] = API::User()->get([
				'output' => [],
				'selectRole' => ['roleid'],
				'userids' => $this->getInput('userid')
			]);

			if ($db_user['role']) {
				error(_s('Field "%1$s" is mandatory.', 'roleid'));

				return false;
			}
		}

		return true;
	}

	/**
	 * Get user medias data from form input.
	 *
	 * @return array of user medias sent by form.
	 */
	protected function getInputUserMedia(): array {
		$medias = [];
		$media_fields = array_flip(['mediaid', 'mediatypeid', 'sendto', 'active', 'severity', 'period']);

		foreach ($this->getInput('medias', []) as $media) {
			$medias[] = array_intersect_key($media, $media_fields);
		}

		return $medias;
	}

	/**
	 * Get user groups.
	 *
	 * @return array of usergroupids.
	 */
	protected function getUserGroups(): array {
		$usrgrps = [];

		if ($this instanceof CControllerUserProfileUpdate) {
			$usrgrps = API::UserGroup()->get([
				'output' => ['gui_access'],
				'userids' => CWebUser::$data['userid']
			]);
		}
		elseif ($this->getInput('user_groups', [])) {
			$usrgrps = API::UserGroup()->get([
				'output' => ['gui_access'],
				'usrgrpids' => $this->getInput('user_groups')
			]);
		}

		return $usrgrps;
	}
}
