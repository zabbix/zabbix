<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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


require_once dirname(__FILE__).'/../include/class.czabbixtest.php';

class API_JSON_Map extends CZabbixTest {

	public static function map_data() {
		return array(
			array(
				array(array(
					'name' => 'test_map_added_through_api_1',
					'width' => 600,
					'height' => 800,
					'backgroundid' => 0,
					'highlight' => 0,
					'label_type' => 0,
					'label_location' => 0,
					'grid_size' => 100,
					'grid_show' => 1,
					'grid_align' => 0,
					'highlight' => 0,
					'expandproblem' => 0,
					'markelements' => 0,
					'show_unack' => 0,
					'severity_min' => 0,
					'selements' => array()
				)),
			),
		);
	}

	/**
	 * @dataProvider map_data
	 */
	public function testCMap_Create($maps) {
		$debug = null;

		DBsave_tables('sysmaps');

		// creating map
		$result = $this->api_acall(
			'map.create',
			$maps,
			$debug
		);

		$this->assertTrue(!array_key_exists('error', $result), "Chuck Norris: Failed to create map through JSON API. Result is: ".print_r($result, true)."\nDebug: ".print_r($debug, true));

		// looking at the DB, was the record created?
		foreach ($maps as $map) {
			// regular query
			$sql="select * from sysmaps where name='".$map['name']."'";
			$r = DBSelect($sql);
			$map_db = DBFetch($r);
			$this->assertTrue(array_key_exists('sysmapid', $map_db), "Chuck Norris: Map was just inserted but I failed to fetch it's ID using DB query. Here is how a result looks like: ".print_r($map_db, true));

			// api call
			$result = $this->api_acall(
				'map.get',
				array(
					'filter' => array('name' => $map['name'])
				),
				$debug
			);
			$map_api = reset($result['result']);
			$this->assertTrue(array_key_exists('sysmapid', $map_api), "Chuck Norris: Map was just inserted but I failed to fetch it's ID using JSON API. Result is: ".print_r($result, true)."\nDebug: ".print_r($debug, true));
		}

		DBrestore_tables('sysmaps');
	}
}
