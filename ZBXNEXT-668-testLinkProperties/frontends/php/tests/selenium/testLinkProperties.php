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
		/*	array(
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
					'login' => 'hosts.php?form=update&hostid=50001&groupid=111111',
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
					'login' => 'discoveryconf.php?form=update&druleid=abc',
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
					'login' => 'discoveryconf.php?form=update&druleid=',
					'title' => 'Configuration of discovery',
					'errors' => array(
						'ERROR: Zabbix has received an incorrect request.',
						'Critical error. Field "druleid" is not integer.'
					)
				)
			),
*/			// Overview
			array(
				array(
					'expected' => LINK_GOOD,
					'login' => 'overview.php?&form_refresh=1&groupid=4&application=Filesystems&type=0',
					'title' => 'Overview \[refreshed every 30 sec\]',
					'text' => 'OVERVIEW'
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'overview.php?&form_refresh=1&groupid=4444&application=Filesystems&type=0',
					'title' => 'Overview \[refreshed every 30 sec\]',
					'errors' => array(
						'ERROR: No permissions to referred object or it does not exist!'
					)
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'overview.php?&form_refresh=1&groupid=4&application=SomeApplication&type=0',
					'title' => 'Overview \[refreshed every 30 sec\]',
					'errors' => array(
						'ERROR: No permissions to referred object or it does not exist!'
					)
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'overview.php?&form_refresh=1&groupid=abc&application=Filesystems&type=0',
					'title' => 'Overview \[refreshed every 30 sec\]'
					'errors' => array(
						'ERROR: Zabbix has received an incorrect request.',
						'Critical error. Field "groupid" is not integer.'
					)
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'overview.php?&form_refresh=1&groupid=&application=Filesystems&type=0',
					'title' => 'Overview \[refreshed every 30 sec\]'
					'errors' => array(
						'ERROR: Zabbix has received an incorrect request.',
						'Critical error. Field "groupid" is not integer.'
					)
				)
			),
			// Web scenarios
		/*	array(
				array(
					'expected' => LINK_GOOD,
					'login' => 'httpdetails.php?httptestid=1',
					'title' => 'Details of scenario',
					'text' => 'DETAILS OF SCENARIO'
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'httpdetails.php?httptestid=1111',
					'title' => 'Details of scenario',
					'errors' => array(
						'ERROR: No permissions to referred object or it does not exist!'
					)
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'httpdetails.php?httptestid=abc',
					'title' => 'Details of scenario',
					'errors' => array(
						'ERROR: Zabbix has received an incorrect request.',
						'Critical error. Field "httptestid" is not integer.'
					)
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'httpdetails.php?httptestid=',
					'title' => 'Details of scenario',
					'errors' => array(
						'ERROR: Zabbix has received an incorrect request.',
						'Critical error. Field "httptestid" is not integer.'
					)
				)
			),
			// Latest data
			array(
				array(
					'expected' => LINK_GOOD,
					'login' => 'latest.php?&form_refresh=1&groupid=4&hostid=30001',
					'title' => 'Latest data \[refreshed every 30 sec\]',
					'text' => 'LATEST DATA'
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'latest.php?&form_refresh=1&groupid=4&hostid=111111',
					'title' => 'Latest data \[refreshed every 30 sec\]',
					'errors' => array(
						'ERROR: No permissions to referred object or it does not exist!'
					)
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'latest.php?&form_refresh=1&groupid=123&hostid=30001',
					'title' => 'Latest data \[refreshed every 30 sec\]',
					'errors' => array(
						'ERROR: No permissions to referred object or it does not exist!'
					)
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'latest.php?&form_refresh=1&groupid=abc&hostid=abc',
					'title' => 'Latest data \[refreshed every 30 sec\]',
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
					'login' => 'latest.php?&form_refresh=1&groupid=&hostid=',
					'title' => 'Latest data \[refreshed every 30 sec\]',
					'errors' => array(
						'ERROR: Zabbix has received an incorrect request.',
						'Critical error. Field "groupid" is not integer.',
						'Critical error. Field "hostid" is not integer.'
					)
				)
			),
			// Status of triggers
			array(
				array(
					'expected' => LINK_GOOD,
					'login' => 'tr_status.php?&form_refresh=1&groupid=4&hostid=0&fullscreen=0',
					'title' => 'Status of triggers \[refreshed every 30 sec\]',
					'text' => 'STATUS OF TRIGGERS'
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'tr_status.php?&form_refresh=1&groupid=15&hostid=0&fullscreen=0',
					'title' => 'Status of triggers \[refreshed every 30 sec\]',
					'errors' => array(
						'ERROR: No permissions to referred object or it does not exist!'
					)
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'tr_status.php?&form_refresh=1&groupid=1&hostid=1234&fullscreen=0',
					'title' => 'Status of triggers \[refreshed every 30 sec\]',
					'errors' => array(
						'ERROR: No permissions to referred object or it does not exist!'
					)
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'tr_status.php?&form_refresh=1&groupid=abc&hostid=abc&fullscreen=0',
					'title' => 'Status of triggers \[refreshed every 30 sec\]',
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
					'login' => 'tr_status.php?&form_refresh=1&groupid=&hostid=&fullscreen=0',
					'title' => 'Status of triggers \[refreshed every 30 sec\]',
					'errors' => array(
						'ERROR: Zabbix has received an incorrect request.',
						'Critical error. Field "groupid" is not integer.',
						'Critical error. Field "hostid" is not integer.'
					)
				)
			),
			// Events, also Availability report
			array(
				array(
					'expected' => LINK_GOOD,
					'login' => 'events.php?triggerid=13498',
					'title' => 'Latest events \[refreshed every 30 sec\]',
					'text' => 'HISTORY OF EVENTS'
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'events.php?triggerid=1',
					'title' => 'Latest events \[refreshed every 30 sec\]',
					'errors' => array(
						'ERROR: No permissions to referred object or it does not exist!'
					)
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'events.php?triggerid=abc',
					'title' => 'Latest events \[refreshed every 30 sec\]',
					'errors' => array(
						'ERROR: Zabbix has received an incorrect request.',
						'Critical error. Field "triggerid" is not integer.'
					)
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'events.php?triggerid=',
					'title' => 'Latest events \[refreshed every 30 sec\]',
					'errors' => array(
						'ERROR: Zabbix has received an incorrect request.',
						'Critical error. Field "triggerid" is not integer.'
					)
				)
			),
			// Custom graphs
			array(
				array(
					'expected' => LINK_GOOD,
					'login' => 'charts.php?&form_refresh=1&fullscreen=0&groupid=0&hostid=40001&graphid=0',
					'title' => 'Custom graphs \[refreshed every 30 sec\]',
					'text' => 'Graphs'
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'charts.php?&form_refresh=1&fullscreen=0&groupid=0&hostid=1&graphid=0',
					'title' => 'Custom graphs [refreshed every 30 sec]',
					'errors' => array(
						'ERROR: No permissions to referred object or it does not exist!'
					)
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'charts.php?&form_refresh=1&fullscreen=0&groupid=15&hostid=0&graphid=0',
					'title' => 'Custom graphs \[refreshed every 30 sec\]',
					'errors' => array(
						'ERROR: No permissions to referred object or it does not exist!'
					)
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'charts.php?&form_refresh=1&fullscreen=0&groupid=0&hostid=0&graphid=15',
					'title' => 'Custom graphs [refreshed every 30 sec]',
					'errors' => array(
						'ERROR: No permissions to referred object or it does not exist!'
					)
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'charts.php?&form_refresh=1&fullscreen=0&groupid=abc&hostid=abc&graphid=abc',
					'title' => 'Custom graphs \[refreshed every 30 sec\]',
					'errors' => array(
						'ERROR: Zabbix has received an incorrect request.',
						'Critical error. Field "groupid" is not integer.',
						'Critical error. Field "hostid" is not integer.',
						'Critical error. Field "graphid" is not integer.'
					)
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'charts.php?&form_refresh=1&fullscreen=0&groupid=&hostid=&graphid=',
					'title' => 'Custom graphs \[refreshed every 30 sec\]',
					'errors' => array(
						'ERROR: Zabbix has received an incorrect request.',
						'Critical error. Field "groupid" is not integer.',
						'Critical error. Field "hostid" is not integer.',
						'Critical error. Field "graphid" is not integer.'
					)
				)
			),
			// Custom screens
			array(
				array(
					'expected' => LINK_GOOD,
					'login' => 'screens.php?&form_refresh=1&fullscreen=0&elementid=200019',
					'title' => 'Custom screens \[refreshed every 30 sec\]',
					'text' => 'SCREENS'
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'screens.php?&form_refresh=1&fullscreen=0&elementid=1',
					'title' => 'Custom screens \[refreshed every 30 sec\]',
					'errors' => array(
						'ERROR: Screen with ID "1" does not exist.'
					)
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'screens.php?&form_refresh=1&fullscreen=0&elementid=abc',
					'title' => 'Custom screens \[refreshed every 30 sec\]',
					'errors' => array(
						'ERROR: Zabbix has received an incorrect request.',
						'Critical error. Field "elementid" is not integer.'
					)
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'screens.php?&form_refresh=1&fullscreen=0&elementid=',
					'title' => 'Custom screens \[refreshed every 30 sec\]',
					'errors' => array(
						'ERROR: Zabbix has received an incorrect request.',
						'Critical error. Field "elementid" is not integer.'
					)
				)
			),
			// Custom maps
			array(
				array(
					'expected' => LINK_GOOD,
					'login' => 'maps.php?&form_refresh=1&fullscreen=0&sysmapid=3',
					'title' => 'Network maps \[refreshed every 30 sec\]',
					'text' => 'NETWORK MAPS'
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'maps.php?&form_refresh=1&fullscreen=0&sysmapid=3333',
					'title' => 'Network maps \[refreshed every 30 sec\]',
					'errors' => array(
						'ERROR: No permissions to referred object or it does not exist!'
					)
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'maps.php?&form_refresh=1&fullscreen=0&sysmapid=abc',
					'title' => 'Network maps \[refreshed every 30 sec\]',
					'errors' => array(
						'ERROR: Zabbix has received an incorrect request.',
						'Critical error. Field "sysmapid" is not integer.'
					)
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'maps.php?&form_refresh=1&fullscreen=0&sysmapid=',
					'title' => 'Network maps \[refreshed every 30 sec\]',
					'errors' => array(
						'ERROR: Zabbix has received an incorrect request.',
						'Critical error. Field "sysmapid" is not integer.'
					)
				)
			),*/
			// Discovery rule
			array(
				array(
					'expected' => LINK_GOOD,
					'login' => 'discovery.php?&form_refresh=1&fullscreen=0&druleid=3',
					'title' => 'Status of discovery',
					'text' => 'STATUS OF DISCOVERY'
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'discovery.php?&form_refresh=1&fullscreen=0&druleid=11111',
					'title' => 'Status of discovery',
					'errors' => array(
						'ERROR: No permissions to referred object or it does not exist!'
					)
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'discovery.php?&form_refresh=1&fullscreen=0&druleid=abc',
					'title' => 'Status of discovery',
					'errors' => array(
						'ERROR: Zabbix has received an incorrect request.',
						'Critical error. Field "druleid" is not integer.'
					)
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'discovery.php?&form_refresh=1&fullscreen=0&druleid=',
					'title' => 'Status of discovery',
					'errors' => array(
						'ERROR: Zabbix has received an incorrect request.',
						'Critical error. Field "druleid" is not integer.'
					)
				)
			),
			// IT services
			array(
				array(
					'expected' => LINK_GOOD,
					'login' => '/srv_status.php?&form_refresh=1&fullscreen=0&period=month',
					'title' => 'IT services \[refreshed every 30 sec\]',
					'text' => 'IT SERVICES'
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => '/srv_status.php?&form_refresh=1&fullscreen=0&period=1',
					'title' => 'IT services \[refreshed every 30 sec\]',
					'errors' => array(
						'ERROR: No permissions to referred object or it does not exist!'
					)
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => '/srv_status.php?&form_refresh=1&fullscreen=0&period=abc',
					'title' => 'IT services \[refreshed every 30 sec\]',
					'errors' => array(
						'ERROR: Zabbix has received an incorrect request.',
						'Critical error. Field "druleid" is not string.'
					)
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => '/srv_status.php?&form_refresh=1&fullscreen=0&period=month',
					'title' => 'IT services \[refreshed every 30 sec\]',
					'errors' => array(
						'ERROR: Zabbix has received an incorrect request.',
						'Critical error. Field "druleid" is not string.'
					)
				)
			),
			// Inventory overview
			array(
				array(
					'expected' => LINK_GOOD,
					'login' => 'hostinventoriesoverview.php?&form_refresh=1&groupid=50003&groupby=chassis',
					'title' => 'Host inventory overview',
					'text' => 'HOST INVENTORY OVERVIEW'
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'hostinventoriesoverview.php?&form_refresh=1&groupid=78&groupby=chassis',
					'title' => 'Host inventory overview',
					'errors' => array(
						'ERROR: No permissions to referred object or it does not exist!'
					)
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'hostinventoriesoverview.php?&form_refresh=1&groupid=abc&groupby=chassis',
					'title' => 'Host inventory overview',
					'errors' => array(
						'ERROR: Zabbix has received an incorrect request.',
						'Critical error. Field "groupid" is not string.'
					)
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'hostinventoriesoverview.php?&form_refresh=1&groupid=&groupby=chassis',
					'title' => 'Host inventory overview',
					'errors' => array(
						'ERROR: Zabbix has received an incorrect request.',
						'Critical error. Field "groupid" is not string.'
					)
				)
			),
			// Host inventories
			array(
				array(
					'expected' => LINK_GOOD,
					'login' => 'hostinventories.php?&form_refresh=1&groupid=50003',
					'title' => 'Host inventories',
					'text' => 'HOST INVENTORIES'
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'hostinventories.php?&form_refresh=1&groupid=1111',
					'title' => 'Host inventories',
					'errors' => array(
						'ERROR: No permissions to referred object or it does not exist!'
					)
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'hostinventories.php?&form_refresh=1&groupid=abc',
					'title' => 'Host inventories',
					'errors' => array(
						'ERROR: Zabbix has received an incorrect request.',
						'Critical error. Field "groupid" is not string.'
					)
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'hostinventories.php?&form_refresh=1&groupid=',
					'title' => 'Host inventories',
					'errors' => array(
						'ERROR: Zabbix has received an incorrect request.',
						'Critical error. Field "groupid" is not string.'
					)
				)
			),
			// Availability report
				array(
					'expected' => LINK_GOOD,
					'login' => 'report2.php?filter_groupid=0&filter_hostid=50001&triggerid=16012',
					'title' => 'Availability report',
					'text' => 'AVAILABILITY REPORT'
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'report2.php?filter_groupid=0&filter_hostid=11111&triggerid=16012',
					'title' => 'Availability report',
					'errors' => array(
						'ERROR: No permissions to referred object or it does not exist!'
					)
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'report2.php?filter_groupid=0&filter_hostid=50001&triggerid=11111',
					'title' => 'Availability report',
					'errors' => array(
						'ERROR: No permissions to referred object or it does not exist!'
					)
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'report2.php?filter_groupid=11111&filter_hostid=50001&triggerid=16012',
					'title' => 'Availability report',
					'errors' => array(
						'ERROR: No permissions to referred object or it does not exist!'
					)
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'report2.php?filter_groupid=abc&filter_hostid=abc&triggerid=abc',
					'title' => 'Availability report',
					'errors' => array(
						'ERROR: Zabbix has received an incorrect request.',
						'Critical error. Field "triggerid" is not string.',
						'Critical error. Field "filter_groupid" is not string.',
						'Critical error. Field "filter_hostid" is not string.'
					)
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'report2.php?filter_groupid=&filter_hostid=&triggerid=',
					'title' => 'Availability report',
					'errors' => array(
						'ERROR: Zabbix has received an incorrect request.',
						'Critical error. Field "triggerid" is not string.',
						'Critical error. Field "filter_groupid" is not string.',
						'Critical error. Field "filter_hostid" is not string.'
					)
				)
			),
			// Bar reports
			array(
				array(
					'expected' => LINK_GOOD,
					'login' => 'report6.php?&form_refresh=1&form=1&config=1&items[0][caption]=item1&items[0][itemid]=24338&items[0][color]=009900&items[0][calc_fnc]=2&items[0][axisside]=0&report_timesince=20130722170101&report_timetill=20130723170101&title=Report+1&xlabel=&ylabel=&scaletype=3&report_timesince_day=22&report_timesince_month=07&report_timesince_year=2013&report_timesince_hour=17&report_timesince_minute=01&report_timetill_day=23&report_timetill_month=07&report_timetill_year=2013&report_timetill_hour=17&report_timetill_minute=01&report_show=Show',
					'title' => 'Bar reports',
					'text' => 'Report'
				)
			),
			array(
				array(
					'expected' => LINK_BAD,
					'login' => 'report6.php?&form_refresh=1&form=1&config=1&items[0][caption]=item1&items[0][itemid]=11111&items[0][color]=009900&items[0][calc_fnc]=2&items[0][axisside]=0&report_timesince=20130722170101&report_timetill=20130723170101&title=Report+1&xlabel=&ylabel=&scaletype=3&report_timesince_day=22&report_timesince_month=07&report_timesince_year=2013&report_timesince_hour=17&report_timesince_minute=01&report_timetill_day=23&report_timetill_month=07&report_timetill_year=2013&report_timetill_hour=17&report_timetill_minute=01&report_show=Show',
					'title' => 'Bar reports',
					'errors' => array(
						'ERROR: No permissions to referred object or it does not exist!'
					)
				)
			),
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
