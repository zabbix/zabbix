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
	 * A parser for function macros.
	 *
	 * @var CFunctionMacroParser
	 */
	protected $functionMacroParser;

	/**
	 * Converted used to convert simple check item keys.
	 *
	 * @var CConverter
	 */
	protected $itemKeyConverter;

	public function __construct() {
		$this->functionMacroParser = new CFunctionMacroParser(['18_simple_checks' => true]);
		$this->itemKeyConverter = new C10ItemKeyConverter();
	}

	/**
	 * Converts simple check item keys used in trigger expressions.
	 *
	 * @param string $expression
	 *
	 * @return string
	 */
	public function convert($expression) {
		$new_expression = '';

		for ($pos = 0; isset($expression[$pos]); $pos++) {
			if ($expression[$pos] == '{') {
				$result = $this->functionMacroParser->parse($expression, $pos);

				if ($result) {
					$new_expression .= '{'.
						$result->expression['host'].':'.
						$this->itemKeyConverter->convert($result->expression['item']).'.'.
						$result->expression['function'].
					'}';

					$pos += $result->length - 1;
				}
				else {
					$new_expression .= $expression[$pos];
				}
			}
			else {
				$new_expression .= $expression[$pos];
			}
		}

		return $new_expression;
	}

}
