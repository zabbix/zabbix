<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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


class CIdValidator extends CValidator {

	/**
	 * Value to determine whether to allow empty values
	 *
	 * @var bool
	 */
	public $empty = false;

	/**
	 * Error message if the id has wrong type or id is out of range or invalid
	 *
	 * @var string
	 */
	public $messageInvalid;

	/**
	 * Error message if the id is empty
	 *
	 * @var string
	 */
	public $messageEmpty;

	/**
	 * Validates ID value
	 *
	 * @param string $value
	 *
	 * @return bool
	 */
	public function validate($value) {
		if (!is_string($value) && !is_int($value)) {
			$this->error($this->messageInvalid, $this->stringify($value));

			return false;
		}

		if (!$this->empty && (string) $value === '0') {
			$this->error($this->messageEmpty);

			return false;
		}

		$regex = $this->empty ? '/^(0|(?!0)[0-9]+)$/' : '/^(?!0)\d+$/';

		if (!preg_match($regex, $value) ||
			bccomp($value, 0)  == -1 ||
			bccomp($value, ZBX_DB_MAX_ID) == 1
		) {
			$this->error($this->messageInvalid, $value);

			return false;
		}

		return true;
	}
}
