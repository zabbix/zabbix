<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
	 * Set checkbox state.
	 *
	 * @param boolean $checked    checked or not
	 *
	 * @return $this
	 */
	public function set($checked) {
		if ($checked !== $this->isSelected()) {
			CElementQuery::getDriver()->executeScript('arguments[0].click();', [$this]);
		}

		return $this;
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
	 * @return CElement|null
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
		if (($label = $this->getLabel()) !== null) {
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
