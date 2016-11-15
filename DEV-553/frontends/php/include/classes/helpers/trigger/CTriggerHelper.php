<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
 * Helper class that simplifies working with CTriggerDescription class.
 */
class CTriggerHelper {

	/**
	 * @var CTriggerDescription
	 */
	private static $tDescription;

	/**
	 * Helper for CTriggerDescription->expand.
	 *
	 * @static
	 * @see CTriggerDescription
	 *
	 * @param array $trigger
	 *
	 * @return string
	 */
	public static function expandDescription(array $trigger) {
		self::init();
		return self::$tDescription->expand($trigger);
	}

	/**
	 * Helper for CTriggerDescription->batchExpand.
	 *
	 * @static
	 * @see CTriggerDescription
	 *
	 * @param array $triggers
	 *
	 * @return array
	 */
	public static function batchExpandDescription(array $triggers) {
		self::init();
		return self::$tDescription->batchExpand($triggers);
	}

	/**
	 * Helper for CTriggerDescription->expandById.
	 *
	 * @static
	 * @see CTriggerDescription
	 *
	 * @param string $triggerId
	 *
	 * @return string
	 */
	public static function expandDescriptionById($triggerId) {
		self::init();
		return self::$tDescription->expandById($triggerId);
	}

	/**
	 * Helper for CTriggerDescription->batchExpandById.
	 *
	 * @static
	 * @see CTriggerDescription
	 *
	 * @param array $triggerIds
	 *
	 * @return array
	 */
	public static function batchExpandDescriptionById(array $triggerIds) {
		self::init();
		return self::$tDescription->batchExpandById($triggerIds);
	}

	/**
	 * Helper for CTriggerDescription->expandReferenceMacros.
	 *
	 * @static
	 * @see CTriggerDescription
	 *
	 * @param array $trigger
	 *
	 * @return string
	 */
	public static function expandReferenceMacros(array $trigger) {
		self::init();
		return self::$tDescription->expandReferenceMacros($trigger);
	}

	/**
	 * Create CTriggerDescription object and store in static variable.
	 *
	 * @static
	 */
	private static function init() {
		if (self::$tDescription === null) {
			self::$tDescription = new CTriggerDescription();
		}
	}
}
