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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
require_once(dirname(__FILE__) . '/../include/class.cwebtest.php');

class testPageItems extends CWebTest{
	public function testPageItems_SimpleTest(){

		$this->login('items.php');
		$this->assertTitle('Configuration of items');
		$this->ok('CONFIGURATION OF ITEMS');
		$this->ok('Displaying');

		$this->ok(array('Wizard', 'Description', 'Triggers', 'Key', 'Interval', 'History', 'Trends', 'Type', 'Status', 'Applications', 'Error'));
		// someday should check that interval is not shown for trapper items, trends not shown for non-numeric items etc

		$this->dropdown_select('go', 'Activate selected');
		$this->dropdown_select('go', 'Disable selected');
		$this->dropdown_select('go', 'Mass update');
		$this->dropdown_select('go', 'Copy selected to ...');
		$this->dropdown_select('go', 'Clear history for selected');
		$this->dropdown_select('go', 'Delete selected');
	}
}
?>
