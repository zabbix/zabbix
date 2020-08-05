<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

require_once 'vendor/autoload.php';
require_once dirname(__FILE__).'/../CElement.php';

/**
 * InputGroup element.
 */
class CInputGroupElement extends CElement {

	protected $type_secret = 'Secret text';
	protected $type_text = 'Text';

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
	 * Check is InputGroup element type is set to secret text.
	 *
	 * @return boolean
	 */
	public function isSecret() {
		return $this->query('xpath:.//input[@type="password"]')->one(false)->isValid();
	}

	/**
	 * Select the type of InputGroup element.
	 *
	 * @param	string	$new_type	vlaue field type to be selected
	 *
	 * @return $this
	 */
	public function changeInputType($new_type) {
		$this->query('xpath:.//button['.CXPathHelper::fromClass('btn-dropdown-toggle').']')->one()->click();
		$type_menu = CPopupMenuElement::find()->waitUntilVisible()->one();

		return $type_menu->select($new_type);
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
		$xpath = 'xpath:.//button['.CXPathHelper::fromClass('icon-text').']';
		$type = ($this->query($xpath)->one(false)->isValid()) ? $this->type_text : $this->type_secret;

		return $type;
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

	/**
	 * Function checks if textarea element is present in the InputGroup element
	 *
	 * @return boolean
	 */
	public function isTextareaPresent() {
		return $this->query('xpath:.//textarea[contains(@class, "textarea-flexible")]')->one(false)->isValid();
	}
}
