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
 * Checkbox element.
 */
class CCheckboxElement extends CElement {

	/**
	 * Alias for isSelected.
	 * @see self::isSelected
	 */
	public function isChecked($checked = true) {
		return $this->isSelected($checked);
	}

	/**
	 * @inheritdoc
	 */
	public function isVisible($visible = true) {
		return $this->parents()->query('tag:label')->one(false)->isVisible($visible);
	}

	/**
	 * Set checkbox state.
	 *
	 * @param boolean $checked    checked or not
	 *
	 * @return $this
	 */
	public function set($checked) {
		if ($checked !== $this->isSelected()) {
			$this->click(true);
		}

		return $this;
	}

	/**
	 * @inheritdoc
	 */
	public function getValue() {
		return $this->isChecked();
	}

	/**
	 * Set checkbox state to checked.
	 *
	 * @return $this
	 */
	public function check() {
		return $this->set(true);
	}

	/**
	 * Set checkbox state to not checked.
	 *
	 * @return $this
	 */
	public function uncheck() {
		return $this->set(false);
	}

	/**
	 * Get label element.
	 *
	 * @return CElement|CNullElement
	 */
	public function getLabel() {
		return $this->query('xpath:../label')->one(false);
	}

	/**
	 * Get label text.
	 *
	 * @return string|null
	 */
	public function getText() {
		$label = $this->getLabel();
		if ($label->isValid()) {
			return $label->getText();
		}

		return null;
	}

	/**
	 * Alias for set.
	 * @see self::set
	 *
	 * @param boolean $checked    checked or not
	 *
	 * @return $this
	 */
	public function fill($checked) {
		return $this->set($checked);
	}
}
