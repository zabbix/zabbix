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
		else {
			$overlay->asColorPicker()->selectTab('Solid color');
			$overlay->query('class:color-picker-input')->waitUntilVisible()->one()->overwrite($color);
		}

		$apply_button = $overlay->query('button:Apply');

		if (preg_match('/^[a-fA-F0-9]+$/', $color) === 1 && strlen($color) === 6) {
			CElementQuery::getPage()->pressKey(WebDriverKeys::ENTER);
			$overlay->waitUntilNotVisible();
		}
		else {
			if (!$apply_button->one()->isAttributePresent('disabled')) {
				throw new \Exception('Passes value is not a valid hexadecimal value, but Apply button is not disabled.');
			}
		}

		return $this;
	}

	/**
	 * Get text of selected color-picker tab.
	 *
	 * @return string
	 */
	public function getSelectedTab() {
		return $this->query('xpath:.//ul[@class="color-picker-tabs"]'.
			'//li[contains(@class, "color-picker-tab-selected")]/label')->waitUntilPresent()->one()->getText();
	}

	/**
	 * Switch color-picker tab by its name.
	 *
	 * @return $this
	 */
	public function selectTab($name) {
		$selector = 'xpath:.//label[text()='.CXPathHelper::escapeQuotes($name).']';

		if ($this->getSelectedTab() !== $name) {
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
		CElementQuery::getPage()->pressKey(WebDriverKeys::ESCAPE);
		(new CElementQuery('id:color_picker'))->waitUntilNotVisible();
	}

	/**
	 * Add/rewrite color code and submit it.
	 *
	 * @param string $color		color code
	 */
	public function fill($color) {
		$this->overwrite($color);
	}

	/**
	 * Check if color-picker dialog can be submitted
	 *
	 * @param boolean	$submitable		should dialog submission be disabled or not
	 *
	 * @return type
	 */
	public function isSubmittionDisabled($submitable = false) {
		$dialog = (new CElementQuery('id:color_picker'))->one();
		$clickable = $dialog->query('button:Apply')->one()->isClickable();

		CElementQuery::getPage()->pressKey(WebDriverKeys::ENTER);
		$displayed = $dialog->isDisplayed();

		return ($clickable === $submitable && $displayed === !$submitable);
	}
}
