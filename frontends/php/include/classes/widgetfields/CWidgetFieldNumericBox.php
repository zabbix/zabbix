<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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
 * Widget Field for numeric box
 */
class CWidgetFieldNumericBox extends CWidgetField
{
	/**
	 * Allowed min value
	 *
	 * @var int
	 */
	private $min;

	/**
	 * Allowed max value
	 *
	 * @var int
	 */
	private $max;

	/**
	 * C widget field numeric box constructor
	 *
	 * @param string $name
	 * @param null|string $label
	 * @param string $default
	 * @param int $min
	 * @param int $max
	 */
	public function __construct($name, $label, $default = '', $min = 0, $max = ZBX_MAX_INT32) {
		$this->min = $min;
		$this->max = $max;
		parent::__construct($name, $label, $default, null);
		$this->setSaveType(ZBX_WIDGET_FIELD_TYPE_INT32);
	}

	/**
	 * Validate.
	 *
	 * @return array
	 */
	public function validate()
	{
		$errors = parent::validate();

		if (!($this->getValue() >= $this->min && $this->getValue() <= $this->max)) {
			$errors[] = _s(
				'Incorrect value "%1$s" for "%2$s" field: must be between %3$s and %4$s.',
				$this->getValue(), $this->getName(), $this->min, $this->max
			);
		}
		return $errors;
	}
}
