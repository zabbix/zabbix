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


require_once dirname(__FILE__).'/../../include/CBehavior.php';

/**
 * Behavior for filter related tests.
 */
class CTableBehavior extends CBehavior {

	/**
	 * Table column names.
	 *
	 * @var array
	 */
	protected $column_names = null;

	/**
	 * Set names of columns.
	 *
	 * @param array $names column names
	 */
	public function setColumnNames($names) {
		$this->column_names = $names;
	}

	/**
	 * Perform data array normalization.
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	protected function normalizeData($data) {
		foreach ($data as &$values) {
			foreach ($values as &$value) {
				if (!is_array($value)) {
					$value = ['text' => $value];
				}
			}
			unset($value);
		}
		unset($values);

		return $data;
	}

	public function getTable($selector = null) {
		if ($selector === null) {
			$selector = 'class:list-table';
		}

		$table = $this->test->query($selector)->asTable()->one();
		if ($this->column_names !== null) {
			$table->setColumnNames($this->column_names);
		}

		return $table;
	}

	/**
	 * Check if values in table rows match data from data provider.
	 *
	 * @param array   $data        data array to be match with result in table
	 * @param string  $selector    table selector
	 */
	public function assertTableData($data = [], $selector = null) {
		$rows = $this->getTable($selector)->getRows();
		if (!$data) {
			// Check that table contain one row with text "No data found."
			$this->test->assertEquals(['No data found'], $rows->asText());

			return;
		}

		$this->test->assertEquals(count($data), $rows->count(), 'Rows count does not match results count in data provider.');
		$this->test->assertEquals(array_keys($data), array_keys($rows->asArray()),
				'Row indices don\'t not match indices in data provider.'
		);

		foreach ($this->normalizeData($data) as $i => $values) {
			$row = $rows->get($i);

			foreach ($values as $name => $value) {
				if (($text = $row->getColumnData($name, $value)) === null) {
					continue;
				}

				$this->test->assertEquals($value['text'], $text);
			}
		}
	}

	/**
	 * Check if values in table rows have data from data provider.
	 *
	 * @param array   $data        data array to be matched with result in table
	 * @param string  $selector    table selector
	 *
	 * @throws Exception
	 */
	public function assertTableHasData($data = [], $selector = null) {
		$table = $this->getTable($selector);

		if (!$data) {
			// Check that table contains one row with text "No data found."
			$this->test->assertEquals(['No data found'], $table->getRows()->asText());

			return;
		}

		foreach ($data as $data_row) {
			$found = false;

			foreach ($table->index() as $table_row) {
				$match = true;

				foreach ($data_row as $key => $value) {
					if (!isset($table_row[$key]) || $table_row[$key] != $data_row[$key]) {
						$match = false;
						break;
					}
				}

				if ($match) {
					$found = true;
					break;
				}
			}

			if (!$found) {
				throw new \Exception('Row ('.implode(', ', array_map(function ($value) {
					return '"'.$value.'"';
				}, $data_row)).') was not found in table.');
			}
		}
	}

	/**
	 * Check if values in table column match data from data provider.
	 *
	 * @param array   $rows        data array to be match with result in table
	 * @param string  $field       table column name
	 */
	public function assertTableDataColumn($rows = [], $field = 'Name', $selector = 'class:list-table') {
		$data = [];
		foreach ($rows as $row) {
			$data[] = [$field => $row];
		}

		$this->assertTableData($data, $selector);
	}

	/**
	 * Check if values in table column have data from data provider.
	 *
	 * @param array   $rows        data array to be matched with result in table
	 * @param string  $field       table column name
	 */
	public function assertTableHasDataColumn($rows = [], $field = 'Name', $selector = 'class:list-table') {
		$data = [];
		foreach ($rows as $row) {
			$data[] = [$field => $row];
		}

		$this->assertTableHasData($data, $selector);
	}

	/**
	 * Select table rows.
	 *
	 * @param mixed $data			rows to be selected
	 * @param string $column		column name
	 * @param string $selector		table selector
	 */
	public function selectTableRows($data = [], $column = 'Name', $selector = null) {
		$table = $this->getTable($selector);

		if (!$data) {
			// Select all rows in table.
			$table->query('xpath:./thead/tr/th/input[@type="checkbox"]')->asCheckbox()->one()->check();

			return;
		}

		$table->findRows($column, $data)->select();
	}

	/**
	 * Assert text of displayed rows amount.
	 *
	 * @param integer|string $count		rows count per page
	 * @param integer $total			total rows count
	 */
	public function assertTableStats($count = null, $total = null) {
		if ($count === null || $count === 0) {
			$this->test->assertFalse($this->test->query('xpath://div[@class="table-stats"]')->one(false)->isValid(),
					'Table rows amount is visible on page');

			return;
		}

		if ($total === null) {
			$total = $count;
		}

		$this->test->assertEquals('Displaying '.$count.' of '.$total.' found',
				$this->test->query('xpath://div[@class="table-stats"]')->one()->getText()
		);
	}

	/**
	 * Get data from chosen column.
	 *
	 * @param string $column    column name, where value should be checked
	 * @param string $selector  table selector
	 */
	public function getTableColumnData($column, $selector = null) {
		$table = $this->getTable($selector);
		$result = [];
		foreach ($table->getRows() as $row) {
			$result[] = $row->getColumn($column)->getText();
		}
		return $result;
	}

	/**
	 * Assert text of selected rows amount.
	 *
	 * @param integer $count	selected rows count
	 */
	public function assertSelectedCount($count) {
		$this->test->assertEquals($count.' selected', $this->test->query('id:selected_count')->one()->getText());
	}
}
