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

require_once 'vendor/autoload.php';

require_once dirname(__FILE__).'/../CElement.php';

/**
 * Table row element.
 */
class CTableRowElement extends CElement {
	/**
	 * Parent element
	 *
	 * @var CTableElement
	 */
	public $parent;

	/**
	 * Table column selector.
	 *
	 * @var string
	 */
	protected $column_selector = 'xpath:./td';

	/**
	 * @inheritdoc
	 */
	public function normalize() {
		if ($this->parent === null) {
			$this->parent = $this->parents('tag:table')->asTable()->one();
		}
	}

	/**
	 * Get collection of row columns indexed by table headers.
	 *
	 * @return CElementCollection
	 */
	public function getColumns() {
		$headers = $this->parent->getHeadersText();
		$columns = [];

		foreach ($this->query($this->column_selector)->all() as $i => $column) {
			$columns[CTestArrayHelper::get($headers, $i, $i)] = $column;
		}

		return new CElementCollection($columns);
	}

	/**
	 * Get column by index or name.
	 *
	 * @param mixed $column    column index or name
	 *
	 * @return CElement
	 */
	public function getColumn($column) {
		$headers = $this->parent->getHeadersText();

		if (is_string($column)) {
			$index = array_search($column, $headers);
			if ($index === false) {
				return new CNullElement(['locator' => '"'.$column.'" (table column name)']);
			}

			$column = $index;
		}

		return $this->query('xpath:./'.CXPathHelper::fromSelector($this->column_selector).'['.((int)$column + 1).']')->one();
	}

	/**
	 * Get value from table column.
	 *
	 * @param string $name		table column name
	 * @param string $value		value from data provider
	 *
	 * @return string
	 *
	 * @throws Exception    on unsupported value format
	 */
	public function getColumnData($name, $value) {
		if (!is_array($value)) {
			$value = ['text' => $value];
		}

		if (!array_key_exists('text', $value)) {
			throw new Exception('Cannot get column data not by element text.');
		}

		$column = $this->getColumn($name);

		if (array_key_exists('selector', $value)) {
			$query = $column->query($value['selector']);
			$text = (!is_array($value['text'])) ? $query->one()->getText() : $query->all()->asText();
		}
		else {
			$text = (is_array($value['text'])) ? [$column->getText()] : $column->getText();
		}

		return $text;
	}

	/**
	 * Select table row.
	 * For tables with checkboxes.
	 *
	 * @return $this
	 */
	public function select() {
		$this->query('xpath:.//input[@type="checkbox"]')->asCheckbox()->one()->check();

		return $this;
	}

	/**
	 * Check if table row is selected.
	 * For tables with checkboxes.
	 *
	 * @param boolean $selected    if it is expected for row to be selected or not
	 *
	 * @return boolean
	 */
	public function isSelected($selected = true) {
		return $this->query('xpath:.//input[@type="checkbox"]')->one()->isSelected($selected);
	}
}
