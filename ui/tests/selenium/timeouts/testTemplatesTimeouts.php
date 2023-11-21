<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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


require_once dirname(__FILE__).'/../common/testTimeoutsDisplay.php';

/**
 * @onBefore prepareTimeoutsData
 *
 * @backup config, hosts, proxy
 */
class testTemplatesTimeouts extends testTimeoutsDisplay {

	protected static $templateid;
	protected static $template_druleids;

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
		self::$templateid = $template_result['templateids'];
		self::$template_druleids = $template_result['discoveryruleids'];
	}

	public function testTemplatesTimeouts_checkItemsMacros() {
		$link = 'zabbix.php?action=item.list&filter_set=1&context=template&filter_hostids%5B0%5D='.
				self::$templateid['Template for timeouts check'];
		$this->checkGlobal('global_macros', $link, 'Create item');
	}

	public function testTemplatesTimeouts_checkDiscoveryMacros() {
		$link = 'host_discovery.php?filter_set=1&context=template&filter_hostids%5B0%5D='.
				self::$templateid['Template for timeouts check'];
		$this->checkGlobal('global_macros', $link, 'Create discovery rule');
	}

	public function testHostsTimeouts_checkPrototypeMacros() {
		$link = 'zabbix.php?action=item.prototype.list&context=template&parent_discoveryid='.
				self::$template_druleids['Template for timeouts check:zabbix_agent_drule'];
		$this->checkGlobal('global_macros', $link, 'Create item prototype');
	}

	public function testHostsTimeouts_checkItemsCustom() {
		$link = 'zabbix.php?action=item.list&context=template&filter_set=1&filter_hostids%5B0%5D='.
				self::$templateid['Template for timeouts check'];
		$this->checkGlobal('global_custom', $link, 'Create item');
	}

	public function testHostsTimeouts_checkDiscoveryCustom() {
		$link = 'host_discovery.php?filter_set=1&context=template&filter_hostids%5B0%5D='.
				self::$templateid['Template for timeouts check'];
		$this->checkGlobal('global_custom', $link, 'Create discovery rule');
	}

	public function testHostsTimeouts_checkPrototypeCustom() {
		$link = 'zabbix.php?action=item.prototype.list&context=template&parent_discoveryid='.
				self::$template_druleids['Template for timeouts check:zabbix_agent_drule'];
		$this->checkGlobal('global_custom', $link, 'Create item prototype');
	}

	public function testHostsTimeouts_checkItemsDefault() {
		$link = 'zabbix.php?action=item.list&context=template&filter_set=1&filter_hostids%5B0%5D='.
				self::$templateid['Template for timeouts check'];
		$this->checkGlobal('global_default', $link, 'Create item');
	}

	public function testHostsTimeouts_checkDiscoveryDefault() {
		$link = 'host_discovery.php?filter_set=1&context=template&filter_hostids%5B0%5D='.
				self::$templateid['Template for timeouts check'];
		$this->checkGlobal('global_default', $link, 'Create discovery rule');
	}

	public function testHostsTimeouts_checkPrototypeDefault() {
		$link = 'zabbix.php?action=item.prototype.list&context=template&parent_discoveryid='.
				self::$template_druleids['Template for timeouts check:zabbix_agent_drule'];
		$this->checkGlobal('global_default', $link, 'Create item prototype');
	}
}
