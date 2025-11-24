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

	const USE_DEFAULT = null;

	/**
	 * Get input field of color pick form.
	 *
	 * @return type
	 */
	public function getInput() {
		return $this->query('xpath:./input')->one();
	}

	/**
	 * Overwrite input value.
	 *
	 * @param string|int|null $color	color code or palette number as a 'palette:number' pattern
	 *
	 * @return $this
	 */
	public function overwrite($color) {
		$overlay = $this->open();
		$name = (str_starts_with($color, 'palette')) ? 'Palette' : 'Solid color';

		if ($color === self::USE_DEFAULT) {
			$overlay->query('button:Use default')->one()->click()->waitUntilNotVisible();
			return $this;
		}
		else {
			if ($overlay->query('xpath:.//li['.CXPathHelper::fromClass('color-picker-tab-selected').']/label')
					->waitUntilPresent()->one()->getText() !== $name) {
				$tab_selector = ('xpath:.//label[text()='.CXPathHelper::escapeQuotes($name).']');
				$overlay->query($tab_selector)->waitUntilPresent()->one()->click();
				$overlay->query($tab_selector.'/..')->waitUntilClassesPresent('color-picker-tab-selected');
			}

			if ($name === 'Palette') {
				$palette_number = substr($color, strlen('palette:'));
				$overlay->query('xpath:.//input[@id="color-picker-palette-input-'.$palette_number.'"]')->one()->click();
			}
			else {
				$overlay->query('class:color-picker-input')->waitUntilVisible()->one()->overwrite($color);

				if (preg_match('/^[a-fA-F0-9]+$/', $color) === 1 && strlen($color) === 6) {
					CElementQuery::getPage()->pressKey(WebDriverKeys::ENTER);
					$overlay->waitUntilNotVisible();
				}
			}
		}

		return $this;
	}

	/**
	 * @inheritdoc
	 */
	public function isEnabled($enabled = true) {
		return parent::isEnabled($enabled) && $this->getInput()->isEnabled($enabled)
				&& $this->query('xpath:./button')->one()->isEnabled($enabled);
	}

	/**
	 * @inheritdoc
	 */
	public function getValue() {
		return $this->getInput()->getValue();
	}

	/**
	 * @inheritdoc
	 */
	public function checkValue($expected, $raise_exception = true) {
		if (is_string($expected) && str_starts_with($expected, 'palette:')) {
			$expected = substr($expected, strlen('palette:'));
		}

		return parent::checkValue($expected, $raise_exception);
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
	 * Open color picker.
	 *
	 * @return CElement
	 */
	public function open() {
		$this->query('xpath:./button['.CXPathHelper::fromClass('color-picker-preview').']')->one()->click();
		return (new CElementQuery('id:color_picker'))->waitUntilVisible()->one();
	}

	/**
	 * Press Escape key to close color picker.
	 */
	public static function close() {
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
	 * Check if color-picker dialog can be submitted.
	 *
	 * @param boolean	$submitable		should dialog submission be disabled or not
	 *
	 * @return boolean
	 */
	public static function isSubmitable($submitable = true) {
		$dialog = (new CElementQuery('id:color_picker'))->one();
		$clickable = $dialog->query('button:Apply')->one()->isClickable();

		CElementQuery::getPage()->pressKey(WebDriverKeys::ENTER);
		$displayed = $dialog->isDisplayed();

		return ($clickable === $submitable && $displayed === !$submitable);
	}
}
