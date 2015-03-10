<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
 * XML array validator.
 */
class CXmlArrayValidator {

	private $error;

	public function validate($name, array $array) {
		$i = 0;

		foreach ($array as $key => $value) {
			$postFix = ($i == 0) ? '' : $i;
			$validKey = $name.$postFix;

			if ($key !== $validKey) {
				$this->error = _s('unexpected tag "%1$s"', $key);
				break;
			}

			$i++;
		}
	}

	/**
	 * Returns array of error messages.
	 *
	 * @return array
	 */
	public function getError() {
		return $this->error;
	}
}
