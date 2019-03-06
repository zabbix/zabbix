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
 * Range control element.
 */
class CRangeControlElement extends CElement {

	/**
	 * Get range control input field.
	 *
	 * @return type
	 */
	public function getInput() {
		return $this->query('xpath:./input')->waitUntilVisible()->one();
	}

	/**
	 * Select range control value.
	 *
	 * @inheritdoc
	 */
	public function selectValue() {
		$this->getInput()->selectValue();

		return $this;
	}

	/**
	 * Overwrite range control value.
	 *
	 * @inheritdoc
	 */
	public function overwrite($text) {
		$this->getInput()->overwrite($text);

		return $this;
	}
}
