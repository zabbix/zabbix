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


require_once __DIR__.'/../common/testTimeoutsDisplay.php';

/**
 * @onBefore prepareTimeoutsData
 *
 * @backup config, hosts, proxy
 *
 * TODO: remove ignoreBrowserErrors after DEV-4233
 * @ignoreBrowserErrors
 */
class testTimeoutsTemplates extends testTimeoutsDisplay {

	protected static $templateid;
	protected static $template_druleid;

	public static function prepareTimeoutsData() {
		$template_result = CDataHelper::createTemplates([
			[
				'host' => 'Template for timeouts check',
				'groups' => ['groupid' => 1], // Templates.
				'discoveryrules' => [
					[
						'name' => 'Zabbix agent drule',
						'key_' => 'zabbix_agent_drule',
						'type' => ITEM_TYPE_ZABBIX,
						'delay' => 5
					]
				]
			]
		]);
		self::$templateid = $template_result['templateids']['Template for timeouts check'];
		self::$template_druleid = $template_result['discoveryruleids']['Template for timeouts check:zabbix_agent_drule'];
	}

	public function testTimeoutsTemplates_CheckItemsMacros() {
		$link = 'zabbix.php?action=item.list&filter_set=1&context=template&filter_hostids%5B0%5D='.self::$templateid;
		$this->checkGlobal('global_macros', $link, 'Create item');
	}

	public function testTimeoutsTemplates_CheckDiscoveryMacros() {
		$link = 'host_discovery.php?filter_set=1&context=template&filter_hostids%5B0%5D='.self::$templateid;
		$this->checkGlobal('global_macros', $link, 'Create discovery rule');
	}

	public function testTimeoutsTemplates_CheckPrototypeMacros() {
		$link = 'zabbix.php?action=item.prototype.list&context=template&parent_discoveryid='.self::$template_druleid;
		$this->checkGlobal('global_macros', $link, 'Create item prototype');
	}

	public function testTimeoutsTemplates_CheckItemsCustom() {
		$link = 'zabbix.php?action=item.list&context=template&filter_set=1&filter_hostids%5B0%5D='.self::$templateid;
		$this->checkGlobal('global_custom', $link, 'Create item');
	}

	public function testTimeoutsTemplates_CheckDiscoveryCustom() {
		$link = 'host_discovery.php?filter_set=1&context=template&filter_hostids%5B0%5D='.self::$templateid;
		$this->checkGlobal('global_custom', $link, 'Create discovery rule');
	}

	public function testTimeoutsTemplates_CheckPrototypeCustom() {
		$link = 'zabbix.php?action=item.prototype.list&context=template&parent_discoveryid='.self::$template_druleid;
		$this->checkGlobal('global_custom', $link, 'Create item prototype');
	}

	public function testTimeoutsTemplates_CheckItemsDefault() {
		$link = 'zabbix.php?action=item.list&context=template&filter_set=1&filter_hostids%5B0%5D='.self::$templateid;
		$this->checkGlobal('global_default', $link, 'Create item');
	}

	public function testTimeoutsTemplates_CheckDiscoveryDefault() {
		$link = 'host_discovery.php?filter_set=1&context=template&filter_hostids%5B0%5D='.self::$templateid;
		$this->checkGlobal('global_default', $link, 'Create discovery rule');
	}

	public function testTimeoutsTemplates_CheckPrototypeDefault() {
		$link = 'zabbix.php?action=item.prototype.list&context=template&parent_discoveryid='.self::$template_druleid;
		$this->checkGlobal('global_default', $link, 'Create item prototype');
	}
}
