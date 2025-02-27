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

require_once dirname(__FILE__).'/CBaseElement.php';
require_once dirname(__FILE__).'/CElementQuery.php';

require_once dirname(__FILE__).'/IWaitable.php';
require_once dirname(__FILE__).'/WaitableTrait.php';
require_once dirname(__FILE__).'/CastableTrait.php';

use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\Remote\RemoteWebElement;
use Facebook\WebDriver\WebDriverKeys;
use Facebook\WebDriver\Interactions\WebDriverActions;

/**
 * Generic web page element.
 */
class CElement extends CBaseElement implements IWaitable {

	/**
	 * Element can be used as waitable object to wait for element state changes.
	 */
	use WaitableTrait;

	/**
	 * Element can be used as castable object to cast element to specific type.
	 */
	use CastableTrait;

	/**
	 * Element selector.
	 *
	 * @var WebDriverBy
	 */
	protected $by;

	/**
	 * Parent element (if any).
	 * Parent element is never set for elements retrieved from element collection.
	 *
	 * @var CElement
	 */
	protected $parent;

	/**
	 * Flag that allows to disable normalizing.
	 *
	 * @var boolean
	 */
	protected $normalized = false;

	/**
	 * Initialize element.
	 *
	 * @param RemoteWebElement $element
	 * @param type $options
	 */
	public static function createInstance(RemoteWebElement $element, $options = []) {
		$instance = new static($element->executor, $element->id, $element->isW3cCompliant);
		$instance->setElement($element);

		foreach ($options as $key => $value) {
			$instance->$key = $value;
		}

		if (!$instance->normalized) {
			$instance->normalize();
		}

		return $instance;
	}

	/**
	 * Simplified selector for elements that can be located directly on page.
	 * @throws Exception
	 */
	public static function find() {
		throw new Exception('Element cannot be located without selector.');
	}

	/**
	 * Invalidate element state.
	 * This method should be overridden in order to reset cached objects that will be broken during reload operation.
	 * @see CBaseElement::reload
	 */
	public function invalidate() {
		// Code is not missing here.
	}

	/**
	 * Reload stalled element if reload is possible.
	 *
	 * @return $this
	 *
	 * @throws Exception
	 */
	public function reload() {
		if ($this->by === null) {
			throw new Exception('Cannot reload stalled element selected as a part of multi-element selection.');
		}

		if ($this->parent !== null && $this->parent->isStalled()) {
			$this->parent->reload();
		}

		$this->invalidate();
		$query = new CElementQuery($this->by);
		if ($this->parent !== null) {
			$query->setContext($this->parent);
		}

		$this->setElement($query->waitUntilPresent()->one());
		if (!$this->normalized) {
			$this->normalize();
		}

		return $this;
	}

	/**
	 * Set new base element.
	 *
	 * @param RemoteWebElement $element    element to be set
	 */
	protected function setElement(RemoteWebElement $element) {
		$this->executor = $element->executor;
		$this->id = $element->id;
		$this->fileDetector = $element->fileDetector;
		$this->isW3cCompliant = $element->isW3cCompliant;
	}

	/**
	 * Perform element selector normalization.
	 * This method can be overridden to check if element is selected properly.
	 */
	protected function normalize() {
		// Code is not missing here.
	}

	/**
	 * Get parent selection query.
	 *
	 * @param mixed  $type     selector type (method) or selector
	 * @param string $locator  locator part of selector
	 *
	 * @return CElementQuery
	 */
	public function parents($type = null, $locator = null) {
		$selector = 'xpath:./ancestor';
		if ($type !== null) {
			$selector .= '::'.CXPathHelper::fromSelector($type, $locator);
		}
		else {
			$selector .= '::*';
		}

		return $this->query($selector)->setReversedOrder();
	}

	/**
	 * Get children selection query.
	 *
	 * @param mixed  $type     selector type (method) or selector
	 * @param string $locator  locator part of selector
	 *
	 * @return CElementQuery
	 */
	public function children($type = null, $locator = null) {
		$selector = 'xpath:./*';
		if ($type !== null) {
			$selector = 'xpath:./'.CXPathHelper::fromSelector($type, $locator);
		}

		return $this->query($selector);
	}

	/**
	 * Get element query with current element context.
	 *
	 * @param mixed  $type     selector type (method) or selector
	 * @param string $locator  locator part of selector
	 *
	 * @return CElementQuery
	 */
	public function query($type, $locator = null) {
		$query = new CElementQuery($type, $locator);
		$query->setContext($this);

		return $query;
	}

	/**
	 * Dispatch HTML event to an element.
	 *
	 * @param string $event    event type
	 *
	 * @return $this
	 */
	public function fireEvent($event = 'change') {
		$driver = CElementQuery::getDriver();
		$driver->executeScript('arguments[0].dispatchEvent(new Event(arguments[1]));', [$this, $event]);

		return $this;
	}

	/**
	 * Highlight element by setting orange border around it.
	 * This method should be used for test debugging purposes only.
	 *
	 * @return $this
	 */
	public function highlight() {
		$driver = CElementQuery::getDriver();
		$driver->executeScript('arguments[0].style.border="3px solid #ff9800";', [$this]);

		return $this;
	}

	/**
	 * Take screenshot of the specific element.
	 *
	 * @return string
	 */
	public function takeScreenshot() {
		return CElementQuery::getPage()->takeScreenshot($this);
	}

	/**
	 * Get instance of specified element class from current element.
	 *
	 * @param string $class      class to be casted to
	 * @param array  $options    additional options passed to object
	 *
	 * @return CElement
	 */
	public function cast($class, $options = []) {
		return call_user_func([$class, 'createInstance'], $this, array_merge($options, [
			'parent' => $this->parent,
			'by' => $this->by
		]));
	}

	/**
	 * @inheritdoc
	 */
	public function getText() {
		if (!$this->isVisible()) {
			return CElementQuery::getDriver()->executeScript('return arguments[0].textContent;', [$this]);
		}

		return parent::getText();
	}

	/**
	 * Get element value.
	 *
	 * @return type
	 */
	public function getValue() {
		return CElementQuery::getDriver()->executeScript('return arguments[0].value;', [$this]);
	}

	/**
	 * @inheritdoc
	 */
	public function sendKeys($value) {
		if (is_string($value) && strpos($value, '"') === false) {
			parent::sendKeys($value);
		}
		else {
			CElementQuery::getDriver()->executeScript('arguments[0].value=arguments[1];arguments[0].focus();',
					[$this, $value]
			);
		}

		return $this;
	}

	/**
	 * Highlight the value in the field.
	 *
	 * @return $this
	 */
	public function selectValue() {
		CElementQuery::getDriver()->executeScript('arguments[0].focus();arguments[0].select();', [$this]);

		return $this;
	}

	/**
	 * Overwrite value in field.
	 *
	 * @param $text    text to be written into the field
	 *
	 * @return $this
	 */
	public function overwrite($text) {
		if ($text === '' || $text === null) {
			$text = WebDriverKeys::DELETE;
		}

		return $this->selectValue()->type($text);
	}

	/**
	 * Alias for overwrite.
	 * @see self::overwrite
	 *
	 * @param $text    text to be written into the field
	 *
	 * @return $this
	 */
	public function fill($text) {
		if (is_string($text) && preg_match('/[\x{10000}-\x{10FFFF}]/u', $text) === 1) {
			CElementQuery::getDriver()->executeScript('arguments[0].value = '.json_encode($text).';', [$this]);
		}
		else {
			return $this->overwrite($text);
		}
	}

	/**
	 * Remove element from the page.
	 */
	public function delete() {
		CElementQuery::getDriver()->executeScript('arguments[0].remove();', [$this]);
	}

	/**
	 * Get element rectangle.
	 *
	 * @return array
	 */
	public function getRect() {
		$location = $this->getLocation();
		$size = $this->getSize();

		return [
			'x' => $location->getX(),
			'y' => $location->getY(),
			'width' => $size->getWidth(),
			'height' => $size->getHeight()
		];
	}

	/**
	 * Hover over an element.
	 *
	 * @return $this
	 */
	public function hover() {
		return $this->fireEvent('mouseover');
	}

	/**
	 * Check if element is clickable.
	 *
	 * @return boolean
	 */
	public function isClickable() {
		return $this->isDisplayed() && $this->isEnabled();
	}

	/**
	 * @inheritdoc
	 */
	public function getClickableCondition() {
		$target = $this;

		return function () use ($target) {
			return $target->isClickable();
		};
	}

	/**
	 * Check if element is present.
	 *
	 * @return boolean
	 */
	public function isPresent() {
		return !$this->isStalled();
	}

	/**
	 * @inheritdoc
	 */
	public function getPresentCondition() {
		$target = $this;

		return function () use ($target) {
			return $target->isPresent();
		};
	}

	/**
	 * @inheritdoc
	 */
	public function getVisibleCondition() {
		$target = $this;

		return function () use ($target) {
			return $target->isVisible();
		};
	}

	/**
	 * @inheritdoc
	 */
	public function getSelectedCondition() {
		$target = $this;

		return function () use ($target) {
			return $target->isSelected();
		};
	}

	/**
	 * Check if text is present.
	 *
	 * @param string $text    text to be present
	 *
	 * @return boolean
	 */
	public function isTextPresent($text) {
		return (strpos($this->getText(), $text) !== false);
	}

	/**
	 * @inheritdoc
	 */
	public function getTextPresentCondition($text) {
		$target = $this;

		return function () use ($target, $text) {
			return $target->isTextPresent($text);
		};
	}

	/**
	 * Check presence of the attribute(s).
	 *
	 * @param string|array    $attributes attribute or attributes to be present.
	 *
	 * @return boolean
	 */
	public function isAttributePresent($attributes) {
		if (!is_array($attributes)) {
			$attributes = [$attributes];
		}

		foreach ($attributes as $key => $value) {
			if (is_numeric($key)) {
				if ($this->getAttribute($value) === null) {
					return false;
				}
			}
			elseif ($this->getAttribute($key) !== $value) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @inheritdoc
	 */
	public function getAttributesPresentCondition($attributes) {
		$target = $this;

		return function () use ($target, $attributes) {
			return $target->isAttributePresent($attributes);
		};
	}

	/**
	 * @inheritdoc
	 */
	public function getClassesPresentCondition($classes) {
		$target = $this;

		return function () use ($target, $classes) {
			return $target->hasClass($classes);
		};
	}

	/**
	 * Check if element is ready.
	 *
	 * @return boolean
	 */
	public function isReady() {
		return call_user_func($this->getReadyCondition());
	}

	/**
	* @inheritdoc
	*/
	public function isEnabled($enabled = true) {
		$attribute = parent::getAttribute('class');
		$classes = ($attribute !== null) ? explode(' ', $attribute) : [];

		$is_enabled = parent::isEnabled()
				&& (parent::getAttribute('disabled') === null)
				&& (!array_intersect(['disabled', 'readonly'], $classes))
				&& (parent::getAttribute('readonly') === null);

		return $is_enabled === $enabled;
	}

	/**
	 * @inheritdoc
	 */
	public function getEnabledCondition() {
		$target = $this;

		return function () use ($target) {
			return $target->isEnabled();
		};
	}

	/**
	 * @inheritdoc
	 */
	public function click($force = false) {
		try {
			return parent::click();
		} catch (Exception $exception) {
			if (!$force) {
				throw $exception;
			}

			$this->forceClick();
		}

		return $this;
	}

	/**
	 * Force click on element.
	 *
	 * @return $this
	 */
	public function forceClick() {
		try {
			CElementQuery::getDriver()->executeScript('arguments[0].click();', [$this]);
		}
		catch (StaleElementReferenceException $exception) {
			if (!$this->reload_staled) {
				throw $exception;
			}

			$this->reload();
			CElementQuery::getDriver()->executeScript('arguments[0].click();', [$this]);
		}

		return $this;
	}

	/**
	 * Double-click on element.
	 *
	 * @return $this
	 */
	public function doubleClick() {
		$actions = new WebDriverActions(CElementQuery::getDriver());
		$actions->doubleClick($this)->perform();

		return $this;
	}

	/**
	 * @inheritdoc
	 */
	public function getReadyCondition() {
		return $this->getClickableCondition();
	}

	/**
	 * Wait until element changes it's state from stalled to normal.
	 *
	 * @param integer $timeout    timeout in seconds
	 *
	 * @return $this
	 * @throws Exception
	 */
	public function waitUntilReloaded($timeout = null) {
		if ($this->by === null) {
			throw new Exception('Cannot wait for element reload on element selected in multi-element query.');
		}

		$element = $this;
		$wait = forward_static_call_array([CElementQuery::class, 'wait'], $timeout !== null ? [$timeout] : []);
		$wait->until(function () use ($element) {
			try {
				if ($element->isStalled()) {
					$element->reload();

					return !$element->isStalled();
				}
			}
			catch (Exception $e) {
				// Code is not missing here.
			}

			return null;
		}, 'Failed to wait until element reloaded.');

		return $this;
	}

	/**
	 * Wait until element is selected.
	 *
	 * @param integer $timeout    timeout in seconds
	 *
	 * @return $this
	 */
	public function waitUntilSelected($timeout = null) {
		$wait = forward_static_call_array([CElementQuery::class, 'wait'], $timeout !== null ? [$timeout] : []);
		$wait->until(WebDriverExpectedCondition::elementToBeSelected($this));

		return $this;
	}

	/**
	 * Detect element by its tag or class.
	 *
	 * @param type $options
	 */
	public function detect($options = []) {

		$tag = $this->getTagName();
		if ($tag === 'textarea') {
			return $this->asElement($options);
		}

		if ($tag === 'select') {
			return $this->asList($options);
		}

		if ($tag === 'z-select') {
			return $this->asDropdown($options);
		}

		if ($tag === 'table') {
			return $this->asTable($options);
		}

		if ($tag === 'input') {
			$type = $this->getAttribute('type');
			if ($type === 'checkbox' || $type === 'radio') {
				return $this->asCheckbox($options);
			}
			else {
				return $this->asElement($options);
			}
		}

		$class = explode(' ', $this->getAttribute('class'));
		if (in_array('multiselect-control', $class)) {
			return $this->asMultiselect($options);
		}

		if (in_array('radio-list-control', $class)) {
			return $this->asSegmentedRadio($options);
		}

		if (in_array('checkbox-list', $class)) {
			return $this->asCheckboxList($options);
		}

		if (in_array('range-control', $class) || in_array('calendar-control', $class)) {
			return $this->asCompositeInput($options);
		}

		if (in_array('color-picker', $class)) {
			return $this->asColorPicker($options);
		}

		if (in_array('multilineinput-control', $class)) {
			return $this->asMultiline($options);
		}

		if (in_array('macro-input-group', $class)) {
			return $this->asInputGroup($options);
		}

		CTest::zbxAddWarning('No specific element was detected');

		return $this;
	}

	/**
	 * Throw error for not supported method invocation.
	 *
	 * @param string $method    method name
	 *
	 * @throws Exception
	 */
	public static function onNotSupportedMethod($method) {
		throw new Exception('Method "'.$method.'" is not supported by "'.static::class.'" class elements.');
	}

	/**
	 * Check element value.
	 *
	 * @param mixed $expected    expected value of the element
	 *
	 * @return boolean
	 *
	 * @throws Exception
	 */
	public function checkValue($expected, $raise_exception = true) {
		$value = $this->getValue();
		if ($value === null) {
			if ($raise_exception) {
				throw new Exception('Cannot get value of the non-interactable element.');
			}

			return false;
		}

		if (is_array($value)) {
			if (!is_array($expected)) {
				$expected = [$expected];
			}

			foreach (['value', 'expected'] as $var) {
				$values = [];
				foreach ($$var as $item) {
					$values[] = '"'.$item.'"';
				}

				sort($values);

				$$var = implode(', ', $values);
			}
		}

		if ($expected != $value && $raise_exception) {
			if (!is_scalar($value) || is_bool($value)) {
				$value = json_encode($value);
			}

			if (!is_scalar($expected) || is_bool($expected)) {
				$expected = json_encode($expected);
			}

			throw new Exception('Element value "'.$value.'" doesn\'t match expected "'.$expected.'".');
		}

		return ($expected == $value);
	}

	/**
	 * Remove focus from the element.
	 *
	 * @return $this
	 */
	public function removeFocus() {
		CElementQuery::getDriver()->executeScript('arguments[0].blur();', [$this]);

		return $this;
	}

	/**
	 * Scroll the element to the top position.
	 */
	public function scrollToTop() {
		CElementQuery::getDriver()->executeScript('arguments[0].scrollTo(0, 0)', [$this]);
	}

	/**
	 * Scroll the element to the visible position.
	 */
	public function scrollIntoView() {
		CElementQuery::getDriver()->executeScript('arguments[0].scrollIntoView({behavior:\'instant\',block:\'end\',inline:\'nearest\'});', [$this]);

		return $this;
	}

	/**
	 * Check presence of the class(es).
	 *
	 * @param string|array $class	class or classes to be present.
	 *
	 * @return boolean
	 */
	public function hasClass($class) {
		$attribute = parent::getAttribute('class');
		$classes = ($attribute !== null) ? explode(' ', $attribute) : [];

		if (!is_array($class)) {
			$class = [$class];
		}

		return (count(array_diff($class, $classes)) === 0);
	}

	/**
	 * Hover mouse over the element
	 */
	public function hoverMouse() {
		$mouse = CElementQuery::getDriver()->getMouse();
		$mouse->mouseMove($this->getCoordinates());

		return $this;
	}
}
