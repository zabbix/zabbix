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


abstract class CControllerPopupItemTest extends CController {
	/**
	 * Types of preprocessing tests, depending on type of item.
	 */
	const ZBX_TEST_TYPE_ITEM = 0;
	const ZBX_TEST_TYPE_ITEM_PROTOTYPE = 1;
	const ZBX_TEST_TYPE_LLD = 2;

	/**
	 * Define a set of item types allowed to test.
	 *
	 * @var array
	 */
	public static $testable_item_types = [ITEM_TYPE_ZABBIX, ITEM_TYPE_SIMPLE, ITEM_TYPE_SNMPV1, ITEM_TYPE_SNMPV2C,
		ITEM_TYPE_SNMPV3, ITEM_TYPE_INTERNAL, ITEM_TYPE_AGGREGATE, ITEM_TYPE_EXTERNAL, ITEM_TYPE_DB_MONITOR,
		ITEM_TYPE_HTTPAGENT, ITEM_TYPE_IPMI, ITEM_TYPE_SSH, ITEM_TYPE_TELNET, ITEM_TYPE_JMX, ITEM_TYPE_CALCULATED
	];

	/**
	 * Item value type used if user has not specified one.
	 */
	const ZBX_DEFAULT_VALUE_TYPE = ITEM_VALUE_TYPE_TEXT;

	/**
	 * @var object
	 */
	protected $preproc_item;

	/**
	 * @var array
	 */
	protected static $preproc_steps_using_prev_value = [ZBX_PREPROC_DELTA_VALUE, ZBX_PREPROC_DELTA_SPEED,
		ZBX_PREPROC_THROTTLE_VALUE, ZBX_PREPROC_THROTTLE_TIMED_VALUE
	];

	protected function checkPermissions() {
		return ($this->getUserType() >= USER_TYPE_ZABBIX_ADMIN);
	}

	protected function getPreprocessingItemType($test_type) {
		switch ($test_type) {
			case self::ZBX_TEST_TYPE_ITEM:
				return new CItem;

			case self::ZBX_TEST_TYPE_ITEM_PROTOTYPE:
				return new CItemPrototype;

			case self::ZBX_TEST_TYPE_LLD:
				return new CDiscoveryRule;
		}
	}

	protected function getHostProxies() {
		$proxies = API::Proxy()->get([
			'output' => ['host'],
			'preservekeys' => true
		]);

		CArrayHelper::sort($proxies, [['field' => 'host', 'order' => ZBX_SORT_UP]]);

		return $proxies;
	}
}
