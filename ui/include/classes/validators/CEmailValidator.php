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


/**
 * Class to validate e-mails.
 */
class CEmailValidator extends CStringValidator {

	/**
	 * Function validates given string against the defined e-mail pattern.
	 *
	 * @param string $value  String to validate against defined e-mail pattern.
	 *
	 * @return bool
	 */
	public function validate($value) {
		if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
			preg_match('/.*<(?<email>.*[^>])>$/i', $value, $match);

			if (!array_key_exists('email', $match) || !filter_var($match['email'], FILTER_VALIDATE_EMAIL)) {
				$this->setError(_s('Invalid email address "%1$s".', $value));

				return false;
			}
		}

		return true;
	}
}
