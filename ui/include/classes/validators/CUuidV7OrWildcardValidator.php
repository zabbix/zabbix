<?php declare(strict_types = 0);
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
 * Class to validate UUIDv7 or wildcard value.
 */
class CUuidV7OrWildcardValidator extends CUuidV7Validator {

	/**
	 * @param string $value
	 *
	 * @return bool
	 */
	public function validate($value) {
		if ($value === '*') {
			return true;
		}

		if (!parent::validate($value)) {
			$this->setError(_s("a wildcard pattern '*' or a UUIDv7 is expected"));

			return false;
		}

		return true;
	}
}
