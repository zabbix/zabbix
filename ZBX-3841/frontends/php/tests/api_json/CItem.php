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
require_once 'PHPUnit/Framework.php';

require_once(dirname(__FILE__).'/../include/class.czabbixtest.php');
require_once(dirname(__FILE__).'/../../include/hosts.inc.php');

class API_JSON_Item extends CZabbixTest
{
	public static function profile_links()
	{
		$data = array();
		$profileFields = getHostProfiles();
		$profileFieldNumbers = array_keys($profileFields);
		foreach($profileFieldNumbers as $nr){
			$data[] = array(
				$nr,
				$nr != 1  // item that has profile_link == 1 exists in test data
			);
		}
		// few non-existing fields
		$maxNr = max($profileFieldNumbers);
		$data[] = array($maxNr + 1, false);
		$data[] = array('string', false);

		return $data;
	}

	/**
	 * @dataProvider profile_links
	 */
	public function testCItem_create_profile_item($profileFieldNr, $successExpected)
	{
		DBsave_tables('items');

		$debug = null;

		// creating item
		$result = $this->api_acall(
			'item.create',
			array(
				"name" => "Item that populates field ".$profileFieldNr,
				"key_" => "key.test.pop.".$profileFieldNr,
				"hostid" => "10017",
				"type"  => "0",
                "interfaceid"  => "10017",
				"profile_link" => $profileFieldNr
			),
			&$debug
		);

		if($successExpected){
			$this->assertTrue(!array_key_exists('error', $result),"Chuck Norris: Method returned an error. Result is: ".print_r($result, true)."\nDebug: ".print_r($debug, true));
		}
		else{
			$this->assertTrue(array_key_exists('error', $result),"Chuck Norris: I was expecting call to fail, but it did not. Result is: ".print_r($result, true)."\nDebug: ".print_r($debug, true));
		}

		DBrestore_tables('items');
	}
}
?>
