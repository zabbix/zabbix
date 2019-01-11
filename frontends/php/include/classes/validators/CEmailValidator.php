<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
