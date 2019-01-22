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
		if (strpos($text, '"') !== false) {
			return '\''.$text.'\'';
		}
		elseif (strpos($text, '\'') === false) {
			return '"'.$text.'"';
		}

		throw new Exception('Cannot escape XPath param containing both quote and apostrophe characters.');
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
	public function fromSelector($type, $locator = null) {
		$selector = CElementQuery::getSelector($type, $locator);

		switch ($selector->getMechanism()) {
			case 'class name':
				return '*[@class='.static::escapeQuotes($selector->getValue()).']';

			case 'id':
				return '*[@id='.static::escapeQuotes($selector->getValue()).']';

			case 'name':
				return '*[@name='.static::escapeQuotes($selector->getValue()).']';

			case 'link text':
				return 'a[string()='.static::escapeQuotes($selector->getValue()).']';

			case 'partial link text':
				return 'a[contains(string(), '.static::escapeQuotes($selector->getValue()).')]';

			case 'tag name':
				return $selector->getValue();

			case 'xpath':
				return ltrim($selector->getValue(), './');
		}

		throw new Exception('Not supported selector type "'.$type.'".');
	}
}
