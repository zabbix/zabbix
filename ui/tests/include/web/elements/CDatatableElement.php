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


require_once 'vendor/autoload.php';
require_once __DIR__.'/../CElement.php';

/**
 * Datatable element.
 */
class CDatatableElement extends CElement {

	/**
	 * Datatable element selectors.
	 *
	 * @var array
	 */
	protected $selectors = [
		'header' => 'xpath:./div[contains(@class, "datatable-header")]/div[contains(@class, "cell-header")]',
		'row' => 'xpath:./div[@class="datatable-body"]/div[contains(@class, "row")]',
		'column' => 'xpath:./*'
	];

	/**
	 * Header element collection.
	 *
	 * @var CElementCollection
	 */
	protected $headers;

	/**
	 * Array of header element texts.
	 *
	 * @var array
	 */
	protected $headers_text;

	/**
	 * Array of column names.
	 *
	 * @var array
	 */
	protected $column_names;

	/**
	 * @inheritdoc
	 */
	protected function normalize() {
		if ($this->getTagName() !== 'div') {
			$this->setElement($this->query('xpath:.//div[contains(@class, "datatable")]')->waitUntilPresent()->one());
		}
	}

	/**
	 * @inheritdoc
	 */
	public function invalidate() {
		parent::invalidate();

		$this->headers = null;
		$this->headers_text = null;
	}

	/**
	 * Get header element collection.
	 *
	 * @return CElementCollection
	 */
	public function getHeaders() {
		if ($this->headers === null) {
			$this->headers = $this->query($this->selectors['header'])->waitUntilVisible()->all();
		}

		return $this->headers;
	}

	/**
	 * Get header element by text.
	 *
	 * @param string $text     header text.
	 *
	 * @return CElement
	 */
	public function getHeaderByText($text = '') {
		return $this->query($this->selectors['header'].'//span[text()='.CXPathHelper::escapeQuotes($text).']/..')
				->waitUntilVisible()->one();
	}

	/**
	 * Get array of header element texts.
	 *
	 * @return array
	 */
	public function getHeadersText() {
		if ($this->getHeaders()->isStalled()) {
			$this->invalidate();
		}

		if ($this->headers_text === null) {
			$this->headers_text = $this->getHeaders()->asText();
		}

		return $this->headers_text;
	}

	/**
	 * Get sortable datatable headers.
	 *
	 * @return CElementCollection
	 */
	public function getSortableHeaders() {
		return $this->getHeaders()->query('tag:a');
	}

	/**
	 * Get array of header element texts for column naming.
	 *
	 * @return array
	 */
	public function getColumnNames() {
		if ($this->column_names === null) {
			$this->column_names = $this->getHeadersText();
		}

		return $this->column_names;
	}

	/**
	 * Get collection of datatable rows.
	 *
	 * @param array $names  array of names
	 */
	public function setColumnNames($names) {
		$this->column_names = $names;
	}

	/**
	 * Get collection of table rows.
	 *
	 * @return CElementCollection
	 */
	public function getRows() {
		return $this->query($this->selectors['row'])->asTableRow([
			'parent' => $this,
			'column_selector' => $this->selectors['column']
		])->all();
	}

	/**
	 * Get datatable row by index.
	 *
	 * @param $index    row index
	 *
	 * @return CTableRow
	 */
	public function getRow($index) {
		return $this->query($this->selectors['row'].'['.((int)$index + 1).']')->asTableRow([
			'parent' => $this,
			'column_selector' => $this->selectors['column']
		])->one();
	}

	/**
	 * Find row by column value.
	 *
	 * @param string	$column    column name
	 * @param string	$value     column value
	 * @param boolean	$contains  flag that determines if column value should contain the passed value or coincide with it
	 *
	 * @return CTableRow|CNullElement
	 */
	public function findRow($column, $value, $contains = false) {
		try {
			$this->getColumnNames();
		}
		catch (StaleElementReferenceException $exception) {
			$this->invalidate();
		}
		$headers = $this->getColumnNames();

		if (is_string($column)) {
			$index = array_search($column, $headers);
			if ($index === false) {
				return new CNullElement(['locator' => '"'.$column.'" (table column name)']);
			}

			$column = $index + 1;
		}

		$suffix = $contains
			? '['.$column.'][contains(string(), '.CXPathHelper::escapeQuotes($value).')]/..'
			: '['.$column.'][string()='.CXPathHelper::escapeQuotes($value).']/..';

		return $this->query('xpath:.//div[@class="datatable-body"]/div[contains(@class,"row")]/div[contains(@class, "cell-data")]'.
				$suffix)->asTableRow(['parent' => $this, 'column_selector' => $this->selectors['column']])->one(false);
	}

	/**
	 * Find rows by column value or by criteria in function.
	 *
	 * @param callable $param		column name or function
	 * @param mixed $data			row data
	 *
	 * @return CElementCollection
	 */
	public function findRows($param, $data = []) {
		$rows = [];

		if ($param instanceof \Closure) {
			foreach ($this->getRows() as $i => $row) {
				if (call_user_func($param, $row)) {
					$rows[$i] = $row;
				}
			}
		}
		else {
			if (!is_array($data)) {
				$data = [$data];
			}

			foreach ($this->getRows() as $row) {
				if (in_array($row->getColumnData($param, ''), $data)) {
					$rows[] = $row;
				}
			}
		}

		return new CElementCollection($rows, CTableRowElement::class);
	}

	/**
	 * @inheritdoc
	 */
	public function waitUntilReady($timeout = null) {
		$this->waitUntilClassesNotPresent('is-loading');

		return $this;
	}

	/**
	 * Wait until datatable has the expected count of rows.
	 *
	 * @param int $count   expected count of rows
	 */
	public function waitUntilRowsCount($count) {
		$table = $this;
		CElementQuery::wait(2)->until(function () use ($table, $count) {
			try {
				return $table->getRows()->count() === $count;
			}
			catch (Exception $e) {
				// Code is not missing here.
			}
		}, 'Failed to wait until there are exactly '.$count.' rows in the datatable.');
	}

	/**
	 * Scroll the datatable to the right position.
	 */
	public function scrollRightHorizontally() {
		CElementQuery::getDriver()->executeScript('arguments[0].querySelector(\'.datatable-body\').scrollLeft = '.
				'arguments[0].offsetWidth;', [$this]
		);
	}
}
