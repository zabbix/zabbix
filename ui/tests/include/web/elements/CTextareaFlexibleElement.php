<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
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


//require_once 'vendor/autoload.php';
require_once __DIR__.'/../CElement.php';

/**
 * TextareaFlexible element.
 */
class CTextareaFlexibleElement extends CElement {
	/**
	 * Get value of InputGroup element.
	 *
	 * @return $this
	 */
	public function clear() {
		$driver = CElementQuery::getDriver();
		$driver->executeScript('arguments[0].value="";', [$this]);

		return $this;
	}

//	/**
//	 * Get value of InputGroup element.
//	 *
//	 * @return $this
//	 */
//	public function fill($text) {
//		CElement::fill($text);
//	}
}

