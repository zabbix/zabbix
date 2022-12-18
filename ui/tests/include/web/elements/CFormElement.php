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

require_once dirname(__FILE__).'/../CElement.php';

use Facebook\WebDriver\Remote\RemoteWebElement;

/**
 * Form element.
 */
class CFormElement extends CElement {

	const TABLE_FORM = 'ul[contains(@class, "table-forms")]';
	const TABLE_FORM_LEFT = 'div[contains(@class, "table-forms-td-left")]';
	const TABLE_FORM_RIGHT = 'div[contains(@class, "table-forms-td-right")]';

	/**
	 * Local form input cache.
	 * @var array
	 */
	protected $fields;

	/**
	 * Condition to be filtered by.
	 * @var CElementFilter
	 */
	protected $filter = null;

	/**
	 * Get filter.
	 *
	 * @return CElementFilter
	 */
	public function getFilter() {
		return $this->filter;
	}

	/**
	 * Set filter conditions.
	 *
	 * @param mixed $filter		conditions to be filtered by
	 *
	 * @return $this
	 */
	public function setFilter($filter) {
		$this->filter = $filter;

		return $this;
	}

	/**
	 * @inheritdoc
	 */
	protected function setElement(RemoteWebElement $element) {
		parent::setElement($element);
		$this->invalidate();
	}

	/**
	 * @inheritdoc
	 */
	protected function normalize() {
		if ($this->getTagName() !== 'form') {
			$this->setElement($this->query('xpath:.//form')->waitUntilPresent()->one());
		}
	}

	/**
	 * @inheritdoc
	 */
	public function invalidate() {
		parent::invalidate();

		$this->fields = new CElementCollection([]);

		return $this;
	}

	/**
	 * Get collection of form label elements.
	 *
	 * @return CElementCollection
	 */
	public function getLabels() {
		$labels = $this->query('xpath:.//'.self::TABLE_FORM.'/li/'.self::TABLE_FORM_LEFT.'/label')->all();

		foreach ($labels as $key => $label) {
			if ($label->getText() === '') {
				$element = $this->getFieldByLabelElement($label);
				if ($element->isValid() && get_class($element) === CCheckboxElement::class) {
					$labels->set($key, $element->query('xpath:../label')->one(false));
				}
			}
		}

		if ($this->filter !== null) {
			return $labels->filter($this->filter);
		}

		return $labels;
	}

	/**
	 * Get form label element by text.
	 *
	 * @param string $name    field label text
	 *
	 * @return CElement
	 *
	 * @throws Exception
	 */
	public function getLabel($name) {
		$prefix = 'xpath:.//'.self::TABLE_FORM.'/li/'.self::TABLE_FORM_LEFT;
		$labels = $this->query($prefix.'/label[text()='.CXPathHelper::escapeQuotes($name).']')->all();

		if ($labels->isEmpty()) {
			throw new Exception('Failed to find form label by name: "'.$name.'".');
		}

		if ($labels->count() > 1) {
			$labels = $labels->filter(new CElementFilter(CElementFilter::VISIBLE));

			if ($labels->isEmpty()) {
				throw new Exception('Failed to find visible form label by name: "'.$name.'".');
			}

			if ($labels->count() > 1) {
				CTest::zbxAddWarning('Form label "'.$name.'" is not unique.');
			}
		}

		return $labels->first();
	}

	/**
	 * Get element field by label element.
	 *
	 * @param CElement $label     label element
	 *
	 * @return CElement|CNullElement
	 */
	public function getFieldByLabelElement($label) {
		if (($element = CElementQuery::getInputElement($label, './../../'.self::TABLE_FORM_RIGHT))->isValid()) {
			return $element;
		}

		// Nested table forms.
		$element = $label->query('xpath', './../../'.self::TABLE_FORM_RIGHT.'//'.self::TABLE_FORM.'/..')
				->cast('CFormElement', ['normalized' => true])
				->one(false);

		if (!$element->isValid()) {
			$element = $label->query('xpath:./../../'.self::TABLE_FORM_RIGHT)->one(false);
		}

		return $element;
	}

	/**
	 * Get collection of element fields indexed by label name.
	 *
	 * @return CElementCollection
	 */
	public function getFields() {
		$fields = [];

		foreach ($this->getLabels() as $label) {
			$element = $this->getFieldByLabelElement($label);
			if ($this->filter !== null && !$this->filter->match($element)) {
				$element = new CNullElement();
			}

			if ($element->isValid()) {
				$fields[$label->getText()] = $element;
			}
		}

		$this->fields = new CElementCollection($fields);

		return $this->fields;
	}

	/**
	 * Get field by label name or selector.
	 *
	 * @param string  $name          field label text or element selector
	 * @param boolean $invalidate    cache usage flag
	 *
	 * @return CElement
	 *
	 * @throws Exception
	 */
	public function getField($name, $invalidate = false) {
		if (!$invalidate && $this->fields->exists($name)) {
			return $this->fields->get($name);
		}

		$parts = explode(':', $name, 2);
		if (count($parts) === 2 && in_array($parts[0], ['id', 'name', 'css', 'class', 'tag', 'link', 'button', 'xpath'])
				&& ($element = $this->query($name)->one(false))->isValid()) {
			$element = $element->detect();
		}
		else {
			try {
				$label = $this->getLabel($name);
				$element = $this->getFieldByLabelElement($label);
			}
			catch (\Exception $exception) {
				$label = $this->query('xpath:.//label[text()='.CXPathHelper::escapeQuotes($name).']')->one(false);
				if (!$label->isValid()) {
					throw $exception;
				}

				$element = CElementQuery::getInputElement($label, './../');
				if (get_class($element) !== CCheckboxElement::class) {
					throw $exception;
				}
			}

			if (!$element->isValid()) {
				throw new Exception('Failed to find form field by label name or selector: "'.$name.'".');
			}
		}

		$this->fields->set($name, $element);

		return $element;
	}

	/**
	 * Get field container element by label name.
	 *
	 * @param string $name    field label text
	 *
	 * @return CElement
	 *
	 * @throws Exception
	 */
	public function getFieldContainer($name) {
		return $this->getLabel($name)->query('xpath:./../../'.self::TABLE_FORM_RIGHT)->one();
	}

	/**
	 * Switch to tab by tab name.
	 *
	 * @return $this
	 */
	public function selectTab($name) {
		$selector = '/a[text()='.CXPathHelper::escapeQuotes($name).']';

		$this->query('xpath:.//ul[contains(@class, "ui-tabs-nav")]/'.$selector)->waitUntilPresent()->one()->click();
		$this->query('xpath:.//li[@aria-selected="true"]'.$selector)->waitUntilPresent();

		return $this;
	}

	/**
	 * Get text of selected tab.
	 *
	 * @return string
	 */
	public function getSelectedTab() {
		return $this->query('xpath:.//ul[contains(@class, "ui-tabs-nav")]'.
				'//li[@aria-selected="true"]/a')->waitUntilPresent()->one()->getText();
	}

	/**
	 * Get message of form in overlay dialog.
	 *
	 * @return CMessageElement
	 */
	public function getOverlayMessage() {
		return $this->parents('class:overlay-dialogue-body')->waitUntilVisible()->one()
				->query('tag:output')->asMessage()->waitUntilVisible()->one();
	}

	/**
	 * @inheritdoc
	 */
	public function submit() {
		$buttons = $this->query('xpath:.//button[@type="submit"]|.//input[@type="submit"]')->all();

		if ($buttons->count() > 1) {
			$buttons = $buttons->filter(new CElementFilter(CElementFilter::VISIBLE));
		}

		$submit = ($buttons->count() === 0) ? (new CNullElement()) : $buttons->first();

		if ($submit->isValid()) {
			$submit->click(true);
		}
		else {
			parent::submit();
		}

		return $this;
	}

	/**
	 * Fill form fields with specific values.
	 *
	 * @param string $field   field name to filled in
	 * @param string $values  value to be put in field
	 *
	 * @return $this
	 */
	protected function setFieldValue($field, $values) {
		$classes = [CMultifieldTableElement::class, CMultiselectElement::class, CCheckboxListElement::class];
		$element = $this->getField($field);

		if (is_array($values) && !in_array(get_class($element), $classes)) {
			if ($values !== []) {
				$container = $this->getFieldContainer($field);
				if ($this->filter !== null && !$this->filter->match($container)) {
					return $this;
				}

				foreach ($values as $name => $value) {
					$xpath = './/*[@id='.CXPathHelper::escapeQuotes($name).' or @name='.CXPathHelper::escapeQuotes($name).']';
					$container->query('xpath', $xpath)->one()->detect()->fill($value);
				}
			}

			return $this;
		}
		elseif ($values instanceof \Closure) {
			$values($this, $field, $element);

			return $this;
		}

		if ($this->filter !== null && !$this->filter->match($element)) {
			return $this;
		}

		$element->fill($values);

		return $this;
	}

	/**
	 * Fill form with specified data.
	 *
	 * @param array $data    data array where keys are label text and values are values to be put in fields
	 *
	 * @return $this
	 */
	public function fill($data) {
		if ($data && is_array($data)) {
			foreach ($data as $field => $values) {
				try {
					$this->setFieldValue($field, $values);
				}
				catch (\Exception $e1) {
					sleep(1);

					try {
						$this->invalidate();
						$this->setFieldValue($field, $values);
					} catch (\Exception $e2) {
						throw $e1;
					}
				}
			}
		}

		return $this;
	}

	/**
	* @inheritdoc
	*/
	public function checkValue($expected, $raise_exception = true) {
		if ($expected && is_array($expected)) {
			foreach ($expected as $field => $value) {
				if ($this->checkFieldValue($field, $value, $raise_exception) === false) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Check single form field value to have a specific value.
	 *
	 * @param string  $field              field name to filled checked
	 * @param mixed   $values             value to be checked in field
	 * @param boolean $raise_exception    flag to raise exceptions on error
	 *
	 * @return boolean
	 *
	 * @throws Exception
	 */
	protected function checkFieldValue($field, $values, $raise_exception = true) {
		$classes = [CMultifieldTableElement::class, CMultiselectElement::class, CCheckboxListElement::class];
		$element = $this->getField($field);

		if (is_array($values) && !in_array(get_class($element), $classes)) {
			if ($values !== []) {
				$container = $this->getFieldContainer($field);
				if ($this->filter !== null && !$this->filter->match($container)) {
					if ($raise_exception) {
						throw new Exception('Failed to check value of field not matching the filter.');
					}

					return false;
				}

				foreach ($values as $name => $value) {
					$xpath = './/*[@id='.CXPathHelper::escapeQuotes($name).' or @name='.CXPathHelper::escapeQuotes($name).']';
					if (!$container->query('xpath', $xpath)->one()->detect()->checkValue($value, $raise_exception)) {
						return false;
					}
				}
			}

			return true;
		}

		if ($this->filter !== null && !$this->filter->match($element)) {
			if ($raise_exception) {
				throw new Exception('Failed to check value of field not matching the filter.');
			}

			return false;
		}

		return $element->checkValue($values, $raise_exception);
	}

	/**
	 * Wait for form reload after form element select.
	 *
	 * @param string $value		text to be written into the field
	 *
	 * @return Closure
	 */
	public static function RELOADABLE_FILL($value) {
		return function ($form, $field, $element) use ($value) {
			if (!($element instanceof CDropdownElement) || $element->getText() !== $value) {
				$element->fill($value);
				$form->waitUntilReloaded();
			}
		};
	}

	/**
	 * Check if field is marked as required in form.
	 *
	 * @param string $label    field label text
	 *
	 * @return boolean
	 */
	public function isRequired($label) {
		return $this->getLabel($label)->hasClass('form-label-asterisk');
	}
}
