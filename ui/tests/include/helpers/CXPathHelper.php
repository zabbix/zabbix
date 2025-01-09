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

/**
 * XPath helper.
 */
class CXPathHelper {

	/**
	 * Escape quotes in XPath param.
	 *
	 * @param string $text
	 *
	 * @return string
	 *
	 * @throws Exception
	 */
	public static function escapeQuotes($text) {
		if (strpos($text, '"') === false) {
			return '"'.$text.'"';
		}
		elseif (strpos($text, '\'') === false) {
			return '\''.$text.'\'';
		}

		$parts = explode('"', $text);
		foreach ($parts as &$part) {
			$part = '"'.$part.'"';
		}
		unset ($part);

		return 'concat('.implode(',\'"\',', $parts).')';
	}

	/**
	 * Get XPath selector from specified selector.
	 *
	 * @param mixed  $type     selector type (method) or selector
	 * @param string $locator  locator part of selector
	 *
	 * @return string
	 *
	 * @throws Exception
	 */
	public static function fromSelector($type, $locator = null) {
		return self::fromWebDriverBy(CElementQuery::getSelector($type, $locator));
	}

	/**
	 * Get XPath selector from WebDriverBy selector.
	 *
	 * @param WebDriverBy  $by     selector
	 *
	 * @return string
	 *
	 * @throws Exception
	 */
	public static function fromWebDriverBy($by) {
		switch ($by->getMechanism()) {
			case 'class name':
				return '*['.static::fromClass($by->getValue()).']';

			case 'id':
				return '*[@id='.static::escapeQuotes($by->getValue()).']';

			case 'name':
				return '*[@name='.static::escapeQuotes($by->getValue()).']';

			case 'link text':
				return 'a[string()='.static::escapeQuotes($by->getValue()).']';

			case 'partial link text':
				return 'a[contains(string(), '.static::escapeQuotes($by->getValue()).')]';

			case 'tag name':
				return $by->getValue();

			case 'xpath':
				return ltrim($by->getValue(), './');
		}

		throw new Exception('Not supported selector type "'.$by->getMechanism().'".');
	}

	/**
	 * Get XPath selector from class.
	 *
	 * @param string  $class     class to be converted to XPath selector
	 *
	 * @return string
	 */
	public static function fromClass($class) {
		$length = strlen(' '.$class);

		return '@class="'.$class.'" or contains(@class,'.static::escapeQuotes(' '.$class.' ').
				') or starts-with(@class, '.static::escapeQuotes($class.' ').') or'.
				' substring(@class, string-length(@class)-'.($length - 1).')='.
				static::escapeQuotes(' '.$class).'';
	}
}
