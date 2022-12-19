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

require_once dirname(__FILE__).'/../../include/CWebTest.php';

/**
 * Trait for filter related tests.
 */
trait TableTrait {

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

	/**
	 * Check if values in table rows match data from data provider.
	 *
	 * @param array   $data        data array to be match with result in table
	 * @param string  $selector    table selector
	 */
	public function assertTableData($data = [], $selector = 'class:list-table') {
		$rows = $this->query($selector)->asTable()->one()->getRows();
		if (!$data) {
			// Check that table contain one row with text "No data found."
			$this->assertEquals(['No data found.'], $rows->asText());

			return;
		}

		$this->assertEquals(count($data), $rows->count(), 'Rows count does not match results count in data provider.');
		$this->assertEquals(array_keys($data), array_keys($rows->asArray()),
				'Row indices don\'t not match indices in data provider.'
		);

		foreach ($this->normalizeData($data) as $i => $values) {
			$row = $rows->get($i);

			foreach ($values as $name => $value) {
				if (($text = $row->getColumnData($name, $value)) === null) {
					continue;
				}

				$this->assertEquals($value['text'], $text);
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
	public function assertTableHasData($data = [], $selector = 'class:list-table') {
		$table_rows = $this->query($selector)->asTable()->one()->index();

		if (!$data) {
			// Check that table contain one row with text "No data found."
			$this->assertEquals(['No data found.'], $rows->asText());

			return;
		}

		foreach ($data as $data_row) {
			$found = false;

			foreach ($table_rows as $table_row) {
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
				throw new \Exception('Row "'.implode(', ', $data_row).'" was not found in table.');
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
	public function selectTableRows($data = [], $column = 'Name', $selector = 'class:list-table') {
		$table = $this->query($selector)->asTable()->one();

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
	 * @param integer $count	rows count per page
	 * @param integer $total	total rows count
	 */
	public function assertTableStats($count, $total = null) {
		if ($total === null) {
			$total = $count;
		}
		$this->assertEquals('Displaying '.$count.' of '.$count.' found',
				$this->query('xpath://div[@class="table-stats"]')->one()->getText()
		);
	}

	/**
	 * Get data from chosen column.
	 *
	 * @param string $column		Column name, where value should be checked
	 * @param string $selector		Table selector
	 */
	private function getTableColumnData($column, $selector = 'class:list-table') {
		$table = $this->query($selector)->asTable()->one();
		$result = [];
		foreach ($table->getRows() as $row) {
			$result[] = $row->getColumn($column)->getText();
		}
		return $result;
	}
}
