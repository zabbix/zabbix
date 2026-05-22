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


require_once __DIR__.'/../../include/CBehavior.php';

use Facebook\WebDriver\Exception\ElementClickInterceptedException;

/**
 * Behavior for datatable element.
 */
class CDatatableBehavior extends CBehavior {

	/**
	 * Datatable column names.
	 *
	 * @var array
	 */
	protected $column_names = null;

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
	 * Default selector for locating the datatable element.
	 *
	 * @var string
	 */
	const COMMON_SELECTOR = 'class:datatable-scrollable';

	/**
	 * Get datatable element by specified selector or by common selector.
	 *
	 * @param string   $selector   selector for identifying the datatable element
	 *
	 * @return CDatatableElement
	 */
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
	 * Check if values in datatable rows match data from data provider.
	 *
	 * @param array   $data        data array to be match with result in datatable
	 * @param string  $selector    datatable selector
	 */
	public function assertDatatableData($data = [], $selector = null) {
		$rows = $this->getDatatable($selector)->waitUntilReady()->getRows();
		if (!$data) {
			$this->test->assertEquals(0, $rows->count());
			// Check that datatable contain one row with text "No data found."
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

	/**
	 * Check if values in datatable column match data from data provider.
	 *
	 * @param array   $rows        data array to be match with result in datatable
	 * @param string  $field       datatable column name
	 * @param string  $selector    selector used for locating the datatable
	 */
	public function assertDatatableDataColumn($rows = [], $field = 'Name', $selector = self::COMMON_SELECTOR) {
		$data = [];
		foreach ($rows as $row) {
			$data[] = [$field => $row];
		}

		$this->assertDatatableData($data, $selector);
	}

	/**
	 * Select datatable rows.
	 *
	 * @param mixed  $data			rows to be selected
	 * @param string $column		column name
	 * @param string $selector		datatable selector
	 */
	public function selectDatatableRows($data = [], $column = 'Name', $selector = null) {
		$table = $this->getDatatable($selector);

		if (!$data) {
			// Select all rows in datatable.
			$table->query('xpath:./div[contains(@class, "datatable-header")]/div/input[@type="checkbox"]')->asCheckbox()
					->one()->check();

			return;
		}

		$table->findRows($column, $data)->select();
	}

	/**
	 * Update the datatable layout by changing settings in datatable column headers.
	 *
	 * @param array    $header_settings    settings to be changed through datatable headers
	 * @param string   $selector           datatable selector
	 */
	public function changeLayoutFromHeader($header_settings, $selector = self::COMMON_SELECTOR) {
		$table = $this->getDatatable($selector);

		foreach ($header_settings as $column => $select_data) {
			$button_selector = (in_array($column, ['Name', 'Time', 'Problem']))
				? 'xpath:.//span[text()='.CXPathHelper::escapeQuotes($column).']/../../button'
				: 'tag:button';
			$button = $table->getHeaderByText($column)->query($button_selector)->one();

			if (!$button->isClickable()) {
				$table->scrollRightHorizontally();
			}

			/**
			 *  When the button is placed under the datatable-options button it is considered clickable.
			 *  Additional scrolling is required in such cases.
			 */
			try {
				$button->click();
			}
			catch (ElementClickInterceptedException $exception) {
				$table->scrollRightHorizontally();
				$button->click();
			}

			$popup_dialog = $this->test->query('class:datatable-options-popup')->waitUntilVisible()->one();

			foreach ($select_data as $field => $value) {
				$for = $popup_dialog->query('xpath:.//label[text()='.CXPathHelper::escapeQuotes($field).']')->one()
						->getAttribute('for');
				$popup_dialog->query('id', $for)->one()->detect()->fill($value);

				$table->waitUntilReady()->invalidate();
			}

			// Click on button again to close the popup.
			$button->invalidate();
			$button->click();
			$popup_dialog->waitUntilNotVisible();
		}
	}

	/**
	 * Check the layout of datatable header settings in corresponding headers.
	 *
	 * @param array    $header_settings    settings in corresponding datatable headers and their parameters
	 * @param string   $selector           datatable selector
	 */
	public function checkHeaderSettingsLayout($header_settings, $selector = self::COMMON_SELECTOR) {
		$table = $this->getDatatable($selector);

		foreach ($header_settings as $column => $column_settings) {
			$button_selector = (in_array($column, ['Name', 'Time', 'Problem']))
				? 'xpath:.//span[text()='.CXPathHelper::escapeQuotes($column).']/../../button'
				: 'tag:button';
			$button = $table->getHeaderByText($column)->query($button_selector)->one();

			/**
			 *  When the button is placed under the datatable-options button it is considered clickable.
			 *  Additional scrolling is required in such cases.
			 */
			try {
				$button->click();
			}
			catch (ElementClickInterceptedException $exception) {
				$table->scrollRightHorizontally();
				$button->click();
			}

			$popup_dialog = $this->test->query('class:datatable-options-popup')->waitUntilVisible()->one();

			foreach ($column_settings as $field => $parameters) {
				if ($field === 'duplicate') {
					$this->test->assertTrue($popup_dialog->query('link:Duplicate column')->one()->isClickable());

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

	/**
	 * Check the available datatable configuration options in datatable options dialog.
	 *
	 * @param array $column_list   list of available columns in datatable options dialog, their state and status
	 */
	public function checkColumnList($column_list) {
		$table = $this->getDatatable();
		$table->query('xpath:.//button[@title="Customize table"]')->one()->waitUntilClickable()->click();

		$popup_dialog = $this->test->query('xpath://div[@class="datatable-options-popup datatable-options"]')
				->waitUntilVisible()->one();
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
	 * Change the whether specified columns should be displayed.
	 *
	 * @param array $column_state_changes   list of columns which state should be changed
	 */
	public function updateColumnList($column_state_changes) {
		$table = $this->getDatatable();
		$button = $table->query('xpath:.//button[@title="Customize table"]')->one()->waitUntilClickable();
		$button->click();
		$popup_dialog = $this->test->query('xpath://div[@class="datatable-options-popup datatable-options"]')
				->waitUntilVisible()->one();

		foreach ($column_state_changes as $field => $value) {
			$for = $popup_dialog->query('xpath:.//div[text()='.CXPathHelper::escapeQuotes($field).']/..')->one()
						->getAttribute('for');
			$popup_dialog->query('id', $for)->one()->asCheckbox()->set($value);
			$table->waitUntilReady();
		}

		// Click on button again to close the popup.
		$button->invalidate();
		$button->click();
		$popup_dialog->waitUntilNotVisible();
	}

	/**
	 * Change datatable column configuration in DB.
	 *
	 * @param string   $layout   JSON that contains configuration of datatable headers
	 * @param string   $idx      id of the profile that represents the datatable that should be updated
	 */
	public function updateDatatableLayout($layout, $idx) {
		// Check if the corresponding record already exists. If yes - replace the value, if not - add new profile.
		$record_exists = CDBHelper::getCount('SELECT NULL FROM profiles WHERE idx = '. zbx_dbstr($idx));

		if ($record_exists) {
			DBexecute('UPDATE profiles SET value_str = '.zbx_dbstr($layout).' WHERE idx = '. zbx_dbstr($idx));
		}
		else {
			DBExecute('INSERT INTO profiles (profileid, userid, idx, value_str, type) VALUES'.
					' (666, 1, '. zbx_dbstr($idx).', '.zbx_dbstr($layout).', 3);'
			);
		}
	}

	/**
	 * Assert text of displayed rows count.
	 *
	 * @param integer|string $count     rows count per page
	 * @param integer        $total     total rows count
	 */
	public function assertDatatableStats($count = null, $total = null) {
		if ($count === null || $count === 0) {
			$this->test->assertFalse($this->getDatatable()->query('xpath://div[@class="table-stats"]')
					->one()->isVisible(), 'Datatable rows count is visible on page'
			);

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
	 * @param string $selector  datatable selector
	 *
	 * @return array
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
	 * Assert the count of selected datatable rows.
	 *
	 * @param integer $count	selected rows count
	 */
	public function assertSelectedCount($count) {
		$this->test->assertEquals($count.' selected', $this->test->query('class:selected-item-count')->one()->getText());
	}
}
