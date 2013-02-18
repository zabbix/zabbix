<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * Helper class that simplifies working with CEventDescription class.
 */
class CEventHelper {

	/**
	 * @var CEventDescription
	 */
	private static $eDescription;

	/**
	 * Helper for CEventDescription->expand.
	 *
	 * @static
	 * @see CEventDescription
	 *
	 * @param array $event
	 *
	 * @return string
	 */
	public static function expandDescription(array $event) {
		self::init();
		return self::$eDescription->expand($event);
	}

	/**
	 * Create CEventDescription object and store in static variable.
	 *
	 * @static
	 */
	private static function init() {
		if (self::$eDescription === null) {
			self::$eDescription = new CEventDescription();
		}
	}
}

