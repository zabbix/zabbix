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
				return '*[@class='.static::escapeQuotes($by->getValue()).']';

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
}
