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

require_once 'vendor/autoload.php';

require_once __DIR__.'/../CElement.php';

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
		$headers = $this->parent->getColumnNames();
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
		$headers = $this->parent->getColumnNames();

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

	/**
	 * Check text of defined columns.
	 *
	 * @param mixed   $expected		values to be checked in column
	 *
	 * @throws Exception
	 */
	public function assertValues($expected) {
		if (!is_array($expected)) {
			$expected = [$expected];
		}

		foreach ($expected as $column => $value) {
			$column_value = $this->getColumn($column)->getText();

			if ($value !== $column_value) {
				throw new \Exception('Column "'.$column.'" value "'.$column_value.
						'" is not equal to "'.$value.'".'
				);
			}
		}
	}
}
