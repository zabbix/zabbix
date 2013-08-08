<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
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


class CStringValidator extends CValidator {

	/**
	 * If set to false, the string cannot be empty.
	 *
	 * @var bool
	 */
	public $empty = false;

	/**
	 * Maximum string length.
	 *
	 * @var int
	 */
	public $maxLength;

	/**
	 * Regex to match the string against.
	 *
	 * @var string
	 */
	public $regex;

	/**
	 * Error message if the string is empty.
	 *
	 * @var string
	 */
	public $messageEmpty;

	/**
	 * Error message if the string is too long.
	 *
	 * @var string
	 */
	public $messageMaxLength;

	/**
	 * Error message if the string doesn't match the regex.
	 *
	 * @var string
	 */
	public $messageRegex;


	/**
	 * Checks if the given string is:
	 * - empty
	 * - not too long
	 * - matches a certain regex
	 *
	 * @param string $value
	 *
	 * @return bool
	 */
	public function validate($value)
	{
		if (zbx_empty($value)) {
			if ($this->empty) {
				return true;
			}
			else {
				$this->error($this->messageEmpty);

				return false;
			}
		}

		if ($this->maxLength && zbx_strlen($value) > $this->maxLength) {
			$this->error($this->messageMaxLength, $this->maxLength);

			return false;
		}

		if ($this->regex && !zbx_empty($value) && !preg_match($this->regex, $value)) {
			$this->error($this->messageRegex, $value);

			return false;
		}

		return true;
	}

}
