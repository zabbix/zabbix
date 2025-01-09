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

require_once dirname(__FILE__).'/../CElement.php';

/**
 * Segmented radio element.
 */
class CSegmentedRadioElement extends CElement {

	/**
	 * Get collection of labels.
	 *
	 * @return CElementCollection
	 */
	public function getLabels() {
		return $this->query('tag:label')->all();
	}

	/**
	 * Get text of selected element.
	 *
	 * @return string
	 */
	public function getText() {
		$radio = $this->query('xpath:.//input[@type="radio"]')->all()->filter(new CElementFilter(CElementFilter::SELECTED));

		if ($radio->isEmpty()) {
			throw new Exception('Failed to find selected element.');
		}
		if ($radio->count() > 1) {
			$radio = $radio->filter(new CElementFilter(CElementFilter::VISIBLE));

			if ($radio->isEmpty()) {
				throw new Exception('Failed to find visible selected element.');
			}

			if ($radio->count() > 1) {
				CTest::zbxAddWarning('Selected element is not one.');
			}
		}

		return $radio->first()->query('xpath:../label')->one()->getText();
	}

	/**
	 * Select label by text.
	 *
	 * @param string $text    label text to be selected
	 *
	 * @return $this
	 */
	public function select($text) {
		$this->query('xpath:.//label[text()='.CXPathHelper::escapeQuotes($text).']')->waitUntilVisible()->one()->click();

		return $this;
	}

	/**
	 * Get label of selected element.
	 *
	 * @return string
	 */
	public function getSelected() {
		return $this->getText();
	}

	/**
	 * @inheritdoc
	 */
	public function isEnabled($enabled = true) {
		return (($this->query('xpath:.//input[@type="radio"]')->all()->filter(CElementFilter::DISABLED)->count() === 0)
				=== $enabled
		);
	}

	/**
	 * Alias for select.
	 * @see self::select
	 *
	 * @param string $text    label text to be selected
	 *
	 * @return $this
	 */
	public function fill($text) {
		return $this->select($text);
	}

	/**
	 * @inheritdoc
	 */
	public function getValue() {
		return $this->getSelected();
	}
}
