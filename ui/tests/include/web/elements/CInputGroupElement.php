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
 * Dashboard element.
 */
class CInputGroupElement extends CElement {

	protected $textarea_xpath = 'xpath:.//textarea[contains(@class, "textarea-flexible")]';

	/**
	 * @inheritdoc
	 */
	public static function find() {
		return (new CElementQuery('xpath:.//div[contains(@class, "input-group")]'))->asInputGroup();
	}

	/**
	 * Get value of InputGroup element.
	 *
	 * @return string
	 */
	public function getValue() {
		$input = ($this->query($this->textarea_xpath)->one(false)->isValid()) ? $this->query($this->textarea_xpath)->one() :
				$this->query('xpath:.//input[@type="password"]')->one();

		return $input->getValue();
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
	 * @return $this
	 */
	public function changeInputType($new_type) {
		$this->query('xpath:.//button['.CXPathHelper::fromClass('btn-dropdown-toggle').']')->one()->click();
		$type_menu = CPopupMenuElement::find()->waitUntilVisible()->one();

		return $type_menu->select($new_type);
	}

	/**
	 * Press revert button for the corresponding InputGroup element
	 */
	public function pressRevertButton() {
		try {
			$this->query('xpath:.//button[@title="Revert changes"]')->one()->click();
		}
		catch (Exception $e) {
			throw new Exception('Revert button is not elabled for this element.');
		}
	}

	/**
	 * Get the current type of InputGroup element
	 *
	 * @return string
	 */
	public function getInputType() {
		$xpath = 'xpath:.//button['.CXPathHelper::fromClass('icon-text').']';
		$type = ($this->query($xpath)->one(false)->isValid()) ? 'Text' : 'Secret text';

		return $type;
	}

	/**
	 *
	 * @param type $input
	 */
	public function fill($input) {
		if (!is_array($input)) {
			$xpath = ($this->query('xpath:.//input[@type="password"]')->one(false)->isValid()) ?
					'xpath:.//input[@type="password"]' : $this->textarea_xpath;
			$this->query($xpath)->one()->fill($input);

			return $this;
		}
		if (array_key_exists('type', $input)) {
			$this->changeInputType($input['type']);
		}

		$type = CTestArrayHelper::get($input, 'type', $this->getInputType());
		if (array_key_exists('value', $input)) {
			$change_button = $this->query('button:Set new value')->one(false);
			if ($change_button->isValid()) {
				$change_button->click();
			}

			$xpath = ($type === 'Text') ? $this->textarea_xpath : 'xpath:.//input[@type="password"]';
			$this->query($xpath)->one()->fill($input['value']);
		}

		return $this;
	}
}
