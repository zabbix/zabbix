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

require_once 'vendor/autoload.php';

require_once dirname(__FILE__).'/CElementQuery.php';

/**
 * Element collection holds elements retrieved and allows to perform actions on those elements.
 */
class CElementCollection implements Iterator {

	/**
	 * All elements from element collection can be casted to specified element type.
	 */
	use CastableTrait;

	/**
	 * Element array.
	 *
	 * @var array
	 */
	private $elements = [];

	/**
	 * List of available element methods.
	 *
	 * @var string
	 */
	private $element_class;

	/**
	 * Initialize element collection with specified array of elements.
	 *
	 * @param array $elements
	 */
	public function __construct($elements, $class = 'CElement') {
		if (is_array($elements)) {
			$this->elements = $elements;
			$this->element_class = $class;
		}
	}

	/**
	 * @inheritdoc
	 */
	public function rewind(): void {
		reset($this->elements);
	}

	/**
	 * @inheritdoc
	 */
	#[\ReturnTypeWillChange]
	public function current() {
		return current($this->elements);
	}

	/**
	 * @inheritdoc
	 */
	#[\ReturnTypeWillChange]
	public function key() {
		return key($this->elements);
	}

	/**
	 * @inheritdoc
	 */
	public function next(): void {
		next($this->elements);
	}

	/**
	 * @inheritdoc
	 */
	public function valid(): bool {
		$key = $this->key();

		return ($key !== null && $key !== false);
	}

	/**
	 * Get element count.
	 *
	 * @return integer
	 */
	public function count(): int {
		return count($this->elements);
	}

	/**
	 * Check if element collection is empty.
	 *
	 * @return boolean
	 */
	public function isEmpty(): bool {
		return ($this->elements === []);
	}

	/**
	 * Get first element from collection.
	 *
	 * @return CElement
	 *
	 * @throws Exception
	 */
	public function first(): CElement {
		$element = reset($this->elements);

		if ($element === false) {
			throw new Exception('Cannot get first element of empty element set.');
		}

		return $element;
	}

	/**
	 * Get last element from collection.
	 *
	 * @return CElement
	 *
	 * @throws Exception
	 */
	public function last(): CElement {
		$element = end($this->elements);

		if ($element === false) {
			throw new Exception('Cannot get last element of empty element set.');
		}

		return $element;
	}

	/**
	 * Check if specific element array key is present in collection.
	 *
	 * @param mixed $key    array key
	 *
	 * @return boolean
	 */
	public function exists($key): bool {
		return array_key_exists($key, $this->elements);
	}

	/**
	 * Get element by key.
	 *
	 * @param mixed $key    array key
	 *
	 * @return CElement
	 */
	public function get($key): CElement {
		return $this->elements[$key];
	}

	/**
	 * Set element by key.
	 *
	 * @param mixed $key        array key
	 * @param mixed $element    element to be set
	 */
	public function set($key, $element): void {
		$this->elements[$key] = $element;
	}

	/**
	 * Perform action on all array elements.
	 *
	 * @param string $method    method name
	 * @param array  $params    params to be passed to specified method
	 *
	 * @return $this
	 */
	protected function each($method, $params = []) {
		foreach ($this->elements as $element) {
			call_user_func_array([$element, $method], $params);
		}

		return $this;
	}

	/**
	 * Element specific action @see CElement::type.
	 *
	 * @return $this
	 */
	public function type($text) {
		return $this->each(__FUNCTION__, [$text]);
	}

	/**
	 * Element specific action @see CElement::fireEvent.
	 *
	 * @return $this
	 */
	public function fireEvent($event = 'change') {
		return $this->each(__FUNCTION__, [$event]);
	}

	/**
	 * Element specific action @see CElement::highlight.
	 *
	 * @return $this
	 */
	public function highlight() {
		return $this->each(__FUNCTION__);
	}

	/**
	 * Element specific action @see CElement::clear.
	 *
	 * @return $this
	 */
	public function clear() {
		return $this->each(__FUNCTION__);
	}

	/**
	 * Element specific action @see CElement::click.
	 *
	 * @return $this
	 */
	public function click() {
		return $this->each(__FUNCTION__);
	}

	/**
	 * Element specific action @see CElement::submit.
	 *
	 * @return $this
	 */
	public function submit() {
		return $this->each(__FUNCTION__);
	}

	/**
	 * Element specific action @see CDropdownElement::select | CTableRowElement::select.
	 *
	 * @return $this
	 */
	public function select() {
		return $this->each(__FUNCTION__);
	}

	/**
	 * Cast all collection elements to specified class.
	 *
	 * @param string $class      class to be casted to
	 * @param array  $options    additional options passed to object
	 *
	 * @return $this
	 */
	public function cast($class, $options = []) {
		foreach ($this->elements as &$element) {
			$element = $element->cast($class, $options);
		}

		$this->element_class = $class;
		return $this;
	}

	/**
	 * Get text of elements as array of strings.
	 *
	 * @return array
	 */
	public function asText() {
		$text = [];

		foreach ($this->elements as $key => $element) {
			$text[$key] = $element->getText();
		}

		return $text;
	}

	/**
	 * Get values of elements as array.
	 *
	 * @return array
	 */
	public function asValues() {
		$values = [];

		foreach ($this->elements as $key => $element) {
			$values[$key] = $element->getValue();
		}

		return $values;
	}

	/**
	 * Get elements as array.
	 *
	 * @return array
	 */
	public function asArray() {
		return $this->elements;
	}

	/**
	 * Get sliced collection of elements.
	 *
	 * @return CElementCollection
	 */
	public function slice($offset, $length = null) {
		return new CElementCollection(array_slice($this->elements, $offset, $length), $this->element_class);
	}

	/**
	 * Filter element collection based on a specified condition and parameters.
	 *
	 * @param CElementFilter $filter    condition to be filtered by
	 *
	 * @return CElementCollection
	 * @throws Exception
	 */
	public function filter($filter) {
		$elements = [];
		foreach ($this->elements as $key => $element) {
			if ($filter->match($element)) {
				$elements[$key] = $element;
			}
		}

		return new CElementCollection($elements, $this->element_class);
	}

	/**
	 * Call methods specific for custom element types.
	 *
	 * @param string $name         method name
	 * @param array  $arguments    method arguments
	 *
	 * @return $this
	 *
	 * @throws Exception
	 */
	public function __call($name, $arguments) {
		$class = new ReflectionClass($this->element_class);

		if (!$class->hasMethod($name)) {
			throw new Exception('Cannot call method "'.$name.'" on collection of "'.$this->element_class.'" elements.');
		}

		return $this->each($name, $arguments);
	}

	/**
	 * Index elements by attribute values.
	 *
	 * @param string $name    attribute name
	 *
	 * @return CElementCollection
	 */
	public function indexByAttribute($name) {
		$elements = [];

		foreach ($this->elements as $element) {
			$key = $element->getAttribute($name);

			if ($key === null) {
				throw new Exception('Attribute "'.$name.'" is not present for all collection elements.');
			}

			if (array_key_exists($key, $elements)) {
				CTest::zbxAddWarning('Element attribute "'.$name.'" values are not unique in element collection.');
			}

			$elements[$key] = $element;
		}

		return new CElementCollection($elements, $this->element_class);
	}
}
