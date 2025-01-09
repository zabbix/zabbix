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


class CLimitedSetValidator extends CValidator {

	/**
	 * Allowed values.
	 *
	 * @var array
	 */
	public $values = [];

	/**
	 * Error message if the value is invalid or is not of an acceptable type.
	 *
	 * @var string
	 */
	public $messageInvalid = null;

	/**
	 * Checks if the given value belongs to some set.
	 *
	 * @param $value
	 *
	 * @return bool
	 */
	public function validate($value) {
		if (!is_string($value) && !is_int($value)) {
			if ($this->messageInvalid !== null) {
				$this->error($this->messageInvalid, $this->stringify($value));
			}

			return false;
		}

		$values = array_flip($this->values);

		if (!isset($values[$value])) {
			if ($this->messageInvalid !== null) {
				$this->error($this->messageInvalid, $value);
			}

			return false;
		}

		return true;
	}

}
