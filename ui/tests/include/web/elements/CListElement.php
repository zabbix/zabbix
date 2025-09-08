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
 * List (select) element.
 */
class CListElement extends CElement {

	/**
	 * Get collection of options.
	 *
	 * @return CElementCollection
	 */
	public function getOptions() {
		return $this->query('tag:option')->all();
	}

	/**
	 * Get text of selected option.
	 *
	 * @return string
	 */
	public function getText() {
		foreach ($this->getOptions() as $option) {
			if ($option->isSelected()) {
				return $option->getText();
			}
		}

		return null;
	}

	/**
	 * Select option by text.
	 *
	 * @param string $text    option text to be selected
	 *
	 * @return $this
	 */
	public function select($text) {
		$option = $this->query('xpath:.//option[text()='.CXPathHelper::escapeQuotes($text).']')->one();
		if (!$option->isSelected()) {
			if ($option->isClickable()) {
				$option->click();
			}
			else {
				throw new Exception('Cannot select disabled list element.');
			}
		}

		return $this;
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
