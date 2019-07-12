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


/**
 * Abstract class for tags.
 */
abstract class CXmlTag {

	/**
	 * Class tag.
	 *
	 * @var string
	 */
	protected $tag;

	/**
	 * Data key.
	 *
	 * @var ?string
	 */
	protected $key;

	/**
	 * Tag required.
	 *
	 * @var boolean
	 */
	protected $is_required = false;

	/**
	 * Tag has constant.
	 *
	 * @var boolean
	 */
	protected $has_constants = false;

	/**
	 * Store constant by id.
	 *
	 * @var array
	 */
	protected $constantids;

	/**
	 * Store constant by value.
	 *
	 * @var array
	 */
	protected $constant_values;

	/**
	 * Tag default value.
	 *
	 * @var ?string
	 */
	protected $default_value;

	/**
	 * Subtags.
	 *
	 * @var array
	 */
	protected $sub_tags = [];

	/**
	 * Callback for toXml method.
	 *
	 * @var ?callable
	 */
	protected $to_xml_callback;

	/**
	 * Callback for fromXml method.
	 *
	 * @var ?callable
	 */
	protected $from_xml_callback;

	/**
	 * Class constructor.
	 *
	 * @param string $tag
	 */
	public function __construct($tag) {
		$this->tag = $tag;
	}

	public function getTag() {
		return $this->tag;
	}

	public function setRequired() {
		$this->is_required = true;

		return $this;
	}

	public function isRequired() {
		return $this->is_required;
	}

	public function setKey($key) {
		$this->key = $key;

		return $this;
	}

	public function getKey() {
		return $this->key;
	}

	public function setDefaultValue($val) {
		$this->default_value = $val;

		return $this;
	}

	public function getDefaultValue() {
		return $this->default_value;
	}

	public function addConstant($const, $value, $index = 0) {
		$this->has_constants = true;

		$this->constantids[$index][$value] = $const;
		if (is_string($const)) {
			$this->constans_values[$index][$const] = $value;
		}
		return $this;
	}

	/**
	 * Get constant name by constant value.
	 *
	 * @param string $const
	 * @param integer      $index
	 *
	 * @return string
	 */
	public function getConstantByValue($const, $index = 0) {
		if (!array_key_exists($const, $this->constantids[$index])) {
			throw new \InvalidArgumentException(_s('Constant "%1$s" for tag "%2$s" does not exist.', $const, $this->tag));
		}

		return $this->constantids[$index][$const];
	}

	/**
	 * Get constant value by constant name.
	 *
	 * @param string $const
	 * @param integer $index
	 *
	 * @return void
	 */
	public function getConstantValueByName($const, $index = 0) {
		if (!isset($this->constans_values[$index][$const])) {
			throw new \InvalidArgumentException(_s('Constant "%1$s" for tag "%2$s" does not exist.', $const, $this->tag));
		}

		return $this->constans_values[$index][$const];
	}

	public function setSchema() {
		$this->sub_tags = func_get_args();

		return $this;
	}

	public function buildSchema() {
		$result = [];
		foreach ($this->sub_tags as $class) {
			$result[$class->getTag()] = $class;
		}

		return [$this->getTag() => $result];
	}

	public function getNextSchema() {
		if (!($this instanceof CXmlTagIndexedArray)) {
			return false;
		}

		foreach ($this->sub_tags as $class) {
			return [$class->getTag() => $class];
		}
	}

	public function toXml($data) {
		if (is_callable($this->to_xml_callback)) {
			return call_user_func($this->to_xml_callback, $data, $this);
		}

		if ($this->has_constants) {
			return $this->getConstantByValue($data[$this->tag]);
		}

		return $data[$this->tag];
	}

	public function setToXmlCallback(callable $func) {
		$this->to_xml_callback = $func;

		return $this;
	}

	public function fromXml($data) {
		if (is_callable($this->from_xml_callback)) {
			return call_user_func($this->from_xml_callback, $data, $this);
		}

		if (!array_key_exists($this->tag, $data)) {
			if ($this->getDefaultValue() !== null) {
				return (string) $this->getDefaultValue();
			}

			return '';
		}

		if ($this->has_constants) {
			return (string)$this->getConstantValueByName($data[$this->tag]);
		}

		return (string)$data[$this->tag];
	}

	public function setFromXmlCallback(callable $func) {
		$this->from_xml_callback = $func;

		return $this;
	}
}
