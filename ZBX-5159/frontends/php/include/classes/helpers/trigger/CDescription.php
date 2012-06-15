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
 * Helper class that simplifies working with CTriggerDescription class.
 */
class CDescription {

	/**
	 * @var CTriggerDescription
	 */
	private static $tDescription;

	/**
	 * @var CEventDescription
	 */
	private static $eDescription;

	/**
	 * Helper for CTriggerDescription->addTrigger.
	 *
	 * @static
	 * @see CTriggerDescription
	 *
	 * @param array $trigger
	 *
	 * @return string
	 */
	public static function expandTrigger(array $trigger) {
		self::initTriggers();
		self::$tDescription->addTrigger($trigger);
		return self::$tDescription->expand();
	}

	/**
	 * Helper for CTriggerDescription->addTriggers.
	 *
	 * @static
	 * @see CTriggerDescription
	 *
	 * @param array $triggers
	 *
	 * @return array
	 */
	public static function expandTriggers(array $triggers) {
		self::initTriggers();
		self::$tDescription->addTriggers($triggers);
		return self::$tDescription->expand();
	}

	/**
	 * Helper for CTriggerDescription->addTriggerById.
	 *
	 * @static
	 * @see CTriggerDescription
	 *
	 * @param string $triggerId
	 *
	 * @return string
	 */
	public static function expandTriggerById($triggerId) {
		self::initTriggers();
		self::$tDescription->addTriggerById($triggerId);
		return self::$tDescription->expand();
	}

	/**
	 * Helper for CTriggerDescription->addTriggersById.
	 *
	 * @static
	 * @see CTriggerDescription
	 *
	 * @param array $triggerIds
	 *
	 * @return array
	 */
	public static function expandTriggersById(array $triggerIds) {
		self::initTriggers();
		self::$tDescription->addTriggersById($triggerIds);
		return self::$tDescription->expand();
	}

	public static function expandReferenceMacros(array $trigger) {
		self::initTriggers();
		return self::$tDescription->expandReferenceMacros($trigger);
	}

	/**
	 * Helper for CEventDescription->addTrigger.
	 *
	 * @static
	 * @see CEventDescription
	 *
	 * @param array $event
	 *
	 * @return string
	 */
	public static function expandEvent(array $event) {
		self::initEvents();
		self::$eDescription->addTrigger($event);
		return self::$eDescription->expand();
	}

	/**
	 * Create CTriggerDescription object and store in static variable.
	 *
	 * @static
	 */
	private static function initTriggers() {
		if (self::$tDescription === null) {
			self::$tDescription = new CTriggerDescription();
		}
	}

	/**
	 * Create CEventDescription object and store in static variable.
	 *
	 * @static
	 */
	private static function initEvents() {
		if (self::$eDescription === null) {
			self::$eDescription = new CEventDescription();
		}
	}
}
