<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


class CHostGroupNameValidator extends CValidator {

	/**
	 * Checks if host group name is string or at least an integer, is not empty. Check if name contains forward slashes
	 * and asterisks. Slashes cannot be first character, last or repeat in the middle multiple times. Asterisks are not
	 * allowed at all.
	 *
	 * @param mixed $name				Host group name.
	 *
	 * @return bool
	 */
	public function validate($name) {
		if (!is_string($name)) {
			$this->error(_s('Incorrect value for field "%1$s": %2$s.', 'name', _('must be a string')));

			return false;
		}

		if ($name === '') {
			$this->error(_s('Incorrect value for field "%1$s": %2$s.', 'name', _('cannot be empty')));

			return false;
		}

		if ($name[0] === '/' || substr($name, -1) === '/' || strpos($name, '//') !== false
			|| strpos($name, '*') !== false) {
			$this->error(_s('Incorrect value for field "%1$s": %2$s.', 'name', _('unacceptable characters are used')));

			return false;
		}

		return true;
	}
}
