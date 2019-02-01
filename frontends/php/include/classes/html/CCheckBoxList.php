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


class CCheckBoxList extends CList {

	/**
	 * @var array $values
	 */
	protected $values;

	/**
	 * @var string $name
	 */
	protected $name;

	/**
	 * @param string $name
	 */
	public function __construct($name) {
		parent::__construct();

		$this->addClass(ZBX_STYLE_CHECKBOX_LIST);
		$this->name = $name;
		$this->values = [];
	}

	/**
	 * @param array $values
	 *
	 * @return CCheckBoxList
	 */
	public function setChecked(array $values) {
		$values = array_flip($values);

		foreach ($this->values as &$value) {
			$value['checked'] = array_key_exists($value['value'], $values);
		}
		unset($value);

		return $this;
	}

	/**
	 * @param array $values
	 *
	 * @return CCheckBoxList
	 */
	public function setOptions(array $values) {
		$this->values = [];

		foreach ($values as $value) {
			$this->values[] = $value + [
				'name' => '',
				'value' => null,
				'checked' => false
			];
		}

		return $this;
	}

	/*
	 * @param bool $destroy
	 *
	 * @return string
	 */
	public function toString($destroy = true) {
		foreach ($this->values as $value) {
			parent::addItem(
				(new CCheckBox($this->name.'['.$value['value'].']', $value['value']))
					->setLabel($value['name'])
					->setChecked($value['checked'])
			);
		}

		return parent::toString($destroy);
	}
}
