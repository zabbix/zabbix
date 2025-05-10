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

require_once __DIR__.'/../CElement.php';

use Facebook\WebDriver\WebDriverKeys;

/**
 * Color picker element.
 */
class CColorPickerElement extends CElement {

	/**
	 * Get input field of color pick form.
	 *
	 * @return type
	 */
	public function getInput() {
		return $this->query('xpath:.//input')->one();
	}

	/**
	 * Overwrite input value.
	 *
	 * @inheritdoc
	 *
	 * @param string $color		color code
	 */
	public function overwrite($color) {
		$this->query('xpath:./button['.CXPathHelper::fromClass('color-picker-preview').']')->one()->click();
		$overlay = (new CElementQuery('id:color_picker'))->waitUntilVisible()->asOverlayDialog()->one();

		if ($color === null) {
			$overlay->query('button:Use default')->one()->click();
		}
		elseif ($overlay->query('class:color-picker-tabs')->one(false)->isValid()) {
			$overlay->asColorPicker()->selectTab('Solid color');
			$overlay->query('class:color-picker-input')->waitUntilVisible()->one()->overwrite($color);
		}
		// TODO: remove the below else part and move elseif to else when DEV-4301 is ready.
		else {
			if (!$apply_button->one()->isAttributePresent('disabled')) {
				throw new \Exception('Passes value is not a valid hexadecimal value, but Apply button is not disabled.');
			}
		}

		$apply_button = $overlay->query('button:Apply');

	/**
	 * Switch color-picker tab by its name.
	 *
	 * @return $this
	 */
	public function selectTab($name) {
		$selector = 'xpath:.//label[text()='.CXPathHelper::escapeQuotes($name).']';
		$tab_element = $this->query($selector.'/..')->one();

		if (!$tab_element->hasClass('color-picker-tab-selected')) {
			$this->query($selector)->waitUntilPresent()->one()->click();
			$this->query($selector.'/..')->waitUntilClassesPresent('color-picker-tab-selected');
		}

		return $this;
	}

	/**
	 * @inheritdoc
	 */
	public function isEnabled($enabled = true) {
		return $this->getInput()->isEnabled($enabled);
	}

	/**
	 * @inheritdoc
	 */
	public function getValue() {
		return $this->getInput()->getValue();
	}

	/**
	 * Alias for getValue.
	 * @see self::getValue
	 *
	 * @return string
	 */
	public function getText() {
		return $this->getValue();
	}

	/**
	 * Close color picker overlay dialog.
	 *
	 * @return $this
	 */
	public function close() {
		$dialog = $this->query('class:color-picker-dialog')->one();
		CElementQuery::getPage()->pressKey(WebDriverKeys::ESCAPE);
		$dialog->waitUntilNotPresent();
	}

	/**
	 * Add/rewrite color code and submit it.
	 *
	 * @param string $color		color code
	 */
	public function fill($color) {
		$this->overwrite($color);
	}
}
