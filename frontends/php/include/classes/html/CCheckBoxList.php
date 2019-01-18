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
	 * @var string $name
	 */
	protected $name;
	/**
	 * @var array $checked_values
	 */
	protected $checked_values;

	/**
	 * @param string $name
	 * @param array $checked_values
	 */
	public function __construct($name, $checked_values = []) {
		parent::__construct();

		$this->addClass(ZBX_STYLE_COLUMNS);
		$this->addStyle('line-height: 20px;');
		$this->name = $name;
		$this->checked_values = array_flip($checked_values);
	}

	/**
	 * @param string $label
	 * @param string $value
	 *
	 * @return CCheckBoxList
	 */
	public function addCheckBox($label, $value) {
		parent::addItem(
			(new CCheckBox($this->name.'['.$value.']', $value))
				->setLabel($label)
				->setChecked(array_key_exists($value, $this->checked_values)),
			ZBX_STYLE_COLUMN_33
		);

		return $this;
	}
}
