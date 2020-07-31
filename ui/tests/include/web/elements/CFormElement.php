<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

	protected $toggle_fields = true;

	/**
	 * Local form input cache.
	 * @var array
	 */
	protected $fields;

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
	}

	/**
	 * Get collection of form label elements.
	 *
	 * @return CElementCollection
	 */
	public function getLabels() {
		return $this->query('xpath:.//'.self::TABLE_FORM.'/li/'.self::TABLE_FORM_LEFT.'/label')->all();
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
			$labels = $labels->filter(CElementQuery::VISIBLE);

			if ($labels->isEmpty()) {
				throw new Exception('Failed to find visible form label by name: "'.$name.'".');
			}

			if ($labels->count() > 1) {
				CTest::addWarning('Form label "'.$name.'" is not unique.');
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
		elseif ($this->toggle_fields) {
			$for = $label->getAttribute('for');
			if (substr($for, 0, 8) === 'visible_') {
				try {
					$this->query('id', $for)->asCheckbox()->one()->check();
					if (($element = CElementQuery::getInputElement($label, './../../'.self::TABLE_FORM_RIGHT))->isValid()) {
						return $element;
					}
				}
				catch (\Exception $e) {
					// Code is not missing here.
				}
			}
		}

		// Nested table forms.
		return $label->query('xpath', './../../'.self::TABLE_FORM_RIGHT.'//'.self::TABLE_FORM.'/..')
				->cast('CFormElement', ['normalized' => true])
				->one(false);
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
		$element = new CNullElement(['locator' => 'form field (by name or selector "'.$name.'")']);

		if (count($parts) === 2
				&& in_array($parts[0], ['id', 'name', 'css', 'class', 'tag', 'link', 'button', 'xpath'])
				&& (($element = $this->query($name)->one(false))->isValid())) {
			$element = $element->detect();
		}

		if (!$element->isValid()) {
			$label = $this->getLabel($name);

			if (($element = $this->getFieldByLabelElement($label))->isValid() === false && $this->toggle_fields !== false) {
				throw new Exception('Failed to find form field by label name or selector: "'.$name.'".');
			}
		}

		$this->fields->set($name, $element);

		return $element;
	}

	/**
	 * Get field by field id.
	 *
	 * @param string $id    field id
	 *
	 * @return CElement
	 *
	 * @throws Exception
	 */
	public function getFieldById($id) {
		$prefix = './/'.self::TABLE_FORM.'/li/'.self::TABLE_FORM_LEFT;
		$label = $this->query('xpath:'.$prefix.'/label[@for='.CXPathHelper::escapeQuotes($id).
				' or @for='.CXPathHelper::escapeQuotes('visible_'.$id).']')->one(false);

		if ($label->isValid() === false) {
			$label = $this->query('xpath:.//'.self::TABLE_FORM.'/li/'.self::TABLE_FORM_RIGHT.'//*[@id='.
					CXPathHelper::escapeQuotes($id).']/ancestor::'.self::TABLE_FORM_RIGHT.'/../'.
					self::TABLE_FORM_LEFT.'/label')->one(false);

			if (!$label->isValid()) {
				throw new Exception('Failed to find form label by field id: "'.$id.'".');
			}
		}

		if (!($element = $this->getFieldByLabelElement($label))->isValid()) {
			throw new Exception('Failed to find form field by label id: "'.$id.'".');
		}

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
	 * Get field elements by label name.
	 *
	 * @param string $name    field label text
	 *
	 * @return CElementCollection
	 *
	 * @throws Exception
	 */
	public function getFieldElements($name) {
		return $this->getFieldContainer($name)->query('xpath:./*')->all();
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
		$submit = $this->query('xpath:.//button[@type="submit"]')->one(false);
		if ($submit->isValid()) {
			$submit->click();
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
	 * @return
	 */
	private function setFieldValue($field, $values) {
		$element = $this->getField($field);

		if ($values === null) {
			$label = $this->getLabel($field);
			$for = $label->getAttribute('for');

			if (substr($for, 0, 8) === 'visible_') {
				try {
					$this->query('id', $for)->asCheckbox()->one()->uncheck();

					return;
				}
				catch (\Exception $e) {
					// Code is not missing here.
				}
			}
		}
		elseif (is_array($values) && !in_array(get_class($element),
				[CMultifieldTableElement::class, CMultiselectElement::class, CCheckboxListElement::class])) {

			if ($values !== []) {
				$container = $this->getFieldContainer($field);

				foreach ($values as $name => $value) {
					$container->query('id', $name)->one()->detect()->fill($value);
				}
			}

			return;
		}

		$element->fill($values);
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
		$state = $this->toggle_fields;
		$this->toggle_fields = false;

		try {
			if ($expected && is_array($expected)) {
				foreach ($expected as $field => $value) {
					if ($this->checkFieldValue($field, $value, $raise_exception) === false) {
						$this->toggle_fields = $state;
						return false;
					}
				}
			}
		}
		catch (\Exception $e) {
			$this->toggle_fields = $state;
			throw $e;
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
	private function checkFieldValue($field, $values, $raise_exception = true) {
		$element = $this->getField($field);

		if ($values === null) {
			$label = $this->getLabel($field);
			$for = $label->getAttribute('for');

			if (substr($for, 0, 8) === 'visible_') {
				$checkbox = $this->query('id', $for)->asCheckbox()->one(false);
				if ($checkbox->isValid()) {
					return $checkbox->checkValue(false, $raise_exception);
				}
			}

			return true;
		}
		elseif (is_array($values) && !in_array(get_class($element),
				[CMultifieldTableElement::class, CMultiselectElement::class, CCheckboxListElement::class])) {

			if ($values !== []) {
				$container = $this->getFieldContainer($field);

				foreach ($values as $name => $value) {
					if (!$container->query('id', $name)->one()->detect()->checkValue($value, $raise_exception)) {
						return false;
					}
				}
			}

			return true;
		}

		$element->checkValue($values, $raise_exception);
	}
}
