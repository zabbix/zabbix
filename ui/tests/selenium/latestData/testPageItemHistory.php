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


require_once __DIR__.'/../../include/CLegacyWebTest.php';

class testPageItemHistory extends CLegacyWebTest {

	public static function checkLayoutItems() {
		return CDBHelper::getDataProvider(
			'SELECT i.itemid,i.value_type,i.key_,i.name'.
			' FROM items i,hosts h'.
			' WHERE i.hostid=h.hostid'.
				' AND h.host=\'testPageItemHistory_CheckLayout\''
		);
	}

	/**
	* @dataProvider checkLayoutItems
	*/
	public function testPageItemHistory_CheckLayout($item) {
		$this->zbxTestLogin('history.php?action=showvalues&itemids[]='.$item['itemid']);
		$this->zbxTestCheckTitle('History [refreshed every 30 sec.]');
		$this->zbxTestCheckHeader('testPageItemHistory_CheckLayout: '.$item['name']);
		switch ($item['value_type']) {
			case ITEM_VALUE_TYPE_LOG:
				if (substr($item['key_'], 0, 9) === 'eventlog[') {
					$table_titles = ['Timestamp', 'Local time', 'Source', 'Severity', 'Event ID', 'Value'];
				}
				else {
					$table_titles = ['Timestamp', 'Local time', 'Value'];
				}
				break;

			default:
				$table_titles = ['Timestamp', 'Value'];
		}
		$this->zbxTestTextPresent($table_titles);

		$view_as = $this->query('id:filter-view-as')->asDropdown()->one();
		$view_as->select('500 latest values');
		$this->zbxTestCheckTitle('History [refreshed every 30 sec.]');
		$this->zbxTestCheckHeader('testPageItemHistory_CheckLayout: '.$item['name']);

		$this->zbxTestClickWait('plaintext');
		$this->zbxTestTextPresent('testPageItemHistory_CheckLayout: '.$item['name']);

		$this->zbxTestOpen('history.php?action=showvalues&itemids[]='.$item['itemid']);
		$this->zbxTestCheckTitle('History [refreshed every 30 sec.]');
		$view_as->select('Values');
		$this->zbxTestCheckHeader('testPageItemHistory_CheckLayout: '.$item['name']);

		$this->zbxTestClickWait('plaintext');
		$this->zbxTestTextPresent('testPageItemHistory_CheckLayout: '.$item['name']);
	}
}
