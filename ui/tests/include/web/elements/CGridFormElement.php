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

require_once __DIR__.'/CFormElement.php';

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
			foreach ($grid->query('xpath:./*|./fieldset/legend/..|./fieldset/*')->all() as $element) {
				if ($element->getTagName() === 'label') {
					$labels[] = $element;

					$has_label = true;
				}
				elseif ($element->getTagName() === 'fieldset') {
					$labels[] = $element->asFieldset();

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
		if ($label instanceof CFieldsetElement) {
			return $label;
		}

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
		$labels = $this->query('xpath:.//div[contains(@class, "form-grid")]/label[text()='.
				CXPathHelper::escapeQuotes($name).']|.//div[contains(@class, "form-grid")]/fieldset/label[text()='.
				CXPathHelper::escapeQuotes($name).']|.//div[contains(@class, "form-grid")]/fieldset/div/label[text()='.
				CXPathHelper::escapeQuotes($name).']')->all();

		if ($labels->isEmpty()) {
			$labels = $this->query('xpath:.//div[contains(@class, "form-grid")]/fieldset/legend/button/span[text()='.
					CXPathHelper::escapeQuotes($name).']/../../..')->asFieldset()->all();
		}

		return $labels;
	}
}
