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


class CDecimalValidator extends CValidator {

	/**
	 * Max precision (optional).
	 *
	 * @var int
	 */
	public $maxPrecision;

	/**
	 * Max scale (optional).
	 *
	 * @var int
	 */
	public $maxScale;

	/**
	 * Error message for precision validation (optional).
	 *
	 * @var string
	 */
	public $messagePrecision;

	/**
	 * Error message for natural validation (optional).
	 *
	 * @var string
	 */
	public $messageNatural;

	/**
	 * Error message for scale validation (optional).
	 *
	 * @var string
	 */
	public $messageScale;

	/**
	 * Error message for type and decimal format validation
	 *
	 * @var string
	 */
	public $messageType;

	/**
	 * Checks if the given string is correct double.
	 *
	 * @param string $value
	 *
	 * @return bool
	 */
	public function validate($value) {
		if (!is_numeric($value) || !preg_match('/^-?\d+(\.\d+)?$/', $value)) {
			$this->error($this->messageType, $value);

			return false;
		}

		$parts = explode('.', $value);

		$beforeDot = trim($parts[0], '-');

		$afterDot = isset($parts[1]) ? $parts[1] : '';

		if ($this->maxPrecision > 0 && $this->maxScale > 0) {
			// validate overall precision
			if (strlen($beforeDot) + strlen($afterDot) > $this->maxPrecision) {
				$this->error($this->messagePrecision, $value, $this->maxPrecision - $this->maxScale, $this->maxScale);

				return false;
			}
		}

		// digits before dot
		if (strlen($beforeDot) > $this->maxPrecision - $this->maxScale) {
			$this->error($this->messageNatural, $value, $this->maxPrecision - $this->maxScale);

			return false;
		}

		// digits after dot
		if (strlen($afterDot) > $this->maxScale) {
			$this->error($this->messageScale, $value, $this->maxScale);

			return false;
		}

		return true;
	}
}
