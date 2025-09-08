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

/**
 * Custom dropdown element.
 */
class CDropdownElement extends CElement {

	/**
	 * Get collection of options.
	 *
	 * @return CElementCollection
	 */
	public function getOptions() {
		return $this->query('xpath:.//li[not(@optgroup) and not(contains(@style,"display: none"))]')->all();
	}

	/**
	 * Get text of selected element.
	 *
	 * @return string
	 */
	public function getText() {
		return $this->query('xpath:./button')->one()->getText();
	}

	/**
	 * Select option by text.
	 *
	 * @param string $text		option text to be selected
	 *
	 * @return $this
	 */
	public function select($text) {
		if ($text === $this->getText()) {
			return $this;
		}

		$option = null;
		if ($this->query("xpath:.//li[not(@optgroup)]/*")->count() > 0) {
			foreach ($this->getOptions() as $element) {
				if ($text === $element->getText()) {
					$option = $element;

					break;
				}
			}
		}
		else {
			$option = $this->query('xpath:.//li[not(@optgroup) and text()='.CXPathHelper::escapeQuotes($text).']')->one();
		}
		if ($option !== null) {
			for ($i = 0; $i < 5; $i++) {
				try {
					$this->waitUntilClickable()->click();
					$option->scrollIntoView();
					$option->click();

					return $this;
				}
				catch (Exception $exception) {
					// Code is not missing here.
				}
			}
		}

		throw new Exception('Failed to select dropdown option "'.$text.'".');
	}

	/**
	 * Alias for select.
	 * @see self::select
	 *
	 * @param string $text    option text to be selected
	 *
	 * @return $this
	 */
	public function fill($text) {
		return $this->select($text);
	}

	/**
	 * Alias for getText.
	 * @see self::getText
	 *
	 * @return string
	 */
	public function getValue() {
		return $this->getText();
	}
}
