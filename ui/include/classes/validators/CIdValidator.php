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
