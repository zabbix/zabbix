<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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
 * A helper class for working with JavaScript.
 */
class CJs {

	/**
	 * The object used to encode values in JSON.
	 *
	 * @var CJSON
	 */
	protected static $json;

	/**
	 * Encodes the data as a JSON string to be used in JavaScript code.
	 *
	 * @static
	 *
	 * @param mixed $data
	 *
	 * @param bool $forceObject force all arrays to objects
	 *
	 * @return mixed
	 */
	public static function encodeJson($data, $forceObject = false) {
		if (self::$json === null) {
			self::$json = new CJSON();
		}

		return self::$json->encode($data, array(), $forceObject);
	}
}
