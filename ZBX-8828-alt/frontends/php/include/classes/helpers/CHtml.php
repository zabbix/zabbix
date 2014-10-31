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
?>
<?php

/**
 * A helper class for working with HTML.
 */
class CHtml {

	/**
	 * Encodes the value to be used in HTML code. If the given value is an array, the values will be
	 * encoded recursively.
	 *
	 * @static
	 *
	 * @param mixed $data
	 *
	 * @return mixed
	 */
	public static function encode($data) {
		if (is_array($data)) {
			$rs = array();
			foreach ($data as $key => $value) {
				$rs[$key] = self::encode($value);
			}

			return $rs;
		}

		return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
	}

	/**
	 * Encodes the data as a JSON string with HTML entities escaped.
	 *
	 * @static
	 *
	 * @param array $data
	 *
	 * @return string
	 */
	public static function serialize(array $data) {
		return self::encode(CJs::encodeJson($data));
	}
}
