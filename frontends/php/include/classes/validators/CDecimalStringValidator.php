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


/**
 * Class CDecimalStringValidator
 */
class CDecimalStringValidator extends CValidator {

	/**
	 * Error message for type and decimal format validation
	 *
	 * @var string
	 */
	public $messageInvalid;

	/**
	 * Returns true if the given $value is valid, or sets an error and returns false otherwise.
	 *
	 * @param $value
	 *
	 * @return bool
	 */
	public function validate($value) {
		$isValid = false;

		if (is_scalar($value)) {
			$isValid = ($this->isValidCommonNotation($value)
				|| $this->isValidDotNotation($value)
				|| $this->isValidScientificNotation($value));
		}

		if (!$isValid) {
			$this->error($this->messageInvalid, $this->stringify($value));
		}

		return $isValid;
	}

	/**
	 * Validates usual decimal syntax - "1.0", "0.11", "0".
	 *
	 * @param string $value
	 *
	 * @return boolean
	 */
	protected function isValidCommonNotation($value){
		return preg_match('/^-?\d+(\.\d+)?$/', $value);
	}

	/**
	 * Validates "dot notation" - ".11", "22."
	 *
	 * @param string $value
	 *
	 * @return boolean
	 */
	protected function isValidDotNotation($value) {
		return preg_match('/^-?(\.\d+|\d+\.)$/', $value);
	}

	/**
	 * Validate decimal string in scientific notation - "10e3", "1.0e-5".
	 *
	 * @param string $value
	 *
	 * @return boolean
	 */
	protected function isValidScientificNotation($value) {
		return preg_match('/^-?[0-9]+(\.[0-9]+)?e[+|-]?[0-9]+$/i', $value);
	}
}
