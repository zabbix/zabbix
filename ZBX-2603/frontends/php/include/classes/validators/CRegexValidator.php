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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

class CRegexValidator extends CValidator
{
	/**
	 * Error message if the argument is not a string.
	 *
	 * @var string
	 */
	public $messageType;

	/**
	 * Error message if the value is invalid.
	 *
	 * @var string
	 */
	public $messageInvalid;

	/**
	 * Check if regular expression is valid
	 *
	 * @param string $value
	 *
	 * @return bool
	 */
	public function validate($value) {
		if (!is_string($value) && !is_numeric($value)) {
			$this->error($this->messageType);
			return false;
		}

		// escape '/' since Zabbix server threats them as literal characters.
		$value = str_replace('/', '\/', $value);

		// validate through preg_match
		$error = false;

		set_error_handler(function ($errno, $errstr) use (&$error) {
			if ($errstr != '') {
				$error = $errstr;
			}
		});

		preg_match('/'.$value.'/', null);

		restore_error_handler();

		if ($error) {
			$this->error(
				$this->messageInvalid,
				$value,
				str_replace('preg_match(): ', '', $error)
			);
			return false;
		}

		return true;
	}
}
