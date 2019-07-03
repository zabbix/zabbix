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

require_once 'vendor/autoload.php';

require_once dirname(__FILE__).'/../CElement.php';

/**
 * Table element.
 */
class CTableElement extends CElement {

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
			$this->headers = $this->query('xpath:./thead/tr/th')->all();
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
	 * Get collection of table rows.
	 *
	 * @return CElementCollection
	 */
	public function getRows() {
		return $this->query('xpath:./tbody/tr')->asTableRow(['parent' => $this])->all();
	}

	/**
	 * Get table row by index.
	 *
	 * @param $index    row index
	 *
	 * @return CTableRow
	 */
	public function getRow($index) {
		return $this->query('xpath:./tbody/tr['.((int)$index + 1).']')->asTableRow(['parent' => $this])->one();
	}

	/**
	 * Get indexed collections of table columns.
	 *
	 * @return array
	 */
	public function getCells() {
		$headers = $this->getHeadersText();

		$table = [];
		foreach ($this->getRows() as $row) {
			$data = [];

			foreach ($row->query('xpath:./td|./th')->all() as $i => $column) {
				$data[CTestArrayHelper::get($headers, $i, $i)] = $column;
			}

			$table[] = new CElementCollection($data);
		}

		return $table;
	}

	/**
	 * Find row by column value.
	 *
	 * @param string $column    column name
	 * @param string $value     column value
	 *
	 * @return CTableRow|null
	 */
	public function findRow($column, $value) {
		$headers = $this->getHeadersText();

		if (is_string($column)) {
			$column = array_search($column, $headers);
			if ($column === false) {
				return null;
			}

			$column++;
		}

		$selector = 'xpath:.//tbody/tr/td['.$column.'][string()='.CXPathHelper::escapeQuotes($value).']/..';
		return $this->query($selector)->asTableRow(['parent' => $this])->one(false);
	}

	/**
	 * Find row by column value.
	 *
	 * @param array $content    column data
	 *
	 * @return CTableRow|null
	 */
	public function findRows($content) {
		$rows = [];

		if (CTestArrayHelper::isAssociative($content)) {
			$content = [$content];
		}

		foreach ($this->getRows() as $row) {
			foreach ($content as $columns) {
				$found = true;

				foreach ($columns as $name => $value) {
					if (CTestArrayHelper::get($value, 'text', $value) !== $row->getColumnData($name, $value)) {
						$found = false;
						break;
					}
				}

				if ($found) {
					$rows[] = $row;
					break;
				}
			}
		}

		return new CElementCollection($rows, CTableRowElement::class);
	}

	/**
	 * Index table row text by values of table column.
	 *
	 * @param string $column	column name
	 *
	 * @return array
	 */
	public function index($column = null) {
		$table = [];
		foreach ($this->getCells() as $i => $row) {
			$data = [];
			$id = $i;

			foreach ($row as $header => $element) {
				$data[$header] = $element->getText();

				if ($header === $column) {
					$id = $data[$header];
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
