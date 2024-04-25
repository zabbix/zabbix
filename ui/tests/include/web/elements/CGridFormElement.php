<?php
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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

require_once dirname(__FILE__).'/CFormElement.php';

/**
 * Grid form element.
 */
class CGridFormElement extends CFormElement {

	const TABLE_FORM = 'div[contains(@class, "form-grid")]';
	const TABLE_FORM_FIELD = 'following-sibling::div[1]';

	/**
	 * @inheritDoc
	 */
	public function getLabels($filter = null, $filter_params = []) {
		$selectors = ['./'.self::TABLE_FORM, './/div/div/'.self::TABLE_FORM, './div/div/div/div/'.self::TABLE_FORM];

		$labels = [];

		foreach ($this->query('xpath', implode('|', $selectors))->all() as $grid) {
			$has_label = false;
			foreach ($grid->query('xpath:./*')->all() as $element) {
				if ($element->getTagName() === 'label') {
					$labels[] = $element;

					$has_label = true;
				}
				elseif ($element->hasClass('form-field')) {
					if (!$has_label) {
						$input = CElementQuery::getInputElement($element);

						if ($input->isValid() && get_class($input) === CCheckboxElement::class) {
							$label = $input->query('xpath:../label')->one(false);
							if ($label->isValid()) {
								$labels[] = $label;
							}
						}
					}

					$has_label = false;
				}
			}
		}

		return $this->filterCollection(new CElementCollection($labels), $filter, $filter_params);
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
		else {
			$element = $label->query('xpath:./'.self::TABLE_FORM_FIELD)->one(false);
			if (!$element->isValid()) {
				$parent = $label->parents()->one();
				if ($parent->hasClass('form-field')) {
					$element = CElementQuery::getInputElement($label, './..');
				}
			}

			return $element;
		}
	}

	/**
	 * Get field container element by label name.
	 *
	 * @param string $name    field label text
	 *
	 * @return CElement
	 */
	public function getFieldContainer($name) {
		return $this->getLabel($name)->query('xpath:./'.self::TABLE_FORM_FIELD)->one();
	}

	/**
	 * Get label elements by text.
	 *
	 * @param string $name    field label text
	 *
	 * @return CElementCollection
	 */
	protected function findLabels($name) {
		return $this->query('xpath:.//div[contains(@class, "form-grid")]/label[text()='.CXPathHelper::escapeQuotes($name).']')->all();
	}
}
