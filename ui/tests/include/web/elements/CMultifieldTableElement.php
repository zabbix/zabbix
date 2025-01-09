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

require_once dirname(__FILE__).'/../CElement.php';

use \Facebook\WebDriver\Exception\UnrecognizedExceptionException;
use \Facebook\WebDriver\Exception\ElementNotInteractableException;

/**
 * Multifield table element.
 */
class CMultifieldTableElement extends CTableElement {

	/**
	 * Element selectors.
	 *
	 * @var array
	 */
	protected $selectors = [
		'header' => 'xpath:./thead/tr/th',
		'row' => 'xpath:./tbody/tr[contains(@class, "form_row") or contains(@class, "pairRow") or contains(@class, "editable_table_row")]',
		'column' => 'xpath:./td'
	];

	/**
	 * Field mapping.
	 *
	 * @var array
	 */
	protected $mapping;

	/**
	 * Field mapping names.
	 *
	 * @var array
	 */
	protected $names;

	/**
	 * Field mapping aliases.
	 *
	 * @var array
	 */
	protected $aliases = [];

	/**
	 * Get field mapping.
	 *
	 * @return array
	 */
	public function getFieldMapping() {
		return is_array($this->mapping) ? $this->mapping : [];
	}

	/**
	 * Set field mapping names.
	 *
	 * @param array $names	field name
	 *
	 * @return CMultifieldTableElement
	 */
	public function setFieldNames($names) {
		if (!is_array($this->names)) {
			$this->names = [];
		}

		foreach ($names as $field => $name) {
			$this->names[$field] = $name;
		}

		return $this;
	}

	/**
	 * Set field mapping.
	 * Field mapping is used to address controls within multifield table row.
	 *
	 * For example, if there is a three control row like this:
	 * [ tag         ] [Contains|Equals] [ value           ]
	 *
	 * The following mappings can be used:
	 *     1. ['tag', 'operator', 'value']
	 *        This will set names for the fields, but controls will be detected automatically (slow).
	 *     2. [['name' => 'tag'], ['name' => 'operator'], ['name' => 'value']]
	 *        This is the same mapping as was described in #1.
	 *     3. [
	 *            ['name' => 'tag', 'class' => 'CElement'],
	 *            ['name' => 'operator', 'class' => 'CSegmentedRadioElement'],
	 *            ['name' => 'value', 'class' => 'CElement']
	 *        ]
	 *        This will set names and expected control types for the fields (CElement is generic input).
	 *     4. [
	 *            ['name' => 'tag', 'selector' => 'xpath:./input', 'class' => 'CElement'],
	 *            ['name' => 'operator', 'selector' => 'class:radio-list-control', 'class' => 'CSegmentedRadioElement'],
	 *            ['name' => 'value', 'selector' => 'xpath:./input', 'class' => 'CElement']
	 *        ]
	 *        This will set names, selectors and expected control types for the fields.
	 *
	 * Field mapping indices should match indices of columns in table row. For example, for sortable table rows, there
	 * is an additional column with sortable controls (first column in this example):
	 * [::] [ field ] [ value ]
	 *
	 * When defining a mapping, sortable column could be skipped by specifying the indices:
	 * [1 => 'field', 2 => 'value']
	 * Or it could be set to null:
	 * [null, 'field', 'value']
	 *
	 * For tables with headings, mapping keys should match headings and not indices. For example, mapping for table:
	 * Name               Value
	 * [ tag            ] [ value             ]
	 * Should be defined as follows (array keys match table headers):
	 * ['Name' => 'tag', 'Value' => 'value']
	 *
	 * In case if selectors need to be specified for tables with headings, mapping keys also should match table header:
	 * ['Name' => ['selector' => 'xpath:./input[@id="name"]'], 'Value' => ['selector' => 'xpath:./input[@id="value"]']]
	 *
	 * Be advised that when mapping is not set, multifield operations are slower and fields are indexed by indices (for
	 * tables without headers) or by header text (for tables with headers).
	 *
	 * @param array $mapping    field mapping
	 */
	public function setFieldMapping($mapping) {
		$this->mapping = $mapping;

		return $this;
	}

	/**
	 * Detect field mapping based on the first row elements.
	 *
	 * @param array    $headers    table headers
	 *
	 * @return array
	 */
	public function detectFieldMapping($headers = null) {
		$rows = $this->getRows();

		if ($rows->count() === 0) {
			throw new \Exception('Failed to detect mapping for an empty multifield table.');
		}

		if ($headers === null) {
			$headers = $this->getHeadersText();
		}

		$result = [];
		foreach ($rows->first()->query($this->selectors['column'])->all() as $i => $column) {
			$label = CTestArrayHelper::get($headers, $i, $i);
			$element = CElementQuery::getInputElement($column, '.')->detect();

			if (!$element->isValid()) {
				$result[$label] = null;

				continue;
			}

			$value = $element->getAttribute('name');
			if ($value !== null) {
				$element->query('xpath', './/*[@name]')->one(false);
				if ($element->isValid()) {
					$value = $element->getAttribute('name');
				}
			}

			if ($value !== null) {
				$name = $value;
				if (substr($value, -1) === ']') {
					$pos = strrpos($value, '[');
					if ($pos !== false) {
						$name = substr($value, $pos + 1, -1);
						if (is_numeric($name)) {
							$value = substr($value, 0, $pos);
							$pos = strrpos($value, '[');

							if ($pos !== false) {
								$name = substr($value, $pos + 1, -1);
							}
						}
					}

					if (!$name || is_numeric($name)) {
						$name = $label;
					}
				}
			}
			else {
				// Element name cannot be detected, using label or index.
				$name = $label;
			}

			$aliases = [];
			if ($name !== $label) {
				$aliases[] = $label;
			}
			$aliases[] = $i;

			if (!empty($this->names) && array_key_exists($name, $this->names)) {
				$aliases[] = $name;
				$name = $this->names[$name];
			}

			foreach ($aliases as $alias) {
				$this->aliases[$alias] = $name;
			}

			$result[$label] = [
				'name' => $name,
				'class' => get_class($element),
				'selector' => CElementQuery::getLastSelector()
			];
		}

		return $result;
	}

	/**
	 * Get controls from row.
	 *
	 * @param CTableRowElement $row        table row
	 * @param array            $headers    table headers
	 *
	 * @return array
	 */
	public function getRowControls($row, $headers = null) {
		$controls = [];

		if ($headers === null) {
			$headers = $this->getHeadersText();
		}

		if ($this->mapping === null) {
			$this->mapping = $this->detectFieldMapping();
		}

		foreach ($row->query($this->selectors['column'].'|./th')->all() as $i => $column) {
			$label = CTestArrayHelper::get($headers, $i, $i);
			$mapping = CTestArrayHelper::get($this->mapping, $label, $label);
			if ($mapping === null) {
				continue;
			}

			if (!is_array($mapping)) {
				$mapping = ['name' => $mapping];
			}
			elseif (!array_key_exists('name', $mapping)) {
				$mapping['name'] = $label;
			}

			if (array_key_exists('selector', $mapping)) {
				$control = $column->query($mapping['selector'])
						->cast(CTestArrayHelper::get($mapping, 'class', 'CElement'))
						->one(false);
			}
			else {
				$control = (!is_array($this->mapping) || array_key_exists($label, $this->mapping))
						? CElementQuery::getInputElement($column, '.', CTestArrayHelper::get($mapping, 'class'))
						: new CNullElement();
			}

			if (!$control->isValid()) {
				continue;
			}

			$controls[$mapping['name']] = $control;
		}

		return $controls;
	}

	/**
	 * Get values from all the rows.
	 *
	 * @return array
	 */
	public function getValue() {
		$data = [];
		$headers = $this->getHeadersText();

		foreach ($this->getRows() as $row) {
			$values = [];

			foreach ($this->getRowControls($row, $headers) as $name => $control) {
				$values[$name] = $control->getValue();
			}

			$data[] = $values;
		}

		return $data;
	}

	/**
	 * Get values from a specific row.
	 *
	 * @param integer $index     row index
	 *
	 * @return array
	 */
	public function getRowValue($index) {
		$value = [];

		foreach ($this->getRowControls($this->getRow($index)) as $name => $control) {
			$value[$name] = $control->getValue();
		}

		return $value;
	}

	/**
	 * Add new row.
	 *
	 * @param array $values    row values
	 *
	 * return $this
	 */
	public function addRow($values) {
		$rows = $this->getRows()->count();
		$this->query('button:Add')->one()->click();

		// Wait until new table row appears.
		$this->query('xpath:.//'.CXPathHelper::fromSelector($this->selectors['row']).'['.($rows + 1).']')->waitUntilPresent();
		return $this->updateRow($rows, $values);
	}

	/**
	 * Update row by index.
	 *
	 * @param integer $index     row index
	 * @param array   $values    row values
	 *
	 * @throws Exception    if not all fields could be found within a row
	 *
	 * return $this
	 */
	public function updateRow($index, $values) {
		$controls = $this->getRowControls($this->getRow($index));
		foreach ($values as $name => $value) {
			$field = (array_key_exists($name, $this->aliases)) ? $this->aliases[$name] : $name;

			if (array_key_exists($field, $controls)) {
				try {
					$controls[$field]->fill($value);
				}
				catch (\Exception $e1) {
					if (!($e1 instanceof UnrecognizedExceptionException)
							&& !($e1 instanceof ElementNotInteractableException)) {
						throw $e1;
					}

					try {
						$controls = $this->getRowControls($this->getRow($index));
						$controls[$field]->fill($value);
					}
					catch (\Exception $e2) {
						throw $e1;
					}
				}
				unset($values[$name]);
			}
		}

		if ($values) {
			throw new Exception('Failed to set values for fields ['.implode(', ', array_keys($values)).'] when filling'.
					' multifield row (controls are not present for those fields).'
			);
		}

		return $this;
	}

	/**
	 * Remove row by index.
	 *
	 * @param array $index    row index
	 *
	 * return $this
	 */
	public function removeRow($index) {
		$row = $this->getRow($index);
		$row->query('button:Remove')->one()->click();
		$row->waitUntilNotPresent();

		return $this;
	}

	/**
	 * Remove all rows.
	 *
	 * return $this
	 */
	public function clear() {
		foreach(array_reverse($this->getRows()->asArray()) as $row) {
			$row->query('button:Remove')->one()->click();
		}

		$this->query($this->selectors['row'])->waitUntilNotPresent();

		return $this;
	}

	/**
	 * Find row indexes by row data.
	 *
	 * @param array $fields     row fields
	 *
	 * @return array
	 */
	protected function findRowsByFields($fields) {
		$indices = [];

		if (array_key_exists('index', $fields)) {
			return [$fields['index']];
		}

		foreach ($this->getValue() as $index => $values) {
			foreach ($fields as $name => $value) {
				$field = (array_key_exists($name, $this->aliases)) ? $this->aliases[$name] : $name;

				if (array_key_exists($field, $values) && $values[$field] === $value) {
					$indices[] = $index;
					break;
				}
			}
		}

		return $indices;
	}

	/**
	 * Fill table with specified data.
	 * For example, if there is a two control row, with mapping set to ['tag', 'value'], the following $data values
	 * can be used:
	 *     1. [
	 *            ['tag' => 'tag1', 'value' => '1'],
	 *            ['tag' => 'tag2', 'value' => '2'],
	 *            ['tag' => 'tag3', 'value' => '3']
	 *        ]
	 *        This will add three rows with values "tag1:1", "tag2:2" and "tag2:3".
	 *     2. [
	 *            ['tag' => 'tag4', 'value' => '4'],
	 *            ['action' => USER_ACTION_UPDATE, 'index' => 1, 'tag' => 'new tag2', 'value' => 'new 2'],
	 *            ['action' => USER_ACTION_REMOVE, 'index' => 2],
	 *            ['action' => USER_ACTION_REMOVE, 'tag' => 'tag1']
	 *        ]
	 *        This will add row "tag4:4", will update row with index 1 to "new tag2:new 2", will remove rows by index 2
	 *        and rows by tag name "tag1".
	 *
	 * @param array $data    data array to be set.
	 *
	 * @throws Exception
	 *
	 * @return $this
	 */
	public function fill($data) {
		if (CTestArrayHelper::isAssociative($data)) {
			$data = [$data];
		}

		// If the first row  already presents in multifield table no need to press Add.
		$rows = $this->getRows()->count();
		if (count($data) >= 1 && CTestArrayHelper::get($data[0], 'action') === null && $rows >= 1) {
			if ($this->mapping === null) {
				$this->mapping = $this->detectFieldMapping();
			}

			$fields = [];
			foreach ($this->mapping as $mapping) {
				if (!is_array($mapping) || !array_key_exists('name', $mapping) || !array_key_exists('class', $mapping)) {
					continue;
				}

				$fields[$mapping['name']] = $mapping['class'];
			}

			$empty = true;
			$values = $this->getRowValue($rows - 1);

			foreach ($values as $key => $value) {
				// Elements with predefined values are always ignored.
				if (in_array(CTestArrayHelper::get($fields, $key), [CCheckboxElement::class, CRadioButtonList::class,
						CSegmentedRadioElement::class])) {
					continue;
				}

				if ($value !== '') {
					$empty = false;
					break;
				}
			}

			if ($empty) {
				$data[0]['action'] = USER_ACTION_UPDATE;
				$data[0]['index'] = $rows - 1;
			}
		}

		foreach ($data as $row) {
			$action = CTestArrayHelper::get($row, 'action', USER_ACTION_ADD);
			unset($row['action']);

			switch ($action) {
				case USER_ACTION_ADD:
					$this->addRow($row);
					break;

				case USER_ACTION_UPDATE:
					$indices = $this->findRowsByFields($row);
					unset($row['index']);

					foreach ($indices as $index) {
						$this->updateRow($index, $row);
					}

					break;

				case USER_ACTION_REMOVE:
					$indices = $this->findRowsByFields($row);
					sort($indices);

					foreach (array_reverse($indices) as $index) {
						$this->removeRow($index);
					}
					break;

				default:
					throw new Exception('Cannot perform action "'.$action.'".');
			}
		}

		return $this;
	}

	/*
	 * @inheritdoc
	 */
	public function checkValue($expected, $raise_exception = true) {
		$rows = $this->getRows();

		if ($rows->count() !== count($expected)) {
			if ($raise_exception) {
				throw new Exception('Row count "'.$rows->count().'" doesn\'t match expected row count "'.
						count($expected).'" of multifield element.'
				);
			}

			return false;
		}

		$headers = $this->getHeadersText();
		foreach ($rows as $id => $row) {
			if (!array_key_exists($id, $expected)) {
				if ($raise_exception) {
					throw new Exception('Row with index "'.$id.'" is not expected in multifield element.');
				}

				return false;
			}

			$controls = $this->getRowControls($row, $headers);
			foreach ($expected[$id] as $name => $value) {
				$field = (array_key_exists($name, $this->aliases)) ? $this->aliases[$name] : $name;

				if (!array_key_exists($field, $controls)) {
					if ($raise_exception) {
						throw new Exception('Expected field "'.$name.'" is not present in multifield element row.');
					}

					return false;
				}

				try {
					if (!$controls[$field]->checkValue($value, $raise_exception)) {
						return false;
					}
				}
				catch (Exception $exception) {
					if ($raise_exception) {
						CExceptionHelper::setMessage($exception, 'Multifield element value for field "'.$name.
								'['.$id.']" is invalid: '.$exception->getMessage()
						);
					}

					throw $exception;
				}
			}
		}

		return true;
	}
}
