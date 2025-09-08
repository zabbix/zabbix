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
 * Table element.
 */
class CTableElement extends CElement {

	/**
	 * Table element selectors.
	 *
	 * @var array
	 */
	protected $selectors = [
		'header' => 'xpath:./thead/tr/th',
		'row' => 'xpath:./tbody/tr',
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
		if ($this->getTagName() !== 'table') {
			$this->setElement($this->query('xpath:.//table')->waitUntilPresent()->one());
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
			$this->headers = $this->query($this->selectors['header'])->all();
		}

		return $this->headers;
	}

	/**
	 * Get array of header element texts.
	 *
	 * @return array
	 */
	public function getHeadersText() {
		if ($this->headers_text === null) {
			$this->headers_text = $this->getHeaders()->asText();
		}

		return $this->headers_text;
	}

	/**
	 * Get sortable table headers.
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
	 * Set array of header element texts for column naming.
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
	 * Get table row by index.
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
	 * Get indexed collections of table columns.
	 *
	 * @return array
	 */
	public function getCells() {
		$headers = $this->getColumnNames();

		$table = [];
		foreach ($this->getRows() as $row) {
			$data = [];
			foreach ($row->query('xpath:./'.CXPathHelper::fromSelector($this->selectors['column']).'|./th')->all() as $i => $column) {
				$data[CTestArrayHelper::get($headers, $i, $i)] = $column;
			}

			$table[] = new CElementCollection($data);
		}

		return $table;
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
		$headers = $this->getColumnNames();

		if (is_string($column)) {
			$index = array_search($column, $headers);
			if ($index === false) {
				return new CNullElement(['locator' => '"'.$column.'" (table column name)']);
			}

			$column = $index + 1;
		}

		$suffix = $contains ? '['.$column.'][contains(string(), '.CXPathHelper::escapeQuotes($value).')]/..'
			: '['.$column.'][string()='.CXPathHelper::escapeQuotes($value).']/..';
		$xpaths = ['.//tbody/tr/td'.$suffix, './/tbody/tr/th'.$suffix];

		return $this->query('xpath', implode('|', $xpaths))->asTableRow([
			'parent' => $this,
			'column_selector' => $this->selectors['column']
		])->one(false);
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
	 * Index table row text by values of table column.
	 *
	 * @param string  $column	         column name
	 * @param boolean $include_column    flag used to include or remove column used in index
	 *
	 * @return array
	 */
	public function index($column = null, $include_column = false) {
		$table = [];
		foreach ($this->getCells() as $i => $row) {
			$data = [];
			$id = $i;

			foreach ($row as $header => $element) {
				$value = $element->getText();

				if ($header === $column) {
					$id = $value;
				}

				if ($include_column || $header !== $column) {
					$data[$header] = $value;
				}
			}

			$table[$id] = $data;
		}

		return $table;
	}

	/**
	 * Index table rows by column text.
	 *
	 * @param mixed $column    column name or index @see CTableRowElement::getColumn
	 *
	 * @return CElementCollection
	 */
	public function indexRows($column) {
		$table = [];
		foreach ($this->getRows() as $row) {
			$table[$row->getColumn($column)->getText()] = $row;
		}

		return new CElementCollection($table, 'CTableRowElement');
	}
}
