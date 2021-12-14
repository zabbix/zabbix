<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
	 * @var bool $enabled
	 */
	protected $enabled = true;

	/**
	 * Checkboxes id unique suffix.
	 */
	protected $uniqid = '';

	/**
	 * @var bool $vertical
	 */
	protected $vertical = false;

	/**
	 * @var int $columns
	 */
	protected $columns;

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
	 * Set unique ID, is used as suffix for generated check-box IDs.
	 *
	 * @param string $uniqid  Unique id string.
	 */
	public function setUniqid(string $uniqid) {
		$this->uniqid = $uniqid;

		return $this;
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
				'label' => '',
				'value' => null,
				'checked' => false
			];
		}

		return $this;
	}

	/**
	 * Sets the width of the checkbox list.
	 *
	 * @return CCheckBoxList
	 */
	public function setWidth($value) {
		$this->addStyle('width: '.$value.'px;');

		return $this;
	}

	/**
	 * Set checkboxes enabled state.
	 *
	 * @param bool $enabled  State of checkboxes.
	 */
	public function setEnabled($enabled) {
		$this->enabled = $enabled;

		return $this;
	}

	/**
	 * Display checkboxes in vertical order.
	 *
	 * @param bool $vertical
	 *
	 * @return CCheckBoxList
	 */
	public function setVertical(bool $vertical): CCheckBoxList {
		$this->vertical = $vertical;

		return $this;
	}

	/**
	 * Set number of columns.
	 *
	 * @param int $columns
	 *
	 * @return CCheckBoxList
	 */
	public function setColumns(int $columns): CCheckBoxList {
		$this->columns = $columns;
		$classes = [
			1 => null,
			2 => ZBX_STYLE_COLUMNS_2,
			3 => ZBX_STYLE_COLUMNS_3
		];
		if (array_key_exists($columns, $classes)) {
			$this->addClass(ZBX_STYLE_COLUMNS);
			$this->addClass($classes[$columns]);
		}

		return $this;
	}

	/**
	 * Change order of values for them to appear vertically aligned.
	 *
	 * @return array
	 */
	protected function orderValuesVertical(): array {
		$ordered = [];
		$values_count = count($this->values);
		$max_rows = (int) ceil($values_count / $this->columns);
		$values_last_row = $values_count % $this->columns;
		if ($values_count < $this->columns) {
			$values_last_row = 0;
		}

		for ($row = 0; $row < $max_rows; $row++) {
			for ($i = 0; $i < $values_count; $i += $max_rows) {
				if ($values_last_row !== 0 && $i >= $values_count - ($this->columns - $values_last_row)) {
					$i -= $values_last_row;
				}
				if (array_key_exists($row + $i, $this->values)) {
					$ordered[$row + $i] = $this->values[$row + $i];
				}
			}
		}
		return $ordered;
	}

	/*
	 * @param bool $destroy
	 *
	 * @return string
	 */
	public function toString($destroy = true) {
		$uniqid = ($this->uniqid === '') ? '' : '_'.$this->uniqid;

		if ($this->vertical) {
			$this->values = $this->orderValuesVertical();
		}

		foreach ($this->values as $value) {
			$name = array_key_exists('name', $value) ? $value['name'] : $this->name.'['.$value['value'].']';

			$checkbox = (new CCheckBox($name, $value['value']))
				->setLabel($value['label'])
				->setChecked($value['checked'])
				->setEnabled($this->enabled);
			$checkbox->setId($checkbox->getId().$uniqid);

			parent::addItem($checkbox);
		}

		return parent::toString($destroy);
	}
}
