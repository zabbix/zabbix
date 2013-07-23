<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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

require_once dirname(__FILE__).'/../include/class.cwebtest.php';

define('LINK_GOOD', 0);
define('LINK_BAD', 1);

class testLinkProperties extends CWebTest {

	// Returns test data
	public static function zbx_data() {
		return array(
			// Host groups
			array(
				array(
					'expected' => LINK_GOOD,
					'login' => 'hostgroups.php?form=update&groupid=1',
					'title' => 'Configuration of host groups',
					'text' => 'CONFIGURATION OF HOST GROUPS'
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'hostgroups.php?form=update&groupid=123',
					'title' => 'Configuration of host groups',
					'errors' => array(
						'ERROR: No permissions to referred object or it does not exist!'
					)
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'hostgroups.php?form=update&groupid=abc',
					'title' => 'Configuration of host groups',
					'errors' => array(
						'ERROR: Zabbix has received an incorrect request.',
						'Critical error. Field "groupid" is not integer.'
					)
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'hostgroups.php?form=update&groupid=',
					'title' => 'Configuration of host groups',
					'errors' => array(
						'ERROR: Zabbix has received an incorrect request.',
						'Critical error. Field "groupid" is not integer.'
					)
				)
			),
			// Templates
			array(
				array(
					'expected' => LINK_GOOD,
					'login' => 'templates.php?form=update&templateid=40000&groupid=0',
					'title' => 'Configuration of templates',
					'text' => 'CONFIGURATION OF TEMPLATES'
				)
			),
			array(
				array(
					'expected' => LINK_GOOD,
					'login' => 'templates.php?form=update&templateid=50000&groupid=50000',
					'title' => 'Configuration of templates',
					'text' => 'CONFIGURATION OF TEMPLATES'
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'templates.php?form=update&templateid=1&groupid=1',
					'title' => 'Configuration of templates',
					'errors' => array(
						'ERROR: No permissions to referred object or it does not exist!'
					)
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'templates.php?form=update&templateid=abc&groupid=abc',
					'title' => 'Configuration of templates',
					'errors' => array(
						'ERROR: Zabbix has received an incorrect request.',
						'Critical error. Field "templateid" is not integer.',
						'Critical error. Field "groupid" is not integer.'
					)
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'templates.php?form=update&templateid=&groupid=',
					'title' => 'Configuration of templates',
					'errors' => array(
						'ERROR: Zabbix has received an incorrect request.',
						'Critical error. Field "templateid" is not integer.',
						'Critical error. Field "groupid" is not integer.'
					)
				)
			),
			// Hosts
			array(
				array(
					'expected' => LINK_GOOD,
					'login' => 'hosts.php?form=update&hostid=50001&groupid=0',
					'title' => 'Configuration of hosts',
					'text' => 'CONFIGURATION OF HOSTS'
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'hosts.php?form=update&hostid=50001&groupid=50001',
					'title' => 'Configuration of hosts',
					'errors' => array(
						'ERROR: No permissions to referred object or it does not exist!'
					)
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'hosts.php?form=update&hostid=1&groupid=0',
					'title' => 'Configuration of hosts',
					'errors' => array(
						'ERROR: No permissions to referred object or it does not exist!'
					)
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'hosts.php?form=update&hostid=abc&groupid=abc',
					'title' => 'Configuration of hosts',
					'errors' => array(
						'ERROR: Zabbix has received an incorrect request.',
						'Critical error. Field "groupid" is not integer.',
						'Critical error. Field "hostid" is not integer.'
					)
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'hosts.php?form=update&hostid=&groupid=',
					'title' => 'Configuration of hosts',
					'errors' => array(
						'ERROR: Zabbix has received an incorrect request.',
						'Critical error. Field "groupid" is not integer.',
						'Critical error. Field "hostid" is not integer.'
					)
				)
			),
			// Maintenance
			array(
				array(
					'expected' => LINK_GOOD,
					'login' => 'maintenance.php?form=update&maintenanceid=1#form',
					'title' => 'Configuration of maintenance',
					'text' => 'CONFIGURATION OF MAINTENANCE PERIODS'
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'maintenance.php?form=update&maintenanceid=2000#form',
					'title' => 'Configuration of maintenance',
					'errors' => array(
						'ERROR: No permissions to referred object or it does not exist!'
					)
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'maintenance.php?form=update&maintenanceid=abc#form',
					'title' => 'Configuration of maintenance',
					'errors' => array(
						'ERROR: Zabbix has received an incorrect request.',
						'Critical error. Field "maintenanceid" is not integer.'
					)
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'maintenance.php?form=update&maintenanceid=',
					'title' => 'Configuration of maintenance',
					'errors' => array(
						'ERROR: Zabbix has received an incorrect request.',
						'Critical error. Field "maintenanceid" is not integer.'
					)
				)
			),
			// Actions
			array(
				array(
					'expected' => LINK_GOOD,
					'login' => 'actionconf.php?form=update&actionid=11',
					'title' => 'Configuration of actions',
					'text' => 'CONFIGURATION OF ACTIONS'
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'actionconf.php?form=update&actionid=12222',
					'title' => 'Configuration of actions',
					'errors' => array(
						'ERROR: No permissions to referred object or it does not exist!'
					)
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'actionconf.php?form=update&actionid=abc',
					'title' => 'Configuration of actions',
					'errors' => array(
						'ERROR: Zabbix has received an incorrect request.',
						'Critical error. Field "actionid" is not integer.'
					)
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'actionconf.php?form=update&actionid=',
					'title' => 'Configuration of actions',
					'errors' => array(
						'ERROR: Zabbix has received an incorrect request.',
						'Critical error. Field "actionid" is not integer.'
					)
				)
			),
			// Screens
			array(
				array(
					'expected' => LINK_GOOD,
					'login' => 'screenedit.php?screenid=200000',
					'title' => 'Configuration of screens',
					'text' => 'CONFIGURATION OF SCREEN'
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'screenedit.php?screenid=111111',
					'title' => 'Configuration of screens',
					'errors' => array(
						'ERROR: No permissions to referred object or it does not exist!'
					)
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'screenedit.php?screenid=abc',
					'title' => 'Configuration of screens',
					'errors' => array(
						'ERROR: Zabbix has received an incorrect request.',
						'Critical error. Field "screenid" is not integer.'
					)
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'screenedit.php?screenid=',
					'title' => 'Configuration of screens',
					'errors' => array(
						'ERROR: Zabbix has received an incorrect request.',
						'Critical error. Field "screenid" is not integer.'
					)
				)
			),
			// Slide shows
			array(
				array(
					'expected' => LINK_GOOD,
					'login' => 'slideconf.php?config=1&form=update&slideshowid=200001',
					'title' => 'Configuration of slide shows',
					'text' => 'CONFIGURATION OF SLIDE SHOWS'
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'slideconf.php?config=1&form=update&slideshowid=111111',
					'title' => 'Configuration of slide shows',
					'errors' => array(
						'ERROR: No permissions to referred object or it does not exist!'
					)
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'slideconf.php?config=1&form=update&slideshowid=abc',
					'title' => 'Configuration of slide shows',
					'errors' => array(
						'ERROR: Zabbix has received an incorrect request.',
						'Critical error. Field "slideshowid" is not integer.'
					)
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'slideconf.php?config=1&form=update&slideshowid=',
					'title' => 'Configuration of slide shows',
					'errors' => array(
						'ERROR: Zabbix has received an incorrect request.',
						'Critical error. Field "slideshowid" is not integer.'
					)
				)
			),
			// Maps
			array(
				array(
					'expected' => LINK_GOOD,
					'login' => 'sysmap.php?sysmapid=3',
					'title' => 'Configuration of network maps',
					'text' => 'CONFIGURATION OF NETWORK MAPS'
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'sysmap.php?sysmapid=111',
					'title' => 'Configuration of network maps',
					'errors' => array(
						'ERROR: No permissions to referred object or it does not exist!'
					)
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'sysmap.php?sysmapid=abc',
					'title' => 'Configuration of network maps',
					'errors' => array(
						'ERROR: Zabbix has received an incorrect request.',
						'Critical error. Field "sysmapid" is not integer.'
					)
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'sysmap.php?sysmapid=',
					'title' => 'Configuration of network maps',
					'errors' => array(
						'ERROR: Zabbix has received an incorrect request.',
						'Critical error. Field "sysmapid" is not integer.'
					)
				)
			),
			// Discovery rule
			array(
				array(
					'expected' => LINK_GOOD,
					'login' => 'discoveryconf.php?form=update&druleid=3',
					'title' => 'Configuration of discovery',
					'text' => 'CONFIGURATION OF DISCOVERY RULE'
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'discoveryconf.php?form=update&druleid=111',
					'title' => 'Configuration of discovery',
					'errors' => array(
						'ERROR: No permissions to referred object or it does not exist!'
					)
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'discoveryconf.php?form=update&druleid=3',
					'title' => 'Configuration of discovery',
					'errors' => array(
						'ERROR: Zabbix has received an incorrect request.',
						'Critical error. Field "druleid" is not integer.'
					)
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'discoveryconf.php?form=update&druleid=3',
					'title' => 'Configuration of discovery',
					'errors' => array(
						'ERROR: Zabbix has received an incorrect request.',
						'Critical error. Field "druleid" is not integer.'
					)
				)
			)
		);
	}

	/**
	 * @dataProvider zbx_data
	 */
	public function testLinkProperties_linkLoad($zbx_data) {
		$this->zbxTestLogin($zbx_data['login']);
		switch ($zbx_data['expected']) {
			case LINK_GOOD:
				$this->checkTitle($zbx_data['title']);
				$this->zbxTestTextPresent($zbx_data['text']);
				break;
			case LINK_BAD:
				$this->checkTitle($zbx_data['title']);
				foreach ($zbx_data['errors'] as $msg) {
					$this->zbxTestTextPresent($msg);
				}
				break;
			}

	}
}
