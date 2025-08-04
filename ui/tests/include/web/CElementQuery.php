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

require_once __DIR__.'/CElement.php';
require_once __DIR__.'/CElementCollection.php';
require_once __DIR__.'/CElementFilter.php';
require_once __DIR__.'/elements/CNullElement.php';
require_once __DIR__.'/elements/CFormElement.php';
require_once __DIR__.'/elements/CGridFormElement.php';
require_once __DIR__.'/elements/CCheckboxFormElement.php';
require_once __DIR__.'/elements/CTableElement.php';
require_once __DIR__.'/elements/CTableRowElement.php';
require_once __DIR__.'/elements/CWidgetElement.php';
require_once __DIR__.'/elements/CDashboardElement.php';
require_once __DIR__.'/elements/CListElement.php';
require_once __DIR__.'/elements/CDropdownElement.php';
require_once __DIR__.'/elements/CCheckboxElement.php';
require_once __DIR__.'/elements/COverlayDialogElement.php';
require_once __DIR__.'/elements/CMainMenuElement.php';
require_once __DIR__.'/elements/CMessageElement.php';
require_once __DIR__.'/elements/CMultiselectElement.php';
require_once __DIR__.'/elements/CSegmentedRadioElement.php';
require_once __DIR__.'/elements/CCheckboxListElement.php';
require_once __DIR__.'/elements/CMultifieldTableElement.php';
require_once __DIR__.'/elements/CMultilineElement.php';
require_once __DIR__.'/elements/CColorPickerElement.php';
require_once __DIR__.'/elements/CCompositeInputElement.php';
require_once __DIR__.'/elements/CPopupMenuElement.php';
require_once __DIR__.'/elements/CPopupButtonElement.php';
require_once __DIR__.'/elements/CInputGroupElement.php';
require_once __DIR__.'/elements/CHostInterfaceElement.php';
require_once __DIR__.'/elements/CFilterElement.php';
require_once __DIR__.'/elements/CFieldsetElement.php';

require_once __DIR__.'/IWaitable.php';
require_once __DIR__.'/WaitableTrait.php';
require_once __DIR__.'/CastableTrait.php';

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\Exception\NoSuchElementException;
use Facebook\WebDriver\Exception\WebDriverException;

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
	 * Timeout in seconds.
	 */
	const WAIT_TIMEOUT = 20;

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
	 * Element reverse order flag.
	 *
	 * @var boolean
	 */
	protected $reverse_order = false;

	/**
	 * Shared web page instance.
	 *
	 * @var CPage
	 */
	protected static $page;

	/**
	 * Last input element selector.
	 *
	 * @var string
	 */
	protected static $selector;

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
			if (!is_array($type)) {
				if ($type !== 'button') {
					$parts = explode(':', $type, 2);
					if (count($parts) !== 2) {
						throw new Exception('Element selector "'.$type.'" is not well formatted.');
					}

					list($type, $locator) = $parts;
				}
			}
			else {
				$selectors = [];
				foreach ($type as $selector) {
					$selectors[] = './/'.CXPathHelper::fromSelector($selector);
				}

				$type = 'xpath';
				$locator = implode('|', $selectors);
			}
		}
		else if (is_array($locator)) {
			foreach ($locator as $selector) {
				$selectors[] = './/'.CXPathHelper::fromSelector($type, $selector);
			}

			$type = 'xpath';
			$locator = implode('|', $selectors);
		}

		$mapping = [
			'css' => 'cssSelector',
			'class' => 'className',
			'tag' => 'tagName',
			'link' => 'linkText',
			'button' => function () use ($locator) {
				if ($locator === null) {
					return WebDriverBy::tagName('button');
				}

				return WebDriverBy::xpath('.//button[normalize-space(text())='.CXPathHelper::escapeQuotes($locator).']');
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

		return call_user_func([WebDriverBy::class, $type], $locator);
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
	 * Get last selector.
	 *
	 * @return string|null
	 */
	public static function getLastSelector() {
		return static::$selector;
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
	 * Set reversed element order flag.
	 *
	 * @param boolean $order    order to set
	 *
	 * @return $this
	 */
	public function setReversedOrder($order = true) {
		$this->reverse_order = $order;

		return $this;
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
		$prefix = ($this->by->getMechanism() !== 'xpath')
			? './/'.CXPathHelper::fromWebDriverBy($this->by)
			: $this->by->getValue();

		if ($this->reverse_order) {
			$prefix .= '[1]';
			$this->reverse_order = false;
		}

		$by = self::getSelector($type, $locator);
		$suffix = ($by->getMechanism() !== 'xpath')
				? '//'.CXPathHelper::fromWebDriverBy($by)
				: $by->getValue();

		if (substr($suffix, 0, 1) !== '/') {
			$suffix = '/'.$suffix;
		}

		$this->by = static::getSelector('xpath', $prefix.$suffix);

		return $this;
	}

	/**
	 * Get wait instance.
	 *
	 * @return WebDriverWait
	 */
	public static function wait($timeout = null, $iteration = null) {
		if ($iteration === null) {
			$iteration = self::WAIT_ITERATION;
		}
		if ($timeout === null) {
			$timeout = self::WAIT_TIMEOUT;
		}

		return static::getDriver()->wait($timeout, $iteration);
	}

	/**
	 * Wait until condition is met for target.
	 *
	 * @param IWaitable $target       target for wait operation
	 * @param string    $condition    condition to be waited for
	 * @param array     $params       condition params
	 * @param integer   $timeout	  timeout in seconds
	 */
	public static function waitUntil($target, $condition, $params = [], $timeout = null) {
		$selector = $target->getSelectorAsText();
		if ($selector !== null) {
			$selector = ' located by '.$selector;
		}

		$callable = call_user_func_array([$target, CElementFilter::getConditionCallable($condition)], $params);
		self::wait($timeout)->until($callable, 'Failed to wait for element'.$selector.' to be '.$condition.'.');
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

		for ($i = 0; $i < 2; $i++) {
			try {
				if (!$this->reverse_order) {
					$element = $this->context->findElement($this->by);
				}
				else {
					$elements = $this->context->findElements($this->by);
					if (!$elements) {
						throw new NoSuchElementException('No such element.');
					}

					$element = end($elements);
				}

				break;
			}
			catch (NoSuchElementException $exception) {
				if (!$should_exist) {
					return new CNullElement(array_merge($this->options, ['parent' => $parent, 'by' => $this->by]));
				}

				throw $exception;
			}
		}

		return call_user_func([$class, 'createInstance'], $element, array_merge($this->options, [
			'parent' => $parent,
			'by' => $this->by
		]));
	}

	/**
	 * Get all elements located by specified query.
	 *
	 * @return CElement
	 */
	public function all() {
		$class = $this->class;

		$elements = $this->context->findElements($this->by);

		if ($this->reverse_order) {
			$elements = array_reverse($elements);
		}

		if ($this->class !== 'RemoteWebElement') {
			foreach ($elements as &$element) {
				$element = call_user_func([$class, 'createInstance'], $element, $this->options);
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
			return $target->one(false)->isClickable();
		};
	}

	/**
	 * @inheritdoc
	 */
	public function getEnabledCondition() {
		$target = $this;

		return function () use ($target) {
			return $target->one(false)->isEnabled();
		};
	}

	/**
	 * @inheritdoc
	 */
	public function getReadyCondition() {
		$driver = static::getDriver();

		return function () use ($driver) {
			return $driver->executeScript('return document.readyState === \'complete\' && (window.jQuery||{active:0}).active === 0;');
		};
	}

	/**
	 * @inheritdoc
	 */
	public function getPresentCondition() {
		$target = $this;

		return function () use ($target) {
			return $target->one(false)->isValid();
		};
	}

	/**
	 * @inheritdoc
	 */
	public function getTextPresentCondition($text) {
		$target = $this;

		return function () use ($target, $text) {
			$element = $target->one(false);
			if (!$element->isValid()) {
				return false;
			}

			return (strpos($element->getText(), $text) !== false);
		};
	}

	/**
	 * @inheritdoc
	 */
	public function getAttributesPresentCondition($attributes) {
		$target = $this;

		return function () use ($target, $attributes) {
			$element = $target->one(false);
			if (!$element->isValid() || !$element->isAttributePresent($attributes)) {
				return false;
			}

			return true;
		};
	}

	/**
	 * @inheritdoc
	 */
	public function getClassesPresentCondition($classes) {
		$target = $this;

		return function () use ($target, $classes) {
			return $target->one(false)->hasClass($classes);
		};
	}

	/**
	 * @inheritdoc
	 */
	public function getVisibleCondition() {
		$target = $this;

		return function () use ($target) {
			return $target->one(false)->isVisible();
		};
	}

	/**
	 * @inheritdoc
	 */
	public function getSelectedCondition() {
		$target = $this;

		return function () use ($target) {
			return $target->one(false)->isSelected();
		};
	}

	/**
	 * @inheritdoc
	 */
	public function getCountCondition($count) {
		$target = $this;

		return function () use ($target, $count) {
			return $target->count() === $count;
		};
	}

	/**
	 * Check that the corresponding element exists.
	 *
	 * @return boolean
	 */
	public function exists() {
		return $this->one(false)->isValid();
	}

	/**
	 * Get input element from container.
	 *
	 * @param CElement     $target    container element
	 * @param string       $prefix    xpath prefix
	 * @param array|string $class     element classes to look for
	 *
	 * @return CElement|CNullElement
	 */
	public static function getInputElement($target, $prefix = './', $class = null) {
		$classes = [
			'CElement'					=> [
				// TODO: change after DEV-1630 (1) is resolved.
				'/input[@name][not(@type) or @type="text" or @type="password"][not(@style) or not(contains(@style,"display: none"))]',
				'/textarea[@name]'
			],
			'CListElement'				=> '/select[@name]',
			'CDropdownElement'			=> '/z-select[@name]',
			'CCheckboxElement'			=> '/input[@name][@type="checkbox" or @type="radio"]',
			'CMultiselectElement'		=> [
				'/div[contains(@class, "multiselect-control")]'
			],
			'CSegmentedRadioElement'	=> [
				'/ul[contains(@class, "radio-list-control")]',
				'/ul/li/ul[contains(@class, "radio-list-control")]'
			],
			'CCheckboxListElement'		=> [
				'/ul[contains(@class, "checkbox-list")]',
				'/ul[contains(@class, "list-check-radio")]'
			],
			'CHostInterfaceElement'		=> [
				'/div/div[contains(@class, "interface-container")]/../..'
			],
			'CMultifieldTableElement'	=> [
				'/table',
				'/*[contains(@class, "table-forms-separator")]/table'
			],
			'CCompositeInputElement'	=> [
				'/div[contains(@class, "range-control")]',
				'/div[contains(@class, "calendar-control")]'
			],
			'CColorPickerElement'		=> '/div[contains(@class, "color-picker")]',
			'CMultilineElement'			=> '/div[contains(@class, "multilineinput-control")]',
			'CInputGroupElement'		=> '/div[contains(@class, "macro-input-group")]',
			'CFieldsetElement'			=> '/fieldset'
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

			static::$selector = 'xpath:'.implode('|', $xpaths);
			$element = $target->query(static::$selector)->cast($class)->one(false);
			if ($element->isValid()) {
				return $element;
			}
		}

		static::$selector = null;
		return new CNullElement(['locator' => 'input element']);
	}
}
