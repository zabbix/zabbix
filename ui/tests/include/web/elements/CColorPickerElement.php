<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
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
use Facebook\WebDriver\Exception\TimeoutException;

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
	 * @inheritdoc
	 *
	 * @param string $color		color code
	 */
	public function overwrite($color) {
		$overlay = $this->open();

		if ($color === self::USE_DEFAULT) {
			$overlay->query('button:Use default')->one()->click()->waitUntilNotVisible();
			return $this;
		}
		else {
			$overlay->query('xpath:.//div[@class="color-picker-input"]/input')->one()->overwrite($color);
			$overlay->query('class:btn-overlay-close')->one()->click()->waitUntilNotVisible();
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
	 * Open color picker.
	 *
	 * @return CElement
	 */
	public function open() {
		$button = $this->query('xpath:./button['.CXPathHelper::fromClass('color-picker-preview').']')->one();

		/*
		 * The color picker closes itself on any scroll event. WebDriver scrolls the button into view when clicking
		 * it, and that scroll can dismiss the just-opened dialog. Scroll the button into view first to minimise the
		 * click scroll; if the dialog still gets dismissed, click again (button is in view, so it does not scroll).
		 */
		$button->scrollIntoView();
		$button->click();

		$popup = new CElementQuery('id:color_picker');

		try {
			return $popup->waitUntilVisible(3)->one();
		}
		catch (TimeoutException $exception) {
			$button->click();

			return $popup->waitUntilVisible()->one();
		}
	}

	/**
	 * Close color pick overlay dialog.
	 *
	 * @return $this
	 */
	public function close() {
		$this->query('class:btn-overlay-close')->one()->click()->waitUntilNotVisible();
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
