<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

	private const ZBX_STYLE_CLASS = 'checkbox-list';

	private const ZBX_STYLE_VERTICAL = 'vertical';

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
	public function __construct($name = '') {
		parent::__construct();

		$this->addClass(self::ZBX_STYLE_CLASS);
		$this->name = $name;
		$this->values = [];
		$this->columns = 1;
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
	public function setVertical(bool $vertical = true): CCheckBoxList {
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

		return $this;
	}

	/*
	 * @param bool $destroy
	 *
	 * @return string
	 */
	public function toString($destroy = true) {
		$this->addStyle('--columns: '.$this->columns.';');

		if ($this->vertical) {
			$values_count = count($this->values);
			$max_rows = (int) ceil($values_count / $this->columns);

			$this->addClass(self::ZBX_STYLE_VERTICAL);
			$this->addStyle('--rows: '.$max_rows.';');
		}

		foreach ($this->values as $value) {
			$name = array_key_exists('name', $value) ? $value['name'] : $this->name.'['.$value['value'].']';
			$checkbox = (new CCheckBox($name, $value['value']))
				->setLabel($value['label'])
				->setChecked($value['checked'])
				->setEnabled($this->enabled);

			if (array_key_exists('id', $value) || $this->uniqid !== '') {
				$checkbox->setId(array_key_exists('id', $value)
					? $value['id']
					: $checkbox->getId().'_'.$this->uniqid
				);
			}

			if (array_key_exists('unchecked_value', $value)) {
				$checkbox->setUncheckedValue($value['unchecked_value']);
			}

			parent::addItem($checkbox);
		}

		return parent::toString($destroy);
	}
}
