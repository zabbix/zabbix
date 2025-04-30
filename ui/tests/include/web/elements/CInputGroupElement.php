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
 * InputGroup element.
 */
class CInputGroupElement extends CElement {

	const TYPE_SECRET = 'Secret text';
	const TYPE_TEXT = 'Text';
	const TYPE_VAULT = 'Vault';

	/**
	 * Get value of InputGroup element.
	 *
	 * @return string
	 */
	public function getValue() {
		return $this->query('xpath:.//textarea[contains(@class, "textarea-flexible")]|.//input[@type="password"]')
				->one()->getValue();
	}

	/**
	 * Select the type of InputGroup element.
	 *
	 * @param	string	$new_type	value field type to be selected
	 *
	 * @return $this
	 */
	public function changeInputType($new_type) {
		return $this->query('xpath:.//button['.CXPathHelper::fromClass('btn-dropdown-toggle').']')
				->asPopupButton()
				->one()
				->getMenu()
				->select($new_type);
	}

	/**
	 * Get "Revert" button for the corresponding InputGroup element
	 *
	 * @return CElement
	 */
	public function getRevertButton() {
		return	$this->query('xpath:.//button[@title="Revert changes"]')->one(false);
	}

	/**
	 * Get "Set new value" button for the corresponding InputGroup element
	 *
	 * @return CElement
	 */
	public function getNewValueButton() {
		return	$this->query('button:Set new value')->one(false);
	}

	/**
	 * Get the current type of InputGroup element
	 *
	 * @return string
	 */
	public function getInputType() {
		$xpath_vault = 'xpath:./../div[contains(@class, "macro-value-vault")]';
		$xpath_secret = 'xpath:.//div[@class="input-secret"]/..//button[contains(@id, "type_button")]';

		if ($this->query($xpath_secret)->exists()) {
			return self::TYPE_SECRET;
		}

		if ($this->query($xpath_vault)->exists()) {
			return self::TYPE_VAULT;
		}

		return self::TYPE_TEXT;
	}

	/**
	 * Fill InputGroup element type and value
	 *
	 * @param	array|string	$input		values to be set in InputGroups element
	 *
	 * @return	$this
	 */
	public function fill($input) {
		if (!is_array($input)) {
			$xpath = 'xpath:.//textarea[contains(@class, "textarea-flexible")]|.//input[@type="password"]';
			$this->query($xpath)->one()->fill($input);

			return $this;
		}

		if (array_key_exists('type', $input) && $this->getInputType() !== $input['type']) {
			$this->changeInputType($input['type']);
		}

		if (array_key_exists('text', $input)) {
			$change_button = $this->query('button:Set new value')->one(false);
			if ($change_button->isValid()) {
				$change_button->click();
			}

			$xpath = 'xpath:.//textarea[contains(@class, "textarea-flexible")]|.//input[@type="password"]';
			$this->query($xpath)->one()->fill($input['text']);
		}

		return $this;
	}
}
