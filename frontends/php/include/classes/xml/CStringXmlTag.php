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

	public function addConstants(array $const, $index = 0) {
		$this->has_constants = true;

		foreach ($const as $key => $val) {
			$this->constant_names[$index][$val] = $key;
			if (is_string($key)) {
				$this->constant_values[$index][$key] = $val;
			}
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
			throw new Exception(_s('Invalid tag "%1$s": %2$s.', $this->getTag(), _s('unexpected constant value "%1$s"', $value)));
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
			throw new Exception(_s('Invalid tag "%1$s": %2$s.', $this->getTag(), _s('unexpected constant "%1$s"', $const)));
		}

		return $this->constant_values[$index][$const];
	}
}
