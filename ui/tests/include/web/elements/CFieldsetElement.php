<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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
 * Fieldset element.
 */
class CFieldsetElement extends CElement {

	/**
	 * Determine whether element is open or not.
	 *
	 * @return boolean
	 */
	public function isOpen($open = true) {
		return $this->hasClass('collapsed') != $open;
	}

	/**
	 * Set fieldset state.
	 *
	 * @param boolean $open    open or not
	 *
	 * @return $this
	 */
	public function set($open) {
		if ($open !== $this->isOpen()) {
			$this->query('tag:button')->one()->click();
		}

		return $this;
	}

	/**
	 * @inheritdoc
	 */
	public function getValue() {
		return $this->isOpen();
	}

	/**
	 * Set fieldset state to open.
	 *
	 * @return $this
	 */
	public function open() {
		return $this->set(true);
	}

	/**
	 * Set fieldset state to close.
	 *
	 * @return $this
	 */
	public function close() {
		return $this->set(false);
	}

	/**
	 * Get label element.
	 *
	 * @return CElement|CNullElement
	 */
	public function getLabel() {
		return $this->query('xpath:./legend/button/span')->one(false);
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
	 * @param boolean $open    open or not
	 *
	 * @return $this
	 */
	public function fill($open) {
		return $this->set($open);
	}
}
