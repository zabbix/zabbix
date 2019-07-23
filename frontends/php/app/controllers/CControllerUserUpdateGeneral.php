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


/**
 * Class containing operations for updating a user.
 */
abstract class CControllerUserUpdateGeneral extends CController {

	/**
	 * User authentication type.
	 *
	 * @var int
	 */
	protected $auth_type;

	/**
	 * Validate password directly from input when updating user.
	 *
	 * @param $auth_type  User authentication type.
	 */
	protected function validatePassword($auth_type) {
		$password1 = $this->hasInput('password1') ? $this->getInput('password1') : null;
		$password2 = $this->hasInput('password2') ? $this->getInput('password2') : null;

		if ($password1 !== null && $password2 !== null) {
			if ($password1 !== $password2) {
				error(_('Both passwords must be equal.'));
				return false;
			}

			if ($password1 === '' && $auth_type == ZBX_AUTH_INTERNAL) {
				error(_s('Incorrect value for field "%1$s": %2$s.', _('Password'), _('cannot be empty')));
				return false;
			}
		}

		return true;
	}
}
