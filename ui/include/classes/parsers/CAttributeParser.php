<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
 * Attribute parser, syntax: <attribute>="<value>"
 */
class CAttributeParser extends CParser {

	/**
	 * Parsed name part value.
	 *
	 * @var string
	 */
	public $name;

	/**
	 * Parsed value part value.
	 *
	 * @var string
	 */
	public $value;

	/**
	 * Attribute names whitelist.
	 *
	 * @var array
	 */
	public $names = ['tag', 'group'];

	/**
	 * Parse the given attribute.
	 *
	 * @param string $source  Source string that needs to be parsed.
	 * @param int    $pos     Position offset.
	 */
	public function parse($source, $pos = 0) {
		$this->length = 0;
		$this->name = '';
		$this->value = '';
		$this->match = '';

		$p = $pos;

		if (!$this->parseAttribute($source, $p)) {
			return self::PARSE_FAIL;
		}

		$this->length = $p - $pos;
		$this->match = substr($source, $pos, $this->length);

		return isset($source[$p]) ? self::PARSE_SUCCESS_CONT : self::PARSE_SUCCESS;
	}

	/**
	 * Parse attribute to name and quoted value. Modifies char position in source string.
	 *
	 * @param string $source
	 * @param int    $pos
	 */
	protected function parseAttribute($source, &$pos) {
		$name = '(?P<name>('.implode('|', $this->names).'))';
		$value = '(?P<value>"[^"]+")';
		$pattern = $name.'='.$value;

		if (!preg_match('/^'.$pattern.'/', substr($source, $pos), $matches)) {
			return false;
		}

		if (!array_key_exists('name', $matches) || !array_key_exists('value', $matches)) {
			return false;
		}

		$this->name = $matches['name'];
		$this->value = $matches['value'];
		$pos += strlen($matches[0]);

		return true;
	}
}
