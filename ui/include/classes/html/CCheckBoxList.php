<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


class CCheckBoxList extends CList {

	private const ZBX_STYLE_CLASS = 'checkbox-list';

	private const ZBX_STYLE_LAYOUT_FIXED = 'fixed';
	private const ZBX_STYLE_VERTICAL = 'vertical';

	/**
	 * @var array $values
	 */
	protected array $values = [];

	/**
	 * @var string $name
	 */
	protected $name;

	/**
	 * Checkboxes id unique suffix.
	 */
	protected $uniqid = '';

	/**
	 * @var bool $enabled
	 */
	protected $enabled = true;

	/**
	 * @var bool $readonly
	 */
	protected $readonly = false;

	/**
	 * @var bool $layout_fixed
	 */
	protected $layout_fixed = false;

	/**
	 * @var bool $vertical
	 */
	protected $vertical = false;

	/**
	 * @var int $columns
	 */
	protected int $columns = 1;

	/**
	 * @var bool
	 */
	protected bool $show_titles = false;

	/**
	 * @param string $name
	 */
	public function __construct($name = '') {
		parent::__construct();

		$this->addClass(self::ZBX_STYLE_CLASS);
		$this->name = $name;
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
	 * Set checkboxes readonly state.
	 *
	 * @param bool $readonly  State of checkboxes.
	 */
	public function setReadonly(bool $readonly) {
		$this->readonly = $readonly;

		return $this;
	}

	/**
	 * Make columns the same size.
	 *
	 * @param bool $layout_fixed
	 *
	 * @return CCheckBoxList
	 */
	public function setLayoutFixed(bool $layout_fixed = true): CCheckBoxList {
		$this->layout_fixed = $layout_fixed;

		return $this;
	}

	/**
	 * Display checkboxes in vertical order.
	 *
	 * @param bool $vertical
	 *
	 * @return CCheckBoxList
	 */
	public function setVertical(bool $vertical = true): self {
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
	public function setColumns(int $columns): self {
		$this->columns = $columns;

		return $this;
	}

	/**
	 * Set if checkboxes inside have a title.
	 *
	 * @param bool $show_titles
	 *
	 * @return CCheckBoxList
	 */
	public function showTitles(bool $show_titles = true): self {
		$this->show_titles = $show_titles;

		return $this;
	}

	/**
	 * @param bool $destroy
	 *
	 * @return string
	 */
	public function toString($destroy = true) {
		$this->addStyle('--columns: '.$this->columns.';');

		if ($this->layout_fixed) {
			$this->addClass(self::ZBX_STYLE_LAYOUT_FIXED);
		}

		if ($this->vertical) {
			$max_rows = (int) ceil(count($this->values) / $this->columns);

			$this->addClass(self::ZBX_STYLE_VERTICAL);
			$this->addStyle('--rows: '.$max_rows.';');
		}

		foreach ($this->values as $value) {
			$name = array_key_exists('name', $value) ? $value['name'] : $this->name.'['.$value['value'].']';

			$checkbox = (new CCheckBox($name, $value['value']))
				->setLabel($value['label'])
				->setChecked($value['checked'])
				->setEnabled($this->enabled)
				->setReadonly($this->readonly);

			if ($this->show_titles) {
				$checkbox->setTitle($value['label']);
			}

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
