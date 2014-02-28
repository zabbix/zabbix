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


class CNumericValidator extends CValidator {

	/**
	 * Allowed numbers count before point.
	 *
	 * @var int
	 */
	public $scaleBeforePoint;

	/**
	 * Allowed numbers count after point.
	 *
	 * @var int
	 */
	public $scaleAfterPoint;

	/**
	 * Error message for numbers before point validation.
	 *
	 * @var string
	 */
	public $messageBeforePoint;

	/**
	 * Error message for numbers after point validation.
	 *
	 * @var string
	 */
	public $messageAfterPoint;

	/**
	 * Checks if the given string is correct double.
	 *
	 * @param double $value
	 *
	 * @return bool
	 */
	public function validate($value) {
		$parts = explode('.', $value);

		if (!is_numeric($parts[0]) || strlen($parts[0]) > $this->scaleBeforePoint) {
			$this->error($this->messageBeforePoint, $value);

			return false;
		}

		if (isset($parts[1]) && (!is_numeric($parts[1]) || strlen($parts[1]) > $this->scaleAfterPoint)) {
			$this->error($this->messageAfterPoint, $value);

			return false;
		}

		return true;
	}
}
