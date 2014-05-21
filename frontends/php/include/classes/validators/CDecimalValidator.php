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
	 * Error message for format validation.
	 *
	 * @var string
	 */
	public $messageFormat;

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
	 * Checks if the given string is correct double.
	 *
	 * @param string $value
	 *
	 * @return bool
	 */
	public function validate($value) {
		// validate format
		if (!preg_match('/^-?(?:\d+|\d*\.\d+)$/', $value)) {
			$this->error($this->messageFormat, $value);

			return false;
		}

		$parts = explode('.', $value);

		$natural = $parts[0];
		$naturalSize = strlen($natural);

		if (isset($parts[1])) {
			$scale = $parts[1];
			$scaleSize = strlen($scale);
		}
		else {
			$scale = null;
			$scaleSize = 0;
		}

		// validate scale without natural
		if ($scaleSize > 0 && $naturalSize == 0) {
			$this->error($this->messageFormat, $value);

			return false;
		}

		if ($this->maxPrecision !== null) {
			$maxNaturals = $this->maxPrecision - $this->maxScale;

			// validate precision
			if ($naturalSize + $scaleSize > $this->maxPrecision) {
				$this->error($this->messagePrecision, $value, $maxNaturals, $this->maxScale);

				return false;
			}

			// validate digits before point
			if ($this->maxScale !== null) {
				if ($naturalSize > $maxNaturals) {
					$this->error($this->messageNatural, $value, $maxNaturals);

					return false;
				}
			}
		}

		// validate scale
		if ($this->maxScale !== null && $scaleSize > $this->maxScale) {
			$this->error($this->messageScale, $value, $this->maxScale);

			return false;
		}

		return true;
	}
}
