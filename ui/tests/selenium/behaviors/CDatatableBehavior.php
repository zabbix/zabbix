<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
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


require_once __DIR__.'/CTableBehavior.php';

/**
 * Behavior for filter related tests.
 */
class CDatatableBehavior extends CTableBehavior {

//	/**
//	 * Table column names.
//	 *
//	 * @var array
//	 */
//	protected $column_names = null;
//
//	/**
//	 * Set names of columns.
//	 *
//	 * @param array $names column names
//	 */
//	public function setColumnNames($names) {
//		$this->column_names = $names;
//	}
//
//	/**
//	 * Perform data array normalization.
//	 *
//	 * @param array $data
//	 *
//	 * @return array
//	 */
//	protected function normalizeData($data) {
//		foreach ($data as &$values) {
//			foreach ($values as &$value) {
//				if (!is_array($value)) {
//					$value = ['text' => $value];
//				}
//			}
//			unset($value);
//		}
//		unset($values);
//
//		return $data;
//	}

	const COMMON_SELECTOR = 'class:datatable';

	public function getDatatable($selector = null) {
		if ($selector === null) {
			$selector = self::COMMON_SELECTOR;
		}

		$datatable = $this->test->query($selector)->asDatatable()->one()->waitUntilReady();
		if ($this->column_names !== null) {
			$datatable->setColumnNames($this->column_names);
		}

		return $datatable;
	}

	/**
	 * Check if values in table rows match data from data provider.
	 *
	 * @param array   $data        data array to be match with result in table
	 * @param string  $selector    table selector
	 */
	public function assertDatatableData($data = [], $selector = null) {
		$rows = $this->getDatatable($selector)->waitUntilReady()->getRows();
		if (!$data) {
			$this->test->assertEquals(0, $rows->count());
			// Check that table contain one row with text "No data found."
			$this->test->assertEquals('No data found', $this->test->query('class:datatable-body')->one()->getText());

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

//	/**
//	 * Check if values in table rows have data from data provider.
//	 *
//	 * @param array   $data        data array to be matched with result in table
//	 * @param string  $selector    table selector
//	 *
//	 * @throws Exception
//	 */
//	public function assertTableHasData($data = [], $selector = null) {
//		$table = $this->getTable($selector);
//
//		if (!$data) {
//			// Check that table contains one row with text "No data found."
//			$this->test->assertEquals(['No data found'], $table->getRows()->asText());
//
//			return;
//		}
//
//		foreach ($data as $data_row) {
//			$found = false;
//			$current = null;
//
//			foreach ($table->index() as $table_row) {
//				$match = true;
//
//				foreach ($data_row as $key => $value) {
//					if (!isset($table_row[$key]) || $table_row[$key] != $data_row[$key]) {
//						$current = json_encode($table_row);
//						$match = false;
//						break;
//					}
//				}
//
//				if ($match) {
//					$found = true;
//					break;
//				}
//			}
//
//			if (!$found) {
//				throw new \Exception('Row ('.implode(', ', array_map(function ($value) {
//					return '"'.$value.'"';
//				}, $data_row)).') was not found in table. Table row is: '.$current);
//			}
//		}
//	}

	/**
	 * Check if values in table column match data from data provider.
	 *
	 * @param array   $rows        data array to be match with result in table
	 * @param string  $field       table column name
	 */
	public function assertDatatableDataColumn($rows = [], $field = 'Name', $selector = self::COMMON_SELECTOR) {
		$data = [];
		foreach ($rows as $row) {
			$data[] = [$field => $row];
		}

		$this->assertDatatableData($data, $selector);
	}
//
//	/**
//	 * Check if values in table column have data from data provider.
//	 *
//	 * @param array   $rows        data array to be matched with result in table
//	 * @param string  $field       table column name
//	 */
//	public function assertTableHasDataColumn($rows = [], $field = 'Name', $selector = self::COMMON_SELECTOR) {
//		$data = [];
//		foreach ($rows as $row) {
//			$data[] = [$field => $row];
//		}
//
//		$this->assertTableHasData($data, $selector);
//	}

	/**
	 * Select table rows.
	 *
	 * @param mixed  $data			rows to be selected
	 * @param string $column		column name
	 * @param string $selector		table selector
	 */
	public function selectDatatableRows($data = [], $column = 'Name', $selector = null) {
		$table = $this->getDatatable($selector);

		if (!$data) {
			// Select all rows in table.
			$table->query('xpath:./div[contains(@class, "datatable-header")]/div/input[@type="checkbox"]')->asCheckbox()
					->one()->check();

			return;
		}

		$table->findRows($column, $data)->select();
	}

	public function filterFromHeader($header_filter, $selector = self::COMMON_SELECTOR) {
		$table = $this->getDatatable($selector);

		foreach ($header_filter as $column => $select_data) {
			$button_selector = ($column === 'Name')
				? 'xpath:.//span[text()='.CXPathHelper::escapeQuotes($column).']/../../button'
				: 'tag:button';
			$button = $table->getHeaderByText($column)->query($button_selector)->one();
			$button->click();
			$popup_dialog = $this->test->query('class:datatable-options-popup')->waitUntilVisible()->one();

			foreach ($select_data as $field => $value) {
				$for = $popup_dialog->query('xpath:.//label[text()='.CXPathHelper::escapeQuotes($field).']')->one()
						->getAttribute('for');
				$popup_dialog->query('id', $for)->one()->detect()->fill($value);
			}

			// Click on button again to close the popup.
			$button->click();
			$popup_dialog->waitUntilNotVisible();
		}
	}

	public function checkHeaderFilterLayout($header_filter, $selector = self::COMMON_SELECTOR) {
		$table = $this->getDatatable($selector);

		foreach ($header_filter as $column => $column_filter) {
			$button_selector = ($column === 'Name')
				? 'xpath:.//span[text()='.CXPathHelper::escapeQuotes($column).']/../../button'
				: 'tag:button';
			$button = $table->getHeaderByText($column)->query($button_selector)->one();
			$button->click();
			$popup_dialog = $this->test->query('class:datatable-options-popup')->waitUntilVisible()->one();

			foreach ($column_filter as $field => $parameters) {
				if ($field === 'duplicate') {
					$this->test->assertTrue($popup_dialog->query('link:Duplicate column')->one()->isCLickable());

					continue;
				}

				$for = $popup_dialog->query('xpath:.//label[text()='.CXPathHelper::escapeQuotes($field).']')->one()
						->getAttribute('for');
				$field = $popup_dialog->query('id', $for)->one()->detect();

				if (array_key_exists('value', $parameters)) {
					$this->test->assertEquals($parameters['value'], $field->getValue());
				}

				if (array_key_exists('labels', $parameters)) {
					$this->test->assertEquals($parameters['labels'], $field->getLabels()->asText());
				}

				if (array_key_exists('maxlength', $parameters)) {
					$this->test->assertEquals($parameters['maxlength'], $field->getAttribute('maxlength'));
				}
			}

			// Click on button again to close the popup.
			$button->click();
			$popup_dialog->waitUntilNotVisible();
		}
	}

	public function checkColumnList($column_list) {
		$table = $this->test->query('id:latest')->one()->asDatatable();
		$table->query('xpath:.//button[@title="Customize table"]')->one()->waitUntilClickable()->click();

		$popup_dialog = $this->test->query('xpath:.//div[@class="datatable-options-popup datatable-options"]')->one()
				->waitUntilVisible();
		$this->test->assertEquals('Column list', $popup_dialog->query('class:datatable-options-header')->one()->getText());

		foreach ($column_list as $column => $parameters) {
			$for = $popup_dialog->query('xpath:.//div[text()='.CXPathHelper::escapeQuotes($column).']/..')->one()
						->getAttribute('for');
			$field = $popup_dialog->query('id', $for)->one()->detect();

			$this->test->assertEquals(CTestArrayHelper::get($parameters, 'enabled', true), $field->isEnabled());
			$this->test->assertEquals($parameters['value'], $field->getValue());
			$this->test->assertTrue($field->query('xpath:./../div[@class="drag-icon"]')->one(false)->isValid());
		}

		$this->test->assertTrue($popup_dialog->query('button:Reset layout')->one()->isClickable());
	}

	/**
	 * Change datatable column configuration in DB.
	 *
	 * @param string   $layout   JSON that contains configuration of datatable headers.
	 * @param string   $idx      id of the profile that represents the datatable that should be updated.
	 */
	public function updateDatatableLayout($layout, $idx) {
		// Check if the corresponding record already exists. If yes - replace the value, if not - add new profile.
		$record_exists = CDBHelper::getCount('SELECT NULL FROM profiles WHERE idx = '. zbx_dbstr($idx));

		if ($record_exists) {
			DBexecute('UPDATE profiles SET value_str = '.zbx_dbstr($layout).' WHERE idx = '. zbx_dbstr($idx));
		}
		else {
			DBExecute('INSERT INTO profiles (profileid, userid, idx, value_str, type) VALUES '.
					' (666, 1, '. zbx_dbstr($idx).', '.zbx_dbstr($layout).', 3);'
			);
		}
	}

	/**
	 * Assert text of displayed rows amount.
	 *
	 * @param integer|string $count		rows count per page
	 * @param integer $total			total rows count
	 */
	public function assertDatatableStats($count = null, $total = null) {
		if ($count === null || $count === 0) {
			$this->test->assertFalse($this->test->query('xpath://div[@class="table-stats"]')->one()->isVisible(),
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
	public function getDatatableColumnData($column, $selector = null) {
		$table = $this->getDatatable($selector);
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
		$this->test->assertEquals($count.' selected', $this->test->query('class:selected-item-count')->one()->getText());
	}
}
