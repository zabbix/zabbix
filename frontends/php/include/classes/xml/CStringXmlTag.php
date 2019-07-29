<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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


class CStringXmlTag extends CXmlTag implements CStringXmlTagInterface {

	/**
	 * Tag has constant.
	 *
	 * @var boolean
	 */
	protected $has_constants = false;

	/**
	 * Constant store. Name => Value.
	 *
	 * @var array
	 */
	protected $constant_values = [];

	/**
	 * Constant store. Value => Name.
	 *
	 * @var array
	 */
	protected $constant_names = [];

	/**
	 * Tag default value.
	 *
	 * @var string
	 */
	protected $default_value;

	public function setDefaultValue($value) {
		$this->default_value = $value;

		return $this;
	}

	public function getDefaultValue() {
		return $this->default_value;
	}

	public function hasConstant() {
		return $this->has_constants;
	}

	public function addConstant($const, $value, $index = 0) {
		$this->has_constants = true;

		$this->constant_names[$index][$value] = $const;
		if (is_string($const)) {
			$this->constant_values[$index][$const] = $value;
		}

		return $this;
	}

	/**
	 * Get constant name by constant value.
	 *
	 * @param string  $value
	 * @param integer $index
	 *
	 * @return string
	 */
	public function getConstantByValue($value, $index = 0) {
		if (!array_key_exists($value, $this->constant_names[$index])) {
			throw new InvalidArgumentException(_s('Constant with value "%1$s" for tag "%2$s" does not exist.', $value, $this->tag));
		}

		return $this->constant_names[$index][$value];
	}

	/**
	 * Get constant value by constant name.
	 *
	 * @param string  $const
	 * @param integer $index
	 *
	 * @return string
	 */
	public function getConstantValueByName($const, $index = 0) {
		if (!array_key_exists($const, $this->constant_values[$index])) {
			throw new InvalidArgumentException(_s('Constant "%1$s" for tag "%2$s" does not exist.', $const, $this->tag));
		}

		return $this->constant_values[$index][$const];
	}
}
