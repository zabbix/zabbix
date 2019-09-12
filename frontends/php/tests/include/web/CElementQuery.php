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

require_once dirname(__FILE__).'/CElement.php';
require_once dirname(__FILE__).'/CElementCollection.php';
require_once dirname(__FILE__).'/elements/CFormElement.php';
require_once dirname(__FILE__).'/elements/CTableElement.php';
require_once dirname(__FILE__).'/elements/CTableRowElement.php';
require_once dirname(__FILE__).'/elements/CWidgetElement.php';
require_once dirname(__FILE__).'/elements/CDashboardElement.php';
require_once dirname(__FILE__).'/elements/CDropdownElement.php';
require_once dirname(__FILE__).'/elements/CCheckboxElement.php';
require_once dirname(__FILE__).'/elements/COverlayDialogElement.php';
require_once dirname(__FILE__).'/elements/CMessageElement.php';
require_once dirname(__FILE__).'/elements/CMultiselectElement.php';
require_once dirname(__FILE__).'/elements/CSegmentedRadioElement.php';
require_once dirname(__FILE__).'/elements/CRangeControlElement.php';
require_once dirname(__FILE__).'/elements/CCheckboxListElement.php';
require_once dirname(__FILE__).'/elements/CMultifieldTableElement.php';
require_once dirname(__FILE__).'/elements/CMultilineElement.php';

require_once dirname(__FILE__).'/IWaitable.php';
require_once dirname(__FILE__).'/WaitableTrait.php';
require_once dirname(__FILE__).'/CastableTrait.php';

/**
 * Element selection query.
 */
class CElementQuery implements IWaitable {

	/**
	 * Query can be used as waitable object to wait for elements before retrieving them.
	 */
	use WaitableTrait;

	/**
	 * Query can be used as castable object to cast elements to specific type when elements are retrieved.
	 */
	use CastableTrait;

	/**
	 * Wait iteration step duration.
	 */
	const WAIT_ITERATION = 50;

	/**
	 * Possible wait conditions.
	 */
	const PRESENT = 'present';
	const TEXT_PRESENT = 'text_present';
	const ATTRIBUTES_PRESENT = 'attributes_present';
	const VISIBLE = 'visible';
	const CLICKABLE = 'clickable';
	const READY = 'ready';
	const NOT_PRESENT = 'not present';
	const TEXT_NOT_PRESENT = 'text not present';
	const ATTRIBUTES_NOT_PRESENT = 'attributes_not_present';
	const NOT_VISIBLE = 'not visible';
	const NOT_CLICKABLE = 'not clickable';

	/**
	 * Element selector.
	 *
	 * @var WebDriverBy
	 */
	protected $by;

	/**
	 * Selector context (can be set to specific element or to global level).
	 *
	 * @var mixed
	 */
	protected $context;

	/**
	 * Class to be used to instantiate elements.
	 *
	 * @var string
	 */
	protected $class;

	/**
	 * Options applied to instantiated elements.
	 *
	 * @var array
	 */
	protected $options = [];

	/**
	 * Shared web page instance.
	 *
	 * @var CPage
	 */
	protected static $page;

	/**
	 * Initialize element query by specified selector.
	 *
	 * @param mixed  $type     selector type (method) or selector
	 * @param string $locator  locator part of selector
	 */
	public function __construct($type, $locator = null) {
		$this->class = 'CElement';
		$this->context = static::getDriver();

		if ($type !== null) {
			$this->by = static::getSelector($type, $locator);
		}
	}

	/**
	 * Get selector from type and locator.
	 *
	 * @param mixed  $type     selector type (method) or selector
	 * @param string $locator  locator part of selector
	 *
	 * @return WebDriverBy
	 */
	public static function getSelector($type, $locator = null) {
		if ($type instanceof WebDriverBy) {
			return $type;
		}

		if ($locator === null) {
			$parts = explode(':', $type, 2);
			if (count($parts) !== 2) {
				throw new Exception('Element selector "'.$type.'" is not well formatted.');
			}

			list($type, $locator) = $parts;
		}

		$mapping = [
			'css' => 'cssSelector',
			'class' => 'className',
			'tag' => 'tagName',
			'link' => 'linkText',
			'button' => function () use ($locator) {
				return WebDriverBy::xpath('.//button[contains(text(),'.CXPathHelper::escapeQuotes($locator).')]');
			}
		];

		if (array_key_exists($type, $mapping)) {
			if (is_callable($mapping[$type])) {
				return call_user_func($mapping[$type]);
			}
			else {
				$type = $mapping[$type];
			}
		}

		return call_user_func(['WebDriverBy', $type], $locator);
	}

	/**
	 * Set query context.
	 *
	 * @param CElement $context    context to be set
	 */
	public function setContext($context) {
		$this->context = $context;
	}

	/**
	 * Get query context.
	 *
	 * @return CElement
	 */
	public function getContext() {
		return $this->context;
	}

	/**
	 * Set web page instance.
	 *
	 * @param CPage $page    web page instance to be set
	 */
	public static function setPage($page) {
		self::$page = $page;
	}

	/**
	 * Get web driver instance.
	 *
	 * @return RemoteWebDriver
	 */
	public static function getDriver() {
		if (self::$page === null) {
			return null;
		}

		return self::$page->getDriver();
	}

	/**
	 * Get web page instance.
	 *
	 * @return CPage
	 */
	public static function getPage() {
		return self::$page;
	}

	/**
	 * Apply chained element query.
	 *
	 * @param mixed  $type     selector type (method) or selector
	 * @param string $locator  locator part of selector
	 *
	 * @return $this
	 */
	public function query($type, $locator = null) {
		$prefix = CXPathHelper::fromWebDriverBy($this->by);
		$suffix = CXPathHelper::fromSelector($type, $locator);
		$this->by = static::getSelector('xpath', './'.$prefix.'/'.$suffix);

		return $this;
	}

	/**
	 * Get wait instance.
	 *
	 * @return WebDriverWait
	 */
	public static function wait() {
		return static::getDriver()->wait(20, self::WAIT_ITERATION);
	}

	/**
	 * Get element condition callable name.
	 *
	 * @param string $condition    condition name
	 *
	 * @return array
	 */
	public static function getConditionCallable($condition) {
		$conditions = [
			static::READY => 'getReadyCondition',
			static::PRESENT => 'getPresentCondition',
			static::NOT_PRESENT => 'getNotPresentCondition',
			static::TEXT_PRESENT => 'getTextPresentCondition',
			static::TEXT_NOT_PRESENT => 'getTextNotPresentCondition',
			static::ATTRIBUTES_PRESENT => 'getAttributesPresentCondition',
			static::ATTRIBUTES_NOT_PRESENT => 'getAttributesNotPresentCondition',
			static::VISIBLE => 'getVisibleCondition',
			static::NOT_VISIBLE => 'getNotVisibleCondition',
			static::CLICKABLE => 'getClickableCondition',
			static::NOT_CLICKABLE => 'getNotClickableCondition'
		];

		if (!array_key_exists($condition, $conditions)) {
			throw new Exception('Cannot get element condition callable by name "'.$condition.'"!');
		}

		return $conditions[$condition];
	}

	/**
	 * Wait until condition is met for target.
	 *
	 * @param IWaitable $target       target for wait operation
	 * @param string    $condition    condition to be waited for
	 * @param array     $params       condition params
	 */
	public static function waitUntil($target, $condition, $params = []) {
		$selector = $target->getSelectorAsText();
		if ($selector !== null) {
			$selector = ' located by '.$selector;
		}

		$callable = call_user_func_array([$target, self::getConditionCallable($condition)], $params);
		self::wait()->until($callable, 'Failed to wait for element'.$selector.' to be '.$condition.'.');
	}

	/**
	 * Get one element located by specified query.
	 *
	 * @param boolean $should_exist    if method is allowed to return null as a result
	 *
	 * @return CElement
	 */
	public function one($should_exist = true) {
		$class = $this->class;
		$parent = ($this->context !== static::getDriver()) ? $this->context : null;

		try {
			$element = $this->context->findElement($this->by);
		}
		catch (NoSuchElementException $exception) {
			if (!$should_exist) {
				return null;
			}

			throw $exception;
		}

		return new $class($element, array_merge($this->options, ['parent' => $parent, 'by' => $this->by]));
	}

	/**
	 * Get all elements located by specified query.
	 *
	 * @return CElement
	 */
	public function all() {
		$class = $this->class;

		$elements = $this->context->findElements($this->by);

		if ($this->class !== 'RemoteWebElement') {
			foreach ($elements as &$element) {
				$element = new $class($element, $this->options);
			}
			unset($element);
		}

		return new CElementCollection($elements, $class);
	}

	/**
	 * Get count of elements located by specified query.
	 *
	 * @return integer
	 */
	public function count() {
		return $this->all()->count();
	}

	/**
	 * Set element class and options.
	 *
	 * @param string $class      class to be used to instantiate elements
	 * @param array  $options    additional options passed to object
	 *
	 * @return $this
	 */
	public function cast($class, $options = []) {
		$this->class = $class;
		$this->options = $options;

		return $this;
	}

	/**
	 * @inheritdoc
	 */
	public function getClickableCondition() {
		$target = $this;

		return function () use ($target) {
			return $target->one()->isClickable();
		};
	}

	/**
	 * @inheritdoc
	 */
	public function getReadyCondition() {
		$driver = static::getDriver();

		return function () use ($driver) {
			return $driver->executeScript('return document.readyState;') == 'complete';
		};
	}

	/**
	 * @inheritdoc
	 */
	public function getPresentCondition() {
		$target = $this;

		return function () use ($target) {
			return $target->one();
		};
	}

	/**
	 * @inheritdoc
	 */
	public function getTextPresentCondition($text) {
		$target = $this;

		return function () use ($target, $text) {
			return (strpos($target->one()->getText(), $text) !== false);
		};
	}

	/**
	 * @inheritdoc
	 */
	public function getAttributesPresentCondition($attributes) {
		$target = $this;

		return function () use ($target, $attributes) {
			$element = $target->one();

			foreach ($attributes as $key => $value) {
				if (is_numeric($key) && $element->getAttribute($value) === null) {
					return false;
				}
				elseif ($element->getAttribute($key) !== $value) {
					return false;
				}
			}

			return true;
		};
	}

	/**
	 * @inheritdoc
	 */
	public function getVisibleCondition() {
		$target = $this;

		return function () use ($target) {
			return $target->one()->isVisible();
		};
	}

	/**
	 * Get input element from container.
	 *
	 * @param CElement     $target    container element
	 * @param string       $prefix    xpath prefix
	 * @param array|string $class     element classes to look for
	 *
	 * @return CElement|null
	 */
	public static function getInputElement($target, $prefix = './', $class = null) {
		$classes = [
			'CElement'					=> [
				'/input[@name][not(@type) or @type="text" or @type="password"]',
				'/textarea[@name]'
			],
			'CDropdownElement'			=> '/select[@name]',
			'CCheckboxElement'			=> '/input[@name][@type="checkbox" or @type="radio"]',
			'CMultiselectElement'		=> [
				'/div[@class="multiselect-control"]',
				'/div/div[@class="multiselect-control"]' // TODO: remove after fix DEV-1071.
			],
			'CSegmentedRadioElement'	=> [
				'/ul[@class="radio-list-control"]',
				'/div/ul[@class="radio-list-control"]' // TODO: remove after fix DEV-1071.
			],
			'CCheckboxListElement'		=> '/ul[@class="checkbox-list col-3"]',
			'CTableElement'				=> [
				'/table',
				'/*[@class="table-forms-separator"]/table'
			],
			'CRangeControlElement'		=> '/div[@class="range-control"]',
			'CMultilineElement'			=> '/div[@class="multilineinput-control"]'
		];

		if ($class !== null) {
			if (!is_array($class)) {
				$class = [$class];
			}

			foreach (array_keys($classes) as $name) {
				if (!in_array($name, $class)) {
					unset($classes[$name]);
				}
			}
		}

		foreach ($classes as $class => $selectors) {
			if (!is_array($selectors)) {
				$selectors = [$selectors];
			}

			$xpaths = [];
			foreach ($selectors as $selector) {
				$xpaths[] = $prefix.$selector;
			}

			if (($element = $target->query('xpath', implode('|', $xpaths))->cast($class)->one(false)) !== null) {
				return $element;
			}
		}

		return null;
	}
}
