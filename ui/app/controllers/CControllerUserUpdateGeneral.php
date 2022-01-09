<?php
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
	 * Validate password directly from input when updating user.
	 *
	 * @return bool
	 */
	protected function validatePassword() {
		if ($this instanceof CControllerUserProfileUpdate) {
			$usrgrps = API::UserGroup()->get([
				'output' => ['gui_access'],
				'userids' => CWebUser::$data['userid'],
				'filter' => [
					'gui_access' => [GROUP_GUI_ACCESS_SYSTEM, GROUP_GUI_ACCESS_INTERNAL]
				]
			]);
		}
		else {
			$usrgrps = API::UserGroup()->get([
				'output' => ['gui_access'],
				'usrgrpids' => $this->getInput('user_groups'),
				'filter' => [
					'gui_access' => [GROUP_GUI_ACCESS_SYSTEM, GROUP_GUI_ACCESS_INTERNAL]
				]
			]);
		}

		$this->allow_empty_password = !self::hasInternalAuth($usrgrps);

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
		if (!$this->hasInput('roleid')) {
			error(_s('Field "%1$s" is mandatory.', 'roleid'));

			return false;
		}

		$role = API::Role()->get(['output' => [], 'roleids' => [$this->getInput('roleid')]]);

		if (!$role) {
			error(_('No permissions to referred object or it does not exist!'));

			return false;
		}

		return true;
	}
}
