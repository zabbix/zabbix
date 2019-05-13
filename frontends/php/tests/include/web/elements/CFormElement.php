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
 * Form element.
 */
class CFormElement extends CElement {
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
		return $this->query('xpath:.//ul[@class="table-forms"]/li/div[@class="table-forms-td-left"]/label')->all();
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
		$prefix = 'xpath:.//ul[@class="table-forms"]/li/div[@class="table-forms-td-left"]';
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
	 * @param CElement $label    label element
	 *
	 * @return CElement|null
	 */
	public function getFieldByLabelElement($label) {
		$prefix = './../../div[@class="table-forms-td-right"]';

		if (($element = CElementQuery::getInputElement($label, $prefix)) !== null) {
			return $element;
		}

		// Nested table forms.
		$element = $label->query('xpath', $prefix.'//ul[@class="table-forms"]/..')
				->cast('CFormElement', ['normalized' => true])
				->one(false);

		if ($element !== null) {
			return $element;
		}

		return null;
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

			if ($element !== null) {
				$fields[$label->getText()] = $element;
			}
		}

		$this->fields = new CElementCollection($fields);

		return $this->fields;
	}

	/**
	 * Get field by label name.
	 *
	 * @param string  $name          field label text
	 * @param boolean $invalidate    cache usage flag
	 *
	 * @return CElement
	 *
	 * @throws Exception
	 */
	public function getField($name, $invalidate = false) {
		if ($invalidate || !$this->fields->exists($name)) {
			$label = $this->getLabel($name);

			if (($element = $this->getFieldByLabelElement($label)) === null) {
				throw new Exception('Failed to find form field by label name: "'.$name.'".');
			}

			$this->fields->set($name, $element);
		}

		return $this->fields->get($name);
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
		$prefix = 'xpath:.//ul[@class="table-forms"]/li/div[@class="table-forms-td-left"]';
		$label = $this->query($prefix.'/label[@for='.CXPathHelper::escapeQuotes($id).']')->one(false);

		if (!$label) {
			$label = $this->query('xpath:.//ul[@class="table-forms"]/li/div[@class="table-forms-td-right"]//*[@id='.
					CXPathHelper::escapeQuotes($id).']/ancestor::div[@class="table-forms-td-right"]/../'.
					'div[@class="table-forms-td-left"]/label')->one(false);

			if (!$label) {
				throw new Exception('Failed to find form label by field id: "'.$id.'".');
			}
		}

		if (($element = $this->getFieldByLabelElement($label)) === null) {
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
		return $this->getLabel($name)->query('xpath:./../../div[@class="table-forms-td-right"]')->one();
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
		return $this->parents('class:overlay-dialogue-body')->one()
				->query('tag:output')->waitUntilPresent()->asMessage()->one();
	}

	/**
	 * @inheritdoc
	 */
	public function submit() {
		$submit = $this->query('xpath:.//button[@type="submit"]')->one(false);
		if ($submit !== null) {
			$submit->click();
		}
		else {
			parent::submit();
		}

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
		if ($data) {
			if (!is_array($data)) {
				$data = [$data];
			}

			foreach ($data as $field => $value) {
				$this->getField($field)->fill($value);
			}
		}

		return $this;
	}
}
