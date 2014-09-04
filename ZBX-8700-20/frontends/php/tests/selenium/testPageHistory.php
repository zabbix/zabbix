<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

require_once dirname(__FILE__) . '/../include/class.cwebtest.php';

class testPageHistory extends CWebTest {
	// Returns all enabled items that belong to enabled hosts
	public static function allEnabledItems() {
		return DBdata(
				'SELECT i.itemid'.
				' FROM items i,hosts h'.
				' WHERE i.hostid=h.hostid'.
					' AND h.status='.HOST_STATUS_MONITORED.
					' AND i.status='.ITEM_STATUS_ACTIVE.
					' AND i.flags='.ZBX_FLAG_DISCOVERY_NORMAL
		);
	}

	/**
	* @dataProvider allEnabledItems
	*/

	public function testPageItems_CheckLayout($item) {

		// should switch to graph for numeric items, should check filter for history & text items
		// also different header for log items (different for eventlog items ?)
		$itemid = $item['itemid'];
		$this->zbxTestLogin("history.php?action=showvalues&itemid=$itemid");
		$this->checkTitle('History');
		// Header
		$this->zbxTestTextPresent(array('Timestamp', 'Value'));
		$this->zbxTestDropdownSelectWait('action', '500 latest values');
		$this->checkTitle('History');
		$this->zbxTestClickWait('plaintext');

		// there surely is a better way to get out of the plaintext page than just clicking 'back'...
		$this->goBack();
		$this->wait();
		$this->zbxTestDropdownSelectWait('action', 'Values');
		$this->checkTitle('History');
		$this->zbxTestClickWait('plaintext');
	}
}
