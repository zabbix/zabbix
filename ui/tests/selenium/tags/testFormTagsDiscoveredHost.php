<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

require_once dirname(__FILE__).'/../common/testFormTags.php';

/**
 * @dataSource DiscoveredHosts
 * @backup hosts
 */
class testFormTagsDiscoveredHost extends testFormTags {

	public $update_name = 'Discovered host from prototype 1';
	public $clone_name = 'Discovered host from prototype 1';
	public $remove_name = 'Discovered host from prototype 1';
	public $link = 'zabbix.php?action=host.list';
	public $saved_link = 'zabbix.php?action=host.edit&hostid=';
	
	/**
	 * Test update of Discovered Host with tags.
	 *
	 * @dataProvider getUpdateData
	 */
	public function testFormTagsDiscoveredHost_Update($data) {
		$this->checkTagsUpdate($data, 'host');
	}

	/**
	 * Test cloning of Discovered Host with tags.
	 */
	public function testFormTagsDiscoveredHost_Clone() {
		$this->executeCloning('discovered host', 'Clone');
	}
	
	/**
	 * Test full cloning of Discovered Host with tags.
	 */
	public function testFormTagsDiscoveredHost_FullClone() {
		$this->executeCloning('discovered host', 'Full clone');
	}
	
	/**
	 * Test removing tags from Discovered Host.
	 */
	public function testFormTagsDiscoveredHost_RemoveTags() {
		$this->clearTags('host');
	}
	
}


