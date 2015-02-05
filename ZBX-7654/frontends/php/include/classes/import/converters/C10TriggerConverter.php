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
 * A converted for converting 1.8 trigger expressions.
 */
class C10TriggerConverter extends CConverter {

	/**
	 * Converted used to convert simple check item keys.
	 *
	 * @var CConverter
	 */
	protected $itemKeyConverter;

	public function __construct(CConverter $itemKeyConverter) {
		$this->itemKeyConverter = $itemKeyConverter;
	}

	/**
	 * Converts simple check item keys used in trigger expressions.
	 *
	 * @param string $expression
	 *
	 * @return string
	 */
	public function convert($expression) {
		// During the refactoring this conversion code has been taken from CXmlImport18.php and it's functionality
		// has been left unchanged. It has lots of issues.
		// To convert the expressions correctly, this method needs to implement a trigger parser.
		$expressionParts = explode(':', $expression);
		$keyName = explode(',', $expressionParts[1], 2);
		if (count($keyName) == 2) {
			$keyValue = explode('.', $keyName[1], 2);
			$key = $keyName[0].",".$keyValue[0];

			$newKey = $this->itemKeyConverter->convert($key);

			$expression = str_replace($key, $newKey, $expression);
		}

		return $expression;
	}

}
