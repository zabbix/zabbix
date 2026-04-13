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
 * Class to validate UUIDv7.
 */
class CUuidV7Validator extends CValidator {

	private const LENGTH = 32;

	/**
	 * @param string $value
	 *
	 * @return bool
	 */
	public function validate($value) {
		if (strlen($value) != self::LENGTH) {
			$this->setError(_s('must be %1$s characters long', self::LENGTH));

			return false;
		}

		if (!ctype_xdigit($value)) {
			$this->setError(_('must contain only hexadecimal characters'));

			return false;
		}

		$binary = hex2bin($value);

		if ((ord($binary[6]) & 0xf0) != 0x70 || (ord($binary[8]) & 0xc0) != 0x80) {
			$this->setError(_('a UUIDv7 is expected'));

			return false;
		}

		return true;
	}
}
