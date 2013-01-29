<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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
?>
<?php
require_once dirname(__FILE__).'/../include/class.cwebtest.php';

class testPageDiscoveryRules extends CWebTest {

	// Returns all Discovery Rules
	public static function allRules() {
		$sql = 'SELECT h.hostid, i.itemid'.
				' FROM hosts h, items i'.
				' WHERE i.hostid=h.hostid'.
					' AND i.flags='.ZBX_FLAG_DISCOVERY;

		return DBdata($sql);
	}

	/**
	* @dataProvider allRules
	*/
	public function testPageActionsDiscovery_MassDelete($rule) {
		$itemid = $rule['itemid'];
		$this->chooseOkOnNextConfirmation();

		DBsave_tables('triggers');

		$this->login('host_discovery.php?&hostid='.$rule['hostid']);
		$this->checkTitle('Configuration of discovery');
		$this->checkbox_select("group_itemid[$itemid]");
		$this->dropdown_select('go', 'Delete selected');
		$this->button_click('goButton');
		$this->wait();

		$this->getConfirmation();

		$this->checkTitle('Configuration of discovery');
		$this->ok('Discovery rule deleted');
		$this->ok('CONFIGURATION OF DISCOVERY RULES');

		$sql = "select * from items where itemid=$itemid AND flags=".ZBX_FLAG_DISCOVERY;
		$this->assertEquals(0, DBcount($sql));
		$sql = "select * from item_discovery where parent_itemid=$itemid";
		$this->assertEquals(0, DBcount($sql));
		$sql = "SELECT gi.gitemid from graphs_items gi, item_discovery id WHERE gi.itemid=id.itemid AND id.parent_itemid=$itemid";
		$this->assertEquals(0, DBcount($sql));
		$sql = "SELECT f.functionid from functions f, item_discovery id WHERE f.itemid=id.itemid AND id.parent_itemid=$itemid";
		$this->assertEquals(0, DBcount($sql));

		DBrestore_tables('triggers');
	}
}
?>
