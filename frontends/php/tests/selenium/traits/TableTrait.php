<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
	 * Check if values in table rows by one column match data from data provider.
	 *
	 * @param array   $results        data array to be match with result in table
	 * @param string  $field          table column name
	 */
	public function checkTableRows($results = [], $field = 'Name') {
		$rows = $this->query('class:list-table')->asTable()->one()->getRows();
		if (!$results) {
			// Check that table contain one row with text "No data found."
			$this->assertEquals(['No data found.'], $rows->asText());

			return;
		}

		$this->assertEquals(count($results), $rows->count(), 'Rows count does not match results count in data provider.');
		foreach ($rows as $i => $row) {
			// Get name in row excluding inherited, dependent or master element names.
			$row_data = $row->getColumn($field)->query('xpath:./a[not(@class)]')->one()->getText();
			$this->assertEquals($results[$i], $row_data);
		}
	}
}
