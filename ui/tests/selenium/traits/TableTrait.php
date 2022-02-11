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
	 * Select table rows.
	 *
	 * @param mixed $data			rows to be selected
	 * @param string $column		column name
	 * @param string $selector		table selector
	 */
	public function selectTableRows( $data = [], $column = 'Name', $selector = 'class:list-table') {
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
	 */
	private function getTableResult($column) {
		$table = $this->query('class:list-table')->asTable()->one();
		$result = [];
		foreach ($table->getRows() as $row) {
			$result[] = $row->getColumn($column)->getText();
		}
		return $result;
	}
}
