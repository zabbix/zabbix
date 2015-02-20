<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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


/**
 * A class for rendering button elements.
 *
 * Should be used as a newer alternative to CButton.
 */
class CSimpleButton extends CTag implements CButtonInterface {

	/**
	 * Button class that will be added to the other classes of the element.
	 *
	 * @var string
	 */
	protected $buttonClass;

	public function __construct($caption = '', $buttonClass = 'button-plain shadow ui-corner-all') {
		parent::__construct('button', 'yes', $caption, 'button');
		$this->setAttribute('type', 'button');

		$this->buttonClass = $buttonClass;
	}

	/**
	 * Mark the button as main.
	 */
	public function main() {
		$this->addClass('main');
	}

	/**
	 * Enable or disable the element.
	 *
	 * @param bool $value
	 */
	public function setEnabled($value) {
		if ($value) {
			$this->removeAttribute('disabled');
		}
		else {
			$this->attr('disabled', 'disabled');
		}
	}

	/**
	 * @see CButtonInterface::setButtonClass()
	 */
	public function setButtonClass($class) {
		$this->buttonClass = $class;
	}

	public function toString($destroy = true) {
		// append the button class
		if ($this->buttonClass !== null) {
			$this->addClass($this->buttonClass);
		}

		return parent::toString($destroy);
	}
}
