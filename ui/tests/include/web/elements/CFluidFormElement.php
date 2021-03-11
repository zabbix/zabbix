<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
require_once dirname(__FILE__).'/CFormElement.php';

use Facebook\WebDriver\Remote\RemoteWebElement;

/**
 * Form element.
 */
class CFluidFormElement extends CFormElement {

	const TABLE_FORM = 'div[contains(@class, "form-grid")]';
	const TABLE_FORM_FIELD = 'following-sibling::div[1]';

	/**
	 * Get collection of form label elements.
	 *
	 * @return CElementCollection
	 */
	public function getLabels() {
		$labels = $this->query('xpath:.//'.self::TABLE_FORM.'/label')->all();

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
		$labels = $this->query('xpath:.//'.self::TABLE_FORM.'/label[text()='.CXPathHelper::escapeQuotes($name).']')->all();

		if ($labels->isEmpty()) {
			throw new Exception('Failed to find form label by name: "'.$name.'".');
		}

		if ($labels->count() > 1) {
			$labels = $labels->filter(new CElementFilter(CElementFilter::VISIBLE));

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
		if (($element = CElementQuery::getInputElement($label, './'.self::TABLE_FORM_FIELD))->isValid()) {
			return $element;
		}

		return null;
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
		return $this->getLabel($name)->query('xpath:./'.self::TABLE_FORM_FIELD)->one();
	}
}
