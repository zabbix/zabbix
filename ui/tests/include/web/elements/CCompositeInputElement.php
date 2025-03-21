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
 * Composite input element.
 */
class CCompositeInputElement extends CElement {

	/**
	 * Get composite input field.
	 *
	 * @return type
	 */
	public function getInput() {
		return $this->query('xpath:./input')->waitUntilPresent()->one();
	}

	/**
	 * Select composite input value.
	 *
	 * @inheritdoc
	 */
	public function selectValue() {
		$this->getInput()->selectValue();

		return $this;
	}

	/**
	 * Overwrite composite input value.
	 *
	 * @inheritdoc
	 */
	public function overwrite($text) {
		$this->getInput()->overwrite($text);

		return $this;
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
	 * @inheritdoc
	 */
	public function checkValue($expected, $raise_exception = true) {
		return $this->getInput()->checkValue($expected, $raise_exception);
	}
}
