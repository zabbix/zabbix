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
 * Widget Field for numeric box
 */
class CWidgetFieldNumericBox extends CWidgetField {

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
	 * A numeric box widget field.
	 *
	 * @param string $name   field name in form
	 * @param string $label  label for the field in form
	 * @param int    $min    minimal allowed value (this included)
	 * @param int    $max    maximal allowed value (this included)
	 */
	public function __construct($name, $label, $min = 0, $max = ZBX_MAX_INT32) {
		parent::__construct($name, $label);

		$this->setSaveType(ZBX_WIDGET_FIELD_TYPE_INT32);
		$this->min = $min;
		$this->max = $max;
		$this->setExValidationRules(['in' => $this->min.':'.$this->max]);
	}

	public function getMaxLength() {
		return strlen((string) $this->max);
	}

	public function setValue($value) {
		return parent::setValue((int) $value);
	}
}
