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

class CRegexValidator extends CValidator
{

	/**
	 * Error message if the is not a string.
	 *
	 * @var string
	 */
	public $messageInvalid;

	/**
	 * Error message if the value is invalid
	 *
	 * @var string
	 */
	public $messageRegex;

	/**
	 * Check if regular expression is valid
	 *
	 * @param string $value
	 *
	 * @return bool
	 */
	public function validate($value) {
		if (!is_string($value) && !is_numeric($value)) {
			$this->error($this->messageInvalid);
			return false;
		}

		// escape '/' since Zabbix server treats them as literal characters.
		$value = str_replace('/', '\/', $value);

		// validate through preg_match
		$error = false;

		set_error_handler(function ($errno, $errstr) use (&$error) {
			if ($errstr != '') {
				$error = $errstr;
			}
		});

		preg_match('/'.$value.'/', '');

		restore_error_handler();

		if ($error) {
			$this->error(
				$this->messageRegex,
				$value,
				str_replace('preg_match(): ', '', $error)
			);
			return false;
		}

		return true;
	}
}
