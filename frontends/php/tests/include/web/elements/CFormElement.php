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
			$this->setElement($this->query('xpath:.//form')->one());
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
	 * Get element field by label element.
	 *
	 * @param CElement $label    label element
	 *
	 * @return CElement|null
	 */
	protected function getFieldByLabelElement($label) {
		$prefix = 'xpath:./../../div[@class="table-forms-td-right"]';
		$selectors = [
			'/*[@name]'								=> 'CElement',
			'/div[@class="multiselect-wrapper"]'	=> 'CMultiselectElement',
			'/ul[@class="radio-segmented"]'			=> 'CSegmentedRadioElement',
			'/div[@class="range-control"]'			=> 'CRangeControlElement'
		];

		foreach ($selectors as $selector => $class) {
			if (($element = $label->query($prefix.$selector)->cast($class)->one(false)) !== null) {
				return $element;
			}
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
	 * @param string $name    field label text
	 *
	 * @return CElement
	 *
	 * @throws Exception
	 */
	public function getField($name) {
		if (!$this->fields->exists($name)) {
			$prefix = 'xpath:.//ul[@class="table-forms"]/li/div[@class="table-forms-td-left"]';
			$label = $this->query($prefix.'/label[text()='.CXPathHelper::escapeQuotes($name).']')->one(false);

			if ($label === null || ($element = $this->getFieldByLabelElement($label)) === null) {
				throw new Exception('Failed to find form field by label name: "'.$name.'".');
			}

			$this->fields->set($name, $element);
		}

		return $this->fields->get($name);
	}

	/**
	 * Switch to tab by tab name.
	 *
	 * @return $this
	 */
	public function selectTab($name) {
		$xpath = './/ul[contains(@class, "ui-tabs-nav")]//a[text()='.CXPathHelper::escapeQuotes($name).']';
		$this->query('xpath', $xpath)->waitUntilPresent()->one()->click();
		$this->query('xpath://li[@aria-selected="true"]/a[text()='.CXPathHelper::escapeQuotes($name).']')->waitUntilPresent();

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
}
